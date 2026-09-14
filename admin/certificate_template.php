<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdminAuth();
require_once __DIR__ . '/../includes/db.php';

// 1. Active Cohort & All Cohorts
$activeCohort = getActiveCohort();
$cohortId = !empty($_REQUEST['cohort_id']) ? (int)$_REQUEST['cohort_id'] : (int)$activeCohort['id'];
$allCohorts = dbFetchAll("SELECT * FROM cohorts ORDER BY id DESC");

if ($cohortId !== (int)$activeCohort['id']) {
    $found = dbFetchOne("SELECT * FROM cohorts WHERE id = ?", [$cohortId]);
    if ($found) {
        $activeCohort = $found;
        setActiveCohort($cohortId);
    } else {
        $cohortId = (int)$activeCohort['id'];
    }
}

// 2. Fetch or initialize active template for this cohort
$template = dbFetchOne("SELECT * FROM certificate_templates WHERE cohort_id = ? AND is_active = 1 LIMIT 1", [$cohortId]);
if (!$template) {
    $template = dbFetchOne("SELECT * FROM certificate_templates WHERE cohort_id = ? LIMIT 1", [$cohortId]);
}
if (!$template) {
    dbQuery("
        INSERT INTO certificate_templates (
            cohort_id, template_name, image_path, 
            name_pos_x, name_pos_y, name_font_size, name_font_family, name_font_color, name_text_align, 
            show_cert_id, cert_id_pos_x, cert_id_pos_y, cert_id_color, 
            show_qr, qr_pos_x, qr_pos_y, qr_size, is_active
        ) VALUES (
            ?, 'AI Workshop Standard Template', 'uploads/templates/default_blank_template.jpg',
            50.00, 39.50, 48, 'Playfair Display', '#000000', 'center',
            0, 50.00, 88.00, '#64748B',
            0, 88.00, 84.00, 80, 1
        )
    ", [$cohortId]);
    $template = dbFetchOne("SELECT * FROM certificate_templates WHERE id = ?", [dbLastInsertId()]);
}

$successMessage = null;
$errorMessage = null;

// 3. Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action A: Upload new template image
    if ($action === 'upload_image') {
        if (isset($_FILES['certificate_image']) && is_uploaded_file($_FILES['certificate_image']['tmp_name'])) {
            $file = $_FILES['certificate_image'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedExts)) {
                $errorMessage = 'Invalid file format. Please upload a PNG, JPG, or WebP image.';
            } elseif ($file['size'] > 12 * 1024 * 1024) {
                $errorMessage = 'Image exceeds the maximum allowed size of 12MB.';
            } else {
                if (!is_dir(__DIR__ . '/../uploads/templates')) {
                    mkdir(__DIR__ . '/../uploads/templates', 0777, true);
                }

                $newFilename = 'template_cohort_' . $cohortId . '_' . time() . '.' . $fileExt;
                $destPath = __DIR__ . '/../uploads/templates/' . $newFilename;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $relPath = 'uploads/templates/' . $newFilename;
                    dbQuery("UPDATE certificate_templates SET image_path = ? WHERE id = ?", [$relPath, $template['id']]);
                    $template['image_path'] = $relPath;
                    $successMessage = 'Certificate blank image uploaded and set as active template!';
                } else {
                    $errorMessage = 'Failed to save the uploaded image. Please check directory permissions.';
                }
            }
        } else {
            $errorMessage = 'No image file was received. Please select an image file to upload.';
        }
    }

    // Action B: Reset to default pre-packaged blank image
    elseif ($action === 'reset_default_image') {
        $defaultPath = 'uploads/templates/default_blank_template.jpg';
        dbQuery("UPDATE certificate_templates SET image_path = ?, name_pos_x = 50.0, name_pos_y = 39.5, name_font_family = 'Playfair Display', name_font_size = 48, name_font_color = '#000000' WHERE id = ?", [$defaultPath, $template['id']]);
        $template = dbFetchOne("SELECT * FROM certificate_templates WHERE id = ?", [$template['id']]);
        $successMessage = 'Default certificate blank restored successfully!';
    }

    // Action C: Save coordinates and typography settings
    elseif ($action === 'save_settings') {
        $templateName   = trim($_POST['template_name'] ?? $template['template_name']);
        $namePosX       = floatval($_POST['name_pos_x'] ?? 50.0);
        $namePosY       = floatval($_POST['name_pos_y'] ?? 40.0);
        $nameFontSize   = intval($_POST['name_font_size'] ?? 48);
        $nameFontFamily = trim($_POST['name_font_family'] ?? 'Playfair Display');
        $nameFontColor  = trim($_POST['name_font_color'] ?? '#000000');
        if (!str_starts_with($nameFontColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $nameFontColor)) {
            $nameFontColor = '#' . $nameFontColor;
        }
        $nameTextAlign  = in_array($_POST['name_text_align'] ?? '', ['left', 'center', 'right']) ? $_POST['name_text_align'] : 'center';

        // Certificate Number (ID) Settings
        $showCertId       = isset($_POST['show_cert_id']) ? 1 : 0;
        $showCertIdPrefix = isset($_POST['show_cert_id_prefix']) ? 1 : 0;
        $certIdPosX       = floatval($_POST['cert_id_pos_x'] ?? 50.0);
        $certIdPosY       = floatval($_POST['cert_id_pos_y'] ?? 88.0);
        $certIdColor      = trim($_POST['cert_id_color'] ?? '#64748B');
        $certIdFontSize   = intval($_POST['cert_id_font_size'] ?? 10);
        if ($certIdFontSize < 5 || $certIdFontSize > 36) $certIdFontSize = 10;
        if (!str_starts_with($certIdColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $certIdColor)) {
            $certIdColor = '#' . $certIdColor;
        }

        // Issued Date Settings
        $showIssueDate       = isset($_POST['show_issue_date']) ? 1 : 0;
        $issueDateText       = trim($_POST['issue_date_text'] ?? '12–13 September 2026');
        if (empty($issueDateText)) $issueDateText = '12–13 September 2026';
        $issueDatePosX       = floatval($_POST['issue_date_pos_x'] ?? 28.0);
        $issueDatePosY       = floatval($_POST['issue_date_pos_y'] ?? 88.0);
        $issueDateFontSize   = intval($_POST['issue_date_font_size'] ?? 10);
        if ($issueDateFontSize < 5 || $issueDateFontSize > 36) $issueDateFontSize = 10;
        $issueDateColor      = trim($_POST['issue_date_color'] ?? '#1E293B');
        if (!str_starts_with($issueDateColor, '#') && preg_match('/^[0-9a-fA-F]{3,6}$/', $issueDateColor)) {
            $issueDateColor = '#' . $issueDateColor;
        }
        $issueDateFontFamily = trim($_POST['issue_date_font_family'] ?? 'Inter');

        // Verification QR Code Settings
        $showQr         = isset($_POST['show_qr']) ? 1 : 0;
        $qrPosX         = floatval($_POST['qr_pos_x'] ?? 88.0);
        $qrPosY         = floatval($_POST['qr_pos_y'] ?? 84.0);
        $qrSize         = intval($_POST['qr_size'] ?? 75);

        dbQuery("
            UPDATE certificate_templates SET 
                template_name = ?,
                name_pos_x = ?, name_pos_y = ?, name_font_size = ?, name_font_family = ?, name_font_color = ?, name_text_align = ?,
                show_cert_id = ?, cert_id_pos_x = ?, cert_id_pos_y = ?, cert_id_color = ?, cert_id_font_size = ?, show_cert_id_prefix = ?,
                show_issue_date = ?, issue_date_text = ?, issue_date_pos_x = ?, issue_date_pos_y = ?, issue_date_font_size = ?, issue_date_color = ?, issue_date_font_family = ?,
                show_qr = ?, qr_pos_x = ?, qr_pos_y = ?, qr_size = ?
            WHERE id = ?
        ", [
            $templateName,
            $namePosX, $namePosY, $nameFontSize, $nameFontFamily, $nameFontColor, $nameTextAlign,
            $showCertId, $certIdPosX, $certIdPosY, $certIdColor, $certIdFontSize, $showCertIdPrefix,
            $showIssueDate, $issueDateText, $issueDatePosX, $issueDatePosY, $issueDateFontSize, $issueDateColor, $issueDateFontFamily,
            $showQr, $qrPosX, $qrPosY, $qrSize,
            $template['id']
        ]);

        $template = dbFetchOne("SELECT * FROM certificate_templates WHERE id = ?", [$template['id']]);
        $successMessage = 'Certificate template coordinates and styling saved successfully!';
    }
}

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

$page_title = 'Certificate Template Studio - CertificateHub';
$active_page = 'template';

include __DIR__ . '/../includes/head.php';
?>
<style>
  #stageQrCode, #modalQrCode, #printModalQrCode {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  #stageQrCode canvas, #stageQrCode img,
  #modalQrCode canvas, #modalQrCode img,
  #printModalQrCode canvas, #printModalQrCode img {
    max-width: 100% !important;
    max-height: 100% !important;
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important;
  }
  #stageQrCode [style*="display: none"],
  #modalQrCode [style*="display: none"],
  #printModalQrCode [style*="display: none"],
  #stageQrCode canvas[style*="display: none"],
  #stageQrCode img[style*="display: none"],
  #modalQrCode canvas[style*="display: none"],
  #modalQrCode img[style*="display: none"],
  #printModalQrCode canvas[style*="display: none"],
  #printModalQrCode img[style*="display: none"] {
    display: none !important;
  }
