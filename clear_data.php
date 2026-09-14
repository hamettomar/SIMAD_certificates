<?php
/**
 * Database Data Cleaner & Reset Utility
 * CertificateHub / SIMAD Certificates
 * 
 * Usage:
 * 1. Via CLI:
 *    php clear_data.php              (Clears participants and certificates, leaves 1 clean default workshop)
 *    php clear_data.php --full       (Full reset: wipes all participants, certificates, extra cohorts, and resets default template)
 *    php clear_data.php --certs-only (Only clears participants and certificates, keeps all workshops intact)
 * 
 * 2. Via Browser:
 *    http://localhost/certificate/clear_data.php
 */

require_once __DIR__ . '/includes/db.php';

$isCli = (php_sapi_name() === 'cli');

/**
 * Execute cleanup logic
 *
 * @param string $mode 'certs_only' | 'full_reset'
 * @return array
 */
function performDatabaseCleanup(string $mode = 'full_reset'): array {
    $db = getDB();
    $result = [
        'mode' => $mode,
        'participants_deleted' => 0,
        'certificates_deleted' => 0,
        'cohorts_deleted' => 0,
        'templates_deleted' => 0,
        'admins_preserved' => 0,
        'status' => 'success',
        'message' => ''
    ];

    try {
        // Count before deletion
        $result['participants_deleted'] = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants")['c'] ?? 0);
        $result['certificates_deleted'] = (int) (dbFetchOne("SELECT COUNT(*) as c FROM certificates")['c'] ?? 0);
        $result['admins_preserved'] = (int) (dbFetchOne("SELECT COUNT(*) as c FROM admins")['c'] ?? 0);

        // Temporarily disable foreign key constraints for clean truncation
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");

        // 1. Truncate certificates and participants
        $db->exec("TRUNCATE TABLE certificates");
        $db->exec("TRUNCATE TABLE participants");

        if ($mode === 'full_reset') {
            $totalCohorts = (int) (dbFetchOne("SELECT COUNT(*) as c FROM cohorts")['c'] ?? 0);
            $totalTemplates = (int) (dbFetchOne("SELECT COUNT(*) as c FROM certificate_templates")['c'] ?? 0);

            // Truncate certificate templates and cohorts
            $db->exec("TRUNCATE TABLE certificate_templates");
            $db->exec("TRUNCATE TABLE cohorts");

            $result['cohorts_deleted'] = $totalCohorts;
            $result['templates_deleted'] = $totalTemplates;

            // Seed 1 fresh, clean starter Workshop / Cohort
            $today = date('Y-m-d');
            $nextWeek = date('Y-m-d', strtotime('+7 days'));

            dbQuery("
                INSERT INTO cohorts (id, name, batch_code, start_date, end_date, instructor_name, instructor_title, issue_date, location, status)
                VALUES (1, 'Supervised Machine Learning: Regression and Classification', 'Cohort #849', ?, ?, 'Dr. Sarah Jenkins', 'Director of AI Research', ?, 'Mogadishu Tech Campus', 'active')
            ", [$today, $nextWeek, $nextWeek]);

            // Seed 1 standard clean template for the starter cohort
            dbQuery("
                INSERT INTO certificate_templates (
                    id, cohort_id, template_name, image_path,
                    name_pos_x, name_pos_y, name_font_size, name_font_family, name_font_color, name_text_align,
                    show_cert_id, cert_id_pos_x, cert_id_pos_y, cert_id_color, cert_id_font_size, show_cert_id_prefix,
                    show_issue_date, issue_date_text, issue_date_pos_x, issue_date_pos_y, issue_date_font_size, issue_date_color, issue_date_font_family,
                    show_qr, qr_pos_x, qr_pos_y, qr_size, is_active
                ) VALUES (
                    1, 1, 'Standard Official Certificate', 'uploads/templates/default_blank_template.jpg',
                    50.00, 39.50, 48, 'Playfair Display', '#000000', 'center',
                    1, 50.00, 88.00, '#64748B', 10, 0,
                    1, '12–13 September 2026', 28.00, 88.00, 10, '#1E293B', 'Inter',
                    1, 88.00, 84.00, 80, 1
                )
            ");
        }

        // Re-enable foreign key constraints
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Clear active cohort session if present
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['active_cohort_id']);
        }

        $result['message'] = 'Database data cleared successfully!';
    } catch (Exception $e) {
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        $result['status'] = 'error';
        $result['message'] = $e->getMessage();
    }

    return $result;
}

