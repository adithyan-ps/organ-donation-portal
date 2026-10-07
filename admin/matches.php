<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Administrator Matching Engine & Decision Console
 * Complete controls: Automatic Matching, Manual Pairing Overrides, Status Updates, Deletion, and CSV Exports
 */

$page_title = "Matching Engine";
$include_dashboard_css = true;
$include_dashboard_js = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/matching_engine.php';

require_admin();
$pdo = get_db_connection();

// Filter parameters
$filter_organ   = trim($_GET['organ'] ?? '');
$filter_urgency = trim($_GET['urgency'] ?? '');
$filter_status  = trim($_GET['status'] ?? '');

$filters = [];
if ($filter_organ !== '')   $filters['organ'] = $filter_organ;
if ($filter_urgency !== '') $filters['urgency'] = $filter_urgency;
if ($filter_status !== '')  $filters['status'] = $filter_status;

// Execute matching algorithm
$matches = run_preliminary_matching($pdo, $filters);

// Also fetch verified donors and recipients for manual matching modal
$verifiedDonors = $pdo->query("SELECT id, full_name, blood_group, organ_donated, address_city FROM donors WHERE verification_status = 'Verified' ORDER BY full_name ASC")->fetchAll();
$verifiedRecipients = $pdo->query("SELECT id, full_name, blood_group, organ_needed, urgency_level, hospital_city FROM recipients WHERE verification_status = 'Verified' ORDER BY full_name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
  
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="dashboard-main">
    
    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>Preliminary Matching Engine</h1>
        <p>100-Point immunohematological &amp; clinical urgency scoring algorithm. Manual pairing overrides and status reviews.</p>
      </div>
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <!-- Run Match Engine Form -->
        <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST" style="margin: 0;">
          <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
          <input type="hidden" name="action" value="recalculate_matches">
          <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
          <button type="submit" class="btn btn-primary" title="Trigger fresh evaluation of all verified records">
            ⚡ Run Match Engine Now
          </button>
        </form>

        <!-- Manual Match Modal Trigger -->
        <button type="button" class="btn btn-outline-primary" onclick="openModal('manualMatchModal')">
          + Manual Match Pair
        </button>

        <!-- Export Matches CSV -->
        <a href="<?php echo BASE_URL; ?>/admin/update_status.php?action=export_csv&type=matches&csrf_token=<?php echo csrf_token(); ?>" class="btn btn-outline" title="Download Matches CSV">
          📥 Export CSV
        </a>
      </div>
    </div>

    <!-- Prominent Medical Disclaimer Banner -->
    <div class="disclaimer-banner">
      <div class="icon">⚠️</div>
      <div>
        <h4>Mandatory Clinical &amp; Legal Protocol Notice</h4>
        <p>
          <strong>Potential matches shown by this portal are preliminary digital comparisons only.</strong> Medical compatibility testing (HLA tissue typing, cross-matching, panel reactive antibody screening, viral markers), legal clearance, allocation rules, and final transplant decisions must be completed by authorized healthcare professionals and transplant boards.
        </p>
      </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="filter-bar">
      <div class="filter-group">
        <!-- Organ Filter -->
        <select name="organ" id="filterOrgan" class="form-control" style="width: auto; min-width: 150px;">
          <option value="">All Organs</option>
          <?php foreach ($GLOBALS['SUPPORTED_ORGANS'] as $org): ?>
            <option value="<?php echo $org; ?>" <?php echo ($filter_organ === $org) ? 'selected' : ''; ?>><?php echo $org; ?></option>
          <?php endforeach; ?>
        </select>

        <!-- Urgency Filter -->
        <select name="urgency" id="filterUrgency" class="form-control" style="width: auto; min-width: 150px;">
          <option value="">All Urgency Levels</option>
          <?php foreach ($GLOBALS['URGENCY_LEVELS'] as $key => $u): ?>
            <option value="<?php echo $key; ?>" <?php echo ($filter_urgency === $key) ? 'selected' : ''; ?>><?php echo $key; ?></option>
          <?php endforeach; ?>
        </select>

        <!-- Match Status Filter -->
        <select name="status" id="filterStatus" class="form-control" style="width: auto; min-width: 150px;">
          <option value="">All Match Statuses</option>
          <?php foreach ($GLOBALS['MATCH_STATUSES'] as $key => $ms): ?>
            <option value="<?php echo $key; ?>" <?php echo ($filter_status === $key) ? 'selected' : ''; ?>><?php echo $ms['label']; ?></option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary btn-sm">Filter Matches</button>
        <a href="<?php echo BASE_URL; ?>/admin/matches.php" class="btn btn-outline btn-sm">Reset</a>
      </div>

      <div style="font-size: 0.85rem; color: var(--text-muted);">
        Priority Sorting: <strong>Critical &rarr; High &rarr; Score DESC</strong> (<?php echo count($matches); ?> matches found)
      </div>
    </form>

    <!-- Matches Table Card -->
    <div class="table-card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Matched Organ</th>
              <th>Recipient (Need)</th>
              <th>Donor (Pledge)</th>
              <th>Blood Compatibility</th>
              <th>Score</th>
              <th>Match Status</th>
              <th>Date Flagged</th>
              <th style="text-align: right;">Clinical Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($matches)): ?>
              <tr>
                <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 3.5rem;">
                  <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔍</div>
                  <strong>No preliminary matches found matching the selected criteria.</strong>
                  <p style="font-size: 0.85rem; margin-top: 0.25rem;">Ensure that both donors and recipients are set to <em>Verified</em> status and donor availability is <em>Available</em>.</p>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($matches as $m): ?>
                <tr id="match-row-<?php echo $m['match_id']; ?>" data-match-id="<?php echo $m['match_id']; ?>" data-searchable="true" data-organ="<?php echo htmlspecialchars($m['organ']); ?>" data-status="<?php echo htmlspecialchars($m['status']); ?>">
                  <td><strong>#<?php echo $m['match_id']; ?></strong></td>
                  <td>
                    <strong style="color: var(--primary); font-size: 0.95rem;"><?php echo htmlspecialchars($m['organ']); ?></strong>
                  </td>
                  <td>
                    <div class="user-cell">
                      <span class="user-cell-name">
                        <?php echo htmlspecialchars($m['recipient_name']); ?>
                        <span class="badge <?php echo ($m['recipient_urgency'] === 'Critical') ? 'badge-critical' : (($m['recipient_urgency'] === 'High') ? 'badge-high' : 'badge-medium'); ?>" style="font-size: 0.7rem; margin-left: 0.25rem;">
                          <?php echo htmlspecialchars($m['recipient_urgency']); ?>
                        </span>
                      </span>
                      <span class="user-cell-meta">Blood: <strong><?php echo htmlspecialchars($m['recipient_blood']); ?></strong> &bull; <?php echo htmlspecialchars($m['recipient_hospital']); ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="user-cell">
                      <span class="user-cell-name"><?php echo htmlspecialchars($m['donor_name']); ?></span>
                      <span class="user-cell-meta">Blood: <strong><?php echo htmlspecialchars($m['donor_blood']); ?></strong> &bull; <?php echo htmlspecialchars($m['donor_city']); ?></span>
                    </div>
                  </td>
                  <td>
                    <div style="font-size: 0.85rem;">
                      <strong><?php echo htmlspecialchars($m['donor_blood']); ?> &rarr; <?php echo htmlspecialchars($m['recipient_blood']); ?></strong>
                      <span class="badge <?php echo ($m['blood_match_type'] === 'Identical Match') ? 'badge-verified' : 'badge-low'; ?>" style="font-size: 0.72rem; display: block; margin-top: 0.2rem; width: fit-content;">
                        <?php echo htmlspecialchars($m['blood_match_type']); ?>
                      </span>
                    </div>
                  </td>
                  <td>
                    <?php 
                      $sc = $m['score'];
                      $scoreClass = ($sc >= 95) ? 'match-score-100' : (($sc >= 90) ? 'match-score-90' : (($sc >= 80) ? 'match-score-80' : 'match-score-low'));
                    ?>
                    <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                      <span class="match-score-badge <?php echo $scoreClass; ?>">
                        <?php echo $sc; ?>%
                      </span>
                      <a href="javascript:void(0)" class="breakdown-toggle" onclick="showScoreBreakdown(<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8'); ?>)">
                        View Formula
                      </a>
                    </div>
                  </td>
                  <td>
                    <?php
                      $statusInfo = $GLOBALS['MATCH_STATUSES'][$m['status']] ?? ['label' => $m['status'], 'badge' => 'badge-potential'];
                    ?>
                    <span class="badge <?php echo $statusInfo['badge']; ?>" style="font-size: 0.8rem;">
                      <?php echo htmlspecialchars($statusInfo['label']); ?>
                    </span>
                  </td>
                  <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                    <?php echo date('M d, Y', strtotime($m['matched_at'])); ?>
                  </td>
                  <td style="text-align: right; white-space: nowrap;">
                    <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                      <!-- Clinical Match Dossier Sheet -->
                      <a href="<?php echo BASE_URL; ?>/admin/match_dossier.php?id=<?php echo $m['match_id']; ?>" target="_blank" class="btn btn-outline btn-sm" title="View &amp; Print Official Clinical Transplant Dossier">
                        🖨️ Dossier
                      </a>

                      <!-- Update Match Status Button -->
                      <button type="button" class="btn btn-outline-primary btn-sm" onclick="openMatchStatusModal(<?php echo $m['match_id']; ?>, '<?php echo htmlspecialchars(addslashes($m['donor_name'])); ?>', '<?php echo htmlspecialchars(addslashes($m['recipient_name'])); ?>', '<?php echo htmlspecialchars($m['organ']); ?>', '<?php echo htmlspecialchars($m['status']); ?>', '<?php echo htmlspecialchars(addslashes($m['notes'])); ?>')">
                        Update Status
                      </button>

                      <!-- Delete Match Button -->
                      <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDeleteMatch(<?php echo $m['match_id']; ?>, '<?php echo htmlspecialchars(addslashes($m['donor_name'])); ?>', '<?php echo htmlspecialchars(addslashes($m['recipient_name'])); ?>')" title="Dismiss this match">
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

  </main>

