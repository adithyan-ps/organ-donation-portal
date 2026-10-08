<?php
/**
 * Organ Donation – Recipient Matching Portal
 * System Configuration & Global Database Connection
 */

// Error reporting for academic / development environment
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Helper function to resolve environment variables across various PHP SAPIs / Vercel runtimes
if (!function_exists('get_cfg_env')) {
    function get_cfg_env($key, $default = null) {
        if (!empty($_ENV[$key])) return trim((string)$_ENV[$key]);
        if (!empty($_SERVER[$key])) return trim((string)$_SERVER[$key]);
        $val = getenv($key);
        if ($val !== false && $val !== '') return trim((string)$val);
        return $default;
    }
}

// Robust database connection URL parser
if (!function_exists('parse_db_url')) {
    function parse_db_url($url) {
        if (empty($url)) return [];
        $url = trim($url, " \t\n\r\0\x0B\"'");

        // Standard parse_url attempt
        $parsed = @parse_url($url);
        if ($parsed && !empty($parsed['host'])) {
            return [
                'host' => $parsed['host'],
                'port' => (string)($parsed['port'] ?? 3306),
                'user' => isset($parsed['user']) ? urldecode($parsed['user']) : '',
                'pass' => isset($parsed['pass']) ? urldecode($parsed['pass']) : '',
                'name' => isset($parsed['path']) ? trim(urldecode($parsed['path']), '/') : ''
            ];
        }

        // Regex fallback for passwords containing special characters (#, ?, @, !, etc.)
        if (preg_match('~^[a-zA-Z0-9_+]+://(?P<user>[^:]+):(?P<pass>.+)@(?P<host>[^:/@?#\s]+)(?::(?P<port>\d+))?(?:/(?P<name>[^?#\s]*))?~s', $url, $matches)) {
            return [
                'host' => $matches['host'],
                'port' => (string)(!empty($matches['port']) ? $matches['port'] : 3306),
                'user' => urldecode($matches['user']),
                'pass' => urldecode($matches['pass']),
                'name' => urldecode($matches['name'] ?? '')
            ];
        }

        // PDO DSN fallback (mysql:host=...;port=...;dbname=...)
        if (preg_match('~host=(?P<host>[^;]+)~i', $url, $hMatch)) {
            preg_match('~port=(?P<port>\d+)~i', $url, $pMatch);
            preg_match('~dbname=(?P<name>[^;]+)~i', $url, $dMatch);
            preg_match('~user=(?P<user>[^;]+)~i', $url, $uMatch);
            preg_match('~password=(?P<pass>[^;]+)~i', $url, $pwMatch);
            return [
                'host' => trim($hMatch['host']),
                'port' => (string)(!empty($pMatch['port']) ? $pMatch['port'] : 3306),
                'user' => trim($uMatch['user'] ?? ''),
                'pass' => trim($pwMatch['pass'] ?? ''),
                'name' => trim($dMatch['name'] ?? '')
            ];
        }

        return [];
    }
}

// Parse connection URL if provided (e.g. DATABASE_URL or MYSQL_URL from cloud providers)
$dbUrl = get_cfg_env('DATABASE_URL') ?: get_cfg_env('MYSQL_URL');
$parsedUrl = $dbUrl ? parse_db_url($dbUrl) : [];

// Database Credentials (support environment variables for cloud deployment such as Vercel/Railway/Aiven with local fallback)
if (!defined('DB_HOST')) define('DB_HOST', $parsedUrl['host'] ?? (get_cfg_env('DB_HOST') ?: (get_cfg_env('MYSQLHOST') ?: '127.0.0.1')));
if (!defined('DB_PORT')) define('DB_PORT', (string)($parsedUrl['port'] ?? (get_cfg_env('DB_PORT') ?: (get_cfg_env('MYSQLPORT') ?: '3306'))));
if (!defined('DB_NAME')) define('DB_NAME', !empty($parsedUrl['name']) ? $parsedUrl['name'] : (get_cfg_env('DB_NAME') ?: (get_cfg_env('MYSQLDATABASE') ?: 'organ_donation_db')));
if (!defined('DB_USER')) define('DB_USER', isset($parsedUrl['user']) && $parsedUrl['user'] !== '' ? $parsedUrl['user'] : (get_cfg_env('DB_USER') ?: (get_cfg_env('MYSQLUSER') ?: 'root')));
if (!defined('DB_PASS')) define('DB_PASS', isset($parsedUrl['pass']) ? $parsedUrl['pass'] : (get_cfg_env('DB_PASS') ?: (get_cfg_env('MYSQLPASSWORD') ?: '')));

