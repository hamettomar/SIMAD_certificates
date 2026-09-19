<?php
$page_title = 'Find My Certificate - CertificateHub';
include __DIR__ . '/../includes/head.php';
?>
<body class="bg-surface font-body-md text-on-surface antialiased min-h-screen flex flex-col justify-between">
  <!-- Main Public Content Stage (No sidebar, no admin header) -->
  <main class="w-full flex-grow flex items-center justify-center px-space-md py-space-xl relative overflow-hidden">
    <!-- Ambient Glow Background Ornaments -->
    <div class="pointer-events-none absolute -top-16 left-1/2 -translate-x-1/2 w-96 h-96 bg-primary/5 rounded-full blur-3xl -z-10"></div>
    <div class="pointer-events-none absolute top-48 -left-20 w-64 h-64 bg-tertiary-fixed/20 rounded-full blur-2xl -z-10"></div>

    <div class="relative w-full max-w-2xl mx-auto">
      <!-- Main Container Card -->
      <div class="bg-surface-container-lowest rounded-xl shadow-xl p-space-lg sm:p-space-2xl transition-all duration-300">
        <!-- Brand & Badge Header Area -->
        <div class="flex flex-col items-center text-center">
          <div class="flex items-center gap-1 mb-space-lg">
            <img src="../simad_university_logo.png" alt="SIMAD University Logo" class="w-12 h-12 object-contain shrink-0 drop-shadow-sm">
            <div class="flex items-center gap-space-2xs">
              <span class="font-headline-sm text-headline-sm text-on-surface font-bold tracking-tight">SIMAD Certificates</span>
            </div>
          </div>

          <div class="relative flex items-center justify-center w-16 h-16 rounded-full bg-primary-fixed mb-space-md shadow-sm">
            <div class="absolute inset-0 rounded-full bg-primary/10 animate-ping opacity-25"></div>
            <span class="material-symbols-outlined text-primary text-[32px]" style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
          </div>

          <h1 class="font-headline-lg text-headline-lg sm:font-display sm:text-display text-on-surface font-semibold tracking-tight">
            Your Certificate is Ready
          </h1>
          <p class="font-body-md text-body-md text-on-surface-variant max-w-md mt-space-xs leading-relaxed">
            Enter the email address you used during the workshop to find, preview, and download your authentic credential.
          </p>
        </div>

        <!-- Form Search Card -->
        <form class="mt-space-xl flex flex-col gap-space-md" id="lookupForm" onsubmit="event.preventDefault(); handleLookup();">
          <div class="flex flex-col gap-1.5 text-left">
            <label class="font-label-md text-label-md text-on-surface font-medium flex items-center justify-between" for="emailInput">
              <span>Your Registered Email Address</span>
            </label>
            <div class="relative flex items-center">
              <span class="material-symbols-outlined absolute left-3.5 text-[20px] text-outline pointer-events-none">mail</span>
              <input class="w-full h-12 pl-11 pr-space-md rounded-lg bg-surface-container-low font-body-md text-body-md text-on-surface placeholder:text-outline focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary shadow-sm transition-all duration-150" id="emailInput" placeholder="name@example.com" required="" type="email"/>
            </div>
          </div>

          <button class="group relative flex items-center justify-center gap-space-xs w-full h-12 px-space-lg rounded-lg bg-primary text-on-primary font-label-md text-label-md font-medium hover:bg-primary-container shadow-md hover:shadow-lg transition-all duration-150 active:scale-[0.99]" id="submitBtn" type="submit">
            <span id="btnText">Find My Certificate</span>
            <span class="material-symbols-outlined text-[20px] transition-transform duration-150 group-hover:translate-x-1" id="btnIcon">arrow_forward</span>
          </button>
        </form>

        <!-- State: Match Found Result Box (Populated dynamically via AJAX) -->
        <div class="hidden mt-space-lg rounded-2xl bg-surface-container-lowest border border-primary/25 shadow-xl overflow-hidden transition-all duration-300 animate-in fade-in zoom-in-95" id="resultMatch">
          
          <!-- Top Header Banner: Verified Badge & Token -->
          <div class="bg-gradient-to-r from-primary/10 via-primary/5 to-secondary/10 px-4 py-3 border-b border-surface-container flex items-center justify-between flex-wrap gap-2">
            <div class="inline-flex items-center gap-1.5 text-primary">
              <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">verified</span>
              <span class="font-label-sm text-caption sm:text-label-sm font-bold uppercase tracking-wider">Official Certificate Found</span>
            </div>
            <span class="font-mono text-[11px] sm:text-caption text-on-surface bg-white px-2.5 py-0.5 rounded-full font-bold border border-secondary/30 shadow-xs" id="matchTokenBadge">
              Verified Credential
            </span>
          </div>

          <!-- Main Credential Content Body -->
          <div class="p-4 sm:p-6 flex flex-col gap-4 text-left">
            
            <!-- Recipient Announcement -->
            <div class="flex flex-col">
              <span class="text-[11px] font-bold uppercase tracking-widest text-secondary flex items-center gap-1 mb-1">
                <span class="material-symbols-outlined text-[14px]">person</span>
                Conferred Recipient / Qofka Shahaadada Leh
              </span>
              <h2 class="text-xl sm:text-2xl font-bold text-on-surface tracking-tight leading-snug break-words" id="matchRecipientName">
                Participant Name
              </h2>
            </div>

            <!-- Program / Workshop Title (High Visibility Container) -->
            <div class="p-3.5 sm:p-4 rounded-xl bg-surface-container-low/70 border border-surface-container flex flex-col gap-1.5">
              <span class="text-[11px] font-bold uppercase tracking-wider text-outline flex items-center gap-1">
                <span class="material-symbols-outlined text-[15px] text-primary">workspace_premium</span>
                Workshop / Program Name
              </span>
              <h3 class="text-[15px] sm:text-[17px] font-semibold text-on-surface leading-snug break-words" id="matchCohortName">
                Workshop Title
              </h3>
              <div class="flex items-center flex-wrap gap-x-3 gap-y-1 pt-1 text-caption text-outline">
                <span class="inline-flex items-center gap-1.5 text-primary font-semibold">
                  <img src="../simad_university_logo.png" alt="SIMAD" class="w-4 h-4 object-contain">
                  <span>SIMAD University</span>
                </span>
              </div>
            </div>

            <!-- Metadata Grid -->
            <div class="grid grid-cols-2 gap-3 pt-1 text-caption">
              <div class="flex flex-col">
                <span class="text-outline uppercase text-[10px] tracking-wider font-semibold">Issue Date</span>
                <span class="font-label-md text-label-md font-semibold text-on-surface mt-0.5" id="matchIssueDate">—</span>
              </div>
              <div class="flex flex-col">
                <span class="text-outline uppercase text-[10px] tracking-wider font-semibold">Verification Status</span>
                <span class="font-label-md text-label-md font-semibold text-primary inline-flex items-center gap-1 mt-0.5">
                  <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                  Valid &amp; Authentic
                </span>
              </div>
              <div class="col-span-2 flex flex-col pt-1 border-t border-surface-container/60">
                <span class="text-outline uppercase text-[10px] tracking-wider font-semibold">Ledger Hash (SHA-256)</span>
                <span class="font-mono text-[11px] text-secondary truncate mt-0.5" id="matchHash" title="">e3b0c44298...</span>
              </div>
            </div>

            <!-- Full-Width Mobile-Optimized Call to Action -->
            <div class="pt-1">
              <a class="flex items-center justify-center gap-2 w-full h-12 px-space-lg rounded-xl bg-primary hover:bg-primary-container text-white font-label-md text-label-md font-bold transition-all shadow-md hover:shadow-lg active:scale-[0.99]" id="matchViewLink" href="certificate_found_result.php">
                <span class="material-symbols-outlined text-[20px]">verified</span>
                <span>View &amp; Download Official Certificate</span>
                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
              </a>
            </div>

          </div>
        </div>

        <!-- State: Not Found Alert -->
        <div class="hidden mt-space-lg p-space-md rounded-xl bg-error-container text-on-error-container transition-all duration-300 animate-in fade-in zoom-in-95 duration-200" id="resultNotFound">
          <div class="flex items-start gap-space-sm">
            <span class="material-symbols-outlined text-error text-[24px] shrink-0 mt-0.5">search_off</span>
            <div class="flex flex-col text-left">
              <span class="font-headline-sm text-headline-sm font-semibold text-on-error-container">No Credential Record Found</span>
              <p class="font-body-sm text-body-sm mt-0.5 text-on-error-container/90">
                We couldn't find an active certificate record for <strong id="queriedEmail" class="underline decoration-dotted">this query</strong>. Please check your spelling or verify if you registered under a different email.
              </p>
              <div class="mt-space-sm">
                <a id="notFoundDetailsLink" href="certificate_not_found.php" class="inline-flex items-center gap-1 font-label-sm text-label-sm text-error font-semibold hover:underline">
                  <span>Go to Support &amp; Verification Desk</span>
                  <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </a>
              </div>
            </div>
          </div>
        </div>

      <!-- Public Footer -->
      <div class="mt-space-lg text-center flex flex-col sm:flex-row items-center justify-center gap-y-2 gap-x-4 text-outline font-caption text-caption">
        <span>SIMAD University © <?= date('Y') ?></span>
        <span class="hidden sm:inline">•</span>
        <a href="verify.php" class="text-tertiary font-medium hover:underline flex items-center gap-1 justify-center">
          <span class="material-symbols-outlined text-[14px]">verified_user</span>
          Public Verification Ledger
        </a>

      </div>
    </div>
  </main>

  <script>
    async function handleLookup() {
      const input = document.getElementById('emailInput');
      const submitBtn = document.getElementById('submitBtn');
      const btnText = document.getElementById('btnText');
      const btnIcon = document.getElementById('btnIcon');
      const matchBox = document.getElementById('resultMatch');
      const notFoundBox = document.getElementById('resultNotFound');

      const val = (input?.value || '').trim();
      if (!val) {
        input?.focus();
        return;
      }

      // Show loading state
      const originalText = btnText.textContent;
      btnText.textContent = 'Verifying Registry...';
      if (btnIcon) btnIcon.classList.add('hidden');
      submitBtn.disabled = true;
      submitBtn.classList.add('opacity-75');

      try {
        const response = await fetch('api_lookup.php?email=' + encodeURIComponent(val));
        const res = await response.json();

        if (res && res.found && res.data) {
          // Hide not found box
          notFoundBox.classList.add('hidden');

          // Populate match box
          document.getElementById('matchRecipientName').textContent = res.data.recipient_name;
          document.getElementById('matchCohortName').textContent = res.data.cohort_name;
          document.getElementById('matchInstructor').textContent = res.data.instructor_name;
          document.getElementById('matchIssueDate').textContent = res.data.issue_date;
          if (res.data.certificate_token) {
            document.getElementById('matchTokenBadge').textContent = res.data.certificate_token;
          }
          document.getElementById('matchHash').textContent = res.data.hash_snippet;
          document.getElementById('matchHash').title = res.data.document_hash;
          document.getElementById('matchViewLink').href = res.data.view_url;

          // Reveal with smooth transition
          matchBox.classList.remove('hidden');
          matchBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
          // Hide match box
          matchBox.classList.add('hidden');

          // Populate error box
          document.getElementById('queriedEmail').textContent = val;
          const notFoundLink = document.getElementById('notFoundDetailsLink');
          if (notFoundLink) {
            notFoundLink.href = res?.support_url || ('certificate_not_found.php?email=' + encodeURIComponent(val));
          }

          // Reveal error box
          notFoundBox.classList.remove('hidden');
          notFoundBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      } catch (err) {
        console.error('Lookup request error:', err);
        // Fallback: redirect directly
        window.location.href = 'certificate_found_result.php?email=' + encodeURIComponent(val);
      } finally {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-75');
        btnText.textContent = originalText;
        if (btnIcon) btnIcon.classList.remove('hidden');
      }
    }
  </script>
</body>
</html>
