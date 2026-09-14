<?php
/**
 * Bulk Certificate Export Modal
 * CertificateHub / SIMAD Certificates
 * 
 * Supports:
 * - All-in-One Multi-Page PDF (A4 Landscape at 300 DPI for Color Printing)
 * - ZIP Archive of Individual Named PDFs (for archiving / student distribution)
 */

if (!isset($cohortId) || empty($cohortId) || $cohortId === 'all' || !is_numeric($cohortId)) {
    $activeCohort = function_exists('getActiveCohort') ? getActiveCohort() : ['id' => 1, 'name' => 'Cohort', 'batch_code' => 'BATCH'];
    $cohortId = (int)$activeCohort['id'];
} else {
    $cohortId = (int)$cohortId;
}

// Fetch Active Certificate Template configuration
$bulkTemplate = dbFetchOne("SELECT * FROM certificate_templates WHERE cohort_id = ? AND is_active = 1 LIMIT 1", [$cohortId]);
if (!$bulkTemplate) {
    $bulkTemplate = dbFetchOne("SELECT * FROM certificate_templates WHERE cohort_id = ? LIMIT 1", [$cohortId]);
}

$bulkTemplateImage = !empty($bulkTemplate['image_path']) ? '../' . ltrim($bulkTemplate['image_path'], '/') : '';
$bulkTemplateName = htmlspecialchars($bulkTemplate['template_name'] ?? 'Default Certificate Template');

// Typography and coordinates
$bNamePosX = (float)($bulkTemplate['name_pos_x'] ?? 50.0);
$bNamePosY = (float)($bulkTemplate['name_pos_y'] ?? 48.0);
$bNameFontSize = (int)($bulkTemplate['name_font_size'] ?? 50);
$bNameFontFamily = htmlspecialchars($bulkTemplate['name_font_family'] ?? 'Playfair Display');
$bNameFontColor = htmlspecialchars($bulkTemplate['name_font_color'] ?? '#000000');
if (!str_starts_with($bNameFontColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $bNameFontColor)) {
    $bNameFontColor = '#' . $bNameFontColor;
}
$bNameTextAlign = htmlspecialchars($bulkTemplate['name_text_align'] ?? 'center');

$bShowCertId = (int)($bulkTemplate['show_cert_id'] ?? 1);
$bShowCertIdPrefix = (int)($bulkTemplate['show_cert_id_prefix'] ?? 0);
$bCertIdPosX = (float)($bulkTemplate['cert_id_pos_x'] ?? 50.0);
$bCertIdPosY = (float)($bulkTemplate['cert_id_pos_y'] ?? 88.0);
$bCertIdFontSize = (int)($bulkTemplate['cert_id_font_size'] ?? 10);
$bCertIdColor = htmlspecialchars($bulkTemplate['cert_id_color'] ?? '#64748B');
if (!str_starts_with($bCertIdColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $bCertIdColor)) {
    $bCertIdColor = '#' . $bCertIdColor;
}

$bShowIssueDate = (int)($bulkTemplate['show_issue_date'] ?? 1);
$bIssueDateText = htmlspecialchars($bulkTemplate['issue_date_text'] ?? '12–13 September 2026');
$bIssueDatePosX = (float)($bulkTemplate['issue_date_pos_x'] ?? 28.0);
$bIssueDatePosY = (float)($bulkTemplate['issue_date_pos_y'] ?? 88.0);
$bIssueDateFontSize = (int)($bulkTemplate['issue_date_font_size'] ?? 10);
$bIssueDateColor = htmlspecialchars($bulkTemplate['issue_date_color'] ?? '#1E293B');
if (!str_starts_with($bIssueDateColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $bIssueDateColor)) {
    $bIssueDateColor = '#' . $bIssueDateColor;
}
$bIssueDateFontFamily = htmlspecialchars($bulkTemplate['issue_date_font_family'] ?? 'Inter');

