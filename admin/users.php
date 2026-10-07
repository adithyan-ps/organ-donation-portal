<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Administrator User Accounts & Access Governance
 */

$page_title = "User Accounts & Staff";
$include_dashboard_css = true;
$include_dashboard_js = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();
$admin = current_user();
$pdo = get_db_connection();

// Filters
$search      = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');

$query = "SELECT u.*, 
                 d.id as donor_record_id, d.organ_donated,
                 r.id as recip_record_id, r.organ_needed
          FROM users u
          LEFT JOIN donors d ON d.user_id = u.id
          LEFT JOIN recipients r ON r.user_id = u.id
          WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like]);
}

if ($role_filter !== '') {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

$query .= " ORDER BY FIELD(u.role, 'admin', 'donor', 'recipient'), u.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
  
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="dashboard-main">
    
    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>User Accounts &amp; Staff Governance</h1>
        <p>Manage system credentials, create coordinators, reset forgotten passwords, and inspect access logs.</p>
      </div>
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <button type="button" class="btn btn-primary" onclick="openModal('addAdminModal')">
          + Add Administrator
        </button>
        <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=users&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline" title="Download User List CSV">
          📥 Export Users CSV
        </a>
      </div>
    </div>

    <!-- Filter & Search Bar -->
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="filter-bar">
      <div class="filter-group">
        <div class="search-input-wrapper">
          <span class="search-icon">🔍</span>
          <input type="text" name="search" class="form-control" placeholder="Search by name, email..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <select name="role" class="form-control" style="width: auto; min-width: 160px;">
          <option value="">All Account Roles</option>
          <option value="admin" <?php echo ($role_filter === 'admin') ? 'selected' : ''; ?>>Administrator</option>
          <option value="donor" <?php echo ($role_filter === 'donor') ? 'selected' : ''; ?>>Donor</option>
          <option value="recipient" <?php echo ($role_filter === 'recipient') ? 'selected' : ''; ?>>Recipient</option>
        </select>

        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-outline btn-sm">Reset</a>
      </div>

      <div style="font-size: 0.85rem; color: var(--text-muted);">
        Found <strong><?php echo count($users); ?></strong> registered accounts
      </div>
    </form>

    <!-- Users Table Card -->
    <div class="table-card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Full Name</th>
              <th>Email Address</th>
              <th>System Role</th>
              <th>Linked Medical Record</th>
              <th>Account Created</th>
              <th style="text-align: right;">Credentials &amp; Access</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                  <div style="font-size: 2rem; margin-bottom: 0.5rem;">👥</div>
                  No user accounts found matching your query.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <tr id="user-row-<?php echo $u['id']; ?>" data-searchable="true">
                  <td><strong>#<?php echo $u['id']; ?></strong></td>
                  <td>
                    <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                    <?php if ((int)$u['id'] === (int)$admin['id']): ?>
                      <span class="badge badge-verified" style="font-size: 0.7rem; margin-left: 0.25rem;">You</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($u['email']); ?></td>
                  <td>
                    <?php 
                      $roleBadge = match($u['role']) {
                        'admin'     => 'badge-critical',
                        'donor'     => 'badge-verified',
                        'recipient' => 'badge-contacted',
                        default     => 'badge-low'
                      };
                    ?>
                    <span class="badge <?php echo $roleBadge; ?>" style="text-transform: capitalize; font-weight: 600;">
                      <?php echo htmlspecialchars($u['role']); ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($u['donor_record_id']): ?>
                      <a href="<?php echo BASE_URL; ?>/admin/donors.php?search=<?php echo urlencode($u['email']); ?>" style="font-size: 0.82rem; color: var(--primary); font-weight: 600;">
                        Donor #<?php echo $u['donor_record_id']; ?> (<?php echo htmlspecialchars($u['organ_donated']); ?>)
                      </a>
                    <?php elseif ($u['recip_record_id']): ?>
                      <a href="<?php echo BASE_URL; ?>/admin/recipients.php?search=<?php echo urlencode($u['email']); ?>" style="font-size: 0.82rem; color: var(--secondary); font-weight: 600;">
                        Recipient #<?php echo $u['recip_record_id']; ?> (<?php echo htmlspecialchars($u['organ_needed']); ?>)
                      </a>
                    <?php else: ?>
                      <span style="font-size: 0.8rem; color: var(--text-muted);">Console Staff</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                    <?php echo date('M d, Y &bull; H:i', strtotime($u['created_at'])); ?>
                  </td>
                  <td style="text-align: right; white-space: nowrap;">
                    <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                      <!-- Reset Password Button -->
                      <button type="button" class="btn btn-outline btn-sm" onclick="openResetPasswordModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['email'])); ?>')" title="Reset account password">
                        🔑 Reset
                      </button>

                      <!-- Delete User Button (Disabled for self) -->
                      <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDeleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['email'])); ?>', '<?php echo $u['role']; ?>')" title="Delete user account">
                          🗑️
                        </button>
                      <?php endif; ?>
                    </div>
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
<!-- MODAL: ADD ADMINISTRATOR ACCOUNT                        -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="addAdminModal" role="dialog" aria-modal="true" aria-labelledby="addAdminTitle">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h3 id="addAdminTitle">Add Administrator / Coordinator</h3>
      <button type="button" class="modal-close" onclick="closeModal('addAdminModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="create_admin_user">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
          Create a new administrative coordinator account with full review, verification, and allocation privileges.
        </p>

        <div class="form-group">
          <label class="form-label" for="admin_name">Full Name <span class="required">*</span></label>
          <input type="text" name="name" id="admin_name" class="form-control" placeholder="e.g. Dr. Priya Menon" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="admin_email">Official Email Address <span class="required">*</span></label>
          <input type="email" name="email" id="admin_email" class="form-control" placeholder="priya@hospital.org" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="admin_password">Portal Password <span class="required">*</span></label>
          <input type="password" name="password" id="admin_password" class="form-control" placeholder="At least 6 characters" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addAdminModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Admin Account</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: RESET PASSWORD                                    -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="resetPasswordModal" role="dialog" aria-modal="true" aria-labelledby="resetPwdTitle">
  <div class="modal-content" style="max-width: 450px;">
    <div class="modal-header">
      <h3 id="resetPwdTitle">🔑 Reset User Password</h3>
      <button type="button" class="modal-close" onclick="closeModal('resetPasswordModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="reset_user_password">
      <input type="hidden" name="user_id" id="reset_user_id" value="">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <div style="margin-bottom: 1.25rem;">
          <span style="font-size: 0.85rem; color: var(--text-muted); display: block;">Target User:</span>
          <strong id="reset_user_email" style="font-size: 1.05rem; color: var(--text-main);">--</strong>
        </div>

        <div class="form-group">
          <label class="form-label" for="new_password">Assign New Password <span class="required">*</span></label>
          <input type="text" name="new_password" id="new_password" class="form-control" value="donor123" required>
          <span class="form-help">Enter a temporary password to provide to the user. Minimum 6 characters.</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('resetPasswordModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save New Password</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: DELETE USER CONFIRMATION                          -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="deleteUserModal" role="dialog" aria-modal="true" aria-labelledby="deleteUserTitle">
  <div class="modal-content" style="max-width: 450px;">
    <div class="modal-header" style="border-bottom-color: #fee2e2;">
      <h3 id="deleteUserTitle" style="color: var(--danger);">🗑️ Delete User Account</h3>
      <button type="button" class="modal-close" onclick="closeModal('deleteUserModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" id="delete_user_id" value="">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <p style="font-size: 0.95rem; line-height: 1.5; color: var(--text-main);">
          Are you sure you want to permanently remove this user account?
        </p>
        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin: 0.75rem 0;">
          <strong id="delete_user_label" style="color: #991b1b; font-size: 1.05rem;">--</strong>
        </div>
        <p style="font-size: 0.82rem; color: var(--text-muted);">
          ⚠️ The associated donor or recipient records will be unlinked. Account sign-in credentials will be revoked immediately.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('deleteUserModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, Delete Account</button>
      </div>
    </form>
  </div>
</div>

<script>
function openResetPasswordModal(userId, email) {
  document.getElementById('reset_user_id').value = userId;
  document.getElementById('reset_user_email').textContent = email;
  openModal('resetPasswordModal');
}

function confirmDeleteUser(userId, email, role) {
  document.getElementById('delete_user_id').value = userId;
  document.getElementById('delete_user_label').textContent = email + ' (' + role + ')';
  openModal('deleteUserModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
