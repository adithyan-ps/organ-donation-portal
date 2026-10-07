<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Reusable Admin Sidebar Component
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/matching_engine.php';

$current_admin_page = basename($_SERVER['PHP_SELF']);
$admin_stats = get_portal_summary_statistics();
?>

<aside class="dashboard-sidebar" aria-label="Administrator Sidebar">
  <div class="sidebar-brand">
    <img src="<?php echo BASE_URL; ?>/assets/images/logo.svg" alt="Admin Logo" width="32" height="32">
    <div>
      <h4>Admin Console</h4>
      <span style="font-size: 0.75rem; color: var(--secondary); display: block; font-weight: 600;">Coordinator Desk</span>
    </div>
  </div>

  <button type="button" class="sidebar-toggle-btn" id="adminSidebarToggle" aria-expanded="false">
    <span>☰ Navigation Menu</span>
  </button>

  <ul class="sidebar-menu" id="adminSidebarMenu">
    <li class="sidebar-item">
      <a href="<?php echo BASE_URL; ?>/admin/index.php" class="sidebar-link <?php echo ($current_admin_page === 'index.php') ? 'active' : ''; ?>">
        <span class="sidebar-icon">📊</span>
        <span>Overview</span>
      </a>
    </li>

    <li class="sidebar-item">
      <a href="<?php echo BASE_URL; ?>/admin/donors.php" class="sidebar-link <?php echo ($current_admin_page === 'donors.php') ? 'active' : ''; ?>">
        <span class="sidebar-icon">❤️</span>
        <span>Review Donors</span>
        <?php if ($admin_stats['pending_donors'] > 0): ?>
          <span class="sidebar-count" style="background: var(--warning-light); color: #92400e;" title="<?php echo $admin_stats['pending_donors']; ?> pending verification">
            <?php echo $admin_stats['pending_donors']; ?>
          </span>
        <?php endif; ?>
      </a>
    </li>

    <li class="sidebar-item">
      <a href="<?php echo BASE_URL; ?>/admin/recipients.php" class="sidebar-link <?php echo ($current_admin_page === 'recipients.php') ? 'active' : ''; ?>">
        <span class="sidebar-icon">📋</span>
        <span>Review Recipients</span>
        <?php if ($admin_stats['pending_recipients'] > 0): ?>
          <span class="sidebar-count" style="background: var(--warning-light); color: #92400e;" title="<?php echo $admin_stats['pending_recipients']; ?> pending verification">
            <?php echo $admin_stats['pending_recipients']; ?>
          </span>
        <?php endif; ?>
      </a>
    </li>

    <li class="sidebar-item">
      <a href="<?php echo BASE_URL; ?>/admin/matches.php" class="sidebar-link <?php echo ($current_admin_page === 'matches.php') ? 'active' : ''; ?>">
        <span class="sidebar-icon">⚡</span>
        <span>Potential Matches</span>
        <span class="sidebar-count" style="background: var(--primary-light); color: var(--primary);" title="Active match pairs">
          <?php echo $admin_stats['total_matches']; ?>
        </span>
      </a>
    </li>

    <li class="sidebar-item">
      <a href="<?php echo BASE_URL; ?>/admin/logs.php" class="sidebar-link <?php echo ($current_admin_page === 'logs.php') ? 'active' : ''; ?>">
        <span class="sidebar-icon">📝</span>
        <span>System Audit Logs</span>
      </a>
    </li>

    <li class="sidebar-item">
      <a href="<?php echo BASE_URL; ?>/admin/users.php" class="sidebar-link <?php echo ($current_admin_page === 'users.php') ? 'active' : ''; ?>">
        <span class="sidebar-icon">👥</span>
        <span>User Accounts</span>
      </a>
    </li>

    <li class="sidebar-item">
      <a href="<?php echo BASE_URL; ?>/admin/system.php" class="sidebar-link <?php echo ($current_admin_page === 'system.php') ? 'active' : ''; ?>">
        <span class="sidebar-icon">⚙️</span>
        <span>System &amp; Backup</span>
      </a>
    </li>

    <li style="margin-top: 1rem; padding: 0 0.5rem; border-top: 1px solid var(--border);"></li>

    <li class="sidebar-item">
      <a href="<?php echo BASE_URL; ?>/index.php" class="sidebar-link" target="_blank">
        <span class="sidebar-icon">🌐</span>
        <span>View Public Portal</span>
      </a>
    </li>

    <li class="sidebar-item">
      <a href="<?php echo BASE_URL; ?>/logout.php" class="sidebar-link" style="color: var(--danger);">
        <span class="sidebar-icon">🚪</span>
        <span>Sign Out</span>
      </a>
    </li>
  </ul>

  <div class="sidebar-footer">
    <div style="font-weight: 600; color: var(--text-main); font-size: 0.85rem;">System Mode:</div>
    <div style="font-size: 0.78rem; color: var(--text-muted);">Immunohematology v1.0</div>
    <div style="font-size: 0.75rem; color: var(--secondary); margin-top: 0.25rem;">Academic Demo Ready</div>
  </div>
</aside>
