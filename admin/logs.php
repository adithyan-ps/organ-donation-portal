<?php
/**
 * Organ Donation – Recipient Matching Portal
 * System Audit & Action Logs (Search, CSV Export & Pruning)
 */

$page_title = "System Audit Logs";
$include_dashboard_css = true;
$include_dashboard_js = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();
$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$action_filter = trim($_GET['action_filter'] ?? '');

$query = "SELECT l.*, u.name AS admin_name, u.email AS admin_email 
          FROM admin_logs l 
          LEFT JOIN users u ON l.admin_id = u.id 
          WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (l.description LIKE ? OR l.action LIKE ? OR u.name LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}

if ($action_filter !== '') {
    $query .= " AND l.action = ?";
    $params[] = $action_filter;
}

$query .= " ORDER BY l.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct actions for dropdown
$actionsStmt = $pdo->query("SELECT DISTINCT action FROM admin_logs ORDER BY action ASC");
$distinctActions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
  
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="dashboard-main">
    
    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>System Audit Trail</h1>
        <p>Immutable log of administrative reviews, verifications, status modifications, and match evaluations.</p>
      </div>
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=logs&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline" title="Download Audit Logs CSV">
          📥 Export Logs CSV
        </a>
        <button type="button" class="btn btn-outline-danger" onclick="openModal('clearLogsModal')" title="Purge system logs">
          🗑️ Clear Logs
        </button>
      </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="filter-bar">
      <div class="filter-group">
        
        <div class="search-input-wrapper">
          <span class="search-icon">🔍</span>
          <input type="text" name="search" class="form-control" placeholder="Search log entries, administrators..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <select name="action_filter" class="form-control" style="width: auto; min-width: 180px;">
          <option value="">All Action Types</option>
          <?php foreach ($distinctActions as $act): ?>
            <option value="<?php echo htmlspecialchars($act); ?>" <?php echo ($action_filter === $act) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($act); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary btn-sm">Filter Logs</button>
        <a href="<?php echo BASE_URL; ?>/admin/logs.php" class="btn btn-outline btn-sm">Reset</a>
      </div>

      <div style="font-size: 0.85rem; color: var(--text-muted);">
        Total Log Events: <strong><?php echo count($logs); ?></strong>
      </div>
    </form>

    <!-- Table Card -->
    <div class="table-card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 80px;">Log ID</th>
              <th style="width: 180px;">Timestamp</th>
              <th style="width: 160px;">Administrator</th>
              <th style="width: 200px;">Action Category</th>
              <th>Audit Log Description</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
              <tr>
                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                  <div style="font-size: 2rem; margin-bottom: 0.5rem;">📜</div>
                  No audit log records match your current filters.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
                <tr>
                  <td><strong>#<?php echo $log['id']; ?></strong></td>
                  <td style="font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                    <?php echo date('M d, Y &bull; H:i:s', strtotime($log['created_at'])); ?>
                  </td>
                  <td>
                    <?php if ($log['admin_name']): ?>
                      <span style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($log['admin_name']); ?></span>
                    <?php else: ?>
                      <span style="color: var(--text-muted); font-style: italic;">System / Public</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 0.75rem; border: 1px solid #cbd5e1;">
                      <?php echo htmlspecialchars($log['action']); ?>
                    </span>
                  </td>
                  <td style="font-size: 0.88rem; line-height: 1.4;">
                    <?php echo htmlspecialchars($log['description']); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>

</div>

<!-- ======================================================= -->
<!-- MODAL: CLEAR AUDIT LOGS CONFIRMATION                     -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="clearLogsModal" role="dialog" aria-modal="true" aria-labelledby="clearLogsTitle">
  <div class="modal-content" style="max-width: 450px;">
    <div class="modal-header" style="border-bottom-color: #fee2e2;">
      <h3 id="clearLogsTitle" style="color: var(--danger);">🗑️ Clear System Audit Trail</h3>
      <button type="button" class="modal-close" onclick="closeModal('clearLogsModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="clear_logs">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <p style="font-size: 0.95rem; line-height: 1.5; color: var(--text-main);">
          Are you sure you want to purge all historical system audit log records?
        </p>
        <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.5rem;">
          ⚠️ A new initial entry documenting this log wipe action will be created. All previous records will be permanently removed.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('clearLogsModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, Purge Logs</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
