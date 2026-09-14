<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdminAuth();
require_once __DIR__ . '/../includes/db.php';

// Active Cohort & All Cohorts
$activeCohort = getActiveCohort();
$cohortId = (int)$activeCohort['id'];
$allCohorts = dbFetchAll("SELECT * FROM cohorts ORDER BY id DESC");

// Real Metric Counts for backdrop
$totalEnrolled   = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ?", [$cohortId])['c'] ?? 0);
$issuedCount     = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ? AND status = 'issued'", [$cohortId])['c'] ?? 0);
$pendingCount    = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants WHERE cohort_id = ? AND status = 'pending'", [$cohortId])['c'] ?? 0);
$issuanceRate    = $totalEnrolled > 0 ? round(($issuedCount / $totalEnrolled) * 100, 1) : 0;

$errorMessage = null;
$fullName = '';
$email = '';

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $fullName       = trim($_POST['fullName'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $targetCohortId = !empty($_POST['cohort_id']) ? (int)$_POST['cohort_id'] : $cohortId;
    $genMode        = $_POST['certificateStatus'] ?? 'issue_now';

    if (empty($fullName) || empty($email)) {
        $errorMessage = 'Full Name and Email Address are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please provide a valid email address.';
    } else {
        try {
            // Check for duplicate in target cohort
            $existing = dbFetchOne("SELECT id FROM participants WHERE cohort_id = ? AND email = ?", [$targetCohortId, $email]);
            if ($existing) {
                $errorMessage = 'A participant with this email address is already registered in this workshop.';
            } else {
                $status = ($genMode === 'issue_now') ? 'issued' : 'pending';

                // 1. Insert Participant into selected workshop
                dbQuery("
                    INSERT INTO participants (cohort_id, full_name, email, status)
                    VALUES (?, ?, ?, ?)
                ", [$targetCohortId, $fullName, $email, $status]);
                $participantId = (int) dbLastInsertId();

                // 2. Auto-issue certificate if requested
                if ($status === 'issued') {
                    $randomToken = generateNextCertificateToken($targetCohortId);
                    $docHash = hash('sha256', $randomToken . '|' . $fullName . '|' . $email . '|' . time());
                    
                    $template = dbFetchOne("SELECT id FROM certificate_templates WHERE cohort_id = ? LIMIT 1", [$targetCohortId]);
                    $templateId = (int)($template['id'] ?? 1);

                    dbQuery("
                        INSERT INTO certificates (participant_id, template_id, certificate_token, document_hash, status)
                        VALUES (?, ?, ?, ?, 'valid')
                    ", [$participantId, $templateId, $randomToken, $docHash]);
                }

                // Switch active session to this cohort if different
                setActiveCohort($targetCohortId);

                header('Location: participants_management.php?success=enrolled');
                exit;
            }
        } catch (Exception $e) {
            $errorMessage = 'Database error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Add Participant - CertificateHub';
$active_page = 'participants';

include __DIR__ . '/../includes/head.php';
?>
<body class="bg-surface font-body-md text-on-surface antialiased">
<?php
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col w-full relative">
  <!-- Underlying Contextual View (Participants Table Preview & Metric Summary) -->
  <div class="w-full px-gutter-desktop py-space-xl flex flex-col gap-space-lg select-none opacity-40 pointer-events-none transition-opacity duration-200">
    <!-- Top Action & Meta Bar -->
    <div class="flex items-center justify-between">
      <div class="flex flex-col">
        <span class="font-caption text-caption text-secondary uppercase tracking-widest"><?= htmlspecialchars($activeCohort['batch_code']) ?> Directory</span>
        <span class="font-headline-lg text-headline-lg text-on-surface">Registered Participants</span>
      </div>
      <div class="flex items-center gap-space-sm">
        <div class="h-9 px-space-md bg-surface-container-low rounded-lg flex items-center gap-space-xs text-on-surface-variant font-label-md text-label-md">
          <span class="material-symbols-outlined text-[18px]">filter_list</span>
          <span>Filter (All Cohorts)</span>
        </div>
      </div>
    </div>

    <!-- Quick Metrics Strip -->
    <div class="grid grid-cols-4 gap-space-md">
      <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex flex-col gap-space-2xs">
        <span class="font-caption text-caption text-secondary uppercase tracking-wider">Total Enrolled</span>
        <span class="font-display text-display text-on-surface leading-none"><?= $totalEnrolled ?></span>
      </div>
      <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex flex-col gap-space-2xs">
        <span class="font-caption text-caption text-secondary uppercase tracking-wider">Issued &amp; Verified</span>
        <span class="font-display text-display text-on-surface leading-none"><?= $issuedCount ?></span>
        <span class="font-caption text-caption text-secondary mt-space-2xs"><?= $issuanceRate ?>% completion</span>
      </div>
      <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex flex-col gap-space-2xs">
        <span class="font-caption text-caption text-secondary uppercase tracking-wider">Pending Release</span>
        <span class="font-display text-display text-on-surface leading-none"><?= $pendingCount ?></span>
      </div>
      <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex flex-col gap-space-2xs">
        <span class="font-caption text-caption text-secondary uppercase tracking-wider">Active Cohort</span>
        <span class="font-headline-sm text-headline-sm text-on-surface truncate"><?= htmlspecialchars($activeCohort['name']) ?></span>
      </div>
    </div>
  </div>

  <!-- Modal Backdrop Overlay -->
  <div class="fixed inset-0 z-50 flex items-center justify-center p-gutter-mobile md:p-space-lg bg-inverse-surface/40 backdrop-blur-sm transition-opacity duration-200" id="modalOverlay">
    <div class="relative w-full max-w-xl bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-200">
      <!-- Modal Header -->
      <div class="px-space-lg pt-space-lg pb-space-sm flex items-start justify-between border-b border-surface-container-low">
        <div class="flex flex-col gap-1">
          <div class="flex items-center gap-space-xs">
            <h2 class="font-headline-lg text-headline-lg text-on-surface font-semibold tracking-tight">Add Participant</h2>
          </div>
          <p class="font-body-sm text-body-sm text-secondary">
            Enter attendee information to register them for certificate generation in <span class="text-on-surface font-medium"><?= htmlspecialchars($activeCohort['name']) ?></span>.
          </p>
        </div>
        <a href="participants_management.php" class="w-8 h-8 rounded-lg flex items-center justify-center text-secondary hover:text-on-surface hover:bg-surface-container transition-colors" id="closeModalBtn" title="Close dialog">
          <span class="material-symbols-outlined text-[20px]">close</span>
        </a>
      </div>

      <!-- Error Banner -->
      <?php if (!empty($errorMessage)): ?>
        <div class="mx-space-lg mt-space-md p-space-sm bg-error-container text-on-error-container rounded-xl flex items-center gap-space-xs text-body-sm">
          <span class="material-symbols-outlined text-[20px] text-error shrink-0">error</span>
          <span><?= htmlspecialchars($errorMessage) ?></span>
        </div>
      <?php endif; ?>

      <!-- Modal Body / Form Inputs -->
      <form class="px-space-lg py-space-md flex flex-col gap-space-md" id="addParticipantForm" method="POST" action="add_participant_modal.php">
        <!-- Field 1: Full Name -->
        <div class="flex flex-col gap-1.5">
          <label class="font-label-md text-label-md text-on-surface font-semibold flex items-center justify-between" for="fullNameInput">
            <span>Full Name</span>
            <span class="font-caption text-caption text-outline">Required</span>
          </label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3 text-[18px] text-outline pointer-events-none">badge</span>
            <input class="w-full h-10 pl-9 pr-space-md rounded-lg bg-surface-container-low text-on-surface font-body-md text-body-md placeholder:text-outline border border-transparent focus:border-primary-container focus:bg-surface-container-lowest focus:outline-none transition-colors" id="fullNameInput" name="fullName" placeholder="Ahmed Omar" required type="text" value="<?= htmlspecialchars($fullName) ?>"/>
          </div>
        </div>

        <!-- Field 2: Email Address -->
        <div class="flex flex-col gap-1.5">
          <label class="font-label-md text-label-md text-on-surface font-semibold flex items-center justify-between" for="emailInput">
            <span>Email Address</span>
            <span class="font-caption text-caption text-outline">For credential retrieval</span>
          </label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3 text-[18px] text-outline pointer-events-none">mail</span>
            <input class="w-full h-10 pl-9 pr-space-md rounded-lg bg-surface-container-low text-on-surface font-body-md text-body-md placeholder:text-outline border border-transparent focus:border-primary-container focus:bg-surface-container-lowest focus:outline-none transition-colors" id="emailInput" name="email" placeholder="ahmed@example.com" required type="email" value="<?= htmlspecialchars($email) ?>"/>
          </div>
        </div>

        <!-- Field 3: Target Workshop Cohort -->
        <div class="flex flex-col gap-1.5">
          <label class="font-label-md text-label-md text-on-surface font-semibold flex items-center justify-between" for="cohortSelect">
            <span>Target Workshop / Cohort *</span>
            <span class="font-caption text-caption text-primary font-medium">Select program</span>
          </label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3 text-[18px] text-primary pointer-events-none">school</span>
            <select name="cohort_id" id="cohortSelect" required class="w-full h-10 pl-9 pr-space-md rounded-lg bg-surface-container-low text-on-surface font-body-md text-body-md border border-transparent focus:border-primary-container focus:bg-surface-container-lowest focus:outline-none transition-colors cursor-pointer">
              <?php foreach ($allCohorts as $ch): 
                $chStatus = getCohortComputedStatus($ch);
                $statusLabel = match($chStatus) {
                  'active' => '🟢 Ongoing',
                  'upcoming' => '🔵 Upcoming',
                  'archived' => '🟡 Archived',
                  default => '⚪ Completed'
                };
              ?>
                <option value="<?= (int)$ch['id'] ?>" <?= (int)$ch['id'] === $cohortId ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ch['name']) ?> (<?= htmlspecialchars($ch['batch_code']) ?>) — <?= $statusLabel ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Field 4: Generation Mode -->
        <div class="flex flex-col gap-space-xs pt-space-2xs">
          <span class="font-label-md text-label-md text-on-surface font-semibold">Certificate Generation Mode</span>
          <div class="flex flex-col gap-2">
            <label class="relative flex items-start gap-space-sm p-space-sm rounded-lg bg-surface-container-low hover:bg-surface-container cursor-pointer transition-all">
              <input checked class="mt-0.5 w-4 h-4 text-primary bg-surface-container-lowest focus:ring-0 cursor-pointer accent-primary" name="certificateStatus" type="radio" value="issue_now"/>
              <div class="flex flex-col leading-tight min-w-0">
                <span class="font-label-md text-label-md text-on-surface font-semibold flex items-center gap-1.5">
                  Issue certificate immediately upon creation
                  <span class="font-caption text-caption px-1.5 py-0.2 rounded-full bg-secondary-container text-on-secondary-container font-medium">Recommended</span>
                </span>
                <span class="font-caption text-caption text-secondary mt-0.5">
                  Generates unique verification token (CERT-...), creates SHA-256 hash, and marks ready for lookup.
                </span>
              </div>
            </label>
            <label class="relative flex items-start gap-space-sm p-space-sm rounded-lg bg-surface-container-lowest hover:bg-surface-container-low cursor-pointer transition-all">
              <input class="mt-0.5 w-4 h-4 text-primary bg-surface-container-lowest focus:ring-0 cursor-pointer accent-primary" name="certificateStatus" type="radio" value="draft"/>
              <div class="flex flex-col leading-tight min-w-0">
                <span class="font-label-md text-label-md text-on-surface font-semibold">Save as pending / unissued</span>
                <span class="font-caption text-caption text-secondary mt-0.5">
                  Attendee will be enrolled but the certificate will remain locked until issued.
                </span>
              </div>
            </label>
          </div>
        </div>

        <!-- Modal Footer Strip -->
        <div class="px-0 py-space-sm flex items-center justify-between gap-space-sm border-t border-surface-container-low mt-space-sm">
          <div class="flex items-center gap-1.5 font-caption text-caption text-secondary">
          </div>
          <div class="flex items-center gap-space-sm">
            <a href="participants_management.php" class="h-9 px-space-md rounded-lg font-label-md text-label-md text-secondary hover:text-on-surface hover:bg-surface-container transition-colors inline-flex items-center justify-center">
              Cancel
            </a>
            <button class="h-9 px-space-md rounded-lg bg-primary text-on-primary font-label-md text-label-md font-medium hover:bg-primary-container transition-all flex items-center gap-space-xs shadow-sm" id="submitBtn" type="submit">
              <span class="material-symbols-outlined text-[18px]">check</span>
              <span>Enroll &amp; Mint</span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
