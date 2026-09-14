<?php
$active_page = $active_page ?? $current_page ?? 'dashboard';

// Fetch current admin profile if session is active
$adminProfile = function_exists('currentAdmin') ? currentAdmin() : null;
$adminDisplayName = htmlspecialchars($adminProfile['name'] ?? 'Sarah Jenkins');
$adminDisplayRole = htmlspecialchars($adminProfile['role'] ?? 'Lead Organizer');

if (!function_exists('nav_class')) {
  function nav_class($page, $active) {
    if ($page === $active) {
      return 'flex items-center gap-space-sm px-space-sm py-2.5 transition-all bg-primary text-white font-label-md font-semibold rounded-xl shadow-xs';
    }
    return 'flex items-center gap-space-sm px-space-sm py-2 rounded-xl font-label-md text-label-md text-on-surface-variant hover:bg-primary-fixed hover:text-primary transition-all';
  }
}
?>
<aside class="fixed left-0 top-0 h-full w-64 bg-surface-container-lowest border-r border-surface-container shadow-[0_1px_8px_rgba(0,0,0,0.04)] z-50 flex flex-col justify-between select-none">
  <div class="flex flex-col">
    <!-- Brand Logo -->
    <div class="h-16 px-space-md flex items-center border-b border-surface-container">
      <a href="organizer_dashboard.php" class="flex items-center gap-2.5 group">
        <?php 
          $sidebarLogoSrc = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/public/') !== false) ? '../simad_university_logo.png' : 'simad_university_logo.png';
        ?>
        <img src="<?= $sidebarLogoSrc ?>" alt="SIMAD University" class="w-10 h-10 object-contain shrink-0 group-hover:scale-105 transition-transform drop-shadow-xs" />
        <div class="flex flex-col leading-none">
          <span class="font-headline-sm text-[16px] font-bold tracking-tight text-on-surface group-hover:text-primary transition-colors">SIMAD</span>
          <span class="font-caption text-[11px] font-semibold text-secondary uppercase tracking-widest mt-0.5">Certificates</span>
        </div>
      </a>
    </div>

    <!-- Navigation Area -->
    <div class="px-space-md pt-space-md">
      <span class="font-caption text-caption uppercase tracking-wider text-outline px-space-xs block mb-space-xs">Workspace</span>
      <nav class="flex flex-col gap-space-2xs">
        <a href="organizer_dashboard.php" class="<?= nav_class('dashboard', $active_page) ?>" <?= $active_page === 'dashboard' ? 'aria-current="page"' : '' ?> data-path="dashboard">
          <span class="material-symbols-outlined text-[20px]">grid_view</span>
          Dashboard
        </a>
        <a href="cohorts_management.php" class="<?= nav_class('cohorts', $active_page) ?>" <?= $active_page === 'cohorts' ? 'aria-current="page"' : '' ?> data-path="cohorts">
          <span class="material-symbols-outlined text-[20px]">school</span>
          Workshops &amp; Cohorts
        </a>
        <a href="participants_management.php" class="<?= nav_class('participants', $active_page) ?>" <?= $active_page === 'participants' ? 'aria-current="page"' : '' ?> data-path="participants">
          <span class="material-symbols-outlined text-[20px]">group</span>
          Participants
        </a>
        <a href="certificates_management.php" class="<?= nav_class('certificates', $active_page) ?>" <?= $active_page === 'certificates' ? 'aria-current="page"' : '' ?> data-path="certificates">
          <span class="material-symbols-outlined text-[20px]">verified</span>
          Certificates
        </a>
        <a href="certificate_template.php" class="<?= nav_class('template', $active_page) ?>" <?= $active_page === 'template' ? 'aria-current="page"' : '' ?> data-path="template">
          <span class="material-symbols-outlined text-[20px]">design_services</span>
          Certificate Template
        </a>
        <a href="../public/public_certificate_download.php" target="_blank" class="flex items-center justify-between px-space-sm py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-all" data-path="public-portal">
          <span class="flex items-center gap-space-sm">
            <span class="material-symbols-outlined text-[20px]">open_in_new</span>
            Student Portal
          </span>
          <span class="material-symbols-outlined text-[16px] text-outline">north_east</span>
        </a>
        <a href="../public/verify.php" target="_blank" class="flex items-center justify-between px-space-sm py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-all" data-path="verify-portal">
          <span class="flex items-center gap-space-sm">
            <span class="material-symbols-outlined text-[20px] text-tertiary">verified_user</span>
            Verification Ledger
          </span>
          <span class="material-symbols-outlined text-[16px] text-outline">north_east</span>
        </a>
      </nav>
    </div>
  </div>

  <!-- Profile & Secondary Navigation Footer -->
  <div class="p-space-md flex flex-col gap-space-xs bg-surface-container-low/50">
    <div class="flex items-center gap-space-sm p-space-xs rounded-lg hover:bg-surface-container-low transition-colors">
      <img alt="Profile" class="w-8 h-8 rounded-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDQUR22ex0RRtMTx6AinRVS96-9Cu7DjdXDZD6VA95RnRAKhp10EZx4i3yxciKN04OfEMbYC_K7ZJRvvOUehBhXhStJvNU4zuErBqAjDIbURt1q3om8Wflvw7sZVQbmqRMRqJz3hugUJaSe3F34AyOF5R6Ai_xFl6TZ-nUUwzn-OvtPGI5BDM0g663Q-I6KNiXpQfM27JCrzzPZxsKFdVa5-FmbNYSMBPgaQj_tS_LsKDfB3xThzzE"/>
      <div class="flex flex-col overflow-hidden">
        <span class="font-label-sm text-label-sm text-on-surface font-semibold truncate"><?= $adminDisplayName ?></span>
        <span class="font-caption text-caption text-on-surface-variant truncate"><?= $adminDisplayRole ?></span>
      </div>
    </div>
    <div class="pt-space-xs flex flex-col gap-space-2xs">
      <a href="#" class="flex items-center gap-space-sm px-space-xs py-1.5 rounded-lg font-label-sm text-label-sm text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" data-path="settings">
        <span class="material-symbols-outlined text-[18px]">settings</span>
        Settings
      </a>
      <a href="logout.php" class="flex items-center gap-space-sm px-space-xs py-1.5 rounded-lg font-label-sm text-label-sm text-error hover:bg-error-container hover:text-on-error-container transition-colors" data-path="login">
        <span class="material-symbols-outlined text-[18px]">logout</span>
        Sign Out
      </a>
    </div>
  </div>
</aside>
