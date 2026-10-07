<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Official Clinical Match Clearance Dossier & Printable Transplant Sheet
 * Accessible to Administrators, and securely to the respective matched Donor and Recipient
 */

$page_title = "Clinical Match Clearance Dossier";
$include_dashboard_css = true;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/matching_engine.php';

require_login();
$user = current_user();
$pdo = get_db_connection();

$match_id = (int)($_GET['id'] ?? 0);
if (!$match_id) {
    if (is_admin()) {
        header('Location: ' . BASE_URL . '/admin/matches.php');
    } elseif (is_donor()) {
        header('Location: ' . BASE_URL . '/donor_dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/recipient_dashboard.php');
    }
    exit;
}

// Fetch match record with donor and recipient data
$stmt = $pdo->prepare("
    SELECT m.*, 
           d.id as donor_id, d.user_id as donor_user_id, d.full_name as donor_name, d.age as donor_age, d.gender as donor_gender, 
           d.blood_group as donor_blood, d.organ_donated, d.address_city as donor_city, d.mobile as donor_mobile, 
           d.medical_notes as donor_notes, d.availability_status as donor_avail, d.admin_notes as donor_admin_notes,
           r.id as recipient_id, r.user_id as recipient_user_id, r.full_name as recipient_name, r.age as recipient_age, 
           r.gender as recipient_gender, r.blood_group as recipient_blood, r.organ_needed, r.hospital_city as recipient_hospital, 
           r.urgency_level as recipient_urgency, r.mobile as recipient_mobile, r.medical_notes as recipient_notes,
           r.admin_notes as recipient_admin_notes
    FROM matches m
    JOIN donors d ON m.donor_id = d.id
    JOIN recipients r ON m.recipient_id = r.id
    WHERE m.id = ?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    set_flash_message('error', 'Match record #' . $match_id . ' not found or is no longer available.');
    if (is_admin()) {
        header('Location: ' . BASE_URL . '/admin/matches.php');
    } elseif (is_donor()) {
        header('Location: ' . BASE_URL . '/donor_dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/recipient_dashboard.php');
    }
    exit;
}

// Security & Role Authorization Check
$is_admin = is_admin();
$is_donor_party = false;
$is_recipient_party = false;

if (!$is_admin) {
    // Check if logged in user is the donor of this match
    if (is_donor()) {
        // Find donor profile for this user
        $dCheck = $pdo->prepare("SELECT id FROM donors WHERE user_id = ? OR email = ?");
        $dCheck->execute([$user['id'], $user['email']]);
        $donorProfile = $dCheck->fetch();
        if ($donorProfile && (int)$donorProfile['id'] === (int)$match['donor_id']) {
            $is_donor_party = true;
        }
    }
    
    // Check if logged in user is the recipient of this match
    if (is_recipient()) {
        $rCheck = $pdo->prepare("SELECT id FROM recipients WHERE user_id = ? OR email = ?");
        $rCheck->execute([$user['id'], $user['email']]);
        $recipientProfile = $rCheck->fetch();
        if ($recipientProfile && (int)$recipientProfile['id'] === (int)$match['recipient_id']) {
            $is_recipient_party = true;
        }
    }

    if (!$is_donor_party && !$is_recipient_party) {
        set_flash_message('error', 'Unauthorized access: You are only permitted to view dossiers for your own official match allocations.');
        header('Location: ' . (is_donor() ? BASE_URL . '/donor_dashboard.php' : BASE_URL . '/recipient_dashboard.php'));
        exit;
    }
}

// Evaluate detailed algorithm compatibility
$analysis = evaluate_compatibility(
    ['organ_donated' => $match['organ_donated'], 'blood_group' => $match['donor_blood'], 'address_city' => $match['donor_city']],
    ['organ_needed' => $match['organ_needed'], 'blood_group' => $match['recipient_blood'], 'hospital_city' => $match['recipient_hospital'], 'urgency_level' => $match['recipient_urgency']]
);