// ----------------------------------------------------
// CLI Execution Handler
// ----------------------------------------------------
if ($isCli) {
    global $argv;
    $cliMode = 'full_reset';
    if (isset($argv[1])) {
        if ($argv[1] === '--certs-only' || $argv[1] === '-c') {
            $cliMode = 'certs_only';
        } elseif ($argv[1] === '--help' || $argv[1] === '-h') {
            echo "CertificateHub Database Cleanup Tool\n";
            echo "Usage: php clear_data.php [OPTIONS]\n\n";
            echo "Options:\n";
            echo "  (no args)       Full reset (Clears all participants, certs, resets to 1 clean starter cohort)\n";
            echo "  --certs-only    Clears only participants and certificates; preserves all existing workshops\n";
            echo "  --full          Same as default full reset\n";
            exit(0);
        }
    }

    echo "======================================================\n";
    echo "  CertificateHub Database Cleanup Utility (CLI)      \n";
    echo "======================================================\n";
    echo "Mode: " . strtoupper($cliMode) . "\n";
    echo "Connecting to database: " . DB_NAME . "...\n";

    $res = performDatabaseCleanup($cliMode);

    if ($res['status'] === 'success') {
        echo "\n[SUCCESS] " . $res['message'] . "\n\n";
        echo "Summary of actions taken:\n";
        echo "  - Participants deleted:  " . $res['participants_deleted'] . "\n";
        echo "  - Certificates deleted:  " . $res['certificates_deleted'] . "\n";
        if ($cliMode === 'full_reset') {
            echo "  - Extra Cohorts cleared: " . $res['cohorts_deleted'] . " (Reset to 1 clean active cohort #1)\n";
            echo "  - Templates reset:       " . $res['templates_deleted'] . " (1 standard default template seeded)\n";
        } else {
            echo "  - Existing Workshops:   Preserved intact\n";
        }
        echo "  - Admin Accounts:        " . $res['admins_preserved'] . " (Kept safe)\n";
        echo "\nDatabase is now clean and ready!\n";
        exit(0);
    } else {
        echo "\n[ERROR] Failed to clean database: " . $res['message'] . "\n";
        exit(1);
    }
}

// ----------------------------------------------------
// Browser Web UI Handler
// ----------------------------------------------------
session_start();
$actionResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'clear_certs_only') {
        $actionResult = performDatabaseCleanup('certs_only');
    } elseif ($action === 'full_reset') {
        $actionResult = performDatabaseCleanup('full_reset');
    }
}

// Live record counts
$countParticipants = (int) (dbFetchOne("SELECT COUNT(*) as c FROM participants")['c'] ?? 0);
$countCertificates = (int) (dbFetchOne("SELECT COUNT(*) as c FROM certificates")['c'] ?? 0);
$countCohorts      = (int) (dbFetchOne("SELECT COUNT(*) as c FROM cohorts")['c'] ?? 0);
$countTemplates    = (int) (dbFetchOne("SELECT COUNT(*) as c FROM certificate_templates")['c'] ?? 0);
$countAdmins       = (int) (dbFetchOne("SELECT COUNT(*) as c FROM admins")['c'] ?? 0);

