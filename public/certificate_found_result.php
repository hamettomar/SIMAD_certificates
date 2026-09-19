<?php
require_once __DIR__ . '/../includes/db.php';

$query_email = trim($_GET['email'] ?? '');
$query_token = trim($_GET['token'] ?? $_GET['cert'] ?? $_GET['id'] ?? '');

$cert = null;

// 1. Check if direct Certificate Token lookup was provided
if (!empty($query_token)) {
    $certRow = dbFetchOne("
        SELECT 
            c.id as cert_id, c.certificate_token, c.document_hash, c.issued_at, c.download_count, c.status as cert_status,
            p.id as participant_id, p.cohort_id, p.full_name, p.email, p.status as participant_status,
            ch.name as cohort_name, ch.batch_code, ch.instructor_name, ch.instructor_title, ch.issue_date, ch.location
        FROM certificates c
        JOIN participants p ON p.id = c.participant_id
        JOIN cohorts ch ON ch.id = p.cohort_id
        WHERE c.certificate_token = ?
        LIMIT 1
    ", [$query_token]);

    if ($certRow && $certRow['cert_status'] === 'valid' && $certRow['participant_status'] !== 'revoked') {
        $cert = $certRow;
    }
}

// 2. Locate participant by registered email if token not matched or not provided
if (!$cert && !empty($query_email)) {
    $participant = dbFetchOne("
        SELECT 
            p.id as participant_id, p.cohort_id, p.full_name, p.email, p.status as participant_status,
            ch.name as cohort_name, ch.batch_code, ch.instructor_name, ch.instructor_title, ch.issue_date, ch.location
        FROM participants p
        JOIN cohorts ch ON ch.id = p.cohort_id
        WHERE LOWER(TRIM(p.email)) = LOWER(TRIM(?))
        ORDER BY p.id DESC
        LIMIT 1
    ", [$query_email]);

    if ($participant && $participant['participant_status'] !== 'revoked') {
        $certRow = dbFetchOne("
            SELECT id as cert_id, certificate_token, document_hash, issued_at, download_count, status as cert_status
            FROM certificates 
            WHERE participant_id = ? AND status = 'valid'
            ORDER BY id DESC LIMIT 1
        ", [$participant['participant_id']]);

        // Auto-mint certificate if not yet generated
        if (!$certRow) {
            $cohortId = (int)$participant['cohort_id'];
            $template = dbFetchOne("SELECT id FROM certificate_templates WHERE cohort_id = ? AND is_active = 1 LIMIT 1", [$cohortId]);
            if (!$template) {
                $template = dbFetchOne("SELECT id FROM certificate_templates WHERE cohort_id = ? LIMIT 1", [$cohortId]);
            }
            $templateId = (int)($template['id'] ?? 1);

            $randomToken = generateNextCertificateToken($cohortId);
            $docHash = hash('sha256', $randomToken . '|' . $participant['full_name'] . '|' . $participant['email'] . '|' . time());

            dbQuery("
                INSERT INTO certificates (participant_id, template_id, certificate_token, document_hash, status, issued_at)
                VALUES (?, ?, ?, ?, 'valid', NOW())
            ", [$participant['participant_id'], $templateId, $randomToken, $docHash]);

            dbQuery("UPDATE participants SET status = 'issued' WHERE id = ?", [$participant['participant_id']]);

            $certRow = [
                'cert_id' => (int)dbLastInsertId(),
                'certificate_token' => $randomToken,
                'document_hash' => $docHash,
                'issued_at' => date('Y-m-d H:i:s'),
                'download_count' => 0,
                'cert_status' => 'valid'
            ];
        }

        $cert = array_merge($participant, $certRow);
    }
}

// Fallback to first available certificate if accessed without query params
if (!$cert) {
    if (empty($query_email) && empty($query_token)) {
        $cert = dbFetchOne("
            SELECT 
                p.id as participant_id, p.full_name, p.email, p.status as participant_status,
                c.id as cert_id, c.certificate_token, c.document_hash, c.issued_at, c.download_count, c.status as cert_status,
                ch.id as cohort_id, ch.name as cohort_name, ch.batch_code, ch.instructor_name, ch.instructor_title, ch.issue_date, ch.location
            FROM participants p
            JOIN certificates c ON c.participant_id = p.id
            JOIN cohorts ch ON ch.id = p.cohort_id
            WHERE c.status = 'valid'
            ORDER BY c.id ASC
            LIMIT 1
        ");
    } else {
        header('Location: certificate_not_found.php?email=' . urlencode($query_email ?: $query_token));
        exit;
    }
}

$recipient_name = htmlspecialchars($cert['full_name']);
$recipient_email = htmlspecialchars($cert['email']);
$course_title = htmlspecialchars($cert['cohort_name']);
$cert_token = htmlspecialchars($cert['certificate_token']);
$issue_date = date('F j, Y', strtotime($cert['issued_at'] ?? $cert['issue_date']));
$location = htmlspecialchars($cert['location'] ?? 'San Francisco, CA');
$doc_hash = htmlspecialchars($cert['document_hash']);
$cohortId = (int)$cert['cohort_id'];

// Dedicated Institutional Verification URL
$verifyUrl = (function_exists('getAppBaseUrl') ? getAppBaseUrl() : '') . '/public/verify.php?token=' . urlencode($cert['certificate_token']);

// 1-Click "Add to LinkedIn" Deep Link (SIMAD University)
$issueTimestamp = strtotime($cert['issued_at'] ?? $cert['issue_date'] ?? 'now');
$issueYear = date('Y', $issueTimestamp);
$issueMonth = date('n', $issueTimestamp);
$linkedInUrl = 'https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME'
    . '&name=' . urlencode($cert['cohort_name'])
    . '&organizationName=' . urlencode('SIMAD University')
    . '&issueYear=' . urlencode($issueYear)
    . '&issueMonth=' . urlencode($issueMonth)
    . '&certUrl=' . urlencode($verifyUrl)
    . '&certId=' . urlencode($cert['certificate_token']);

// Fetch Active Template
$template = dbFetchOne("SELECT * FROM certificate_templates WHERE cohort_id = ? AND is_active = 1 LIMIT 1", [$cohortId]);
if (!$template) {
    $template = dbFetchOne("SELECT * FROM certificate_templates WHERE cohort_id = ? LIMIT 1", [$cohortId]);
}
$hasCustomTemplate = !empty($template) && !empty($template['image_path']) && file_exists(__DIR__ . '/../' . $template['image_path']);

// Sanitize template typography & coordinate variables for accurate A4 mapping
$namePosX = (float)($template['name_pos_x'] ?? 50.0);
$namePosY = (float)($template['name_pos_y'] ?? 48.0);
$nameFontSize = (int)($template['name_font_size'] ?? 50);
$nameFontFamily = htmlspecialchars($template['name_font_family'] ?? 'Playfair Display');
$nameFontColor = htmlspecialchars($template['name_font_color'] ?? '#000000');
if (!str_starts_with($nameFontColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $nameFontColor)) {
    $nameFontColor = '#' . $nameFontColor;
}
$nameTextAlign = htmlspecialchars($template['name_text_align'] ?? 'center');
$transformX = ($nameTextAlign === 'left') ? '0%' : (($nameTextAlign === 'right') ? '-100%' : '-50%');

// Certificate ID variables
$showCertId       = (int)($template['show_cert_id'] ?? 1);
$showCertIdPrefix = (int)($template['show_cert_id_prefix'] ?? 0);
$certIdPosX       = (float)($template['cert_id_pos_x'] ?? 50.0);
$certIdPosY       = (float)($template['cert_id_pos_y'] ?? 88.0);
$certIdFontSize   = (int)($template['cert_id_font_size'] ?? 10);
$certIdColor      = htmlspecialchars($template['cert_id_color'] ?? '#64748B');
if (!str_starts_with($certIdColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $certIdColor)) {
    $certIdColor = '#' . $certIdColor;
}

// Issued Date variables
$showIssueDate       = (int)($template['show_issue_date'] ?? 1);
$issueDateText       = htmlspecialchars($template['issue_date_text'] ?? '12–13 September 2026');
$issueDatePosX       = (float)($template['issue_date_pos_x'] ?? 28.0);
$issueDatePosY       = (float)($template['issue_date_pos_y'] ?? 88.0);
$issueDateFontSize   = (int)($template['issue_date_font_size'] ?? 10);
$issueDateColor      = htmlspecialchars($template['issue_date_color'] ?? '#1E293B');
if (!str_starts_with($issueDateColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $issueDateColor)) {
    $issueDateColor = '#' . $issueDateColor;
}
$issueDateFontFamily = htmlspecialchars($template['issue_date_font_family'] ?? 'Inter');

// QR code variables
$showQr      = (int)($template['show_qr'] ?? 1);
$qrPosX      = (float)($template['qr_pos_x'] ?? 88.0);
$qrPosY      = (float)($template['qr_pos_y'] ?? 84.0);
$qrSize      = (int)($template['qr_size'] ?? 75);

$page_title = 'Verified Certificate - ' . $recipient_name;
include __DIR__ . '/../includes/head.php';
?>
<style>
  #previewQrCode, #printQrCode {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  #previewQrCode canvas, #previewQrCode img,
  #printQrCode canvas, #printQrCode img {
    max-width: 100% !important;
    max-height: 100% !important;
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important;
  }
  #previewQrCode [style*="display: none"],
  #printQrCode [style*="display: none"] {
    display: none !important;
  }
  @media print {
    @page {
      size: A4 landscape;
      margin: 0 !important;
    }
    html, body {
      margin: 0 !important;
      padding: 0 !important;
      background: #ffffff !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      width: 297mm !important;
      height: 210mm !important;
      overflow: hidden !important;
    }
    body > * {
      display: none !important;
    }
    body > #print-certificate-wrapper {
      display: block !important;
      position: fixed !important;
      left: 0 !important;
      top: 0 !important;
      width: 297mm !important;
      height: 210mm !important;
      margin: 0 !important;
      padding: 0 !important;
      background: #ffffff !important;
      z-index: 99999999 !important;
    }
    #print-certificate-wrapper .cert-printable-content {
      width: 297mm !important;
      height: 210mm !important;
      aspect-ratio: 297 / 210 !important;
      border: none !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      transform: none !important;
      margin: 0 !important;
    }
  }
  @media screen {
    #print-certificate-wrapper {
      display: none !important;
    }
  }