</div>

<!-- ======================================================= -->
<!-- MODAL: MANUAL MATCH PAIRING OVERRIDE                    -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="manualMatchModal" role="dialog" aria-modal="true" aria-labelledby="manualMatchTitle">
  <div class="modal-content" style="max-width: 650px;">
    <div class="modal-header">
      <h3 id="manualMatchTitle">Manual Match Pairing Override</h3>
      <button type="button" class="modal-close" onclick="closeModal('manualMatchModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="create_manual_match">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
          Select any verified donor and verified recipient to link them as a potential match. The scoring engine will calculate preliminary immunohematological points automatically.
        </p>

        <div class="form-grid">
          <div class="form-group col-span-2">
            <label class="form-label" for="manual_donor_id">Select Verified Donor <span class="required">*</span></label>
            <select name="donor_id" id="manual_donor_id" class="form-control" required>
              <option value="">-- Choose Verified Donor --</option>
              <?php foreach ($verifiedDonors as $vd): ?>
                <option value="<?php echo $vd['id']; ?>">
                  #<?php echo $vd['id']; ?> <?php echo htmlspecialchars($vd['full_name']); ?> (<?php echo $vd['blood_group']; ?>, Organ: <?php echo $vd['organ_donated']; ?>, <?php echo htmlspecialchars($vd['address_city']); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label" for="manual_recip_id">Select Verified Recipient <span class="required">*</span></label>
            <select name="recipient_id" id="manual_recip_id" class="form-control" required>
              <option value="">-- Choose Verified Recipient --</option>
              <?php foreach ($verifiedRecipients as $vr): ?>
                <option value="<?php echo $vr['id']; ?>">
                  #<?php echo $vr['id']; ?> <?php echo htmlspecialchars($vr['full_name']); ?> (<?php echo $vr['blood_group']; ?>, Need: <?php echo $vr['organ_needed']; ?>, <?php echo $vr['urgency_level']; ?>, <?php echo htmlspecialchars($vr['hospital_city']); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="manual_status">Initial Review Status</label>
            <select name="status" id="manual_status" class="form-control">
              <option value="Potential">Potential</option>
              <option value="Under Review" selected>Under Review</option>
              <option value="Contacted">Contacted</option>
              <option value="Approved">Approved</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="manual_notes">Clinical Override Reason / Notes</label>
            <input type="text" name="notes" id="manual_notes" class="form-control" placeholder="Special authorization / emergency allocation...">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('manualMatchModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Establish Match Pair</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: DELETE / DISMISS MATCH                            -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="deleteMatchModal" role="dialog" aria-modal="true" aria-labelledby="deleteMatchTitle">
  <div class="modal-content" style="max-width: 450px;">
    <div class="modal-header" style="border-bottom-color: #fee2e2;">
      <h3 id="deleteMatchTitle" style="color: var(--danger);">🗑️ Dismiss Match Record</h3>
      <button type="button" class="modal-close" onclick="closeModal('deleteMatchModal')" aria-label="Close">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="delete_match">
      <input type="hidden" name="match_id" id="delete_match_id" value="">
      <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

      <div class="modal-body">
        <p style="font-size: 0.95rem; line-height: 1.5; color: var(--text-main);">
          Are you sure you want to remove this preliminary match record?
        </p>
        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin: 0.75rem 0;">
          <div id="delete_match_details" style="font-size: 0.95rem; color: #991b1b; font-weight: 600;">--</div>
        </div>
        <p style="font-size: 0.82rem; color: var(--text-muted);">
          This unlinks the donor and recipient pair. The donor and recipient profiles remain intact.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('deleteMatchModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, Dismiss Match</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: UPDATE MATCH STATUS                               -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="matchStatusModal" role="dialog" aria-modal="true" aria-labelledby="matchStatusTitle">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="matchStatusTitle">Update Clinical Match Status</h3>
      <button type="button" class="modal-close" onclick="closeModal('matchStatusModal')" aria-label="Close modal">&times;</button>
    </div>
    <form action="<?php echo BASE_URL; ?>/admin/update_status.php" method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="action" value="update_match_status">
        <input type="hidden" name="match_id" id="modalMatchId" value="">
        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

        <div style="background: var(--bg-subtle); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; font-size: 0.9rem;">
          <div><strong>Organ:</strong> <span id="modalMatchOrgan" style="color: var(--primary);">--</span></div>
          <div style="margin-top: 0.25rem;"><strong>Pair:</strong> <span id="modalMatchDonor">--</span> &rarr; <span id="modalMatchRecipient">--</span></div>
        </div>

        <div class="form-group">
          <label class="form-label" for="matchNewStatus">Match Review Status <span class="required">*</span></label>
          <select name="new_status" id="matchNewStatus" class="form-control" required>
            <?php foreach ($GLOBALS['MATCH_STATUSES'] as $key => $info): ?>
              <option value="<?php echo $key; ?>"><?php echo $info['label']; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="matchNotes">Clinical / Administrative Notes</label>
          <textarea name="notes" id="matchNotes" class="form-control" rows="3" placeholder="Document HLA crossmatch requests, patient outreach, or transplant hospital dispatch..."></textarea>
        </div>

        <div class="form-group" style="background: #f8fafc; border: 1px dashed var(--border); padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-top: 0.75rem;">
          <label style="display: flex; align-items: flex-start; gap: 0.65rem; cursor: pointer; font-size: 0.85rem; margin: 0;">
            <input type="checkbox" name="auto_allocate" value="1" id="autoAllocateCheck" style="margin-top: 0.2rem;">
            <span>
              <strong>Auto-allocate &amp; retire donor organ:</strong><br>
              <span style="color: var(--text-muted); font-size: 0.8rem;">If status is set to <em>Approved</em>, immediately update donor availability status to <em>Donated</em> and close other competing matches.</span>
            </span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('matchStatusModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Match</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: SCORE BREAKDOWN FORMULA VIEW                      -->
<!-- ======================================================= -->
<div class="modal-backdrop" id="breakdownModal" role="dialog" aria-modal="true" aria-labelledby="breakdownTitle">
  <div class="modal-content" style="max-width: 600px;">
    <div class="modal-header">
      <h3 id="breakdownTitle">100-Point Score Formula Breakdown</h3>
      <button type="button" class="modal-close" onclick="closeModal('breakdownModal')" aria-label="Close modal">&times;</button>
    </div>
    <div class="modal-body" id="breakdownBody">
      <!-- Injected via JavaScript -->
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('breakdownModal')">Close</button>
    </div>
  </div>
</div>

<script>
function openMatchStatusModal(id, donorName, recipientName, organ, currentStatus, notes) {
  document.getElementById('modalMatchId').value = id;
  document.getElementById('modalMatchOrgan').textContent = organ;
  document.getElementById('modalMatchDonor').textContent = donorName;
  document.getElementById('modalMatchRecipient').textContent = recipientName;
  document.getElementById('matchNewStatus').value = currentStatus;
  document.getElementById('matchNotes').value = notes || '';
  openModal('matchStatusModal');
}

function confirmDeleteMatch(id, donorName, recipientName) {
  document.getElementById('delete_match_id').value = id;
  document.getElementById('delete_match_details').textContent = '#' + id + ': ' + donorName + ' ➔ ' + recipientName;
  openModal('deleteMatchModal');
}

function showScoreBreakdown(match) {
  const body = document.getElementById('breakdownBody');
  const details = match.score_breakdown || {};

  body.innerHTML = `
    <div style="text-align: center; margin-bottom: 1.5rem;">
      <div class="match-score-badge match-score-100" style="font-size: 2rem; padding: 0.5rem 1.5rem; display: inline-block;">
        ${match.score}% Total Match
      </div>
      <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">
        Composite compatibility rating for ${escapeHtml(match.donor_name)} &rarr; ${escapeHtml(match.recipient_name)}
      </p>
    </div>

    <table class="data-table" style="font-size: 0.88rem; margin-bottom: 1.5rem;">
      <thead>
        <tr>
          <th>Scoring Parameter</th>
          <th>Rule / Condition</th>
          <th style="text-align: right;">Points</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>Organ Congruence</strong></td>
          <td>${escapeHtml(match.organ)} == ${escapeHtml(match.organ)} (Biological Requirement)</td>
          <td style="text-align: right; color: var(--success); font-weight: 700;">+${details.organ_match ?? 40}/40</td>
        </tr>
        <tr>
          <td><strong>Immunohematology</strong></td>
          <td>${escapeHtml(match.donor_blood)} &rarr; ${escapeHtml(match.recipient_blood)} (${escapeHtml(match.blood_match_type)})</td>
          <td style="text-align: right; color: var(--success); font-weight: 700;">+${details.blood_compatibility ?? 35}/35</td>
        </tr>
        <tr>
          <td><strong>Clinical Urgency</strong></td>
          <td>${escapeHtml(match.recipient_urgency)} Urgency Tier Priority</td>
          <td style="text-align: right; color: var(--success); font-weight: 700;">+${details.urgency_weight ?? 15}/20</td>
        </tr>
        <tr>
          <td><strong>Geographic Proximity</strong></td>
          <td>${escapeHtml(match.donor_city)} &harr; ${escapeHtml(match.recipient_hospital)}</td>
          <td style="text-align: right; color: var(--primary); font-weight: 700;">+${details.proximity_bonus ?? 0}/5</td>
        </tr>
        <tr style="background: var(--bg-subtle); font-weight: 700;">
          <td colspan="2">Calculated Total Compatibility Score</td>
          <td style="text-align: right; color: var(--primary); font-size: 1.05rem;">${match.score}/100</td>
        </tr>
      </tbody>
    </table>

    <div class="disclaimer-banner" style="margin-top: 1rem;">
      <div class="icon">ℹ️</div>
      <div>
        <h4>Academic Scoring Model Explanation</h4>
        <p style="font-size: 0.8rem; line-height: 1.4;">
          Scoring awards 40 points for exact organ pledge, 35 for identical blood group (30 for compatible universal blood), up to 20 for clinical urgency, and 5 for city proximity. Final allocation requires rigorous HLA tissue typing.
        </p>
      </div>
    </div>
  `;

  openModal('breakdownModal');
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
