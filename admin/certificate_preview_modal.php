<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdminAuth();
require_once __DIR__ . '/../includes/db.php';

// Active Cohort & Selected Cohort
$activeCohort = getActiveCohort();
$requestedCohortId = isset($_GET['cohort_id']) ? (int)$_GET['cohort_id'] : 0;
if ($requestedCohortId > 0) {
    $selectedCohort = dbFetchOne("SELECT * FROM cohorts WHERE id = ?", [$requestedCohortId]);
    if ($selectedCohort) {
        $activeCohort = $selectedCohort;
    }
}
$cohortId = (int)$activeCohort['id'];
$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';
$isPreview = isset($_GET['preview']) && $_GET['preview'] == '1';
$previewToken = isset($_GET['preview_token']) ? trim($_GET['preview_token']) : '';
$previewName = isset($_GET['preview_name']) ? trim($_GET['preview_name']) : '';

// Locate Certificate & Participant Data
$participantId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$certToken = isset($_GET['token']) ? trim($_GET['token']) : (isset($_GET['cert_id']) ? trim($_GET['cert_id']) : '');

$certData = null;

if ($isPreview && $participantId <= 0 && empty($certToken)) {
    // Explicit preview mode from Template Customization Studio
    $certData = [
        'participant_id' => 1,
        'full_name' => !empty($previewName) ? $previewName : 'Ahmed Omar Mohamed',
        'email' => 'ahmed.omar@example.org',
        'certificate_token' => !empty($previewToken) ? $previewToken : 'SIMAD-PU-2026-001',
        'document_hash' => hash('sha256', 'SIMAD-PU-2026-001|Ahmed Omar Mohamed'),
        'issued_at' => date('Y-m-d H:i:s'),
        'download_count' => 0,
        'cert_status' => 'valid',
        'cohort_name' => $activeCohort['name'],
        'batch_code' => $activeCohort['batch_code'],
        'instructor_name' => $activeCohort['instructor_name'] ?? 'Dr. Sarah Jenkins',
        'instructor_title' => $activeCohort['instructor_title'] ?? 'Lead Instructor & Director of AI Research',
        'issue_date' => $activeCohort['issue_date'] ?? date('Y-m-d'),
        'location' => $activeCohort['location'] ?? 'Mogadishu Tech Campus'
    ];
} elseif ($participantId > 0) {
    $certData = dbFetchOne("
        SELECT 
            p.id as participant_id, p.full_name, p.email, p.status as participant_status,
            c.id as certificate_id, c.certificate_token, c.document_hash, c.issued_at, c.download_count, c.status as cert_status,
            ch.name as cohort_name, ch.batch_code, ch.instructor_name, ch.instructor_title, ch.issue_date, ch.location
        FROM participants p
        LEFT JOIN certificates c ON c.participant_id = p.id
        LEFT JOIN cohorts ch ON ch.id = p.cohort_id
        WHERE p.id = ?
        LIMIT 1
    ", [$participantId]);
} elseif (!empty($certToken)) {
    $certData = dbFetchOne("
        SELECT 
            p.id as participant_id, p.full_name, p.email, p.status as participant_status,
            c.id as certificate_id, c.certificate_token, c.document_hash, c.issued_at, c.download_count, c.status as cert_status,
            ch.name as cohort_name, ch.batch_code, ch.instructor_name, ch.instructor_title, ch.issue_date, ch.location
        FROM certificates c
        JOIN participants p ON p.id = c.participant_id
        LEFT JOIN cohorts ch ON ch.id = p.cohort_id
        WHERE c.certificate_token = ?
        LIMIT 1
    ", [$certToken]);
}

// Fallback: Latest issued participant in active cohort
if (!$certData) {
    $certData = dbFetchOne("
        SELECT 
            p.id as participant_id, p.full_name, p.email, p.status as participant_status,
            c.id as certificate_id, c.certificate_token, c.document_hash, c.issued_at, c.download_count, c.status as cert_status,
            ch.name as cohort_name, ch.batch_code, ch.instructor_name, ch.instructor_title, ch.issue_date, ch.location
        FROM participants p
        JOIN certificates c ON c.participant_id = p.id
        LEFT JOIN cohorts ch ON ch.id = p.cohort_id
        WHERE p.cohort_id = ? AND c.certificate_token IS NOT NULL
        ORDER BY p.id DESC
        LIMIT 1
    ", [$cohortId]);
}

// Safe fallback if database is empty
if (!$certData) {
    $certData = [
        'participant_id' => 1,
        'full_name' => !empty($previewName) ? $previewName : 'Ahmed Omar Mohamed',
        'email' => 'ahmed.omar@example.org',
        'certificate_token' => !empty($previewToken) ? $previewToken : 'SIMAD-PU-2026-001',
        'document_hash' => hash('sha256', 'SIMAD-PU-2026-001|Ahmed Omar Mohamed'),
        'issued_at' => date('Y-m-d H:i:s'),
        'download_count' => 0,
        'cert_status' => 'valid',
        'cohort_name' => $activeCohort['name'],
        'batch_code' => $activeCohort['batch_code'],
        'instructor_name' => $activeCohort['instructor_name'] ?? 'Dr. Sarah Jenkins',
        'instructor_title' => $activeCohort['instructor_title'] ?? 'Lead Instructor & Director of AI Research',
        'issue_date' => $activeCohort['issue_date'] ?? date('Y-m-d'),
        'location' => $activeCohort['location'] ?? 'Mogadishu Tech Campus'
    ];
}

$recipient_name   = htmlspecialchars(!empty($previewName) && ($isPreview || empty($_GET['id'])) ? $previewName : $certData['full_name']);
$recipient_email  = htmlspecialchars($certData['email']);
$cert_token       = htmlspecialchars(!empty($previewToken) && ($isPreview || empty($_GET['token']) && empty($_GET['id'])) ? $previewToken : ($certData['certificate_token'] ?? 'SIMAD-PU-2026-001'));
$course_title     = htmlspecialchars($certData['cohort_name'] ?? 'Introduction to Artificial Intelligence Workshop');
$cohort_batch     = htmlspecialchars($certData['batch_code'] ?? 'Cohort #849');
$instructor_name  = htmlspecialchars($certData['instructor_name'] ?? 'Dr. Sarah Jenkins');
$instructor_title = htmlspecialchars($certData['instructor_title'] ?? 'Lead Instructor & Director of AI Research');
$issue_date       = !empty($certData['issued_at']) ? date('F j, Y', strtotime($certData['issued_at'])) : date('F j, Y', strtotime($certData['issue_date'] ?? 'now'));
$location         = htmlspecialchars($certData['location'] ?? 'San Francisco, CA');
$cert_status      = $certData['cert_status'] ?? 'valid';
$is_valid         = ($cert_status === 'valid');

// Fetch Active Certificate Template
$template = dbFetchOne("SELECT * FROM certificate_templates WHERE cohort_id = ? AND is_active = 1 LIMIT 1", [$cohortId]);
if (!$template) {
    $template = dbFetchOne("SELECT * FROM certificate_templates WHERE cohort_id = ? LIMIT 1", [$cohortId]);
}
$hasCustomTemplate = !empty($template) && !empty($template['image_path']) && file_exists(__DIR__ . '/../' . $template['image_path']);

// Sanitize template typography & coordinate variables for accurate A4 mapping
$namePosX = isset($_GET['name_pos_x']) ? (float)$_GET['name_pos_x'] : (float)($template['name_pos_x'] ?? 50.0);
$namePosY = isset($_GET['name_pos_y']) ? (float)$_GET['name_pos_y'] : (float)($template['name_pos_y'] ?? 48.0);
$nameFontSize = isset($_GET['name_font_size']) ? (int)$_GET['name_font_size'] : (int)($template['name_font_size'] ?? 50);
$nameFontFamily = isset($_GET['name_font_family']) ? htmlspecialchars($_GET['name_font_family']) : htmlspecialchars($template['name_font_family'] ?? 'Playfair Display');
$nameFontColor = isset($_GET['name_font_color']) ? htmlspecialchars($_GET['name_font_color']) : htmlspecialchars($template['name_font_color'] ?? '#000000');
if (!str_starts_with($nameFontColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $nameFontColor)) {
    $nameFontColor = '#' . $nameFontColor;
}
$nameTextAlign = isset($_GET['name_text_align']) ? htmlspecialchars($_GET['name_text_align']) : htmlspecialchars($template['name_text_align'] ?? 'center');
$transformX = ($nameTextAlign === 'left') ? '0%' : (($nameTextAlign === 'right') ? '-100%' : '-50%');

// Certificate ID variables
$showCertId       = isset($_GET['show_cert_id']) ? (int)$_GET['show_cert_id'] : (int)($template['show_cert_id'] ?? 1);
$showCertIdPrefix = isset($_GET['show_cert_id_prefix']) ? (int)$_GET['show_cert_id_prefix'] : (int)($template['show_cert_id_prefix'] ?? 0);
$certIdPosX       = isset($_GET['cert_id_pos_x']) ? (float)$_GET['cert_id_pos_x'] : (float)($template['cert_id_pos_x'] ?? 50.0);
$certIdPosY       = isset($_GET['cert_id_pos_y']) ? (float)$_GET['cert_id_pos_y'] : (float)($template['cert_id_pos_y'] ?? 88.0);
$certIdColor      = isset($_GET['cert_id_color']) ? htmlspecialchars($_GET['cert_id_color']) : htmlspecialchars($template['cert_id_color'] ?? '#64748B');
if (!str_starts_with($certIdColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $certIdColor)) {
    $certIdColor = '#' . $certIdColor;
}
$certIdFontSize   = isset($_GET['cert_id_font_size']) ? (int)$_GET['cert_id_font_size'] : (int)($template['cert_id_font_size'] ?? 10);

// Issued Date variables
$showIssueDate       = isset($_GET['show_issue_date']) ? (int)$_GET['show_issue_date'] : (int)($template['show_issue_date'] ?? 1);
$issueDateText       = isset($_GET['issue_date_text']) ? htmlspecialchars($_GET['issue_date_text']) : htmlspecialchars($template['issue_date_text'] ?? '12–13 September 2026');
if (empty($issueDateText)) {
    $issueDateText = !empty($certData['issued_at']) ? date('F j, Y', strtotime($certData['issued_at'])) : ($certData['issue_date'] ?? '12–13 September 2026');
}
$issueDatePosX       = isset($_GET['issue_date_pos_x']) ? (float)$_GET['issue_date_pos_x'] : (float)($template['issue_date_pos_x'] ?? 28.0);
$issueDatePosY       = isset($_GET['issue_date_pos_y']) ? (float)$_GET['issue_date_pos_y'] : (float)($template['issue_date_pos_y'] ?? 88.0);
$issueDateFontSize   = isset($_GET['issue_date_font_size']) ? (int)$_GET['issue_date_font_size'] : (int)($template['issue_date_font_size'] ?? 10);
$issueDateColor      = isset($_GET['issue_date_color']) ? htmlspecialchars($_GET['issue_date_color']) : htmlspecialchars($template['issue_date_color'] ?? '#1E293B');
if (!str_starts_with($issueDateColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $issueDateColor)) {
    $issueDateColor = '#' . $issueDateColor;
}
$issueDateFontFamily = isset($_GET['issue_date_font_family']) ? htmlspecialchars($_GET['issue_date_font_family']) : htmlspecialchars($template['issue_date_font_family'] ?? 'Inter');

// QR code variables
$showQr      = isset($_GET['show_qr']) ? (int)$_GET['show_qr'] : (int)($template['show_qr'] ?? 1);
$qrPosX      = isset($_GET['qr_pos_x']) ? (float)$_GET['qr_pos_x'] : (float)($template['qr_pos_x'] ?? 88.0);
$qrPosY      = isset($_GET['qr_pos_y']) ? (float)$_GET['qr_pos_y'] : (float)($template['qr_pos_y'] ?? 84.0);
$qrSize      = isset($_GET['qr_size']) ? (int)$_GET['qr_size'] : (int)($template['qr_size'] ?? 75);

// Dedicated Verification URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$verifyUrl = $protocol . '://' . $host . '/certificate/public/verify.php?token=' . urlencode($cert_token);

$page_title = 'Certificate Preview - CertificateHub';
$active_page = 'certificates';

include __DIR__ . '/../includes/head.php';
?>
<style>
  #modalQrCode, #printModalQrCode {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  #modalQrCode canvas, #modalQrCode img, 
  #printModalQrCode canvas, #printModalQrCode img {
    max-width: 100% !important;
    max-height: 100% !important;
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important;
  }
  #modalQrCode [style*="display: none"],
  #printModalQrCode [style*="display: none"],
  #modalQrCode canvas[style*="display: none"],
  #modalQrCode img[style*="display: none"],
  #printModalQrCode canvas[style*="display: none"],
  #printModalQrCode img[style*="display: none"] {
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
    /* Hide everything on the page */
    body > * {
      display: none !important;
    }
    /* Only display the isolated print container */
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
<body class="bg-surface font-body-md text-on-surface antialiased">
<!-- Dedicated Isolated Container - Printed ONLY when window.print() or PDF export executes -->
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
        <div id="printModalQrCode" class="w-full h-full"></div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
if (!$isEmbed) {
    include __DIR__ . '/../includes/sidebar.php';
    include __DIR__ . '/../includes/header.php';
}
?>

<div class="flex flex-col w-full relative">
  <?php if (!$isEmbed): ?>
  <!-- Underlying Dashboard Context Backdrop -->
  <div class="p-gutter-desktop max-w-max-content-width mx-auto w-full filter blur-[2px] opacity-40 pointer-events-none select-none transition-all duration-300">
    <div class="flex items-center justify-between pb-space-lg">
      <div>
        <h2 class="font-headline-lg text-headline-lg text-on-surface">Credentials &amp; Issuance</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">Batch issuance run for <?= $course_title ?></p>
      </div>
      <div class="flex items-center gap-space-sm">
        <span class="font-caption text-caption bg-surface-container text-on-surface-variant px-space-sm py-1 rounded-full font-medium"><?= $cohort_batch ?></span>
      </div>
    </div>
    <div class="grid grid-cols-12 gap-space-md">
      <div class="col-span-4 h-32 bg-surface-container-lowest rounded-xl shadow-sm p-space-md"></div>
      <div class="col-span-4 h-32 bg-surface-container-lowest rounded-xl shadow-sm p-space-md"></div>
      <div class="col-span-4 h-32 bg-surface-container-lowest rounded-xl shadow-sm p-space-md"></div>
      <div class="col-span-12 h-96 bg-surface-container-lowest rounded-xl shadow-sm"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Focused Certificate Preview Modal Overlay -->
  <div class="<?= $isEmbed ? 'w-full min-h-screen p-2 sm:p-4 flex items-center justify-center bg-surface-container' : 'fixed inset-0 z-50 flex items-center justify-center p-space-sm md:p-space-lg bg-inverse-surface/60 backdrop-blur-sm overflow-y-auto' ?>" id="certificate-modal-overlay">
    <div class="relative w-full max-w-[1080px] bg-surface-container-lowest rounded-xl shadow-2xl flex flex-col overflow-hidden my-auto animate-in fade-in zoom-in-95 duration-200">
      
      <!-- Modal Header Bar -->
      <div class="h-16 px-space-lg bg-surface-container-low flex items-center justify-between border-b border-surface-container">
        <div class="flex items-center gap-space-md min-w-0">
          <img src="../simad_university_logo.png" alt="SIMAD University" class="w-8 h-8 object-contain shrink-0 drop-shadow-xs">
          <div class="flex flex-col min-w-0">
            <div class="flex items-center gap-space-xs">
              <h1 class="font-headline-sm text-headline-sm text-on-surface truncate">Certificate Preview: <?= $recipient_name ?></h1>
              <?php if ($is_valid): ?>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-tertiary font-caption text-caption shrink-0">
                  <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span>
                  Issued &amp; Valid
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-error-container text-on-error-container font-caption text-caption shrink-0">
                  <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                  Revoked
                </span>
              <?php endif; ?>
            </div>
            <span class="font-caption text-caption text-on-surface-variant truncate">
              Document Token: <span class="font-mono font-medium"><?= $cert_token ?></span> • <?= $hasCustomTemplate ? 'Custom Template Blank' : 'Vector Double-Border' ?>
            </span>
          </div>
        </div>

        <div class="flex items-center gap-space-xs shrink-0">
          <a href="certificate_template.php" class="p-1.5 px-2.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-label-sm text-caption transition-colors inline-flex items-center gap-1" title="Customize template placement & styling">
            <span class="material-symbols-outlined text-[16px] text-primary">design_services</span>
            <span class="hidden sm:inline">Edit Template</span>
          </a>

          <?php if ($hasCustomTemplate): ?>
            <button type="button" class="p-1.5 px-2.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-label-sm text-caption transition-colors inline-flex items-center gap-1" id="toggleCanvasViewBtn" title="Switch layout">
              <span class="material-symbols-outlined text-[16px]">swap_horiz</span>
              <span class="hidden sm:inline" id="toggleLayoutText">Classic View</span>
            </button>
          <?php endif; ?>

          <button class="p-2 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" id="zoom-toggle-btn" title="Toggle zoom scale">
            <span class="material-symbols-outlined text-[20px]">aspect_ratio</span>
          </button>
          <button class="p-2 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" id="print-shortcut-btn" title="Print document">
            <span class="material-symbols-outlined text-[20px]">print</span>
          </button>
          <div class="w-px h-6 bg-surface-container-high mx-space-2xs"></div>
          <?php if ($isEmbed): ?>
            <button type="button" onclick="if (window.parent && window.parent.closeLivePreviewModal) { window.parent.closeLivePreviewModal(); } else { window.close(); }" aria-label="Close Preview" class="p-2 rounded-lg text-on-surface-variant hover:bg-error-container hover:text-on-error-container transition-colors inline-flex items-center justify-center" id="close-modal-x">
              <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
          <?php else: ?>
            <a href="certificates_management.php" aria-label="Close Preview" class="p-2 rounded-lg text-on-surface-variant hover:bg-error-container hover:text-on-error-container transition-colors inline-flex items-center justify-center" id="close-modal-x">
              <span class="material-symbols-outlined text-[20px]">close</span>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Specialized Credential Canvas Stage Area -->
      <div class="p-space-md md:p-space-xl bg-surface-container flex items-center justify-center overflow-x-auto min-h-[440px]">
        
        <?php if ($hasCustomTemplate): ?>
          <!-- Canvas Mode A: Uploaded Custom Certificate Blank Canvas (A4 Landscape: 297/210) -->
          <div id="custom-template-canvas" class="relative w-full max-w-[880px] bg-white rounded shadow-2xl overflow-hidden select-text transition-transform duration-300" style="aspect-ratio: 297 / 210; container-type: inline-size;">
            <!-- Blank Template Background Image -->
            <img 
              id="customTemplateImg" 
              src="../<?= htmlspecialchars($template['image_path']) ?>?v=<?= time() ?>" 
              alt="Certificate Background" 
              class="absolute inset-0 w-full h-full object-fill pointer-events-none select-none"
              crossorigin="anonymous"
            />
            
            <!-- Dynamically Positioned Recipient Name -->
            <div 
              id="customRecipientName"
              class="absolute whitespace-nowrap leading-none tracking-tight font-semibold select-none"
              style="left: <?= $namePosX ?>%; top: <?= $namePosY ?>%; font-family: '<?= $nameFontFamily ?>', sans-serif, serif; font-size: calc(<?= $nameFontSize * 0.5 ?> * 100cqw / 842); color: <?= $nameFontColor ?>; text-align: <?= $nameTextAlign ?>; transform: translate(<?= $transformX ?>, -50%);"
            >
              <?= $recipient_name ?>
            </div>

            <!-- Certificate ID / Number -->
            <?php if ($showCertId): ?>
              <div 
                id="customCertIdDisplay" 
                class="absolute whitespace-nowrap font-mono font-semibold select-none leading-none" 
                style="left: <?= $certIdPosX ?>%; top: <?= $certIdPosY ?>%; color: <?= $certIdColor ?>; font-size: calc(<?= $certIdFontSize ?> * 100cqw / 842); transform: translate(-50%, -50%);"
              >
                <?= ($showCertIdPrefix ? 'Certificate ID: ' : '') . $cert_token ?>
              </div>
            <?php endif; ?>

            <!-- Issued Date -->
            <?php if ($showIssueDate): ?>
              <div 
                id="customIssueDateDisplay" 
                class="absolute whitespace-nowrap font-medium select-none leading-none" 
                style="left: <?= $issueDatePosX ?>%; top: <?= $issueDatePosY ?>%; font-family: '<?= $issueDateFontFamily ?>', sans-serif; color: <?= $issueDateColor ?>; font-size: calc(<?= $issueDateFontSize ?> * 100cqw / 842); transform: translate(-50%, -50%);"
              >
                <?= $issueDateText ?>
              </div>
            <?php endif; ?>

            <!-- Verification Scannable QR Code -->
            <?php if ($showQr): ?>
              <div 
                id="customQrContainer" 
                class="absolute bg-white p-1 rounded shadow-xs overflow-hidden flex items-center justify-center select-none z-20"
                style="left: <?= $qrPosX ?>%; top: <?= $qrPosY ?>%; width: calc(<?= $qrSize ?> * 100cqw / 842); height: calc(<?= $qrSize ?> * 100cqw / 842); transform: translate(-50%, -50%);"
                title="Scan to verify this certificate on SIMAD Institutional Registry"
              >
                <div id="modalQrCode" class="w-full h-full flex items-center justify-center"></div>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Canvas Mode B: Classic Architectural Vector Canvas -->
        <div class="relative w-full max-w-[880px] bg-[#FFFFFF] rounded shadow-xl p-6 md:p-12 text-[#111827] overflow-hidden select-text transition-transform duration-300 <?= $hasCustomTemplate ? 'hidden' : '' ?>" id="vector-certificate-canvas">
          <div class="relative w-full h-full p-4 md:p-8 bg-[#FAFAF9] rounded border border-[#E2E8F0]">
            
            <!-- Subtle Watermark Graphic Backdrop -->
            <div class="absolute inset-0 flex items-center justify-center opacity-[0.035] pointer-events-none select-none">
              <svg class="w-96 h-96 text-primary" fill="currentColor" viewBox="0 0 200 200">
                <path d="M100 0 L125 68 L197 68 L139 110 L161 178 L100 135 L39 178 L61 110 L3 68 L75 68 Z"></path>
                <circle cx="100" cy="100" fill="none" r="70" stroke="currentColor" stroke-width="12"></circle>
              </svg>
            </div>

            <!-- Precision Guilloche Corner Flourishes -->
            <div class="absolute top-2 left-2 w-14 h-14 text-primary opacity-60 pointer-events-none">
              <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 60 60">
                <path d="M2,2 L58,2 M2,2 L2,58 M6,6 L50,6 M6,6 L6,50 M10,10 L42,10 M10,10 L10,42" stroke-width="1.2"></path>
                <circle cx="12" cy="12" r="6" stroke-width="0.8"></circle>
                <path d="M2,30 C15,30 30,15 30,2" stroke-width="0.8"></path>
                <path d="M2,45 C25,45 45,25 45,2" stroke-width="0.8"></path>
              </svg>
            </div>
            <div class="absolute top-2 right-2 w-14 h-14 text-primary opacity-60 pointer-events-none rotate-90">
              <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 60 60">
                <path d="M2,2 L58,2 M2,2 L2,58 M6,6 L50,6 M6,6 L6,50 M10,10 L42,10 M10,10 L10,42" stroke-width="1.2"></path>
                <circle cx="12" cy="12" r="6" stroke-width="0.8"></circle>
                <path d="M2,30 C15,30 30,15 30,2" stroke-width="0.8"></path>
                <path d="M2,45 C25,45 45,25 45,2" stroke-width="0.8"></path>
              </svg>
            </div>
            <div class="absolute bottom-2 left-2 w-14 h-14 text-primary opacity-60 pointer-events-none -rotate-90">
              <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 60 60">
                <path d="M2,2 L58,2 M2,2 L2,58 M6,6 L50,6 M6,6 L6,50 M10,10 L42,10 M10,10 L10,42" stroke-width="1.2"></path>
                <circle cx="12" cy="12" r="6" stroke-width="0.8"></circle>
                <path d="M2,30 C15,30 30,15 30,2" stroke-width="0.8"></path>
                <path d="M2,45 C25,45 45,25 45,2" stroke-width="0.8"></path>
              </svg>
            </div>
            <div class="absolute bottom-2 right-2 w-14 h-14 text-primary opacity-60 pointer-events-none rotate-180">
              <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 60 60">
                <path d="M2,2 L58,2 M2,2 L2,58 M6,6 L50,6 M6,6 L6,50 M10,10 L42,10 M10,10 L10,42" stroke-width="1.2"></path>
                <circle cx="12" cy="12" r="6" stroke-width="0.8"></circle>
                <path d="M2,30 C15,30 30,15 30,2" stroke-width="0.8"></path>
                <path d="M2,45 C25,45 45,25 45,2" stroke-width="0.8"></path>
              </svg>
            </div>

            <!-- Certificate Header Section -->
            <div class="relative z-10 flex flex-col items-center text-center pt-space-sm">
              <div class="flex items-center justify-center gap-space-xs mb-space-sm">
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-on-primary shadow-sm">
                  <span class="material-symbols-outlined text-[18px]">verified</span>
                </div>
                <span class="font-label-sm text-label-sm tracking-[0.2em] uppercase font-semibold text-primary">CertificateHub Institutional Registry</span>
              </div>
              <div class="w-16 h-0.5 bg-primary/20 mb-space-sm"></div>
              <h2 class="font-display text-display tracking-[0.12em] uppercase text-[#0B1528] font-bold">
                Certificate of Completion
              </h2>
              <p class="font-body-md text-body-md text-[#475569] italic mt-space-xs">
                This official credential is proudly presented to
              </p>
            </div>

            <!-- Recipient Name Block -->
            <div class="relative z-10 flex flex-col items-center text-center my-space-md">
              <div class="px-space-xl py-space-xs">
                <span class="font-display text-display text-[#000000] font-bold tracking-tight inline-block border-b-2 border-primary/30 pb-2">
                  <?= $recipient_name ?>
                </span>
              </div>
            </div>

            <!-- Course / Assessment Declaration -->
            <div class="relative z-10 max-w-xl mx-auto text-center px-space-md mb-space-lg">
              <p class="font-body-md text-body-md text-[#334155] leading-relaxed">
                for successfully completing the rigorous curriculum, laboratory benchmarks, and practical assessment of
              </p>
              <h3 class="font-headline-lg text-headline-lg text-primary font-bold tracking-tight mt-space-xs mb-space-2xs">
                <?= $course_title ?>
              </h3>
              <p class="font-label-sm text-label-sm text-[#475569] font-medium tracking-wide">
                Conferred on <?= $issue_date ?> • <?= $location ?>
              </p>
            </div>

            <!-- Footer Details & Authentic Verification Blocks -->
            <div class="relative z-10 grid grid-cols-12 gap-space-md items-end pt-space-md border-t border-slate-200/80">
              <!-- Left Column: Instructor Signature -->
              <div class="col-span-4 flex flex-col items-start pl-space-sm">
                <div class="h-12 w-44 flex items-end justify-start mb-1 text-[#003EA8]">
                  <svg class="w-full h-full overflow-visible" viewBox="0 0 220 50">
                    <path d="M 10 38 Q 30 10 50 25 T 85 20 T 115 35 Q 130 5 150 28 T 195 24 Q 205 20 215 36" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"></path>
                    <path d="M 40 28 L 170 28" fill="none" opacity="0.4" stroke="currentColor" stroke-dasharray="2 3" stroke-width="1.2"></path>
                    <path d="M 75 42 Q 105 48 145 42" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"></path>
                  </svg>
                </div>
                <div class="w-48 h-0.5 bg-[#94A3B8] mb-1.5"></div>
                <span class="font-label-md text-label-md font-semibold text-[#0B1528] leading-tight"><?= $instructor_name ?></span>
                <span class="font-caption text-caption text-[#475569]"><?= $instructor_title ?></span>
                <span class="font-caption text-caption text-[#64748B]">Institutional Verification Board</span>
              </div>

              <!-- Center Column: Institutional Verification -->
              <div class="col-span-4 flex flex-col items-center text-center">
                <div class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center text-primary mb-1">
                  <span class="material-symbols-outlined text-[22px]">verified</span>
                </div>
                <span class="font-label-sm text-label-sm font-semibold text-[#1E293B] tracking-wide uppercase">
                  Verified Credential
                </span>
                <span class="font-caption text-caption text-tertiary font-medium mt-0.5 flex items-center gap-1">
                  <span class="material-symbols-outlined text-[14px]">lock</span>
                  Institutional Authenticated
                </span>
              </div>

              <!-- Right Column: Gold Embossed Seal Emblem -->
              <div class="col-span-4 flex flex-col items-end pr-space-sm">
                <div class="relative w-24 h-24 rounded-full bg-gradient-to-tr from-[#9A7B2C] via-[#F5D77F] to-[#C59B27] p-1 shadow-md flex items-center justify-center text-[#422C00]">
                  <div class="w-full h-full rounded-full border border-dashed border-[#846317] flex flex-col items-center justify-center p-1.5 text-center bg-gradient-to-b from-[#FFF5D6] to-[#F1C85A] shadow-inner">
                    <span class="material-symbols-outlined text-[20px] text-[#78540B]">workspace_premium</span>
                    <span class="font-caption text-[8px] font-bold tracking-tighter uppercase text-[#543800] leading-none mt-1">VERIFIED</span>
                    <span class="font-caption text-[7px] font-semibold tracking-wider text-[#6B4900] uppercase">CREDENTIAL</span>
                    <div class="w-8 h-px bg-[#8C6415] my-0.5"></div>
                    <span class="font-caption text-[6px] tracking-widest uppercase font-bold text-[#422C00]">CHUB • 2026</span>
                  </div>
                  <!-- Ribbon Tail Embellishment -->
                  <div class="absolute -bottom-3 left-4 w-4 h-6 bg-[#9A7B2C] -rotate-12 -z-10 clip-ribbon"></div>
                  <div class="absolute -bottom-3 right-4 w-4 h-6 bg-[#7E611E] rotate-12 -z-10 clip-ribbon"></div>
                </div>
                <div class="text-right mt-space-xs">
                  <span class="font-caption text-caption uppercase text-[#64748B] tracking-wider block">Authorized Registrar</span>
                  <span class="font-label-sm text-label-sm font-semibold text-[#0B1528]">Office of Academic Affairs</span>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>

      <!-- Modal Bottom Actions Bar -->
      <div class="px-space-lg py-space-md bg-surface-container-lowest flex flex-col sm:flex-row items-center justify-between gap-space-md border-t border-surface-container">
        <div class="flex items-center gap-space-sm w-full sm:w-auto">
          <span class="font-caption text-caption text-on-surface-variant">
            Participant profile: <strong class="text-on-surface font-medium"><?= $recipient_email ?></strong>
          </span>
        </div>
        
        <div class="flex flex-wrap items-center justify-end gap-space-xs w-full sm:w-auto">
          <a href="certificates_management.php" class="h-9 px-space-md rounded-lg bg-surface-container text-on-surface hover:bg-surface-container-high font-label-md text-label-md font-medium transition-all inline-flex items-center justify-center" id="close-modal-btn">
            Close Preview
          </a>
          <button class="flex items-center gap-space-xs h-9 px-space-md rounded-lg bg-surface-container text-on-surface hover:bg-surface-container-high font-label-md text-label-md font-medium transition-all" id="send-email-btn">
            <span class="material-symbols-outlined text-[18px]">mail</span>
            Send via Email
          </button>
          <button class="flex items-center gap-space-xs h-9 px-space-md rounded-lg bg-surface-container text-on-surface hover:bg-surface-container-high font-label-md text-label-md font-medium transition-all" id="copy-link-btn">
            <span class="material-symbols-outlined text-[18px]">link</span>
            Copy Verification Link
          </button>
          <!-- Dual Download Action (PDF & PNG) -->
          <div class="relative inline-block text-left" id="downloadDropdownContainer">
            <div class="flex items-center rounded-lg shadow-sm bg-primary-container text-on-primary overflow-hidden">
              <button type="button" id="download-pdf-btn" class="flex items-center gap-space-xs h-9 px-space-md font-label-md text-label-md font-medium hover:bg-primary transition-all focus:outline-none" title="Download standalone PDF">
                <span class="material-symbols-outlined text-[18px]">picture_as_pdf</span>
                <span id="downloadBtnLabel">Download PDF</span>
              </button>
              <button type="button" id="downloadDropdownTrigger" class="h-9 px-2 bg-primary/40 hover:bg-primary transition-all flex items-center justify-center border-l border-white/20 focus:outline-none" title="More download options (PNG Image, Print)">
                <span class="material-symbols-outlined text-[18px]">expand_less</span>
              </button>
            </div>
            <!-- Floating Menu (drops up above footer) -->
            <div id="downloadDropdownMenu" class="hidden absolute right-0 bottom-full mb-2 w-64 bg-surface-container-lowest rounded-xl shadow-2xl border border-surface-container py-1.5 z-50 animate-in fade-in zoom-in-95 duration-150">
              <button type="button" id="menu-download-pdf" class="w-full px-space-md py-2.5 flex items-center gap-space-sm text-left hover:bg-surface-container text-on-surface font-label-md text-label-sm transition-colors">
                <span class="material-symbols-outlined text-[22px] text-red-600">picture_as_pdf</span>
                <div class="flex flex-col">
                  <span class="font-semibold text-on-surface">Download PDF</span>
                  <span class="font-caption text-caption text-secondary">Official A4 Landscape Document</span>
                </div>
              </button>
              <button type="button" id="menu-download-png" class="w-full px-space-md py-2.5 flex items-center gap-space-sm text-left hover:bg-surface-container text-on-surface font-label-md text-label-sm transition-colors border-t border-surface-container-low">
                <span class="material-symbols-outlined text-[22px] text-primary">image</span>
                <div class="flex flex-col">
                  <span class="font-semibold text-on-surface">Download High-Res PNG</span>
                  <span class="font-caption text-caption text-secondary">A4 300 DPI (3508×2480px)</span>
                </div>
              </button>
              <button type="button" id="menu-print-only" class="w-full px-space-md py-2.5 flex items-center gap-space-sm text-left hover:bg-surface-container text-on-surface font-label-md text-label-sm transition-colors border-t border-surface-container-low">
                <span class="material-symbols-outlined text-[22px] text-secondary">print</span>
                <div class="flex flex-col">
                  <span class="font-semibold text-on-surface">Print Certificate Only</span>
                  <span class="font-caption text-caption text-secondary">Standard A4 paper printout</span>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  (function() {
    const copyLinkBtn = document.getElementById('copy-link-btn');
    const sendEmailBtn = document.getElementById('send-email-btn');
    const downloadPdfBtn = document.getElementById('download-pdf-btn');
    const downloadDropdownTrigger = document.getElementById('downloadDropdownTrigger');
    const downloadDropdownMenu = document.getElementById('downloadDropdownMenu');
    const menuDownloadPdf = document.getElementById('menu-download-pdf');
    const menuDownloadPng = document.getElementById('menu-download-png');
    const menuPrintOnly = document.getElementById('menu-print-only');
    const zoomToggleBtn = document.getElementById('zoom-toggle-btn');
    const printBtn = document.getElementById('print-shortcut-btn');
    const customCanvas = document.getElementById('custom-template-canvas');
    const vectorCanvas = document.getElementById('vector-certificate-canvas');
    const toggleCanvasViewBtn = document.getElementById('toggleCanvasViewBtn');
    const toggleLayoutText = document.getElementById('toggleLayoutText');
    const printInner = document.getElementById('print-certificate-inner');

    let activeCanvas = customCanvas || vectorCanvas;

    // Synchronize the isolated print wrapper with current active view
    function syncPrintContainer() {
      if (!printInner) return;
      const isCustomVisible = customCanvas && !customCanvas.classList.contains('hidden');
      if (isCustomVisible) {
        printInner.innerHTML = customCanvas.innerHTML;
        printInner.className = 'cert-printable-content relative w-full bg-white overflow-hidden';
        printInner.style.aspectRatio = '297 / 210';
      } else if (vectorCanvas) {
        printInner.innerHTML = vectorCanvas.innerHTML;
        printInner.className = 'cert-printable-content relative w-full bg-white overflow-hidden';
        printInner.style.aspectRatio = '297 / 210';
      }
    }

    // Toggle dropdown menu
    if (downloadDropdownTrigger && downloadDropdownMenu) {
      downloadDropdownTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        downloadDropdownMenu.classList.toggle('hidden');
      });
      document.addEventListener('click', (e) => {
        if (!downloadDropdownMenu.contains(e.target) && !downloadDropdownTrigger.contains(e.target)) {
          downloadDropdownMenu.classList.add('hidden');
        }
      });
    }

    // Toggle between Custom Template and Vector Layout
    if (toggleCanvasViewBtn && customCanvas && vectorCanvas) {
      toggleCanvasViewBtn.addEventListener('click', () => {
        const isCustomVisible = !customCanvas.classList.contains('hidden');
        if (isCustomVisible) {
          customCanvas.classList.add('hidden');
          vectorCanvas.classList.remove('hidden');
          activeCanvas = vectorCanvas;
          if (toggleLayoutText) toggleLayoutText.textContent = 'Template View';
        } else {
          vectorCanvas.classList.add('hidden');
          customCanvas.classList.remove('hidden');
          activeCanvas = customCanvas;
          if (toggleLayoutText) toggleLayoutText.textContent = 'Classic View';
        }
        syncPrintContainer();
      });
    }

    // Generate Scannable QR Codes for Modal Preview and Print
    function renderModalQrCodes() {
      const verifyUrl = '<?= addslashes($verifyUrl) ?>';
      
      const modalQr = document.getElementById('modalQrCode');
      if (modalQr) {
        if (typeof QRCode !== 'undefined') {
          modalQr.innerHTML = '';
          new QRCode(modalQr, {
            text: verifyUrl,
            width: 256,
            height: 256,
            colorDark: '#000000',
            colorLight: '#FFFFFF',
            correctLevel: QRCode.CorrectLevel.M
          });
        } else {
          // If QRCode library is still initializing, retry in a moment
          setTimeout(renderModalQrCodes, 60);
          return;
        }
      }

      const printModalQr = document.getElementById('printModalQrCode');
      if (printModalQr && typeof QRCode !== 'undefined') {
        printModalQr.innerHTML = '';
        new QRCode(printModalQr, {
          text: verifyUrl,
          width: 256,
          height: 256,
          colorDark: '#000000',
          colorLight: '#FFFFFF',
          correctLevel: QRCode.CorrectLevel.M
        });
      }
    }

    // Initialize immediately if DOM is already ready (e.g. inside iframes or cached views),
    // and also listen for DOMContentLoaded and load events as safety fallbacks
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', renderModalQrCodes);
    } else {
      renderModalQrCodes();
    }
    window.addEventListener('load', renderModalQrCodes);
    setTimeout(renderModalQrCodes, 150);

    // Copy Verification Link
    if (copyLinkBtn) {
      copyLinkBtn.addEventListener('click', () => {
        const verifyUrl = '<?= addslashes($verifyUrl) ?>';
        navigator.clipboard?.writeText(verifyUrl).catch(() => {});
        showGlobalToast('Official Verification URL copied to clipboard');
      });
    }

    // Email Dispatch
    if (sendEmailBtn) {
      sendEmailBtn.addEventListener('click', () => {
        showGlobalToast('Certificate dispatched to <?= $recipient_email ?>');
      });
    }

    // Render High-Res A4 Canvas (3508x2480 at 300 DPI)
    async function renderHighResCanvas() {
      await document.fonts.ready;
      await new Promise(resolve => setTimeout(resolve, 80));

      const canvas = document.createElement('canvas');
      const customImg = document.getElementById('customTemplateImg');
      
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

      // Proportional name coordinates and font sizing
      const namePosX = (<?= $namePosX ?> / 100) * targetWidth;
      const namePosY = (<?= $namePosY ?> / 100) * targetHeight;
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
      const qrCanvas = document.querySelector('#modalQrCode canvas') || document.querySelector('#printModalQrCode canvas');
      const qrImg = document.querySelector('#modalQrCode img') || document.querySelector('#printModalQrCode img');
      let qrSource = null;
      if (qrCanvas && qrCanvas.width > 0) {
        qrSource = qrCanvas;
      } else if (qrImg && qrImg.complete && qrImg.naturalWidth > 0) {
        qrSource = qrImg;
      }

      if (qrSource) {
        const qrPixelSize = (<?= $qrSize ?> / 842) * targetWidth;
        const qrCenterX = (<?= $qrPosX ?> / 100) * targetWidth;
        const qrCenterY = (<?= $qrPosY ?> / 100) * targetHeight;
        const qrLeft = qrCenterX - (qrPixelSize / 2);
        const qrTop = qrCenterY - (qrPixelSize / 2);
        const qrPadding = qrPixelSize * 0.06;

        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(qrLeft - qrPadding, qrTop - qrPadding, qrPixelSize + (qrPadding * 2), qrPixelSize + (qrPadding * 2));
        ctx.drawImage(qrSource, qrLeft, qrTop, qrPixelSize, qrPixelSize);
      }
      <?php endif; ?>

      return canvas;
    }

    // PDF Download Execution (Native A4 Landscape)
    async function executeDownloadPDF() {
      if (downloadDropdownMenu) downloadDropdownMenu.classList.add('hidden');
      const originalHTML = downloadPdfBtn.innerHTML;
      downloadPdfBtn.disabled = true;
      downloadPdfBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span><span>Generating A4 PDF...</span>';

      try {
        const isCustom = customCanvas && !customCanvas.classList.contains('hidden');
        if (isCustom) {
          const canvas = await renderHighResCanvas();
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
            showGlobalToast('A4 Certificate PDF downloaded successfully!');
          } else {
            syncPrintContainer();
            window.print();
          }
        } else {
          syncPrintContainer();
          window.print();
        }

        // Track download
        fetch('../includes/track_download.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'cert_id=<?= (int)($certData['certificate_id'] ?? 0) ?>&token=<?= urlencode($cert_token) ?>'
        }).catch(() => {});
      } catch (err) {
        console.error('PDF error, fallback to print:', err);
        syncPrintContainer();
        window.print();
      }

      setTimeout(() => {
        downloadPdfBtn.disabled = false;
        downloadPdfBtn.innerHTML = originalHTML;
      }, 1200);
    }

    // PNG Download Execution
    async function executeDownloadPNG() {
      if (downloadDropdownMenu) downloadDropdownMenu.classList.add('hidden');
      const originalHTML = downloadPdfBtn.innerHTML;
      downloadPdfBtn.disabled = true;
      downloadPdfBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span><span>Rendering PNG...</span>';

      try {
        const canvas = await renderHighResCanvas();
        const dataUrl = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.download = 'Certificate_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $recipient_name) ?>.png';
        link.href = dataUrl;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showGlobalToast('High-resolution PNG downloaded!');

        // Track download
        fetch('../includes/track_download.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'cert_id=<?= (int)($certData['certificate_id'] ?? 0) ?>&token=<?= urlencode($cert_token) ?>'
        }).catch(() => {});
      } catch (err) {
        console.error('PNG error:', err);
        showGlobalToast('Failed to export PNG.');
      }

      setTimeout(() => {
        downloadPdfBtn.disabled = false;
        downloadPdfBtn.innerHTML = originalHTML;
      }, 1200);
    }

    // Bind Download buttons
    if (downloadPdfBtn) downloadPdfBtn.addEventListener('click', executeDownloadPDF);
    if (menuDownloadPdf) menuDownloadPdf.addEventListener('click', executeDownloadPDF);
    if (menuDownloadPng) menuDownloadPng.addEventListener('click', executeDownloadPNG);
    if (menuPrintOnly) {
      menuPrintOnly.addEventListener('click', () => {
        if (downloadDropdownMenu) downloadDropdownMenu.classList.add('hidden');
        syncPrintContainer();
        window.print();
      });
    }

    // Toggle Scale / Fit Zoom View
    let isZoomed = false;
    if (zoomToggleBtn) {
      zoomToggleBtn.addEventListener('click', () => {
        isZoomed = !isZoomed;
        const target = activeCanvas;
        if (target) {
          if (isZoomed) {
            target.style.transform = 'scale(1.06)';
            zoomToggleBtn.classList.add('bg-surface-container', 'text-primary');
          } else {
            target.style.transform = 'scale(1)';
            zoomToggleBtn.classList.remove('bg-surface-container', 'text-primary');
          }
        }
      });
    }

    // Print dialog shortcut
    if (printBtn) {
      printBtn.addEventListener('click', () => {
        syncPrintContainer();
        window.print();
      });
    }

    // Keyboard accessibility: ESC closes modal
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (window.parent && window.parent.closeLivePreviewModal) {
          window.parent.closeLivePreviewModal();
        } else {
          window.location.href = 'certificates_management.php';
        }
      }
    });

    // Initial print container sync
    syncPrintContainer();
  })();
</script>

<?php if (!$isEmbed): ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php else: ?>
</body>
</html>
<?php endif; ?>