$page_title = 'Database Cleanup Tool - CertificateHub';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
            mono: ['"JetBrains Mono"', 'monospace'],
          },
          colors: {
            brand: {
              50: '#eef2ff',
              500: '#4f46e5',
              600: '#4338ca',
              700: '#3730a3',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen font-sans antialiased flex flex-col items-center justify-center p-4">

  <div class="w-full max-w-xl bg-slate-800/90 border border-slate-700/80 rounded-2xl shadow-2xl p-6 sm:p-8 backdrop-blur-sm">
    
    <!-- Header -->
    <div class="flex items-center gap-3.5 pb-6 border-b border-slate-700">
      <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-[28px]">cleaning_services</span>
      </div>
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white flex items-center gap-2">
          Database Reset Tool
          <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 font-mono">PHP Utility</span>
        </h1>
        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">Nadiifinta iyo dib-u-dejinta xogta CertificateHub</p>
      </div>
    </div>

    <!-- Alert / Toast Banner -->
    <?php if ($actionResult): ?>
      <?php if ($actionResult['status'] === 'success'): ?>
        <div class="mt-5 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-start gap-3 animate-in fade-in duration-200">
          <span class="material-symbols-outlined text-[22px] text-emerald-400 shrink-0 mt-0.5">check_circle</span>
          <div class="flex-1">
            <strong class="font-semibold block text-emerald-200">Xogta si guul leh ayaa loo nadiifiyay!</strong>
            <ul class="text-xs text-emerald-300/90 mt-1.5 space-y-0.5 list-disc list-inside">
              <li>Ardayda la tirtiray: <strong><?= $actionResult['participants_deleted'] ?></strong></li>
              <li>Shahaadooyinka la tirtiray: <strong><?= $actionResult['certificates_deleted'] ?></strong></li>
              <?php if ($actionResult['mode'] === 'full_reset'): ?>
                <li>Workshops la dib-u-dejiyay: <strong><?= $actionResult['cohorts_deleted'] ?></strong> (1 Workshop nadiif ah ayaa la dhalay)</li>
              <?php endif; ?>
              <li>Akoonka Admin-ka: <strong><?= $actionResult['admins_preserved'] ?></strong> (Waa la xafiday)</li>
            </ul>
          </div>
        </div>
      <?php else: ?>
        <div class="mt-5 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-start gap-3">
          <span class="material-symbols-outlined text-[22px] text-rose-400 shrink-0 mt-0.5">error</span>
          <div>
            <strong class="font-semibold block text-rose-200">Khalad ayaa dhacay:</strong>
            <p class="text-xs text-rose-300/90 mt-1"><?= htmlspecialchars($actionResult['message']) ?></p>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Current Database Stats -->
    <div class="mt-6">
      <div class="flex items-center justify-between text-xs text-slate-400 uppercase tracking-wider font-semibold mb-3">
        <span>Xogta Hadda Ku Jirta Database-ka</span>
        <span class="font-mono text-indigo-400"><?= htmlspecialchars(DB_NAME) ?></span>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
        <div class="bg-slate-900/60 border border-slate-700/60 rounded-xl p-3 flex flex-col">
          <span class="text-xs text-slate-400">Ardayda</span>
          <span class="text-xl font-bold font-mono text-white mt-1"><?= $countParticipants ?></span>
        </div>
        <div class="bg-slate-900/60 border border-slate-700/60 rounded-xl p-3 flex flex-col">
          <span class="text-xs text-slate-400">Shahaadooyinka</span>
          <span class="text-xl font-bold font-mono text-white mt-1"><?= $countCertificates ?></span>
        </div>
        <div class="bg-slate-900/60 border border-slate-700/60 rounded-xl p-3 flex flex-col">
          <span class="text-xs text-slate-400">Workshops</span>
          <span class="text-xl font-bold font-mono text-white mt-1"><?= $countCohorts ?></span>
        </div>
        <div class="bg-slate-900/60 border border-slate-700/60 rounded-xl p-3 flex flex-col">
          <span class="text-xs text-slate-400">Admins</span>
          <span class="text-xl font-bold font-mono text-emerald-400 mt-1"><?= $countAdmins ?></span>
        </div>
      </div>
    </div>

    <!-- Action Forms -->
    <div class="mt-8 flex flex-col gap-3.5">
      
      <!-- Option 1: Full Factory Reset -->
      <form method="POST" onsubmit="return confirmAction('FULL_RESET', 'Ma hubtaa inaad rabto inaad TIRTIRO dhammaan ardayda, shahaadooyinka, iyo workshops-ka oo dhan, kuna soo celiso nidaamka xaalad nadiif ah?');">
        <input type="hidden" name="action" value="full_reset">
        <button type="submit" class="w-full group relative flex items-center justify-between p-4 rounded-xl bg-rose-600/90 hover:bg-rose-600 text-white font-semibold transition-all shadow-lg hover:shadow-rose-600/20 active:scale-[0.99] border border-rose-500/40">
          <div class="flex items-center gap-3 text-left">
            <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-[24px]">delete_sweep</span>
            </div>
            <div>
              <div class="text-sm sm:text-base font-bold flex items-center gap-2">
                Dib-u-dejin Guud (Full Reset)
                <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-white/20">Recommended</span>
              </div>
              <p class="text-xs text-rose-100 font-normal mt-0.5">Wuxuu tirtirayaa dhammaan ardayda, shahaadooyinka, iyo tababarada; wuxuuna abuurayaa 1 Workshop nadiif ah.</p>
            </div>
          </div>
          <span class="material-symbols-outlined text-[20px] text-rose-200 group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </button>
      </form>

      <!-- Option 2: Clear Participants & Certs Only -->
      <form method="POST" onsubmit="return confirmAction('CERTS_ONLY', 'Ma hubtaa inaad rabto inaad TIRTIRO dhammaan ardayda iyo shahaadooyinka oo keliya, adigoo deynaya workshops-ka iyo templates-kooda?');">
        <input type="hidden" name="action" value="clear_certs_only">
        <button type="submit" class="w-full group relative flex items-center justify-between p-4 rounded-xl bg-slate-700/80 hover:bg-slate-700 text-slate-100 font-semibold transition-all border border-slate-600/60 active:scale-[0.99]">
          <div class="flex items-center gap-3 text-left">
            <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-amber-400 shrink-0">
              <span class="material-symbols-outlined text-[24px]">group_remove</span>
            </div>
            <div>
              <div class="text-sm sm:text-base font-bold text-white">
                Tirtir Ardayda &amp; Shahaadooyinka Keliya
              </div>
              <p class="text-xs text-slate-400 font-normal mt-0.5">Workshops-ka iyo templates-ka sida ay yihiin ayay u joogayaan, ardayda iyo shahaadooyinka uun baa eber noqonaya.</p>
            </div>
          </div>
          <span class="material-symbols-outlined text-[20px] text-slate-400 group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </button>
      </form>

    </div>

    <!-- Quick Navigation Links -->
    <div class="mt-8 pt-5 border-t border-slate-700/80 flex flex-wrap items-center justify-between text-xs text-slate-400 gap-2">
      <a href="admin/organizer_dashboard.php" class="text-indigo-400 hover:text-indigo-300 transition-colors flex items-center gap-1 font-medium">
        <span class="material-symbols-outlined text-[16px]">dashboard</span>
        Ku Noqo Dashboard-ka
      </a>
      <a href="admin/cohorts_management.php" class="hover:text-slate-200 transition-colors flex items-center gap-1">
        <span class="material-symbols-outlined text-[16px]">school</span>
        Workshops &amp; Cohorts
      </a>
      <a href="admin/participants_management.php" class="hover:text-slate-200 transition-colors flex items-center gap-1">
        <span class="material-symbols-outlined text-[16px]">group</span>
        Ardayda
      </a>
    </div>

    <!-- CLI Instructions Note -->
    <div class="mt-4 p-3 rounded-lg bg-slate-900/90 border border-slate-700/50 text-[11px] font-mono text-slate-400 flex items-center justify-between">
      <span class="flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[14px] text-indigo-400">terminal</span>
        <span>CLI Command:</span>
        <code class="text-indigo-300">php clear_data.php</code>
      </span>
      <span class="text-emerald-400 flex items-center gap-1">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
        Terminal Ready
      </span>
    </div>

  </div>

  <script>
    function confirmAction(type, message) {
      return confirm(message);
    }
  </script>
</body>
</html>
