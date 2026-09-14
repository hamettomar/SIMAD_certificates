<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdminAuth();
require_once __DIR__ . '/../includes/db.php';

// Active Cohort & All Cohorts
$activeCohort = getActiveCohort();
$cohortId = (int)$activeCohort['id'];
$allCohorts = dbFetchAll("SELECT * FROM cohorts ORDER BY id DESC");

// Real Metric Counts for backdrop
$totalEnrolled = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ?", [$cohortId])['c'] ?? 0);
$recentParticipants = dbFetchAll("SELECT * FROM participants WHERE cohort_id = ? ORDER BY id DESC LIMIT 5", [$cohortId]);

$errorMessage = null;

// Helper: Parse XLSX file using native PHP ZipArchive & SimpleXML
if (!function_exists('parseXlsxFile')) {
    function parseXlsxFile($filePath) {
        $rows = [];
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return false;
        }

        // 1. Read shared strings if present
        $sharedStrings = [];
        if (($xmlIndex = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $xmlContent = $zip->getFromIndex($xmlIndex);
            $sxml = simplexml_load_string($xmlContent);
            if ($sxml) {
                foreach ($sxml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Read sheet1.xml (or first sheet)
        $sheetXml = false;
        if (($sheetIndex = $zip->locateName('xl/worksheets/sheet1.xml')) !== false) {
            $sheetXml = $zip->getFromIndex($sheetIndex);
        } else {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strpos($name, 'xl/worksheets/') === 0 && preg_match('/sheet\d+\.xml$/i', $name)) {
                    $sheetXml = $zip->getFromIndex($i);
                    break;
                }
            }
        }

        if (!$sheetXml) {
            $zip->close();
            return false;
        }

        $xml = simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData)) {
            $zip->close();
            return false;
        }

        foreach ($xml->sheetData->row as $row) {
            $rowCells = [];
            $lastColIndex = 0;

            foreach ($row->c as $cell) {
                $ref = (string)$cell['r'];
                preg_match('/^([A-Z]+)(\d+)$/', $ref, $matches);
                $colLetter = $matches[1] ?? 'A';
                
                $colIndex = 0;
                for ($l = 0; $l < strlen($colLetter); $l++) {
                    $colIndex = $colIndex * 26 + (ord($colLetter[$l]) - ord('A') + 1);
                }
                $colIndex -= 1;

                while ($lastColIndex < $colIndex) {
                    $rowCells[] = '';
                    $lastColIndex++;
                }

                $type = (string)$cell['t'];
                $val = isset($cell->v) ? (string)$cell->v : '';

                if ($type === 's') {
                    $idx = (int)$val;
                    $val = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $val = (string)$cell->is->t;
                }

                $rowCells[] = trim($val);
                $lastColIndex = $colIndex + 1;
            }

            if (!empty(array_filter($rowCells, fn($v) => trim((string)$v) !== ''))) {
                $rows[] = $rowCells;
            }
        }

        $zip->close();
        return $rows;
    }
}

// Helper: Extract valid participant name & email from raw row arrays
if (!function_exists('extractParticipantsFromRows')) {
    function extractParticipantsFromRows(array $rawRows): array {
        $results = [];
        $header = null;

        foreach ($rawRows as $data) {
            if (empty(array_filter($data, fn($v) => trim((string)$v) !== ''))) continue;

            if ($header === null) {
                $lowerCols = array_map('strtolower', array_map('trim', array_map('strval', $data)));
                if (in_array('email', $lowerCols) || in_array('full name', $lowerCols) || in_array('name', $lowerCols) || in_array('email address', $lowerCols) || in_array('student name', $lowerCols)) {
                    $header = $lowerCols;
                    continue;
                } else {
                    $header = false;
                }
            }

            if ($header !== false) {
                $nameIdx = array_search('name', $header);
                if ($nameIdx === false) $nameIdx = array_search('full name', $header);
                if ($nameIdx === false) $nameIdx = array_search('participant name', $header);
                if ($nameIdx === false) $nameIdx = array_search('student name', $header);
                if ($nameIdx === false) $nameIdx = 0;

                $emailIdx = array_search('email', $header);
                if ($emailIdx === false) $emailIdx = array_search('email address', $header);
                if ($emailIdx === false) $emailIdx = 1;

                $name = trim((string)($data[$nameIdx] ?? ''));
                $email = trim((string)($data[$emailIdx] ?? ''));
            } else {
                $name = trim((string)($data[0] ?? ''));
                $email = trim((string)($data[1] ?? ''));
            }

            if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results[] = [
                    'name' => $name,
                    'email' => $email
                ];
            }
        }

        return $results;
    }
}

