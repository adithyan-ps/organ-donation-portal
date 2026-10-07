<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Official Organ Donor Pledge Card Generator (Printable Wallet Card)
 */

$page_title = "Official Organ Donor Pledge Card";
$include_dashboard_css = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();
$pdo = get_db_connection();

$donor_id = (int)($_GET['id'] ?? 0);
if (!$donor_id) {
    header('Location: ' . BASE_URL . '/admin/donors.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM donors WHERE id = ?");
$stmt->execute([$donor_id]);
$donor = $stmt->fetch();

if (!$donor) {
    set_flash_message('error', 'Donor record #' . $donor_id . ' not found.');
    header('Location: ' . BASE_URL . '/admin/donors.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
@media print {
  body { background: #ffffff !important; }
  .navbar, .footer, .dossier-actions, .dashboard-sidebar, .sidebar-toggle-btn { display: none !important; }
  .dashboard-wrapper { display: block !important; padding: 0 !important; }
  .dashboard-main { padding: 0 !important; max-width: 100% !important; margin: 0 !important; }
  .card-container { box-shadow: none !important; margin: 2cm auto !important; }
}

.donor-id-card {
  width: 480px;
  max-width: 100%;
  height: 290px;
  background: linear-gradient(135deg, #ffffff 0%, #f0fdfa 100%);
  border: 2px solid var(--primary);
  border-radius: 20px;
  box-shadow: 0 15px 35px -5px rgba(13, 148, 136, 0.2);
  padding: 1.5rem 1.75rem;
  position: relative;
  overflow: hidden;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.donor-id-card::after {
  content: "❤️";
  position: absolute;
  right: -25px;
  bottom: -35px;
  font-size: 140px;
  opacity: 0.06;
  pointer-events: none;
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 2px solid rgba(13, 148, 136, 0.2);
  padding-bottom: 0.75rem;
}

.card-org-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.15;
}

.card-org-sub {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.card-body-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.card-name {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--text-main);
  margin-bottom: 0.25rem;
}

.card-meta-line {
  font-size: 0.82rem;
  color: #475569;
  line-height: 1.4;
}

.card-blood-badge {
  background: #dc2626;
  color: #ffffff;
  padding: 0.5rem 0.85rem;
  border-radius: 10px;
  text-align: center;
  box-shadow: 0 4px 8px rgba(220, 38, 38, 0.25);
}

.blood-label {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
  display: block;
}

.blood-val {
  font-size: 1.45rem;
  font-weight: 900;
  line-height: 1;
}

.card-bottom {
  border-top: 1px dashed #cbd5e1;
  padding-top: 0.6rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  font-size: 0.72rem;
  color: #64748b;
}
</style>

<div class="dashboard-wrapper">
  
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <main class="dashboard-main">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto;" class="dossier-actions">
      <div>
        <a href="<?php echo BASE_URL; ?>/admin/donors.php" class="btn btn-outline btn-sm">&larr; Back to Donors</a>
      </div>
      <div>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
          🖨️ Print Donor Card
        </button>
      </div>
    </div>

    <!-- Official Printable Donor Card -->
    <div class="card-container" style="display: flex; flex-direction: column; align-items: center; gap: 1.5rem; padding: 2rem 0;">
      
      <div class="donor-id-card">
        
        <!-- Card Top Bar -->
        <div class="card-top">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo.svg" width="28" height="28" alt="Logo">
            <div>
              <div class="card-org-title"><?php echo APP_SHORT_NAME; ?></div>
              <div class="card-org-sub">Official Organ Donor Pledge Pass</div>
            </div>
          </div>
          <div style="font-family: monospace; font-size: 0.78rem; font-weight: 700; color: var(--primary);">
            #DNR-<?php echo str_pad($donor['id'], 5, '0', STR_PAD_LEFT); ?>
          </div>
        </div>

        <!-- Card Middle / Info -->
        <div class="card-body-info">
          <div>
            <div class="card-name"><?php echo htmlspecialchars($donor['full_name']); ?></div>
            <div class="card-meta-line">
              <strong>Age / Gender:</strong> <?php echo $donor['age']; ?> Yrs &bull; <?php echo htmlspecialchars($donor['gender']); ?>
            </div>
            <div class="card-meta-line">
              <strong>Organ Pledged:</strong> <span style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($donor['organ_donated']); ?></span>
            </div>
            <div class="card-meta-line">
              <strong>Location:</strong> <?php echo htmlspecialchars($donor['address_city']); ?>
            </div>
            <div class="card-meta-line">
              <strong>Emergency Phone:</strong> <?php echo htmlspecialchars($donor['mobile']); ?>
            </div>
          </div>

          <!-- Blood Group Badge -->
          <div class="card-blood-badge">
            <span class="blood-label">Blood</span>
            <span class="blood-val"><?php echo htmlspecialchars($donor['blood_group']); ?></span>
          </div>
        </div>

        <!-- Card Bottom Declaration -->
        <div class="card-bottom">
          <div>
            <div><strong>Verification:</strong> <?php echo htmlspecialchars($donor['verification_status']); ?></div>
            <div><strong>Registered Date:</strong> <?php echo date('d M Y', strtotime($donor['created_at'])); ?></div>
          </div>
          <div style="text-align: right; max-width: 200px; font-style: italic;">
            "I have pledged my organ to give the gift of life to someone in need."
          </div>
        </div>

      </div>

      <div style="font-size: 0.82rem; color: var(--text-muted); text-align: center; max-width: 480px;">
        💡 <strong>Printing Advice:</strong> Standard credit card or ID card paper size. Click "Print Donor Card" to print or save as a digital PDF.
      </div>

    </div>

  </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