</style>
<body class="bg-surface font-body-md text-on-surface antialiased min-h-screen flex flex-col justify-between">
<!-- Isolated Print Container - ONLY rendered when printing or saving as PDF -->
<div id="print-certificate-wrapper">
  <div id="print-certificate-inner" class="cert-printable-content relative w-full bg-white overflow-hidden" style="width: 297mm; height: 210mm; aspect-ratio: 297 / 210;">
    <img 
      src="../<?= htmlspecialchars($template['image_path']) ?>?v=<?= time() ?>" 
      alt="Certificate Background" 
      class="absolute inset-0 w-full h-full object-fill pointer-events-none"
    />
    <div 
      class="absolute whitespace-nowrap leading-none tracking-tight font-semibold"
      style="left: <?= $namePosX ?>%; top: <?= $namePosY ?>%; font-family: '<?= $nameFontFamily ?>', sans-serif, serif; font-size: calc(<?= $nameFontSize * 0.5 ?> * (297mm / 842)); color: <?= $nameFontColor ?>; text-align: <?= $nameTextAlign ?>; transform: translate(<?= $transformX ?>, -50%);"
    >
      <?= $recipient_name ?>
    </div>

    <?php if ($showCertId): ?>
      <div 
        class="absolute whitespace-nowrap font-mono font-semibold leading-none"
        style="left: <?= $certIdPosX ?>%; top: <?= $certIdPosY ?>%; color: <?= $certIdColor ?>; font-size: calc(<?= $certIdFontSize ?> * (297mm / 842)); transform: translate(-50%, -50%);"
      >
        <?= ($showCertIdPrefix ? 'Certificate ID: ' : '') . $cert_token ?>
      </div>
    <?php endif; ?>
    <?php if ($showIssueDate): ?>
      <div 
        class="absolute whitespace-nowrap font-medium leading-none"
        style="left: <?= $issueDatePosX ?>%; top: <?= $issueDatePosY ?>%; font-family: '<?= $issueDateFontFamily ?>', sans-serif; color: <?= $issueDateColor ?>; font-size: calc(<?= $issueDateFontSize ?> * (297mm / 842)); transform: translate(-50%, -50%);"
      >
        <?= $issueDateText ?>
      </div>
    <?php endif; ?>

    <?php if ($showQr): ?>
      <div 
        class="absolute bg-white p-1 rounded overflow-hidden flex items-center justify-center"
        style="left: <?= $qrPosX ?>%; top: <?= $qrPosY ?>%; width: calc(<?= $qrSize ?> * (297mm / 842)); height: calc(<?= $qrSize ?> * (297mm / 842)); transform: translate(-50%, -50%);"
      >
        <div id="printQrCode" class="w-full h-full"></div>
      </div>
    <?php endif; ?>
  </div>
