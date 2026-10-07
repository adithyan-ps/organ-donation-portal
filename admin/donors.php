<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Administrator Donors Management Suite (Full CRUD & Exports)
 */

$page_title = "Manage Donors";
$include_dashboard_css = true;
$include_dashboard_js = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();
$pdo = get_db_connection();

// Filter parameters
$search        = trim($_GET['search'] ?? '');
$blood_filter  = trim($_GET['blood'] ?? '');
$organ_filter  = trim($_GET['organ'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$match_filter  = trim($_GET['match'] ?? '');

$query = "SELECT d.*,
                 m_app.id AS approved_match_id,
                 m_app.status AS approved_match_status,
                 m_app.compatibility_score AS approved_match_score,
                 m_app.recipient_id AS approved_recipient_id,
                 r_app.full_name AS approved_recipient_name,
                 (SELECT COUNT(*) FROM matches WHERE donor_id = d.id AND status = 'Approved') AS count_approved,
                 (SELECT COUNT(*) FROM matches WHERE donor_id = d.id AND status IN ('Under Review', 'Contacted')) AS count_in_review,
                 (SELECT COUNT(*) FROM matches WHERE donor_id = d.id AND status = 'Potential') AS count_potential
          FROM donors d
          LEFT JOIN matches m_app ON m_app.donor_id = d.id AND m_app.status = 'Approved'
          LEFT JOIN recipients r_app ON m_app.recipient_id = r_app.id
          WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (d.full_name LIKE ? OR d.email LIKE ? OR d.address_city LIKE ? OR d.mobile LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($blood_filter !== '') {
    $query .= " AND d.blood_group = ?";
    $params[] = $blood_filter;
}
if ($organ_filter !== '') {
    $query .= " AND d.organ_donated = ?";
    $params[] = $organ_filter;
}
if ($status_filter !== '') {
    $query .= " AND d.verification_status = ?";
    $params[] = $status_filter;
}
if ($match_filter === 'matched') {
    $query .= " AND (SELECT COUNT(*) FROM matches WHERE donor_id = d.id AND status = 'Approved') > 0";
} elseif ($match_filter === 'in_review') {
    $query .= " AND (SELECT COUNT(*) FROM matches WHERE donor_id = d.id AND status IN ('Under Review', 'Contacted')) > 0";
} elseif ($match_filter === 'potential') {
    $query .= " AND (SELECT COUNT(*) FROM matches WHERE donor_id = d.id AND status = 'Potential') > 0";
} elseif ($match_filter === 'unmatched') {
    $query .= " AND (SELECT COUNT(*) FROM matches WHERE donor_id = d.id AND status != 'Closed') = 0";
}

$query .= " ORDER BY d.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$donors = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
  
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="dashboard-main">
    
    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>Donor Registry &amp; Management</h1>
        <p>Complete administrative controls: Add, edit, verify, toggle availability, and export donor pledges.</p>
      </div>
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <button type="button" class="btn btn-primary" onclick="openModal('addDonorModal')">
          + Add New Donor
        </button>
        <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=donors&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline" title="Download Donors CSV">
          📥 Export CSV
        </a>
      </div>
    </div>

    <!-- Filter & Search Bar -->
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="filter-bar">
      <div class="filter-group">
        
        <!-- Search Input -->
        <div class="search-input-wrapper">
          <span class="search-icon">🔍</span>
          <input type="text" name="search" id="tableSearch" class="form-control" placeholder="Search by name, city, email..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <!-- Blood Group Filter -->
        <select name="blood" id="filterBlood" class="form-control" style="width: auto; min-width: 140px;">
          <option value="">All Blood Groups</option>
          <?php foreach ($GLOBALS['BLOOD_GROUPS'] as $bg): ?>
            <option value="<?php echo $bg; ?>" <?php echo ($blood_filter === $bg) ? 'selected' : ''; ?>><?php echo $bg; ?></option>
          <?php endforeach; ?>
        </select>

        <!-- Organ Filter -->
        <select name="organ" id="filterOrgan" class="form-control" style="width: auto; min-width: 150px;">
          <option value="">All Organs</option>
          <?php foreach ($GLOBALS['SUPPORTED_ORGANS'] as $org): ?>
            <option value="<?php echo $org; ?>" <?php echo ($organ_filter === $org) ? 'selected' : ''; ?>><?php echo $org; ?></option>
          <?php endforeach; ?>
        </select>

        <!-- Status Filter -->
        <select name="status" id="filterStatus" class="form-control" style="width: auto; min-width: 140px;">
          <option value="">All Statuses</option>
          <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
          <option value="Verified" <?php echo ($status_filter === 'Verified') ? 'selected' : ''; ?>>Verified</option>
          <option value="Rejected" <?php echo ($status_filter === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
        </select>

        <!-- Match State Filter -->
        <select name="match" id="filterMatch" class="form-control" style="width: auto; min-width: 160px;">
          <option value="">All Match States</option>
          <option value="matched" <?php echo ($match_filter === 'matched') ? 'selected' : ''; ?>>🎉 Matched (Approved)</option>
          <option value="in_review" <?php echo ($match_filter === 'in_review') ? 'selected' : ''; ?>>⏳ In Clinical Review</option>
          <option value="potential" <?php echo ($match_filter === 'potential') ? 'selected' : ''; ?>>🔍 Potential Matches</option>
          <option value="unmatched" <?php echo ($match_filter === 'unmatched') ? 'selected' : ''; ?>>⚪ Unmatched</option>
        </select>

        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="<?php echo BASE_URL; ?>/admin/donors.php" class="btn btn-outline btn-sm" id="clearFilters">Reset</a>
      </div>

      <div style="font-size: 0.85rem; color: var(--text-muted);">
        Found <strong><?php echo count($donors); ?></strong> registered donors
      </div>
    </form>

    <!-- Donors Table Card with Multi-Select Bulk Triage -->
    <form id="donorBulkForm" action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="bulk_donor_action">
      <input type="hidden" name="bulk_action" id="bulkDonorActionInput" value="">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <!-- Bulk Action Bar -->
      <div id="bulkDonorActionBar" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.75rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div style="font-size: 0.9rem; color: #1e40af; font-weight: 600;">
          <span id="donorSelectedCount">0</span> donor profile(s) selected:
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
          <button type="button" class="btn btn-primary btn-sm" onclick="submitBulkDonor('verify')">
            ✓ Bulk Verify
          </button>
          <button type="button" class="btn btn-outline btn-sm" onclick="submitBulkDonor('reject')">
            ✕ Bulk Reject
          </button>
          <button type="button" class="btn btn-outline-danger btn-sm" onclick="submitBulkDonor('delete')">
            🗑️ Bulk Delete
          </button>
          <button type="button" class="btn btn-outline btn-sm" onclick="clearDonorSelection()">
            Cancel Selection
          </button>
        </div>
      </div>

      <div class="table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 36px; text-align: center;">
                  <input type="checkbox" id="selectAllDonors" onclick="toggleAllDonors(this.checked)" title="Select all donors on this page">
                </th>
                <th>ID</th>
                <th>Donor Name &amp; Location</th>
                <th>Age/Gender</th>
                <th>Blood</th>
                <th>Organ Pledged</th>
                <th>Availability</th>
                <th>Status</th>
                <th>Match State</th>
                <th>Pledge Date</th>
                <th style="text-align: right;">Administrative Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($donors)): ?>
                <tr>
                  <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                    No donor records found matching your filters.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($donors as $d): ?>
                  <tr id="donor-row-<?php echo $d['id']; ?>" data-donor-id="<?php echo $d['id']; ?>" data-searchable="true" data-blood="<?php echo htmlspecialchars($d['blood_group']); ?>" data-organ="<?php echo htmlspecialchars($d['organ_donated']); ?>" data-status="<?php echo htmlspecialchars($d['verification_status']); ?>">
                    <td style="text-align: center;">
                      <input type="checkbox" name="selected_ids[]" value="<?php echo $d['id']; ?>" class="donor-checkbox" onchange="syncDonorBulkBar()">
                    </td>
                    <td><strong>#<?php echo $d['id']; ?></strong></td>
                    <td>
                      <div class="user-cell">
                        <span class="user-cell-name"><?php echo htmlspecialchars($d['full_name']); ?></span>
                        <span class="user-cell-meta"><?php echo htmlspecialchars($d['address_city']); ?> &bull; <?php echo htmlspecialchars($d['mobile']); ?></span>
                      </div>
                    </td>
                    <td><?php echo $d['age']; ?>y / <?php echo htmlspecialchars($d['gender']); ?></td>
                    <td>
                      <span class="badge" style="background: #fee2e2; color: #b91c1c; font-weight: 700; font-size: 0.85rem;">
                        <?php echo htmlspecialchars($d['blood_group']); ?>
                      </span>
                    </td>
                    <td>
                      <strong style="color: var(--primary);"><?php echo htmlspecialchars($d['organ_donated']); ?></strong>
                    </td>
                    <td>
                      <?php if ($d['availability_status'] === 'Available'): ?>
                        <span class="badge badge-verified">Available</span>
                      <?php elseif ($d['availability_status'] === 'Donated'): ?>
                        <span class="badge badge-low">Donated</span>
                      <?php else: ?>
                        <span class="badge badge-pending">Unavailable</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge badge-<?php echo strtolower($d['verification_status']); ?>">
                        <?php echo htmlspecialchars($d['verification_status']); ?>
                      </span>
                    </td>
                    <td>
                      <?php if (!empty($d['approved_match_id'])): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/match_dossier.php?id=<?php echo $d['approved_match_id']; ?>" target="_blank" class="badge badge-verified" style="font-size: 0.74rem; display: inline-flex; align-items: center; gap: 0.25rem;" title="Transplant Approved with Recipient #<?php echo $d['approved_recipient_id']; ?> (<?php echo htmlspecialchars($d['approved_recipient_name'] ?? ''); ?>)">
                          🎉 Matched #<?php echo $d['approved_recipient_id']; ?> (Approved)
                        </a>
                      <?php elseif (!empty($d['count_in_review'])): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/matches.php?search=<?php echo urlencode($d['full_name']); ?>" class="badge badge-pending" style="font-size: 0.74rem;" title="<?php echo $d['count_in_review']; ?> match(es) under review or contacted">
                          ⏳ In Review (<?php echo $d['count_in_review']; ?>)
                        </a>
                      <?php elseif (!empty($d['count_potential'])): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/matches.php?search=<?php echo urlencode($d['full_name']); ?>" class="badge badge-low" style="font-size: 0.74rem;" title="<?php echo $d['count_potential']; ?> algorithm matches identified">
                          🔍 <?php echo $d['count_potential']; ?> Potential
                        </a>
                      <?php else: ?>
                        <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 0.74rem;">Unmatched</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                      <?php echo date('M d, Y', strtotime($d['created_at'])); ?>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                        <!-- Printable Pledge Card -->
                        <a href="<?php echo BASE_URL; ?>/admin/donor_card.php?id=<?php echo $d['id']; ?>" target="_blank" class="btn btn-outline btn-sm" title="Print Official Wallet-size Donor Card">
                          🪪 Card
                        </a>

                        <!-- View Dossier -->
                        <button type="button" class="btn btn-outline btn-sm" onclick="viewDonorDetails(<?php echo htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8'); ?>)" title="View complete medical dossier">
                          👁️ View
                        </button>

                        <!-- Edit Donor Record -->
                        <button type="button" class="btn btn-outline btn-sm" onclick="openEditDonorModal(<?php echo htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit donor details">
                          ✏️ Edit
                        </button>

                        <!-- Verification Decision Trigger -->
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="openActionModal('donor', <?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['full_name'])); ?>', '<?php echo $d['verification_status']; ?>')">
                          ⚖️ Status
                        </button>

                        <!-- Delete Donor Trigger -->
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDeleteDonor(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['full_name'])); ?>')" title="Permanently delete donor">
                          🗑️
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
    </form>

  </main>

</div>

<!-- ======================================================= -->
<!-- MODAL: ADD NEW DONOR (DIRECT ADMIN CREATION)             -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="addDonorModal" role="dialog" aria-modal="true" aria-labelledby="addDonorTitle">
  <div class="modal-content" style="max-width: 650px;">
    <div class="modal-header">
      <h3 id="addDonorTitle">Direct Add: New Organ Donor</h3>
      <button type="button" class="modal-close" onclick="closeModal('addDonorModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="create_donor">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group col-span-2">
            <label class="form-label" for="add_full_name">Full Legal Name <span class="required">*</span></label>
            <input type="text" id="add_full_name" name="full_name" class="form-control" placeholder="e.g. Arun Kumar" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_age">Age (18-85) <span class="required">*</span></label>
            <input type="number" id="add_age" name="age" min="18" max="85" class="form-control" value="28" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_gender">Gender <span class="required">*</span></label>
            <select id="add_gender" name="gender" class="form-control" required>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_email">Email Address <span class="required">*</span></label>
            <input type="email" id="add_email" name="email" class="form-control" placeholder="donor@domain.com" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_mobile">Mobile Number <span class="required">*</span></label>
            <input type="tel" id="add_mobile" name="mobile" class="form-control" placeholder="9876543210" required>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="add_address_city">City &amp; State <span class="required">*</span></label>
            <input type="text" id="add_address_city" name="address_city" class="form-control" placeholder="e.g. Kochi, Kerala" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_blood_group">Blood Group <span class="required">*</span></label>
            <select id="add_blood_group" name="blood_group" class="form-control" required>
              <?php foreach ($GLOBALS['BLOOD_GROUPS'] as $bg): ?>
                <option value="<?php echo $bg; ?>"><?php echo $bg; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_organ_donated">Pledged Organ <span class="required">*</span></label>
            <select id="add_organ_donated" name="organ_donated" class="form-control" required>
              <?php foreach ($GLOBALS['SUPPORTED_ORGANS'] as $org): ?>
                <option value="<?php echo $org; ?>"><?php echo $org; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_availability_status">Availability Status <span class="required">*</span></label>
            <select id="add_availability_status" name="availability_status" class="form-control" required>
              <option value="Available">Available (Active Pledge)</option>
              <option value="Temporarily Unavailable">Temporarily Unavailable</option>
              <option value="Donated">Donated (Completed)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_verification_status">Verification Status <span class="required">*</span></label>
            <select id="add_verification_status" name="verification_status" class="form-control" required>
              <option value="Verified" selected>Verified (Directly Enrolled)</option>
              <option value="Pending">Pending Review</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="add_medical_notes">Health / Medical Notes</label>
            <textarea id="add_medical_notes" name="medical_notes" class="form-control" rows="2" placeholder="Non-smoker, fitness details, medical history..."></textarea>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="add_admin_notes">Admin Verification Notes</label>
            <textarea id="add_admin_notes" name="admin_notes" class="form-control" rows="2" placeholder="Administrative verification remarks..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addDonorModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Donor Record</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: EDIT DONOR DETAILS                                -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="editDonorModal" role="dialog" aria-modal="true" aria-labelledby="editDonorTitle">
  <div class="modal-content" style="max-width: 650px;">
    <div class="modal-header">
      <h3 id="editDonorTitle">Edit Donor Record</h3>
      <button type="button" class="modal-close" onclick="closeModal('editDonorModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="edit_donor">
      <input type="hidden" name="donor_id" id="edit_donor_id" value="">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group col-span-2">
            <label class="form-label" for="edit_full_name">Full Legal Name <span class="required">*</span></label>
            <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_age">Age <span class="required">*</span></label>
            <input type="number" id="edit_age" name="age" min="18" max="85" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_gender">Gender <span class="required">*</span></label>
            <select id="edit_gender" name="gender" class="form-control" required>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_email">Email Address <span class="required">*</span></label>
            <input type="email" id="edit_email" name="email" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_mobile">Mobile Number <span class="required">*</span></label>
            <input type="tel" id="edit_mobile" name="mobile" class="form-control" required>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="edit_address_city">City &amp; State <span class="required">*</span></label>
            <input type="text" id="edit_address_city" name="address_city" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_blood_group">Blood Group <span class="required">*</span></label>
            <select id="edit_blood_group" name="blood_group" class="form-control" required>
              <?php foreach ($GLOBALS['BLOOD_GROUPS'] as $bg): ?>
                <option value="<?php echo $bg; ?>"><?php echo $bg; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_organ_donated">Pledged Organ <span class="required">*</span></label>
            <select id="edit_organ_donated" name="organ_donated" class="form-control" required>
              <?php foreach ($GLOBALS['SUPPORTED_ORGANS'] as $org): ?>
                <option value="<?php echo $org; ?>"><?php echo $org; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_availability_status">Availability <span class="required">*</span></label>
            <select id="edit_availability_status" name="availability_status" class="form-control" required>
              <option value="Available">Available</option>
              <option value="Temporarily Unavailable">Temporarily Unavailable</option>
              <option value="Donated">Donated</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_verification_status">Verification Status <span class="required">*</span></label>
            <select id="edit_verification_status" name="verification_status" class="form-control" required>
              <option value="Verified">Verified</option>
              <option value="Pending">Pending</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="edit_medical_notes">Medical / Health Notes</label>
            <textarea id="edit_medical_notes" name="medical_notes" class="form-control" rows="2"></textarea>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="edit_admin_notes">Admin Reviewer Remarks</label>
            <textarea id="edit_admin_notes" name="admin_notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editDonorModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: DELETE DONOR CONFIRMATION                         -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="deleteDonorModal" role="dialog" aria-modal="true" aria-labelledby="deleteDonorTitle">
  <div class="modal-content" style="max-width: 450px;">
    <div class="modal-header" style="border-bottom-color: #fee2e2;">
      <h3 id="deleteDonorTitle" style="color: var(--danger);">🗑️ Delete Donor Record</h3>
      <button type="button" class="modal-close" onclick="closeModal('deleteDonorModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="delete_donor">
      <input type="hidden" name="donor_id" id="delete_donor_id" value="">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <p style="font-size: 0.95rem; line-height: 1.5; color: var(--text-main);">
          Are you sure you want to permanently delete donor:
        </p>
        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin: 0.75rem 0;">
          <strong id="delete_donor_name" style="color: #991b1b; font-size: 1.05rem;">--</strong>
        </div>
        <p style="font-size: 0.82rem; color: var(--text-muted);">
          ⚠️ This will remove the donor dossier, linked account credentials, and all associated preliminary matches. This action cannot be undone.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('deleteDonorModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, Permanently Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: VIEW FULL DOSSIER                                 -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="donorDetailsModal" role="dialog" aria-modal="true" aria-labelledby="donorDetailTitle">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="donorDetailTitle">Donor Medical Dossier</h3>
      <button type="button" class="modal-close" onclick="closeModal('donorDetailsModal')" aria-label="Close modal">&times;</button>
    </div>
    <div class="modal-body" id="donorDetailBody">
      <!-- Injected via JavaScript -->
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('donorDetailsModal')">Close</button>
    </div>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: UPDATE STATUS                                     -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="statusActionModal" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="statusModalTitle">Update Donor Verification</h3>
      <button type="button" class="modal-close" onclick="closeModal('statusActionModal')" aria-label="Close modal">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="update_donor_status">
        <input type="hidden" name="donor_id" id="modalDonorId" value="">
        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

        <div style="margin-bottom: 1.25rem;">
          <span style="font-size: 0.85rem; color: var(--text-muted); display: block;">Donor:</span>
          <strong id="actionTargetName" style="font-size: 1.15rem; color: var(--text-main);">--</strong>
        </div>

        <div class="form-group">
          <label class="form-label" for="actionNewStatus">Verification Status <span class="required">*</span></label>
          <select name="new_status" id="actionNewStatus" class="form-control" required>
            <option value="Verified">Verified (Active in Match Engine)</option>
            <option value="Pending">Pending (Under review)</option>
            <option value="Rejected">Rejected (Ineligible / Revoked)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="actionAdminNotes">Reviewer Clinical Notes</label>
          <textarea name="admin_notes" id="actionAdminNotes" class="form-control" rows="3" placeholder="Notes on ID verification, medical consent, or deferral reason..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('statusActionModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Decision</button>
      </div>
    </form>
  </div>
</div>

<script>
function viewDonorDetails(donor) {
  const body = document.getElementById('donorDetailBody');
  let matchSection = '';
  if (donor.approved_match_id) {
    matchSection = `
      <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
          <div style="font-weight: 700; color: #065f46; font-size: 1rem; display: flex; align-items: center; gap: 0.4rem;">
            <span>🎉</span> Transplant Allocation Approved
          </div>
          <a href="<?php echo BASE_URL; ?>/admin/match_dossier.php?id=${donor.approved_match_id}" target="_blank" class="btn btn-sm btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">
            Open Match Dossier &rarr;
          </a>
        </div>
        <div style="font-size: 0.88rem; color: #047857; line-height: 1.5;">
          Matched Recipient: <strong>#${donor.approved_recipient_id} (${escapeHtml(donor.approved_recipient_name || 'Anonymous Recipient')})</strong><br>
          Compatibility Score: <strong>${donor.approved_match_score}%</strong> &bull; Organ: <strong>${escapeHtml(donor.organ_donated)}</strong>
        </div>
      </div>
    `;
  } else if (parseInt(donor.count_in_review || 0) > 0) {
    matchSection = `
      <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 0.85rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; font-size: 0.88rem; color: #92400e; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>⏳ <strong>${donor.count_in_review} match(es) under active clinical review.</strong> Outreach in progress.</div>
        <a href="<?php echo BASE_URL; ?>/admin/matches.php?search=${encodeURIComponent(donor.full_name)}" class="btn btn-sm btn-outline" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">Review Matches</a>
      </div>
    `;
  } else if (parseInt(donor.count_potential || 0) > 0) {
    matchSection = `
      <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.85rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; font-size: 0.88rem; color: #1e40af; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>🔍 <strong>${donor.count_potential} compatible recipient candidate(s)</strong> identified by matching engine.</div>
        <a href="<?php echo BASE_URL; ?>/admin/matches.php?search=${encodeURIComponent(donor.full_name)}" class="btn btn-sm btn-outline" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">View Candidates</a>
      </div>
    `;
  }

  body.innerHTML = `
    ${matchSection}
    <div class="profile-info-grid" style="margin-bottom: 1.5rem;">
      <div class="profile-item"><span class="profile-item-label">Full Name</span><span class="profile-item-value">${escapeHtml(donor.full_name)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Age &amp; Gender</span><span class="profile-item-value">${donor.age} yrs, ${escapeHtml(donor.gender)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Blood Group</span><span class="profile-item-value" style="color: var(--danger); font-size: 1.2rem;">${escapeHtml(donor.blood_group)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Pledged Organ</span><span class="profile-item-value" style="color: var(--primary); font-size: 1.2rem;">${escapeHtml(donor.organ_donated)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Contact Email</span><span class="profile-item-value">${escapeHtml(donor.email)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Mobile Number</span><span class="profile-item-value">${escapeHtml(donor.mobile)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Address / City</span><span class="profile-item-value">${escapeHtml(donor.address_city)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Availability</span><span class="profile-item-value">${escapeHtml(donor.availability_status)}</span></div>
    </div>

    <div style="background: var(--bg-subtle); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; border: 1px solid var(--border);">
      <strong>Medical Declaration / Health History:</strong>
      <p style="font-size: 0.9rem; margin-top: 0.35rem; color: #334155;">
        ${donor.medical_notes ? escapeHtml(donor.medical_notes) : '<em>No additional medical declarations recorded.</em>'}
      </p>
    </div>

    <div style="font-size: 0.85rem; color: var(--text-muted);">
      <div><strong>Legal Consent:</strong> ${donor.consent_given == 1 ? '✅ Voluntary Consent Documented' : '❌ Consent Missing'}</div>
      <div><strong>Verification Status:</strong> <span class="badge badge-${donor.verification_status.toLowerCase()}">${donor.verification_status}</span></div>
      <div><strong>Registered At:</strong> ${donor.created_at}</div>
      ${donor.admin_notes ? `<div style="margin-top: 0.5rem; color: var(--primary);"><strong>Admin Notes:</strong> ${escapeHtml(donor.admin_notes)}</div>` : ''}
    </div>
  `;
  openModal('donorDetailsModal');
}

function openEditDonorModal(donor) {
  document.getElementById('edit_donor_id').value = donor.id;
  document.getElementById('edit_full_name').value = donor.full_name;
  document.getElementById('edit_age').value = donor.age;
  document.getElementById('edit_gender').value = donor.gender;
  document.getElementById('edit_email').value = donor.email;
  document.getElementById('edit_mobile').value = donor.mobile;
  document.getElementById('edit_address_city').value = donor.address_city;
  document.getElementById('edit_blood_group').value = donor.blood_group;
  document.getElementById('edit_organ_donated').value = donor.organ_donated;
  document.getElementById('edit_availability_status').value = donor.availability_status;
  document.getElementById('edit_verification_status').value = donor.verification_status;
  document.getElementById('edit_medical_notes').value = donor.medical_notes || '';
  document.getElementById('edit_admin_notes').value = donor.admin_notes || '';
  openModal('editDonorModal');
}

function confirmDeleteDonor(id, name) {
  document.getElementById('delete_donor_id').value = id;
  document.getElementById('delete_donor_name').textContent = '#' + id + ' ' + name;
  openModal('deleteDonorModal');
}

function openActionModal(type, id, name, currentStatus) {
  document.getElementById('modalDonorId').value = id;
  document.getElementById('actionTargetName').textContent = name;
  const select = document.getElementById('actionNewStatus');
  if (select && currentStatus) select.value = currentStatus;
  openModal('statusActionModal');
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Bulk Selection and Triage Handlers
function syncDonorBulkBar() {
  const checked = document.querySelectorAll('.donor-checkbox:checked');
  const bar = document.getElementById('bulkDonorActionBar');
  const countSpan = document.getElementById('donorSelectedCount');
  const selectAll = document.getElementById('selectAllDonors');
  const allBoxes = document.querySelectorAll('.donor-checkbox');

  if (countSpan) countSpan.textContent = checked.length;
  if (bar) bar.style.display = checked.length > 0 ? 'flex' : 'none';
  if (selectAll && allBoxes.length > 0) {
    selectAll.checked = (checked.length === allBoxes.length);
  }
}

function toggleAllDonors(checked) {
  document.querySelectorAll('.donor-checkbox').forEach(cb => cb.checked = checked);
  syncDonorBulkBar();
}

function clearDonorSelection() {
  document.querySelectorAll('.donor-checkbox').forEach(cb => cb.checked = false);
  const selectAll = document.getElementById('selectAllDonors');
  if (selectAll) selectAll.checked = false;
  syncDonorBulkBar();
}

function submitBulkDonor(action) {
  const checked = document.querySelectorAll('.donor-checkbox:checked');
  if (checked.length === 0) {
    alert('Please select at least one donor.');
    return;
  }

  let promptMsg = '';
  if (action === 'verify') promptMsg = `Approve and verify ${checked.length} selected donor(s)?`;
  else if (action === 'reject') promptMsg = `Reject ${checked.length} selected donor(s)?`;
  else if (action === 'delete') promptMsg = `Permanently delete ${checked.length} selected donor(s)? This action cannot be undone.`;

  if (confirm(promptMsg)) {
    document.getElementById('bulkDonorActionInput').value = action;
    document.getElementById('donorBulkForm').submit();
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
