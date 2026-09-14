<?php
$page_title = 'Sign In - CertificateHub';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// If already logged in, redirect straight to dashboard
if (isLoggedIn()) {
    header('Location: organizer_dashboard.php');
    exit;
}

$errorMessage = null;
$email = '';

// Handle Sign-in POST Request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errorMessage = 'Please enter both your email address and password.';
    } else {
        try {
            $admin = dbFetchOne("SELECT id, name, email, password_hash, role FROM admins WHERE email = ?", [$email]);
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // Set authenticated admin session variables
                $_SESSION['admin_id']    = $admin['id'];
                $_SESSION['admin_name']  = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role']  = $admin['role'];

                header('Location: organizer_dashboard.php');
                exit;
            } else {
                $errorMessage = 'Invalid email address or password. Please try again.';
            }
        } catch (Exception $e) {
            $errorMessage = 'Authentication service is temporarily unavailable. Please try again.';
        }
    }
}

include __DIR__ . '/../includes/head.php';
?>
<body class="bg-surface font-body-md text-on-surface antialiased min-h-screen flex flex-col items-center justify-center p-gutter-mobile">

  <main class="w-full max-w-[480px] bg-surface-container-lowest rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-surface-container p-space-xl transition-all">
    <div class="flex flex-col w-full">
      <!-- Header / Logo -->
      <div class="flex items-center justify-between pb-space-lg mb-space-lg border-b border-surface-container">
        <div class="flex items-center gap-space-sm">
          <img alt="SIMAD University" class="w-12 h-12 object-contain drop-shadow-xs" src="../simad_university_logo.png"/>
          <div class="flex flex-col">
            <span class="font-headline-md text-headline-md tracking-tight text-on-surface font-bold">SIMAD</span>
            <span class="font-caption text-[11px] font-semibold text-secondary uppercase tracking-widest -mt-1">Certificates Admin</span>
          </div>
        </div>
      </div>

      <!-- Welcome Message -->
      <div class="flex flex-col space-y-space-2xs mb-space-lg">
        <h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight">Welcome back</h1>
      </div>

      <!-- Error / Notice Messages -->
      <?php if (!empty($errorMessage)): ?>
        <div class="mb-space-md p-space-sm bg-error-container text-on-error-container rounded-xl flex items-center gap-space-xs text-body-sm animate-in fade-in duration-150">
          <span class="material-symbols-outlined text-[20px] text-error shrink-0">error</span>
          <span><?= htmlspecialchars($errorMessage) ?></span>
        </div>
      <?php elseif (isset($_GET['logged_out'])): ?>
        <div class="mb-space-md p-space-sm bg-tertiary-fixed/30 text-tertiary rounded-xl flex items-center gap-space-xs text-body-sm animate-in fade-in duration-150">
          <span class="material-symbols-outlined text-[20px] text-tertiary shrink-0">check_circle</span>
          <span>You have been signed out successfully.</span>
        </div>
      <?php endif; ?>

      <!-- Sign In Form -->
      <form id="signInForm" class="flex flex-col space-y-space-md" method="POST" action="organizer_sign_in.php">
        <div class="flex flex-col space-y-space-2xs">
          <label class="font-label-md text-label-md text-on-surface font-medium" for="email">Work Email address</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3 text-secondary text-[20px] pointer-events-none">mail</span>
            <input class="w-full h-11 pl-10 pr-3 rounded-lg bg-surface-container-low text-on-surface placeholder:text-outline font-body-md text-body-md border border-transparent focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary-container/20 focus:bg-surface-container-lowest transition-all" id="email" name="email" placeholder="example@gmail.com" required type="email" value="<?= htmlspecialchars($email) ?>"/>
          </div>
        </div>

        <div class="flex flex-col space-y-space-2xs">
          <label class="font-label-md text-label-md text-on-surface font-medium" for="password">Password</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3 text-secondary text-[20px] pointer-events-none">key</span>
            <input class="w-full h-11 pl-10 pr-10 rounded-lg bg-surface-container-low text-on-surface placeholder:text-outline font-body-md text-body-md border border-transparent focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary-container/20 focus:bg-surface-container-lowest transition-all" id="password" name="password" placeholder="Enter your password" required type="password"/>
            <button aria-label="Toggle password visibility" class="absolute right-2.5 p-1.5 rounded-lg hover:bg-surface-container text-secondary hover:text-on-surface flex items-center justify-center transition-colors" id="togglePassword" type="button">
              <span class="material-symbols-outlined text-[18px]" id="eyeIcon">visibility</span>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between pt-space-2xs">
          <label class="flex items-center space-x-space-xs cursor-pointer select-none group">
            <input checked class="w-4 h-4 rounded text-primary-container bg-surface-container focus:ring-0 focus:outline-none cursor-pointer accent-primary-container" type="checkbox" name="remember"/>
            <span class="font-body-sm text-body-sm text-secondary group-hover:text-on-surface transition-colors">Remember me on this device</span>
          </label>
          <a class="font-label-sm text-label-sm text-primary hover:underline font-medium transition-colors" href="#">Forgot password?</a>
        </div>

        <div class="pt-space-xs flex flex-col space-y-space-sm">
          <button id="submitBtn" class="w-full h-11 bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-medium rounded-xl shadow-sm transition-all duration-150 flex items-center justify-center space-x-space-xs active:scale-[0.99]" type="submit">
            <span>Sign In to Dashboard</span>
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
          </button>

          <div class="relative flex py-space-xs items-center">
            <div class="flex-grow h-px bg-surface-container-highest"></div>
            <span class="flex-shrink mx-3 text-secondary font-caption text-caption uppercase tracking-wider">or continue with</span>
            <div class="flex-grow h-px bg-surface-container-highest"></div>
          </div>

          <button onclick="window.location.href='organizer_dashboard.php'" class="w-full h-10 bg-surface-container-low hover:bg-surface-container text-on-surface font-label-md text-label-md rounded-xl transition-all duration-150 flex items-center justify-center space-x-space-xs shadow-sm hover:shadow active:scale-[0.99]" type="button">
            <svg class="w-4 h-4" viewBox="0 0 24 24">
              <path d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z" fill="#4285F4"></path>
              <path d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z" fill="#34A853"></path>
              <path d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z" fill="#FBBC05"></path>
              <path d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z" fill="#EA4335"></path>
            </svg>
            <span>Sign in with Google / SSO</span>
          </button>
        </div>
      </form>
    </div>
  </main>

  <footer class="mt-space-lg text-center">
    <p class="font-caption text-caption text-secondary">
      © <?= date('Y') ?> CertificateHub
    </p>
  </footer>

  <script>
    (function() {
      const toggleBtn = document.getElementById('togglePassword');
      const pwdInput = document.getElementById('password');
      const eyeIcon = document.getElementById('eyeIcon');
      const signInForm = document.getElementById('signInForm');
      const submitBtn = document.getElementById('submitBtn');
      
      if (toggleBtn && pwdInput && eyeIcon) {
        toggleBtn.addEventListener('click', function() {
          const isPassword = pwdInput.type === 'password';
          pwdInput.type = isPassword ? 'text' : 'password';
          eyeIcon.textContent = isPassword ? 'visibility_off' : 'visibility';
        });
      }

      if (signInForm && submitBtn) {
        signInForm.addEventListener('submit', function(e) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = `
            <span class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
            <span>Authenticating...</span>
          `;
        });
      }
    })();
  </script>
</body>
</html>
