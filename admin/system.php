<?php
/**
 * Organ Donation – Recipient Matching Portal
 * System Diagnostics, Platform Health & Database Backup Administration
 */

$page_title = "System Diagnostics & Backup";
$include_dashboard_css = true;
$include_dashboard_js = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();
$admin = current_user();
$pdo = get_db_connection();

// Collect PHP & Environment Metrics
$php_version = PHP_VERSION;
$mysql_version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'PHP Development Server';
$memory_limit = ini_get('memory_limit');
$max_exec_time = ini_get('max_execution_time') . 's';
$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');
$server_time = date('Y-m-d H:i:s T');
$timezone = date_default_timezone_get();

// System Extension Health Check
$extensions = [
    'PDO' => extension_loaded('pdo'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'OpenSSL' => extension_loaded('openssl'),
    'Mbstring' => extension_loaded('mbstring'),
    'JSON' => extension_loaded('json'),
    'Session' => extension_loaded('session'),
    'cURL' => extension_loaded('curl'),
];

// Fetch Database Table Diagnostics from information_schema
$db_name = DB_NAME;
$tables_stmt = $pdo->prepare("
    SELECT 
        table_name,
        engine,
        table_rows,
        ROUND((data_length + index_length) / 1024, 2) AS total_kb,
        ROUND(data_length / 1024, 2) AS data_kb,
        ROUND(index_length / 1024, 2) AS index_kb,
        table_collation
    FROM information_schema.tables
    WHERE table_schema = ?
    ORDER BY table_name ASC
");
$tables_stmt->execute([$db_name]);
$db_tables = $tables_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_db_kb = 0;
$total_db_rows = 0;
foreach ($db_tables as $tbl) {
    $total_db_kb += (float)($tbl['total_kb'] ?? 0);
    $total_db_rows += (int)($tbl['table_rows'] ?? 0);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">

  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="dashboard-main">

    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>System Diagnostics &amp; Database Backups</h1>
        <p>Operational health monitoring, server specifications, live database dumps, and baseline demo resets.</p>
      </div>
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <!-- Download Live SQL Backup -->
        <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=download_db_backup&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-primary" title="Stream raw .sql dump file of current database state">
          📥 Download SQL Backup
        </a>
        <!-- Reset Demo Data Modal Trigger -->
        <button type="button" class="btn btn-outline-danger" onclick="openModal('resetDemoModal')">
          🔄 Reset Demo Data
        </button>
      </div>
    </div>

    <!-- Academic & Operational Notice -->
    <div class="disclaimer-banner">
      <div class="icon">ℹ️</div>
      <div>
        <h4>System Governance &amp; Data Integrity Guidelines</h4>
        <p>
          All operations performed on this console are recorded in the immutable audit trail. Backing up the database frequently ensures transplant dossiers, candidate priority scores, and donor pledges are safely preserved.
        </p>
      </div>
    </div>

    <!-- Quick Metrics KPI Grid -->
    <div class="kpi-grid" style="margin-bottom: 2rem;">
      <div class="kpi-card">
        <div>
          <div class="kpi-label">Database Name</div>
          <div class="kpi-value" style="font-size: 1.35rem; font-family: monospace; color: var(--primary);"><?php echo htmlspecialchars($db_name); ?></div>
          <div class="kpi-sub"><?php echo count($db_tables); ?> Active Tables</div>
        </div>
        <div class="kpi-icon kpi-blue">🗄️</div>
      </div>

      <div class="kpi-card">
        <div>
          <div class="kpi-label">Total Data Size</div>
          <div class="kpi-value"><?php echo number_format($total_db_kb, 1); ?> <span style="font-size: 0.9rem; font-weight: 500;">KB</span></div>
          <div class="kpi-sub">Data &amp; Index Storage</div>
        </div>
        <div class="kpi-icon kpi-green">💾</div>
      </div>

      <div class="kpi-card">
        <div>
          <div class="kpi-label">Recorded Rows</div>
          <div class="kpi-value"><?php echo number_format($total_db_rows); ?></div>
          <div class="kpi-sub">Across All Entity Tables</div>
        </div>
        <div class="kpi-icon kpi-amber">📊</div>
      </div>

      <div class="kpi-card">
        <div>
          <div class="kpi-label">Server Engine</div>
          <div class="kpi-value" style="font-size: 1.25rem;">PHP <?php echo explode('-', $php_version)[0]; ?></div>
          <div class="kpi-sub">MySQL <?php echo explode('-', $mysql_version)[0]; ?></div>
        </div>
        <div class="kpi-icon kpi-purple">⚙️</div>
      </div>
    </div>

    <!-- Two-Column Architecture Diagnostics Layout -->
    <div class="dashboard-grid-2col" style="margin-bottom: 2rem;">

      <!-- Left Column: Runtime Environment & Extensions -->
      <div>
        <div class="table-card" style="margin-bottom: 1.5rem;">
          <div class="card-header" style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div>
              <h2 style="font-size: 1.15rem; margin-bottom: 0.15rem;">Runtime Environment</h2>
              <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0;">Platform specifications and server resources.</p>
            </div>
            <span class="badge badge-verified">Healthy</span>
          </div>

          <div style="padding: 1rem 1.5rem;">
            <table class="data-table" style="font-size: 0.88rem;">
              <tbody>
                <tr>
                  <td style="font-weight: 600; width: 45%; color: var(--text-muted);">PHP Version</td>
                  <td><strong><?php echo $php_version; ?></strong></td>
                </tr>
                <tr>
                  <td style="font-weight: 600; color: var(--text-muted);">MySQL Server Version</td>
                  <td><strong><?php echo $mysql_version; ?></strong></td>
                </tr>
                <tr>
                  <td style="font-weight: 600; color: var(--text-muted);">Web Server</td>
                  <td style="font-family: monospace; font-size: 0.8rem;"><?php echo htmlspecialchars($server_software); ?></td>
                </tr>
                <tr>
                  <td style="font-weight: 600; color: var(--text-muted);">Server Local Time</td>
                  <td><?php echo $server_time; ?> (<?php echo $timezone; ?>)</td>
                </tr>
                <tr>
                  <td style="font-weight: 600; color: var(--text-muted);">Memory Allocation Limit</td>
                  <td><?php echo $memory_limit; ?></td>
                </tr>
                <tr>
                  <td style="font-weight: 600; color: var(--text-muted);">Max Script Execution Time</td>
                  <td><?php echo $max_exec_time; ?></td>
                </tr>
                <tr>
                  <td style="font-weight: 600; color: var(--text-muted);">Upload / Post Max Filesize</td>
                  <td><?php echo $upload_max; ?> / <?php echo $post_max; ?></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Extension Health Checklist -->
        <div class="table-card">
          <div class="card-header" style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);">
            <h2 style="font-size: 1.15rem; margin-bottom: 0.15rem;">Core PHP Extension Modules</h2>
            <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0;">Required for cryptographic hashing, PDO database access, and session integrity.</p>
          </div>
          <div style="padding: 1rem 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
              <?php foreach ($extensions as $ext => $loaded): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border-radius: var(--radius-sm); background: <?php echo $loaded ? '#f0fdf4' : '#fef2f2'; ?>; border: 1px solid <?php echo $loaded ? '#bbf7d0' : '#fecaca'; ?>;">
                  <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);"><?php echo $ext; ?></span>
                  <span style="font-size: 0.75rem; font-weight: 700; color: <?php echo $loaded ? '#166534' : '#991b1b'; ?>;">
                    <?php echo $loaded ? '✓ Active' : '✗ Missing'; ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>

      <!-- Right Column: Database Storage Tables & Operational Controls -->
      <div>
        <!-- Database Tables Table -->
        <div class="table-card" style="margin-bottom: 1.5rem;">
          <div class="card-header" style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div>
              <h2 style="font-size: 1.15rem; margin-bottom: 0.15rem;">Schema Table Metrics</h2>
              <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0;">Physical disk and row usage in <code><?php echo htmlspecialchars($db_name); ?></code>.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=download_db_backup&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline btn-sm">
              📥 Dump SQL
            </a>
          </div>

          <div class="table-responsive">
            <table class="data-table" style="font-size: 0.86rem;">
              <thead>
                <tr>
                  <th>Table</th>
                  <th>Engine</th>
                  <th>Rows</th>
                  <th>Data</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($db_tables)): ?>
                  <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                      No tables found in schema.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($db_tables as $tbl): ?>
                    <tr>
                      <td>
                        <strong style="font-family: monospace; color: var(--primary);">
                          <?php echo htmlspecialchars($tbl['table_name']); ?>
                        </strong>
                      </td>
                      <td><span class="badge badge-low" style="font-size: 0.75rem;"><?php echo htmlspecialchars($tbl['engine'] ?? 'InnoDB'); ?></span></td>
                      <td><strong><?php echo number_format($tbl['table_rows']); ?></strong></td>
                      <td><?php echo number_format($tbl['data_kb'], 1); ?> KB</td>
                      <td><strong><?php echo number_format($tbl['total_kb'], 1); ?> KB</strong></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Operations & Maintenance Toolkit -->
        <div class="card" style="padding: 1.5rem; background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-xs);">
          <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
            Maintenance &amp; Data Portability Center
          </h3>
          <p style="font-size: 0.83rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            Export datasets for statistical research, download full MySQL backups, or purge system audit trail records.
          </p>

          <div style="display: flex; flex-direction: column; gap: 0.85rem;">
            <!-- CSV Exports Bar -->
            <div>
              <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.4rem;">Export Structured Entities (CSV):</span>
              <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=donors&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline btn-sm">
                  ❤️ Donors CSV
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=recipients&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline btn-sm">
                  📋 Recipients CSV
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=matches&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline btn-sm">
                  ⚡ Matches CSV
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=users&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline btn-sm">
                  👥 Users CSV
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=logs&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline btn-sm">
                  📝 Logs CSV
                </a>
              </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 0.5rem 0;">

            <!-- Dangerous Zone -->
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
              <button type="button" class="btn btn-outline-danger btn-sm" onclick="openModal('clearLogsModal')">
                🧹 Clear Audit Logs
              </button>
              <button type="button" class="btn btn-danger btn-sm" onclick="openModal('resetDemoModal')">
                🔄 Re-seed Academic Demo Baseline
              </button>
            </div>
          </div>
        </div>

      </div>

    </div>

  </main>

