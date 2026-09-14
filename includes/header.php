<?php
require_once __DIR__ . '/db.php';
?>
<div class="pl-64">
  <header class="fixed top-0 left-64 right-0 h-16 bg-surface-container-lowest/95 backdrop-blur-md border-b border-surface-container shadow-[0_1px_8px_rgba(0,0,0,0.03)] z-40 flex items-center justify-between px-gutter-desktop">
    <div class="flex items-center gap-space-md min-w-0">
      <!-- Dedicated sidebar-page handles workshops/cohorts; header remains clean -->
    </div>
    <div class="flex items-center gap-space-md shrink-0">
      <div class="relative hidden lg:flex items-center">
        <span class="material-symbols-outlined absolute left-3 text-[18px] text-outline pointer-events-none">search</span>
        <input class="h-9 pl-9 pr-space-md rounded-lg bg-surface-container-low font-body-sm text-body-sm text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary/20 w-64" placeholder="Search credentials, recipients..." type="text" id="globalNavSearch"/>
      </div>
      <button aria-label="Notifications" class="relative p-2 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" onclick="showGlobalToast('No unread notifications')">
        <span class="material-symbols-outlined text-[20px]">notifications</span>
      </button>
      <a href="add_participant_modal.php" class="flex items-center gap-space-xs h-9 px-space-md rounded-lg bg-primary text-on-primary font-label-md text-label-md font-medium hover:bg-primary-container transition-colors shadow-sm">
        <span class="material-symbols-outlined text-[18px]">add</span>
        Add Participant
      </a>
      <div class="flex items-center pl-space-xs">
        <img alt="Profile" class="w-8 h-8 rounded-full object-cover ring-2 ring-surface-container-high" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDQUR22ex0RRtMTx6AinRVS96-9Cu7DjdXDZD6VA95RnRAKhp10EZx4i3yxciKN04OfEMbYC_K7ZJRvvOUehBhXhStJvNU4zuErBqAjDIbURt1q3om8Wflvw7sZVQbmqRMRqJz3hugUJaSe3F34AyOF5R6Ai_xFl6TZ-nUUwzn-OvtPGI5BDM0g663Q-I6KNiXpQfM27JCrzzPZxsKFdVa5-FmbNYSMBPgaQj_tS_LsKDfB3xThzzE"/>
      </div>
    </div>
  </header>
  <main class="w-full pt-16 bg-surface min-h-screen">
