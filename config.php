<?php
/**
 * Organ Donation – Recipient Matching Portal
 * System Configuration & Global Database Connection
 */

// Error reporting for academic / development environment
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Parse connection URL if provided (e.g. DATABASE_URL or MYSQL_URL from cloud providers)
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
$parsedUrl = $dbUrl ? parse_url($dbUrl) : [];

// Database Credentials (support environment variables for cloud deployment such as Vercel/Railway/Aiven with local fallback)
if (!defined('DB_HOST')) define('DB_HOST', $parsedUrl['host'] ?? (getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1')));
if (!defined('DB_PORT')) define('DB_PORT', (string)($parsedUrl['port'] ?? (getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '3306'))));
if (!defined('DB_NAME')) define('DB_NAME', isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : (getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'organ_donation_db')));
if (!defined('DB_USER')) define('DB_USER', $parsedUrl['user'] ?? (getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root')));
if (!defined('DB_PASS')) define('DB_PASS', $parsedUrl['pass'] ?? (getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: '')));

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
    if (getenv('VERCEL_URL')) {
        return 'https://' . rtrim(getenv('VERCEL_URL'), '/');
    }
    if (isset($_SERVER['HTTP_HOST'])) {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        // Normalize paths
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $currentDir = str_replace('\\', '/', dirname(realpath(__FILE__)));
        
        $subDir = '';
        if (!empty($docRoot) && strpos($currentDir, $docRoot) === 0) {
            $subDir = substr($currentDir, strlen($docRoot));
        }
        $subDir = trim($subDir, '/');
        
        return $scheme . '://' . $host . ($subDir ? '/' . $subDir : '');
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
 * Get or create PDO database connection with auto-initialization fallback
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // If database doesn't exist yet, attempt automatic creation
        if ($e->getCode() == 1049) {
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
                die("Database Auto-Creation Error: " . htmlspecialchars($initErr->getMessage()));
            }
        }
        die("Database Connection Error: " . htmlspecialchars($e->getMessage()) . "<br><br>Please make sure MySQL is running in XAMPP/WAMP.");
    }
}