// Determine back URL
if ($is_admin) {
    $back_url = BASE_URL . '/admin/matches.php';
    $back_label = '&larr; Back to Matches Console';
} elseif ($is_donor_party) {
    $back_url = BASE_URL . '/donor_dashboard.php';
    $back_label = '&larr; Back to Donor Dashboard';
} else {
    $back_url = BASE_URL . '/recipient_dashboard.php';
    $back_label = '&larr; Back to Recipient Dashboard';
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
@media print {
  body { background: #ffffff !important; font-size: 11pt; color: #000000; }
  .navbar, .footer, .dossier-actions, .dashboard-sidebar, .sidebar-toggle-btn { display: none !important; }
  .dashboard-wrapper { display: block !important; padding: 0 !important; }
  .dashboard-main, .container { padding: 0 !important; max-width: 100% !important; margin: 0 !important; }
  .dossier-sheet { box-shadow: none !important; border: 1px solid #999 !important; padding: 1.2cm !important; margin: 0 !important; }
  .print-header { border-bottom: 2px solid #000 !important; }
}

.dossier-sheet {
  background: #ffffff;
  border-radius: var(--radius-xl);
  border: 1px solid rgba(226, 232, 240, 0.9);
  box-shadow: 0 15px 35px -5px rgba(15, 23, 42, 0.06);
  padding: 3rem;
  max-width: 920px;
  margin: 0 auto 3rem auto;
}

.dossier-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 1.5rem;
  border-bottom: 2px solid var(--primary);
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.dossier-badge {
  font-family: monospace;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--primary);
  background: rgba(13, 148, 136, 0.08);
  padding: 0.4rem 0.85rem;
  border-radius: var(--radius-sm);
  border: 1px solid rgba(13, 148, 136, 0.2);
}

.comparison-box {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 768px) {
  .comparison-box {
    grid-template-columns: 1fr;
  }
}

.party-card {
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 1.35rem 1.5rem;
  background: var(--bg-subtle);
  transition: all var(--transition-fast);
}

.party-title {
  font-size: 1.05rem;
  font-weight: 800;
  margin-bottom: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
</style>

<div class="container" style="padding-top: 2rem; padding-bottom: 3.5rem;">
  
  <!-- Top Action Bar -->
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; max-width: 920px; margin-left: auto; margin-right: auto; flex-wrap: wrap; gap: 1rem;" class="dossier-actions">
    <div>
      <a href="<?php echo $back_url; ?>" class="btn btn-outline btn-sm"><?php echo $back_label; ?></a>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <span class="badge" style="background: var(--primary-light); color: var(--primary); font-weight: 700; font-size: 0.85rem;">
        Viewing as: <?php echo $is_admin ? 'Transplant Administrator' : ($is_donor_party ? 'Registered Donor' : 'Registered Recipient'); ?>
      </span>
      <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
        🖨️ Print Clearance Dossier
      </button>
    </div>
  </div>

  <!-- Official Printable Sheet -->
  <div class="dossier-sheet">
    
    <!-- Sheet Header -->
    <div class="dossier-header-bar print-header">
      <div>
        <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.35rem;">
          <img src="<?php echo BASE_URL; ?>/assets/images/logo.svg" width="32" height="32" alt="LifeBridge Logo">
          <span style="font-weight: 800; font-size: 1.25rem; color: #0f172a;"><?php echo APP_NAME; ?></span>
        </div>
        <h2 style="font-size: 1.55rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem; line-height: 1.2;">
          Preliminary Transplant Allocation Dossier
        </h2>
        <span style="font-size: 0.88rem; color: var(--text-muted);">
          Immunohematological Compatibility Evaluation &amp; Clinical Match Report
        </span>
      </div>
      <div style="text-align: right;">
        <div class="dossier-badge">REF: LB-MATCH-<?php echo str_pad($match['id'], 6, '0', STR_PAD_LEFT); ?></div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.4rem;">
          Initial Match: <?php echo date('d M Y &bull; H:i', strtotime($match['matched_at'])); ?>
        </div>
        <?php if (!empty($match['updated_at'])): ?>
          <div style="font-size: 0.75rem; color: var(--secondary); margin-top: 0.2rem;">
            Last Updated: <?php echo date('d M Y &bull; H:i', strtotime($match['updated_at'])); ?>
          </div>
        <?php endif; ?>
        <div style="margin-top: 0.45rem;">
          <span class="badge <?php echo ($match['status'] === 'Approved') ? 'badge-approved' : (($match['status'] === 'Under Review' || $match['status'] === 'Contacted') ? 'badge-review' : 'badge-potential'); ?>" style="font-size: 0.88rem; padding: 0.4rem 0.9rem;">
            Status: <?php echo htmlspecialchars($match['status']); ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Match Celebration & Status Notice -->
    <?php if ($match['status'] === 'Approved'): ?>
      <div style="background: linear-gradient(135deg, #042f2e 0%, #0d9488 100%); color: #ffffff; padding: 1.25rem 1.65rem; border-radius: var(--radius-lg); margin-bottom: 2rem;">
        <div style="font-weight: 800; font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
          <span>🎉</span> Official Transplant Allocation Approved
        </div>
        <p style="font-size: 0.9rem; color: #e6fffa; margin: 0; line-height: 1.5;">
          The administrative coordination desk and medical review board have approved this donor-recipient pairing for organ transplant preparation at <strong><?php echo htmlspecialchars($match['recipient_hospital']); ?></strong>.
        </p>
      </div>
    <?php elseif (in_array($match['status'], ['Under Review', 'Contacted'])): ?>
      <div style="background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 1.15rem 1.45rem; border-radius: var(--radius-lg); margin-bottom: 2rem;">
        <div style="font-weight: 700; font-size: 0.98rem; display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.25rem;">
          <span>⏳</span> Active Clinical Compatibility Review
        </div>
        <p style="font-size: 0.88rem; margin: 0; line-height: 1.45;">
          Transplant teams are reviewing laboratory markers, organ viability, and clinical readiness for this candidate pair.
        </p>
      </div>
    <?php else: ?>
      <div style="background: #f0fdfa; border: 1px solid #99f6e4; color: #0f766e; padding: 1.15rem 1.45rem; border-radius: var(--radius-lg); margin-bottom: 2rem;">
        <div style="font-weight: 700; font-size: 0.98rem; display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.25rem;">
          <span>⚡</span> Preliminary Algorithm Match
        </div>
        <p style="font-size: 0.88rem; margin: 0; line-height: 1.45;">
          This match has been identified by the automated 100-point matching engine based on blood group compatibility, organ viability, and urgency.
        </p>
      </div>
    <?php endif; ?>

    <!-- Two-Party Comparison Grid -->
    <div class="comparison-box">
      <!-- Donor Column -->
      <div class="party-card">
        <div class="party-title" style="color: var(--primary);">
          <span>❤️</span> Registered Voluntary Donor
        </div>
        <div style="font-size: 0.88rem; line-height: 1.65;">
          <?php if ($is_admin || $is_donor_party): ?>
            <div><strong>Donor Code:</strong> #DNR-<?php echo str_pad($match['donor_id'], 4, '0', STR_PAD_LEFT); ?> (<?php echo htmlspecialchars($match['donor_name']); ?>)</div>
            <div><strong>Contact:</strong> <?php echo htmlspecialchars($match['donor_mobile']); ?></div>
          <?php else: ?>
            <div><strong>Donor Code:</strong> #DNR-<?php echo str_pad($match['donor_id'], 4, '0', STR_PAD_LEFT); ?> (<em>Voluntary Donor Registry</em>)</div>
            <div><strong>Contact:</strong> <span style="color: var(--text-muted); font-size: 0.8rem;">🔒 Masked for Medical Privacy</span></div>
          <?php endif; ?>
          <div><strong>Demographics:</strong> <?php echo $match['donor_age']; ?> Years &bull; <?php echo htmlspecialchars($match['donor_gender']); ?></div>
          <div><strong>ABO/Rh Blood Group:</strong> <span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:700;"><?php echo htmlspecialchars($match['donor_blood']); ?></span></div>
          <div><strong>Organ Pledged:</strong> <strong style="color:var(--primary); font-size: 0.95rem;"><?php echo htmlspecialchars($match['organ_donated']); ?></strong></div>
          <div><strong>Location / City:</strong> <?php echo htmlspecialchars($match['donor_city']); ?></div>
          <?php if (!empty($match['donor_notes']) && ($is_admin || $is_donor_party)): ?>
            <div style="margin-top:0.4rem; font-size:0.8rem; color:#475569; border-top:1px dashed var(--border); padding-top:0.35rem;">
              <strong>Donor Declaration:</strong> <?php echo htmlspecialchars($match['donor_notes']); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recipient Column -->
      <div class="party-card">
        <div class="party-title" style="color: var(--secondary);">
          <span>📋</span> Registered Organ Recipient
        </div>
        <div style="font-size: 0.88rem; line-height: 1.65;">
          <?php if ($is_admin || $is_recipient_party): ?>
            <div><strong>Recipient Code:</strong> #RCP-<?php echo str_pad($match['recipient_id'], 4, '0', STR_PAD_LEFT); ?> (<?php echo htmlspecialchars($match['recipient_name']); ?>)</div>
            <div><strong>Contact:</strong> <?php echo htmlspecialchars($match['recipient_mobile']); ?></div>
          <?php else: ?>
            <div><strong>Recipient Code:</strong> #RCP-<?php echo str_pad($match['recipient_id'], 4, '0', STR_PAD_LEFT); ?> (<em>Registered Transplant Candidate</em>)</div>
            <div><strong>Contact:</strong> <span style="color: var(--text-muted); font-size: 0.8rem;">🔒 Masked for Medical Privacy</span></div>
          <?php endif; ?>
          <div><strong>Demographics:</strong> <?php echo $match['recipient_age']; ?> Years &bull; <?php echo htmlspecialchars($match['recipient_gender']); ?></div>
          <div><strong>ABO/Rh Blood Group:</strong> <span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:700;"><?php echo htmlspecialchars($match['recipient_blood']); ?></span></div>
          <div><strong>Organ Required:</strong> <strong style="color:var(--secondary); font-size: 0.95rem;"><?php echo htmlspecialchars($match['organ_needed']); ?></strong></div>
          <div><strong>Attending Hospital:</strong> <?php echo htmlspecialchars($match['recipient_hospital']); ?></div>
          <div><strong>Clinical Urgency:</strong> <span class="badge badge-<?php echo strtolower($match['recipient_urgency']); ?>"><?php echo htmlspecialchars($match['recipient_urgency']); ?> Priority</span></div>
          <?php if (!empty($match['recipient_notes']) && ($is_admin || $is_recipient_party)): ?>
            <div style="margin-top:0.4rem; font-size:0.8rem; color:#475569; border-top:1px dashed var(--border); padding-top:0.35rem;">
              <strong>Clinical Diagnosis:</strong> <?php echo htmlspecialchars($match['recipient_notes']); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 100-Point Scoring Breakdown Table -->
    <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 0.75rem; color: var(--text-main);">
      Algorithmic Compatibility Breakdown (Score: <?php echo $match['compatibility_score']; ?>/100)
    </h3>

    <div class="table-responsive">
      <table class="data-table" style="font-size: 0.88rem; margin-bottom: 2rem;">
        <thead>
          <tr>
            <th>Clinical Compatibility Factor</th>
            <th>Rule Evaluation Parameter</th>
            <th style="text-align: right;">Points Awarded</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Organ Type Congruence</strong></td>
            <td>Donor (<?php echo htmlspecialchars($match['organ_donated']); ?>) &harr; Recipient (<?php echo htmlspecialchars($match['organ_needed']); ?>)</td>
            <td style="text-align: right; color: var(--success); font-weight: 700;">+40 / 40</td>
          </tr>
          <tr>
            <td><strong>ABO / Rh Immunohematology</strong></td>
            <td><?php echo htmlspecialchars($match['donor_blood']); ?> &rarr; <?php echo htmlspecialchars($match['recipient_blood']); ?> (<?php echo htmlspecialchars($analysis['blood_match_type']); ?>)</td>
            <td style="text-align: right; color: var(--success); font-weight: 700;">+<?php echo $analysis['blood_points']; ?> / 35</td>
          </tr>
          <tr>
            <td><strong>Medical Urgency Priority</strong></td>
            <td>Tier Weight: <?php echo htmlspecialchars($match['recipient_urgency']); ?> Priority Candidate</td>
            <td style="text-align: right; color: var(--success); font-weight: 700;">+<?php echo $analysis['urgency_points']; ?> / 20</td>
          </tr>
          <tr>
            <td><strong>Geographic Proximity Bonus</strong></td>
            <td><?php echo htmlspecialchars($match['donor_city']); ?> &harr; <?php echo htmlspecialchars($match['recipient_hospital']); ?></td>
            <td style="text-align: right; color: var(--primary); font-weight: 700;">+<?php echo $analysis['proximity_points']; ?> / 5</td>
          </tr>
          <tr style="background: rgba(248, 250, 252, 0.9); font-weight: 800; font-size: 0.98rem;">
            <td colspan="2">Composite Preliminary Compatibility Score</td>
            <td style="text-align: right; color: var(--primary); font-size: 1.25rem;"><?php echo $match['compatibility_score']; ?>%</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Official Coordinator Notes / Admin Updates -->
    <?php if (!empty($match['notes'])): ?>
      <div style="background: #f8fafc; border: 1px solid rgba(203, 213, 225, 0.9); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-main); font-weight: 800; font-size: 0.95rem; margin-bottom: 0.35rem;">
          <span>📋</span> Transplant Coordinator &amp; Medical Desk Notes:
        </div>
        <p style="font-size: 0.9rem; color: #334155; line-height: 1.55; margin: 0; white-space: pre-line;">
          <?php echo htmlspecialchars($match['notes']); ?>
        </p>
      </div>
    <?php endif; ?>

    <!-- Legal / Academic Disclaimer -->
    <div class="disclaimer-banner" style="margin-bottom: 2.5rem;">
      <div class="icon">⚠️</div>
      <div>
        <h4>Mandatory Clinical &amp; Legal Protocol Notice</h4>
        <p style="font-size: 0.82rem; line-height: 1.5; margin: 0;">
          <?php echo MEDICAL_DISCLAIMER; ?>
        </p>
      </div>
    </div>

    <!-- Signatures Box for Clinical Board -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px dashed #cbd5e1;">
      <div>
        <div style="border-bottom: 1px solid #000; height: 40px; margin-bottom: 0.35rem;"></div>
        <div style="font-size: 0.85rem; font-weight: 700;">Chief Transplant Coordinator</div>
        <div style="font-size: 0.78rem; color: var(--text-muted);">State Organ &amp; Tissue Allocation Registry</div>
      </div>

      <div>
        <div style="border-bottom: 1px solid #000; height: 40px; margin-bottom: 0.35rem;"></div>
        <div style="font-size: 0.85rem; font-weight: 700;">Lead Transplant Surgeon</div>
        <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo htmlspecialchars($match['recipient_hospital']); ?></div>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
