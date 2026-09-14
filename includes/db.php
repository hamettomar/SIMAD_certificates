<?php
/**
 * Database Connection & Query Helpers
 * CertificateHub - Precision Credentialing
 */

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'certificate_hub');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Auto-detect Base URL for both Localhost (e.g. http://localhost/certificate) and Live Hosting (e.g. https://domain.com)
 *
 * @return string
 */
function getAppBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Strip subdirectories like /admin/ or /public/ or /includes/ to obtain project base
    $basePath = preg_replace('#/(admin|public|includes)/.*$#i', '', $script);
    $basePath = preg_replace('#/index\.php$#i', '', $basePath);
    return rtrim($protocol . '://' . $host . $basePath, '/');
}

/**
 * Auto-detect Base Path (e.g. '/certificate' or '')
 *
 * @return string
 */
function getAppBasePath(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = preg_replace('#/(admin|public|includes)/.*$#i', '', $script);
    $basePath = preg_replace('#/index\.php$#i', '', $basePath);
    return rtrim($basePath, '/');
}

/**
 * Returns a shared PDO instance (Singleton pattern)
 * 
 * @return PDO
 * @throws PDOException
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error internally and show friendly response
            error_log('Database Connection Failed: ' . $e->getMessage());
            throw new Exception('Database connection failed. Please ensure MySQL is running in XAMPP.');
        }
    }

    return $pdo;
}

/**
 * Prepares and executes a SQL statement
 * 
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 */
function dbQuery(string $sql, array $params = []): PDOStatement {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetches all matching rows from a query
 * 
 * @param string $sql
 * @param array $params
 * @return array
 */
function dbFetchAll(string $sql, array $params = []): array {
    return dbQuery($sql, $params)->fetchAll();
}

/**
 * Fetches a single row from a query
 * 
 * @param string $sql
 * @param array $params
 * @return array|null
 */
function dbFetchOne(string $sql, array $params = []): ?array {
    $row = dbQuery($sql, $params)->fetch();
    return $row ?: null;
}

/**
 * Returns the ID of the last inserted row
 * 
 * @return string
 */
function dbLastInsertId(): string {
    return getDB()->lastInsertId();
}

/**
 * Returns the currently active cohort.
 * Respects $_GET['cohort_id'], $_SESSION['active_cohort_id'], database status = 'active', or latest cohort.
 *
 * @return array
 */
function getActiveCohort(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    try {
        // 1. Explicit query parameter switch
        if (!empty($_GET['cohort_id'])) {
            $cId = (int)$_GET['cohort_id'];
            $cohort = dbFetchOne("SELECT * FROM cohorts WHERE id = ?", [$cId]);
            if ($cohort) {
                $_SESSION['active_cohort_id'] = (int)$cohort['id'];
                return $cohort;
            }
        }

        // 2. Active cohort in session
        if (!empty($_SESSION['active_cohort_id'])) {
            $cId = (int)$_SESSION['active_cohort_id'];
            $cohort = dbFetchOne("SELECT * FROM cohorts WHERE id = ?", [$cId]);
            if ($cohort) {
                return $cohort;
            }
        }

        // 3. Database status = 'active'
        $cohort = dbFetchOne("SELECT * FROM cohorts WHERE status = 'active' ORDER BY id DESC LIMIT 1");
        if ($cohort) {
            $_SESSION['active_cohort_id'] = (int)$cohort['id'];
            return $cohort;
        }

        // 4. Most recent cohort in database
        $cohort = dbFetchOne("SELECT * FROM cohorts ORDER BY id DESC LIMIT 1");
        if ($cohort) {
            $_SESSION['active_cohort_id'] = (int)$cohort['id'];
            return $cohort;
        }
    } catch (Exception $e) {
        error_log("getActiveCohort error: " . $e->getMessage());
    }

    // 5. Default fallback
    return [
        'id' => 1,
        'name' => 'Supervised Machine Learning: Regression and Classification',
        'batch_code' => 'Cohort #849',
        'instructor_name' => 'Dr. Sarah Jenkins',
        'instructor_title' => 'Director of AI Research',
        'issue_date' => date('Y-m-d'),
        'location' => 'Online',
        'status' => 'active'
    ];
}

/**
 * Sets a specific cohort as active in the database and session
 *
 * @param int $cohortId
 * @return bool
 */
function setActiveCohort(int $cohortId): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    try {
        $cohort = dbFetchOne("SELECT * FROM cohorts WHERE id = ?", [$cohortId]);
        if (!$cohort) {
            return false;
        }
        $_SESSION['active_cohort_id'] = $cohortId;
        return true;
    } catch (Exception $e) {
        error_log("setActiveCohort error: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculates dynamic workshop status based on start_date, end_date, and current date.
 * Allows multiple concurrent active workshops automatically.
 *
 * @param array $cohort
 * @return string ('upcoming'|'active'|'completed'|'archived')
 */
function getCohortComputedStatus(array $cohort): string {
    if (($cohort['status'] ?? '') === 'archived') {
        return 'archived';
    }

    $today = date('Y-m-d');
    $startDate = !empty($cohort['start_date']) ? $cohort['start_date'] : ($cohort['issue_date'] ?? $today);
    $endDate = !empty($cohort['end_date']) ? $cohort['end_date'] : ($cohort['issue_date'] ?? $startDate);

    if ($today < $startDate) {
        return 'upcoming';
    } elseif ($today >= $startDate && $today <= $endDate) {
        return 'active';
    } else {
        return 'completed';
    }
}

/**
 * Fetches all cohorts ordered by ID descending
 *
 * @return array
 */
function getAllCohorts(): array {
    return dbFetchAll("SELECT * FROM cohorts ORDER BY id DESC");
}

/**
 * Generates the next sequential certificate token in the format SIMAD-PU-2026-001, 002, 003...
 *
 * @param int|null $cohortId
 * @param string $prefix
 * @return string
 */
function generateNextCertificateToken(?int $cohortId = null, string $prefix = 'SIMAD-PU-2026-'): string {
    try {
        $tokens = dbFetchAll("SELECT certificate_token FROM certificates WHERE certificate_token LIKE ?", [$prefix . '%']);
        $maxNum = 0;
        $regex = '/^' . preg_quote($prefix, '/') . '(\d+)$/i';
        foreach ($tokens as $row) {
            $tok = $row['certificate_token'] ?? '';
            if (preg_match($regex, $tok, $matches)) {
                $num = (int)$matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        $nextNum = $maxNum + 1;
        $padded = str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
        return $prefix . $padded;
    } catch (Exception $e) {
        error_log("generateNextCertificateToken error: " . $e->getMessage());
        return $prefix . str_pad((string)time() % 1000, 3, '0', STR_PAD_LEFT);
    }
}
