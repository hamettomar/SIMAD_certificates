<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdminAuth();
require_once __DIR__ . '/../includes/db.php';

// Active Cohort & All Cohorts
$activeCohort = getActiveCohort();
$cohortId = (int)$activeCohort['id'];
$allCohorts = dbFetchAll("SELECT * FROM cohorts ORDER BY id DESC");

// Real Metric Counts
$totalEnrolled   = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ?", [$cohortId])['c'] ?? 0);
$issuedCount     = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ? AND status = 'issued'", [$cohortId])['c'] ?? 0);
$pendingCount    = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ? AND status = 'pending'", [$cohortId])['c'] ?? 0);
$revokedCount    = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ? AND status = 'revoked'", [$cohortId])['c'] ?? 0);
$deliveryRate    = $totalEnrolled > 0 ? round(($issuedCount / $totalEnrolled) * 100, 1) : 0;

// Downloaded certificates count (certificates with download_count > 0)
$downloadedCount = (int) (dbFetchOne("
    SELECT COUNT(*) as c 
    FROM certificates cert 
    JOIN participants p ON cert.participant_id = p.id 
    WHERE p.cohort_id = ? AND cert.download_count > 0
", [$cohortId])['c'] ?? 0);

// Recent Participants
$participants = dbFetchAll("
    SELECT 
        p.id, p.full_name, p.email, p.status as participant_status, p.created_at,
        c.id as cert_id, c.certificate_token, c.document_hash, c.issued_at, c.download_count, c.last_downloaded_at, c.status as cert_status
    FROM participants p
    LEFT JOIN certificates c ON c.participant_id = p.id
    WHERE p.cohort_id = ?
    ORDER BY p.id DESC
    LIMIT 20
", [$cohortId]);

function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) $initials .= strtoupper($w[0]);
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: 'P';
}

$page_title = 'Dashboard - CertificateHub';
$active_page = 'dashboard';

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
        <div class="flex items-center gap-space-xs flex-wrap">
          <span class="font-caption text-caption uppercase tracking-wider text-primary font-semibold bg-surface-container-high px-space-xs py-0.5 rounded"><?= htmlspecialchars($activeCohort['batch_code']) ?></span>
          <div class="relative inline-flex items-center">
            <select onchange="window.location.href='organizer_dashboard.php?cohort_id=' + this.value" class="h-7 pl-2 pr-6 rounded-md bg-surface-container-low border border-outline-variant/40 font-caption text-caption font-semibold text-on-surface focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer">
              <?php foreach ($allCohorts as $ch): 
                $chStatus = getCohortComputedStatus($ch);
                $statusBadge = match($chStatus) {
                  'active' => '🟢',
                  'upcoming' => '🔵',
                  'archived' => '📁',
                  default => '⚪'
                };
              ?>
                <option value="<?= (int)$ch['id'] ?>" <?= ((int)$ch['id'] === $cohortId) ? 'selected' : '' ?>>
                  <?= $statusBadge ?> <?= htmlspecialchars($ch['name']) ?> (<?= htmlspecialchars($ch['batch_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <span class="font-caption text-caption text-outline">
            <?php if (!empty($activeCohort['start_date']) && !empty($activeCohort['end_date'])): ?>
              <?= date('M d', strtotime($activeCohort['start_date'])) ?> – <?= date('M d, Y', strtotime($activeCohort['end_date'])) ?>
            <?php else: ?>
              Conferred: <?= date('M d, Y', strtotime($activeCohort['issue_date'])) ?>
            <?php endif; ?>
          </span>
        </div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight">Dashboard</h1>
        <p class="font-body-md text-body-md text-on-surface-variant max-w-2xl">
          Manage your workshop participants and certificates for <span class="text-on-surface font-semibold">“<?= htmlspecialchars($activeCohort['name']) ?>”</span>.
        </p>
      </div>
      <div class="flex items-center gap-space-sm self-start lg:self-center">
        <button class="flex items-center gap-space-xs h-10 px-space-md rounded-lg bg-surface-container-lowest text-on-surface hover:bg-surface-container font-label-md text-label-md transition-all shadow-sm" id="shareLinkBtn">
          <span class="material-symbols-outlined text-[18px] text-secondary">share</span>
          <span>Share Public Link</span>
        </button>
        <a href="add_participant_modal.php" class="flex items-center gap-space-xs h-10 px-space-md rounded-lg bg-primary-container text-on-primary hover:bg-primary font-label-md text-label-md transition-all shadow-sm" id="quickAddParticipantBtn">
          <span class="material-symbols-outlined text-[18px]">person_add</span>
          <span>+ Add Participant</span>
        </a>
      </div>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
      <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col justify-between gap-space-md">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="font-caption text-caption uppercase tracking-wider text-outline font-semibold">Total Enrolled</span>
            <span class="font-display text-display text-on-surface mt-space-2xs font-semibold"><?= $totalEnrolled ?></span>
          </div>
          <div class="w-10 h-10 rounded-lg bg-surface-container-low flex items-center justify-center text-primary">
            <span class="material-symbols-outlined text-[22px]">group</span>
          </div>
        </div>
        <div class="flex items-center justify-between pt-space-xs">
          <div class="flex items-center gap-1.5 font-label-sm text-label-sm text-tertiary font-medium">
            <span class="material-symbols-outlined text-[16px]">how_to_reg</span>
            <span><?= $totalEnrolled ?> active attendees</span>
          </div>
        </div>
      </div>

      <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col justify-between gap-space-md">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="font-caption text-caption uppercase tracking-wider text-outline font-semibold">Issued &amp; Verified</span>
            <span class="font-display text-display text-on-surface mt-space-2xs font-semibold"><?= $issuedCount ?></span>
          </div>
          <div class="w-10 h-10 rounded-lg bg-tertiary-fixed/40 flex items-center justify-center text-tertiary">
            <span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">verified</span>
          </div>
        </div>
        <div class="flex items-center justify-between pt-space-xs">
          <div class="flex items-center gap-1.5 font-label-sm text-label-sm text-tertiary font-medium">
            <span class="material-symbols-outlined text-[16px]">check_circle</span>
            <span><?= $deliveryRate ?>% completion rate</span>
          </div>
        </div>
      </div>

      <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col justify-between gap-space-md">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="font-caption text-caption uppercase tracking-wider text-outline font-semibold">Pending Release</span>
            <span class="font-display text-display text-on-surface mt-space-2xs font-semibold"><?= $pendingCount ?></span>
          </div>
          <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600">
            <span class="material-symbols-outlined text-[22px]">pending_actions</span>
          </div>
        </div>
        <div class="flex items-center justify-between pt-space-xs">
          <div class="flex items-center gap-1.5 font-label-sm text-label-sm text-secondary font-medium">
            <span class="material-symbols-outlined text-[16px]">hourglass_empty</span>
            <span>Awaiting authorization</span>
          </div>
        </div>
      </div>

      <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col justify-between gap-space-md">
        <div class="flex items-start justify-between">
          <div class="flex flex-col">
            <span class="font-caption text-caption uppercase tracking-wider text-outline font-semibold">Delivery Success</span>
            <span class="font-display text-display text-on-surface mt-space-2xs font-semibold"><?= $deliveryRate ?>%</span>
          </div>
          <div class="w-10 h-10 rounded-lg bg-surface-container-low flex items-center justify-center text-primary">
            <span class="material-symbols-outlined text-[22px]">mark_email_read</span>
          </div>
        </div>
        <div class="flex items-center justify-between pt-space-xs">
          <div class="flex items-center gap-1.5 font-label-sm text-label-sm text-tertiary font-medium">
            <span class="material-symbols-outlined text-[16px]">task_alt</span>
            <span><?= $downloadedCount ?> claimed credentials</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Cohort Batch Progress Strip -->
    <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col gap-space-sm">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-xs">
        <div>
          <span class="font-headline-sm text-headline-sm text-on-surface font-semibold"><?= htmlspecialchars($activeCohort['name']) ?></span>
          <span class="font-body-sm text-body-sm text-outline block sm:inline sm:ml-2">Credential Issuance Progress</span>
        </div>
        <span class="font-label-md text-label-md font-semibold text-primary"><?= $issuedCount ?> / <?= $totalEnrolled ?> Certificates Issued (<?= $deliveryRate ?>%)</span>
      </div>
      <div class="w-full h-2.5 bg-surface-container rounded-full overflow-hidden">
        <div class="h-full bg-primary-container transition-all duration-500 rounded-full" style="width: <?= $deliveryRate ?>%"></div>
      </div>
    </div>

    <!-- Recent Participants Table Card -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col">
      <div class="p-space-lg flex flex-col md:flex-row md:items-center justify-between gap-space-md bg-surface-container-lowest">
        <div class="flex flex-col sm:flex-row sm:items-center gap-space-md">
          <div>
            <h2 class="font-headline-md text-headline-md text-on-surface">Recent Participants</h2>
            <p class="font-body-sm text-body-sm text-outline">Real-time delivery registry &amp; tracking metrics</p>
          </div>
          <div class="flex items-center bg-surface-container-low p-1 rounded-lg">
            <button class="filter-tab px-3 py-1 text-label-sm font-label-sm rounded-md bg-surface-container-lowest text-on-surface shadow-xs transition-colors" data-filter="all">
              All (<?= $totalEnrolled ?>)
            </button>
            <button class="filter-tab px-3 py-1 text-label-sm font-label-sm rounded-md text-on-surface-variant hover:text-on-surface transition-colors" data-filter="issued">
              Issued (<?= $issuedCount ?>)
            </button>
            <button class="filter-tab px-3 py-1 text-label-sm font-label-sm rounded-md text-on-surface-variant hover:text-on-surface transition-colors" data-filter="pending">
              Pending (<?= $pendingCount ?>)
            </button>
          </div>
        </div>
        <div class="flex items-center gap-space-sm">
          <div class="relative flex-1 sm:w-64">
            <span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-outline pointer-events-none">search</span>
            <input class="w-full h-10 pl-9 pr-space-sm bg-surface-container-low text-on-surface rounded-lg font-body-sm text-body-sm placeholder:text-outline focus:outline-none focus:bg-surface-container transition-all" id="participantSearchInput" placeholder="Filter by name or email..." type="text"/>
          </div>
          <a href="import_participants_modal.php" class="h-10 px-3 bg-surface-container-low hover:bg-surface-container text-on-surface-variant hover:text-on-surface rounded-lg flex items-center justify-center transition-colors" title="Import (CSV / Excel)">
            <span class="material-symbols-outlined text-[18px]">file_download</span>
          </a>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-surface-container-low font-caption text-caption uppercase tracking-wider text-outline">
              <th class="py-3 px-space-lg font-semibold" scope="col">Participant</th>
              <th class="py-3 px-space-md font-semibold" scope="col">Email Address</th>
              <th class="py-3 px-space-md font-semibold" scope="col">Certificate Status</th>
              <th class="py-3 px-space-md font-semibold" scope="col">Delivery State</th>
              <th class="py-3 px-space-lg text-right font-semibold" scope="col">Actions</th>
            </tr>
          </thead>
          <tbody class="font-body-md text-body-md text-on-surface" id="participantTableBody">
            <?php if (empty($participants)): ?>
              <tr>
                <td colspan="5" class="py-12 text-center text-on-surface-variant">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[40px] text-outline">group_off</span>
                    <span class="font-label-lg text-label-lg font-medium">No participants enrolled yet</span>
                    <p class="font-body-sm text-body-sm text-secondary">Get started by adding participants manually or uploading an Excel or CSV batch.</p>
                    <div class="flex items-center gap-space-xs mt-2">
                      <a href="add_participant_modal.php" class="px-space-md py-1.5 bg-primary text-on-primary rounded-lg text-label-sm font-medium hover:bg-primary-container">+ Add Participant</a>
                      <a href="import_participants_modal.php" class="px-space-md py-1.5 bg-surface-container text-on-surface rounded-lg text-label-sm font-medium hover:bg-surface-container-high">Import (CSV / Excel)</a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($participants as $p): 
                $status = $p['participant_status'] ?? 'pending';
                $isIssued = ($status === 'issued' && !empty($p['cert_id']));
                $downloaded = (int)($p['download_count'] ?? 0) > 0;
                $searchIndex = strtolower($p['full_name'] . ' ' . $p['email']);
                $filterStatus = $isIssued ? ($downloaded ? 'downloaded' : 'issued') : 'pending';
                $initials = getInitials($p['full_name']);
              ?>
                <tr class="participant-row hover:bg-surface-container-low/60 transition-colors border-b border-surface-container-low" data-search="<?= htmlspecialchars($searchIndex) ?>" data-status="<?= $filterStatus ?>">
                  <td class="py-3 px-space-lg">
                    <div class="flex items-center gap-space-sm">
                      <div class="w-9 h-9 rounded-full bg-surface-container-high flex items-center justify-center font-label-md text-label-md font-semibold text-primary shrink-0">
                        <?= $initials ?>
                      </div>
                      <div class="flex flex-col min-w-0">
                        <span class="font-label-md text-label-md text-on-surface font-semibold truncate"><?= htmlspecialchars($p['full_name']) ?></span>
                        <span class="font-caption text-caption text-outline sm:hidden"><?= htmlspecialchars($p['email']) ?></span>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 px-space-md text-on-surface-variant font-body-sm text-body-sm truncate max-w-xs">
                    <?= htmlspecialchars($p['email']) ?>
                  </td>
                  <td class="py-3 px-space-md">
                    <?php if ($isIssued): ?>
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container font-label-sm text-label-sm text-tertiary font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span>
                        Issued
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 font-label-sm text-label-sm font-medium">
                        Pending
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-space-md">
                    <?php if ($downloaded): ?>
                      <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container-low text-tertiary font-label-sm text-label-sm">
                        <span class="material-symbols-outlined text-[14px]">check</span>
                        <span>Downloaded <span class="text-outline font-normal">(<?= date('M d', strtotime($p['last_downloaded_at'])) ?>)</span></span>
                      </div>
                    <?php elseif ($isIssued): ?>
                      <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container-low text-on-surface-variant font-label-sm text-label-sm">
                        <span class="material-symbols-outlined text-[14px] text-tertiary">mark_email_read</span>
                        <span>Ready for Claim</span>
                      </div>
                    <?php else: ?>
                      <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container-low text-secondary font-label-sm text-label-sm">
                        <span class="material-symbols-outlined text-[14px]">hourglass_empty</span>
                        <span>Pending Release</span>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-space-lg text-right">
                    <div class="inline-flex items-center gap-1">
                      <?php if ($isIssued): ?>
                        <a href="certificate_preview_modal.php?id=<?= $p['id'] ?>" class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" title="Quick Preview">
                          <span class="material-symbols-outlined text-[18px]">visibility</span>
                        </a>
                      <?php endif; ?>
                      <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($p['email']) ?>'); showGlobalToast('Copied <?= htmlspecialchars($p['email']) ?>');" class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" title="Copy Email">
                        <span class="material-symbols-outlined text-[18px]">content_copy</span>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="p-space-md bg-surface-container-low/40 flex items-center justify-between border-t border-surface-container-low text-on-surface-variant font-caption text-caption">
        <span>Showing <?= count($participants) ?> of <?= $totalEnrolled ?> enrolled participants</span>
        <a class="font-label-md text-label-md text-primary hover:text-primary-container font-semibold inline-flex items-center gap-1 transition-colors" href="participants_management.php">
          View all participants
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  (function() {
    const searchInput = document.getElementById('participantSearchInput');
    const filterTabs = document.querySelectorAll('.filter-tab');
    const rows = document.querySelectorAll('.participant-row');
    const shareBtn = document.getElementById('shareLinkBtn');
    let currentFilter = 'all';

    function applyFilter() {
      const q = (searchInput?.value || '').toLowerCase().trim();
      rows.forEach(row => {
        const searchVal = row.getAttribute('data-search') || '';
        const statusVal = row.getAttribute('data-status') || '';
        const matchesQuery = !q || searchVal.includes(q);
        const matchesFilter = (currentFilter === 'all') || 
                              (currentFilter === 'issued' && (statusVal === 'issued' || statusVal === 'downloaded')) ||
                              (currentFilter === 'downloaded' && statusVal === 'downloaded') ||
                              (currentFilter === 'pending' && statusVal === 'pending');

        row.style.display = (matchesQuery && matchesFilter) ? '' : 'none';
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', applyFilter);
    }

    filterTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        filterTabs.forEach(t => {
          t.classList.remove('bg-surface-container-lowest', 'text-on-surface', 'shadow-xs');
          t.classList.add('text-on-surface-variant');
        });
        tab.classList.add('bg-surface-container-lowest', 'text-on-surface', 'shadow-xs');
        tab.classList.remove('text-on-surface-variant');

        currentFilter = tab.getAttribute('data-filter') || 'all';
        applyFilter();
      });
    });

    if (shareBtn) {
      shareBtn.addEventListener('click', () => {
        const url = window.location.origin + window.location.pathname.replace(/[^/]*$/, '') + '../public/public_certificate_download.php';
        navigator.clipboard.writeText(url).then(() => {
          showGlobalToast('Public Certificate Portal URL copied!');
        }).catch(() => {
          showGlobalToast('Public Portal URL: ' + url);
        });
      });
    }
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
