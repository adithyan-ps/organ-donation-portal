<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Donor Profile Dashboard
 */

$page_title = "Donor Profile Dashboard";
$include_dashboard_css = true;
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/matching_engine.php';

require_donor();
$user = current_user();
$pdo = get_db_connection();

// Fetch donor record
$stmt = $pdo->prepare("SELECT * FROM donors WHERE user_id = ? OR email = ? LIMIT 1");
$stmt->execute([$user['id'], $user['email']]);
$donor = $stmt->fetch();

if (!$donor) {
    die("No donor profile associated with this account. Please register your donor details.");
}

$update_success = false;
$errors = [];

// Handle permitted profile updates (Mobile, City, Availability, Notes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session token expired. Please try again.';
    } else {
        $mobile              = trim($_POST['mobile'] ?? '');
        $address_city        = trim($_POST['address_city'] ?? '');
        $availability_status = trim($_POST['availability_status'] ?? 'Available');
        $medical_notes       = trim($_POST['medical_notes'] ?? '');

        if (empty($mobile) || strlen(preg_replace('/[^0-9]/', '', $mobile)) < 10) {
            $errors['mobile'] = 'A valid 10-digit mobile contact number is required.';
        }
        if (empty($address_city)) {
            $errors['address_city'] = 'City and address cannot be empty.';
        }

        if (empty($errors)) {
            try {
                $upd = $pdo->prepare("UPDATE donors SET mobile = ?, address_city = ?, availability_status = ?, medical_notes = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$mobile, $address_city, $availability_status, $medical_notes, $donor['id']]);
                
                // Refresh donor
                $stmt->execute([$user['id'], $user['email']]);
                $donor = $stmt->fetch();

                set_flash_message('success', 'Your donor profile details have been successfully updated.');
                $update_success = true;
            } catch (Exception $e) {
                $errors[] = 'Failed to update profile: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Check anonymous preliminary match notifications for this donor
$matchStmt = $pdo->prepare("
    SELECT m.*, 
           r.organ_needed, 
           r.blood_group AS recipient_blood, 
           r.urgency_level AS recipient_urgency,
           r.hospital_city AS recipient_hospital_city
    FROM matches m 
    JOIN recipients r ON m.recipient_id = r.id 
    WHERE m.donor_id = ? 
    ORDER BY CASE 
        WHEN m.status = 'Approved' THEN 1 
        WHEN m.status = 'Contacted' THEN 2 
        WHEN m.status = 'Under Review' THEN 3 
        WHEN m.status = 'Potential' THEN 4 
        ELSE 5 
    END, m.compatibility_score DESC, m.matched_at DESC
");
$matchStmt->execute([$donor['id']]);
$donorMatches = $matchStmt->fetchAll();

$approvedMatch  = null;
$contactedMatch = null;
$inReviewMatch  = null;
$potentialMatch = null;
foreach ($donorMatches as $m) {
    if ($m['status'] === 'Approved' && !$approvedMatch) {
        $approvedMatch = $m;
    } elseif ($m['status'] === 'Contacted' && !$contactedMatch) {
        $contactedMatch = $m;
    } elseif ($m['status'] === 'Under Review' && !$inReviewMatch) {
        $inReviewMatch = $m;
    } elseif ($m['status'] === 'Potential' && !$potentialMatch) {
        $potentialMatch = $m;
    }
}
$topMatch = $approvedMatch ?? $contactedMatch ?? $inReviewMatch ?? $potentialMatch;

require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 2.5rem; padding-bottom: 4rem;">
  
  <!-- Dashboard Top Bar -->
  <div class="dashboard-header">
    <div class="dashboard-title">
      <h1>Donor Profile &amp; Pledge Status</h1>
      <p>Manage your registered organ donation pledge, track verification, and view anonymous match progress.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <?php if ($topMatch): ?>
        <a href="<?php echo BASE_URL; ?>/dossier.php?id=<?php echo $topMatch['id']; ?>" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.4rem;">
          <span>📄</span> View Match Dossier
        </a>
      <?php endif; ?>
      <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-outline btn-sm">Sign Out</a>
    </div>
  </div>

  <!-- Medical & Legal Disclaimer Banner -->
  <div class="disclaimer-banner">
    <div class="icon">🛡️</div>
    <div>
      <h4>Confidentiality &amp; Academic Notice</h4>
      <p>
        <?php echo MEDICAL_DISCLAIMER; ?> All recipient identities are anonymized in compliance with medical confidentiality guidelines.
      </p>
    </div>
  </div>

  <!-- Dynamic Match Alert Banner: "You Got a Match!" (Visible immediately upon Admin Matching) -->
  <?php if ($topMatch): ?>
    <?php if ($topMatch['status'] === 'Approved'): ?>
      <div style="background: linear-gradient(135deg, #042f2e 0%, #0d9488 100%); color: #ffffff; padding: 1.75rem 2rem; border-radius: var(--radius-xl); margin-bottom: 2rem; box-shadow: 0 15px 35px -5px rgba(13, 148, 136, 0.35); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.2);">
        <div style="max-width: 720px;">
          <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255, 255, 255, 0.2); padding: 0.35rem 0.95rem; border-radius: var(--radius-full); font-size: 0.85rem; font-weight: 800; margin-bottom: 0.65rem; letter-spacing: 0.05em; text-transform: uppercase;">
            <span>🎉</span> YOU GOT A MATCH &bull; TRANSPLANT ALLOCATION APPROVED!
          </div>
          <h2 style="font-size: 1.6rem; font-weight: 800; color: #ffffff; margin-bottom: 0.45rem; line-height: 1.25;">
            Life-Saving Organ Allocation Confirmed by Medical Desk
          </h2>
          <p style="font-size: 0.96rem; color: #e6fffa; line-height: 1.55; margin-bottom: 0.75rem;">
            A transplant allocation has been officially confirmed for your pledged <strong><?php echo htmlspecialchars($donor['organ_donated']); ?></strong> with a compatible recipient patient (Score: <strong><?php echo $topMatch['compatibility_score']; ?>%</strong>). The medical coordination desk and attending surgical hospital in <strong><?php echo htmlspecialchars($topMatch['recipient_hospital_city'] ?? 'Designated Hospital'); ?></strong> are preparing surgical protocols.
          </p>
          <?php if (!empty($topMatch['notes'])): ?>
            <div style="background: rgba(0, 0, 0, 0.22); border-left: 3px solid #34d399; padding: 0.65rem 1rem; border-radius: var(--radius-sm); font-size: 0.88rem; color: #ffffff; margin-top: 0.5rem;">
              <strong>📋 Transplant Coordinator Note:</strong> <?php echo htmlspecialchars($topMatch['notes']); ?>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.65rem; align-items: flex-end;">
          <span class="badge" style="background: #ffffff; color: #047857; font-size: 0.95rem; font-weight: 800; padding: 0.65rem 1.2rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 0.5rem;">
            <span>✅</span> Status: Approved
          </span>
          <a href="<?php echo BASE_URL; ?>/dossier.php?id=<?php echo $topMatch['id']; ?>" class="btn" style="background: #ffffff; color: var(--primary-dark); font-weight: 700; font-size: 0.9rem; padding: 0.6rem 1.2rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 0.4rem;">
            <span>📄</span> View Official Match Dossier
          </a>
        </div>
      </div>

    <?php elseif ($topMatch['status'] === 'Contacted'): ?>
      <div style="background: linear-gradient(135deg, #0c4a6e 0%, #0284c7 100%); color: #ffffff; padding: 1.75rem 2rem; border-radius: var(--radius-xl); margin-bottom: 2rem; box-shadow: 0 15px 35px -5px rgba(2, 132, 199, 0.35); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.2);">
        <div style="max-width: 720px;">
          <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255, 255, 255, 0.2); padding: 0.35rem 0.95rem; border-radius: var(--radius-full); font-size: 0.85rem; font-weight: 800; margin-bottom: 0.65rem; letter-spacing: 0.05em; text-transform: uppercase;">
            <span>📞</span> YOU GOT A MATCH &bull; CLINICAL OUTREACH IN PROGRESS
          </div>
          <h2 style="font-size: 1.55rem; font-weight: 800; color: #ffffff; margin-bottom: 0.45rem; line-height: 1.25;">
            Transplant Team Has Initiated Match Coordination
          </h2>
          <p style="font-size: 0.96rem; color: #e0f2fe; line-height: 1.55; margin-bottom: 0.75rem;">
            Transplant coordinators have reached out regarding your pledged <strong><?php echo htmlspecialchars($donor['organ_donated']); ?></strong> for a compatible candidate case (Score: <strong><?php echo $topMatch['compatibility_score']; ?>%</strong>). Attending clinical staff will coordinate laboratory cross-matching steps with you.
          </p>
          <?php if (!empty($topMatch['notes'])): ?>
            <div style="background: rgba(0, 0, 0, 0.22); border-left: 3px solid #38bdf8; padding: 0.65rem 1rem; border-radius: var(--radius-sm); font-size: 0.88rem; color: #ffffff; margin-top: 0.5rem;">
              <strong>📋 Coordinator Instructions:</strong> <?php echo htmlspecialchars($topMatch['notes']); ?>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.65rem; align-items: flex-end;">
          <span class="badge" style="background: #ffffff; color: #0369a1; font-size: 0.95rem; font-weight: 800; padding: 0.65rem 1.2rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 0.5rem;">
            <span>📞</span> Status: Contacted
          </span>
          <a href="<?php echo BASE_URL; ?>/dossier.php?id=<?php echo $topMatch['id']; ?>" class="btn" style="background: #ffffff; color: #0369a1; font-weight: 700; font-size: 0.9rem; padding: 0.6rem 1.2rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 0.4rem;">
            <span>📄</span> View Official Match Dossier
          </a>
        </div>
      </div>

    <?php elseif ($topMatch['status'] === 'Under Review'): ?>
      <div style="background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 1.6rem 1.85rem; border-radius: var(--radius-xl); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 4px 15px -2px rgba(245, 158, 11, 0.1);">
        <div style="max-width: 720px;">
          <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: #fef3c7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 800; margin-bottom: 0.5rem; text-transform: uppercase;">
            <span>⏳</span> YOU GOT A MATCH &bull; CLINICAL EVALUATION IN PROGRESS
          </div>
          <h3 style="font-size: 1.35rem; font-weight: 800; color: #78350f; margin-bottom: 0.35rem; line-height: 1.3;">
            Preliminary Recipient Match Under Clinical Review
          </h3>
          <p style="font-size: 0.94rem; margin-bottom: 0.5rem; line-height: 1.5;">
            Transplant coordinators are actively evaluating compatibility for your pledged <strong><?php echo htmlspecialchars($donor['organ_donated']); ?></strong> (Compatibility Score: <strong><?php echo $topMatch['compatibility_score']; ?>%</strong>). The clinical transplant board is reviewing antibody and immunohematology panels.
          </p>
          <?php if (!empty($topMatch['notes'])): ?>
            <div style="background: #ffffff; border-left: 3px solid #f59e0b; padding: 0.55rem 0.85rem; border-radius: var(--radius-sm); font-size: 0.86rem; color: #78350f;">
              <strong>📋 Coordinator Note:</strong> <?php echo htmlspecialchars($topMatch['notes']); ?>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.65rem; align-items: flex-end;">
          <span class="badge badge-pending" style="font-size: 0.9rem; padding: 0.55rem 1rem;">Status: Under Clinical Review</span>
          <a href="<?php echo BASE_URL; ?>/dossier.php?id=<?php echo $topMatch['id']; ?>" class="btn btn-outline btn-sm" style="font-size: 0.88rem; font-weight: 700; background: #ffffff;">
            <span>📄</span> View Match Dossier
          </a>
        </div>
      </div>

    <?php else: /* Potential */ ?>
      <div style="background: #f0fdfa; border: 1px solid #99f6e4; color: #0f766e; padding: 1.5rem 1.85rem; border-radius: var(--radius-xl); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 4px 15px -2px rgba(13, 148, 136, 0.08);">
        <div style="max-width: 720px;">
          <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: #ccfbf1; color: #0f766e; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 800; margin-bottom: 0.5rem; text-transform: uppercase;">
            <span>⚡</span> YOU GOT A PRELIMINARY MATCH!
          </div>
          <h3 style="font-size: 1.35rem; font-weight: 800; color: #115e59; margin-bottom: 0.35rem; line-height: 1.3;">
            Preliminary Compatibility Flagged by Matching Engine
          </h3>
          <p style="font-size: 0.94rem; margin-bottom: 0.5rem; line-height: 1.5;">
            An automated compatibility match (Score: <strong><?php echo $topMatch['compatibility_score']; ?>%</strong>) has been identified for your pledged <strong><?php echo htmlspecialchars($donor['organ_donated']); ?></strong> with a patient waiting list candidate.
          </p>
          <?php if (!empty($topMatch['notes'])): ?>
            <div style="background: #ffffff; border-left: 3px solid #0d9488; padding: 0.55rem 0.85rem; border-radius: var(--radius-sm); font-size: 0.86rem; color: #115e59;">
              <strong>📋 Coordinator Note:</strong> <?php echo htmlspecialchars($topMatch['notes']); ?>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.65rem; align-items: flex-end;">
          <span class="badge badge-potential" style="font-size: 0.9rem; padding: 0.55rem 1rem;">Status: Potential Match</span>
          <a href="<?php echo BASE_URL; ?>/dossier.php?id=<?php echo $topMatch['id']; ?>" class="btn btn-outline btn-sm" style="font-size: 0.88rem; font-weight: 700; background: #ffffff;">
            <span>📄</span> View Match Dossier
          </a>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Verification Status Banner (Shows Admin Notes & Status) -->
  <?php if ($donor['verification_status'] === 'Pending'): ?>
    <div class="verification-banner pending">
      <div>
        <h3 style="font-size: 1.15rem; margin-bottom: 0.25rem;">⏳ Profile Awaiting Verification</h3>
        <p style="font-size: 0.92rem; margin-bottom: 0;">
          Your registration has been received and is currently in our queue. Our transplant administrators are reviewing your submission. Once approved, your pledge will be active in the automated matching engine.
        </p>
        <?php if (!empty($donor['admin_notes'])): ?>
          <div style="margin-top: 0.75rem; padding: 0.6rem 0.85rem; background: rgba(255, 255, 255, 0.8); border-radius: var(--radius-sm); border-left: 3px solid #f59e0b; font-size: 0.85rem; color: #92400e;">
            <strong>📋 Administrator Review Note:</strong> <?php echo htmlspecialchars($donor['admin_notes']); ?>
          </div>
        <?php endif; ?>
      </div>
      <span class="badge badge-pending" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">Status: Pending Review</span>
    </div>
  <?php elseif ($donor['verification_status'] === 'Verified'): ?>
    <div class="verification-banner verified">
      <div>
        <h3 style="font-size: 1.15rem; margin-bottom: 0.25rem;">✅ Verified Donor Profile</h3>
        <p style="font-size: 0.92rem; margin-bottom: 0;">
          Your identity and basic health declaration have been verified. Your organ pledge is active in the preliminary matching registry. Thank you for your commitment to saving lives!
        </p>
        <?php if (!empty($donor['admin_notes'])): ?>
          <div style="margin-top: 0.75rem; padding: 0.6rem 0.85rem; background: rgba(255, 255, 255, 0.85); border-radius: var(--radius-sm); border-left: 3px solid #10b981; font-size: 0.85rem; color: #065f46;">
            <strong>📋 Administrator Verification Note:</strong> <?php echo htmlspecialchars($donor['admin_notes']); ?>
          </div>
        <?php endif; ?>
      </div>
      <span class="badge badge-verified" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">Status: Verified Donor</span>
    </div>
  <?php else: ?>
    <div class="verification-banner rejected">
      <div>
        <h3 style="font-size: 1.15rem; margin-bottom: 0.25rem;">⚠️ Registration Requires Review</h3>
        <p style="font-size: 0.92rem; margin-bottom: 0;">
          Administrator notes: <?php echo htmlspecialchars($donor['admin_notes'] ?? 'Please contact portal support to verify your health documentation.'); ?>
        </p>
      </div>
      <span class="badge badge-rejected" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">Status: Action Required</span>
    </div>
  <?php endif; ?>

  <div class="dashboard-grid-2col">
    
    <!-- Left Column: Profile Details & Edit Form -->
    <div>
      <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h2>Your Pledged Details</h2>
            <p>Non-medical fields can be updated anytime.</p>
          </div>
          <span class="badge <?php echo ($donor['availability_status'] === 'Available') ? 'badge-verified' : 'badge-pending'; ?>">
            <?php echo htmlspecialchars($donor['availability_status']); ?>
          </span>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="flash-alert error" style="margin-bottom: 1.5rem; position: static; max-width: 100%;">
            <span><?php echo htmlspecialchars(reset($errors)); ?></span>
          </div>
        <?php endif; ?>

        <!-- Read-only Core Medical Attributes -->
        <div class="profile-info-grid" style="background: var(--bg-subtle); padding: 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid var(--border);">
          <div class="profile-item">
            <span class="profile-item-label">Full Name</span>
            <span class="profile-item-value"><?php echo htmlspecialchars($donor['full_name']); ?></span>
          </div>
          <div class="profile-item">
            <span class="profile-item-label">Age &amp; Gender</span>
            <span class="profile-item-value"><?php echo htmlspecialchars($donor['age']); ?> yrs, <?php echo htmlspecialchars($donor['gender']); ?></span>
          </div>
          <div class="profile-item">
            <span class="profile-item-label">Blood Group</span>
            <span class="profile-item-value" style="color: var(--danger); font-size: 1.2rem;"><?php echo htmlspecialchars($donor['blood_group']); ?></span>
          </div>
          <div class="profile-item">
            <span class="profile-item-label">Pledged Organ / Tissue</span>
            <span class="profile-item-value" style="color: var(--primary); font-size: 1.2rem;"><?php echo htmlspecialchars($donor['organ_donated']); ?></span>
          </div>
        </div>

        <!-- Editable Form -->
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" data-validate="true">
          <input type="hidden" name="action" value="update_profile">
          <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="donorEmail">Registered Email (Read Only)</label>
              <input type="email" id="donorEmail" class="form-control" value="<?php echo htmlspecialchars($donor['email']); ?>" disabled style="background-color: var(--bg-subtle);">
            </div>

            <div class="form-group">
              <label class="form-label" for="donorMobile">Mobile Number <span class="required">*</span></label>
              <input type="tel" id="donorMobile" name="mobile" class="form-control" value="<?php echo htmlspecialchars($donor['mobile']); ?>" required>
            </div>

            <div class="form-group col-span-2">
              <label class="form-label" for="donorCity">Current Address &amp; City <span class="required">*</span></label>
              <input type="text" id="donorCity" name="address_city" class="form-control" value="<?php echo htmlspecialchars($donor['address_city']); ?>" required>
            </div>

            <div class="form-group col-span-2">
              <label class="form-label" for="donorAvailability">Pledge Availability Status <span class="required">*</span></label>
              <select id="donorAvailability" name="availability_status" class="form-control" required>
                <option value="Available" <?php echo ($donor['availability_status'] === 'Available') ? 'selected' : ''; ?>>Available (Active in matching)</option>
                <option value="Temporarily Unavailable" <?php echo ($donor['availability_status'] === 'Temporarily Unavailable') ? 'selected' : ''; ?>>Temporarily Unavailable (Deferred)</option>
              </select>
              <span class="form-help">Set to "Temporarily Unavailable" if traveling, ill, or temporarily unable to donate.</span>
            </div>

            <div class="form-group col-span-2">
              <label class="form-label" for="donorNotes">Health Declaration / Medical Notes</label>
              <textarea id="donorNotes" name="medical_notes" class="form-control" rows="3"><?php echo htmlspecialchars($donor['medical_notes'] ?? ''); ?></textarea>
            </div>
          </div>

          <div style="margin-top: 1.5rem; text-align: right;">
            <button type="submit" class="btn btn-primary">Save Profile Updates</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Right Column: Anonymous Preliminary Match Updates -->
    <div>
      <div class="card">
        <div class="card-header">
          <h2>Potential Match Activity</h2>
          <p>Privacy-protected status of preliminary compatibility checks.</p>
        </div>

        <?php if (!empty($donorMatches)): ?>
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($donorMatches as $dm): ?>
              <div style="background: var(--bg-page); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.35rem; transition: all var(--transition-fast);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                  <div>
                    <div style="font-family: monospace; font-size: 0.76rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.15rem;">
                      REF: LB-MATCH-<?php echo str_pad($dm['id'], 6, '0', STR_PAD_LEFT); ?>
                    </div>
                    <strong style="font-size: 1.05rem; color: var(--text-main); display: block;">
                      <?php echo htmlspecialchars($dm['organ']); ?> Compatibility Identified
                    </strong>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">
                      Matched: <?php echo date('M d, Y', strtotime($dm['matched_at'])); ?>
                      <?php if (!empty($dm['updated_at']) && $dm['updated_at'] !== $dm['matched_at']): ?>
                        &bull; Updated: <?php echo date('M d, Y &bull; H:i', strtotime($dm['updated_at'])); ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <span class="badge <?php echo ($dm['status'] === 'Approved') ? 'badge-approved' : (($dm['status'] === 'Contacted' || $dm['status'] === 'Under Review') ? 'badge-review' : 'badge-potential'); ?>">
                    <?php echo htmlspecialchars($dm['status']); ?>
                  </span>
                </div>

                <div style="background: #ffffff; padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--border); margin: 0.75rem 0;">
                  <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    <span style="font-weight: 600; color: var(--text-main);">Compatibility Score:</span>
                    <strong style="color: var(--primary); font-size: 1.05rem;"><?php echo $dm['compatibility_score']; ?>%</strong>
                  </div>
                  <div style="height: 6px; background: #e2e8f0; border-radius: var(--radius-full); overflow: hidden; margin-bottom: 0.4rem;">
                    <div style="width: <?php echo min(100, max(5, $dm['compatibility_score'])); ?>%; height: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary)); border-radius: var(--radius-full);"></div>
                  </div>
                  <div style="font-size: 0.78rem; color: var(--text-muted);">
                    Transplant coordinators are reviewing compatibility metrics for this potential recipient case.
                  </div>
                </div>

                <?php if (!empty($dm['notes'])): ?>
                  <div style="background: #f0fdfa; border-left: 3px solid var(--primary); padding: 0.6rem 0.85rem; border-radius: var(--radius-sm); margin: 0.65rem 0; font-size: 0.82rem; color: #0f766e;">
                    <strong>📋 Coordinator Decision Note:</strong> <?php echo htmlspecialchars($dm['notes']); ?>
                  </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                  <span style="font-size: 0.78rem; color: var(--text-muted);">
                    🔒 <em>Identity confidential</em>
                  </span>
                  <a href="<?php echo BASE_URL; ?>/dossier.php?id=<?php echo $dm['id']; ?>" class="btn btn-outline-primary btn-sm" style="font-size: 0.82rem; padding: 0.35rem 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <span>📄</span> View Match Dossier
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">🔍</div>
            <h4 style="color: var(--text-main); font-size: 1.05rem; margin-bottom: 0.25rem;">No Current Active Matches</h4>
            <p style="font-size: 0.875rem;">
              <?php if ($donor['verification_status'] === 'Verified'): ?>
                The system continually scans for compatible organ requirements. You will see anonymous clinical notifications here as soon as preliminary matches are flagged.
              <?php else: ?>
                Preliminary matching will begin as soon as your profile completes administrative verification.
              <?php endif; ?>
            </p>
          </div>
        <?php endif; ?>

        <!-- Info Notice -->
        <div style="margin-top: 1.5rem; padding: 1rem; background: var(--secondary-light); border-radius: var(--radius-md); font-size: 0.82rem; color: var(--secondary-dark);">
          <strong>Need to withdraw your pledge?</strong> You can set your availability status to "Temporarily Unavailable" above, or contact our medical desk to revoke your pledge at any time.
        </div>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