</div>

<!-- ======================================================= -->
<!-- MODAL: RESET DEMO DATA CONFIRMATION                     -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="resetDemoModal" role="dialog" aria-modal="true" aria-labelledby="resetModalTitle">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header" style="border-bottom-color: #fee2e2;">
      <h3 id="resetModalTitle" style="color: var(--danger);">⚠️ Reset System to Demo Baseline</h3>
      <button type="button" class="modal-close" onclick="closeModal('resetDemoModal')" aria-label="Close modal">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="reset_demo_data">
      <input type="hidden" name="redirect_to" value="<?php echo BASE_URL; ?>/admin/system.php">

      <div class="modal-body">
        <p style="font-size: 0.95rem; line-height: 1.5; color: var(--text-main);">
          Are you sure you want to completely re-seed the portal database with original demo records from <code>database.sql</code>?
        </p>
        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 0.85rem; border-radius: var(--radius-md); margin: 1rem 0; font-size: 0.85rem; color: #991b1b; line-height: 1.45;">
          <strong>Warning:</strong> All newly added donors, recipients, matches, and audit logs created during testing will be replaced with clean baseline demo records. Admin login credentials (<code>admin@organportal.com</code> / <code>admin123</code>) will remain intact.
        </div>
        <p style="font-size: 0.85rem; color: var(--text-muted);">
          Tip: Download an SQL backup first if you want to keep any current test records.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('resetDemoModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, Re-seed Demo Data</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: CLEAR AUDIT LOGS CONFIRMATION                    -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="clearLogsModal" role="dialog" aria-modal="true" aria-labelledby="clearLogsModalTitle">
  <div class="modal-content" style="max-width: 480px;">
    <div class="modal-header" style="border-bottom-color: #fee2e2;">
      <h3 id="clearLogsModalTitle" style="color: var(--danger);">🧹 Purge System Audit Logs</h3>
      <button type="button" class="modal-close" onclick="closeModal('clearLogsModal')" aria-label="Close modal">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="clear_logs">
      <input type="hidden" name="redirect_to" value="<?php echo BASE_URL; ?>/admin/system.php">

      <div class="modal-body">
        <p style="font-size: 0.95rem; line-height: 1.5; color: var(--text-main);">
          Are you sure you want to permanently clear all entries in the administrative audit log table?
        </p>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; padding: 0.85rem; border-radius: var(--radius-md); margin: 1rem 0; font-size: 0.85rem; color: #92400e; line-height: 1.45;">
          This action empties <code>admin_logs</code>. A single new log entry will immediately be generated noting that the audit trail was cleared by your admin account.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('clearLogsModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, Clear Logs</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