// Handle CSV / XLSX Import POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $rowsToImport = [];
    $targetCohortId = !empty($_POST['cohort_id']) ? (int)$_POST['cohort_id'] : $cohortId;

    // 1. Process uploaded file (.xlsx or .csv) if provided
    if (isset($_FILES['csv_file']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $originalFileName = $_FILES['csv_file']['name'] ?? '';
        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

        if ($ext === 'xlsx' || $ext === 'xls') {
            $parsedRows = parseXlsxFile($_FILES['csv_file']['tmp_name']);
            if (is_array($parsedRows)) {
                $rowsToImport = extractParticipantsFromRows($parsedRows);
            }
        } else {
            // Default: CSV Parser
            if (($handle = fopen($_FILES['csv_file']['tmp_name'], 'r')) !== false) {
                $csvRows = [];
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $csvRows[] = $data;
                }
                fclose($handle);
                $rowsToImport = extractParticipantsFromRows($csvRows);
            }
        }
    } 
    
    // 2. Process parsed payload if JSON was passed from client-side preview
    if (empty($rowsToImport) && !empty($_POST['parsed_data'])) {
        $decoded = json_decode($_POST['parsed_data'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $name = trim((string)($item['name'] ?? ''));
                $email = trim((string)($item['email'] ?? ''));
                if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $rowsToImport[] = [
                        'name' => $name,
                        'email' => $email
                    ];
                }
            }
        }
    }

    if (empty($rowsToImport)) {
        $errorMessage = 'No valid participant records found in the provided Excel or CSV file. Please ensure it has columns for Full Name and Email.';
    } else {
        try {
            $template = dbFetchOne("SELECT id FROM certificate_templates WHERE cohort_id = ? LIMIT 1", [$targetCohortId]);
            $templateId = (int)($template['id'] ?? 1);
            $importedCount = 0;

            foreach ($rowsToImport as $row) {
                // Prevent duplicates within target cohort
                $existing = dbFetchOne("SELECT id FROM participants WHERE cohort_id = ? AND email = ?", [$targetCohortId, $row['email']]);
                if ($existing) {
                    continue;
                }

                // Insert participant
                dbQuery("
                    INSERT INTO participants (cohort_id, full_name, email, status)
                    VALUES (?, ?, ?, 'issued')
                ", [$targetCohortId, $row['name'], $row['email']]);
                $participantId = (int)dbLastInsertId();

                // Generate and issue certificate
                $randomToken = generateNextCertificateToken($targetCohortId);
                $docHash = hash('sha256', $randomToken . '|' . $row['name'] . '|' . $row['email'] . '|' . time());

                dbQuery("
                    INSERT INTO certificates (participant_id, template_id, certificate_token, document_hash, status)
                    VALUES (?, ?, ?, ?, 'valid')
                ", [$participantId, $templateId, $randomToken, $docHash]);

                $importedCount++;
            }

            // Switch active session to the target cohort
            setActiveCohort($targetCohortId);

            header("Location: participants_management.php?cohort_id={$targetCohortId}&success=imported&count={$importedCount}");
            exit;
        } catch (Exception $e) {
            $errorMessage = 'Database import error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Import Participants - CertificateHub';
$active_page = 'participants';

include __DIR__ . '/../includes/head.php';
?>
<body class="bg-surface font-body-md text-on-surface antialiased">
<?php
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col w-full relative">
  <!-- Background Participant Management Table (Context Canvas) -->
  <div class="w-full max-w-max-content-width mx-auto px-gutter-desktop py-space-lg select-none opacity-40 pointer-events-none filter blur-[1.5px] transition-all duration-300">
    <div class="flex items-center justify-between pb-space-md">
      <div>
        <span class="font-caption text-caption text-secondary tracking-wider uppercase block"><?= htmlspecialchars($activeCohort['batch_code']) ?> Enrollment</span>
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Registered Participants (<?= $totalEnrolled ?>)</h1>
      </div>
      <div class="flex items-center gap-space-sm">
        <div class="h-9 px-space-md rounded-lg bg-surface-container-high text-on-surface-variant font-label-md text-label-md flex items-center gap-space-xs shadow-sm">
          <span class="material-symbols-outlined text-[18px]">filter_list</span> Filter
        </div>
        <div class="h-9 px-space-md rounded-lg bg-primary text-on-primary font-label-md text-label-md flex items-center gap-space-xs shadow-sm">
          <span class="material-symbols-outlined text-[18px]">file_upload</span> Import CSV
        </div>
      </div>
    </div>
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden p-space-md flex flex-col gap-space-sm">
      <?php foreach ($recentParticipants as $p): ?>
        <div class="h-10 bg-surface-container-low/40 rounded-lg flex items-center px-4 justify-between">
          <span class="text-xs font-medium"><?= htmlspecialchars($p['full_name']) ?></span>
          <span class="text-xs font-mono text-outline"><?= htmlspecialchars($p['email']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (empty($recentParticipants)): ?>
        <div class="h-10 bg-surface-container-low/40 rounded-lg"></div>
        <div class="h-10 bg-surface-container-low/20 rounded-lg"></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Modal Backdrop Overlay with Architectural Elevation -->
  <div class="fixed inset-0 z-50 flex items-center justify-center p-gutter-mobile md:p-space-lg bg-inverse-surface/40 backdrop-blur-sm transition-opacity duration-200" id="import-modal-overlay">
    <!-- Import Modal Card -->
    <div class="relative w-full max-w-2xl bg-surface-container-lowest rounded-xl shadow-2xl flex flex-col max-h-[921px] overflow-hidden animate-in fade-in zoom-in-95 duration-200">

      <!-- Modal Header -->
      <div class="px-space-lg pt-space-lg pb-space-sm flex items-start justify-between">
        <div class="flex items-start gap-space-sm">
          <div class="w-10 h-10 rounded-xl bg-primary-fixed flex items-center justify-center text-primary shrink-0 shadow-sm">
            <span class="material-symbols-outlined text-[24px]">group_add</span>
          </div>
          <div>
            <div class="flex items-center gap-space-xs">
              <h2 class="font-headline-md text-headline-md text-on-surface font-semibold tracking-tight">Import Participants</h2>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Upload an Excel (.xlsx) or CSV file containing participant names and email addresses to issue certificates in bulk.</p>
          </div>
        </div>
        <a href="participants_management.php" aria-label="Close modal" class="p-1 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container transition-colors" id="close-modal-btn">
          <span class="material-symbols-outlined text-[20px]">close</span>
        </a>
      </div>

      <?php if ($errorMessage): ?>
        <div class="mx-space-lg mt-space-sm p-space-sm rounded-lg bg-error-container/20 text-error text-caption flex items-center gap-space-xs">
          <span class="material-symbols-outlined text-[18px]">error</span>
          <span><?= htmlspecialchars($errorMessage) ?></span>
        </div>
      <?php endif; ?>

      <form id="importForm" method="POST" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden m-0">
        <input type="hidden" name="parsed_data" id="parsed_data_input" value="">

        <!-- Modal Scrollable Content Body -->
        <div class="px-space-lg py-space-sm overflow-y-auto flex flex-col gap-space-md flex-1">
          <!-- Target Workshop Cohort Dropdown -->
          <div class="flex flex-col gap-1.5">
            <label class="font-label-md text-label-md text-on-surface font-semibold flex items-center justify-between" for="cohortSelect">
              <span>Target Workshop / Cohort <span class="text-error">*</span></span>
              <span class="font-caption text-caption text-primary font-medium">Select destination</span>
            </label>
            <div class="relative flex items-center">
              <span class="material-symbols-outlined absolute left-3 text-[18px] text-primary pointer-events-none">school</span>
              <select name="cohort_id" id="cohortSelect" required class="w-full h-10 pl-9 pr-space-md rounded-lg bg-surface-container-low text-on-surface font-body-md text-body-md border border-outline-variant/40 focus:border-primary-container focus:bg-surface-container-lowest focus:outline-none transition-colors cursor-pointer">
                <?php foreach ($allCohorts as $ch): 
                  $chStatus = getCohortComputedStatus($ch);
                  $statusLabel = match($chStatus) {
                    'active' => '🟢 Ongoing',
                    'upcoming' => '🔵 Upcoming',
                    'archived' => '🟡 Archived',
                    default => '⚪ Completed'
                  };
                  $dateRange = (!empty($ch['start_date']) && !empty($ch['end_date'])) ? ' (' . date('M j', strtotime($ch['start_date'])) . ' – ' . date('M j, Y', strtotime($ch['end_date'])) . ')' : '';
                ?>
                  <option value="<?= (int)$ch['id'] ?>" <?= (int)$ch['id'] === $cohortId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ch['name']) ?> [<?= htmlspecialchars($ch['batch_code']) ?>] — <?= $statusLabel ?><?= $dateRange ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <p class="font-caption text-caption text-outline">Imported participants will automatically be enrolled and issued certificates for this workshop.</p>
          </div>

          <!-- Dashed Dropzone Area -->
          <div class="relative group cursor-pointer rounded-xl bg-surface-container-low/60 hover:bg-surface-container-low transition-all duration-150 p-space-lg flex flex-col items-center justify-center text-center shadow-inner" id="dropzone">
            <input accept=".csv, .xlsx, .xls, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" name="csv_file" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" id="csv-file-input" type="file"/>
            <div class="w-14 h-14 rounded-full bg-primary-fixed flex items-center justify-center text-primary mb-space-sm group-hover:scale-105 transition-transform duration-150 shadow-sm">
              <span class="material-symbols-outlined text-[28px]">cloud_upload</span>
            </div>
            <div class="flex flex-col gap-1 items-center">
              <span class="font-label-md text-label-md font-semibold text-on-surface flex items-center gap-1.5" id="dropzone-label">
                Drop your Excel (.xlsx) or CSV file here or <span class="text-primary hover:underline">browse files</span>
              </span>
              <span class="font-body-sm text-body-sm text-outline">Supports Excel (.xlsx, .xls) and CSV up to 10MB (Format: Full Name, Email)</span>
            </div>
            <div class="mt-space-md pt-space-xs flex items-center flex-wrap justify-center gap-space-sm z-20">
              <button type="button" class="inline-flex items-center gap-space-2xs font-label-sm text-label-sm font-medium text-primary hover:text-primary-container transition-colors py-1.5 px-3 rounded-lg border border-primary/20 hover:bg-primary-fixed/30" id="downloadSampleBtn">
                <span class="material-symbols-outlined text-[16px]">file_download</span>
                <span>Download Sample CSV</span>
              </button>
              <button type="button" class="inline-flex items-center gap-space-2xs font-label-sm text-label-sm font-medium text-secondary hover:text-secondary-container transition-colors py-1.5 px-3 rounded-lg border border-secondary/30 hover:bg-secondary-fixed/30" id="downloadSampleXlsxBtn">
                <span class="material-symbols-outlined text-[16px]">table_view</span>
                <span>Download Sample Excel (.xlsx)</span>
              </button>
            </div>
          </div>

          <!-- Pre-import Data Preview Card -->
          <div class="rounded-xl bg-surface-container-low/40 p-space-md flex flex-col gap-space-sm" id="previewContainer">
            <div class="flex items-center justify-between flex-wrap gap-space-xs">
              <div class="flex items-center gap-space-xs">
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-1.5">
                  File detected: <span class="text-primary font-mono text-body-md font-medium" id="previewFileName">workshop_attendees.csv</span>
                </h3>
              </div>
              <span class="px-space-xs py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed font-caption text-caption font-semibold" id="previewBadge">
                4 participants ready
              </span>
            </div>

            <!-- Preview Data Table -->
            <div class="rounded-lg bg-surface-container-lowest shadow-sm overflow-hidden">
              <div class="grid grid-cols-12 px-space-md py-space-xs bg-surface-container-high font-caption text-caption text-secondary uppercase tracking-wider font-semibold">
                <div class="col-span-4 flex items-center gap-1">
                  <span>Name</span>
                </div>
                <div class="col-span-5 flex items-center gap-1">
                  <span>Email</span>
                </div>
                <div class="col-span-3 text-right">
                  <span>Status / Validation</span>
                </div>
              </div>
              <div class="divide-y divide-surface-container-low max-h-56 overflow-y-auto" id="previewTableBody">
                <!-- Dynamic or default preview rows -->
                <div class="grid grid-cols-12 px-space-md py-space-xs items-center hover:bg-surface-container-low/50 transition-colors">
                  <div class="col-span-4 font-label-md text-label-md text-on-surface font-medium truncate flex items-center gap-space-xs">
                    <span class="w-6 h-6 rounded-full bg-secondary-fixed text-on-secondary-fixed font-caption text-caption font-semibold flex items-center justify-center shrink-0">RA</span>
                    <span class="truncate">Rachel Adams</span>
                  </div>
                  <div class="col-span-5 font-body-sm text-body-sm text-on-surface-variant truncate font-mono">rachel.a@techlab.io</div>
                  <div class="col-span-3 flex justify-end">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-tertiary-fixed/30 text-tertiary font-caption text-caption font-semibold">Valid record</span>
                  </div>
                </div>
                <div class="grid grid-cols-12 px-space-md py-space-xs items-center hover:bg-surface-container-low/50 transition-colors">
                  <div class="col-span-4 font-label-md text-label-md text-on-surface font-medium truncate flex items-center gap-space-xs">
                    <span class="w-6 h-6 rounded-full bg-surface-container-highest text-on-surface-variant font-caption text-caption font-semibold flex items-center justify-center shrink-0">BV</span>
                    <span class="truncate">Brian Vance</span>
                  </div>
                  <div class="col-span-5 font-body-sm text-body-sm text-on-surface-variant truncate font-mono">brian@cloudventures.co</div>
                  <div class="col-span-3 flex justify-end">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-tertiary-fixed/30 text-tertiary font-caption text-caption font-semibold">Valid record</span>
                  </div>
                </div>
                <div class="grid grid-cols-12 px-space-md py-space-xs items-center hover:bg-surface-container-low/50 transition-colors">
                  <div class="col-span-4 font-label-md text-label-md text-on-surface font-medium truncate flex items-center gap-space-xs">
                    <span class="w-6 h-6 rounded-full bg-primary-fixed text-on-primary-fixed-variant font-caption text-caption font-semibold flex items-center justify-center shrink-0">ML</span>
                    <span class="truncate">Maya Lin</span>
                  </div>
                  <div class="col-span-5 font-body-sm text-body-sm text-on-surface-variant truncate font-mono">maya.lin@studio.com</div>
                  <div class="col-span-3 flex justify-end">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-tertiary-fixed/30 text-tertiary font-caption text-caption font-semibold">Valid record</span>
                  </div>
                </div>
                <div class="grid grid-cols-12 px-space-md py-space-xs items-center hover:bg-surface-container-low/50 transition-colors">
                  <div class="col-span-4 font-label-md text-label-md text-on-surface font-medium truncate flex items-center gap-space-xs">
                    <span class="w-6 h-6 rounded-full bg-secondary-fixed text-on-secondary-fixed font-caption text-caption font-semibold flex items-center justify-center shrink-0">SO</span>
                    <span class="truncate">Samuel O'Connor</span>
                  </div>
                  <div class="col-span-5 font-body-sm text-body-sm text-on-surface-variant truncate font-mono">samuel@devcorp.net</div>
                  <div class="col-span-3 flex justify-end">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-tertiary-fixed/30 text-tertiary font-caption text-caption font-semibold">Valid record</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex items-center justify-between px-space-xs text-secondary font-caption text-caption">
              <span class="flex items-center gap-1" id="previewStatusText">
                <span class="material-symbols-outlined text-[15px] text-tertiary">check_circle</span>
                All email structures parsed successfully
              </span>
            </div>
          </div>
        </div>

        <!-- Footer Actions Bar -->
        <div class="px-space-lg py-space-md bg-surface-container-low/50 flex items-center justify-between">
          <div class="flex items-center gap-space-xs text-on-surface-variant font-caption text-caption">
          </div>
          <div class="flex items-center gap-space-sm">
            <a href="participants_management.php" class="h-9 px-space-md rounded-lg bg-surface-container-lowest text-on-surface hover:bg-surface-container font-label-md text-label-md font-medium transition-colors shadow-sm inline-flex items-center justify-center" id="cancel-btn">
              Cancel
            </a>
            <button type="submit" class="relative overflow-hidden h-9 px-space-lg rounded-lg bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-medium shadow-sm transition-all flex items-center gap-space-xs" id="import-btn-action">
              <span class="material-symbols-outlined text-[18px]" id="import-icon">cloud_done</span>
              <span id="import-text">Import 4 Participants</span>
            </button>
          </div>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
  (function() {
    const fileInput = document.getElementById('csv-file-input');
    const importForm = document.getElementById('importForm');
    const importBtn = document.getElementById('import-btn-action');
    const importIcon = document.getElementById('import-icon');
    const importText = document.getElementById('import-text');
    const previewFileName = document.getElementById('previewFileName');
    const previewBadge = document.getElementById('previewBadge');
    const previewTableBody = document.getElementById('previewTableBody');
    const previewStatusText = document.getElementById('previewStatusText');
    const parsedDataInput = document.getElementById('parsed_data_input');
    const downloadSampleBtn = document.getElementById('downloadSampleBtn');
    const dropzoneLabel = document.getElementById('dropzone-label');

    // Default sample data
    let currentRows = [
      { name: 'Rachel Adams', email: 'rachel.a@techlab.io' },
      { name: 'Brian Vance', email: 'brian@cloudventures.co' },
      { name: 'Maya Lin', email: 'maya.lin@studio.com' },
      { name: 'Samuel O\'Connor', email: 'samuel@devcorp.net' }
    ];
    parsedDataInput.value = JSON.stringify(currentRows);

    function getInitials(name) {
      const parts = name.trim().split(/\s+/);
      let res = '';
      for (const p of parts) {
        if (p.length > 0) res += p[0].toUpperCase();
        if (res.length >= 2) break;
      }
      return res || 'P';
    }

    function renderPreviewRows(rows, filename) {
      if (filename) {
        previewFileName.textContent = filename;
        if (dropzoneLabel) {
          dropzoneLabel.innerHTML = `Loaded: <span class="text-primary font-mono">${filename}</span>`;
        }
      }
      previewBadge.textContent = `${rows.length} participants ready`;
      importText.textContent = `Import ${rows.length} Participants`;

      if (rows.length === 0) {
        previewTableBody.innerHTML = `
          <div class="p-4 text-center text-outline text-caption">No valid rows found in CSV. Expected: Full Name, Email</div>
        `;
        importBtn.disabled = true;
        importBtn.classList.add('opacity-60', 'cursor-not-allowed');
        return;
      }

      importBtn.disabled = false;
      importBtn.classList.remove('opacity-60', 'cursor-not-allowed');

      let html = '';
      rows.forEach(r => {
        const initials = getInitials(r.name);
        html += `
          <div class="grid grid-cols-12 px-space-md py-space-xs items-center hover:bg-surface-container-low/50 transition-colors">
            <div class="col-span-4 font-label-md text-label-md text-on-surface font-medium truncate flex items-center gap-space-xs">
              <span class="w-6 h-6 rounded-full bg-primary-fixed text-on-primary-fixed font-caption text-caption font-semibold flex items-center justify-center shrink-0">${initials}</span>
              <span class="truncate">${escapeHtml(r.name)}</span>
            </div>
            <div class="col-span-5 font-body-sm text-body-sm text-on-surface-variant truncate font-mono">${escapeHtml(r.email)}</div>
            <div class="col-span-3 flex justify-end">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-tertiary-fixed/30 text-tertiary font-caption text-caption font-semibold">Valid record</span>
            </div>
          </div>
        `;
      });
      previewTableBody.innerHTML = html;
      previewStatusText.innerHTML = `
        <span class="material-symbols-outlined text-[15px] text-tertiary">check_circle</span>
        All ${rows.length} record structures parsed successfully
      `;
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function parseRowsArray(rawRows) {
      if (!Array.isArray(rawRows) || rawRows.length === 0) return [];
      const parsed = [];
      let header = null;

      for (let i = 0; i < rawRows.length; i++) {
        const row = rawRows[i];
        if (!row || !Array.isArray(row) || row.every(c => !String(c || '').trim())) continue;

        const cols = row.map(c => String(c ?? '').trim().replace(/^["']|["']$/g, ''));
        if (!header) {
          const lower = cols.map(c => c.toLowerCase());
          if (lower.includes('email') || lower.includes('email address') || lower.includes('name') || lower.includes('full name') || lower.includes('participant name') || lower.includes('student name')) {
            header = lower;
            continue;
          } else {
            header = ['name', 'email'];
          }
        }

        let name = '';
        let email = '';

        const nameIdx = header.indexOf('name') !== -1 ? header.indexOf('name') : (header.indexOf('full name') !== -1 ? header.indexOf('full name') : (header.indexOf('participant name') !== -1 ? header.indexOf('participant name') : (header.indexOf('student name') !== -1 ? header.indexOf('student name') : 0)));
        const emailIdx = header.indexOf('email') !== -1 ? header.indexOf('email') : (header.indexOf('email address') !== -1 ? header.indexOf('email address') : 1);

        name = cols[nameIdx] || '';
        email = cols[emailIdx] || '';

        if (name && email && email.includes('@')) {
          parsed.push({ name, email });
        }
      }
      return parsed;
    }

    // Handle CSV or XLSX File Selection & Client-side parsing
    if (fileInput) {
      fileInput.addEventListener('change', function(e) {
        const file = e.target.files && e.target.files[0];
        if (!file) return;

        const fileName = file.name.toLowerCase();
        const isExcel = fileName.endsWith('.xlsx') || fileName.endsWith('.xls');

        if (isExcel) {
          // Parse via SheetJS
          const reader = new FileReader();
          reader.onload = function(evt) {
            try {
              if (typeof XLSX === 'undefined') {
                throw new Error('SheetJS library is not loaded');
              }
              const data = new Uint8Array(evt.target.result);
              const workbook = XLSX.read(data, { type: 'array' });
              const firstSheetName = workbook.SheetNames[0];
              const worksheet = workbook.Sheets[firstSheetName];
              const rawRows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
              const parsed = parseRowsArray(rawRows);

              if (parsed.length > 0) {
                currentRows = parsed;
                parsedDataInput.value = JSON.stringify(parsed);
                renderPreviewRows(parsed, file.name);
              } else {
                alert('Could not find valid name and email rows in this Excel (.xlsx) file. Please ensure columns include "Full Name" and "Email".');
              }
            } catch (err) {
              console.error('Excel parse error:', err);
              alert('Error reading Excel file. The file will still be uploaded and parsed on the server.');
              renderPreviewRows([], file.name);
            }
          };
          reader.readAsArrayBuffer(file);
        } else {
          // Parse CSV as text
          const reader = new FileReader();
          reader.onload = function(evt) {
            const content = evt.target.result;
            const lines = content.split(/\r\n|\n/);
            const rawRows = lines.map(line => line.split(','));
            const parsed = parseRowsArray(rawRows);

            if (parsed.length > 0) {
              currentRows = parsed;
              parsedDataInput.value = JSON.stringify(parsed);
              renderPreviewRows(parsed, file.name);
            } else {
              alert('Could not find valid name and email rows in this CSV file.');
            }
          };
          reader.readAsText(file);
        }
      });
    }

    // Download Sample Template CSV Action
    if (downloadSampleBtn) {
      downloadSampleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const csvContent = "data:text/csv;charset=utf-8," + 
          "Full Name,Email\r\n" +
          "Rachel Adams,rachel.a@techlab.io\r\n" +
          "Brian Vance,brian@cloudventures.co\r\n" +
          "Maya Lin,maya.lin@studio.com\r\n" +
          "Mohamed Ahmed Nur,mohamed.nur@simad.edu.so\r\n";
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "workshop_participants_sample.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showGlobalToast('Sample CSV template downloaded');
      });
    }

    // Download Sample Template Excel (.xlsx) Action
    const downloadSampleXlsxBtn = document.getElementById('downloadSampleXlsxBtn');
    if (downloadSampleXlsxBtn) {
      downloadSampleXlsxBtn.addEventListener('click', function(e) {
        e.preventDefault();
        try {
          if (typeof XLSX === 'undefined') {
            alert('Spreadsheet generator is loading, please try again in a moment.');
            return;
          }
          const sampleData = [
            { "Full Name": "Rachel Adams", "Email": "rachel.a@techlab.io" },
            { "Full Name": "Brian Vance", "Email": "brian@cloudventures.co" },
            { "Full Name": "Maya Lin", "Email": "maya.lin@studio.com" },
            { "Full Name": "Mohamed Ahmed Nur", "Email": "mohamed.nur@simad.edu.so" },
            { "Full Name": "Fatima Hassan Ali", "Email": "fatima.ali@simad.edu.so" }
          ];
          const ws = XLSX.utils.json_to_sheet(sampleData);
          const wb = XLSX.utils.book_new();
          XLSX.utils.book_append_sheet(wb, ws, "Participants");
          XLSX.writeFile(wb, "workshop_participants_sample.xlsx");
          showGlobalToast('Sample Excel (.xlsx) template downloaded');
        } catch (err) {
          console.error(err);
          alert('Could not generate Excel sample. You can download the sample CSV instead.');
        }
      });
    }

    // Form Submit Feedback
    if (importForm) {
      importForm.addEventListener('submit', function() {
        importBtn.disabled = true;
        importBtn.classList.add('opacity-90', 'cursor-not-allowed');
        importIcon.classList.add('animate-spin');
        importIcon.textContent = 'progress_activity';
        importText.textContent = 'Importing & issuing...';
      });
    }
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