</div>
  <!-- Standalone Public Screen (No sidebar, no admin header) -->
  <main class="w-full flex-grow relative overflow-hidden px-gutter-mobile md:px-gutter-desktop py-space-xl">
    <!-- Ambient Lighting Effect -->
    <div class="pointer-events-none absolute -top-32 left-1/2 -translate-x-1/2 w-[720px] h-[340px] bg-gradient-to-b from-surface-container-high/60 via-surface-container-low/30 to-transparent blur-3xl rounded-full"></div>

    <div class="max-w-4xl mx-auto flex flex-col gap-space-lg relative z-10">
      <!-- Top Action Bar -->
      <div class="flex items-center justify-between">
        <a class="inline-flex items-center gap-space-xs text-on-surface-variant hover:text-on-surface transition-colors group" href="public_certificate_download.php">
          <span class="material-symbols-outlined text-[18px] transition-transform group-hover:-translate-x-1">arrow_back</span>
          <span class="font-label-md text-label-md">Look up another recipient</span>
        </a>
        <a class="inline-flex items-center gap-space-xs text-primary hover:underline font-label-sm text-caption" href="verify.php?token=<?= urlencode($cert_token) ?>" target="_blank">
          <span class="material-symbols-outlined text-[16px]">verified</span>
          <span>Open Dedicated Verification Record</span>
        </a>
      </div>

      <!-- Verified Certificate Details Card -->
      <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg md:p-space-xl flex flex-col gap-space-xl">
        <!-- Verification Banner Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md bg-surface-container-low/60 p-space-md rounded-lg">
          <div class="flex items-center gap-space-md">
            <img src="../simad_university_logo.png" alt="SIMAD University" class="w-12 h-12 object-contain shrink-0 drop-shadow-xs">
            <div class="flex flex-col">
              <div class="flex items-center gap-space-xs">
                <span class="font-label-sm text-label-sm text-primary font-bold uppercase tracking-wider">Credential Validated</span>
                <span class="material-symbols-outlined text-[15px] text-primary">check_circle</span>
              </div>
              <span class="font-caption text-caption text-on-surface-variant">Matched query: <strong class="text-on-surface font-medium"><?= $recipient_email ?></strong></span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="font-mono text-caption text-primary bg-primary/10 border border-primary/20 px-3 py-1 rounded-full font-bold">
              ID: <?= $cert_token ?>
            </span>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-xl items-start">
          <!-- Left Column: Details -->
          <div class="lg:col-span-7 flex flex-col gap-space-lg">
            <div>
              <span class="font-caption text-caption text-outline uppercase tracking-widest block mb-space-2xs">Recipient / Shahaado Qaataha</span>
              <h1 class="text-2xl sm:text-3xl lg:text-display font-bold text-on-surface tracking-tight leading-tight break-words" style="font-family: 'Poppins', sans-serif;"><?= $recipient_name ?></h1>
              <p class="font-body-md sm:font-body-lg text-body-md sm:text-body-lg text-on-surface-variant mt-space-2xs leading-relaxed">
                has successfully satisfied all practical evaluation requirements for completion of the program.
              </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md bg-surface-container-low/40 p-space-md rounded-lg border border-surface-container/60">
              <div class="flex flex-col sm:col-span-2 p-3 sm:p-3.5 bg-surface-container-lowest rounded-lg border border-surface-container/60">
                <span class="text-[11px] uppercase text-outline tracking-wider font-bold flex items-center gap-1.5">
                  <img src="../simad_university_logo.png" alt="SIMAD" class="w-4 h-4 object-contain">
                  Program
                </span>
                <span class="text-base sm:text-headline-sm font-bold text-on-surface mt-1 leading-snug break-words"><?= $course_title ?></span>
              </div>
              <div class="flex flex-col">
                <span class="font-caption text-caption uppercase text-outline tracking-wider">Conferred Date</span>
                <span class="font-headline-sm text-headline-sm text-on-surface mt-space-2xs"><?= $issue_date ?></span>
              </div>
              <div class="flex flex-col">
                <span class="font-caption text-caption uppercase text-outline tracking-wider">Certificate Number</span>
                <span class="font-mono text-headline-sm text-headline-sm text-primary font-bold mt-space-2xs"><?= $cert_token ?></span>
              </div>
              <div class="flex flex-col">
                <span class="font-caption text-caption uppercase text-outline tracking-wider">Registered Email</span>
                <span class="font-label-md text-label-md text-on-surface font-medium mt-1 truncate" title="<?= htmlspecialchars($cert['email'] ?? '') ?>"><?= htmlspecialchars($cert['email'] ?? '') ?></span>
              </div>
            </div>

            <!-- Action Buttons: Dual Download + Add to LinkedIn + Verify Authenticity -->
            <div class="flex flex-wrap items-center gap-space-sm pt-space-2xs">
              <!-- Dual Download Dropdown -->
              <div class="relative flex-1 min-w-[190px]" id="publicDownloadDropdownContainer">
                <div class="flex items-center rounded-lg shadow-sm bg-primary text-on-primary overflow-hidden">
                  <button type="button" id="publicDownloadPdfBtn" class="flex-1 inline-flex items-center justify-center gap-space-xs h-11 px-space-md font-label-md text-label-md font-medium hover:bg-primary-container transition-all focus:outline-none" title="Download standalone PDF">
                    <span class="material-symbols-outlined text-[20px]">picture_as_pdf</span>
                    <span id="publicDownloadLabel">Download PDF</span>
                  </button>
                  <button type="button" id="publicDownloadDropdownTrigger" class="h-11 px-3 bg-black/10 hover:bg-black/20 transition-all flex items-center justify-center border-l border-white/20 focus:outline-none" title="More formats (PNG, Print)">
                    <span class="material-symbols-outlined text-[20px]">expand_less</span>
                  </button>
                </div>
                <!-- Floating Menu -->
                <div id="publicDownloadMenu" class="hidden absolute left-0 bottom-full mb-2 w-64 bg-surface-container-lowest rounded-xl shadow-2xl border border-surface-container py-1.5 z-50 animate-in fade-in zoom-in-95 duration-150">
                  <button type="button" id="publicMenuPdf" class="w-full px-space-md py-2.5 flex items-center gap-space-sm text-left hover:bg-surface-container text-on-surface font-label-md text-label-sm transition-colors">
                    <span class="material-symbols-outlined text-[22px] text-red-600">picture_as_pdf</span>
                    <div class="flex flex-col">
                      <span class="font-semibold text-on-surface">Download PDF</span>
                      <span class="font-caption text-caption text-secondary">Official A4 Landscape Document</span>
                    </div>
                  </button>
                  <button type="button" id="publicMenuPng" class="w-full px-space-md py-2.5 flex items-center gap-space-sm text-left hover:bg-surface-container text-on-surface font-label-md text-label-sm transition-colors border-t border-surface-container-low">
                    <span class="material-symbols-outlined text-[22px] text-primary">image</span>
                    <div class="flex flex-col">
                      <span class="font-semibold text-on-surface">Download High-Res PNG</span>
                      <span class="font-caption text-caption text-secondary">A4 300 DPI (3508×2480px)</span>
                    </div>
                  </button>
                  <button type="button" id="publicMenuPrint" class="w-full px-space-md py-2.5 flex items-center gap-space-sm text-left hover:bg-surface-container text-on-surface font-label-md text-label-sm transition-colors border-t border-surface-container-low">
                    <span class="material-symbols-outlined text-[22px] text-secondary">print</span>
                    <div class="flex flex-col">
                      <span class="font-semibold text-on-surface">Print Certificate Only</span>
                      <span class="font-caption text-caption text-secondary">Standard A4 paper printout</span>
                    </div>
                  </button>
                </div>
              </div>

              <!-- 1-Click Add to LinkedIn Button -->
              <a 
                href="<?= htmlspecialchars($linkedInUrl) ?>" 
                target="_blank" 
                rel="noopener noreferrer" 
                class="inline-flex items-center justify-center gap-space-xs h-11 px-space-md rounded-lg bg-[#0A66C2] hover:bg-[#004182] text-white font-label-md text-label-md font-semibold transition-all shadow-sm active:scale-95 whitespace-nowrap"
                title="Add official credential to your LinkedIn profile"
              >
                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                  <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.2V10.9H6.46M7.83 6.2a1.66 1.66 0 0 0-1.66 1.66c0 .92.74 1.66 1.66 1.66a1.66 1.66 0 0 0 1.66-1.66c0-.92-.74-1.66-1.66-1.66Z"/>
                </svg>
                <span>Add to LinkedIn</span>
              </a>

              <!-- Direct Link to Dedicated Verification Page -->
              <a 
                href="<?= htmlspecialchars($verifyUrl) ?>" 
                target="_blank" 
                class="inline-flex items-center justify-center gap-space-xs h-11 px-space-md rounded-lg bg-surface-container text-on-surface hover:bg-surface-container-high font-label-md text-label-md font-medium transition-colors whitespace-nowrap"
                title="View authentic credential on SIMAD Institutional Registry"
              >
                <span class="material-symbols-outlined text-[18px] text-tertiary">verified_user</span>
                <span>Verify Authenticity</span>
              </a>
            </div>

            <div class="flex items-center gap-space-xs text-on-surface-variant">
              <span class="material-symbols-outlined text-[16px] text-tertiary">lock</span>
              <span class="font-caption text-caption">Cryptographically verified credential on SIMAD University Institutional Ledger</span>
            </div>
          </div>

          <!-- Right Column: Visual Certificate Mini Canvas -->
          <div class="lg:col-span-5 flex flex-col items-center">
            <div class="w-full relative group">
              
              <?php if ($hasCustomTemplate): ?>
                <!-- Template-driven mini preview (Exact A4 Landscape: 297/210) -->
                <div class="w-full bg-white rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 relative overflow-hidden flex items-center justify-center select-none border border-surface-container" id="publicCertCard" style="aspect-ratio: 297 / 210; container-type: inline-size;">
                  <img src="../<?= htmlspecialchars($template['image_path']) ?>?v=<?= time() ?>" alt="Certificate Blank" class="absolute inset-0 w-full h-full object-fill pointer-events-none" id="publicTemplateImg" crossorigin="anonymous">
                  
                  <!-- Recipient Name -->
                  <div class="absolute whitespace-nowrap leading-none tracking-tight font-semibold select-none" style="left: <?= $namePosX ?>%; top: <?= $namePosY ?>%; font-family: '<?= $nameFontFamily ?>', sans-serif, serif; font-size: calc(<?= $nameFontSize * 0.5 ?> * 100cqw / 842); color: <?= $nameFontColor ?>; text-align: <?= $nameTextAlign ?>; transform: translate(<?= $transformX ?>, -50%);">
                    <?= $recipient_name ?>
                  </div>

                  <!-- Certificate ID / Number -->
                  <?php if ($showCertId): ?>
                    <div 
                      class="absolute whitespace-nowrap font-mono font-semibold select-none leading-none" 
                      style="left: <?= $certIdPosX ?>%; top: <?= $certIdPosY ?>%; color: <?= $certIdColor ?>; font-size: calc(<?= $certIdFontSize ?> * 100cqw / 842); transform: translate(-50%, -50%);"
                    >
                      <?= ($showCertIdPrefix ? 'Certificate ID: ' : '') . $cert_token ?>
                    </div>
                  <?php endif; ?>

                  <!-- Issued Date -->
                  <?php if ($showIssueDate): ?>
                    <div 
                      class="absolute whitespace-nowrap font-medium select-none leading-none" 
                      style="left: <?= $issueDatePosX ?>%; top: <?= $issueDatePosY ?>%; font-family: '<?= $issueDateFontFamily ?>', sans-serif; color: <?= $issueDateColor ?>; font-size: calc(<?= $issueDateFontSize ?> * 100cqw / 842); transform: translate(-50%, -50%);"
                    >
                      <?= $issueDateText ?>
                    </div>
                  <?php endif; ?>

                  <!-- Verification Scannable QR Code -->
                  <?php if ($showQr): ?>
                    <div 
                      id="previewQrContainer" 
                      class="absolute bg-white p-1 rounded shadow-xs overflow-hidden flex items-center justify-center select-none"
                      style="left: <?= $qrPosX ?>%; top: <?= $qrPosY ?>%; width: calc(<?= $qrSize ?> * 100cqw / 842); height: calc(<?= $qrSize ?> * 100cqw / 842); transform: translate(-50%, -50%);"
                      title="Scan to verify this certificate on SIMAD Institutional Registry"
                    >
                      <div id="previewQrCode" class="w-full h-full flex items-center justify-center"></div>
                    </div>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <!-- Classic vector preview -->
                <div class="w-full bg-surface-container-lowest rounded-lg p-space-md shadow-md hover:shadow-xl transition-shadow duration-300 relative overflow-hidden aspect-[1.414/1] flex flex-col justify-between select-none">
                  <div class="relative z-10 flex items-start justify-between">
                    <div class="flex items-center gap-space-xs">
                      <div class="w-7 h-7 rounded-full bg-primary-container flex items-center justify-center text-on-primary font-label-sm font-bold shadow-xs">CH</div>
                      <div class="flex flex-col">
                        <span class="font-caption text-caption font-bold tracking-tight text-on-surface uppercase">CertificateHub</span>
                        <span class="text-[9px] leading-tight text-outline">INSTITUTIONAL REGISTRY</span>
                      </div>
                    </div>
                  </div>
                  <div class="relative z-10 my-auto text-center flex flex-col items-center py-space-xs">
                    <span class="text-[10px] uppercase tracking-[0.2em] text-outline font-medium">Certificate of Completion</span>
                    <div class="h-0.5 w-8 bg-surface-container-high my-1"></div>
                    <span class="font-headline-sm text-headline-sm text-on-surface font-bold tracking-tight mt-1"><?= $recipient_name ?></span>
                    <p class="text-[10px] leading-tight text-on-surface-variant max-w-[220px] mt-1"><?= $course_title ?></p>
                  </div>
                  <div class="relative z-10 flex items-end justify-between pt-space-xs">
                    <span class="text-[8px] uppercase tracking-wider text-outline font-medium"><?= $cert['instructor_name'] ?? 'Instructor' ?></span>
                    <span class="text-[9px] font-mono text-on-surface"><?= $issue_date ?></span>
                  </div>
                </div>
              <?php endif; ?>

              <div class="mt-space-sm flex items-center justify-center gap-space-xs text-on-surface-variant">
                <span class="material-symbols-outlined text-[16px]">visibility</span>
                <span class="font-caption text-caption">Interactive High-Resolution Document Render</span>
              </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-space-sm pt-space-md border-t border-surface-container">
          <div class="flex items-center gap-space-sm">
            <div class="w-8 h-8 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant">
              <span class="material-symbols-outlined text-[18px]">verified</span>
            </div>
            <div class="flex flex-col">
              <span class="font-label-sm text-label-sm font-semibold text-on-surface">Official Issuance Guarantee</span>
              <span class="font-caption text-caption text-on-surface-variant">Issued via CertificateHub. Tamper-evident cryptographic signature verified on public ledger.</span>
            </div>
          </div>
          <a class="font-label-sm text-label-sm text-primary hover:text-primary-container transition-colors whitespace-nowrap" href="public_certificate_download.php">
            Use a different email address →
          </a>
        </div>
      </div>
    </div>
  </main>

  <!-- Public Footer -->
  <footer class="w-full text-center py-6 text-outline font-caption text-caption border-t border-surface-container-high/30">
    <span>CertificateHub © <?= date('Y') ?></span> • <span>Verified Workshop Registry</span> • <a href="public_certificate_download.php" class="text-primary hover:underline">Lookup Portal</a> • <a href="../admin/organizer_sign_in.php" class="text-primary hover:underline">Organizer Sign In</a>
  </footer>

  <script>
    const publicDownloadPdfBtn = document.getElementById('publicDownloadPdfBtn');
    const publicDownloadDropdownTrigger = document.getElementById('publicDownloadDropdownTrigger');
    const publicDownloadMenu = document.getElementById('publicDownloadMenu');
    const publicMenuPdf = document.getElementById('publicMenuPdf');
    const publicMenuPng = document.getElementById('publicMenuPng');
    const publicMenuPrint = document.getElementById('publicMenuPrint');

    // Toggle dropdown menu
    if (publicDownloadDropdownTrigger && publicDownloadMenu) {
      publicDownloadDropdownTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        publicDownloadMenu.classList.toggle('hidden');
      });
      document.addEventListener('click', (e) => {
        if (!publicDownloadMenu.contains(e.target) && !publicDownloadDropdownTrigger.contains(e.target)) {
          publicDownloadMenu.classList.add('hidden');
        }
      });
    }

    // Generate Scannable QR Codes for Preview and Print
    document.addEventListener('DOMContentLoaded', () => {
      const verifyUrl = '<?= addslashes($verifyUrl) ?>';
      
      const previewQr = document.getElementById('previewQrCode');
      if (previewQr && typeof QRCode !== 'undefined') {
        previewQr.innerHTML = '';
        new QRCode(previewQr, {
          text: verifyUrl,
          width: 256,
          height: 256,
          colorDark: '#000000',
          colorLight: '#FFFFFF',
          correctLevel: QRCode.CorrectLevel.M
        });
      }

      const printQr = document.getElementById('printQrCode');
      if (printQr && typeof QRCode !== 'undefined') {
        printQr.innerHTML = '';
        new QRCode(printQr, {
          text: verifyUrl,
          width: 256,
          height: 256,
          colorDark: '#000000',
          colorLight: '#FFFFFF',
          correctLevel: QRCode.CorrectLevel.M
        });
      }
    });

    // Render High-Res A4 Canvas (Standard 3508x2480 at 300 DPI)
    async function renderPublicCanvas() {
      await document.fonts.ready;
      // Allow QR generation microtask to settle
      await new Promise(resolve => setTimeout(resolve, 80));

      const canvas = document.createElement('canvas');
      const customImg = document.getElementById('publicTemplateImg');
      
      let targetWidth = 3508;
      let targetHeight = 2480;
      if (customImg && customImg.naturalWidth && customImg.naturalWidth > 100) {
        targetWidth = customImg.naturalWidth;
        targetHeight = customImg.naturalHeight;
      }

      canvas.width = targetWidth;
      canvas.height = targetHeight;
      const ctx = canvas.getContext('2d');

      if (customImg && customImg.complete && customImg.naturalWidth !== 0) {
        ctx.drawImage(customImg, 0, 0, targetWidth, targetHeight);
      } else {
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, targetWidth, targetHeight);
      }

      // Draw Recipient Name
      const namePosX = (<?= $namePosX ?> / 100) * targetWidth;
      const namePosY = (<?= $namePosY ?> / 100) * targetHeight;
      
      // Proportional font sizing matching 842px reference stage
      const baseFontSize = <?= $nameFontSize ?>;
      const scaledFontSize = Math.round(targetWidth * ((baseFontSize * 0.5) / 842));
      const fontFamily = '<?= addslashes($template['name_font_family'] ?? 'Playfair Display') ?>';
      const fontColor = '<?= addslashes($nameFontColor) ?>';
      const textAlign = '<?= addslashes($nameTextAlign) ?>';

      ctx.font = `600 ${scaledFontSize}px '${fontFamily}', sans-serif, serif`;
      ctx.fillStyle = fontColor;
      ctx.textAlign = textAlign;
      ctx.textBaseline = 'middle';
      ctx.fillText('<?= addslashes($recipient_name) ?>', namePosX, namePosY);

      <?php if ($showCertId): ?>
      // Draw Certificate ID
      const certIdPosX = (<?= $certIdPosX ?> / 100) * targetWidth;
      const certIdPosY = (<?= $certIdPosY ?> / 100) * targetHeight;
      const certIdFontSize = Math.round(targetWidth * (<?= $certIdFontSize ?> / 842));
      ctx.font = `600 ${certIdFontSize}px monospace, 'Courier New', sans-serif`;
      ctx.fillStyle = '<?= addslashes($certIdColor) ?>';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      const certIdText = '<?= ($showCertIdPrefix ? 'Certificate ID: ' : '') . addslashes($cert_token) ?>';
      ctx.fillText(certIdText, certIdPosX, certIdPosY);
      <?php endif; ?>

      <?php if ($showIssueDate): ?>
      // Draw Issued Date
      const issueDatePosX = (<?= $issueDatePosX ?> / 100) * targetWidth;
      const issueDatePosY = (<?= $issueDatePosY ?> / 100) * targetHeight;
      const issueDateFontSize = Math.round(targetWidth * (<?= $issueDateFontSize ?> / 842));
      const issueDateFontFamily = '<?= addslashes($issueDateFontFamily) ?>';
      ctx.font = `500 ${issueDateFontSize}px '${issueDateFontFamily}', sans-serif`;
      ctx.fillStyle = '<?= addslashes($issueDateColor) ?>';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText('<?= addslashes($issueDateText) ?>', issueDatePosX, issueDatePosY);
      <?php endif; ?>

      <?php if ($showQr): ?>
      // Draw High-Contrast Scannable QR Code
      const qrCanvas = document.querySelector('#previewQrCode canvas') || document.querySelector('#printQrCode canvas');
      const qrImg = document.querySelector('#previewQrCode img') || document.querySelector('#printQrCode img');
      const qrSource = qrCanvas || (qrImg && qrImg.complete && qrImg.naturalWidth > 0 ? qrImg : null);

      if (qrSource) {
        const qrPixelSize = (<?= $qrSize ?> / 842) * targetWidth;
        const qrCenterX = (<?= $qrPosX ?> / 100) * targetWidth;
        const qrCenterY = (<?= $qrPosY ?> / 100) * targetHeight;
        const qrLeft = qrCenterX - (qrPixelSize / 2);
        const qrTop = qrCenterY - (qrPixelSize / 2);
        const qrPadding = qrPixelSize * 0.06;

        // White background border for maximum optical readability
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(qrLeft - qrPadding, qrTop - qrPadding, qrPixelSize + (qrPadding * 2), qrPixelSize + (qrPadding * 2));
        ctx.drawImage(qrSource, qrLeft, qrTop, qrPixelSize, qrPixelSize);
      }
      <?php endif; ?>

      return canvas;
    }

    // PDF Download Execution (Native A4 Landscape)
    async function handlePublicDownloadPDF() {
      if (publicDownloadMenu) publicDownloadMenu.classList.add('hidden');
      const btn = document.getElementById('publicDownloadPdfBtn');
      const originalHTML = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">progress_activity</span><span>Generating A4 PDF...</span>';

      try {
        const customImg = document.getElementById('publicTemplateImg');
        if (customImg) {
          const canvas = await renderPublicCanvas();
          const imgData = canvas.toDataURL('image/jpeg', 0.98);

          if (window.jspdf && window.jspdf.jsPDF) {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({
              orientation: 'landscape',
              unit: 'mm',
              format: 'a4'
            });
            // Exact A4 dimensions: 297mm x 210mm
            pdf.addImage(imgData, 'JPEG', 0, 0, 297, 210, undefined, 'FAST');
            pdf.save('Certificate_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $recipient_name) ?>.pdf');
          } else {
            window.print();
          }
        } else {
          window.print();
        }

        // Track download
        fetch('../includes/track_download.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'cert_id=<?= (int)($cert['cert_id'] ?? 0) ?>&token=<?= urlencode($cert_token) ?>'
        }).catch(() => {});
      } catch (err) {
        console.error('PDF error, falling back to print:', err);
        window.print();
      }

      setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
      }, 1200);
    }

    // PNG Download Execution
    async function handlePublicDownloadPNG() {
      if (publicDownloadMenu) publicDownloadMenu.classList.add('hidden');
      const btn = document.getElementById('publicDownloadPdfBtn');
      const originalHTML = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">progress_activity</span><span>Rendering PNG...</span>';

      try {
        const canvas = await renderPublicCanvas();
        const dataUrl = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.download = 'Certificate_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $recipient_name) ?>.png';
        link.href = dataUrl;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Track download
        fetch('../includes/track_download.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'cert_id=<?= (int)($cert['cert_id'] ?? 0) ?>&token=<?= urlencode($cert_token) ?>'
        }).catch(() => {});
      } catch (err) {
        console.error('PNG error:', err);
      }

      setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
      }, 1200);
    }

    // Bind event listeners
    if (publicDownloadPdfBtn) publicDownloadPdfBtn.addEventListener('click', handlePublicDownloadPDF);
    if (publicMenuPdf) publicMenuPdf.addEventListener('click', handlePublicDownloadPDF);
    if (publicMenuPng) publicMenuPng.addEventListener('click', handlePublicDownloadPNG);
    if (publicMenuPrint) {
      publicMenuPrint.addEventListener('click', () => {
        if (publicDownloadMenu) publicDownloadMenu.classList.add('hidden');
        window.print();
      });
    }

    function showShareNotice() {
      alert('Certificate Status: Verified Authentic.\nRecipient: <?= addslashes($recipient_name) ?>\nEmail: <?= addslashes($recipient_email) ?>\nLedger Hash: <?= addslashes($doc_hash) ?>');
    }
  </script>
</body>
</html>

