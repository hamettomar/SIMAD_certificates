<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdminAuth();
require_once __DIR__ . '/../includes/db.php';

$active_page = 'cohorts';
$pageTitle = 'Workshops & Cohorts — CertificateHub';

// Handle Action Requests
$action = $_GET['action'] ?? '';
$toast = $_GET['toast'] ?? '';

// 1. Switch Active Cohort
if ($action === 'set_active' && !empty($_GET['id'])) {
    $targetId = (int)$_GET['id'];
    if (setActiveCohort($targetId)) {
        header('Location: cohorts_management.php?toast=activated');
        exit;
    }
}

// 2. Archive Cohort
if ($action === 'archive' && !empty($_GET['id'])) {
    $targetId = (int)$_GET['id'];
    dbQuery("UPDATE cohorts SET status = 'archived' WHERE id = ?", [$targetId]);
    if (isset($_SESSION['active_cohort_id']) && (int)$_SESSION['active_cohort_id'] === $targetId) {
        unset($_SESSION['active_cohort_id']);
    }
    header('Location: cohorts_management.php?toast=archived');
    exit;
}

// 3. Delete Cohort
if ($action === 'delete' && !empty($_GET['id'])) {
    $targetId = (int)$_GET['id'];
    $pCount = (int)(dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ?", [$targetId])['c'] ?? 0);
    if ($pCount > 0) {
        // Has participants: safety rule -> archive instead
        dbQuery("UPDATE cohorts SET status = 'archived' WHERE id = ?", [$targetId]);
        header('Location: cohorts_management.php?toast=archived_instead');
        exit;
    } else {
        dbQuery("DELETE FROM certificate_templates WHERE cohort_id = ?", [$targetId]);
        dbQuery("DELETE FROM cohorts WHERE id = ?", [$targetId]);
        if (isset($_SESSION['active_cohort_id']) && (int)$_SESSION['active_cohort_id'] === $targetId) {
            unset($_SESSION['active_cohort_id']);
        }
        header('Location: cohorts_management.php?toast=deleted');
        exit;
    }
}

// 4. Handle POST Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['post_action'] ?? '';

    if ($postAction === 'create_cohort') {
        $name = trim($_POST['name'] ?? '');
        $batchCode = trim($_POST['batch_code'] ?? '');
        $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
        $endDate = trim($_POST['end_date'] ?? date('Y-m-d'));
        $instructorName = trim($_POST['instructor_name'] ?? '');
        $instructorTitle = trim($_POST['instructor_title'] ?? 'Lead Instructor');
        $issueDate = trim($_POST['issue_date'] ?? $endDate);
        $location = trim($_POST['location'] ?? 'Online');
        $makeActive = isset($_POST['make_active']) && $_POST['make_active'] === '1';

        if (!empty($name) && !empty($batchCode)) {
            $computedStatus = getCohortComputedStatus([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'issue_date' => $issueDate,
                'status' => $makeActive ? 'active' : 'active'
            ]);

            dbQuery("
                INSERT INTO cohorts (name, batch_code, start_date, end_date, instructor_name, instructor_title, issue_date, location, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [$name, $batchCode, $startDate, $endDate, $instructorName, $instructorTitle, $issueDate, $location, $computedStatus]);

            $newCohortId = (int)dbLastInsertId();

            // Seed default certificate template for the new cohort
            dbQuery("
                INSERT INTO certificate_templates (
                    cohort_id, template_name, image_path,
                    name_pos_x, name_pos_y, name_font_size, name_font_family, name_font_color, name_text_align,
                    show_cert_id, cert_id_pos_x, cert_id_pos_y, cert_id_color, cert_id_font_size,
                    show_qr, qr_pos_x, qr_pos_y, qr_size, is_active
                ) VALUES (
                    ?, ?, 'uploads/templates/default_blank_template.jpg',
                    50.00, 39.50, 48, 'Playfair Display', '#000000', 'center',
                    0, 50.00, 88.00, '#64748B', 10,
                    0, 88.00, 84.00, 80, 1
                )
            ", [$newCohortId, $name . ' Certificate Template']);

            if ($makeActive) {
                $_SESSION['active_cohort_id'] = $newCohortId;
            }

            header('Location: cohorts_management.php?toast=created');
            exit;
        }
    }

    if ($postAction === 'edit_cohort') {
        $cohortId = (int)($_POST['cohort_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $batchCode = trim($_POST['batch_code'] ?? '');
        $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
        $endDate = trim($_POST['end_date'] ?? date('Y-m-d'));
        $instructorName = trim($_POST['instructor_name'] ?? '');
        $instructorTitle = trim($_POST['instructor_title'] ?? '');
        $issueDate = trim($_POST['issue_date'] ?? date('Y-m-d'));
        $location = trim($_POST['location'] ?? 'Online');
        $status = in_array($_POST['status'] ?? '', ['upcoming', 'active', 'completed', 'archived']) ? $_POST['status'] : 'active';

        if ($cohortId > 0 && !empty($name) && !empty($batchCode)) {
            dbQuery("
                UPDATE cohorts 
                SET name = ?, batch_code = ?, start_date = ?, end_date = ?, instructor_name = ?, instructor_title = ?, issue_date = ?, location = ?, status = ?
                WHERE id = ?
            ", [$name, $batchCode, $startDate, $endDate, $instructorName, $instructorTitle, $issueDate, $location, $status, $cohortId]);

            header('Location: cohorts_management.php?toast=updated');
            exit;
        }
    }
}

// Current Active Cohort Focus
$activeCohort = getActiveCohort();
$activeCohortId = (int)$activeCohort['id'];

// Fetch all cohorts with real aggregate metrics
$cohorts = dbFetchAll("
    SELECT 
        c.*,
        COUNT(DISTINCT p.id) as participant_count,
        COUNT(DISTINCT CASE WHEN cert.status = 'valid' THEN cert.id END) as cert_count,
        COUNT(DISTINCT CASE WHEN cert.download_count > 0 THEN cert.id END) as claimed_count
    FROM cohorts c
    LEFT JOIN participants p ON p.cohort_id = c.id
    LEFT JOIN certificates cert ON cert.participant_id = p.id
    GROUP BY c.id
    ORDER BY (c.id = ?) DESC, c.id DESC
", [$activeCohortId]);

// Global aggregate counts based on dynamic status
$totalCohorts = count($cohorts);
$totalActiveCohorts = count(array_filter($cohorts, fn($c) => getCohortComputedStatus($c) === 'active'));
$totalUpcomingCohorts = count(array_filter($cohorts, fn($c) => getCohortComputedStatus($c) === 'upcoming'));
$totalCompletedCohorts = count(array_filter($cohorts, fn($c) => getCohortComputedStatus($c) === 'completed'));
$totalArchivedCohorts = count(array_filter($cohorts, fn($c) => ($c['status'] ?? '') === 'archived'));
$totalParticipantsGlobal = (int)(dbFetchOne("SELECT COUNT(*) as c FROM participants")['c'] ?? 0);
$totalCertificatesGlobal = (int)(dbFetchOne("SELECT COUNT(*) as c FROM certificates WHERE status = 'valid'")['c'] ?? 0);

include __DIR__ . '/../includes/head.php';
?>
<body class="bg-surface font-body-md text-on-surface antialiased">
<?php
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col w-full">
  <div class="w-full max-w-max-content-width mx-auto px-gutter-mobile md:px-gutter-desktop py-space-xl flex flex-col gap-space-xl">
    
    <!-- Top Action Bar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-space-md">
      <div class="flex flex-col gap-space-2xs">
        <div class="flex items-center gap-space-xs text-caption text-outline font-label-sm uppercase tracking-wider">
          <span>Workspace</span>
          <span class="material-symbols-outlined text-[14px]">chevron_right</span>
          <span class="text-primary font-semibold">Workshops &amp; Cohorts</span>
        </div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight">Workshops &amp; Cohorts</h1>
        <p class="font-body-md text-body-md text-on-surface-variant max-w-2xl">
          Manage workshop batches, switch active cohort session, track credential delivery across programs, and configure certificate setups.
        </p>
      </div>
      <div class="flex items-center gap-space-sm self-start lg:self-center">
        <button onclick="openCreateCohortModal()" class="flex items-center gap-space-xs h-10 px-space-md rounded-lg bg-primary-container text-on-primary hover:bg-primary font-label-md text-label-md transition-all shadow-sm">
          <span class="material-symbols-outlined text-[18px]">add_circle</span>
          <span>Create Workshop / Cohort</span>
        </button>
      </div>
    </div>

    <!-- Active Cohort Spotlight Hero Card -->
    <div class="bg-gradient-to-r from-primary-container/10 via-surface-container-lowest to-surface-container-lowest border border-primary/20 rounded-2xl p-space-lg shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-space-lg">
      <div class="flex flex-col gap-space-sm max-w-2xl">
        <div class="flex items-center gap-space-xs">
          <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-label-sm font-semibold bg-tertiary/10 text-tertiary border border-tertiary/20">
            <span class="w-2 h-2 rounded-full bg-tertiary animate-pulse"></span>
            Currently Active Workshop
          </span>
          <span class="font-caption text-caption text-outline font-medium px-2 py-0.5 rounded bg-surface-container-high"><?= htmlspecialchars($activeCohort['batch_code']) ?></span>
        </div>
        <div>
          <h2 class="font-headline-md text-headline-md text-on-surface font-semibold tracking-tight"><?= htmlspecialchars($activeCohort['name']) ?></h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
            Led by <span class="text-on-surface font-medium"><?= htmlspecialchars($activeCohort['instructor_name']) ?></span> 
            <?= !empty($activeCohort['instructor_title']) ? '('.htmlspecialchars($activeCohort['instructor_title']).')' : '' ?> 
            &bull; Conferred: <?= date('F j, Y', strtotime($activeCohort['issue_date'])) ?> 
            &bull; Location: <?= htmlspecialchars($activeCohort['location'] ?? 'Online') ?>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-space-md pt-space-2xs text-label-sm text-on-surface-variant">
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[18px] text-primary">group</span>
            <span class="font-medium text-on-surface"><?= (int)($activeCohort['participant_count'] ?? dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ?", [$activeCohortId])['c'] ?? 0) ?></span> Attendees
          </div>
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[18px] text-tertiary">verified</span>
            <span class="font-medium text-on-surface"><?= (int)(dbFetchOne("SELECT COUNT(*) as c FROM certificates cert JOIN participants p ON cert.participant_id = p.id WHERE p.cohort_id = ? AND cert.status = 'valid'", [$activeCohortId])['c'] ?? 0) ?></span> Issued
          </div>
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[18px] text-secondary">cloud_download</span>
            <span class="font-medium text-on-surface"><?= (int)(dbFetchOne("SELECT COUNT(*) as c FROM certificates cert JOIN participants p ON cert.participant_id = p.id WHERE p.cohort_id = ? AND cert.download_count > 0", [$activeCohortId])['c'] ?? 0) ?></span> Claimed
          </div>
        </div>
      </div>

      <div class="flex flex-wrap lg:flex-col gap-2 shrink-0">
        <a href="organizer_dashboard.php" class="flex items-center justify-center gap-space-xs h-9 px-space-md rounded-lg bg-primary-container text-on-primary font-label-md hover:bg-primary transition-colors shadow-xs">
          <span class="material-symbols-outlined text-[18px]">grid_view</span>
          <span>Open Dashboard</span>
        </a>
        <a href="participants_management.php" class="flex items-center justify-center gap-space-xs h-9 px-space-md rounded-lg bg-surface-container-low text-on-surface font-label-md hover:bg-surface-container transition-colors">
          <span class="material-symbols-outlined text-[18px]">group</span>
          <span>Participants</span>
        </a>
        <a href="certificate_template.php" class="flex items-center justify-center gap-space-xs h-9 px-space-md rounded-lg bg-surface-container-low text-on-surface font-label-md hover:bg-surface-container transition-colors">
          <span class="material-symbols-outlined text-[18px]">design_services</span>
          <span>Edit Template</span>
        </a>
      </div>
    </div>

    <!-- Quick Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
      <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between gap-space-md">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="font-caption text-caption uppercase tracking-wider text-outline font-semibold">Total Workshops</span>
            <span class="font-display text-display text-on-surface mt-space-2xs font-semibold"><?= $totalCohorts ?></span>
          </div>
          <div class="w-10 h-10 rounded-lg bg-surface-container-low flex items-center justify-center text-primary">
            <span class="material-symbols-outlined text-[22px]">school</span>
          </div>
        </div>
        <span class="font-label-sm text-label-sm text-tertiary font-medium"><?= $totalActiveCohorts ?> Active Batch</span>
      </div>

      <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between gap-space-md">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="font-caption text-caption uppercase tracking-wider text-outline font-semibold">Total Attendees</span>
            <span class="font-display text-display text-on-surface mt-space-2xs font-semibold"><?= $totalParticipantsGlobal ?></span>
          </div>
          <div class="w-10 h-10 rounded-lg bg-surface-container-low flex items-center justify-center text-secondary">
            <span class="material-symbols-outlined text-[22px]">group</span>
          </div>
        </div>
        <span class="font-label-sm text-label-sm text-outline font-medium">Across all cohorts</span>
      </div>

      <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between gap-space-md">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="font-caption text-caption uppercase tracking-wider text-outline font-semibold">Certificates Issued</span>
            <span class="font-display text-display text-on-surface mt-space-2xs font-semibold"><?= $totalCertificatesGlobal ?></span>
          </div>
          <div class="w-10 h-10 rounded-lg bg-surface-container-low flex items-center justify-center text-tertiary">
            <span class="material-symbols-outlined text-[22px]">verified</span>
          </div>
        </div>
        <span class="font-label-sm text-label-sm text-tertiary font-medium">Cryptographically hashed</span>
      </div>

      <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between gap-space-md">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="font-caption text-caption uppercase tracking-wider text-outline font-semibold">Active Session</span>
            <span class="font-headline-sm text-headline-sm text-on-surface mt-space-2xs font-semibold truncate" title="<?= htmlspecialchars($activeCohort['name']) ?>">
              <?= htmlspecialchars($activeCohort['batch_code']) ?>
            </span>
          </div>
          <div class="w-10 h-10 rounded-lg bg-tertiary/10 flex items-center justify-center text-tertiary">
            <span class="material-symbols-outlined text-[22px]">check_circle</span>
          </div>
        </div>
        <span class="font-label-sm text-label-sm text-on-surface font-medium truncate"><?= htmlspecialchars($activeCohort['instructor_name']) ?></span>
      </div>
    </div>

    <!-- Cohorts Directory Card -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col">
      <div class="p-space-lg flex flex-col md:flex-row md:items-center justify-between gap-space-md border-b border-surface-container">
        <div class="flex flex-col sm:flex-row sm:items-center gap-space-md">
          <div>
            <h2 class="font-headline-md text-headline-md text-on-surface font-semibold">All Cohorts &amp; Workshops</h2>
            <p class="font-body-sm text-body-sm text-outline">Click "Set Active" on any workshop to switch your active workspace session</p>
          </div>
          <div class="flex items-center bg-surface-container-low p-1 rounded-lg overflow-x-auto">
            <button class="filter-tab px-3 py-1 text-label-sm font-label-sm rounded-md bg-surface-container-lowest text-on-surface shadow-xs transition-colors whitespace-nowrap" data-filter="all">
              All (<?= $totalCohorts ?>)
            </button>
            <button class="filter-tab px-3 py-1 text-label-sm font-label-sm rounded-md text-on-surface-variant hover:text-on-surface transition-colors whitespace-nowrap" data-filter="active">
              Ongoing (<?= $totalActiveCohorts ?>)
            </button>
            <button class="filter-tab px-3 py-1 text-label-sm font-label-sm rounded-md text-on-surface-variant hover:text-on-surface transition-colors whitespace-nowrap" data-filter="upcoming">
              Upcoming (<?= $totalUpcomingCohorts ?>)
            </button>
            <button class="filter-tab px-3 py-1 text-label-sm font-label-sm rounded-md text-on-surface-variant hover:text-on-surface transition-colors whitespace-nowrap" data-filter="completed">
              Completed (<?= $totalCompletedCohorts ?>)
            </button>
            <button class="filter-tab px-3 py-1 text-label-sm font-label-sm rounded-md text-on-surface-variant hover:text-on-surface transition-colors whitespace-nowrap" data-filter="archived">
              Archived (<?= $totalArchivedCohorts ?>)
            </button>
          </div>
        </div>
        <div class="flex items-center gap-space-sm">
          <div class="relative flex-1 sm:w-72">
            <span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-outline pointer-events-none">search</span>
            <input class="w-full h-10 pl-9 pr-space-sm bg-surface-container-low text-on-surface rounded-lg font-body-sm text-body-sm placeholder:text-outline focus:outline-none focus:bg-surface-container transition-all" id="cohortSearchInput" placeholder="Filter by name, batch, instructor..." type="text"/>
          </div>
        </div>
      </div>

      <!-- Cohorts Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="cohortsTable">
          <thead>
            <tr class="border-b border-surface-container bg-surface-container-lowest/50 text-caption font-label-sm uppercase tracking-wider text-outline">
              <th class="py-3 px-space-lg">Workshop &amp; Batch</th>
              <th class="py-3 px-space-md">Instructor</th>
              <th class="py-3 px-space-md">Training Period</th>
              <th class="py-3 px-space-md">Attendees</th>
              <th class="py-3 px-space-md">Credentials</th>
              <th class="py-3 px-space-md">Status</th>
              <th class="py-3 px-space-lg text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-surface-container">
            <?php foreach ($cohorts as $c): 
                $isCurrentActive = ((int)$c['id'] === $activeCohortId);
                $computedStatus = getCohortComputedStatus($c);
                $statusBadge = match($computedStatus) {
                    'active' => [
                        'class' => 'bg-emerald-50 text-emerald-800 border border-emerald-200',
                        'dot' => 'bg-emerald-600',
                        'label' => 'Ongoing'
                    ],
                    'upcoming' => [
                        'class' => 'bg-blue-50 text-blue-800 border border-blue-200',
                        'dot' => 'bg-blue-600',
                        'label' => 'Upcoming'
                    ],
                    'archived' => [
                        'class' => 'bg-amber-50 text-amber-800 border border-amber-200',
                        'dot' => 'bg-amber-600',
                        'label' => 'Archived'
                    ],
                    default => [
                        'class' => 'bg-slate-100 text-slate-700 border border-slate-200',
                        'dot' => 'bg-slate-500',
                        'label' => 'Completed'
                    ]
                };
                $startDateFormatted = !empty($c['start_date']) ? date('M d', strtotime($c['start_date'])) : date('M d', strtotime($c['issue_date']));
                $endDateFormatted = !empty($c['end_date']) ? date('M d, Y', strtotime($c['end_date'])) : date('M d, Y', strtotime($c['issue_date']));
            ?>
            <tr class="cohort-row hover:bg-surface-container-lowest/70 transition-colors <?= $isCurrentActive ? 'bg-primary-container/5' : '' ?>" 
                data-status="<?= htmlspecialchars($computedStatus) ?>"
                data-search="<?= htmlspecialchars(strtolower($c['name'] . ' ' . $c['batch_code'] . ' ' . $c['instructor_name'])) ?>">
              
              <td class="py-4 px-space-lg">
                <div class="flex items-start gap-space-sm">
                  <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 <?= $isCurrentActive ? 'bg-primary text-on-primary' : 'bg-surface-container text-outline' ?>">
                    <span class="material-symbols-outlined text-[20px]">school</span>
                  </div>
                  <div class="flex flex-col min-w-0">
                    <div class="flex items-center gap-space-xs flex-wrap">
                      <span class="font-label-md text-label-md font-semibold text-on-surface truncate max-w-md"><?= htmlspecialchars($c['name']) ?></span>
                      <?php if ($isCurrentActive): ?>
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-primary-container/15 text-primary border border-primary/20">
                          <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Current Focus
                        </span>
                      <?php endif; ?>
                    </div>
                    <span class="font-caption text-caption text-outline font-medium mt-0.5"><?= htmlspecialchars($c['batch_code']) ?> &bull; <?= htmlspecialchars($c['location'] ?? 'Online') ?></span>
                  </div>
                </div>
              </td>

              <td class="py-4 px-space-md">
                <div class="flex flex-col">
                  <span class="font-body-sm text-body-sm text-on-surface font-medium"><?= htmlspecialchars($c['instructor_name']) ?></span>
                  <span class="font-caption text-caption text-outline"><?= htmlspecialchars($c['instructor_title'] ?? 'Instructor') ?></span>
                </div>
              </td>

              <td class="py-4 px-space-md">
                <div class="flex flex-col gap-0.5">
                  <div class="flex items-center gap-1.5 text-body-sm text-on-surface font-tabular font-medium">
                    <span class="material-symbols-outlined text-[16px] text-outline">calendar_today</span>
                    <span><?= $startDateFormatted ?> – <?= $endDateFormatted ?></span>
                  </div>
                  <span class="font-caption text-caption text-outline">Conferred: <?= date('M d, Y', strtotime($c['issue_date'])) ?></span>
                </div>
              </td>

              <td class="py-4 px-space-md">
                <div class="flex items-center gap-1.5 text-body-sm font-semibold text-on-surface font-tabular">
                  <span class="material-symbols-outlined text-[16px] text-primary">person</span>
                  <span><?= (int)$c['participant_count'] ?></span>
                </div>
              </td>

              <td class="py-4 px-space-md">
                <div class="flex flex-col gap-0.5 text-caption font-tabular">
                  <span class="font-medium text-tertiary"><?= (int)$c['cert_count'] ?> valid issued</span>
                  <span class="text-outline"><?= (int)$c['claimed_count'] ?> claimed</span>
                </div>
              </td>

              <td class="py-4 px-space-md">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-caption font-semibold <?= $statusBadge['class'] ?>">
                  <span class="w-1.5 h-1.5 rounded-full <?= $statusBadge['dot'] ?>"></span>
                  <?= $statusBadge['label'] ?>
                </span>
              </td>

              <td class="py-4 px-space-lg text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1.5">
                  <?php if (!$isCurrentActive): ?>
                    <a href="cohorts_management.php?action=set_active&id=<?= $c['id'] ?>" 
                       class="inline-flex items-center gap-1 h-8 px-2.5 rounded-lg bg-primary-container text-on-primary font-label-sm text-label-sm hover:bg-primary transition-colors shadow-xs" 
                       title="Switch active session to this workshop">
                      <span class="material-symbols-outlined text-[16px]">sync_alt</span>
                      <span>Set Active</span>
                    </a>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1 h-8 px-2.5 rounded-lg bg-tertiary/10 text-tertiary font-label-sm text-label-sm font-semibold border border-tertiary/20">
                      <span class="material-symbols-outlined text-[16px]">check_circle</span>
                      <span>Active Now</span>
                    </span>
                  <?php endif; ?>

                  <!-- Edit Button -->
                  <button type="button" 
                          onclick='openEditCohortModal(<?= json_encode($c) ?>)' 
                          class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" 
                          title="Edit Workshop Details">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                  </button>

                  <!-- Direct Actions Menu -->
                  <a href="organizer_dashboard.php?cohort_id=<?= $c['id'] ?>" 
                     class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" 
                     title="View Dashboard">
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                  </a>

                  <?php if ($c['status'] !== 'archived'): ?>
                    <a href="cohorts_management.php?action=archive&id=<?= $c['id'] ?>" 
                       onclick="return confirm('Archive this workshop batch?')"
                       class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-amber-600 transition-colors" 
                       title="Archive Workshop">
                      <span class="material-symbols-outlined text-[18px]">archive</span>
                    </a>
                  <?php else: ?>
                    <a href="cohorts_management.php?action=delete&id=<?= $c['id'] ?>" 
                       onclick="return confirm('Permanently delete this empty workshop?')"
                       class="p-1.5 rounded-lg text-on-surface-variant hover:bg-error-container hover:text-on-error-container transition-colors" 
                       title="Delete Workshop">
                      <span class="material-symbols-outlined text-[18px]">delete</span>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Empty State -->
      <div id="noCohortsMessage" class="hidden py-12 flex flex-col items-center justify-center text-center p-space-md">
        <span class="material-symbols-outlined text-[48px] text-outline mb-2">search_off</span>
        <p class="font-headline-sm text-headline-sm text-on-surface">No workshops matched your filter</p>
        <p class="font-body-sm text-body-sm text-outline mt-1">Try clearing your search query or switching tabs.</p>
      </div>
    </div>

  </div>
</div>

<!-- ========================================== -->
<!-- CREATE WORKSHOP MODAL                      -->
<!-- ========================================== -->
<div id="createCohortModal" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-200">
  <div class="bg-surface-container-lowest rounded-2xl max-w-lg w-full p-space-xl shadow-2xl border border-surface-container flex flex-col gap-space-lg transform scale-95 transition-transform duration-200" id="createCohortCard">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-space-sm">
        <div class="w-10 h-10 rounded-lg bg-primary-container/10 flex items-center justify-center text-primary">
          <span class="material-symbols-outlined text-[24px]">school</span>
        </div>
        <div>
          <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Create Workshop / Cohort</h3>
          <p class="font-caption text-caption text-outline">Configure program parameters and credentials setup</p>
        </div>
      </div>
      <button type="button" onclick="closeCreateCohortModal()" class="p-1.5 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container transition-colors">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form method="POST" action="cohorts_management.php" class="flex flex-col gap-space-md">
      <input type="hidden" name="post_action" value="create_cohort">

      <div class="flex flex-col gap-1.5">
        <label class="font-label-sm text-label-sm font-semibold text-on-surface">Workshop Title *</label>
        <input type="text" name="name" required placeholder="e.g. Deep Learning & Computer Vision Specialization" 
               class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Batch Code *</label>
          <input type="text" name="batch_code" required placeholder="e.g. Cohort #850" 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Conferred / Issue Date *</label>
          <input type="date" name="issue_date" id="create_issue_date" required value="<?= date('Y-m-d') ?>" 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Start Date (Training Begins) *</label>
          <input type="date" name="start_date" id="create_start_date" required value="<?= date('Y-m-d') ?>" 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">End Date (Training Ends) *</label>
          <input type="date" name="end_date" id="create_end_date" required value="<?= date('Y-m-d', strtotime('+14 days')) ?>" 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Instructor Name *</label>
          <input type="text" name="instructor_name" required placeholder="e.g. Dr. Sarah Jenkins" 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Instructor Title</label>
          <input type="text" name="instructor_title" placeholder="e.g. Lead Instructor" 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="font-label-sm text-label-sm font-semibold text-on-surface">Location / Modality</label>
        <input type="text" name="location" value="Online" placeholder="e.g. Online, Zurich Campus, San Francisco" 
               class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
      </div>

      <div class="flex items-center gap-2 pt-1">
        <input type="checkbox" name="make_active" id="makeActiveCheck" value="1" checked class="w-4 h-4 rounded text-primary focus:ring-primary/20">
        <label for="makeActiveCheck" class="font-label-sm text-label-sm text-on-surface cursor-pointer select-none">
          Set as active workshop immediately
        </label>
      </div>

      <div class="flex items-center justify-end gap-space-sm pt-space-sm border-t border-surface-container">
        <button type="button" onclick="closeCreateCohortModal()" class="h-10 px-space-md rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container transition-colors">
          Cancel
        </button>
        <button type="submit" class="h-10 px-space-md rounded-lg bg-primary-container text-on-primary font-label-md text-label-md font-medium hover:bg-primary transition-colors shadow-sm">
          Create Workshop
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================== -->
<!-- EDIT WORKSHOP MODAL                        -->
<!-- ========================================== -->
<div id="editCohortModal" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-200">
  <div class="bg-surface-container-lowest rounded-2xl max-w-lg w-full p-space-xl shadow-2xl border border-surface-container flex flex-col gap-space-lg transform scale-95 transition-transform duration-200" id="editCohortCard">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-space-sm">
        <div class="w-10 h-10 rounded-lg bg-secondary/10 flex items-center justify-center text-secondary">
          <span class="material-symbols-outlined text-[24px]">edit</span>
        </div>
        <div>
          <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Edit Workshop Details</h3>
          <p class="font-caption text-caption text-outline">Update program batch information</p>
        </div>
      </div>
      <button type="button" onclick="closeEditCohortModal()" class="p-1.5 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container transition-colors">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form method="POST" action="cohorts_management.php" class="flex flex-col gap-space-md">
      <input type="hidden" name="post_action" value="edit_cohort">
      <input type="hidden" name="cohort_id" id="edit_cohort_id" value="">

      <div class="flex flex-col gap-1.5">
        <label class="font-label-sm text-label-sm font-semibold text-on-surface">Workshop Title *</label>
        <input type="text" name="name" id="edit_name" required 
               class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Batch Code *</label>
          <input type="text" name="batch_code" id="edit_batch_code" required 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Conferred Date *</label>
          <input type="date" name="issue_date" id="edit_issue_date" required 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Start Date *</label>
          <input type="date" name="start_date" id="edit_start_date" required 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">End Date *</label>
          <input type="date" name="end_date" id="edit_end_date" required 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Instructor Name *</label>
          <input type="text" name="instructor_name" id="edit_instructor_name" required 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Instructor Title</label>
          <input type="text" name="instructor_title" id="edit_instructor_title" 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Location / Modality</label>
          <input type="text" name="location" id="edit_location" 
                 class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-label-sm text-label-sm font-semibold text-on-surface">Status</label>
          <select name="status" id="edit_status" class="h-10 px-3 rounded-lg bg-surface-container-low border border-surface-container text-on-surface font-body-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
            <option value="active">Active (Ongoing)</option>
            <option value="upcoming">Upcoming (Scheduled)</option>
            <option value="completed">Completed</option>
            <option value="archived">Archived</option>
          </select>
        </div>
      </div>

      <div class="flex items-center justify-end gap-space-sm pt-space-sm border-t border-surface-container">
        <button type="button" onclick="closeEditCohortModal()" class="h-10 px-space-md rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container transition-colors">
          Cancel
        </button>
        <button type="submit" class="h-10 px-space-md rounded-lg bg-primary-container text-on-primary font-label-md text-label-md font-medium hover:bg-primary transition-colors shadow-sm">
          Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================== -->
<!-- JAVASCRIPT LOGIC                           -->
<!-- ========================================== -->
<script>
// Filter & Search
const searchInput = document.getElementById('cohortSearchInput');
const filterTabs = document.querySelectorAll('.filter-tab');
const rows = document.querySelectorAll('.cohort-row');
const noCohortsMessage = document.getElementById('noCohortsMessage');

let activeFilter = 'all';

function filterTable() {
    const q = (searchInput.value || '').trim().toLowerCase();
    let visibleCount = 0;

    rows.forEach(row => {
        const status = row.getAttribute('data-status');
        const searchTarget = row.getAttribute('data-search') || '';

        const matchesStatus = (activeFilter === 'all') || (status === activeFilter);
        const matchesQuery = !q || searchTarget.includes(q);

        if (matchesStatus && matchesQuery) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (visibleCount === 0) {
        noCohortsMessage.classList.remove('hidden');
    } else {
        noCohortsMessage.classList.add('hidden');
    }
}

if (searchInput) {
    searchInput.addEventListener('input', filterTable);
}

filterTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        filterTabs.forEach(t => {
            t.classList.remove('bg-surface-container-lowest', 'text-on-surface', 'shadow-xs');
            t.classList.add('text-on-surface-variant');
        });
        tab.classList.remove('text-on-surface-variant');
        tab.classList.add('bg-surface-container-lowest', 'text-on-surface', 'shadow-xs');
        activeFilter = tab.getAttribute('data-filter');
        filterTable();
    });
});

// Modal helpers
function openCreateCohortModal() {
    const modal = document.getElementById('createCohortModal');
    const card = document.getElementById('createCohortCard');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        card.classList.remove('scale-95');
    }, 10);
}

function closeCreateCohortModal() {
    const modal = document.getElementById('createCohortModal');
    const card = document.getElementById('createCohortCard');
    modal.classList.add('opacity-0');
    card.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

function openEditCohortModal(cohort) {
    document.getElementById('edit_cohort_id').value = cohort.id;
    document.getElementById('edit_name').value = cohort.name;
    document.getElementById('edit_batch_code').value = cohort.batch_code;
    document.getElementById('edit_start_date').value = cohort.start_date || cohort.issue_date || '';
    document.getElementById('edit_end_date').value = cohort.end_date || cohort.issue_date || '';
    document.getElementById('edit_issue_date').value = cohort.issue_date;
    document.getElementById('edit_instructor_name').value = cohort.instructor_name;
    document.getElementById('edit_instructor_title').value = cohort.instructor_title || '';
    document.getElementById('edit_location').value = cohort.location || 'Online';
    document.getElementById('edit_status').value = cohort.status || 'active';

    const modal = document.getElementById('editCohortModal');
    const card = document.getElementById('editCohortCard');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        card.classList.remove('scale-95');
    }, 10);
}

function closeEditCohortModal() {
    const modal = document.getElementById('editCohortModal');
    const card = document.getElementById('editCohortCard');
    modal.classList.add('opacity-0');
    card.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

// Toast alerts on reload
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const toast = urlParams.get('toast');
    if (toast === 'activated') {
        showGlobalToast('Switched active workshop session successfully!');
    } else if (toast === 'created') {
        showGlobalToast('New workshop batch created successfully!');
    } else if (toast === 'updated') {
        showGlobalToast('Workshop details updated successfully!');
    } else if (toast === 'archived') {
        showGlobalToast('Workshop moved to archives.');
    } else if (toast === 'archived_instead') {
        showGlobalToast('Workshop has active attendees, archived instead of deleted.');
    } else if (toast === 'deleted') {
        showGlobalToast('Workshop deleted permanently.');
    }

    if (toast) {
        // Clean up URL without reload
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
