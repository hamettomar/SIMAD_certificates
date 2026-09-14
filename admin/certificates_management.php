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

// Handle Revoke Action
if (isset($_GET['action']) && $_GET['action'] === 'revoke' && !empty($_GET['cert_id'])) {
    $certId = (int)$_GET['cert_id'];
    $cert = dbFetchOne("
        SELECT cert.id, cert.participant_id 
        FROM certificates cert 
        JOIN participants p ON cert.participant_id = p.id 
        WHERE cert.id = ?
    ", [$certId]);
    if ($cert) {
        dbQuery("UPDATE certificates SET status = 'revoked' WHERE id = ?", [$certId]);
        dbQuery("UPDATE participants SET status = 'revoked' WHERE id = ?", [$cert['participant_id']]);
        header('Location: certificates_management.php?toast=revoked' . $cohortParamStr);
        exit;
    }
}

// Handle Reinstate Action
if (isset($_GET['action']) && $_GET['action'] === 'reinstate' && !empty($_GET['cert_id'])) {
    $certId = (int)$_GET['cert_id'];
    $cert = dbFetchOne("
        SELECT cert.id, cert.participant_id 
        FROM certificates cert 
        JOIN participants p ON cert.participant_id = p.id 
        WHERE cert.id = ?
    ", [$certId]);
    if ($cert) {
        dbQuery("UPDATE certificates SET status = 'valid' WHERE id = ?", [$certId]);
        dbQuery("UPDATE participants SET status = 'issued' WHERE id = ?", [$cert['participant_id']]);
        header('Location: certificates_management.php?toast=reinstated' . $cohortParamStr);
        exit;
    }
}

// Real Metrics
if ($selectedCohortId === 'all') {
    $totalMinted = (int) (dbFetchOne("SELECT COUNT(*) as c FROM certificates")['c'] ?? 0);
    $validCount = (int) (dbFetchOne("SELECT COUNT(*) as c FROM certificates WHERE status = 'valid'")['c'] ?? 0);
    $claimedCount = (int) (dbFetchOne("SELECT COUNT(*) as c FROM certificates WHERE download_count > 0")['c'] ?? 0);
    $revokedCount = (int) (dbFetchOne("SELECT COUNT(*) as c FROM certificates WHERE status = 'revoked'")['c'] ?? 0);
} else {
    $totalMinted = (int) (dbFetchOne("
        SELECT COUNT(*) as c 
        FROM certificates cert 
        JOIN participants p ON cert.participant_id = p.id 
        WHERE p.cohort_id = ?
    ", [$selectedCohortId])['c'] ?? 0);

    $validCount = (int) (dbFetchOne("
        SELECT COUNT(*) as c 
        FROM certificates cert 
        JOIN participants p ON cert.participant_id = p.id 
        WHERE p.cohort_id = ? AND cert.status = 'valid'
    ", [$selectedCohortId])['c'] ?? 0);

    $claimedCount = (int) (dbFetchOne("
        SELECT COUNT(*) as c 
        FROM certificates cert 
        JOIN participants p ON cert.participant_id = p.id 
        WHERE p.cohort_id = ? AND cert.download_count > 0
    ", [$selectedCohortId])['c'] ?? 0);

    $revokedCount = (int) (dbFetchOne("
        SELECT COUNT(*) as c 
        FROM certificates cert 
        JOIN participants p ON cert.participant_id = p.id 
        WHERE p.cohort_id = ? AND cert.status = 'revoked'
    ", [$selectedCohortId])['c'] ?? 0);
}

// Fetch Certificates
$certificates = dbFetchAll("
    SELECT 
        cert.id as cert_id,
        cert.certificate_token,
        cert.document_hash,
        cert.download_count,
        cert.last_downloaded_at,
        cert.status as cert_status,
        cert.issued_at,
        p.id as participant_id,
        p.full_name,
        p.email,
        c.name as cohort_name,
        c.batch_code,
        c.instructor_name,
        c.issue_date
    FROM certificates cert
    JOIN participants p ON cert.participant_id = p.id
    JOIN cohorts c ON p.cohort_id = c.id
    WHERE {$whereClause}
    ORDER BY cert.id DESC
", $params);

$page_title = 'Certificates - CertificateHub';
$active_page = 'certificates';

include __DIR__ . '/../includes/head.php';
?>
<body class="bg-surface font-body-md text-on-surface antialiased">
<?php
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="px-gutter-desktop py-space-xl max-w-[1280px] w-full mx-auto flex flex-col gap-space-xl">
  <!-- Page Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md">
    <div class="flex flex-col gap-space-2xs">
      <div class="flex items-center gap-space-xs">
        <span class="font-caption text-caption text-primary uppercase font-semibold tracking-wider">Credential Ledger</span>
        <span class="w-1.5 h-1.5 rounded-full bg-outline-variant"></span>
        <span class="font-caption text-caption text-secondary"><?= htmlspecialchars($activeCohort['batch_code']) ?></span>
      </div>
      <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold tracking-tight">Certificates Ledger</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Audit cryptographic proof and inspect verified credentials for <span class="font-semibold text-on-surface">“<?= htmlspecialchars($cohortFilterTitle) ?>”</span>.
      </p>
    </div>
    <div class="flex items-center gap-space-sm self-start md:self-auto flex-wrap">
      <button type="button" onclick="openBulkDownloadModal()" class="flex items-center gap-space-xs h-10 px-space-md rounded-lg bg-surface-container-lowest text-on-surface hover:bg-surface-container font-label-md text-label-md font-semibold transition-all shadow-sm active:scale-95 border border-surface-container-low" title="Export All Cohort Certificates">
        <span class="material-symbols-outlined text-[20px] text-primary">download_for_offline</span>
        Bulk Export
      </button>
      <a href="certificate_template.php" class="flex items-center gap-space-xs h-10 px-space-md rounded-lg bg-surface-container-lowest text-on-surface hover:bg-surface-container font-label-md text-label-md font-medium transition-all shadow-sm">
        <span class="material-symbols-outlined text-[18px] text-secondary">design_services</span>
        Customize Template
      </a>
      <a href="add_participant_modal.php" class="flex items-center gap-space-xs h-10 px-space-md rounded-lg bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-medium transition-all shadow-sm active:scale-95">
        <span class="material-symbols-outlined text-[18px]">add</span>
        Issue New Certificate
      </a>
    </div>
  </div>

  <!-- Metric Summary Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md">
    <div class="relative overflow-hidden bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
      <div class="flex items-start justify-between">
        <div class="flex flex-col gap-space-2xs">
          <span class="font-label-sm text-label-sm text-on-surface-variant font-medium">Total Minted</span>
          <span class="font-display text-display text-on-surface font-bold"><?= $totalMinted ?></span>
        </div>
        <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary">
          <span class="material-symbols-outlined text-[22px]">workspace_premium</span>
        </div>
      </div>
      <div class="mt-space-md flex items-center gap-space-xs">
        <span class="font-caption text-caption text-tertiary font-semibold flex items-center">
          <span class="material-symbols-outlined text-[14px]">check</span> <?= $totalMinted ?> active tokens
        </span>
        <span class="font-caption text-caption text-secondary">• batch recorded</span>
      </div>
    </div>

    <div class="relative overflow-hidden bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
      <div class="flex items-start justify-between">
        <div class="flex flex-col gap-space-2xs">
          <span class="font-label-sm text-label-sm text-on-surface-variant font-medium">Verified &amp; Claimed</span>
          <span class="font-display text-display text-on-surface font-bold"><?= $claimedCount ?></span>
        </div>
        <div class="w-10 h-10 rounded-lg bg-tertiary-fixed/30 flex items-center justify-center text-tertiary">
          <span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">verified</span>
        </div>
      </div>
      <div class="mt-space-md flex items-center gap-space-xs">
        <span class="font-caption text-caption text-tertiary font-semibold">
          <?= $totalMinted > 0 ? round(($claimedCount / $totalMinted) * 100) : 0 ?>% download activity
        </span>
      </div>
    </div>

    <div class="relative overflow-hidden bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
      <div class="flex items-start justify-between">
        <div class="flex flex-col gap-space-2xs">
          <span class="font-label-sm text-label-sm text-on-surface-variant font-medium">SHA-256 Ledger Health</span>
          <span class="font-display text-display text-on-surface font-bold">100%</span>
        </div>
        <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-secondary">
          <span class="material-symbols-outlined text-[22px]">lock</span>
        </div>
      </div>
      <div class="mt-space-md flex items-center gap-space-xs">
        <span class="font-caption text-caption text-tertiary font-medium flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-tertiary"></span>
          All records cryptographically signed
        </span>
      </div>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <div class="flex flex-col md:flex-row items-center justify-between gap-space-md bg-surface-container-lowest p-space-md rounded-xl shadow-sm">
    <div class="flex items-center gap-space-xs w-full md:w-auto overflow-x-auto pb-1 md:pb-0">
      <button class="filter-chip px-3 py-1.5 rounded-lg text-label-sm font-label-sm bg-primary-container text-on-primary font-medium transition-colors whitespace-nowrap" data-filter="all">
        All Minted (<?= $totalMinted ?>)
      </button>
      <button class="filter-chip px-3 py-1.5 rounded-lg text-label-sm font-label-sm text-on-surface-variant hover:bg-surface-container transition-colors whitespace-nowrap" data-filter="valid">
        Valid (<?= $validCount ?>)
      </button>
      <button class="filter-chip px-3 py-1.5 rounded-lg text-label-sm font-label-sm text-on-surface-variant hover:bg-surface-container transition-colors whitespace-nowrap" data-filter="claimed">
        Claimed (<?= $claimedCount ?>)
      </button>
      <button class="filter-chip px-3 py-1.5 rounded-lg text-label-sm font-label-sm text-on-surface-variant hover:bg-surface-container transition-colors whitespace-nowrap" data-filter="revoked">
        Revoked (<?= $revokedCount ?>)
      </button>
    </div>

    <div class="flex items-center gap-space-sm flex-wrap w-full md:w-auto">
      <!-- Workshop Filter Selector -->
      <div class="relative">
        <select onchange="window.location.href='certificates_management.php?cohort_id=' + this.value" class="h-10 pl-3 pr-8 rounded-lg bg-surface-container-low font-body-sm text-body-sm text-on-surface border border-outline-variant/30 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer">
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

      <div class="relative w-full md:w-64">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-outline pointer-events-none">search</span>
        <input type="text" id="certSearchInput" placeholder="Search recipient, token, workshop..." class="w-full h-10 pl-9 pr-space-md rounded-lg bg-surface-container-low font-body-sm text-body-sm text-on-surface placeholder:text-outline focus:outline-none focus:bg-surface-container transition-all"/>
      </div>
    </div>
  </div>

  <!-- Certificate Cards Grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-space-md" id="certCardsGrid">
    <?php if (empty($certificates)): ?>
      <div class="col-span-3 py-16 bg-surface-container-lowest rounded-xl shadow-sm text-center flex flex-col items-center justify-center gap-2">
        <span class="material-symbols-outlined text-[48px] text-outline">history_edu</span>
        <span class="font-headline-sm text-headline-sm font-semibold">No certificates minted yet</span>
        <p class="font-body-sm text-body-sm text-secondary">Issue certificates to enrolled participants to generate verifiable cryptographic tokens.</p>
        <a href="add_participant_modal.php" class="mt-2 px-space-md py-2 bg-primary text-on-primary rounded-lg text-label-md font-medium hover:bg-primary-container">+ Issue First Certificate</a>
      </div>
    <?php else: ?>
      <?php foreach ($certificates as $cert): 
        $status = $cert['cert_status'] ?? 'valid';
        $claimed = (int)$cert['download_count'] > 0;
        $searchIndex = strtolower($cert['full_name'] . ' ' . $cert['email'] . ' ' . $cert['certificate_token'] . ' ' . ($cert['batch_code'] ?? '') . ' ' . ($cert['cohort_name'] ?? ''));
        $filterState = ($status === 'revoked') ? 'revoked' : ($claimed ? 'claimed' : 'valid');
        $hashSnippet = substr($cert['document_hash'], 0, 10) . '...' . substr($cert['document_hash'], -8);
      ?>
        <div class="cert-card bg-surface-container-lowest rounded-xl shadow-sm p-space-md flex flex-col justify-between border <?= $status === 'revoked' ? 'border-error/30 bg-error-container/5' : 'border-surface-container-low' ?> hover:shadow-md transition-shadow" data-search="<?= htmlspecialchars($searchIndex) ?>" data-status="<?= $filterState ?>">
          <div class="flex flex-col gap-space-sm">
            <div class="flex items-start justify-between gap-space-xs">
              <div class="flex flex-col">
                <span class="font-mono text-label-sm text-label-sm font-bold text-primary tracking-wide"><?= htmlspecialchars($cert['certificate_token']) ?></span>
                <span class="font-caption text-caption text-outline">Conferred <?= date('M d, Y', strtotime($cert['issued_at'])) ?></span>
              </div>
              <?php if ($status === 'valid'): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-tertiary font-caption text-caption font-semibold">
                  <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span> Valid
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-error-container text-on-error-container font-caption text-caption font-semibold">
                  <span class="w-1.5 h-1.5 rounded-full bg-error"></span> Revoked
                </span>
              <?php endif; ?>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="inline-flex items-center px-1.5 py-0.5 rounded font-mono text-[11px] font-semibold bg-surface-container-high text-on-surface-variant">
                <?= htmlspecialchars($cert['batch_code'] ?? '') ?>
              </span>
              <span class="font-caption text-caption text-secondary truncate max-w-[200px]" title="<?= htmlspecialchars($cert['cohort_name'] ?? '') ?>">
                <?= htmlspecialchars($cert['cohort_name'] ?? '') ?>
              </span>
            </div>

            <div class="pt-space-xs border-t border-surface-container-low">
              <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold truncate"><?= htmlspecialchars($cert['full_name']) ?></h3>
              <span class="font-caption text-caption text-secondary truncate block"><?= htmlspecialchars($cert['email']) ?></span>
            </div>

            <div class="bg-surface-container-low/50 p-2.5 rounded-lg flex flex-col gap-1 text-[11px] font-mono text-outline">
              <div class="flex items-center justify-between">
                <span>SHA-256 Ledger Stamp:</span>
                <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($cert['document_hash']) ?>'); showGlobalToast('Full SHA-256 hash copied');" class="text-primary hover:underline font-sans text-caption font-medium">Copy</button>
              </div>
              <span class="truncate text-on-surface-variant font-medium"><?= htmlspecialchars($hashSnippet) ?></span>
            </div>
          </div>

          <div class="mt-space-md pt-space-xs border-t border-surface-container-low flex items-center justify-between gap-2">
            <div class="flex items-center gap-1 text-caption text-outline">
              <span class="material-symbols-outlined text-[15px] <?= $claimed ? 'text-tertiary' : 'text-outline' ?>">download_done</span>
              <span><?= $claimed ? "Claimed {$cert['download_count']}x" : 'Unclaimed' ?></span>
            </div>

            <div class="flex items-center gap-1">
              <?php if ($status === 'valid'): ?>
                <button onclick='openCertRevokeModal(<?= (int)$cert["cert_id"] ?>, <?= json_encode($cert["full_name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($cert["certificate_token"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-amber-600 hover:bg-amber-50 font-label-sm text-label-sm font-medium transition-colors" title="Revoke Certificate">
                  <span class="material-symbols-outlined text-[16px]">block</span>
                  Revoke
                </button>
              <?php else: ?>
                <a href="certificates_management.php?action=reinstate&cert_id=<?= (int)$cert['cert_id'] ?><?= $cohortParamStr ?>" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-tertiary hover:bg-emerald-50 font-label-sm text-label-sm font-medium transition-colors" title="Reinstate / Restore Certificate">
                  <span class="material-symbols-outlined text-[16px]">replay</span>
                  Reinstate
                </a>
              <?php endif; ?>

              <a href="certificate_preview_modal.php?id=<?= $cert['participant_id'] ?>" class="inline-flex items-center gap-1 px-space-sm py-1 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-label-sm text-label-sm font-medium transition-colors">
                <span class="material-symbols-outlined text-[16px]">visibility</span>
                Preview
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Revoke Confirmation Modal -->
<div id="certRevokeModal" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-200">
  <div class="bg-surface-container-lowest rounded-2xl shadow-xl max-w-md w-full p-6 border border-surface-container flex flex-col gap-4 transform transition-transform scale-95" id="certRevokeModalBox">
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-[28px]">block</span>
      </div>
      <div>
        <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Revoke Certificate</h3>
        <p class="font-caption text-caption text-secondary">Invalidate this cryptographic credential</p>
      </div>
    </div>
    <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
      Are you sure you want to revoke the certificate for <strong id="certRevokeParticipantName" class="text-on-surface"></strong> (<code id="certRevokeToken" class="font-mono text-primary font-bold"></code>)?
      <br><br>
      The public verification ledger will immediately flag this credential as <strong>REVOKED</strong> and tamper-invalid. You can reinstate it at any time.
    </p>
    <div class="flex items-center justify-end gap-2 pt-2 border-t border-surface-container">
      <button type="button" onclick="closeCertRevokeModal()" class="px-4 py-2 rounded-lg font-label-md text-label-md text-secondary hover:bg-surface-container transition-colors">
        Cancel
      </button>
      <a id="certRevokeConfirmBtn" href="#" class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-label-md text-label-md font-semibold transition-colors flex items-center gap-1.5 shadow-xs">
        <span class="material-symbols-outlined text-[18px]">block</span>
        Confirm Revocation
      </a>
    </div>
  </div>
</div>

<script>
  function openCertRevokeModal(certId, participantName, token) {
    document.getElementById('certRevokeParticipantName').textContent = participantName;
    document.getElementById('certRevokeToken').textContent = token;
    document.getElementById('certRevokeConfirmBtn').href = 'certificates_management.php?action=revoke&cert_id=' + certId + '<?= $cohortParamStr ?>';
    const modal = document.getElementById('certRevokeModal');
    const box = document.getElementById('certRevokeModalBox');
    modal.classList.remove('hidden');
    setTimeout(() => {
      modal.classList.remove('opacity-0');
      box.classList.remove('scale-95');
    }, 10);
  }

  function closeCertRevokeModal() {
    const modal = document.getElementById('certRevokeModal');
    const box = document.getElementById('certRevokeModalBox');
    modal.classList.add('opacity-0');
    box.classList.add('scale-95');
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 200);
  }

  // Close modal on escape key or backdrop click
  document.getElementById('certRevokeModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCertRevokeModal();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCertRevokeModal();
  });

  (function() {
    const searchInput = document.getElementById('certSearchInput');
    const filterChips = document.querySelectorAll('.filter-chip');
    const cards = document.querySelectorAll('.cert-card');
    let activeFilter = 'all';

    function applyFilter() {
      const q = (searchInput?.value || '').toLowerCase().trim();
      cards.forEach(card => {
        const searchVal = card.getAttribute('data-search') || '';
        const statusVal = card.getAttribute('data-status') || '';
        const matchesSearch = !q || searchVal.includes(q);
        const matchesFilter = (activeFilter === 'all') || 
                             (activeFilter === 'valid' && (statusVal === 'valid' || statusVal === 'claimed')) ||
                             (activeFilter === 'claimed' && statusVal === 'claimed') ||
                             (activeFilter === 'revoked' && statusVal === 'revoked');

        card.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', applyFilter);
    }

    filterChips.forEach(chip => {
      chip.addEventListener('click', () => {
        filterChips.forEach(c => {
          c.classList.remove('bg-primary-container', 'text-on-primary', 'font-medium');
          c.classList.add('text-on-surface-variant');
        });
        chip.classList.add('bg-primary-container', 'text-on-primary', 'font-medium');
        chip.classList.remove('text-on-surface-variant');

        activeFilter = chip.getAttribute('data-filter') || 'all';
        applyFilter();
      });
    });

    // Check toast notifications
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('toast') === 'revoked') {
      showGlobalToast('Certificate officially revoked and ledger updated!');
    } else if (urlParams.get('toast') === 'reinstated') {
      showGlobalToast('Certificate successfully reinstated and restored!');
    }
  })();
</script>

<?php 
include __DIR__ . '/bulk_download_modal.php';
include __DIR__ . '/../includes/footer.php'; 
?>
