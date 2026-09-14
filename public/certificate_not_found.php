<?php
$page_title = 'Certificate Not Found - CertificateHub Verification Desk';
$target_email = htmlspecialchars($_GET['email'] ?? 'user@unknown.com');
include __DIR__ . '/../includes/head.php';
?>
<body class="bg-surface font-body-md text-on-surface antialiased min-h-screen flex flex-col justify-between">
  <!-- Main Public Content Stage (No sidebar, no admin header) -->
  <main class="w-full flex-grow relative overflow-hidden px-gutter-mobile md:px-gutter-desktop py-space-xl md:py-space-2xl flex flex-col items-center justify-center">
    <!-- Ambient Blur Background -->
    <div class="pointer-events-none absolute -top-32 left-1/2 -translate-x-1/2 w-[720px] h-[360px] bg-gradient-to-b from-surface-container-high/60 via-surface-container-low/40 to-transparent rounded-full blur-3xl -z-10"></div>
    <div class="pointer-events-none absolute bottom-4 right-10 w-64 h-64 bg-secondary-container/30 rounded-full blur-2xl -z-10"></div>

    <div class="w-full max-w-[620px] mx-auto">
      <!-- Status Top Indicator -->
      <div class="mb-space-md flex items-center justify-between px-space-xs text-on-surface-variant">
        <div class="flex items-center gap-2">
          <img src="../simad_university_logo.png" alt="SIMAD University" class="w-8 h-8 object-contain shrink-0">
          <div class="flex flex-col">
            <span class="font-headline-sm text-label-md font-bold text-on-surface leading-tight">SIMAD University</span>
            <span class="font-caption text-[10px] uppercase tracking-wider text-secondary font-medium">Verification Support Desk</span>
          </div>
        </div>
        <div class="font-caption text-caption text-on-surface-variant/80">
          Ref ID: <span class="font-mono text-on-surface">ERR_REC_404_USR</span>
        </div>
      </div>

      <!-- Error Card Container -->
      <div class="bg-surface-container-lowest rounded-xl shadow-md p-space-lg md:p-space-xl relative overflow-hidden transition-all duration-300">
        <!-- Top Status Line -->
        <div class="absolute top-0 inset-x-0 h-1.5 bg-gradient-to-r from-error-container via-surface-container-highest to-secondary-container"></div>

        <div class="flex flex-col items-center text-center">
          <div class="relative mb-space-lg flex items-center justify-center">
            <div class="w-20 h-20 rounded-full bg-error-container/60 flex items-center justify-center shadow-inner">
              <div class="w-14 h-14 rounded-full bg-surface-container-lowest flex items-center justify-center shadow-sm">
                <span class="material-symbols-outlined text-[32px] text-error">search_off</span>
              </div>
            </div>
            <div class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-secondary-container flex items-center justify-center text-on-secondary-container shadow-sm">
              <span class="material-symbols-outlined text-[16px]">help</span>
            </div>
          </div>

          <div class="inline-flex items-center gap-space-xs px-space-sm py-0.5 rounded-full bg-error-container text-on-error-container font-label-sm text-label-sm mb-space-sm">
            <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
            No matching credential
          </div>

          <h1 class="font-headline-lg text-headline-lg text-on-surface font-semibold tracking-tight mb-space-xs">
            Certificate Not Found
          </h1>

          <p class="font-body-md text-body-md text-on-surface-variant max-w-lg leading-relaxed mb-space-lg">
            We couldn't find a certificate associated with
            <span class="font-label-md text-label-md text-on-surface bg-surface-container px-space-xs py-0.5 rounded font-medium inline-block my-0.5" id="target-email-display"><?= $target_email ?></span>.
            Please double-check your email spelling or contact your workshop organizer if you believe this is an error.
          </p>

          <!-- Retry Form -->
          <form class="w-full text-left space-y-space-md" id="verify-retry-form" onsubmit="event.preventDefault(); handleRetry();">
            <div>
              <div class="flex items-center justify-between mb-space-2xs">
                <label class="font-label-sm text-label-sm font-medium text-on-surface" for="search-email-input">
                  Participant Email Address
                </label>
                <span class="font-caption text-caption text-error flex items-center gap-space-2xs">
                  <span class="material-symbols-outlined text-[13px]">error</span>
                  Unrecognized registry record
                </span>
              </div>
              <div class="relative flex items-center rounded-lg bg-surface-container-lowest shadow-sm transition-all focus-within:ring-2 focus-within:ring-primary-container">
                <span class="material-symbols-outlined absolute left-3 text-[20px] text-on-surface-variant pointer-events-none">mail</span>
                <input autocomplete="email" class="w-full h-10 pl-10 pr-10 rounded-lg bg-transparent font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none" id="search-email-input" placeholder="name@organization.com" required="" type="email" value="<?= $target_email ?>"/>
                <button aria-label="Clear email input" class="absolute right-2.5 p-1 rounded-md text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors" id="clear-email-btn" onclick="clearEmailInput()" type="button">
                  <span class="material-symbols-outlined text-[18px]">cancel</span>
                </button>
              </div>
              <p class="font-caption text-caption text-on-surface-variant mt-1.5">
                Previously checked against: <span class="font-medium text-on-surface">AI &amp; Machine Learning Intensive 2026</span>
              </p>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-space-sm pt-space-xs">
              <button class="flex-1 flex items-center justify-center gap-space-xs h-10 px-space-lg rounded-lg bg-primary text-on-primary font-label-md text-label-md font-medium hover:bg-primary-container active:scale-[0.99] transition-all shadow-sm" id="submit-btn" type="submit">
                <span class="material-symbols-outlined text-[18px]">cached</span>
                <span>Try Again</span>
              </button>
              <a href="public_certificate_download.php" class="flex items-center justify-center gap-space-2xs h-10 px-space-md rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-label-md text-label-md transition-colors">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                <span>Back to Search</span>
              </a>
            </div>
          </form>

          <div class="mt-space-lg w-full text-center">
            <a class="inline-flex items-center gap-space-2xs font-label-sm text-label-sm text-primary hover:text-primary-container transition-colors group" href="mailto:support@techworkshop.io">
              <span>Need help? Contact event organizer</span>
              <span class="material-symbols-outlined text-[15px] group-hover:translate-x-0.5 transition-transform">arrow_forward</span>
            </a>
          </div>
        </div>

        <!-- Troubleshooting Tips -->
        <div class="mt-space-xl p-space-md rounded-lg bg-surface-container-low text-left">
          <div class="flex items-start gap-space-sm">
            <div class="p-1.5 rounded-md bg-secondary-container text-on-secondary-container mt-0.5 shrink-0">
              <span class="material-symbols-outlined text-[18px] block">tips_and_updates</span>
            </div>
            <div class="flex flex-col gap-space-2xs">
              <span class="font-label-sm text-label-sm font-semibold text-on-surface">Helpful troubleshooting tips</span>
              <ul class="font-body-sm text-body-sm text-on-surface-variant space-y-1">
                <li class="flex items-center gap-space-xs">
                  <span class="w-1.5 h-1.5 rounded-full bg-outline-variant"></span>
                  Make sure to use the exact email address provided on your workshop registration or ticketing form.
                </li>
                <li class="flex items-center gap-space-xs">
                  <span class="w-1.5 h-1.5 rounded-full bg-outline-variant"></span>
                  Corporate single sign-on alias? Check if you registered with your personal or alternate work alias.
                </li>
                <li class="flex items-center gap-space-xs">
                  <span class="w-1.5 h-1.5 rounded-full bg-outline-variant"></span>
                  Certificates usually generate within 2 hours of workshop attendance sign-off.
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Support Cards -->
      <div class="mt-space-lg grid grid-cols-1 md:grid-cols-3 gap-space-sm">
        <div class="p-space-md rounded-lg bg-surface-container-lowest shadow-sm flex flex-col justify-between">
          <div class="flex items-center gap-space-xs text-on-surface mb-space-xs">
            <span class="material-symbols-outlined text-[18px] text-tertiary">verified_user</span>
            <span class="font-label-sm text-label-sm font-semibold">Instant Authenticity</span>
          </div>
          <p class="font-caption text-caption text-on-surface-variant">Each issued certificate is cryptographically sealed for LinkedIn &amp; CV sharing.</p>
        </div>
        <div class="p-space-md rounded-lg bg-surface-container-lowest shadow-sm flex flex-col justify-between">
          <div class="flex items-center gap-space-xs text-on-surface mb-space-xs">
            <span class="material-symbols-outlined text-[18px] text-primary">badge</span>
            <span class="font-label-sm text-label-sm font-semibold">Attendee ID</span>
          </div>
          <p class="font-caption text-caption text-on-surface-variant">Have your Eventbrite or Luma order number? Reach organizers with that code.</p>
        </div>
        <div class="p-space-md rounded-lg bg-surface-container-lowest shadow-sm flex flex-col justify-between">
          <div class="flex items-center gap-space-xs text-on-surface mb-space-xs">
            <span class="material-symbols-outlined text-[18px] text-secondary">support_agent</span>
            <span class="font-label-sm text-label-sm font-semibold">Live Support</span>
          </div>
          <p class="font-caption text-caption text-on-surface-variant">Certification desk team answers ticket inquiries within 1 business day.</p>
        </div>
      </div>

      <div class="mt-space-lg text-center font-caption text-caption text-outline flex items-center justify-center gap-space-sm">
        <span>Public Portal v2.4</span>
        <span>•</span>
        <span>CertificateHub Verification Engine</span>
        <span>•</span>
        <a class="hover:text-on-surface transition-colors text-primary" href="public_certificate_download.php">Portal Home</a>
        <span>•</span>
        <a class="hover:text-on-surface transition-colors text-primary" href="../admin/organizer_sign_in.php">Organizer Sign In</a>
      </div>
    </div>
  </main>

  <script>
    function clearEmailInput() {
      const input = document.getElementById('search-email-input');
      if (input) {
        input.value = '';
        input.focus();
      }
    }

    function handleRetry() {
      const input = document.getElementById('search-email-input');
      const submitBtn = document.getElementById('submit-btn');
      const displaySpan = document.getElementById('target-email-display');

      if (!input || !input.value.trim()) {
        input.focus();
        return;
      }

      const emailVal = input.value.trim();

      const originalHTML = submitBtn.innerHTML;
      submitBtn.innerHTML = `
        <span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
        <span>Searching...</span>
      `;
      submitBtn.disabled = true;

      window.location.href = 'certificate_found_result.php?email=' + encodeURIComponent(emailVal);
    }
  </script>
</body>
</html>
