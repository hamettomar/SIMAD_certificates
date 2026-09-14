<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdminAuth();
require_once __DIR__ . '/../includes/db.php';

// Active Cohort & All Cohorts
$activeCohort = getActiveCohort();
$allCohorts = dbFetchAll("SELECT * FROM cohorts ORDER BY id DESC");

// Filter cohort resolution: 'all', specific integer ID, or default to active cohort
$filterCohort = $_GET['cohort_id'] ?? null;
if ($filterCohort === 'all') {
    $selectedCohortId = 'all';
    $whereClause = "1=1";
    $params = [];
    $cohortFilterTitle = "All Workshops";
} elseif ($filterCohort !== null && is_numeric($filterCohort) && (int)$filterCohort > 0) {
    $selectedCohortId = (int)$filterCohort;
    setActiveCohort($selectedCohortId);
    $activeCohort = getActiveCohort();
    $whereClause = "p.cohort_id = ?";
    $params = [$selectedCohortId];
    $cohortFilterTitle = $activeCohort['name'];
} else {
    $selectedCohortId = (int)$activeCohort['id'];
    $whereClause = "p.cohort_id = ?";
    $params = [$selectedCohortId];
    $cohortFilterTitle = $activeCohort['name'];
}
$cohortParamStr = ($filterCohort !== null) ? '&cohort_id=' . urlencode($filterCohort) : '';

$errorMessage = null;

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $targetParticipantId = (int)$_GET['id'];
    $targetP = dbFetchOne("SELECT * FROM participants WHERE id = ?", [$targetParticipantId]);
    if ($targetP) {
        dbQuery("DELETE FROM certificates WHERE participant_id = ?", [$targetParticipantId]);
        dbQuery("DELETE FROM participants WHERE id = ?", [$targetParticipantId]);
        header('Location: participants_management.php?toast=deleted' . $cohortParamStr);
        exit;
    }
}

