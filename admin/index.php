<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Administrator Dashboard Overview
 */

$page_title = "Admin Dashboard Overview";
$include_dashboard_css = true;
$include_dashboard_js = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/matching_engine.php';

require_admin();
$admin = current_user();
$pdo = get_db_connection();

// Portal statistics
$stats = get_portal_summary_statistics();

// Fetch Pending Donors Queue (max 5)
$pendingDonors = $pdo->query("SELECT * FROM donors WHERE verification_status = 'Pending' ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Fetch Pending Recipients Queue (max 5)
$pendingRecipients = $pdo->query("SELECT * FROM recipients WHERE verification_status = 'Pending' ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Fetch Critical & High Urgency Recipients
$urgentRecipients = $pdo->query("SELECT * FROM recipients WHERE urgency_level IN ('Critical', 'High') AND verification_status = 'Verified' ORDER BY FIELD(urgency_level, 'Critical', 'High'), created_at ASC LIMIT 5")->fetchAll();

// Recent Audit Logs
$recentLogs = $pdo->query("SELECT l.*, u.name AS admin_name FROM admin_logs l LEFT JOIN users u ON l.admin_id = u.id ORDER BY l.created_at DESC LIMIT 6")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
  
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="dashboard-main">
    
    <!-- Top Bar -->
    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>Administrator Control Center</h1>
        <p>Monitor registrations, verify hospital declarations, and oversee preliminary compatibility matching.</p>
      </div>
      <div>
        <a href="<?php echo BASE_URL; ?>/admin/matches.php" class="btn btn-primary">
          <span>Run Preliminary Matching Engine</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
        </a>
      </div>
    </div>

    <!-- Academic Disclaimer Banner -->
    <div class="disclaimer-banner">
      <div class="icon">⚠️</div>
      <div>
        <h4>Academic Demonstration &amp; Medical Disclaimer</h4>
        <p>
          <?php echo MEDICAL_DISCLAIMER; ?>
        </p>
      </div>
    </div>

    <!-- KPI Metric Summary Cards -->
    <div class="kpi-grid">
      <!-- Donors KPI -->
      <div class="kpi-card">
        <div>
          <div class="kpi-label">Total Donors</div>
          <div class="kpi-value"><?php echo $stats['total_donors']; ?></div>
          <div class="kpi-sub"><?php echo $stats['verified_donors']; ?> Verified &bull; <?php echo $stats['pending_donors']; ?> Pending</div>
        </div>
        <div class="kpi-icon kpi-blue">❤️</div>
      </div>

      <!-- Recipients KPI -->
      <div class="kpi-card">
        <div>
          <div class="kpi-label">Total Recipients</div>
          <div class="kpi-value"><?php echo $stats['total_recipients']; ?></div>
          <div class="kpi-sub"><?php echo $stats['verified_recipients']; ?> Verified &bull; <?php echo $stats['pending_recipients']; ?> Pending</div>
        </div>
        <div class="kpi-icon kpi-teal">📋</div>
      </div>

      <!-- Pending Verifications KPI -->
      <div class="kpi-card">
        <div>
          <div class="kpi-label">Pending Reviews</div>
          <div class="kpi-value" style="color: <?php echo ($stats['pending_verifications'] > 0) ? 'var(--warning)' : 'var(--success)'; ?>;">
            <?php echo $stats['pending_verifications']; ?>
          </div>
          <div class="kpi-sub">Awaiting administrative check</div>
        </div>
        <div class="kpi-icon kpi-amber">⏳</div>
      </div>

      <!-- Potential Matches KPI -->
      <div class="kpi-card">
        <div>
          <div class="kpi-label">Potential Matches</div>
          <div class="kpi-value"><?php echo $stats['total_matches']; ?></div>
          <div class="kpi-sub"><?php echo $stats['under_review_matches']; ?> Under Clinical Review</div>
        </div>
        <div class="kpi-icon kpi-green">⚡</div>
      </div>
    </div>

    <!-- Quick Actions Command Center -->
    <div class="card" style="margin-bottom: 2rem; padding: 1.25rem 1.75rem; background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-xs);">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
          <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: rgba(26, 115, 232, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
            ⚡
          </div>
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.15rem;">Admin Quick Command Center</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Instant operational controls: direct enrollments, automated matching re-evaluations, and data exports.</p>
          </div>
        </div>
        <div style="display: flex; gap: 0.65rem; flex-wrap: wrap; align-items: center;">
          <a href="<?php echo BASE_URL; ?>/admin/donors.php" class="btn btn-outline-primary btn-sm">+ Add Donor</a>
          <a href="<?php echo BASE_URL; ?>/admin/recipients.php" class="btn btn-outline-primary btn-sm">+ Add Recipient</a>
          <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST" style="margin: 0; display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="recalculate_matches">
            <button type="submit" class="btn btn-primary btn-sm">⚡ Recalculate Matches</button>
          </form>
          <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=matches&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline btn-sm">📥 Export Matches CSV</a>
        </div>
      </div>
    </div>

    <!-- Two-column Workstation Layout -->
    <div class="dashboard-grid-2col">
      
      <!-- Left: Pending Verifications Queue -->
      <div>
        <div class="table-card" style="margin-bottom: 0;">
          <div class="card-header" style="padding: 1.25rem 1.5rem; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border);">
            <div>
              <h2 style="font-size: 1.15rem; margin-bottom: 0.15rem;">Pending Verifications Queue</h2>
              <p>Newly submitted profiles awaiting review.</p>
            </div>
            <span class="badge badge-pending"><?php echo count($pendingDonors) + count($pendingRecipients); ?> In Queue</span>
          </div>

          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Name &amp; Contact</th>
                  <th>Blood &amp; Organ</th>
                  <th>Submitted</th>
                  <th style="text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pendingDonors) && empty($pendingRecipients)): ?>
                  <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                      ✅ All submitted profiles are currently verified. No pending items in queue.
                    </td>
                  </tr>
                <?php else: ?>
                  <!-- Pending Donors -->
                  <?php foreach ($pendingDonors as $pd): ?>
                    <tr id="queue-donor-row-<?php echo $pd['id']; ?>">
                      <td><span class="badge badge-low">Donor</span></td>
                      <td>
                        <div class="user-cell">
                          <span class="user-cell-name"><?php echo htmlspecialchars($pd['full_name']); ?></span>
                          <span class="user-cell-meta"><?php echo htmlspecialchars($pd['address_city']); ?></span>
                        </div>
                      </td>
                      <td>
                        <strong><?php echo htmlspecialchars($pd['blood_group']); ?></strong> &bull; <?php echo htmlspecialchars($pd['organ_donated']); ?>
                      </td>
                      <td style="font-size: 0.8rem; color: var(--text-muted);">
                        <?php echo date('M d, H:i', strtotime($pd['created_at'])); ?>
                      </td>
                      <td style="text-align: right; white-space: nowrap;">
                        <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                          <!-- Quick Verify -->
                          <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST" style="margin: 0; display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="quick_verify">
                            <input type="hidden" name="type" value="donor">
                            <input type="hidden" name="id" value="<?php echo $pd['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                            <button type="submit" class="btn btn-primary btn-sm" title="Quick Approve Donor" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;">
                              ✓ Verify
                            </button>
                          </form>

                          <!-- Quick Reject -->
                          <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Reject this donor registration?')">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="quick_reject">
                            <input type="hidden" name="type" value="donor">
                            <input type="hidden" name="id" value="<?php echo $pd['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Quick Reject" style="padding: 0.25rem 0.45rem; font-size: 0.78rem;">
                              ✕
                            </button>
                          </form>

                          <!-- Review Modal Trigger -->
                          <button type="button" class="btn btn-outline-primary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" onclick="openActionModal('donor', <?php echo $pd['id']; ?>, '<?php echo htmlspecialchars(addslashes($pd['full_name'])); ?>', 'Pending')">
                            Review
                          </button>

                          <!-- Printable Card -->
                          <a href="<?php echo BASE_URL; ?>/admin/donor_card.php?id=<?php echo $pd['id']; ?>" target="_blank" class="btn btn-outline btn-sm" title="Print Organ Donor Pledge Card" style="padding: 0.25rem 0.45rem; font-size: 0.78rem;">
                            🪪
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>

                  <!-- Pending Recipients -->
                  <?php foreach ($pendingRecipients as $pr): ?>
                    <tr id="queue-recipient-row-<?php echo $pr['id']; ?>">
                      <td><span class="badge badge-contacted">Recipient</span></td>
                      <td>
                        <div class="user-cell">
                          <span class="user-cell-name"><?php echo htmlspecialchars($pr['full_name']); ?></span>
                          <span class="user-cell-meta"><?php echo htmlspecialchars($pr['hospital_city']); ?></span>
                        </div>
                      </td>
                      <td>
                        <strong><?php echo htmlspecialchars($pr['blood_group']); ?></strong> &bull; <?php echo htmlspecialchars($pr['organ_needed']); ?>
                        <span class="badge <?php echo ($pr['urgency_level'] === 'Critical') ? 'badge-critical' : 'badge-high'; ?>" style="font-size: 0.7rem; margin-left: 0.25rem;">
                          <?php echo $pr['urgency_level']; ?>
                        </span>
                      </td>
                      <td style="font-size: 0.8rem; color: var(--text-muted);">
                        <?php echo date('M d, H:i', strtotime($pr['created_at'])); ?>
                      </td>
                      <td style="text-align: right; white-space: nowrap;">
                        <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                          <!-- Quick Verify -->
                          <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST" style="margin: 0; display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="quick_verify">
                            <input type="hidden" name="type" value="recipient">
                            <input type="hidden" name="id" value="<?php echo $pr['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                            <button type="submit" class="btn btn-primary btn-sm" title="Quick Approve Recipient" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;">
                              ✓ Verify
                            </button>
                          </form>

                          <!-- Quick Reject -->
                          <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Reject this recipient request?')">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="quick_reject">
                            <input type="hidden" name="type" value="recipient">
                            <input type="hidden" name="id" value="<?php echo $pr['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Quick Reject" style="padding: 0.25rem 0.45rem; font-size: 0.78rem;">
                              ✕
                            </button>
                          </form>

                          <!-- Review Modal Trigger -->
                          <button type="button" class="btn btn-outline-primary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" onclick="openActionModal('recipient', <?php echo $pr['id']; ?>, '<?php echo htmlspecialchars(addslashes($pr['full_name'])); ?>', 'Pending')">
                            Review
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Right: Urgent Priority Recipient Watchlist -->
      <div>
        <div class="table-card" style="margin-bottom: 1.5rem;">
          <div class="card-header" style="padding: 1.25rem 1.5rem; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border);">
            <div>
              <h2 style="font-size: 1.15rem; margin-bottom: 0.15rem;">Urgent Recipient Watchlist</h2>
              <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Critical &amp; high-urgency patients awaiting matches.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/admin/recipients.php" style="font-size: 0.82rem; font-weight: 600;">View All &rarr;</a>
          </div>

          <div style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 0.85rem;">
            <?php if (!empty($urgentRecipients)): ?>
              <?php foreach ($urgentRecipients as $ur): ?>
                <div style="background: var(--bg-page); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center;">
                  <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">
                      <strong style="font-size: 0.95rem; color: var(--text-main);"><?php echo htmlspecialchars($ur['full_name']); ?></strong>
                      <span class="badge <?php echo ($ur['urgency_level'] === 'Critical') ? 'badge-critical' : 'badge-high'; ?>">
                        <?php echo htmlspecialchars($ur['urgency_level']); ?>
                      </span>
                    </div>
                    <div style="font-size: 0.82rem; color: var(--text-muted);">
                      Needs: <strong><?php echo htmlspecialchars($ur['organ_needed']); ?></strong> (Blood: <?php echo htmlspecialchars($ur['blood_group']); ?>) &bull; <?php echo htmlspecialchars($ur['hospital_city']); ?>
                    </div>
                  </div>
                  <a href="<?php echo BASE_URL; ?>/admin/matches.php?organ=<?php echo urlencode($ur['organ_needed']); ?>" class="btn btn-outline-primary btn-sm" title="Find matching donors for this organ">
                    Find Match
                  </a>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 1.5rem 0; margin: 0;">No urgent cases currently listed.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Audit Log Stream -->
        <div class="table-card" style="margin-bottom: 0; padding: 1.25rem 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
            <div>
              <h2 style="font-size: 1.15rem; margin-bottom: 0.15rem; color: var(--text-main); font-weight: 700;">Recent Activity Log</h2>
              <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Latest administrative audit trail events.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/admin/logs.php" style="font-size: 0.82rem; font-weight: 600; color: var(--primary);">Full Logs &rarr;</a>
          </div>

          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php foreach ($recentLogs as $log): ?>
              <div style="font-size: 0.85rem; padding: 0.65rem 0; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 0.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <strong style="color: var(--primary); font-size: 0.88rem;"><?php echo htmlspecialchars($log['action']); ?></strong>
                  <span style="color: var(--text-muted); font-size: 0.75rem; font-weight: 500;"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></span>
                </div>
                <div style="color: #475569; font-size: 0.83rem; line-height: 1.4;"><?php echo htmlspecialchars($log['description']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div>

  </main>

</div>

<!-- Universal Review & Verification Action Modal -->
<div class="modal-backdrop" id="statusActionModal" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="statusModalTitle">Verify Registration</h3>
      <button type="button" class="modal-close" onclick="closeModal('statusActionModal')" aria-label="Close modal">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" id="actionFormName" value="update_donor_status">
        <input type="hidden" name="donor_id" id="modalDonorId" value="">
        <input type="hidden" name="recipient_id" id="modalRecipientId" value="">
        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

        <div style="margin-bottom: 1.25rem;">
          <span style="font-size: 0.85rem; color: var(--text-muted); display: block;">Target Profile:</span>
          <strong id="actionTargetName" style="font-size: 1.15rem; color: var(--text-main);">--</strong>
        </div>

        <div class="form-group">
          <label class="form-label" for="actionNewStatus">Set Verification Status <span class="required">*</span></label>
          <select name="new_status" id="actionNewStatus" class="form-control" required>
            <option value="Verified">Verified (Approve for Matching)</option>
            <option value="Pending">Pending (Keep in queue)</option>
            <option value="Rejected">Rejected (Incomplete / Ineligible)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="actionAdminNotes">Administrative Review Notes</label>
          <textarea name="admin_notes" id="actionAdminNotes" class="form-control" rows="3" placeholder="Enter clinical verification details, hospital confirmation, or rejection reason..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('statusActionModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Verification Decision</button>
      </div>
    </form>
  </div>
</div>

<script>
function openActionModal(type, id, name, currentStatus) {
  document.getElementById('actionTargetName').textContent = name + ' (' + (type === 'donor' ? 'Donor' : 'Recipient') + ')';
  document.getElementById('actionFormName').value = (type === 'donor') ? 'update_donor_status' : 'update_recipient_status';
  
  if (type === 'donor') {
    document.getElementById('modalDonorId').value = id;
    document.getElementById('modalRecipientId').value = '';
  } else {
    document.getElementById('modalRecipientId').value = id;
    document.getElementById('modalDonorId').value = '';
  }

  const select = document.getElementById('actionNewStatus');
  if (select && currentStatus) {
    select.value = currentStatus;
  }

  openModal('statusActionModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
