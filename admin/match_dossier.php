<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Clinical Match Clearance Dossier & Printable Transplant Sheet
 */

$page_title = "Clinical Match Clearance Dossier";
$include_dashboard_css = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$match_id = (int)($_GET['id'] ?? 0);
header('Location: ' . BASE_URL . '/dossier.php?id=' . $match_id);
exit;

// Fetch match record
$stmt = $pdo->prepare("
    SELECT m.*, 
           d.full_name as donor_name, d.age as donor_age, d.gender as donor_gender, d.blood_group as donor_blood, 
           d.organ_donated, d.address_city as donor_city, d.mobile as donor_mobile, d.medical_notes as donor_notes,
           r.full_name as recipient_name, r.age as recipient_age, r.gender as recipient_gender, r.blood_group as recipient_blood,
           r.organ_needed, r.hospital_city as recipient_hospital, r.urgency_level as recipient_urgency, r.mobile as recipient_mobile, r.medical_notes as recipient_notes
    FROM matches m
    JOIN donors d ON m.donor_id = d.id
    JOIN recipients r ON m.recipient_id = r.id
    WHERE m.id = ?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    set_flash_message('error', 'Match record #' . $match_id . ' not found.');
    header('Location: ' . BASE_URL . '/admin/matches.php');
    exit;
}