// Handle Issue Action
if (isset($_GET['action']) && $_GET['action'] === 'issue' && !empty($_GET['id'])) {
    $targetParticipantId = (int)$_GET['id'];
    $targetP = dbFetchOne("SELECT * FROM participants WHERE id = ?", [$targetParticipantId]);
    if ($targetP) {
        $pCohortId = (int)$targetP['cohort_id'];
        $existingCert = dbFetchOne("SELECT id FROM certificates WHERE participant_id = ?", [$targetParticipantId]);
        if (!$existingCert) {
            $template = dbFetchOne("SELECT id FROM certificate_templates WHERE cohort_id = ? AND is_active = 1 LIMIT 1", [$pCohortId]);
            if (!$template) {
                $template = dbFetchOne("SELECT id FROM certificate_templates WHERE cohort_id = ? LIMIT 1", [$pCohortId]);
            }
            $templateId = (int)($template['id'] ?? 1);
            $randomToken = generateNextCertificateToken($pCohortId);
            $docHash = hash('sha256', $randomToken . '|' . $targetP['full_name'] . '|' . $targetP['email'] . '|' . time());
            dbQuery("
                INSERT INTO certificates (participant_id, template_id, certificate_token, document_hash, status, issued_at)
                VALUES (?, ?, ?, ?, 'valid', NOW())
            ", [$targetParticipantId, $templateId, $randomToken, $docHash]);
        }
        dbQuery("UPDATE participants SET status = 'issued' WHERE id = ?", [$targetParticipantId]);
        header('Location: participants_management.php?toast=issued' . $cohortParamStr);
        exit;
    }
}

// Handle Revoke Action
if (isset($_GET['action']) && $_GET['action'] === 'revoke' && !empty($_GET['id'])) {
    $targetParticipantId = (int)$_GET['id'];
    $targetP = dbFetchOne("SELECT * FROM participants WHERE id = ?", [$targetParticipantId]);
    if ($targetP) {
        dbQuery("UPDATE participants SET status = 'revoked' WHERE id = ?", [$targetParticipantId]);
        dbQuery("UPDATE certificates SET status = 'revoked' WHERE participant_id = ?", [$targetParticipantId]);
        header('Location: participants_management.php?toast=revoked' . $cohortParamStr);
        exit;
    }
}

// Handle Reinstate Action
if (isset($_GET['action']) && $_GET['action'] === 'reinstate' && !empty($_GET['id'])) {
    $targetParticipantId = (int)$_GET['id'];
    $targetP = dbFetchOne("SELECT * FROM participants WHERE id = ?", [$targetParticipantId]);
    if ($targetP) {
        dbQuery("UPDATE participants SET status = 'issued' WHERE id = ?", [$targetParticipantId]);
        dbQuery("UPDATE certificates SET status = 'valid' WHERE participant_id = ?", [$targetParticipantId]);
        header('Location: participants_management.php?toast=reinstated' . $cohortParamStr);
        exit;
    }
}

// Handle POST Form Actions (Edit Participant)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['post_action'] ?? '';

    if ($postAction === 'edit_participant') {
        $participantId = (int)($_POST['participant_id'] ?? 0);
        $fullName      = trim($_POST['full_name'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $status        = in_array($_POST['status'] ?? '', ['issued', 'pending', 'revoked']) ? $_POST['status'] : 'pending';

        $targetP = dbFetchOne("SELECT * FROM participants WHERE id = ?", [$participantId]);
        if ($targetP && !empty($fullName) && !empty($email)) {
            $pCohortId = (int)$targetP['cohort_id'];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = 'Please provide a valid email address.';
            } else {
                $existing = dbFetchOne("SELECT id FROM participants WHERE cohort_id = ? AND email = ? AND id != ?", [$pCohortId, $email, $participantId]);
                if ($existing) {
                    $errorMessage = 'A participant with this email address is already registered in this workshop.';
                } else {
                    dbQuery("UPDATE participants SET full_name = ?, email = ?, status = ? WHERE id = ?", [
                        $fullName, $email, $status, $participantId
                    ]);

                    $existingCert = dbFetchOne("SELECT id, certificate_token FROM certificates WHERE participant_id = ?", [$participantId]);

                    if ($status === 'issued') {
                        if (!$existingCert) {
                            $template = dbFetchOne("SELECT id FROM certificate_templates WHERE cohort_id = ? AND is_active = 1 LIMIT 1", [$pCohortId]);
                            if (!$template) {
                                $template = dbFetchOne("SELECT id FROM certificate_templates WHERE cohort_id = ? LIMIT 1", [$pCohortId]);
                            }
                            $templateId = (int)($template['id'] ?? 1);
                            $randomToken = generateNextCertificateToken($pCohortId);
                            $docHash = hash('sha256', $randomToken . '|' . $fullName . '|' . $email . '|' . time());
                            dbQuery("
                                INSERT INTO certificates (participant_id, template_id, certificate_token, document_hash, status, issued_at)
                                VALUES (?, ?, ?, ?, 'valid', NOW())
                            ", [$participantId, $templateId, $randomToken, $docHash]);
                        } else {
                            $newHash = hash('sha256', $existingCert['certificate_token'] . '|' . $fullName . '|' . $email . '|' . time());
                            dbQuery("UPDATE certificates SET status = 'valid', document_hash = ? WHERE participant_id = ?", [$newHash, $participantId]);
                        }
                    } elseif ($status === 'revoked') {
                        if ($existingCert) {
                            dbQuery("UPDATE certificates SET status = 'revoked' WHERE participant_id = ?", [$participantId]);
                        }
                    }

                    header('Location: participants_management.php?toast=updated' . $cohortParamStr);
                    exit;
                }
            }
        } else {
            $errorMessage = 'Full Name and Email Address are required fields.';
        }
    }
}

// Real Metric Counts
if ($selectedCohortId === 'all') {
    $totalEnrolled   = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants")['c'] ?? 0);
    $issuedCount     = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE status = 'issued'")['c'] ?? 0);
    $pendingCount    = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE status = 'pending'")['c'] ?? 0);
    $revokedCount    = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE status = 'revoked'")['c'] ?? 0);
} else {
    $totalEnrolled   = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ?", [$selectedCohortId])['c'] ?? 0);
    $issuedCount     = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ? AND status = 'issued'", [$selectedCohortId])['c'] ?? 0);
    $pendingCount    = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ? AND status = 'pending'", [$selectedCohortId])['c'] ?? 0);
    $revokedCount    = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ? AND status = 'revoked'", [$selectedCohortId])['c'] ?? 0);
}
$issuanceRate    = $totalEnrolled > 0 ? round(($issuedCount / $totalEnrolled) * 100, 1) : 0;

// Query All Participants
$participants = dbFetchAll("
    SELECT 
        p.id, p.cohort_id, p.full_name, p.email, p.status as participant_status, p.created_at,
        ch.name as cohort_name, ch.batch_code as cohort_batch_code,
        c.id as cert_id, c.certificate_token, c.document_hash, c.issued_at, c.download_count, c.last_downloaded_at
    FROM participants p
    JOIN cohorts ch ON ch.id = p.cohort_id
    LEFT JOIN certificates c ON c.participant_id = p.id
    WHERE {$whereClause}
    ORDER BY p.id DESC
", $params);

function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) $initials .= strtoupper($w[0]);
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: 'P';
}

$page_title = 'Participants - CertificateHub';
$active_page = 'participants';

include __DIR__ . '/../includes/head.php';
?>
<body class="bg-surface font-body-md text-on-surface antialiased">
<?php
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col w-full">
  <div class="max-w-[1280px] w-full mx-auto px-gutter-desktop py-space-xl flex flex-col gap-space-lg">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md">
      <div class="flex flex-col">
        <div class="flex items-center gap-space-xs">
          <h1 class="font-headline-lg text-headline-lg text-on-surface font-semibold tracking-tight">Participants</h1>
          <span class="px-space-xs py-0.5 rounded-full bg-surface-container font-label-sm text-label-sm text-secondary font-medium"><?= $totalEnrolled ?> Total</span>
        </div>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Showing enrolled attendees for <span class="font-semibold text-on-surface">“<?= htmlspecialchars($cohortFilterTitle) ?>”</span> (<?= $totalEnrolled ?> registered).
        </p>
      </div>
      <div class="flex items-center gap-space-sm self-start md:self-auto flex-wrap">
        <button type="button" onclick="openBulkDownloadModal()" class="flex items-center gap-space-xs px-space-md h-10 rounded-lg bg-surface-container-lowest text-on-surface font-label-md text-label-md font-semibold shadow-sm hover:bg-surface-container-low transition-colors duration-150 border border-surface-container-low" title="Export All Cohort Certificates">
          <span class="material-symbols-outlined text-[20px] text-primary">download_for_offline</span>
          Bulk Export
        </button>
        <a href="import_participants_modal.php" class="flex items-center gap-space-xs px-space-md h-10 rounded-lg bg-surface-container-lowest text-on-surface font-label-md text-label-md font-medium shadow-sm hover:bg-surface-container-low transition-colors duration-150" id="import-btn">
          <span class="material-symbols-outlined text-[18px] text-secondary">upload_file</span>
          Import (CSV / Excel)
        </a>
        <a href="add_participant_modal.php" class="flex items-center gap-space-xs px-space-md h-10 rounded-lg bg-primary-container text-on-primary font-label-md text-label-md font-medium shadow-sm hover:bg-primary transition-colors duration-150" id="add-btn">
          <span class="material-symbols-outlined text-[18px]">person_add</span>
          + Add Participant
        </a>
      </div>
    </div>

    <?php if (!empty($errorMessage)): ?>
      <div class="p-space-sm px-space-md rounded-xl bg-error-container text-on-error-container flex items-center justify-between shadow-sm animate-in fade-in duration-150">
        <div class="flex items-center gap-space-xs">
          <span class="material-symbols-outlined text-[20px] text-error">error</span>
          <span class="font-label-md text-label-md font-medium"><?= htmlspecialchars($errorMessage) ?></span>
        </div>
        <button onclick="this.parentElement.remove();" class="text-on-error-container/70 hover:text-on-error-container"><span class="material-symbols-outlined text-[18px]">close</span></button>
      </div>
    <?php endif; ?>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
      <div class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex items-center justify-between">
        <div class="flex flex-col">
          <span class="font-caption text-caption uppercase text-outline tracking-wider font-semibold">Total Enrolled</span>
          <span class="font-headline-md text-headline-md font-semibold text-on-surface mt-1"><?= $totalEnrolled ?></span>
          <span class="font-caption text-caption text-outline mt-0.5">Filtered cohort</span>
        </div>
        <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary">
          <span class="material-symbols-outlined text-[20px]">group</span>
        </div>
      </div>

      <div class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex items-center justify-between">
        <div class="flex flex-col">
          <span class="font-caption text-caption uppercase text-outline tracking-wider font-semibold">Certificates Issued</span>
          <span class="font-headline-md text-headline-md font-semibold text-on-surface mt-1"><?= $issuedCount ?></span>
          <span class="font-caption text-caption text-tertiary mt-0.5"><?= $issuanceRate ?>% completion</span>
        </div>
        <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-tertiary">
          <span class="material-symbols-outlined text-[20px]">verified</span>
        </div>
      </div>

      <div class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex items-center justify-between">
        <div class="flex flex-col">
          <span class="font-caption text-caption uppercase text-outline tracking-wider font-semibold">Pending Issuance</span>
          <span class="font-headline-md text-headline-md font-semibold text-on-surface mt-1"><?= $pendingCount ?></span>
          <span class="font-caption text-caption text-outline mt-0.5">Awaiting certificate</span>
        </div>
        <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-amber-600">
          <span class="material-symbols-outlined text-[20px]">schedule</span>
        </div>
      </div>

      <div class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex items-center justify-between">
        <div class="flex flex-col">
          <span class="font-caption text-caption uppercase text-outline tracking-wider font-semibold">Revoked / Inactive</span>
          <span class="font-headline-md text-headline-md font-semibold text-on-surface mt-1"><?= $revokedCount ?></span>
          <span class="font-caption text-caption text-outline mt-0.5">Audit invalidated</span>
        </div>
        <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-error">
          <span class="material-symbols-outlined text-[20px]">block</span>
        </div>
      </div>
    </div>

    <!-- Table Container Card -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col">
      <!-- Table Controls Bar -->
      <div class="p-space-md flex flex-col md:flex-row md:items-center justify-between gap-space-md border-b border-surface-container-low">
        <!-- Filter Tabs -->
        <div class="flex items-center gap-space-2xs overflow-x-auto pb-1 md:pb-0">
          <button class="filter-btn px-space-md py-1.5 rounded-lg font-label-md text-label-md transition-colors bg-primary-container text-on-primary font-medium" data-filter="all">
            All (<?= $totalEnrolled ?>)
          </button>
          <button class="filter-btn px-space-md py-1.5 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container transition-colors" data-filter="issued">
            Issued (<?= $issuedCount ?>)
          </button>
          <button class="filter-btn px-space-md py-1.5 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container transition-colors" data-filter="pending">
            Pending (<?= $pendingCount ?>)
          </button>
          <button class="filter-btn px-space-md py-1.5 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container transition-colors" data-filter="revoked">
            Revoked (<?= $revokedCount ?>)
          </button>
        </div>

        <!-- Filter Controls -->
        <div class="flex items-center gap-space-sm flex-wrap">
          <!-- Workshop Filter Selector -->
          <div class="relative">
            <select onchange="window.location.href='participants_management.php?cohort_id=' + this.value" class="h-10 pl-3 pr-8 rounded-lg bg-surface-container-low font-body-sm text-body-sm text-on-surface border border-outline-variant/30 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer">
              <option value="all" <?= ($selectedCohortId === 'all') ? 'selected' : '' ?>>
                All Workshops (All Cohorts)
              </option>
              <optgroup label="Select Specific Workshop">
                <?php foreach ($allCohorts as $ch): 
                  $chStatus = getCohortComputedStatus($ch);
                  $statusLabel = match($chStatus) {
                    'active' => '🟢 Ongoing',
                    'upcoming' => '🔵 Upcoming',
                    'archived' => '📁 Archived',
                    default => '⚪ Completed'
                  };
                ?>
                  <option value="<?= (int)$ch['id'] ?>" <?= ($selectedCohortId === (int)$ch['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ch['name']) ?> (<?= htmlspecialchars($ch['batch_code']) ?>) — <?= $statusLabel ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>

          <!-- Search Box -->
          <div class="relative w-full md:w-64">
            <span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-outline pointer-events-none">search</span>
            <input class="w-full h-10 pl-9 pr-space-md rounded-lg bg-surface-container-low font-body-sm text-body-sm text-on-surface placeholder:text-outline focus:outline-none focus:bg-surface-container transition-all" id="tableSearch" placeholder="Search name, email, workshop..." type="text"/>
          </div>
        </div>
      </div>

      <!-- Table Body -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="participantsTable">
          <thead>
            <tr class="bg-surface-container-low font-caption text-caption uppercase tracking-wider text-outline select-none">
              <th class="py-3 px-space-md w-12 text-center">
                <input type="checkbox" id="selectAllCheckbox" class="rounded text-primary focus:ring-0 cursor-pointer accent-primary"/>
              </th>
              <th class="py-3 px-space-md font-semibold">Participant</th>
              <th class="py-3 px-space-md font-semibold">Workshop / Cohort</th>
              <th class="py-3 px-space-md font-semibold">Certificate Status</th>
              <th class="py-3 px-space-md font-semibold">Enrollment Date</th>
              <th class="py-3 px-space-lg text-right font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="font-body-md text-body-md text-on-surface divide-y divide-surface-container-low">
            <?php if (empty($participants)): ?>
              <tr>
                <td colspan="6" class="py-16 text-center text-on-surface-variant">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[44px] text-outline">group_off</span>
                    <span class="font-headline-sm text-headline-sm font-semibold">No participants found</span>
                    <p class="font-body-sm text-body-sm text-secondary">Start by adding your attendees manually or importing an Excel or CSV file.</p>
                    <div class="flex items-center gap-space-sm mt-3">
                      <a href="add_participant_modal.php" class="px-space-md py-2 bg-primary text-on-primary rounded-lg text-label-md font-medium hover:bg-primary-container">+ Add Participant</a>
                      <a href="import_participants_modal.php" class="px-space-md py-2 bg-surface-container text-on-surface rounded-lg text-label-md font-medium hover:bg-surface-container-high">Import (CSV / Excel)</a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($participants as $p): 
                $status = $p['participant_status'] ?? 'pending';
                $isIssued = ($status === 'issued' && !empty($p['cert_id']));
                $searchContent = strtolower($p['full_name'] . ' ' . $p['email'] . ' ' . ($p['cohort_name'] ?? '') . ' ' . ($p['cohort_batch_code'] ?? ''));
                $initials = getInitials($p['full_name']);
              ?>
                <tr class="table-row hover:bg-surface-container-low/60 transition-colors" data-search="<?= htmlspecialchars($searchContent) ?>" data-status="<?= $status ?>">
                  <td class="py-3 px-space-md text-center">
                    <input type="checkbox" class="row-checkbox rounded text-primary focus:ring-0 cursor-pointer accent-primary"/>
                  </td>
                  <td class="py-3 px-space-md">
                    <div class="flex items-center gap-space-sm">
                      <div class="w-9 h-9 rounded-full bg-surface-container-high flex items-center justify-center font-label-md text-label-md font-semibold text-primary shrink-0">
                        <?= $initials ?>
                      </div>
                      <div class="flex flex-col min-w-0">
                        <span class="font-label-md text-label-md text-on-surface font-semibold truncate"><?= htmlspecialchars($p['full_name']) ?></span>
                        <span class="font-caption text-caption text-secondary truncate"><?= htmlspecialchars($p['email']) ?></span>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 px-space-md">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="inline-flex items-center px-1.5 py-0.5 rounded font-mono text-caption font-semibold bg-surface-container-high text-on-surface-variant">
                        <?= htmlspecialchars($p['cohort_batch_code'] ?? '') ?>
                      </span>
                      <span class="font-label-sm text-label-sm font-medium text-on-surface truncate max-w-[170px]" title="<?= htmlspecialchars($p['cohort_name'] ?? '') ?>">
                        <?= htmlspecialchars($p['cohort_name'] ?? '') ?>
                      </span>
                    </div>
                  </td>
                  <td class="py-3 px-space-md">
                    <?php if ($status === 'issued'): ?>
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-tertiary font-label-sm text-label-sm font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span>
                        Issued &amp; Valid
                      </span>
                    <?php elseif ($status === 'pending'): ?>
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 font-label-sm text-label-sm font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Pending
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-error-container text-on-error-container font-label-sm text-label-sm font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                        Revoked
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-space-md font-body-sm text-body-sm text-secondary">
                    <?= date('M d, Y', strtotime($p['created_at'])) ?>
                  </td>
                  <td class="py-3 px-space-lg text-right">
                    <div class="inline-flex items-center gap-1">
                      <?php if ($isIssued): ?>
                        <a href="certificate_preview_modal.php?id=<?= $p['id'] ?>" class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" title="Preview Certificate">
                          <span class="material-symbols-outlined text-[18px]">visibility</span>
                        </a>
                        <button onclick='openRevokeModal(<?= (int)$p["id"] ?>, <?= json_encode($p["full_name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 hover:text-amber-800 transition-colors" title="Revoke Certificate">
                          <span class="material-symbols-outlined text-[18px]">block</span>
                        </button>
                      <?php elseif ($status === 'revoked'): ?>
                        <a href="participants_management.php?action=reinstate&id=<?= $p['id'] ?>" class="p-1.5 rounded-lg text-tertiary hover:bg-emerald-50 hover:text-tertiary transition-colors" title="Reinstate / Restore Certificate">
                          <span class="material-symbols-outlined text-[18px]">replay</span>
                        </a>
                      <?php else: ?>
                        <a href="participants_management.php?action=issue&id=<?= $p['id'] ?>" class="p-1.5 rounded-lg text-primary hover:bg-primary/10 transition-colors" title="Issue Certificate Now">
                          <span class="material-symbols-outlined text-[18px]">verified</span>
                        </a>
                      <?php endif; ?>
                      <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($p['email']) ?>'); showGlobalToast('Email copied to clipboard');" class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" title="Copy Email">
                        <span class="material-symbols-outlined text-[18px]">content_copy</span>
                      </button>
                      <button onclick='openEditParticipantModal(<?= json_encode([
                        "id" => (int)$p["id"],
                        "full_name" => $p["full_name"],
                        "email" => $p["email"],
                        "status" => $p["participant_status"]
                      ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-primary transition-colors" title="Edit Participant">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                      </button>
                      <button onclick='openDeleteParticipantModal(<?= (int)$p["id"] ?>, <?= json_encode($p["full_name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= !empty($p["cert_id"]) ? "true" : "false" ?>)' class="p-1.5 rounded-lg text-on-surface-variant hover:bg-error-container/60 hover:text-error transition-colors" title="Delete Participant">
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Footer Meta -->
      <div class="p-space-md bg-surface-container-low/30 border-t border-surface-container-low flex items-center justify-between text-caption text-on-surface-variant">
        <span>Showing <?= count($participants) ?> registered attendees</span>
        <span>CertificateHub Credential Ledger</span>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- EDIT PARTICIPANT MODAL                     -->
<!-- ========================================== -->
<div id="editParticipantModal" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-xs p-gutter-mobile transition-opacity duration-200 hidden opacity-0">
  <div id="editParticipantCard" class="bg-surface-container-lowest rounded-2xl shadow-xl border border-surface-container max-w-lg w-full p-space-xl relative flex flex-col gap-space-lg transform transition-all duration-200 scale-95">
    <div class="flex items-center justify-between pb-space-xs border-b border-surface-container">
      <div class="flex flex-col">
        <h2 class="font-headline-sm text-headline-sm font-semibold text-on-surface">Edit Participant</h2>
        <span class="font-caption text-caption text-outline">Update attendee details and certificate status</span>
      </div>
      <button type="button" onclick="closeEditParticipantModal()" class="p-1.5 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container transition-colors">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form method="POST" action="participants_management.php" class="flex flex-col gap-space-md">
      <input type="hidden" name="post_action" value="edit_participant">
      <input type="hidden" name="participant_id" id="edit_participant_id">

      <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm font-medium text-on-surface" for="edit_full_name">
          Full Name <span class="text-error">*</span>
        </label>
        <input type="text" id="edit_full_name" name="full_name" required placeholder="e.g. Ahmed Omar Mohamed" class="w-full h-10 px-3 rounded-lg bg-surface-container-low text-on-surface font-body-md text-body-md border border-transparent focus:border-primary-container focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all">
        <span class="font-caption text-caption text-outline">Name as it will be printed on the certificate.</span>
      </div>

      <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm font-medium text-on-surface" for="edit_email">
          Email Address <span class="text-error">*</span>
        </label>
        <input type="email" id="edit_email" name="email" required placeholder="name@example.com" class="w-full h-10 px-3 rounded-lg bg-surface-container-low text-on-surface font-body-md text-body-md border border-transparent focus:border-primary-container focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all">
        <span class="font-caption text-caption text-outline">Used for public search and verification lookup.</span>
      </div>

      <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm font-medium text-on-surface" for="edit_status">
          Status <span class="text-error">*</span>
        </label>
        <select id="edit_status" name="status" class="w-full h-10 px-3 rounded-lg bg-surface-container-low text-on-surface font-body-md text-body-md border border-transparent focus:border-primary-container focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all">
          <option value="issued">Issued &amp; Valid</option>
          <option value="pending">Pending</option>
          <option value="revoked">Revoked</option>
        </select>
      </div>

      <div class="flex items-center justify-end gap-space-sm pt-space-sm border-t border-surface-container mt-2">
        <button type="button" onclick="closeEditParticipantModal()" class="h-10 px-space-md rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container transition-colors">
          Cancel
        </button>
        <button type="submit" class="h-10 px-space-lg rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-medium transition-colors shadow-sm">
          Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================== -->
<!-- DELETE PARTICIPANT CONFIRMATION MODAL      -->
<!-- ========================================== -->
<div id="deleteParticipantModal" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-xs p-gutter-mobile transition-opacity duration-200 hidden opacity-0">
  <div id="deleteParticipantCard" class="bg-surface-container-lowest rounded-2xl shadow-xl border border-surface-container max-w-md w-full p-space-xl relative flex flex-col gap-space-md transform transition-all duration-200 scale-95">
    <div class="flex items-start gap-space-md">
      <div class="w-12 h-12 rounded-xl bg-error-container text-error flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-[26px]">delete_forever</span>
      </div>
      <div class="flex flex-col">
        <h2 class="font-headline-sm text-headline-sm font-semibold text-on-surface">Delete Participant</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
          Are you sure you want to permanently delete <strong id="delete_participant_name" class="text-on-surface font-semibold"></strong> from this cohort?
        </p>
      </div>
    </div>

    <div id="delete_cert_warning" class="p-space-sm rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-caption font-body-sm flex items-start gap-2 hidden">
      <span class="material-symbols-outlined text-[18px] text-amber-600 shrink-0 mt-0.5">warning</span>
      <span>This participant has an active certificate. Deleting will permanently remove both the participant and certificate records.</span>
    </div>

    <div class="flex items-center justify-end gap-space-sm pt-space-xs border-t border-surface-container mt-2">
      <button type="button" onclick="closeDeleteParticipantModal()" class="h-10 px-space-md rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container transition-colors">
        Cancel
      </button>
      <a id="confirm_delete_btn" href="#" class="flex items-center justify-center h-10 px-space-lg rounded-lg bg-error hover:bg-red-700 text-white font-label-md text-label-md font-medium transition-colors shadow-sm">
        Delete Participant
      </a>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- REVOKE CERTIFICATE CONFIRMATION MODAL      -->
<!-- ========================================== -->
<div id="revokeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-xs p-gutter-mobile transition-opacity duration-200 hidden opacity-0">
  <div id="revokeCard" class="bg-surface-container-lowest rounded-2xl shadow-xl border border-surface-container max-w-md w-full p-space-xl relative flex flex-col gap-space-md transform transition-all duration-200 scale-95">
    <div class="flex items-start gap-space-md">
      <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-[26px]">block</span>
      </div>
      <div class="flex flex-col">
        <h2 class="font-headline-sm text-headline-sm font-semibold text-on-surface">Revoke Certificate</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
          Are you sure you want to revoke the credential for <strong id="revoke_participant_name" class="text-on-surface font-semibold"></strong>?
        </p>
      </div>
    </div>

    <div class="p-space-sm rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-caption font-body-sm flex items-start gap-2">
      <span class="material-symbols-outlined text-[18px] text-amber-600 shrink-0 mt-0.5">info</span>
      <span>This credential will be recorded as officially revoked on the public ledger. It can be reinstated at any time.</span>
    </div>

    <div class="flex items-center justify-end gap-space-sm pt-space-xs border-t border-surface-container mt-2">
      <button type="button" onclick="closeRevokeModal()" class="h-10 px-space-md rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container transition-colors">
        Cancel
      </button>
      <a id="confirm_revoke_btn" href="#" class="flex items-center justify-center h-10 px-space-lg rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-label-md text-label-md font-medium transition-colors shadow-sm">
        Revoke Credential
      </a>
    </div>
  </div>
</div>

<script>
  // Modal Helpers
  function openEditParticipantModal(p) {
    document.getElementById('edit_participant_id').value = p.id;
    document.getElementById('edit_full_name').value = p.full_name || '';
    document.getElementById('edit_email').value = p.email || '';
    document.getElementById('edit_status').value = p.status || 'pending';

    const modal = document.getElementById('editParticipantModal');
    const card = document.getElementById('editParticipantCard');
    modal.classList.remove('hidden');
    setTimeout(() => {
      modal.classList.remove('opacity-0');
      card.classList.remove('scale-95');
    }, 10);
  }

  function closeEditParticipantModal() {
    const modal = document.getElementById('editParticipantModal');
    const card = document.getElementById('editParticipantCard');
    modal.classList.add('opacity-0');
    card.classList.add('scale-95');
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 200);
  }

  function openDeleteParticipantModal(id, name, hasCert) {
    document.getElementById('delete_participant_name').textContent = name;
    document.getElementById('confirm_delete_btn').href = 'participants_management.php?action=delete&id=' + id;

    const warn = document.getElementById('delete_cert_warning');
    if (hasCert) {
      warn.classList.remove('hidden');
    } else {
      warn.classList.add('hidden');
    }

    const modal = document.getElementById('deleteParticipantModal');
    const card = document.getElementById('deleteParticipantCard');
    modal.classList.remove('hidden');
    setTimeout(() => {
      modal.classList.remove('opacity-0');
      card.classList.remove('scale-95');
    }, 10);
  }

  function closeDeleteParticipantModal() {
    const modal = document.getElementById('deleteParticipantModal');
    const card = document.getElementById('deleteParticipantCard');
    modal.classList.add('opacity-0');
    card.classList.add('scale-95');
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 200);
  }

  function openRevokeModal(id, name) {
    document.getElementById('revoke_participant_name').textContent = name;
    document.getElementById('confirm_revoke_btn').href = 'participants_management.php?action=revoke&id=' + id;

    const modal = document.getElementById('revokeModal');
    const card = document.getElementById('revokeCard');
    modal.classList.remove('hidden');
    setTimeout(() => {
      modal.classList.remove('opacity-0');
      card.classList.remove('scale-95');
    }, 10);
  }

  function closeRevokeModal() {
    const modal = document.getElementById('revokeModal');
    const card = document.getElementById('revokeCard');
    modal.classList.add('opacity-0');
    card.classList.add('scale-95');
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 200);
  }

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeEditParticipantModal();
      closeDeleteParticipantModal();
      closeRevokeModal();
    }
  });

  document.getElementById('editParticipantModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'editParticipantModal') closeEditParticipantModal();
  });
  document.getElementById('deleteParticipantModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'deleteParticipantModal') closeDeleteParticipantModal();
  });
  document.getElementById('revokeModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'revokeModal') closeRevokeModal();
  });

  (function() {
    const searchInput = document.getElementById('tableSearch');
    const filterBtns = document.querySelectorAll('.filter-btn');
    const rows = document.querySelectorAll('.table-row');
    let currentFilter = 'all';

    function applyFilter() {
      const q = (searchInput?.value || '').toLowerCase().trim();
      rows.forEach(row => {
        const searchVal = row.getAttribute('data-search') || '';
        const statusVal = row.getAttribute('data-status') || '';
        const matchesQuery = !q || searchVal.includes(q);
        const matchesFilter = (currentFilter === 'all') || (statusVal === currentFilter);

        row.style.display = (matchesQuery && matchesFilter) ? '' : 'none';
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', applyFilter);
    }

    filterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        filterBtns.forEach(b => {
          b.classList.remove('bg-primary-container', 'text-on-primary', 'font-medium');
          b.classList.add('text-on-surface-variant');
        });
        btn.classList.add('bg-primary-container', 'text-on-primary', 'font-medium');
        btn.classList.remove('text-on-surface-variant');

        currentFilter = btn.getAttribute('data-filter') || 'all';
        applyFilter();
      });
    });

    // Handle check all checkbox
    const selectAll = document.getElementById('selectAllCheckbox');
    if (selectAll) {
      selectAll.addEventListener('change', () => {
        document.querySelectorAll('.row-checkbox').forEach(cb => {
          cb.checked = selectAll.checked;
        });
      });
    }

    // Check for success/toast URL params
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('toast') === 'updated') {
      showGlobalToast('Participant details updated successfully!');
    } else if (urlParams.get('toast') === 'deleted') {
      showGlobalToast('Participant deleted successfully!');
    } else if (urlParams.get('toast') === 'revoked') {
      showGlobalToast('Certificate officially revoked!');
    } else if (urlParams.get('toast') === 'reinstated') {
      showGlobalToast('Certificate successfully reinstated!');
    } else if (urlParams.get('toast') === 'issued' || urlParams.get('success') === 'issued') {
      showGlobalToast('Certificate successfully issued and verified!');
    } else if (urlParams.get('success') === 'enrolled') {
      showGlobalToast('Participant registered and certificate issued successfully!');
    } else if (urlParams.get('success') === 'imported') {
      const count = urlParams.get('count') || 'Attendees';
      showGlobalToast(`Successfully imported ${count} participants!`);
    }
  })();
</script>

<?php 
include __DIR__ . '/bulk_download_modal.php';
include __DIR__ . '/../includes/footer.php'; 
?>
