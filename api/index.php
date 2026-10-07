<?php
/**
 * Vercel Serverless Entrypoint / Router for Organ Donation Portal
 * Routes web traffic to the appropriate PHP controllers while preserving
 * native directory traversal and session state.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = trim($uri, '/');

// Handle static assets if caught by router
if (strpos($uri, 'assets/') === 0) {
    $assetPath = __DIR__ . '/../' . $uri;
    if (file_exists($assetPath) && is_file($assetPath)) {
        $ext = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'svg'   => 'image/svg+xml',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2'
        ];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        header('Cache-Control: public, max-age=86400');
        readfile($assetPath);
        exit;
    }
}

// Clean mapping for canonical routes
$routeMap = [
    ''                        => 'index.php',
    'index.php'               => 'index.php',
    'login'                   => 'login.php',
    'login.php'               => 'login.php',
    'logout'                  => 'logout.php',
    'logout.php'              => 'logout.php',
    'donor_register'          => 'donor_register.php',
    'donor_register.php'      => 'donor_register.php',
    'recipient_register'      => 'recipient_register.php',
    'recipient_register.php'  => 'recipient_register.php',
    'donor_dashboard'         => 'donor_dashboard.php',
    'donor_dashboard.php'     => 'donor_dashboard.php',
    'recipient_dashboard'     => 'recipient_dashboard.php',
    'recipient_dashboard.php' => 'recipient_dashboard.php',
    'dossier'                 => 'dossier.php',
    'dossier.php'             => 'dossier.php',
    'admin'                   => 'admin/index.php',
    'admin/'                  => 'admin/index.php',
    'admin/index.php'         => 'admin/index.php',
    'admin/donors.php'        => 'admin/donors.php',
    'admin/recipients.php'    => 'admin/recipients.php',
    'admin/matches.php'       => 'admin/matches.php',
    'admin/match_dossier.php' => 'admin/match_dossier.php',
    'admin/donor_card.php'    => 'admin/donor_card.php',
    'admin/logs.php'          => 'admin/logs.php',
    'admin/users.php'         => 'admin/users.php',
    'admin/system.php'        => 'admin/system.php',
    'admin/update_status.php' => 'admin/update_status.php'
];

$target = $routeMap[$uri] ?? null;

if (!$target) {
    $candidate = __DIR__ . '/../' . $uri;
    if (file_exists($candidate) && is_file($candidate)) {
        $target = $uri;
    } elseif (file_exists($candidate . '.php') && is_file($candidate . '.php')) {
        $target = $uri . '.php';
    }
}

if ($target && file_exists(__DIR__ . '/../' . $target)) {
    // Set working directory to project root so includes resolve properly
    chdir(__DIR__ . '/..');
    require __DIR__ . '/../' . $target;
} else {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Page Not Found</h1><p>The requested URL /' . htmlspecialchars($uri) . ' was not found.</p><p><a href="/">Return to Home</a></p></body></html>';
}