$analysis = evaluate_compatibility(
    ['organ_donated' => $match['organ_donated'], 'blood_group' => $match['donor_blood'], 'address_city' => $match['donor_city']],
    ['organ_needed' => $match['organ_needed'], 'blood_group' => $match['recipient_blood'], 'hospital_city' => $match['recipient_hospital'], 'urgency_level' => $match['recipient_urgency']]
);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
@media print {
  body { background: #ffffff !important; font-size: 12pt; color: #000000; }
  .navbar, .footer, .dossier-actions, .dashboard-sidebar, .sidebar-toggle-btn { display: none !important; }
  .dashboard-wrapper { display: block !important; padding: 0 !important; }
  .dashboard-main { padding: 0 !important; max-width: 100% !important; margin: 0 !important; }
  .dossier-sheet { box-shadow: none !important; border: 1px solid #999 !important; padding: 1.5cm !important; margin: 0 !important; }
  .print-header { border-bottom: 2px solid #000 !important; }
}

.dossier-sheet {
  background: #ffffff;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  padding: 3rem;
  max-width: 900px;
  margin: 0 auto 3rem auto;
}

.dossier-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 1.5rem;
  border-bottom: 2px solid var(--primary);
  margin-bottom: 2rem;
}

.dossier-badge {
  font-family: monospace;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--primary);
  background: rgba(26, 115, 232, 0.08);
  padding: 0.4rem 0.85rem;
  border-radius: var(--radius-sm);
  border: 1px solid rgba(26, 115, 232, 0.2);
}

.comparison-box {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.party-card {
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 1.25rem 1.5rem;
  background: var(--bg-subtle);
}

.party-title {
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
</style>

<div class="dashboard-wrapper">
  
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="dashboard-main">
    
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; max-width: 900px; margin-left: auto; margin-right: auto;" class="dossier-actions">
      <div>
        <a href="<?php echo BASE_URL; ?>/admin/matches.php" class="btn btn-outline btn-sm">&larr; Back to Matches</a>
      </div>
      <div style="display: flex; gap: 0.75rem;">
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
          🖨️ Print Clinical Dossier / Save PDF
        </button>
      </div>
    </div>

    <!-- Official Printable Sheet -->
    <div class="dossier-sheet">
      
      <!-- Sheet Header -->
      <div class="dossier-header-bar print-header">
        <div>
          <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.35rem;">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo.svg" width="30" height="30" alt="Emblem">
            <span style="font-weight: 800; font-size: 1.15rem; color: #0f172a;"><?php echo APP_NAME; ?></span>
          </div>
          <h2 style="font-size: 1.45rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
            Preliminary Transplant Allocation Dossier
          </h2>
          <span style="font-size: 0.85rem; color: var(--text-muted);">
            Automated Immunohematological Compatibility Evaluation Summary
          </span>
        </div>
        <div style="text-align: right;">
          <div class="dossier-badge">REF: LB-MATCH-<?php echo str_pad($match['id'], 6, '0', STR_PAD_LEFT); ?></div>
          <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.4rem;">
            Generated: <?php echo date('d M Y &bull; H:i', strtotime($match['matched_at'])); ?>
          </div>
          <div style="margin-top: 0.35rem;">
            <span class="badge badge-<?php echo strtolower($match['status']); ?>" style="font-size: 0.85rem;">
              Status: <?php echo htmlspecialchars($match['status']); ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Two-Party Comparison Grid -->
      <div class="comparison-box">
        <!-- Donor Column -->
        <div class="party-card">
          <div class="party-title" style="color: var(--primary);">
            <span>❤️</span> Registered Voluntary Donor
          </div>
          <div style="font-size: 0.88rem; line-height: 1.6;">
            <div><strong>Donor Code:</strong> #DNR-<?php echo str_pad($match['donor_id'], 4, '0', STR_PAD_LEFT); ?> (<?php echo htmlspecialchars($match['donor_name']); ?>)</div>
            <div><strong>Demographics:</strong> <?php echo $match['donor_age']; ?> Years &bull; <?php echo htmlspecialchars($match['donor_gender']); ?></div>
            <div><strong>ABO/Rh Blood Group:</strong> <span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:700;"><?php echo htmlspecialchars($match['donor_blood']); ?></span></div>
            <div><strong>Organ Pledged:</strong> <strong style="color:var(--primary);"><?php echo htmlspecialchars($match['organ_donated']); ?></strong></div>
            <div><strong>Location / City:</strong> <?php echo htmlspecialchars($match['donor_city']); ?></div>
            <div><strong>Contact:</strong> <?php echo htmlspecialchars($match['donor_mobile']); ?></div>
            <?php if (!empty($match['donor_notes'])): ?>
              <div style="margin-top:0.4rem; font-size:0.8rem; color:#475569; border-top:1px dashed var(--border); padding-top:0.35rem;">
                <strong>Health Notes:</strong> <?php echo htmlspecialchars($match['donor_notes']); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recipient Column -->
        <div class="party-card">
          <div class="party-title" style="color: var(--secondary);">
            <span>📋</span> Registered Organ Recipient
          </div>
          <div style="font-size: 0.88rem; line-height: 1.6;">
            <div><strong>Recipient Code:</strong> #RCP-<?php echo str_pad($match['recipient_id'], 4, '0', STR_PAD_LEFT); ?> (<?php echo htmlspecialchars($match['recipient_name']); ?>)</div>
            <div><strong>Demographics:</strong> <?php echo $match['recipient_age']; ?> Years &bull; <?php echo htmlspecialchars($match['recipient_gender']); ?></div>
            <div><strong>ABO/Rh Blood Group:</strong> <span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:700;"><?php echo htmlspecialchars($match['recipient_blood']); ?></span></div>
            <div><strong>Organ Required:</strong> <strong style="color:var(--secondary);"><?php echo htmlspecialchars($match['organ_needed']); ?></strong></div>
            <div><strong>Attending Hospital:</strong> <?php echo htmlspecialchars($match['recipient_hospital']); ?></div>
            <div><strong>Clinical Urgency:</strong> <span class="badge badge-<?php echo strtolower($match['recipient_urgency']); ?>"><?php echo htmlspecialchars($match['recipient_urgency']); ?> Priority</span></div>
            <?php if (!empty($match['recipient_notes'])): ?>
              <div style="margin-top:0.4rem; font-size:0.8rem; color:#475569; border-top:1px dashed var(--border); padding-top:0.35rem;">
                <strong>Clinical Diagnosis:</strong> <?php echo htmlspecialchars($match['recipient_notes']); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- 100-Point Scoring Breakdown Table -->
      <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text-main);">
        Algorithmic Compatibility Breakdown (Score: <?php echo $match['compatibility_score']; ?>/100)
      </h3>

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
            <td>Tier Weight: <?php echo htmlspecialchars($match['recipient_urgency']); ?> Priority</td>
            <td style="text-align: right; color: var(--success); font-weight: 700;">+<?php echo $analysis['urgency_points']; ?> / 20</td>
          </tr>
          <tr>
            <td><strong>Geographic Proximity Bonus</strong></td>
            <td><?php echo htmlspecialchars($match['donor_city']); ?> &harr; <?php echo htmlspecialchars($match['recipient_hospital']); ?></td>
            <td style="text-align: right; color: var(--primary); font-weight: 700;">+<?php echo $analysis['proximity_points']; ?> / 5</td>
          </tr>
          <tr style="background: var(--bg-subtle); font-weight: 700; font-size: 0.95rem;">
            <td colspan="2">Composite Preliminary Compatibility Score</td>
            <td style="text-align: right; color: var(--primary); font-size: 1.15rem;"><?php echo $match['compatibility_score']; ?>%</td>
          </tr>
        </tbody>
      </table>

      <?php if (!empty($match['notes'])): ?>
        <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 2rem;">
          <strong style="color: var(--text-main); font-size: 0.9rem;">Transplant Coordinator / Reviewer Notes:</strong>
          <p style="font-size: 0.85rem; color: #334155; margin-top: 0.25rem; margin-bottom: 0;">
            <?php echo htmlspecialchars($match['notes']); ?>
          </p>
        </div>
      <?php endif; ?>

      <!-- Legal / Academic Disclaimer -->
      <div class="disclaimer-banner" style="margin-bottom: 2.5rem;">
        <div class="icon">⚠️</div>
        <div>
          <h4>Mandatory Medical &amp; Academic Protocol Notice</h4>
          <p style="font-size: 0.8rem; line-height: 1.45;">
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

  </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
