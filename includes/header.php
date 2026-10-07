<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Semantic HTML5 Header & Responsive Sticky Navigation
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

$user = current_user();
$current_page = basename($_SERVER['PHP_SELF']);
$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' . APP_NAME : APP_NAME; ?></title>
  <meta name="description" content="Organ Donation – Recipient Matching Portal: Preliminary matching platform connecting hope with life-saving possibilities.">
  
  <!-- Google Fonts: Inter & Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Main Stylesheet -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
  
  <?php if (isset($include_dashboard_css) && $include_dashboard_css): ?>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/dashboard.css">
  <?php endif; ?>

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL; ?>/assets/images/logo.svg">
</head>
<body>
  <!-- Accessibility Skip Navigation -->
  <a href="#main-content" class="skip-to-content">Skip to main content</a>

  <!-- Global Flash Alerts Notification Toast -->
  <?php if ($flash): ?>
  <div class="flash-message-container" role="alert" aria-live="assertive">
    <div class="flash-alert <?php echo htmlspecialchars($flash['type']); ?>">
      <span><?php echo htmlspecialchars($flash['message']); ?></span>
      <button type="button" class="flash-close" aria-label="Close alert">&times;</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Cloud Serverless / Database Mode Banner -->
  <?php if (!is_db_connected()): ?>
  <div style="background: linear-gradient(90deg, #fffbeb, #fef3c7); border-bottom: 1px solid #fde68a; padding: 8px 16px; font-size: 0.85rem; color: #92400e; text-align: center; position: relative; z-index: 1000; display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
    <span>⚡ <strong>Cloud Serverless Preview:</strong> Running on Vercel. Connect a cloud MySQL database via <code>DATABASE_URL</code> in Vercel settings for production storage.</span>
  </div>
  <?php endif; ?>

  <!-- Sticky Navigation Bar -->
  <header>
    <nav class="navbar" aria-label="Primary Navigation">
      <div class="container nav-container">
        <!-- Brand Logo & Name -->
        <a href="<?php echo BASE_URL; ?>/index.php" class="nav-brand" aria-label="LifeBridge Portal Home">
          <img src="<?php echo BASE_URL; ?>/assets/images/logo.svg" alt="LifeBridge Logo" width="42" height="42">
          <div class="brand-text-wrapper">
            <span class="brand-title">Life<span class="gradient-text-teal">Bridge</span></span>
            <span class="brand-sub">Organ Match Engine</span>
          </div>
        </a>

        <!-- Mobile Quick Status or Sign In Button + Hamburger -->
        <div class="mobile-nav-controls">
          <?php if (!$user): ?>
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary btn-sm mobile-quick-login">Sign In</a>
          <?php else: ?>
            <a href="<?php echo ($user['role'] === 'admin') ? BASE_URL . '/admin/index.php' : (($user['role'] === 'donor') ? BASE_URL . '/donor_dashboard.php' : BASE_URL . '/recipient_dashboard.php'); ?>" class="btn btn-outline-primary btn-sm mobile-quick-login">
              Portal
            </a>
          <?php endif; ?>

          <button class="nav-toggle" aria-label="Toggle navigation menu" aria-expanded="false" id="navToggleBtn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
        </div>

        <!-- Navigation Menu (Desktop links & Mobile Slide-down Drawer) -->
        <div class="nav-menu-wrapper" id="navMenuWrapper">
          <ul class="nav-menu">
            <li><a href="<?php echo BASE_URL; ?>/index.php" class="nav-link <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">Home</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php#simulator" class="nav-link">Simulator</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php#how-it-works" class="nav-link">How It Works</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php#awareness" class="nav-link">Organs &amp; Facts</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php#sdgs" class="nav-link">SDGs</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php#contact" class="nav-link">Contact</a></li>

            <?php if ($user): ?>
              <?php if ($user['role'] === 'admin'): ?>
                <li><a href="<?php echo BASE_URL; ?>/admin/index.php" class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'active' : ''; ?>">Admin Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>/admin/matches.php" class="nav-link">Matches</a></li>
              <?php elseif ($user['role'] === 'donor'): ?>
                <li><a href="<?php echo BASE_URL; ?>/donor_dashboard.php" class="nav-link <?php echo ($current_page === 'donor_dashboard.php') ? 'active' : ''; ?>">My Donor Portal</a></li>
              <?php elseif ($user['role'] === 'recipient'): ?>
                <li><a href="<?php echo BASE_URL; ?>/recipient_dashboard.php" class="nav-link <?php echo ($current_page === 'recipient_dashboard.php') ? 'active' : ''; ?>">My Recipient Portal</a></li>
              <?php endif; ?>
            <?php endif; ?>
          </ul>

          <!-- Mobile Action Drawer Buttons (Visible inside mobile menu) -->
          <div class="nav-mobile-actions">
            <?php if ($user): ?>
              <div class="mobile-user-greeting">
                Signed in as <strong><?php echo htmlspecialchars($user['name']); ?></strong> (<?php echo ucfirst($user['role']); ?>)
              </div>
              <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-outline btn-block">Sign Out</a>
            <?php else: ?>
              <a href="<?php echo BASE_URL; ?>/donor_register.php" class="btn btn-rose btn-block">❤️ Register as Donor</a>
              <a href="<?php echo BASE_URL; ?>/recipient_register.php" class="btn btn-secondary btn-block">🔍 Find a Match</a>
              <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline btn-block">Sign In to Account</a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Desktop Navigation Actions -->
        <div class="nav-actions desktop-only">
          <?php if ($user): ?>
            <span class="user-greeting">Hello, <strong><?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?></strong></span>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-outline btn-sm">Sign Out</a>
          <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/donor_register.php" class="btn btn-rose btn-sm" title="Register an organ pledge">❤️ Register Donor</a>
            <a href="<?php echo BASE_URL; ?>/recipient_register.php" class="btn btn-secondary btn-sm" title="Request an organ match">Find Match</a>
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline btn-sm">Sign In</a>
          <?php endif; ?>
        </div>

      </div>
    </nav>
  </header>

  <!-- Main Content Wrapper Starts -->
  <main id="main-content" class="main-content">