// Application Details
if (!defined('APP_NAME')) define('APP_NAME', 'Organ Donation & Matching Portal');
if (!defined('APP_SHORT_NAME')) define('APP_SHORT_NAME', 'LifeBridge Portal');
if (!defined('APP_TAGLINE')) define('APP_TAGLINE', 'Connecting Hope with Life-Saving Possibilities');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');

// Global Medical Disclaimer
if (!defined('MEDICAL_DISCLAIMER')) {
    define('MEDICAL_DISCLAIMER', 'Academic Demonstration Only: Potential matches shown by this portal are preliminary digital comparisons based on blood group, organ type, donor availability, and urgency. Final immunological compatibility testing (HLA tissue typing, cross-matching, viral screening), legal clearance, allocation rules, and surgical decisions must be executed by qualified transplant surgeons and authorized medical organizations.');
}

// Predefined Biological Compatibility Matrix (Immunohematology RBC compatibility)
$GLOBALS['BLOOD_COMPATIBILITY'] = [
    'O-'  => ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'], // Universal donor
    'O+'  => ['O+', 'A+', 'B+', 'AB+'],
    'A-'  => ['A-', 'A+', 'AB-', 'AB+'],
    'A+'  => ['A+', 'AB+'],
    'B-'  => ['B-', 'B+', 'AB-', 'AB+'],
    'B+'  => ['B+', 'AB+'],
    'AB-' => ['AB-', 'AB+'],
    'AB+' => ['AB+']                                             // Universal recipient only
];

// Supported Organs and Tissues
$GLOBALS['SUPPORTED_ORGANS'] = [
    'Kidney',
    'Liver',
    'Heart',
    'Lung',
    'Pancreas',
    'Cornea',
    'Intestine',
    'Tissue'
];

// Blood Groups
$GLOBALS['BLOOD_GROUPS'] = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Urgency Levels & Matching Weight Points
$GLOBALS['URGENCY_LEVELS'] = [
    'Critical' => ['label' => 'Critical (Immediate Priority)', 'badge' => 'badge-critical', 'points' => 20],
    'High'     => ['label' => 'High Priority',               'badge' => 'badge-high',     'points' => 15],
    'Medium'   => ['label' => 'Medium Priority',             'badge' => 'badge-medium',   'points' => 10],
    'Low'      => ['label' => 'Standard / Stable',           'badge' => 'badge-low',      'points' => 5]
];

// Match Statuses
$GLOBALS['MATCH_STATUSES'] = [
    'Potential'    => ['label' => 'Potential Match',    'badge' => 'badge-potential'],
    'Under Review' => ['label' => 'Under Review',       'badge' => 'badge-review'],
    'Contacted'    => ['label' => 'Contacted',          'badge' => 'badge-contacted'],
    'Approved'     => ['label' => 'Approved Match',     'badge' => 'badge-approved'],
    'Closed'       => ['label' => 'Closed / Ineligible','badge' => 'badge-closed']
];

/**
 * Compute Dynamic Base URL for seamless portability across XAMPP/WAMP or built-in server
 */
function get_base_url() {
    if (getenv('BASE_URL')) {
        return rtrim(getenv('BASE_URL'), '/');
    }
    if (getenv('APP_URL')) {
        return rtrim(getenv('APP_URL'), '/');
    }
    if (isset($_SERVER['HTTP_HOST'])) {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        // If on Vercel or cloud domain, it always runs from domain root
        if (getenv('VERCEL') || strpos($host, 'vercel.app') !== false) {
            return $scheme . '://' . $host;
        }

        // Normalize paths for local XAMPP subdirectories
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $currentDir = str_replace('\\', '/', dirname(realpath(__FILE__)));
        
        $subDir = '';
        if (!empty($docRoot) && strpos($currentDir, $docRoot) === 0) {
            $subDir = substr($currentDir, strlen($docRoot));
        }
        $subDir = trim($subDir, '/');
        
        return $scheme . '://' . $host . ($subDir ? '/' . $subDir : '');
    }
    if (getenv('VERCEL_URL')) {
        return 'https://' . rtrim(getenv('VERCEL_URL'), '/');
    }
    return 'http://localhost/organ_donation_portal';
}