$bShowQr = (int)($bulkTemplate['show_qr'] ?? 1);
$bQrPosX = (float)($bulkTemplate['qr_pos_x'] ?? 88.0);
$bQrPosY = (float)($bulkTemplate['qr_pos_y'] ?? 84.0);
$bQrSize = (int)($bulkTemplate['qr_size'] ?? 75);

// Fetch all eligible valid certificates for active cohort
$bulkEligibleCerts = dbFetchAll("
    SELECT 
        c.id as cert_id,
        c.certificate_token,
        c.document_hash,
        c.issued_at,
        p.id as participant_id,
        p.full_name,
        p.email,
        p.status as participant_status,
        c.status as cert_status
    FROM certificates c
    JOIN participants p ON c.participant_id = p.id
    WHERE p.cohort_id = ? AND c.status = 'valid' AND p.status != 'revoked'
    ORDER BY p.full_name ASC
", [$cohortId]);

$bulkCertsCount = count($bulkEligibleCerts);

$verifyBaseUrl = (function_exists('getAppBaseUrl') ? getAppBaseUrl() : '') . '/public/verify.php?token=';
?>

<!-- Bulk Certificate Download Modal -->
<div id="bulkDownloadModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-200">
  <div class="bg-surface-container-lowest rounded-2xl shadow-2xl max-w-xl w-full border border-surface-container flex flex-col overflow-hidden transform transition-transform scale-95" id="bulkDownloadModalBox">
    
    <!-- Modal Header -->
    <div class="p-6 bg-surface-container-low/60 border-b border-surface-container flex items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-[28px]">download_for_offline</span>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Bulk Certificate Export</h3>
            <span class="px-2 py-0.5 rounded-full bg-primary-container text-on-primary font-caption text-caption font-semibold">
              <?= $bulkCertsCount ?> Ready
            </span>
          </div>
          <p class="font-caption text-caption text-secondary mt-0.5">
            <?= htmlspecialchars($activeCohort['name']) ?> • <span class="font-mono"><?= htmlspecialchars($activeCohort['batch_code']) ?></span>
          </p>
        </div>
      </div>
      <button type="button" onclick="closeBulkDownloadModal()" class="p-1 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container transition-colors">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <!-- Modal Body -->
    <div class="p-6 flex flex-col gap-5">

      <?php if ($bulkCertsCount === 0): ?>
        <div class="py-8 px-4 text-center flex flex-col items-center justify-center gap-3 bg-surface-container-low/40 rounded-xl border border-surface-container">
          <span class="material-symbols-outlined text-[42px] text-outline">folder_off</span>
          <h4 class="font-headline-sm text-headline-sm font-semibold text-on-surface">No Valid Certificates Found</h4>
          <p class="font-body-sm text-body-sm text-secondary max-w-sm">
            There are currently no active, valid certificates in this cohort to export. Issue certificates to enrolled participants first.
          </p>
          <a href="participants_management.php" class="mt-2 px-4 py-2 bg-primary text-on-primary rounded-lg text-label-md font-medium hover:bg-primary-container transition-colors">
            Go to Participants Ledger
          </a>
        </div>
      <?php else: ?>

        <!-- Format Options -->
        <div class="flex flex-col gap-2">
          <span class="font-label-sm text-label-sm uppercase font-semibold text-outline tracking-wider">Select Export Format</span>
          
          <!-- Option A: Multi-Page PDF -->
          <label class="relative flex items-start gap-3 p-4 rounded-xl border-2 border-primary bg-primary/5 cursor-pointer hover:bg-primary/10 transition-colors select-none" id="labelFormatPdf">
            <input type="radio" name="bulkFormat" value="pdf" checked class="mt-1 text-primary focus:ring-0 cursor-pointer accent-primary" onchange="updateFormatSelection('pdf')"/>
            <div class="flex flex-col flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2">
                <span class="font-label-md text-label-md font-bold text-on-surface flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-[18px] text-error">picture_as_pdf</span>
                  All-in-One Multi-Page PDF
                </span>
                <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-tertiary font-caption text-caption font-semibold">
                  Best for Printing
                </span>
              </div>
              <p class="font-body-sm text-body-sm text-on-surface-variant mt-1 leading-relaxed">
                Combines all <strong><?= $bulkCertsCount ?> certificates</strong> into a single high-resolution A4 document. Each student receives a consecutive page. Recommended for graduation ceremonies and color print shops.
              </p>
            </div>
          </label>

          <!-- Option B: ZIP Archive -->
          <label class="relative flex items-start gap-3 p-4 rounded-xl border border-surface-container bg-surface-container-lowest cursor-pointer hover:bg-surface-container-low transition-colors select-none" id="labelFormatZip">
            <input type="radio" name="bulkFormat" value="zip" class="mt-1 text-primary focus:ring-0 cursor-pointer accent-primary" onchange="updateFormatSelection('zip')"/>
            <div class="flex flex-col flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2">
                <span class="font-label-md text-label-md font-bold text-on-surface flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-[18px] text-primary">folder_zip</span>
                  ZIP Archive of Individual PDFs
                </span>
                <span class="px-2 py-0.5 rounded-full bg-surface-container text-secondary font-caption text-caption font-semibold">
                  Best for Archive
                </span>
              </div>
              <p class="font-body-sm text-body-sm text-on-surface-variant mt-1 leading-relaxed">
                Compresses individual PDF files into a single <code class="font-mono text-primary font-bold">.zip</code> archive. Each certificate is individually titled with the recipient's name (e.g. <code>Certificate_Ahmed_Omar.pdf</code>).
              </p>
            </div>
          </label>
        </div>

        <!-- Specifications Badge Bar -->
        <div class="p-3 rounded-xl bg-surface-container-low/50 border border-surface-container flex items-center justify-between text-caption text-outline">
          <span class="flex items-center gap-1">
            <span class="material-symbols-outlined text-[15px] text-tertiary">verified</span>
            Template: <strong class="text-on-surface font-medium"><?= $bulkTemplateName ?></strong>
          </span>
          <span class="font-mono text-[11px]">A4 Landscape (300 DPI)</span>
        </div>

        <!-- Live Progress Section (Hidden initially) -->
        <div id="bulkProgressSection" class="hidden flex flex-col gap-2 p-4 rounded-xl bg-surface-container-low border border-surface-container">
          <div class="flex items-center justify-between">
            <span class="font-label-sm text-label-sm font-semibold text-on-surface flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[16px] animate-spin text-primary">progress_activity</span>
              <span id="bulkProgressStatus">Preparing certificate renderer...</span>
            </span>
            <span class="font-mono text-label-sm text-label-sm font-bold text-primary" id="bulkProgressPercent">0%</span>
          </div>
          <div class="w-full bg-surface-container h-2.5 rounded-full overflow-hidden">
            <div id="bulkProgressBar" class="bg-primary h-full rounded-full transition-all duration-150 w-0"></div>
          </div>
          <span class="font-caption text-caption text-secondary truncate" id="bulkProgressRecipient">Initializing...</span>
        </div>

      <?php endif; ?>

    </div>

    <!-- Modal Footer -->
    <div class="p-4 px-6 bg-surface-container-low/60 border-t border-surface-container flex items-center justify-end gap-2">
      <button type="button" onclick="closeBulkDownloadModal()" id="bulkCancelBtn" class="px-4 py-2 rounded-lg font-label-md text-label-md text-secondary hover:bg-surface-container transition-colors">
        Cancel
      </button>
      <?php if ($bulkCertsCount > 0): ?>
        <button type="button" onclick="startBulkExportProcess()" id="bulkStartBtn" class="px-5 py-2.5 rounded-lg bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-semibold transition-all shadow-sm active:scale-95 flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">download</span>
          <span>Start Export (<?= $bulkCertsCount ?>)</span>
        </button>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Hidden Canvas and QR Sandbox for Offscreen Batch Rendering -->
<div id="bulkHiddenSandbox" class="fixed -left-[9999px] -top-[9999px] pointer-events-none opacity-0" aria-hidden="true">
  <img id="bulkPreloadTemplateImg" src="<?= htmlspecialchars($bulkTemplateImage) ?>" alt="Preload Template" crossorigin="anonymous"/>
  <div id="bulkQrContainer"></div>
</div>

<script>
  // Bulk Export Certificates Data payload
  const BULK_EXPORT_DATA = {
    batchCode: <?= json_encode($activeCohort['batch_code']) ?>,
    cohortName: <?= json_encode($activeCohort['name']) ?>,
    verifyBaseUrl: <?= json_encode($verifyBaseUrl) ?>,
    template: {
      imageSrc: <?= json_encode($bulkTemplateImage) ?>,
      namePosX: <?= $bNamePosX ?>,
      namePosY: <?= $bNamePosY ?>,
      nameFontSize: <?= $bNameFontSize ?>,
      nameFontFamily: <?= json_encode($bNameFontFamily) ?>,
      nameFontColor: <?= json_encode($bNameFontColor) ?>,
      nameTextAlign: <?= json_encode($bNameTextAlign) ?>,
      showCertId: <?= $bShowCertId ?>,
      showCertIdPrefix: <?= $bShowCertIdPrefix ?>,
      certIdPosX: <?= $bCertIdPosX ?>,
      certIdPosY: <?= $bCertIdPosY ?>,
      certIdFontSize: <?= $bCertIdFontSize ?>,
      certIdColor: <?= json_encode($bCertIdColor) ?>,
      showIssueDate: <?= $bShowIssueDate ?>,
      issueDateText: <?= json_encode($bIssueDateText) ?>,
      issueDatePosX: <?= $bIssueDatePosX ?>,
      issueDatePosY: <?= $bIssueDatePosY ?>,
      issueDateFontSize: <?= $bIssueDateFontSize ?>,
      issueDateColor: <?= json_encode($bIssueDateColor) ?>,
      issueDateFontFamily: <?= json_encode($bIssueDateFontFamily) ?>,
      showQr: <?= $bShowQr ?>,
      qrPosX: <?= $bQrPosX ?>,
      qrPosY: <?= $bQrPosY ?>,
      qrSize: <?= $bQrSize ?>
    },
    certificates: <?= json_encode(array_values($bulkEligibleCerts), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
  };

  let isBulkExportRunning = false;
  let selectedExportFormat = 'pdf';

  function updateFormatSelection(format) {
    selectedExportFormat = format;
    const labelPdf = document.getElementById('labelFormatPdf');
    const labelZip = document.getElementById('labelFormatZip');
    if (format === 'pdf') {
      labelPdf?.classList.add('border-primary', 'bg-primary/5');
      labelPdf?.classList.remove('border-surface-container', 'bg-surface-container-lowest');
      labelZip?.classList.remove('border-primary', 'bg-primary/5');
      labelZip?.classList.add('border-surface-container', 'bg-surface-container-lowest');
    } else {
      labelZip?.classList.add('border-primary', 'bg-primary/5');
      labelZip?.classList.remove('border-surface-container', 'bg-surface-container-lowest');
      labelPdf?.classList.remove('border-primary', 'bg-primary/5');
      labelPdf?.classList.add('border-surface-container', 'bg-surface-container-lowest');
    }
  }

  function openBulkDownloadModal() {
    const modal = document.getElementById('bulkDownloadModal');
    const box = document.getElementById('bulkDownloadModalBox');
    if (!modal) return;
    modal.classList.remove('hidden');
    setTimeout(() => {
      modal.classList.remove('opacity-0');
      box?.classList.remove('scale-95');
    }, 10);
  }

  function closeBulkDownloadModal() {
    if (isBulkExportRunning) {
      if (!confirm('Export is currently in progress. Do you want to cancel?')) {
        return;
      }
      isBulkExportRunning = false;
    }
    const modal = document.getElementById('bulkDownloadModal');
    const box = document.getElementById('bulkDownloadModalBox');
    if (!modal) return;
    modal.classList.add('opacity-0');
    box?.classList.add('scale-95');
    setTimeout(() => {
      modal.classList.add('hidden');
      resetBulkModalState();
    }, 200);
  }

  function resetBulkModalState() {
    const progressSection = document.getElementById('bulkProgressSection');
    const startBtn = document.getElementById('bulkStartBtn');
    const cancelBtn = document.getElementById('bulkCancelBtn');
    if (progressSection) progressSection.classList.add('hidden');
    if (startBtn) {
      startBtn.disabled = false;
      startBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">download</span><span>Start Export (' + BULK_EXPORT_DATA.certificates.length + ')</span>';
    }
    if (cancelBtn) cancelBtn.disabled = false;
  }

  // Backdrop / Escape keys
  document.getElementById('bulkDownloadModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeBulkDownloadModal();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeBulkDownloadModal();
  });

  // Render individual certificate high-res canvas (3508 x 2480 at 300 DPI)
  async function renderSingleCertCanvas(templateImg, cert) {
    const canvas = document.createElement('canvas');
    let targetWidth = 3508;
    let targetHeight = 2480;

    if (templateImg && templateImg.naturalWidth && templateImg.naturalWidth > 100) {
      targetWidth = templateImg.naturalWidth;
      targetHeight = templateImg.naturalHeight;
    }

    canvas.width = targetWidth;
    canvas.height = targetHeight;
    const ctx = canvas.getContext('2d');

    // 1. Draw Template background
    if (templateImg && templateImg.complete && templateImg.naturalWidth !== 0) {
      ctx.drawImage(templateImg, 0, 0, targetWidth, targetHeight);
    } else {
      ctx.fillStyle = '#FFFFFF';
      ctx.fillRect(0, 0, targetWidth, targetHeight);
    }

    const t = BULK_EXPORT_DATA.template;

    // 2. Draw Recipient Name
    const namePosX = (t.namePosX / 100) * targetWidth;
    const namePosY = (t.namePosY / 100) * targetHeight;
    const baseFontSize = t.nameFontSize;
    const scaledFontSize = Math.round(targetWidth * ((baseFontSize * 0.5) / 842));
    ctx.font = `600 ${scaledFontSize}px '${t.nameFontFamily}', sans-serif, serif`;
    ctx.fillStyle = t.nameFontColor;
    ctx.textAlign = t.nameTextAlign;
    ctx.textBaseline = 'middle';
    ctx.fillText(cert.full_name, namePosX, namePosY);

    // 3. Draw Certificate ID
    if (t.showCertId) {
      const certIdPosX = (t.certIdPosX / 100) * targetWidth;
      const certIdPosY = (t.certIdPosY / 100) * targetHeight;
      const certIdFontSize = Math.round(targetWidth * ((t.certIdFontSize || 10) / 842));
      ctx.font = `600 ${certIdFontSize}px monospace, 'Courier New', sans-serif`;
      ctx.fillStyle = t.certIdColor;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      const certIdText = (t.showCertIdPrefix ? 'Certificate ID: ' : '') + cert.certificate_token;
      ctx.fillText(certIdText, certIdPosX, certIdPosY);
    }

    // 3.5 Draw Issue Date
    if (t.showIssueDate && t.issueDateText) {
      const issueDatePosX = (t.issueDatePosX / 100) * targetWidth;
      const issueDatePosY = (t.issueDatePosY / 100) * targetHeight;
      const issueDateFontSize = Math.round(targetWidth * ((t.issueDateFontSize || 10) / 842));
      ctx.font = `500 ${issueDateFontSize}px '${t.issueDateFontFamily || 'Inter'}', sans-serif`;
      ctx.fillStyle = t.issueDateColor || '#1E293B';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(t.issueDateText, issueDatePosX, issueDatePosY);
    }

    // 4. Generate & Draw QR Code
    if (t.showQr && typeof QRCode !== 'undefined') {
      const qrBox = document.getElementById('bulkQrContainer');
      if (qrBox) {
        qrBox.innerHTML = '';
        const verifyUrl = BULK_EXPORT_DATA.verifyBaseUrl + encodeURIComponent(cert.certificate_token);
        new QRCode(qrBox, {
          text: verifyUrl,
          width: 160,
          height: 160,
          colorDark: '#000000',
          colorLight: '#FFFFFF',
          correctLevel: QRCode.CorrectLevel.M
        });

        // Give the QR a tiny moment to paint into canvas
        await new Promise(r => setTimeout(r, 20));
        const qrCanvas = qrBox.querySelector('canvas') || qrBox.querySelector('img');
        if (qrCanvas) {
          const qrPixelSize = (t.qrSize / 842) * targetWidth;
          const qrCenterX = (t.qrPosX / 100) * targetWidth;
          const qrCenterY = (t.qrPosY / 100) * targetHeight;
          const qrLeft = qrCenterX - (qrPixelSize / 2);
          const qrTop = qrCenterY - (qrPixelSize / 2);
          const qrPadding = qrPixelSize * 0.06;

          ctx.fillStyle = '#FFFFFF';
          ctx.fillRect(qrLeft - qrPadding, qrTop - qrPadding, qrPixelSize + (qrPadding * 2), qrPixelSize + (qrPadding * 2));
          ctx.drawImage(qrCanvas, qrLeft, qrTop, qrPixelSize, qrPixelSize);
        }
      }
    }

    return canvas;
  }

  // Main Export Pipeline
  async function startBulkExportProcess() {
    if (isBulkExportRunning) return;
    const certs = BULK_EXPORT_DATA.certificates;
    if (!certs || certs.length === 0) {
      alert('No certificates available to export.');
      return;
    }

    const progressSection = document.getElementById('bulkProgressSection');
    const progressBar = document.getElementById('bulkProgressBar');
    const progressPercent = document.getElementById('bulkProgressPercent');
    const progressStatus = document.getElementById('bulkProgressStatus');
    const progressRecipient = document.getElementById('bulkProgressRecipient');
    const startBtn = document.getElementById('bulkStartBtn');
    const cancelBtn = document.getElementById('bulkCancelBtn');

    isBulkExportRunning = true;
    startBtn.disabled = true;
    startBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span><span>Exporting...</span>';
    progressSection?.classList.remove('hidden');

    try {
      // Ensure fonts are ready
      if (document.fonts && document.fonts.ready) {
        await document.fonts.ready;
      }

      // Preload template image
      const templateImg = document.getElementById('bulkPreloadTemplateImg');
      if (templateImg && !templateImg.complete) {
        progressStatus.textContent = 'Loading certificate template...';
        await new Promise((resolve) => {
          templateImg.onload = resolve;
          templateImg.onerror = resolve;
          setTimeout(resolve, 2000);
        });
      }

      if (selectedExportFormat === 'pdf') {
        // --- MULTI-PAGE PDF GENERATION ---
        if (!window.jspdf || !window.jspdf.jsPDF) {
          throw new Error('jsPDF library is not loaded.');
        }

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
          orientation: 'landscape',
          unit: 'mm',
          format: 'a4'
        });

        for (let i = 0; i < certs.length; i++) {
          if (!isBulkExportRunning) return; // aborted

          const cert = certs[i];
          const pct = Math.round(((i + 1) / certs.length) * 100);

          progressBar.style.width = pct + '%';
          progressPercent.textContent = pct + '%';
          progressStatus.textContent = `Rendering page ${i + 1} of ${certs.length}...`;
          progressRecipient.textContent = `Participant: ${cert.full_name} (${cert.certificate_token})`;

          const certCanvas = await renderSingleCertCanvas(templateImg, cert);
          const imgData = certCanvas.toDataURL('image/jpeg', 0.95);

          if (i > 0) {
            pdf.addPage('a4', 'landscape');
          }
          pdf.addImage(imgData, 'JPEG', 0, 0, 297, 210, undefined, 'FAST');

          // Yield to UI loop for smooth animations
          await new Promise(r => setTimeout(r, 30));
        }

        progressStatus.textContent = 'Compiling Multi-Page PDF file...';
        await new Promise(r => setTimeout(r, 100));

        const filename = `Batch_${BULK_EXPORT_DATA.batchCode.replace(/[^a-zA-Z0-9_-]/g, '_')}_Certificates_All.pdf`;
        pdf.save(filename);

        if (typeof showGlobalToast === 'function') {
          showGlobalToast(`All ${certs.length} certificates exported to PDF successfully!`);
        } else {
          alert('Certificates exported successfully!');
        }

      } else {
        // --- ZIP ARCHIVE OF INDIVIDUAL PDFS ---
        if (typeof JSZip === 'undefined') {
          throw new Error('JSZip library is not loaded.');
        }
        if (!window.jspdf || !window.jspdf.jsPDF) {
          throw new Error('jsPDF library is not loaded.');
        }

        const zip = new JSZip();
        const { jsPDF } = window.jspdf;

        for (let i = 0; i < certs.length; i++) {
          if (!isBulkExportRunning) return;

          const cert = certs[i];
          const pct = Math.round(((i + 1) / certs.length) * 100);

          progressBar.style.width = pct + '%';
          progressPercent.textContent = pct + '%';
          progressStatus.textContent = `Packaging certificate ${i + 1} of ${certs.length}...`;
          progressRecipient.textContent = `Participant: ${cert.full_name}`;

          const certCanvas = await renderSingleCertCanvas(templateImg, cert);
          const imgData = certCanvas.toDataURL('image/jpeg', 0.95);

          const singlePdf = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4'
          });
          singlePdf.addImage(imgData, 'JPEG', 0, 0, 297, 210, undefined, 'FAST');
          const pdfBlob = singlePdf.output('blob');

          const safeName = cert.full_name.replace(/[^a-zA-Z0-9_-]/g, '_');
          zip.file(`Certificate_${safeName}_${cert.certificate_token}.pdf`, pdfBlob);

          await new Promise(r => setTimeout(r, 30));
        }

        progressStatus.textContent = 'Building compressed ZIP archive...';
        const zipBlob = await zip.generateAsync({ type: 'blob' }, (metadata) => {
          progressStatus.textContent = `Compressing archive (${Math.round(metadata.percent)}%)...`;
        });

        const zipUrl = URL.createObjectURL(zipBlob);
        const link = document.createElement('a');
        link.href = zipUrl;
        link.download = `Batch_${BULK_EXPORT_DATA.batchCode.replace(/[^a-zA-Z0-9_-]/g, '_')}_Certificates_ZIP.zip`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(zipUrl);

        if (typeof showGlobalToast === 'function') {
          showGlobalToast(`All ${certs.length} certificates packaged into ZIP successfully!`);
        } else {
          alert('ZIP archive created successfully!');
        }
      }

      // Close modal smoothly after completion
      setTimeout(() => {
        closeBulkDownloadModal();
      }, 1000);

    } catch (err) {
      console.error('Bulk export error:', err);
      alert('An error occurred during bulk export: ' + (err.message || err));
      resetBulkModalState();
    } finally {
      isBulkExportRunning = false;
    }
  }
</script>