</style>
<body class="bg-surface font-body-md text-on-surface antialiased">
<?php
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="px-gutter-desktop py-space-lg max-w-[1440px] w-full mx-auto flex flex-col gap-space-lg">
  <!-- Top Navigation & Header Bar -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md pb-space-xs border-b border-surface-container">
    <div class="flex flex-col gap-space-2xs">
      <div class="flex items-center gap-space-xs">
        <span class="font-caption text-caption text-primary uppercase font-semibold tracking-wider">Template Designer</span>
        <span class="w-1.5 h-1.5 rounded-full bg-outline-variant"></span>
        <span class="font-caption text-caption text-secondary"><?= htmlspecialchars($activeCohort['batch_code']) ?> • <?= htmlspecialchars($activeCohort['name']) ?></span>
      </div>
      <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold tracking-tight">Certificate Template Studio</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Upload your blank certificate design and visually position the participant's name, font styling, and credentials.
      </p>
    </div>
    <div class="flex items-center gap-space-sm self-start md:self-auto flex-wrap">
      <!-- Workshop Switcher Dropdown -->
      <div class="flex items-center gap-space-xs bg-surface-container-lowest px-3 py-1.5 rounded-lg border border-outline-variant/30 shadow-sm">
        <span class="material-symbols-outlined text-[18px] text-primary">school</span>
        <label for="templateCohortSwitcher" class="font-label-sm text-caption text-secondary font-medium">Workshop:</label>
        <select id="templateCohortSwitcher" onchange="window.location.href='certificate_template.php?cohort_id=' + this.value" class="bg-transparent font-label-md text-label-md font-semibold text-on-surface focus:outline-none cursor-pointer pr-1">
          <?php foreach ($allCohorts as $c): ?>
            <?php 
              $cStatus = getCohortComputedStatus($c);
              $statusBadge = match($cStatus) {
                'active' => '🟢',
                'upcoming' => '🔵',
                'completed' => '⚪',
                'archived' => '📁',
                default => '⚪'
              };
            ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $cohortId) ? 'selected' : '' ?>>
              <?= $statusBadge ?> <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['batch_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="button" onclick="openLivePreviewModal()" class="flex items-center gap-space-xs h-10 px-space-md rounded-lg bg-surface-container-lowest hover:bg-surface-container text-on-surface font-label-md text-label-md font-medium transition-all shadow-sm border border-outline-variant/30 cursor-pointer">
        <span class="material-symbols-outlined text-[18px] text-primary">visibility</span>
        Live Preview
      </button>
      <button type="button" onclick="document.getElementById('settingsForm').submit();" class="flex items-center gap-space-xs h-10 px-space-lg rounded-lg bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-medium transition-all shadow-sm active:scale-95">
        <span class="material-symbols-outlined text-[18px]">save</span>
        Save Changes
      </button>
    </div>
  </div>

  <?php if ($successMessage): ?>
    <div class="p-space-sm px-space-md rounded-xl bg-tertiary-fixed/40 border border-tertiary/20 text-on-surface flex items-center justify-between shadow-sm">
      <div class="flex items-center gap-space-xs">
        <span class="material-symbols-outlined text-[20px] text-tertiary">check_circle</span>
        <span class="font-label-md text-label-md font-semibold"><?= htmlspecialchars($successMessage) ?></span>
      </div>
      <button onclick="this.parentElement.remove();" class="text-outline hover:text-on-surface"><span class="material-symbols-outlined text-[18px]">close</span></button>
    </div>
  <?php endif; ?>

  <?php if ($errorMessage): ?>
    <div class="p-space-sm px-space-md rounded-xl bg-error-container/40 border border-error/20 text-on-error-container flex items-center justify-between shadow-sm">
      <div class="flex items-center gap-space-xs">
        <span class="material-symbols-outlined text-[20px] text-error">error</span>
        <span class="font-label-md text-label-md font-semibold"><?= htmlspecialchars($errorMessage) ?></span>
      </div>
      <button onclick="this.parentElement.remove();" class="text-outline hover:text-on-surface"><span class="material-symbols-outlined text-[18px]">close</span></button>
    </div>
  <?php endif; ?>

  <!-- Main Studio Workspace (Split 2-Column Grid) -->
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-space-lg items-start">
    
    <!-- Left/Center Stage: Visual Interactive Canvas (xl:col-span-8) -->
    <div class="xl:col-span-8 flex flex-col gap-space-md">
      
      <!-- Stage Toolbar -->
      <div class="bg-surface-container-lowest p-space-sm px-space-md rounded-xl shadow-sm flex flex-wrap items-center justify-between gap-space-sm border border-surface-container-low">
        <div class="flex items-center gap-space-xs">
          <span class="font-label-sm text-label-sm text-outline font-medium">Test Name:</span>
          <select id="sampleNameSelect" class="h-8 px-space-xs text-caption font-medium rounded-lg bg-surface-container border border-surface-container-high focus:outline-none focus:ring-1 focus:ring-primary text-on-surface">
            <option value="Ahmed Omar Mohamed">Ahmed Omar Mohamed (Standard)</option>
            <option value="Dr. Alexander Montgomery-Smith III">Dr. Alexander Montgomery-Smith III (Long Name)</option>
            <option value="Jane Doe">Jane Doe (Short Name)</option>
            <option value="Maya Lin">Maya Lin</option>
            <option value="Rachel Adams">Rachel Adams</option>
          </select>
        </div>

        <div class="flex items-center gap-space-xs">
          <button type="button" id="toggleGridBtn" class="flex items-center gap-1 h-8 px-2.5 rounded-lg bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high font-label-sm text-caption transition-colors" title="Toggle alignment guides">
            <span class="material-symbols-outlined text-[16px]">grid_4x4</span>
            <span>Guidelines</span>
          </button>
          <button type="button" id="centerNameBtn" class="flex items-center gap-1 h-8 px-2.5 rounded-lg bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high font-label-sm text-caption transition-colors" title="Auto-center name horizontally">
            <span class="material-symbols-outlined text-[16px]">align_horizontal_center</span>
            <span>Center 50%</span>
          </button>
          <div class="w-px h-5 bg-surface-container-high mx-1"></div>
          <button type="button" id="zoomInBtn" class="p-1 rounded-lg text-on-surface-variant hover:bg-surface-container" title="Zoom In">
            <span class="material-symbols-outlined text-[18px]">zoom_in</span>
          </button>
          <button type="button" id="zoomOutBtn" class="p-1 rounded-lg text-on-surface-variant hover:bg-surface-container" title="Zoom Out">
            <span class="material-symbols-outlined text-[18px]">zoom_out</span>
          </button>
          <span id="zoomLevelLabel" class="font-mono text-caption text-outline px-1">100%</span>
        </div>
      </div>

      <!-- Interactive Canvas Stage Container -->
      <div class="relative w-full bg-surface-container-low/80 p-space-md md:p-space-lg rounded-2xl shadow-inner border border-surface-container overflow-hidden flex items-center justify-center min-h-[480px]">
        
        <!-- Physical Certificate Document Frame (A4 Landscape: 297mm x 210mm) -->
        <div id="certificateStage" class="relative w-full max-w-[842px] bg-white rounded-lg shadow-2xl select-none overflow-hidden transition-transform duration-150 origin-center" style="aspect-ratio: 297 / 210; container-type: inline-size;">
          
          <!-- Background Blank Certificate Image -->
          <img 
            id="templateBgImage" 
            src="../<?= htmlspecialchars($template['image_path']) ?>?v=<?= time() ?>" 
            alt="Blank Certificate Template" 
            class="absolute inset-0 w-full h-full object-fill pointer-events-none"
            onerror="this.src='../uploads/templates/default_blank_template.jpg';"
          />

          <!-- Interactive Alignment Guides Overlay (Hidden by default) -->
          <div id="alignmentGuides" class="absolute inset-0 pointer-events-none hidden">
            <!-- Center Vertical Guide -->
            <div class="absolute top-0 bottom-0 left-1/2 w-px bg-primary/40 -translate-x-1/2"></div>
            <!-- Center Horizontal Guide -->
            <div class="absolute left-0 right-0 top-1/2 h-px bg-primary/40 -translate-y-1/2"></div>
          </div>

          <!-- DRAGGABLE PARTICIPANT NAME ELEMENT -->
          <div 
            id="draggableName" 
            class="absolute cursor-move px-3 py-1 rounded border border-dashed border-primary/60 hover:border-primary hover:bg-primary/5 transition-colors group z-20"
            style="left: <?= $namePosX ?>%; top: <?= $namePosY ?>%; transform: translate(<?= $transformX ?>, -50%);"
            title="Click and drag to position participant name"
          >
            <!-- Positioning Pin & Drag Handle -->
            <div class="absolute -top-6 left-1/2 -translate-x-1/2 bg-primary text-on-primary font-mono text-[9px] font-bold px-1.5 py-0.5 rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none flex items-center gap-1">
              <span class="material-symbols-outlined text-[10px]">drag_pan</span>
              <span id="nameBadgeCoord"><?= number_format($namePosX, 1) ?>%, <?= number_format($namePosY, 1) ?>%</span>
            </div>

            <!-- Dynamic Name Text -->
            <div 
              id="nameTextDisplay" 
              class="whitespace-nowrap transition-all select-none leading-none tracking-tight font-semibold"
              style="font-family: '<?= $nameFontFamily ?>', sans-serif, serif; font-size: calc(<?= $nameFontSize * 0.5 ?> * 100cqw / 842); color: <?= $nameFontColor ?>; text-align: <?= $nameTextAlign ?>;"
            >
              Ahmed Omar Mohamed
            </div>

            <!-- Corner Anchors for visual precision -->
            <div class="absolute -top-1 -left-1 w-2 h-2 bg-primary rounded-full opacity-0 group-hover:opacity-100"></div>
            <div class="absolute -top-1 -right-1 w-2 h-2 bg-primary rounded-full opacity-0 group-hover:opacity-100"></div>
            <div class="absolute -bottom-1 -left-1 w-2 h-2 bg-primary rounded-full opacity-0 group-hover:opacity-100"></div>
            <div class="absolute -bottom-1 -right-1 w-2 h-2 bg-primary rounded-full opacity-0 group-hover:opacity-100"></div>
          </div>

          <!-- DRAGGABLE CERTIFICATE ID / NUMBER -->
          <div 
            id="draggableCertId" 
            class="absolute cursor-move px-2 py-0.5 rounded border border-dashed border-secondary/60 hover:border-secondary hover:bg-secondary/5 transition-colors group z-20 <?= ($showCertId ? '' : 'hidden') ?>"
            style="left: <?= $certIdPosX ?>%; top: <?= $certIdPosY ?>%; transform: translate(-50%, -50%);"
            title="Click and drag to position Certificate Number"
          >
            <div class="absolute -top-6 left-1/2 -translate-x-1/2 bg-secondary text-white font-mono text-[9px] font-bold px-1.5 py-0.5 rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
              Cert ID: <span id="certIdBadgeCoord"><?= number_format($certIdPosX, 1) ?>%, <?= number_format($certIdPosY, 1) ?>%</span>
            </div>
            <div 
              id="certIdTextDisplay" 
              class="font-mono font-semibold tracking-wider select-none leading-none whitespace-nowrap"
              style="color: <?= $certIdColor ?>; font-size: calc(<?= $certIdFontSize ?> * 100cqw / 842);"
            >
              <?= ($showCertIdPrefix ? 'Certificate ID: ' : '') ?>SIMAD-PU-2026-001
            </div>
          </div>

          <!-- DRAGGABLE ISSUED DATE -->
          <div 
            id="draggableIssueDate" 
            class="absolute cursor-move px-2 py-0.5 rounded border border-dashed border-emerald-600/60 hover:border-emerald-600 hover:bg-emerald-50/20 transition-colors group z-20 <?= ($showIssueDate ? '' : 'hidden') ?>"
            style="left: <?= $issueDatePosX ?>%; top: <?= $issueDatePosY ?>%; transform: translate(-50%, -50%);"
            title="Click and drag to position Issued Date"
          >
            <div class="absolute -top-6 left-1/2 -translate-x-1/2 bg-emerald-700 text-white font-mono text-[9px] font-bold px-1.5 py-0.5 rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
              Issued: <span id="issueDateBadgeCoord"><?= number_format($issueDatePosX, 1) ?>%, <?= number_format($issueDatePosY, 1) ?>%</span>
            </div>
            <div 
              id="issueDateTextDisplay" 
              class="font-medium tracking-wide select-none leading-none whitespace-nowrap"
              style="font-family: '<?= $issueDateFontFamily ?>', sans-serif; color: <?= $issueDateColor ?>; font-size: calc(<?= $issueDateFontSize ?> * 100cqw / 842);"
            >
              <?= htmlspecialchars($issueDateText) ?>
            </div>
          </div>

          <!-- DRAGGABLE VERIFICATION QR CODE -->
          <div 
            id="draggableQr" 
            class="absolute cursor-move p-1 bg-white rounded border border-dashed border-primary/60 hover:border-primary shadow-sm hover:shadow-md transition-all group z-20 <?= ($showQr ? '' : 'hidden') ?>"
            style="left: <?= $qrPosX ?>%; top: <?= $qrPosY ?>%; transform: translate(-50%, -50%); width: calc(<?= $qrSize ?> * 100cqw / 842); height: calc(<?= $qrSize ?> * 100cqw / 842);"
            title="Click and drag to position verification QR code"
          >
            <div class="absolute -top-6 left-1/2 -translate-x-1/2 bg-primary text-on-primary font-mono text-[9px] font-bold px-1.5 py-0.5 rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none flex items-center gap-1">
              QR: <span id="qrBadgeCoord"><?= number_format($qrPosX, 1) ?>%, <?= number_format($qrPosY, 1) ?>%</span>
            </div>
            <div id="stageQrCode" class="w-full h-full flex items-center justify-center overflow-hidden select-none">
              <!-- Dynamically populated with actual high-res scannable QR code -->
            </div>
          </div>

        </div>
      </div>

      <!-- Helper info footer -->
      <div class="flex items-center justify-between text-caption text-outline px-1">
        <span class="flex items-center gap-1">
          <span class="material-symbols-outlined text-[16px] text-primary">touch_app</span>
          Drag the recipient's name directly onto any position on your certificate.
        </span>
        <span class="font-mono">Format: A4 Landscape (297×210mm) • 300 DPI (3508×2480px)</span>
      </div>
    </div>

    <!-- Right Controls Panel: Upload & Positioning Studio Controls (xl:col-span-4) -->
    <div class="xl:col-span-4 flex flex-col gap-space-md">

      <!-- SECTION 1: UPLOAD CERTIFICATE IMAGE -->
      <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm border border-surface-container flex flex-col gap-space-sm">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-space-xs">
            <span class="material-symbols-outlined text-[20px] text-primary">image</span>
            <h2 class="font-headline-sm text-headline-sm font-semibold text-on-surface">Certificate Blank</h2>
          </div>
          <span class="font-caption text-caption text-tertiary font-semibold flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span> Active Blank
          </span>
        </div>

        <p class="font-caption text-caption text-on-surface-variant">
          Upload your high-res blank certificate image (PNG, JPG, WebP) with the name field empty.
        </p>

        <!-- Current File Badge -->
        <div class="p-2.5 rounded-lg bg-surface-container-low flex items-center justify-between font-mono text-caption text-on-surface truncate">
          <span class="truncate text-outline" title="<?= htmlspecialchars($template['image_path']) ?>">
            <?= htmlspecialchars(basename($template['image_path'])) ?>
          </span>
          <form method="POST" class="m-0 inline" onsubmit="return confirm('Restore default high-resolution blank template?');">
            <input type="hidden" name="action" value="reset_default_image">
            <input type="hidden" name="cohort_id" value="<?= $cohortId ?>">
            <button type="submit" class="text-primary hover:underline font-sans text-caption font-medium shrink-0 ml-2">Reset</button>
          </form>
        </div>

        <!-- Upload Form -->
        <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-space-xs m-0" id="imageUploadForm">
          <input type="hidden" name="action" value="upload_image">
          <input type="hidden" name="cohort_id" value="<?= $cohortId ?>">
          <label class="relative flex flex-col items-center justify-center p-space-md rounded-xl border-2 border-dashed border-surface-container-high hover:border-primary/60 bg-surface-container-low/40 hover:bg-surface-container-low transition-all cursor-pointer text-center group">
            <input type="file" name="certificate_image" accept=".png,.jpg,.jpeg,.webp" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" onchange="document.getElementById('imageUploadForm').submit();">
            <span class="material-symbols-outlined text-[26px] text-primary group-hover:scale-110 transition-transform mb-1">file_upload</span>
            <span class="font-label-sm text-label-sm font-semibold text-on-surface">Click to upload new blank</span>
            <span class="font-caption text-caption text-outline">Supports PNG, JPG, WebP up to 12MB (A4 recommended)</span>
          </label>
        </form>
      </div>

      <!-- SECTION 2: POSITIONING & TYPOGRAPHY SETTINGS FORM -->
      <form method="POST" id="settingsForm" class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm border border-surface-container flex flex-col gap-space-md m-0">
        <input type="hidden" name="action" value="save_settings">
        <input type="hidden" name="cohort_id" value="<?= $cohortId ?>">

        <!-- Hidden Coordinate Inputs that sync with drag / sliders -->
        <input type="hidden" name="name_pos_x" id="inputNameX" value="<?= htmlspecialchars($template['name_pos_x']) ?>">
        <input type="hidden" name="name_pos_y" id="inputNameY" value="<?= htmlspecialchars($template['name_pos_y']) ?>">

        <!-- Header -->
        <div class="flex items-center justify-between border-b border-surface-container-low pb-space-xs">
          <div class="flex items-center gap-space-xs">
            <span class="material-symbols-outlined text-[20px] text-primary">text_fields</span>
            <h2 class="font-headline-sm text-headline-sm font-semibold text-on-surface">Participant Name Styling</h2>
          </div>
        </div>

        <!-- Template Name -->
        <div class="flex flex-col gap-1">
          <label class="font-caption text-caption font-semibold text-outline uppercase tracking-wider">Template Label</label>
          <input type="text" name="template_name" value="<?= htmlspecialchars($template['template_name']) ?>" class="h-9 px-space-sm rounded-lg bg-surface-container-low border border-surface-container font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-1 focus:ring-primary">
        </div>

        <!-- Font Family -->
        <div class="flex flex-col gap-1">
          <label class="font-caption text-caption font-semibold text-outline uppercase tracking-wider">Font Family</label>
          <select name="name_font_family" id="fontFamilySelect" class="h-10 px-space-sm rounded-lg bg-surface-container-low border border-surface-container font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-1 focus:ring-primary">
            <option value="Poppins" <?= ($template['name_font_family'] === 'Poppins') ? 'selected' : '' ?>>Poppins (Modern Geometric Sans)</option>
            <option value="Playfair Display" <?= ($template['name_font_family'] === 'Playfair Display') ? 'selected' : '' ?>>Playfair Display (Formal Serif - Recommended)</option>
            <option value="Cinzel" <?= ($template['name_font_family'] === 'Cinzel') ? 'selected' : '' ?>>Cinzel (Academic Classical)</option>
            <option value="Great Vibes" <?= ($template['name_font_family'] === 'Great Vibes') ? 'selected' : '' ?>>Great Vibes (Calligraphic Script)</option>
            <option value="Montserrat" <?= ($template['name_font_family'] === 'Montserrat') ? 'selected' : '' ?>>Montserrat (Modern Clean)</option>
            <option value="Inter" <?= ($template['name_font_family'] === 'Inter') ? 'selected' : '' ?>>Inter (Geometric Sans)</option>
          </select>
        </div>

        <!-- Font Size & Color -->
        <div class="grid grid-cols-2 gap-space-sm">
          <div class="flex flex-col gap-1">
            <div class="flex items-center justify-between">
              <label class="font-caption text-caption font-semibold text-outline uppercase tracking-wider">Font Size</label>
              <span id="fontSizeValue" class="font-mono text-caption text-primary font-semibold"><?= (int)$template['name_font_size'] ?>px</span>
            </div>
            <input type="range" name="name_font_size" id="fontSizeSlider" min="20" max="120" value="<?= (int)$template['name_font_size'] ?>" class="w-full accent-primary cursor-pointer">
          </div>

          <div class="flex flex-col gap-1">
            <label class="font-caption text-caption font-semibold text-outline uppercase tracking-wider">Font Color</label>
            <div class="flex items-center gap-space-xs">
              <input type="color" id="fontColorPicker" value="<?= htmlspecialchars($template['name_font_color']) ?>" class="w-9 h-9 p-0.5 rounded cursor-pointer border border-surface-container bg-surface-container-low">
              <input type="text" name="name_font_color" id="fontColorHex" value="<?= htmlspecialchars($template['name_font_color']) ?>" class="w-full h-9 px-2 font-mono text-caption rounded-lg bg-surface-container-low border border-surface-container uppercase">
            </div>
          </div>
        </div>

        <!-- Text Alignment -->
        <div class="flex flex-col gap-1">
          <label class="font-caption text-caption font-semibold text-outline uppercase tracking-wider">Text Alignment</label>
          <div class="grid grid-cols-3 gap-space-xs">
            <label class="flex items-center justify-center gap-1 h-9 rounded-lg border border-surface-container cursor-pointer font-label-sm text-caption transition-all <?= ($template['name_text_align'] === 'left') ? 'bg-primary text-on-primary font-semibold' : 'bg-surface-container-low text-on-surface' ?>">
              <input type="radio" name="name_text_align" value="left" class="hidden" <?= ($template['name_text_align'] === 'left') ? 'checked' : '' ?>>
              <span class="material-symbols-outlined text-[16px]">format_align_left</span> Left
            </label>
            <label class="flex items-center justify-center gap-1 h-9 rounded-lg border border-surface-container cursor-pointer font-label-sm text-caption transition-all <?= ($template['name_text_align'] === 'center') ? 'bg-primary text-on-primary font-semibold' : 'bg-surface-container-low text-on-surface' ?>">
              <input type="radio" name="name_text_align" value="center" class="hidden" <?= ($template['name_text_align'] === 'center') ? 'checked' : '' ?>>
              <span class="material-symbols-outlined text-[16px]">format_align_center</span> Center
            </label>
            <label class="flex items-center justify-center gap-1 h-9 rounded-lg border border-surface-container cursor-pointer font-label-sm text-caption transition-all <?= ($template['name_text_align'] === 'right') ? 'bg-primary text-on-primary font-semibold' : 'bg-surface-container-low text-on-surface' ?>">
              <input type="radio" name="name_text_align" value="right" class="hidden" <?= ($template['name_text_align'] === 'right') ? 'checked' : '' ?>>
              <span class="material-symbols-outlined text-[16px]">format_align_right</span> Right
            </label>
          </div>
        </div>

        <!-- Fine Precision Coordinates: Recipient Name Position -->
        <div class="p-space-sm rounded-lg bg-surface-container-low/60 flex flex-col gap-space-sm border border-surface-container">
          <div class="flex items-center justify-between">
            <label class="font-caption text-caption font-semibold text-outline uppercase tracking-wider">Name Position (Coordinates)</label>
            <span id="nameBadgeCoordDisplay" class="font-mono text-[11px] text-primary font-bold"><?= number_format($namePosX, 1) ?>%, <?= number_format($namePosY, 1) ?>%</span>
          </div>
          <div class="flex items-center justify-between gap-2">
            <div class="flex-1 flex flex-col gap-1">
              <div class="flex items-center justify-between text-[11px] font-mono text-outline">
                <span>X-Axis:</span>
                <span id="nameXVal" class="text-on-surface font-semibold"><?= number_format($namePosX, 1) ?>%</span>
              </div>
              <input type="range" id="sliderNameX" min="0" max="100" step="0.1" value="<?= htmlspecialchars($namePosX) ?>" class="accent-primary cursor-pointer w-full">
            </div>
            <div class="flex-1 flex flex-col gap-1">
              <div class="flex items-center justify-between text-[11px] font-mono text-outline">
                <span>Y-Axis:</span>
                <span id="nameYVal" class="text-on-surface font-semibold"><?= number_format($namePosY, 1) ?>%</span>
              </div>
              <input type="range" id="sliderNameY" min="0" max="100" step="0.1" value="<?= htmlspecialchars($namePosY) ?>" class="accent-primary cursor-pointer w-full">
            </div>
          </div>
          <div class="flex items-center justify-between pt-0.5">
            <button type="button" id="centerNameBtn2" class="flex items-center gap-1 text-[11px] text-primary hover:underline font-medium cursor-pointer">
              <span class="material-symbols-outlined text-[14px]">align_horizontal_center</span> Center Horizontally (50%)
            </button>
            <span class="font-mono text-[10px] text-outline">0.0% – 100.0%</span>
          </div>
        </div>

        <!-- SECTION: CERTIFICATE NUMBER (ID) CONTROLS -->
        <div class="p-space-sm rounded-lg bg-surface-container-low/60 flex flex-col gap-space-sm border border-surface-container">
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="show_cert_id" id="toggleCertIdCheckbox" value="1" class="w-4 h-4 rounded accent-primary cursor-pointer" <?= $showCertId ? 'checked' : '' ?>>
              <span class="font-label-sm text-label-sm font-semibold text-on-surface">Show Certificate Number</span>
            </label>
            <span class="font-caption text-[11px] text-outline font-mono">0.0% – 100.0%</span>
          </div>

          <input type="hidden" name="cert_id_pos_x" id="inputCertIdX" value="<?= htmlspecialchars($certIdPosX) ?>">
          <input type="hidden" name="cert_id_pos_y" id="inputCertIdY" value="<?= htmlspecialchars($certIdPosY) ?>">

          <div id="certIdControlsWrapper" class="flex flex-col gap-space-xs <?= ($showCertId ? '' : 'opacity-50 pointer-events-none') ?>">
            <div class="flex items-center justify-between gap-2">
              <div class="flex-1 flex flex-col gap-1">
                <span class="font-mono text-[11px] text-outline">X-Pos:</span>
                <input type="range" id="sliderCertIdX" min="0" max="100" step="0.1" value="<?= htmlspecialchars($certIdPosX) ?>" class="accent-primary cursor-pointer">
              </div>
              <div class="flex-1 flex flex-col gap-1">
                <span class="font-mono text-[11px] text-outline">Y-Pos:</span>
                <input type="range" id="sliderCertIdY" min="0" max="100" step="0.1" value="<?= htmlspecialchars($certIdPosY) ?>" class="accent-primary cursor-pointer">
              </div>
            </div>
            <div class="flex items-center justify-between pt-1">
              <span class="font-caption text-caption text-outline">Color:</span>
              <div class="flex items-center gap-2">
                <input type="color" id="certIdColorPicker" value="<?= htmlspecialchars($certIdColor) ?>" class="w-7 h-7 p-0.5 rounded cursor-pointer border border-surface-container bg-surface-container-low">
                <input type="text" name="cert_id_color" id="certIdColorHex" value="<?= htmlspecialchars($certIdColor) ?>" class="w-24 h-7 px-2 font-mono text-caption rounded bg-surface-container-low border border-surface-container uppercase">
              </div>
            </div>
            <div class="flex items-center justify-between pt-1 font-mono text-caption">
              <span class="text-outline">Font Size:</span>
              <div class="flex items-center gap-2">
                <input type="range" name="cert_id_font_size" id="sliderCertIdFontSize" min="5" max="24" value="<?= htmlspecialchars($certIdFontSize) ?>" class="w-32 accent-primary cursor-pointer">
                <span id="certIdFontSizeVal" class="font-bold text-primary"><?= (int)$certIdFontSize ?>px</span>
              </div>
            </div>
            <div class="flex items-center justify-between pt-1">
              <span class="font-caption text-caption text-outline">Show Label:</span>
              <label class="flex items-center gap-1.5 cursor-pointer text-caption text-on-surface">
                <input type="checkbox" name="show_cert_id_prefix" id="toggleCertIdPrefix" value="1" class="w-3.5 h-3.5 rounded accent-primary cursor-pointer" <?= $showCertIdPrefix ? 'checked' : '' ?>>
                <span>Include "Certificate ID:" Prefix</span>
              </label>
            </div>
          </div>
        </div>

        <!-- SECTION: ISSUED DATE CONTROLS -->
        <div class="p-space-sm rounded-lg bg-surface-container-low/60 flex flex-col gap-space-sm border border-surface-container">
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="show_issue_date" id="toggleIssueDateCheckbox" value="1" class="w-4 h-4 rounded accent-primary cursor-pointer" <?= $showIssueDate ? 'checked' : '' ?>>
              <span class="font-label-sm text-label-sm font-semibold text-on-surface">Show Issued Date</span>
            </label>
            <span class="font-caption text-[11px] text-outline font-mono">0.0% – 100.0%</span>
          </div>

          <input type="hidden" name="issue_date_pos_x" id="inputIssueDateX" value="<?= htmlspecialchars($issueDatePosX) ?>">
          <input type="hidden" name="issue_date_pos_y" id="inputIssueDateY" value="<?= htmlspecialchars($issueDatePosY) ?>">

          <div id="issueDateControlsWrapper" class="flex flex-col gap-space-xs <?= ($showIssueDate ? '' : 'opacity-50 pointer-events-none') ?>">
            <!-- Custom Date Text Input -->
            <div class="flex flex-col gap-1">
              <label class="font-caption text-[11px] text-outline" for="inputIssueDateText">Custom Date Text:</label>
              <input type="text" name="issue_date_text" id="inputIssueDateText" value="<?= htmlspecialchars($issueDateText) ?>" placeholder="e.g. 12–13 September 2026" class="w-full h-8 px-2 text-caption rounded bg-surface-container-low border border-surface-container text-on-surface focus:outline-none focus:ring-1 focus:ring-primary">
            </div>

            <!-- Position Sliders -->
            <div class="flex items-center justify-between gap-2 pt-1">
              <div class="flex-1 flex flex-col gap-1">
                <span class="font-mono text-[11px] text-outline">X-Pos:</span>
                <input type="range" id="sliderIssueDateX" min="0" max="100" step="0.1" value="<?= htmlspecialchars($issueDatePosX) ?>" class="accent-primary cursor-pointer">
              </div>
              <div class="flex-1 flex flex-col gap-1">
                <span class="font-mono text-[11px] text-outline">Y-Pos:</span>
                <input type="range" id="sliderIssueDateY" min="0" max="100" step="0.1" value="<?= htmlspecialchars($issueDatePosY) ?>" class="accent-primary cursor-pointer">
              </div>
            </div>

            <!-- Color Picker -->
            <div class="flex items-center justify-between pt-1">
              <span class="font-caption text-caption text-outline">Color:</span>
              <div class="flex items-center gap-2">
                <input type="color" id="issueDateColorPicker" value="<?= htmlspecialchars($issueDateColor) ?>" class="w-7 h-7 p-0.5 rounded cursor-pointer border border-surface-container bg-surface-container-low">
                <input type="text" name="issue_date_color" id="issueDateColorHex" value="<?= htmlspecialchars($issueDateColor) ?>" class="w-24 h-7 px-2 font-mono text-caption rounded bg-surface-container-low border border-surface-container uppercase">
              </div>
            </div>

            <!-- Font Size -->
            <div class="flex items-center justify-between pt-1 font-mono text-caption">
              <span class="text-outline">Font Size:</span>
              <div class="flex items-center gap-2">
                <input type="range" name="issue_date_font_size" id="sliderIssueDateFontSize" min="5" max="24" value="<?= htmlspecialchars($issueDateFontSize) ?>" class="w-32 accent-primary cursor-pointer">
                <span id="issueDateFontSizeVal" class="font-bold text-primary"><?= (int)$issueDateFontSize ?>px</span>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION: VERIFICATION QR CODE CONTROLS -->
        <div class="p-space-sm rounded-lg bg-surface-container-low/60 flex flex-col gap-space-sm border border-surface-container">
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="show_qr" id="toggleQrCheckbox" value="1" class="w-4 h-4 rounded accent-primary cursor-pointer" <?= $showQr ? 'checked' : '' ?>>
              <span class="font-label-sm text-label-sm font-semibold text-on-surface">Show Scannable QR Code</span>
            </label>
            <span class="font-caption text-[11px] text-tertiary font-mono font-semibold">Verification</span>
          </div>

          <input type="hidden" name="qr_pos_x" id="inputQrX" value="<?= htmlspecialchars($qrPosX) ?>">
          <input type="hidden" name="qr_pos_y" id="inputQrY" value="<?= htmlspecialchars($qrPosY) ?>">
          <input type="hidden" name="qr_size" id="inputQrSize" value="<?= htmlspecialchars($qrSize) ?>">

          <div id="qrControlsWrapper" class="flex flex-col gap-space-xs <?= ($showQr ? '' : 'opacity-50 pointer-events-none') ?>">
            <div class="flex items-center justify-between gap-2">
              <div class="flex-1 flex flex-col gap-1">
                <span class="font-mono text-[11px] text-outline">X-Pos:</span>
                <input type="range" id="sliderQrX" min="0" max="100" step="0.1" value="<?= htmlspecialchars($qrPosX) ?>" class="accent-primary cursor-pointer">
              </div>
              <div class="flex-1 flex flex-col gap-1">
                <span class="font-mono text-[11px] text-outline">Y-Pos:</span>
                <input type="range" id="sliderQrY" min="0" max="100" step="0.1" value="<?= htmlspecialchars($qrPosY) ?>" class="accent-primary cursor-pointer">
              </div>
            </div>
            <div class="flex items-center justify-between pt-1 font-mono text-caption">
              <span class="text-outline">QR Size:</span>
              <div class="flex items-center gap-2">
                <input type="range" id="sliderQrSize" min="40" max="140" value="<?= htmlspecialchars($qrSize) ?>" class="w-32 accent-primary cursor-pointer">
                <span id="qrSizeVal" class="font-bold text-primary"><?= (int)$qrSize ?>px</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom Save Button -->
        <div class="pt-space-xs">
          <button type="submit" class="w-full h-11 rounded-lg bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-semibold transition-all shadow-sm flex items-center justify-center gap-space-xs active:scale-95">
            <span class="material-symbols-outlined text-[18px]">save</span>
            Save Template Settings
          </button>
        </div>
      </form>

    </div>

  </div>
</div>

<!-- LIVE PREVIEW FULL MODAL (Loads Current Unsaved Changes in Real-Time) -->
<div id="livePreviewModal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm hidden flex items-center justify-center p-2 sm:p-4">
  <div class="bg-surface-container-lowest rounded-2xl shadow-2xl border border-surface-container-high w-full max-w-6xl h-[92vh] flex flex-col overflow-hidden animate-in fade-in duration-200">
    <!-- Modal Header -->
    <div class="px-6 py-3.5 border-b border-surface-container flex items-center justify-between bg-surface-container-low shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
          <span class="material-symbols-outlined text-[22px]">visibility</span>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Live Certificate Preview</h3>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">Live Sync</span>
          </div>
          <p class="font-caption text-caption text-on-surface-variant">Real-time preview of your unsaved styling, font choices, and coordinate placements.</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" onclick="openStandaloneLivePreview()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-label-sm text-caption font-medium transition-colors cursor-pointer" title="Open in dedicated tab for printing">
          <span class="material-symbols-outlined text-[16px]">open_in_new</span>
          <span>Open Full Tab / Print</span>
        </button>
        <button type="button" onclick="closeLivePreviewModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-outline hover:text-on-surface hover:bg-surface-container transition-colors cursor-pointer">
          <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
      </div>
    </div>
    <!-- Modal Body (Iframe) -->
    <div class="flex-1 bg-surface-container p-2 sm:p-4 flex items-center justify-center overflow-hidden relative">
      <iframe id="livePreviewIframe" src="about:blank" class="w-full h-full rounded-xl border border-surface-container shadow-inner bg-white" title="Certificate Live Preview Frame"></iframe>
    </div>
    <!-- Modal Footer -->
    <div class="px-6 py-3 border-t border-surface-container flex items-center justify-between bg-surface-container-low shrink-0">
      <span class="text-caption text-outline font-mono flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[16px] text-tertiary">check_circle</span>
        Coordinates, typography, and QR settings dynamically loaded from your active screen.
      </span>
      <div class="flex items-center gap-2">
        <button type="button" onclick="closeLivePreviewModal()" class="px-4 py-2 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md font-medium hover:bg-surface-container-high transition-colors cursor-pointer">
          Back to Studio
        </button>
        <button type="button" onclick="document.getElementById('settingsForm').submit();" class="flex items-center gap-1.5 px-5 py-2 rounded-lg bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-semibold transition-all shadow-sm cursor-pointer">
          <span class="material-symbols-outlined text-[18px]">save</span>
          <span>Save Changes</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  // Global helpers for Live Preview Modal
  function buildLivePreviewUrl(embed = true) {
    const params = new URLSearchParams();
    params.set('cohort_id', '<?= (int)$cohortId ?>');
    if (embed) params.set('embed', '1');

    const inputNameX = document.getElementById('inputNameX');
    const inputNameY = document.getElementById('inputNameY');
    const fontSizeSlider = document.getElementById('fontSizeSlider');
    const fontFamilySelect = document.getElementById('fontFamilySelect');
    const fontColorHex = document.getElementById('fontColorHex');
    const alignRadio = document.querySelector('input[name="name_text_align"]:checked');

    const toggleCertId = document.getElementById('toggleCertIdCheckbox');
    const inputCertIdX = document.getElementById('inputCertIdX');
    const inputCertIdY = document.getElementById('inputCertIdY');
    const certIdColorHex = document.getElementById('certIdColorHex');
    const sliderCertIdFontSize = document.getElementById('sliderCertIdFontSize');

    const toggleQr = document.getElementById('toggleQrCheckbox');
    const inputQrX = document.getElementById('inputQrX');
    const inputQrY = document.getElementById('inputQrY');
    const sliderQrSize = document.getElementById('sliderQrSize');

    if (inputNameX) params.set('name_pos_x', inputNameX.value);
    if (inputNameY) params.set('name_pos_y', inputNameY.value);
    if (fontSizeSlider) params.set('name_font_size', fontSizeSlider.value);
    if (fontFamilySelect) params.set('name_font_family', fontFamilySelect.value);
    if (fontColorHex) params.set('name_font_color', fontColorHex.value);
    if (alignRadio) params.set('name_text_align', alignRadio.value);

    params.set('show_cert_id', toggleCertId && toggleCertId.checked ? '1' : '0');
    const toggleCertIdPrefix = document.getElementById('toggleCertIdPrefix');
    params.set('show_cert_id_prefix', toggleCertIdPrefix && toggleCertIdPrefix.checked ? '1' : '0');
    if (inputCertIdX) params.set('cert_id_pos_x', inputCertIdX.value);
    if (inputCertIdY) params.set('cert_id_pos_y', inputCertIdY.value);
    if (certIdColorHex) params.set('cert_id_color', certIdColorHex.value);
    if (sliderCertIdFontSize) params.set('cert_id_font_size', sliderCertIdFontSize.value);

    const toggleIssueDate = document.getElementById('toggleIssueDateCheckbox');
    const inputIssueDateText = document.getElementById('inputIssueDateText');
    const inputIssueDateX = document.getElementById('inputIssueDateX');
    const inputIssueDateY = document.getElementById('inputIssueDateY');
    const issueDateColorHex = document.getElementById('issueDateColorHex');
    const sliderIssueDateFontSize = document.getElementById('sliderIssueDateFontSize');

    params.set('show_issue_date', toggleIssueDate && toggleIssueDate.checked ? '1' : '0');
    if (inputIssueDateText) params.set('issue_date_text', inputIssueDateText.value);
    if (inputIssueDateX) params.set('issue_date_pos_x', inputIssueDateX.value);
    if (inputIssueDateY) params.set('issue_date_pos_y', inputIssueDateY.value);
    if (issueDateColorHex) params.set('issue_date_color', issueDateColorHex.value);
    if (sliderIssueDateFontSize) params.set('issue_date_font_size', sliderIssueDateFontSize.value);

    params.set('show_qr', toggleQr && toggleQr.checked ? '1' : '0');
    if (inputQrX) params.set('qr_pos_x', inputQrX.value);
    if (inputQrY) params.set('qr_pos_y', inputQrY.value);
    if (sliderQrSize) params.set('qr_size', sliderQrSize.value);

    // Explicit template design studio preview markers
    params.set('preview', '1');
    params.set('preview_token', 'SIMAD-PU-2026-001');
    params.set('preview_name', 'Ahmed Omar Mohamed');

    return 'certificate_preview_modal.php?' + params.toString();
  }

  window.openLivePreviewModal = function() {
    const modal = document.getElementById('livePreviewModal');
    const iframe = document.getElementById('livePreviewIframe');
    if (modal && iframe) {
      iframe.src = buildLivePreviewUrl(true);
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }
  };

  window.closeLivePreviewModal = function() {
    const modal = document.getElementById('livePreviewModal');
    const iframe = document.getElementById('livePreviewIframe');
    if (modal) {
      modal.classList.add('hidden');
      document.body.style.overflow = '';
    }
    if (iframe) {
      iframe.src = 'about:blank';
    }
  };

  window.openStandaloneLivePreview = function() {
    const url = buildLivePreviewUrl(false);
    window.open(url, '_blank');
  };

  // Canvas and Editor Interactive Controller
  (function() {
    const stage = document.getElementById('certificateStage');
    const draggableName = document.getElementById('draggableName');
    const nameTextDisplay = document.getElementById('nameTextDisplay');
    const nameBadgeCoord = document.getElementById('nameBadgeCoord');
    const nameBadgeCoordDisplay = document.getElementById('nameBadgeCoordDisplay');
    const nameXVal = document.getElementById('nameXVal');
    const nameYVal = document.getElementById('nameYVal');
    
    // Hidden inputs & Sliders
    const inputNameX = document.getElementById('inputNameX');
    const inputNameY = document.getElementById('inputNameY');
    const sliderNameX = document.getElementById('sliderNameX');
    const sliderNameY = document.getElementById('sliderNameY');

    const sampleNameSelect = document.getElementById('sampleNameSelect');
    const fontFamilySelect = document.getElementById('fontFamilySelect');
    const fontSizeSlider = document.getElementById('fontSizeSlider');
    const fontSizeValue = document.getElementById('fontSizeValue');
    const fontColorPicker = document.getElementById('fontColorPicker');
    const fontColorHex = document.getElementById('fontColorHex');
    const centerNameBtn = document.getElementById('centerNameBtn');
    const centerNameBtn2 = document.getElementById('centerNameBtn2');

    // Alignment & Zoom
    const alignmentGuides = document.getElementById('alignmentGuides');
    const toggleGridBtn = document.getElementById('toggleGridBtn');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomLevelLabel = document.getElementById('zoomLevelLabel');
    let currentZoom = 1.0;

    // Helper: update position of Name
    function updateNamePosition(xPercent, yPercent) {
      xPercent = Math.max(0, Math.min(100, parseFloat(xPercent) || 0));
      yPercent = Math.max(0, Math.min(100, parseFloat(yPercent) || 0));

      if (draggableName) {
        draggableName.style.left = xPercent + '%';
        draggableName.style.top = yPercent + '%';
      }

      const formattedX = xPercent.toFixed(1);
      const formattedY = yPercent.toFixed(1);

      if (inputNameX) inputNameX.value = xPercent.toFixed(2);
      if (inputNameY) inputNameY.value = yPercent.toFixed(2);
      if (sliderNameX && document.activeElement !== sliderNameX) sliderNameX.value = formattedX;
      if (sliderNameY && document.activeElement !== sliderNameY) sliderNameY.value = formattedY;
      if (nameXVal) nameXVal.textContent = formattedX + '%';
      if (nameYVal) nameYVal.textContent = formattedY + '%';
      if (nameBadgeCoordDisplay) nameBadgeCoordDisplay.textContent = formattedX + '%, ' + formattedY + '%';
      if (nameBadgeCoord) nameBadgeCoord.textContent = formattedX + '%, ' + formattedY + '%';
    }

    // Auto Center Horizontally
    window.centerNameHorizontally = function() {
      const currentY = sliderNameY ? parseFloat(sliderNameY.value) : (inputNameY ? parseFloat(inputNameY.value) : 48.0);
      updateNamePosition(50.0, currentY);
      if (typeof showGlobalToast === 'function') {
        showGlobalToast('Name centered horizontally (50%)');
      }
    };

    if (centerNameBtn) centerNameBtn.addEventListener('click', window.centerNameHorizontally);
    if (centerNameBtn2) centerNameBtn2.addEventListener('click', window.centerNameHorizontally);

    // Sliders event listeners for Name position
    if (sliderNameX && sliderNameY) {
      sliderNameX.addEventListener('input', () => {
        updateNamePosition(parseFloat(sliderNameX.value), parseFloat(sliderNameY.value));
      });
      sliderNameY.addEventListener('input', () => {
        updateNamePosition(parseFloat(sliderNameX.value), parseFloat(sliderNameY.value));
      });
    }

    // Generic draggable helper inside stage using percentages
    function makeDraggable(el, onMove) {
      if (!el || !stage) return;
      let isDragging = false;

      function onPointerDown(e) {
        isDragging = true;
        try { el.setPointerCapture(e.pointerId); } catch(err) {}
        e.preventDefault();
      }

      function onPointerMove(e) {
        if (!isDragging) return;
        const rect = stage.getBoundingClientRect();
        const clientX = e.clientX;
        const clientY = e.clientY;

        const xPercent = ((clientX - rect.left) / rect.width) * 100;
        const yPercent = ((clientY - rect.top) / rect.height) * 100;

        onMove(xPercent, yPercent);
      }

      function onPointerUp(e) {
        if (isDragging) {
          isDragging = false;
          try { el.releasePointerCapture(e.pointerId); } catch(err) {}
        }
      }

      el.addEventListener('pointerdown', onPointerDown);
      el.addEventListener('pointermove', onPointerMove);
      el.addEventListener('pointerup', onPointerUp);
      el.addEventListener('pointercancel', onPointerUp);
    }

    // Initialize Draggable Name
    if (draggableName) {
      makeDraggable(draggableName, (x, y) => {
        updateNamePosition(x, y);
      });
    }

    // Sample Name Switcher (Live)
    if (sampleNameSelect && nameTextDisplay) {
      sampleNameSelect.addEventListener('change', (e) => {
        nameTextDisplay.textContent = e.target.value;
      });
    }

    // Font Family Switcher (Live)
    if (fontFamilySelect && nameTextDisplay) {
      fontFamilySelect.addEventListener('change', (e) => {
        nameTextDisplay.style.fontFamily = `'${e.target.value}', sans-serif, serif`;
      });
    }

    // Font Size Slider (Live)
    if (fontSizeSlider && nameTextDisplay) {
      fontSizeSlider.addEventListener('input', (e) => {
        const sz = parseInt(e.target.value) || 50;
        if (fontSizeValue) fontSizeValue.textContent = sz + 'px';
        nameTextDisplay.style.fontSize = `calc(${sz * 0.5} * 100cqw / 842)`;
      });
    }

    // Font Color Picker & Hex (Live)
    if (fontColorPicker && fontColorHex && nameTextDisplay) {
      fontColorPicker.addEventListener('input', (e) => {
        fontColorHex.value = e.target.value.toUpperCase();
        nameTextDisplay.style.color = e.target.value;
      });
      fontColorHex.addEventListener('input', (e) => {
        let val = e.target.value.trim();
        if (!val.startsWith('#') && /^[0-9A-Fa-f]{3,6}$/.test(val)) val = '#' + val;
        if (/^#[0-9A-Fa-f]{6}$/i.test(val)) {
          fontColorPicker.value = val;
          nameTextDisplay.style.color = val;
        }
      });
    }

    // Text Alignment Radios (Live)
    document.querySelectorAll('input[name="name_text_align"]').forEach(radio => {
      radio.addEventListener('change', (e) => {
        if (nameTextDisplay) nameTextDisplay.style.textAlign = e.target.value;
        const transformX = (e.target.value === 'left') ? '0%' : ((e.target.value === 'right') ? '-100%' : '-50%');
        if (draggableName) draggableName.style.transform = `translate(${transformX}, -50%)`;
        document.querySelectorAll('input[name="name_text_align"]').forEach(r => {
          const lbl = r.parentElement;
          if (lbl) {
            if (r.checked) {
              lbl.classList.remove('bg-surface-container-low', 'text-on-surface');
              lbl.classList.add('bg-primary', 'text-on-primary', 'font-semibold');
            } else {
              lbl.classList.remove('bg-primary', 'text-on-primary', 'font-semibold');
              lbl.classList.add('bg-surface-container-low', 'text-on-surface');
            }
          }
        });
      });
    });

    // --- Certificate ID Handling ---
    const draggableCertId = document.getElementById('draggableCertId');
    const certIdTextDisplay = document.getElementById('certIdTextDisplay');
    const certIdBadgeCoord = document.getElementById('certIdBadgeCoord');
    const inputCertIdX = document.getElementById('inputCertIdX');
    const inputCertIdY = document.getElementById('inputCertIdY');
    const sliderCertIdX = document.getElementById('sliderCertIdX');
    const sliderCertIdY = document.getElementById('sliderCertIdY');
    const toggleCertIdCheckbox = document.getElementById('toggleCertIdCheckbox');
    const certIdControlsWrapper = document.getElementById('certIdControlsWrapper');
    const certIdColorPicker = document.getElementById('certIdColorPicker');
    const certIdColorHex = document.getElementById('certIdColorHex');

    function updateCertIdPosition(xPercent, yPercent) {
      xPercent = Math.max(0, Math.min(100, parseFloat(xPercent) || 0));
      yPercent = Math.max(0, Math.min(100, parseFloat(yPercent) || 0));
      if (draggableCertId) {
        draggableCertId.style.left = xPercent + '%';
        draggableCertId.style.top = yPercent + '%';
      }
      if (inputCertIdX) inputCertIdX.value = xPercent.toFixed(2);
      if (inputCertIdY) inputCertIdY.value = yPercent.toFixed(2);
      if (sliderCertIdX && document.activeElement !== sliderCertIdX) sliderCertIdX.value = xPercent.toFixed(1);
      if (sliderCertIdY && document.activeElement !== sliderCertIdY) sliderCertIdY.value = yPercent.toFixed(1);
      if (certIdBadgeCoord) certIdBadgeCoord.textContent = xPercent.toFixed(1) + '%, ' + yPercent.toFixed(1) + '%';
    }

    if (draggableCertId) {
      makeDraggable(draggableCertId, (x, y) => updateCertIdPosition(x, y));
    }
    if (sliderCertIdX && sliderCertIdY) {
      sliderCertIdX.addEventListener('input', () => updateCertIdPosition(sliderCertIdX.value, sliderCertIdY.value));
      sliderCertIdY.addEventListener('input', () => updateCertIdPosition(sliderCertIdX.value, sliderCertIdY.value));
    }
    if (toggleCertIdCheckbox && draggableCertId) {
      toggleCertIdCheckbox.addEventListener('change', (e) => {
        draggableCertId.classList.toggle('hidden', !e.target.checked);
        if (certIdControlsWrapper) {
          certIdControlsWrapper.classList.toggle('opacity-50', !e.target.checked);
          certIdControlsWrapper.classList.toggle('pointer-events-none', !e.target.checked);
        }
      });
    }
    if (certIdColorPicker && certIdColorHex) {
      certIdColorPicker.addEventListener('input', (e) => {
        certIdColorHex.value = e.target.value.toUpperCase();
        if (certIdTextDisplay) certIdTextDisplay.style.color = e.target.value;
      });
      certIdColorHex.addEventListener('input', (e) => {
        let val = e.target.value.trim();
        if (!val.startsWith('#') && /^[0-9A-Fa-f]{3,6}$/.test(val)) val = '#' + val;
        if (/^#[0-9A-Fa-f]{6}$/i.test(val)) {
          certIdColorPicker.value = val;
          if (certIdTextDisplay) certIdTextDisplay.style.color = val;
        }
      });
    }

    // Cert ID Font Size Slider
    const sliderCertIdFontSize = document.getElementById('sliderCertIdFontSize');
    const certIdFontSizeVal = document.getElementById('certIdFontSizeVal');
    if (sliderCertIdFontSize && certIdTextDisplay) {
      sliderCertIdFontSize.addEventListener('input', (e) => {
        const sz = parseInt(e.target.value) || 10;
        if (certIdFontSizeVal) certIdFontSizeVal.textContent = sz + 'px';
        certIdTextDisplay.style.fontSize = `calc(${sz} * 100cqw / 842)`;
      });
    }

    // Cert ID Prefix Toggle (Live)
    const toggleCertIdPrefix = document.getElementById('toggleCertIdPrefix');
    function updateCertIdDisplay() {
      if (!certIdTextDisplay) return;
      const hasPrefix = toggleCertIdPrefix && toggleCertIdPrefix.checked;
      certIdTextDisplay.textContent = (hasPrefix ? 'Certificate ID: ' : '') + 'SIMAD-PU-2026-001';
    }
    if (toggleCertIdPrefix) {
      toggleCertIdPrefix.addEventListener('change', updateCertIdDisplay);
    }

    // --- Issued Date Handling ---
    const draggableIssueDate = document.getElementById('draggableIssueDate');
    const issueDateTextDisplay = document.getElementById('issueDateTextDisplay');
    const issueDateBadgeCoord = document.getElementById('issueDateBadgeCoord');
    const inputIssueDateText = document.getElementById('inputIssueDateText');
    const inputIssueDateX = document.getElementById('inputIssueDateX');
    const inputIssueDateY = document.getElementById('inputIssueDateY');
    const sliderIssueDateX = document.getElementById('sliderIssueDateX');
    const sliderIssueDateY = document.getElementById('sliderIssueDateY');
    const toggleIssueDateCheckbox = document.getElementById('toggleIssueDateCheckbox');
    const issueDateControlsWrapper = document.getElementById('issueDateControlsWrapper');
    const issueDateColorPicker = document.getElementById('issueDateColorPicker');
    const issueDateColorHex = document.getElementById('issueDateColorHex');
    const sliderIssueDateFontSize = document.getElementById('sliderIssueDateFontSize');
    const issueDateFontSizeVal = document.getElementById('issueDateFontSizeVal');

    function updateIssueDatePosition(xPercent, yPercent) {
      xPercent = Math.max(0, Math.min(100, parseFloat(xPercent) || 0));
      yPercent = Math.max(0, Math.min(100, parseFloat(yPercent) || 0));
      if (draggableIssueDate) {
        draggableIssueDate.style.left = xPercent + '%';
        draggableIssueDate.style.top = yPercent + '%';
      }
      if (inputIssueDateX) inputIssueDateX.value = xPercent.toFixed(2);
      if (inputIssueDateY) inputIssueDateY.value = yPercent.toFixed(2);
      if (sliderIssueDateX && document.activeElement !== sliderIssueDateX) sliderIssueDateX.value = xPercent.toFixed(1);
      if (sliderIssueDateY && document.activeElement !== sliderIssueDateY) sliderIssueDateY.value = yPercent.toFixed(1);
      if (issueDateBadgeCoord) issueDateBadgeCoord.textContent = xPercent.toFixed(1) + '%, ' + yPercent.toFixed(1) + '%';
    }

    if (draggableIssueDate) {
      makeDraggable(draggableIssueDate, (x, y) => updateIssueDatePosition(x, y));
    }
    if (sliderIssueDateX && sliderIssueDateY) {
      sliderIssueDateX.addEventListener('input', () => updateIssueDatePosition(sliderIssueDateX.value, sliderIssueDateY.value));
      sliderIssueDateY.addEventListener('input', () => updateIssueDatePosition(sliderIssueDateX.value, sliderIssueDateY.value));
    }
    if (toggleIssueDateCheckbox && draggableIssueDate) {
      toggleIssueDateCheckbox.addEventListener('change', (e) => {
        draggableIssueDate.classList.toggle('hidden', !e.target.checked);
        if (issueDateControlsWrapper) {
          issueDateControlsWrapper.classList.toggle('opacity-50', !e.target.checked);
          issueDateControlsWrapper.classList.toggle('pointer-events-none', !e.target.checked);
        }
      });
    }
    if (inputIssueDateText && issueDateTextDisplay) {
      inputIssueDateText.addEventListener('input', (e) => {
        issueDateTextDisplay.textContent = e.target.value;
      });
    }
    if (issueDateColorPicker && issueDateColorHex) {
      issueDateColorPicker.addEventListener('input', (e) => {
        issueDateColorHex.value = e.target.value.toUpperCase();
        if (issueDateTextDisplay) issueDateTextDisplay.style.color = e.target.value;
      });
      issueDateColorHex.addEventListener('input', (e) => {
        let val = e.target.value.trim();
        if (!val.startsWith('#') && /^[0-9A-Fa-f]{3,6}$/.test(val)) val = '#' + val;
        if (/^#[0-9A-Fa-f]{6}$/i.test(val)) {
          issueDateColorPicker.value = val;
          if (issueDateTextDisplay) issueDateTextDisplay.style.color = val;
        }
      });
    }
    if (sliderIssueDateFontSize && issueDateTextDisplay) {
      sliderIssueDateFontSize.addEventListener('input', (e) => {
        const sz = parseInt(e.target.value) || 10;
        if (issueDateFontSizeVal) issueDateFontSizeVal.textContent = sz + 'px';
        issueDateTextDisplay.style.fontSize = `calc(${sz} * 100cqw / 842)`;
      });
    }

    // --- QR Code Handling ---
    const draggableQr = document.getElementById('draggableQr');
    const qrBadgeCoord = document.getElementById('qrBadgeCoord');
    const inputQrX = document.getElementById('inputQrX');
    const inputQrY = document.getElementById('inputQrY');
    const inputQrSize = document.getElementById('inputQrSize');
    const sliderQrX = document.getElementById('sliderQrX');
    const sliderQrY = document.getElementById('sliderQrY');
    const sliderQrSize = document.getElementById('sliderQrSize');
    const qrSizeVal = document.getElementById('qrSizeVal');
    const toggleQrCheckbox = document.getElementById('toggleQrCheckbox');
    const qrControlsWrapper = document.getElementById('qrControlsWrapper');

    function updateQrPosition(xPercent, yPercent) {
      xPercent = Math.max(0, Math.min(100, parseFloat(xPercent) || 0));
      yPercent = Math.max(0, Math.min(100, parseFloat(yPercent) || 0));
      if (draggableQr) {
        draggableQr.style.left = xPercent + '%';
        draggableQr.style.top = yPercent + '%';
      }
      if (inputQrX) inputQrX.value = xPercent.toFixed(2);
      if (inputQrY) inputQrY.value = yPercent.toFixed(2);
      if (sliderQrX && document.activeElement !== sliderQrX) sliderQrX.value = xPercent.toFixed(1);
      if (sliderQrY && document.activeElement !== sliderQrY) sliderQrY.value = yPercent.toFixed(1);
      if (qrBadgeCoord) qrBadgeCoord.textContent = xPercent.toFixed(1) + '%, ' + yPercent.toFixed(1) + '%';
    }

    if (draggableQr) {
      makeDraggable(draggableQr, (x, y) => updateQrPosition(x, y));
    }
    if (sliderQrX && sliderQrY) {
      sliderQrX.addEventListener('input', () => updateQrPosition(sliderQrX.value, sliderQrY.value));
      sliderQrY.addEventListener('input', () => updateQrPosition(sliderQrX.value, sliderQrY.value));
    }
    if (sliderQrSize) {
      sliderQrSize.addEventListener('input', (e) => {
        const sz = parseInt(e.target.value) || 75;
        if (qrSizeVal) qrSizeVal.textContent = sz + 'px';
        if (inputQrSize) inputQrSize.value = sz;
        if (draggableQr) {
          draggableQr.style.width = `calc(${sz} * 100cqw / 842)`;
          draggableQr.style.height = `calc(${sz} * 100cqw / 842)`;
        }
      });
    }
    if (toggleQrCheckbox && draggableQr) {
      toggleQrCheckbox.addEventListener('change', (e) => {
        draggableQr.classList.toggle('hidden', !e.target.checked);
        if (qrControlsWrapper) {
          qrControlsWrapper.classList.toggle('opacity-50', !e.target.checked);
          qrControlsWrapper.classList.toggle('pointer-events-none', !e.target.checked);
        }
      });
    }

    // Render Real Scannable QR Code on Studio Stage
    function renderStudioQrCode() {
      const stageQr = document.getElementById('stageQrCode');
      if (!stageQr) return;
      if (typeof QRCode === 'undefined') {
        setTimeout(renderStudioQrCode, 60);
        return;
      }
      stageQr.innerHTML = '';
      const sampleUrl = window.location.origin + '/certificate/public/verify.php?token=SAMPLE-CERT-VERIFY';
      new QRCode(stageQr, {
        text: sampleUrl,
        width: 256,
        height: 256,
        colorDark: '#000000',
        colorLight: '#FFFFFF',
        correctLevel: QRCode.CorrectLevel.M
      });
    }
    renderStudioQrCode();
    window.addEventListener('load', renderStudioQrCode);

    // Guidelines Toggle
    if (toggleGridBtn && alignmentGuides) {
      toggleGridBtn.addEventListener('click', () => {
        const isHidden = alignmentGuides.classList.toggle('hidden');
        toggleGridBtn.classList.toggle('bg-primary', !isHidden);
        toggleGridBtn.classList.toggle('text-on-primary', !isHidden);
      });
    }

    // Zoom Controls
    if (zoomInBtn && zoomOutBtn && stage) {
      zoomInBtn.addEventListener('click', () => {
        currentZoom = Math.min(1.4, currentZoom + 0.1);
        stage.style.transform = `scale(${currentZoom})`;
        if (zoomLevelLabel) zoomLevelLabel.textContent = Math.round(currentZoom * 100) + '%';
      });
      zoomOutBtn.addEventListener('click', () => {
        currentZoom = Math.max(0.7, currentZoom - 0.1);
        stage.style.transform = `scale(${currentZoom})`;
        if (zoomLevelLabel) zoomLevelLabel.textContent = Math.round(currentZoom * 100) + '%';
      });
    }

    // ESC key closes Live Preview Modal
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeLivePreviewModal();
      }
    });

  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

