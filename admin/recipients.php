<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Administrator Recipients Management Suite (Full CRUD & Exports)
 */

$page_title = "Manage Recipients";
$include_dashboard_css = true;
$include_dashboard_js = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();
$pdo = get_db_connection();

// Filter parameters
$search         = trim($_GET['search'] ?? '');
$urgency_filter = trim($_GET['urgency'] ?? '');
$blood_filter   = trim($_GET['blood'] ?? '');
$organ_filter   = trim($_GET['organ'] ?? '');
$status_filter  = trim($_GET['status'] ?? '');
$match_filter   = trim($_GET['match'] ?? '');

$query = "SELECT r.*,
                 m_app.id AS approved_match_id,
                 m_app.status AS approved_match_status,
                 m_app.compatibility_score AS approved_match_score,
                 m_app.donor_id AS approved_donor_id,
                 d_app.full_name AS approved_donor_name,
                 d_app.blood_group AS approved_donor_blood,
                 (SELECT COUNT(*) FROM matches WHERE recipient_id = r.id AND status = 'Approved') AS count_approved,
                 (SELECT COUNT(*) FROM matches WHERE recipient_id = r.id AND status IN ('Under Review', 'Contacted')) AS count_in_review,
                 (SELECT COUNT(*) FROM matches WHERE recipient_id = r.id AND status = 'Potential') AS count_potential
          FROM recipients r
          LEFT JOIN matches m_app ON m_app.recipient_id = r.id AND m_app.status = 'Approved'
          LEFT JOIN donors d_app ON m_app.donor_id = d_app.id
          WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (r.full_name LIKE ? OR r.email LIKE ? OR r.hospital_city LIKE ? OR r.mobile LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($urgency_filter !== '') {
    $query .= " AND r.urgency_level = ?";
    $params[] = $urgency_filter;
}
if ($blood_filter !== '') {
    $query .= " AND r.blood_group = ?";
    $params[] = $blood_filter;
}
if ($organ_filter !== '') {
    $query .= " AND r.organ_needed = ?";
    $params[] = $organ_filter;
}
if ($status_filter !== '') {
    $query .= " AND r.verification_status = ?";
    $params[] = $status_filter;
}
if ($match_filter === 'matched') {
    $query .= " AND (SELECT COUNT(*) FROM matches WHERE recipient_id = r.id AND status = 'Approved') > 0";
} elseif ($match_filter === 'in_review') {
    $query .= " AND (SELECT COUNT(*) FROM matches WHERE recipient_id = r.id AND status IN ('Under Review', 'Contacted')) > 0";
} elseif ($match_filter === 'potential') {
    $query .= " AND (SELECT COUNT(*) FROM matches WHERE recipient_id = r.id AND status = 'Potential') > 0";
} elseif ($match_filter === 'unmatched') {
    $query .= " AND (SELECT COUNT(*) FROM matches WHERE recipient_id = r.id AND status != 'Closed') = 0";
}

$query .= " ORDER BY FIELD(r.urgency_level, 'Critical', 'High', 'Medium', 'Low'), r.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$recipients = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
  
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="dashboard-main">
    
    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>Recipient Waitlist &amp; Management</h1>
        <p>Complete administrative controls: Add, edit, prioritize urgency, verify hospital records, and export recipients.</p>
      </div>
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <button type="button" class="btn btn-primary" onclick="openModal('addRecipientModal')">
          + Add New Recipient
        </button>
        <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=recipients&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline" title="Download Recipients CSV">
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
          <input type="text" name="search" id="tableSearch" class="form-control" placeholder="Search by name, hospital, city..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <!-- Urgency Filter -->
        <select name="urgency" id="filterUrgency" class="form-control" style="width: auto; min-width: 140px;">
          <option value="">All Urgencies</option>
          <?php foreach ($GLOBALS['URGENCY_LEVELS'] as $lvl => $info): ?>
            <option value="<?php echo $lvl; ?>" <?php echo ($urgency_filter === $lvl) ? 'selected' : ''; ?>><?php echo $lvl; ?></option>
          <?php endforeach; ?>
        </select>

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
          <option value="unmatched" <?php echo ($match_filter === 'unmatched') ? 'selected' : ''; ?>>⚪ Waiting / Unmatched</option>
        </select>

        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="<?php echo BASE_URL; ?>/admin/recipients.php" class="btn btn-outline btn-sm" id="clearFilters">Reset</a>
      </div>

      <div style="font-size: 0.85rem; color: var(--text-muted);">
        Found <strong><?php echo count($recipients); ?></strong> registered recipients
      </div>
    </form>

    <!-- Recipients Table Card with Multi-Select Bulk Triage -->
    <form id="recipBulkForm" action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="bulk_recipient_action">
      <input type="hidden" name="bulk_action" id="bulkRecipActionInput" value="">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <!-- Bulk Action Bar -->
      <div id="bulkRecipActionBar" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.75rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div style="font-size: 0.9rem; color: #1e40af; font-weight: 600;">
          <span id="recipSelectedCount">0</span> recipient patient profile(s) selected:
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
          <button type="button" class="btn btn-primary btn-sm" onclick="submitBulkRecip('verify')">
            ✓ Bulk Verify
          </button>
          <button type="button" class="btn btn-outline btn-sm" onclick="submitBulkRecip('reject')">
            ✕ Bulk Reject
          </button>
          <button type="button" class="btn btn-outline-danger btn-sm" onclick="submitBulkRecip('delete')">
            🗑️ Bulk Delete
          </button>
          <button type="button" class="btn btn-outline btn-sm" onclick="clearRecipSelection()">
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
                  <input type="checkbox" id="selectAllRecips" onclick="toggleAllRecips(this.checked)" title="Select all recipients on this page">
                </th>
                <th>ID</th>
                <th>Patient Name &amp; Hospital</th>
                <th>Age/Gender</th>
                <th>Blood</th>
                <th>Organ Needed</th>
                <th>Urgency Priority</th>
                <th>Status</th>
                <th>Match State</th>
                <th>Registered</th>
                <th style="text-align: right;">Administrative Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recipients)): ?>
                <tr>
                  <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                    No recipient records found matching your filters.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($recipients as $r): ?>
                  <?php
                    $matchStateVal = !empty($r['approved_match_id']) ? 'matched' : (!empty($r['count_in_review']) ? 'in_review' : (!empty($r['count_potential']) ? 'potential' : 'unmatched'));
                  ?>
                  <tr id="recipient-row-<?php echo $r['id']; ?>" data-recip-id="<?php echo $r['id']; ?>" data-searchable="true" data-blood="<?php echo htmlspecialchars($r['blood_group']); ?>" data-organ="<?php echo htmlspecialchars($r['organ_needed']); ?>" data-urgency="<?php echo htmlspecialchars($r['urgency_level']); ?>" data-status="<?php echo htmlspecialchars($r['verification_status']); ?>" data-match="<?php echo $matchStateVal; ?>">
                    <td style="text-align: center;">
                      <input type="checkbox" name="selected_ids[]" value="<?php echo $r['id']; ?>" class="recip-checkbox" onchange="syncRecipBulkBar()">
                    </td>
                    <td><strong>#<?php echo $r['id']; ?></strong></td>
                    <td>
                      <div class="user-cell">
                        <span class="user-cell-name"><?php echo htmlspecialchars($r['full_name']); ?></span>
                        <span class="user-cell-meta"><?php echo htmlspecialchars($r['hospital_city']); ?> &bull; <?php echo htmlspecialchars($r['mobile']); ?></span>
                      </div>
                    </td>
                    <td><?php echo $r['age']; ?>y / <?php echo htmlspecialchars($r['gender']); ?></td>
                    <td>
                      <span class="badge" style="background: #fee2e2; color: #b91c1c; font-weight: 700; font-size: 0.85rem;">
                        <?php echo htmlspecialchars($r['blood_group']); ?>
                      </span>
                    </td>
                    <td>
                      <strong style="color: var(--primary);"><?php echo htmlspecialchars($r['organ_needed']); ?></strong>
                    </td>
                    <td>
                      <?php 
                        $urgBadge = match($r['urgency_level']) {
                          'Critical' => 'badge-critical',
                          'High'     => 'badge-high',
                          'Medium'   => 'badge-medium',
                          default    => 'badge-low'
                        };
                      ?>
                      <span class="badge <?php echo $urgBadge; ?>" style="font-weight: 600;">
                        <?php echo htmlspecialchars($r['urgency_level']); ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge badge-<?php echo strtolower($r['verification_status']); ?>">
                        <?php echo htmlspecialchars($r['verification_status']); ?>
                      </span>
                    </td>
                    <td class="match-state-cell">
                      <?php if (!empty($r['approved_match_id'])): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/match_dossier.php?id=<?php echo $r['approved_match_id']; ?>" target="_blank" class="badge badge-verified" style="font-size: 0.74rem; display: inline-flex; align-items: center; gap: 0.25rem;" title="Transplant Allocation Approved with Donor #<?php echo $r['approved_donor_id']; ?> (<?php echo htmlspecialchars($r['approved_donor_name'] ?? ''); ?>)">
                          🎉 Matched #<?php echo $r['approved_donor_id']; ?> (Approved)
                        </a>
                      <?php elseif (!empty($r['count_in_review'])): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/matches.php?search=<?php echo urlencode($r['full_name']); ?>" class="badge badge-pending" style="font-size: 0.74rem;" title="<?php echo $r['count_in_review']; ?> match(es) under review or contacted">
                          ⏳ In Review (<?php echo $r['count_in_review']; ?>)
                        </a>
                      <?php elseif (!empty($r['count_potential'])): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/matches.php?search=<?php echo urlencode($r['full_name']); ?>" class="badge badge-low" style="font-size: 0.74rem;" title="<?php echo $r['count_potential']; ?> candidate donor(s) identified">
                          🔍 <?php echo $r['count_potential']; ?> Potential
                        </a>
                      <?php else: ?>
                        <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 0.74rem;">Waiting</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                      <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                        <!-- View Dossier -->
                        <button type="button" class="btn btn-outline btn-sm" onclick="viewRecipientDetails(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>)" title="View medical requirement details">
                          👁️ View
                        </button>

                        <!-- Edit Recipient -->
                        <button type="button" class="btn btn-outline btn-sm" onclick="openEditRecipientModal(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit recipient details">
                          ✏️ Edit
                        </button>

                        <!-- Update Verification -->
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="openActionModal('recipient', <?php echo $r['id']; ?>, '<?php echo htmlspecialchars(addslashes($r['full_name'])); ?>', '<?php echo $r['verification_status']; ?>')">
                          ⚖️ Status
                        </button>

                        <!-- Delete Recipient -->
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDeleteRecipient(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars(addslashes($r['full_name'])); ?>')" title="Permanently delete recipient">
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
<!-- MODAL: ADD NEW RECIPIENT (DIRECT ADMIN CREATION)         -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="addRecipientModal" role="dialog" aria-modal="true" aria-labelledby="addRecipientTitle">
  <div class="modal-content" style="max-width: 650px;">
    <div class="modal-header">
      <h3 id="addRecipientTitle">Direct Add: Organ Recipient Request</h3>
      <button type="button" class="modal-close" onclick="closeModal('addRecipientModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="create_recipient">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group col-span-2">
            <label class="form-label" for="add_recip_name">Full Patient Name <span class="required">*</span></label>
            <input type="text" id="add_recip_name" name="full_name" class="form-control" placeholder="e.g. Meera Nambiar" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_recip_age">Age <span class="required">*</span></label>
            <input type="number" id="add_recip_age" name="age" min="1" max="100" class="form-control" value="35" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_recip_gender">Gender <span class="required">*</span></label>
            <select id="add_recip_gender" name="gender" class="form-control" required>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_recip_email">Contact Email <span class="required">*</span></label>
            <input type="email" id="add_recip_email" name="email" class="form-control" placeholder="patient@domain.com" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_recip_mobile">Mobile Number <span class="required">*</span></label>
            <input type="tel" id="add_recip_mobile" name="mobile" class="form-control" placeholder="9876543210" required>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="add_recip_hospital">Hospital &amp; City Location <span class="required">*</span></label>
            <input type="text" id="add_recip_hospital" name="hospital_city" class="form-control" placeholder="e.g. Aster Medcity, Kochi, Kerala" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_recip_blood">Blood Group <span class="required">*</span></label>
            <select id="add_recip_blood" name="blood_group" class="form-control" required>
              <?php foreach ($GLOBALS['BLOOD_GROUPS'] as $bg): ?>
                <option value="<?php echo $bg; ?>"><?php echo $bg; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_recip_organ">Organ Required <span class="required">*</span></label>
            <select id="add_recip_organ" name="organ_needed" class="form-control" required>
              <?php foreach ($GLOBALS['SUPPORTED_ORGANS'] as $org): ?>
                <option value="<?php echo $org; ?>"><?php echo $org; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_recip_urgency">Clinical Urgency Level <span class="required">*</span></label>
            <select id="add_recip_urgency" name="urgency_level" class="form-control" required>
              <option value="Critical">Critical (Immediate Priority)</option>
              <option value="High" selected>High Priority</option>
              <option value="Medium">Medium Priority</option>
              <option value="Low">Low Priority</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_recip_status">Verification Status <span class="required">*</span></label>
            <select id="add_recip_status" name="verification_status" class="form-control" required>
              <option value="Verified" selected>Verified (Active in Engine)</option>
              <option value="Pending">Pending Review</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="add_recip_notes">Medical Diagnosis &amp; Hospital Details</label>
            <textarea id="add_recip_notes" name="medical_notes" class="form-control" rows="2" placeholder="Clinical history, attending physician, eGFR/MELD scores..."></textarea>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="add_recip_admin_notes">Admin Verification Notes</label>
            <textarea id="add_recip_admin_notes" name="admin_notes" class="form-control" rows="2" placeholder="Administrative clearance notes..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addRecipientModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Recipient Request</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: EDIT RECIPIENT DETAILS                            -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="editRecipientModal" role="dialog" aria-modal="true" aria-labelledby="editRecipientTitle">
  <div class="modal-content" style="max-width: 650px;">
    <div class="modal-header">
      <h3 id="editRecipientTitle">Edit Recipient Record</h3>
      <button type="button" class="modal-close" onclick="closeModal('editRecipientModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="edit_recipient">
      <input type="hidden" name="recipient_id" id="edit_recip_id" value="">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group col-span-2">
            <label class="form-label" for="edit_recip_name">Full Patient Name <span class="required">*</span></label>
            <input type="text" id="edit_recip_name" name="full_name" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_recip_age">Age <span class="required">*</span></label>
            <input type="number" id="edit_recip_age" name="age" min="1" max="100" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_recip_gender">Gender <span class="required">*</span></label>
            <select id="edit_recip_gender" name="gender" class="form-control" required>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_recip_email">Contact Email <span class="required">*</span></label>
            <input type="email" id="edit_recip_email" name="email" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_recip_mobile">Mobile Number <span class="required">*</span></label>
            <input type="tel" id="edit_recip_mobile" name="mobile" class="form-control" required>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="edit_recip_hospital">Hospital &amp; City <span class="required">*</span></label>
            <input type="text" id="edit_recip_hospital" name="hospital_city" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_recip_blood">Blood Group <span class="required">*</span></label>
            <select id="edit_recip_blood" name="blood_group" class="form-control" required>
              <?php foreach ($GLOBALS['BLOOD_GROUPS'] as $bg): ?>
                <option value="<?php echo $bg; ?>"><?php echo $bg; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_recip_organ">Organ Required <span class="required">*</span></label>
            <select id="edit_recip_organ" name="organ_needed" class="form-control" required>
              <?php foreach ($GLOBALS['SUPPORTED_ORGANS'] as $org): ?>
                <option value="<?php echo $org; ?>"><?php echo $org; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_recip_urgency">Urgency Level <span class="required">*</span></label>
            <select id="edit_recip_urgency" name="urgency_level" class="form-control" required>
              <option value="Critical">Critical (Immediate Priority)</option>
              <option value="High">High Priority</option>
              <option value="Medium">Medium Priority</option>
              <option value="Low">Low Priority</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_recip_status">Verification Status <span class="required">*</span></label>
            <select id="edit_recip_status" name="verification_status" class="form-control" required>
              <option value="Verified">Verified</option>
              <option value="Pending">Pending</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="edit_recip_notes">Medical Diagnosis &amp; Hospital Details</label>
            <textarea id="edit_recip_notes" name="medical_notes" class="form-control" rows="2"></textarea>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="edit_recip_admin_notes">Admin Clinical Remarks</label>
            <textarea id="edit_recip_admin_notes" name="admin_notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editRecipientModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: DELETE RECIPIENT CONFIRMATION                     -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="deleteRecipientModal" role="dialog" aria-modal="true" aria-labelledby="deleteRecipientTitle">
  <div class="modal-content" style="max-width: 450px;">
    <div class="modal-header" style="border-bottom-color: #fee2e2;">
      <h3 id="deleteRecipientTitle" style="color: var(--danger);">🗑️ Delete Recipient Record</h3>
      <button type="button" class="modal-close" onclick="closeModal('deleteRecipientModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="delete_recipient">
      <input type="hidden" name="recipient_id" id="delete_recip_id" value="">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <p style="font-size: 0.95rem; line-height: 1.5; color: var(--text-main);">
          Are you sure you want to permanently delete recipient:
        </p>
        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin: 0.75rem 0;">
          <strong id="delete_recip_name" style="color: #991b1b; font-size: 1.05rem;">--</strong>
        </div>
        <p style="font-size: 0.82rem; color: var(--text-muted);">
          ⚠️ This will remove the recipient from the waitlist, delete linked credentials, and dismiss all preliminary matches. This action cannot be undone.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('deleteRecipientModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, Permanently Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: VIEW FULL DOSSIER                                 -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="recipientDetailsModal" role="dialog" aria-modal="true" aria-labelledby="recipDetailTitle">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="recipDetailTitle">Recipient Requirement Dossier</h3>
      <button type="button" class="modal-close" onclick="closeModal('recipientDetailsModal')" aria-label="Close modal">&times;</button>
    </div>
    <div class="modal-body" id="recipDetailBody">
      <!-- Injected via JavaScript -->
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('recipientDetailsModal')">Close</button>
    </div>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: UPDATE STATUS                                     -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="statusActionModal" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="statusModalTitle">Update Recipient Verification</h3>
      <button type="button" class="modal-close" onclick="closeModal('statusActionModal')" aria-label="Close modal">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="update_recipient_status">
        <input type="hidden" name="recipient_id" id="modalRecipientId" value="">
        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

        <div style="margin-bottom: 1.25rem;">
          <span style="font-size: 0.85rem; color: var(--text-muted); display: block;">Recipient:</span>
          <strong id="actionTargetName" style="font-size: 1.15rem; color: var(--text-main);">--</strong>
        </div>

        <div class="form-group">
          <label class="form-label" for="actionNewStatus">Verification Status <span class="required">*</span></label>
          <select name="new_status" id="actionNewStatus" class="form-control" required>
            <option value="Verified">Verified (Active in Match Engine)</option>
            <option value="Pending">Pending (Under hospital review)</option>
            <option value="Rejected">Rejected (Ineligible / Revoked)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="actionAdminNotes">Reviewer Clinical Notes</label>
          <textarea name="admin_notes" id="actionAdminNotes" class="form-control" rows="3" placeholder="Hospital verification notes, clinical clearance, or deferral reasons..."></textarea>
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
function viewRecipientDetails(recip) {
  const body = document.getElementById('recipDetailBody');
  let matchSection = '';
  if (recip.approved_match_id) {
    matchSection = `
      <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
          <div style="font-weight: 700; color: #065f46; font-size: 1rem; display: flex; align-items: center; gap: 0.4rem;">
            <span>🎉</span> Transplant Allocation Approved
          </div>
          <a href="<?php echo BASE_URL; ?>/admin/match_dossier.php?id=${recip.approved_match_id}" target="_blank" class="btn btn-sm btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">
            Open Match Dossier &rarr;
          </a>
        </div>
        <div style="font-size: 0.88rem; color: #047857; line-height: 1.5;">
          Matched Donor: <strong>#${recip.approved_donor_id} (${escapeHtml(recip.approved_donor_name || 'Anonymous Donor')})</strong> &bull; Blood: <strong>${escapeHtml(recip.approved_donor_blood || '--')}</strong><br>
          Compatibility Score: <strong>${recip.approved_match_score}%</strong> &bull; Organ: <strong>${escapeHtml(recip.organ_needed)}</strong>
        </div>
      </div>
    `;
  } else if (parseInt(recip.count_in_review || 0) > 0) {
    matchSection = `
      <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 0.85rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; font-size: 0.88rem; color: #92400e; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>⏳ <strong>${recip.count_in_review} match(es) under active clinical review.</strong> Outreach in progress.</div>
        <a href="<?php echo BASE_URL; ?>/admin/matches.php?search=${encodeURIComponent(recip.full_name)}" class="btn btn-sm btn-outline" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">Review Matches</a>
      </div>
    `;
  } else if (parseInt(recip.count_potential || 0) > 0) {
    matchSection = `
      <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.85rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; font-size: 0.88rem; color: #1e40af; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>🔍 <strong>${recip.count_potential} compatible donor candidate(s)</strong> identified by matching engine.</div>
        <a href="<?php echo BASE_URL; ?>/admin/matches.php?search=${encodeURIComponent(recip.full_name)}" class="btn btn-sm btn-outline" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">View Candidates</a>
      </div>
    `;
  }

  body.innerHTML = `
    ${matchSection}
    <div class="profile-info-grid" style="margin-bottom: 1.5rem;">
      <div class="profile-item"><span class="profile-item-label">Full Name</span><span class="profile-item-value">${escapeHtml(recip.full_name)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Age &amp; Gender</span><span class="profile-item-value">${recip.age} yrs, ${escapeHtml(recip.gender)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Blood Group</span><span class="profile-item-value" style="color: var(--danger); font-size: 1.2rem;">${escapeHtml(recip.blood_group)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Needed Organ</span><span class="profile-item-value" style="color: var(--primary); font-size: 1.2rem;">${escapeHtml(recip.organ_needed)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Attending Hospital / City</span><span class="profile-item-value">${escapeHtml(recip.hospital_city)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Clinical Urgency</span><span class="profile-item-value"><span class="badge badge-${recip.urgency_level.toLowerCase()}">${escapeHtml(recip.urgency_level)}</span></span></div>
      <div class="profile-item"><span class="profile-item-label">Contact Email</span><span class="profile-item-value">${escapeHtml(recip.email)}</span></div>
      <div class="profile-item"><span class="profile-item-label">Mobile Number</span><span class="profile-item-value">${escapeHtml(recip.mobile)}</span></div>
    </div>

    <div style="background: var(--bg-subtle); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; border: 1px solid var(--border);">
      <strong>Medical Diagnosis &amp; Hospital Clinical Details:</strong>
      <p style="font-size: 0.9rem; margin-top: 0.35rem; color: #334155;">
        ${recip.medical_notes ? escapeHtml(recip.medical_notes) : '<em>No additional clinical diagnoses supplied.</em>'}
      </p>
    </div>

    <div style="font-size: 0.85rem; color: var(--text-muted);">
      <div><strong>Legal Consent:</strong> ${recip.consent_given == 1 ? '✅ Voluntary Registration Documented' : '❌ Consent Missing'}</div>
      <div><strong>Verification Status:</strong> <span class="badge badge-${recip.verification_status.toLowerCase()}">${recip.verification_status}</span></div>
      <div><strong>Registered At:</strong> ${recip.created_at}</div>
      ${recip.admin_notes ? `<div style="margin-top: 0.5rem; color: var(--primary);"><strong>Admin Notes:</strong> ${escapeHtml(recip.admin_notes)}</div>` : ''}
    </div>
  `;
  openModal('recipientDetailsModal');
}

function openEditRecipientModal(recip) {
  document.getElementById('edit_recip_id').value = recip.id;
  document.getElementById('edit_recip_name').value = recip.full_name;
  document.getElementById('edit_recip_age').value = recip.age;
  document.getElementById('edit_recip_gender').value = recip.gender;
  document.getElementById('edit_recip_email').value = recip.email;
  document.getElementById('edit_recip_mobile').value = recip.mobile;
  document.getElementById('edit_recip_hospital').value = recip.hospital_city;
  document.getElementById('edit_recip_blood').value = recip.blood_group;
  document.getElementById('edit_recip_organ').value = recip.organ_needed;
  document.getElementById('edit_recip_urgency').value = recip.urgency_level;
  document.getElementById('edit_recip_status').value = recip.verification_status;
  document.getElementById('edit_recip_notes').value = recip.medical_notes || '';
  document.getElementById('edit_recip_admin_notes').value = recip.admin_notes || '';
  openModal('editRecipientModal');
}

function confirmDeleteRecipient(id, name) {
  document.getElementById('delete_recip_id').value = id;
  document.getElementById('delete_recip_name').textContent = '#' + id + ' ' + name;
  openModal('deleteRecipientModal');
}

function openActionModal(type, id, name, currentStatus) {
  document.getElementById('modalRecipientId').value = id;
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
function syncRecipBulkBar() {
  const checked = document.querySelectorAll('.recip-checkbox:checked');
  const bar = document.getElementById('bulkRecipActionBar');
  const countSpan = document.getElementById('recipSelectedCount');
  const selectAll = document.getElementById('selectAllRecips');
  const allBoxes = document.querySelectorAll('.recip-checkbox');

  if (countSpan) countSpan.textContent = checked.length;
  if (bar) bar.style.display = checked.length > 0 ? 'flex' : 'none';
  if (selectAll && allBoxes.length > 0) {
    selectAll.checked = (checked.length === allBoxes.length);
  }
}

function toggleAllRecips(checked) {
  document.querySelectorAll('.recip-checkbox').forEach(cb => cb.checked = checked);
  syncRecipBulkBar();
}

function clearRecipSelection() {
  document.querySelectorAll('.recip-checkbox').forEach(cb => cb.checked = false);
  const selectAll = document.getElementById('selectAllRecips');
  if (selectAll) selectAll.checked = false;
  syncRecipBulkBar();
}

function submitBulkRecip(action) {
  const checked = document.querySelectorAll('.recip-checkbox:checked');
  if (checked.length === 0) {
    alert('Please select at least one recipient.');
    return;
  }

  let promptMsg = '';
  if (action === 'verify') promptMsg = `Approve and verify ${checked.length} selected recipient(s)?`;
  else if (action === 'reject') promptMsg = `Reject ${checked.length} selected recipient(s)?`;
  else if (action === 'delete') promptMsg = `Permanently delete ${checked.length} selected recipient(s)? This action cannot be undone.`;

  if (confirm(promptMsg)) {
    document.getElementById('bulkRecipActionInput').value = action;
    document.getElementById('recipBulkForm').submit();
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