if (!defined('BASE_URL')) {
    define('BASE_URL', get_base_url());
}

/**
 * Helper function to retrieve blood compatibility array
 */
function get_blood_compatibility() {
    return $GLOBALS['BLOOD_COMPATIBILITY'];
}

/**
 * Check whether database is accessible
 */
function is_db_connected() {
    return get_db_connection(false) !== null;
}

/**
 * Get or create PDO database connection with auto-initialization fallback
 */
function get_db_connection($throwOnError = false) {
    static $pdo = null;
    static $attempted = false;

    if ($pdo !== null) {
        return $pdo;
    }

    if ($attempted) {
        if ($throwOnError) {
            throw new Exception("Database is currently unreachable.");
        }
        return null;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    // For cloud MySQL (remote host), allow SSL without strict local CA bundle verification
    if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Auto-initialize schema if cloud database tables do not exist yet
        static $schemaChecked = false;
        if (!$schemaChecked) {
            $schemaChecked = true;
            try {
                $checkTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
                if (!$checkTable) {
                    $sqlFile = __DIR__ . '/database.sql';
                    if (file_exists($sqlFile)) {
                        $sql = file_get_contents($sqlFile);
                        // Strip CREATE DATABASE and USE statements so it imports cleanly into existing cloud db
                        $sql = preg_replace('/CREATE\s+DATABASE[^;]+;/i', '', $sql);
                        $sql = preg_replace('/USE\s+`?[^;`]+`?;/i', '', $sql);
                        $pdo->exec($sql);
                    }
                }
            } catch (Exception $schemaErr) {
                error_log("Schema auto-initialization notice: " . $schemaErr->getMessage());
            }
        }

        return $pdo;
    } catch (PDOException $e) {
        $attempted = true;
        // If database doesn't exist yet, attempt automatic creation
        if ($e->getCode() == 1049 && (DB_HOST === '127.0.0.1' || DB_HOST === 'localhost')) {
            try {
                $serverDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
                $serverPdo = new PDO($serverDsn, DB_USER, DB_PASS, $options);
                $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                
                // Re-connect to newly created database
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

                // If database.sql exists, automatically import initial schema and seeds
                $sqlFile = __DIR__ . '/database.sql';
                if (file_exists($sqlFile)) {
                    $sqlContent = file_get_contents($sqlFile);
                    $pdo->exec($sqlContent);
                }
                return $pdo;
            } catch (Exception $initErr) {
                error_log("Database Auto-Creation Error: " . $initErr->getMessage());
            }
        }
        error_log("Database Connection Warning: Host=[" . DB_HOST . ":" . DB_PORT . "] DB=[" . DB_NAME . "] EnvSet=" . (!empty($dbUrl) ? 'YES' : 'NO') . " PDOError: " . $e->getMessage());

        if ($throwOnError) {
            die("<div style='font-family:sans-serif;padding:30px;max-width:640px;margin:50px auto;border:1px solid #fed7aa;background:#fffbeb;border-radius:12px;color:#9a3412;'>
                <h3 style='margin-top:0'>Database Notice</h3>
                <p>Unable to connect to MySQL database (" . htmlspecialchars(DB_HOST) . ":" . htmlspecialchars(DB_PORT) . ").</p>
                <p style='font-size:0.9rem;color:#78350f'>To enable database functionality on Vercel, please connect your cloud MySQL provider (e.g. Railway, Aiven, Supabase) via <code>DATABASE_URL</code> in Vercel Project Settings.</p>
                <p style='margin-bottom:0'><a href='/' style='color:#0284c7;font-weight:600;text-decoration:none;'>&larr; Return to Home</a></p>
            </div>");
        }
        return null;
    }
}
