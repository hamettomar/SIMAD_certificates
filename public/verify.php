<?php
require_once __DIR__ . '/../includes/db.php';

$query_token = trim($_GET['token'] ?? $_GET['cert'] ?? $_GET['id'] ?? '');

$cert = null;
$isValid = false;
$isRevoked = false;

if (!empty($query_token)) {
    $cert = dbFetchOne("
        SELECT 
            p.id as participant_id, p.full_name, p.email, p.status as participant_status,
            ch.id as cohort_id, ch.name as cohort_name, ch.batch_code, ch.instructor_name, ch.instructor_title, ch.issue_date, ch.location,
            c.id as cert_id, c.certificate_token, c.document_hash, c.issued_at, c.download_count, c.status as cert_status
        FROM certificates c
        JOIN participants p ON p.id = c.participant_id
        JOIN cohorts ch ON ch.id = p.cohort_id
        WHERE c.certificate_token = ?
        LIMIT 1
    ", [$query_token]);

    if ($cert) {
        if ($cert['cert_status'] === 'revoked' || $cert['participant_status'] === 'revoked') {
            $isRevoked = true;
        } elseif ($cert['cert_status'] === 'valid' && $cert['participant_status'] !== 'revoked') {
            $isValid = true;
        }
    }
}

// Verification URL for QR code encoding
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/certificate/public/verify.php';
$phpSelf = $_SERVER['PHP_SELF'] ?? '/certificate/public/verify.php';
$verifyUrl = !empty($cert['certificate_token']) 
    ? $protocol . '://' . $host . dirname($phpSelf) . '/verify.php?token=' . urlencode($cert['certificate_token']) 
    : $protocol . '://' . $host . $requestUri;

$page_title = $isValid 
    ? 'Official Verification: ' . htmlspecialchars($cert['full_name']) . ' - SIMAD University' 
    : ($isRevoked 
        ? 'REVOKED CREDENTIAL: ' . htmlspecialchars($cert['full_name']) . ' - SIMAD University'
        : 'Credential Verification Ledger - SIMAD University');

include __DIR__ . '/../includes/head.php';
?>
<body class="bg-surface font-body-md text-on-surface antialiased min-h-screen flex flex-col justify-between">
  
  <!-- Institutional Header Bar -->
  <header class="w-full bg-surface-container-lowest border-b border-surface-container py-3 px-gutter-mobile md:px-gutter-desktop">
    <div class="max-w-4xl mx-auto flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <img src="../simad_university_logo.png" alt="SIMAD University" class="w-10 h-10 object-contain shrink-0 drop-shadow-xs">
        <div class="flex flex-col">
          <span class="font-headline-sm text-headline-sm text-on-surface font-bold tracking-tight">SIMAD Certificates</span>
          <span class="font-caption text-[10px] text-outline uppercase tracking-wider font-medium">Official Institutional Registry</span>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1 text-caption text-outline font-medium">
          <span class="material-symbols-outlined text-[16px] <?= $isValid ? 'text-tertiary' : ($isRevoked ? 'text-error' : 'text-outline') ?>">lock</span>
          Public Ledger
        </span>
      </div>
    </div>
  </header>

  <!-- Main Verification Stage -->
  <main class="w-full flex-grow relative overflow-hidden px-gutter-mobile md:px-gutter-desktop py-space-xl">
    <!-- Ambient Backdrop Effect -->
    <div class="pointer-events-none absolute -top-24 left-1/2 -translate-x-1/2 w-[680px] h-[320px] <?= $isRevoked ? 'bg-gradient-to-b from-error-container/40 via-surface-container-low/20 to-transparent' : 'bg-gradient-to-b from-surface-container-high/60 via-surface-container-low/20 to-transparent' ?> blur-3xl rounded-full"></div>

    <div class="max-w-3xl mx-auto flex flex-col gap-space-lg relative z-10">

      <?php if ($isValid): ?>
        <?php
          $recipient_name  = htmlspecialchars($cert['full_name']);
          $course_title    = htmlspecialchars($cert['cohort_name']);
          $batch_code      = htmlspecialchars($cert['batch_code']);
          $instructor_name = htmlspecialchars($cert['instructor_name'] ?? 'Authorized Registrar');
          $instructor_title= htmlspecialchars($cert['instructor_title'] ?? 'Lead Instructor');
          $cert_token      = htmlspecialchars($cert['certificate_token']);
          $issue_date      = date('F j, Y', strtotime($cert['issued_at'] ?? $cert['issue_date']));
          $doc_hash        = htmlspecialchars($cert['document_hash']);
          $location        = htmlspecialchars($cert['location'] ?? 'Mogadishu, Somalia');
        ?>

        <!-- Authenticity Validation Card -->
        <div class="bg-surface-container-lowest rounded-2xl shadow-lg border border-surface-container overflow-hidden flex flex-col">
          
          <!-- Green Verified Status Banner -->
          <div class="bg-gradient-to-r from-[#1b873d] via-[#21a249] to-[#28bf58] border-b-2 border-secondary/40 p-space-md md:p-space-lg text-white flex flex-col sm:flex-row items-center justify-between gap-space-md">
            <div class="flex items-center gap-space-md text-center sm:text-left">
              <div class="w-14 h-14 rounded-2xl bg-white p-1.5 backdrop-blur-md flex items-center justify-center shrink-0 shadow-sm border border-white/30">
                <img src="../simad_university_logo.png" alt="SIMAD University" class="w-full h-full object-contain">
              </div>
              <div class="flex flex-col">
                <div class="inline-flex items-center justify-center sm:justify-start gap-1">
                  <span class="font-label-sm text-[11px] font-bold uppercase tracking-widest text-emerald-200">Authentic Record</span>
                  <span class="material-symbols-outlined text-[15px] text-emerald-300">check_circle</span>
                </div>
                <h1 class="font-headline-lg text-headline-lg font-bold tracking-tight text-white mt-0.5">
                  Verified Official Credential
                </h1>
                <span class="font-caption text-caption text-white/80">
                  Registered and validated on SIMAD Institutional Registry
                </span>
              </div>
            </div>

            <!-- Scannable Micro QR Code Container -->
            <div class="flex flex-col items-center p-2 rounded-xl bg-white shadow-md shrink-0" title="Scannable Official Credential QR">
              <div id="verifyQrDisplay" class="w-20 h-20"></div>
              <span class="text-[9px] font-mono font-bold text-outline mt-1 uppercase tracking-tighter">Scan to Verify</span>
            </div>
          </div>

          <!-- Credential Specifics -->
          <div class="p-space-lg md:p-space-xl flex flex-col gap-space-lg">
            
            <!-- Recipient Announcement -->
            <div class="border-b border-surface-container pb-space-md">
              <span class="font-caption text-caption text-outline uppercase tracking-widest block mb-1">Conferred Recipient</span>
              <div class="flex items-center justify-between flex-wrap gap-2">
                <h2 class="text-2xl sm:text-3xl md:text-display font-bold text-on-surface tracking-tight break-words leading-tight" style="font-family: 'Poppins', sans-serif;">
                  <?= $recipient_name ?>
                </h2>
              </div>
              <p class="font-body-md text-body-md text-on-surface-variant mt-2 leading-relaxed">
                Has fulfilled all academic criteria, practical laboratory benchmarks, and evaluation requirements for the successful completion of the certified institutional program.
              </p>
            </div>

            <!-- Key Fact Ledger Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md bg-surface-container-low/50 p-space-md rounded-xl border border-surface-container">
              <div class="flex flex-col">
                <span class="font-caption text-caption uppercase text-outline tracking-wider font-semibold">Program / Workshop</span>
                <h3 class="text-base sm:text-headline-sm font-bold text-on-surface mt-1 leading-snug break-words"><?= $course_title ?></h3>
                <span class="font-caption text-caption text-secondary mt-0.5"><?= $batch_code ?></span>
              </div>
              <div class="flex flex-col">
                <span class="font-caption text-caption uppercase text-outline tracking-wider font-semibold">Issuing Institution</span>
                <div class="flex items-center gap-2 mt-1">
                  <img src="../simad_university_logo.png" alt="SIMAD University Logo" class="w-6 h-6 object-contain shrink-0">
                  <span class="font-headline-sm text-headline-sm font-semibold text-on-surface">SIMAD University</span>
                </div>
                <span class="font-caption text-caption text-secondary mt-0.5"><?= $location ?></span>
              </div>
              <div class="flex flex-col border-t border-surface-container pt-3">
                <span class="font-caption text-caption uppercase text-outline tracking-wider">Date Conferred</span>
                <span class="font-label-md text-label-md font-semibold text-on-surface mt-1"><?= $issue_date ?></span>
              </div>
              <div class="flex flex-col border-t border-surface-container pt-3">
                <span class="font-caption text-caption uppercase text-outline tracking-wider">Authorized Signatory</span>
                <span class="font-label-md text-label-md font-semibold text-on-surface mt-1"><?= $instructor_name ?></span>
                <span class="font-caption text-caption text-outline"><?= $instructor_title ?></span>
              </div>
            </div>

            <!-- Cryptographic Ledger Fingerprint -->
            <div class="p-space-md rounded-xl bg-surface-container-lowest border border-surface-container flex flex-col gap-2">
              <div class="flex items-center justify-between">
                <span class="font-caption text-caption uppercase font-semibold tracking-wider text-outline flex items-center gap-1">
                  <span class="material-symbols-outlined text-[16px] text-tertiary">fingerprint</span>
                  Cryptographic Verification Fingerprint
                </span>
                <span class="font-mono text-[11px] text-outline font-semibold">SHA-256</span>
              </div>
              <div class="p-2.5 rounded-lg bg-surface-container-low font-mono text-[11px] text-on-surface break-all select-all border border-surface-container-high/60">
                <?= $doc_hash ?>
              </div>
              <div class="flex items-center justify-between text-caption text-outline text-[11px] pt-1">
                <span>Unique Credential ID: <strong class="font-mono text-on-surface"><?= $cert_token ?></strong></span>
                <button type="button" onclick="copyVerificationUrl()" class="text-primary hover:underline font-medium inline-flex items-center gap-1">
                  <span class="material-symbols-outlined text-[14px]">content_copy</span>
                  Copy Verification URL
                </button>
              </div>
            </div>

            <!-- Tamper-evident Seal Footer -->
            <div class="flex items-center gap-space-sm p-3 rounded-xl bg-surface-container/60 border border-surface-container">
              <span class="material-symbols-outlined text-[24px] text-primary shrink-0">verified_user</span>
              <p class="font-caption text-caption text-on-surface-variant leading-snug">
                <strong>Official Issuance Guarantee:</strong> This credential was issued directly through SIMAD Certificates. Its authenticity is mathematically verifiable and protected against forgery.
              </p>
            </div>

          </div>

        </div>

      <?php elseif ($isRevoked): ?>
        <?php
          $recipient_name  = htmlspecialchars($cert['full_name']);
          $course_title    = htmlspecialchars($cert['cohort_name']);
          $batch_code      = htmlspecialchars($cert['batch_code']);
          $cert_token      = htmlspecialchars($cert['certificate_token']);
          $issue_date      = date('F j, Y', strtotime($cert['issued_at'] ?? $cert['issue_date']));
          $doc_hash        = htmlspecialchars($cert['document_hash']);
          $location        = htmlspecialchars($cert['location'] ?? 'Mogadishu, Somalia');
        ?>

        <!-- Red Official Revocation Notice Card -->
        <div class="bg-surface-container-lowest rounded-2xl shadow-lg border border-error/40 overflow-hidden flex flex-col">
          
          <!-- Crimson Revoked Status Banner -->
          <div class="bg-gradient-to-r from-[#7a0c0c] to-[#ba1a1a] p-space-md md:p-space-lg text-white flex flex-col sm:flex-row items-center justify-between gap-space-md">
            <div class="flex items-center gap-space-md text-center sm:text-left">
              <div class="w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-md flex items-center justify-center text-white shrink-0 shadow-sm border border-white/20">
                <span class="material-symbols-outlined text-[34px]" style="font-variation-settings: 'FILL' 1;">block</span>
              </div>
              <div class="flex flex-col">
                <div class="inline-flex items-center justify-center sm:justify-start gap-1">
                  <span class="font-label-sm text-[11px] font-bold uppercase tracking-widest text-red-200">Institutional Ledger Alert</span>
                  <span class="material-symbols-outlined text-[15px] text-red-300">warning</span>
                </div>
                <h1 class="font-headline-lg text-headline-lg font-bold tracking-tight text-white mt-0.5">
                  Revoked Credential Record
                </h1>
                <span class="font-caption text-caption text-white/80">
                  This certificate has been formally revoked by SIMAD University
                </span>
              </div>
            </div>

            <!-- Scannable Micro QR Code Container -->
            <div class="flex flex-col items-center p-2 rounded-xl bg-white shadow-md shrink-0" title="Scannable Credential QR">
              <div id="verifyQrDisplay" class="w-20 h-20"></div>
              <span class="text-[9px] font-mono font-bold text-outline mt-1 uppercase tracking-tighter">Scan to Verify</span>
            </div>
          </div>

          <!-- Credential Specifics & Warning Notice -->
          <div class="p-space-lg md:p-space-xl flex flex-col gap-space-lg">
            
            <!-- Prominent Revocation Warning Notice Box -->
            <div class="p-space-md rounded-xl bg-error-container/40 border border-error/30 flex items-start gap-3">
              <span class="material-symbols-outlined text-error text-[26px] shrink-0 mt-0.5">gpp_bad</span>
              <div class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-bold text-error">Official Nullification Notice</span>
                <p class="font-body-sm text-body-sm text-on-error-container leading-relaxed">
                  The credential registered under token <strong class="font-mono text-on-surface"><?= $cert_token ?></strong> for <strong><?= $recipient_name ?></strong> has been officially <strong>REVOKED</strong> on the SIMAD Institutional Registry. This document is no longer recognized as valid for academic, professional, or employment accreditation.
                </p>
              </div>
            </div>

            <!-- Recipient Announcement -->
            <div class="border-b border-surface-container pb-space-md">
              <span class="font-caption text-caption text-outline uppercase tracking-widest block mb-1">Conferred Recipient</span>
              <div class="flex items-center justify-between flex-wrap gap-2">
                <h2 class="text-2xl sm:text-3xl md:text-display font-bold text-on-surface line-through opacity-70 tracking-tight break-words leading-tight" style="font-family: 'Poppins', sans-serif;">
                  <?= $recipient_name ?>
                </h2>
                <span class="px-2.5 py-1 rounded-full bg-error-container border border-error/40 text-on-error-container font-label-sm text-caption font-bold flex items-center gap-1">
                  <span class="w-2 h-2 rounded-full bg-error"></span>
                  STATUS: REVOKED / INVALID
                </span>
              </div>
            </div>

            <!-- Key Fact Ledger Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md bg-surface-container-low/50 p-space-md rounded-xl border border-surface-container">
              <div class="flex flex-col">
                <span class="font-caption text-caption uppercase text-outline tracking-wider font-semibold">Program / Workshop</span>
                <h3 class="text-base sm:text-headline-sm font-bold text-on-surface mt-1 leading-snug break-words"><?= $course_title ?></h3>
                <span class="font-caption text-caption text-secondary mt-0.5"><?= $batch_code ?></span>
              </div>
              <div class="flex flex-col">
                <span class="font-caption text-caption uppercase text-outline tracking-wider font-semibold">Issuing Institution</span>
                <div class="flex items-center gap-2 mt-1">
                  <img src="../simad_university_logo.png" alt="SIMAD University Logo" class="w-6 h-6 object-contain shrink-0">
                  <span class="font-headline-sm text-headline-sm font-semibold text-on-surface">SIMAD University</span>
                </div>
                <span class="font-caption text-caption text-secondary mt-0.5"><?= $location ?></span>
              </div>
              <div class="flex flex-col border-t border-surface-container pt-3">
                <span class="font-caption text-caption uppercase text-outline tracking-wider">Original Conferred Date</span>
                <span class="font-label-md text-label-md font-semibold text-on-surface mt-1"><?= $issue_date ?></span>
              </div>
              <div class="flex flex-col border-t border-surface-container pt-3">
                <span class="font-caption text-caption uppercase text-outline tracking-wider">Registry Standing</span>
                <span class="font-label-md text-label-md font-bold text-error mt-1 flex items-center gap-1">
                  <span class="material-symbols-outlined text-[18px]">cancel</span>
                  Null &amp; Void (Revoked)
                </span>
              </div>
            </div>

            <!-- Cryptographic Ledger Fingerprint -->
            <div class="p-space-md rounded-xl bg-surface-container-lowest border border-surface-container flex flex-col gap-2">
              <div class="flex items-center justify-between">
                <span class="font-caption text-caption uppercase font-semibold tracking-wider text-outline flex items-center gap-1">
                  <span class="material-symbols-outlined text-[16px] text-error">fingerprint</span>
                  Revoked Document Ledger Fingerprint
                </span>
                <span class="font-mono text-[11px] text-outline font-semibold">SHA-256</span>
              </div>
              <div class="p-2.5 rounded-lg bg-surface-container-low font-mono text-[11px] text-on-surface break-all select-all border border-surface-container-high/60">
                <?= $doc_hash ?>
              </div>
              <div class="flex items-center justify-between text-caption text-outline text-[11px] pt-1">
                <span>Unique Credential ID: <strong class="font-mono text-on-surface"><?= $cert_token ?></strong></span>
                <button type="button" onclick="copyVerificationUrl()" class="text-primary hover:underline font-medium inline-flex items-center gap-1">
                  <span class="material-symbols-outlined text-[14px]">content_copy</span>
                  Copy Verification URL
                </button>
              </div>
            </div>

            <!-- Security Fraud Advisory -->
            <div class="flex items-center gap-space-sm p-3 rounded-xl bg-error-container/20 border border-error/20">
              <span class="material-symbols-outlined text-[24px] text-error shrink-0">policy</span>
              <p class="font-caption text-caption text-on-surface-variant leading-snug">
                <strong>Anti-Fraud Notice:</strong> Presenting or circulating a revoked certificate is considered academic and institutional misrepresentation. For official registry inquiries, contact the SIMAD University Office of the Registrar.
              </p>
            </div>

          </div>

        </div>

      <?php else: ?>

        <!-- Credential Unverified / Not Found Warning State -->
        <div class="bg-surface-container-lowest rounded-2xl shadow-md border border-surface-container p-space-lg md:p-space-xl flex flex-col items-center text-center gap-space-md">
          <div class="w-16 h-16 rounded-2xl bg-error-container/40 flex items-center justify-center text-error shadow-sm border border-error/20">
            <span class="material-symbols-outlined text-[36px]">shield_with_heart</span>
          </div>

          <div class="flex flex-col gap-1 max-w-lg">
            <span class="font-label-sm text-label-sm uppercase font-bold text-error tracking-wider">Unverified Credential</span>
            <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface">Record Not Found in Registry</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1 leading-relaxed">
              <?php if (!empty($query_token)): ?>
                No active credential was found matching token <code class="font-mono font-bold text-on-surface bg-surface-container px-1.5 py-0.5 rounded"><?= htmlspecialchars($query_token) ?></code>. It may have expired or mistyped.
              <?php else: ?>
                Please enter a Certificate Token or scan a certificate QR code to verify its authenticity.
              <?php endif; ?>
            </p>
          </div>

          <!-- Manual Verification Input -->
          <form method="GET" action="verify.php" class="w-full max-w-md flex items-center gap-2 mt-2">
            <input 
              type="text" 
              name="token" 
              placeholder="e.g. SIMAD-PU-2026-001" 
              value="<?= htmlspecialchars($query_token) ?>" 
              required
              class="flex-1 h-11 px-3.5 rounded-lg bg-surface-container-low border border-surface-container font-mono text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary uppercase"
            >
            <button type="submit" class="h-11 px-5 rounded-lg bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-semibold transition-all">
              Verify
            </button>
          </form>

          <a href="public_certificate_download.php" class="font-label-sm text-label-sm text-primary hover:underline mt-2">
            Looking to download your student certificate? Visit the Student Lookup Portal →
          </a>
        </div>

      <?php endif; ?>

    </div>
  </main>

  <!-- Public Footer -->
  <footer class="w-full text-center py-6 text-outline font-caption text-caption border-t border-surface-container-high/30">
    <span>SIMAD University © <?= date('Y') ?></span> • <span>Institutional Credential Ledger</span> • <a href="public_certificate_download.php" class="text-primary hover:underline">Student Portal</a> • <a href="../admin/organizer_sign_in.php" class="text-primary hover:underline">Organizer Sign In</a>
  </footer>

  <?php if ($isValid || $isRevoked): ?>
  <script>
    // Generate Scannable Micro QR Code for this credential URL
    document.addEventListener('DOMContentLoaded', () => {
      const qrContainer = document.getElementById('verifyQrDisplay');
      if (qrContainer && typeof QRCode !== 'undefined') {
        new QRCode(qrContainer, {
          text: '<?= addslashes($verifyUrl) ?>',
          width: 80,
          height: 80,
          colorDark: '<?= $isRevoked ? "#dc2626" : "#000000" ?>',
          colorLight: '#FFFFFF',
          correctLevel: QRCode.CorrectLevel.M
        });
      }
    });

    function copyVerificationUrl() {
      navigator.clipboard.writeText('<?= addslashes($verifyUrl) ?>')
        .then(() => alert('Official Verification URL copied to clipboard!'))
        .catch(() => alert('<?= addslashes($verifyUrl) ?>'));
    }
  </script>
  <?php endif; ?>

</body>
</html>
