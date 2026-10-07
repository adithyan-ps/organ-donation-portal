<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Recipient Profile Dashboard
 */

$page_title = "Recipient Profile & Waitlist Dashboard";
$include_dashboard_css = true;
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/matching_engine.php';

require_recipient();
$user = current_user();
$pdo = get_db_connection();

// Fetch recipient record
$stmt = $pdo->prepare("SELECT * FROM recipients WHERE user_id = ? OR email = ? LIMIT 1");
$stmt->execute([$user['id'], $user['email']]);
$recipient = $stmt->fetch();

if (!$recipient) {
    die("No recipient requirement found for this account. Please submit your registration.");
}

$update_success = false;
$errors = [];

// Handle permitted updates (Mobile, Hospital, Notes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session token expired. Please try again.';
    } else {
        $mobile        = trim($_POST['mobile'] ?? '');
        $hospital_city = trim($_POST['hospital_city'] ?? '');
        $medical_notes = trim($_POST['medical_notes'] ?? '');

        if (empty($mobile) || strlen(preg_replace('/[^0-9]/', '', $mobile)) < 10) {
            $errors['mobile'] = 'A valid 10-digit mobile contact number is required.';
        }
        if (empty($hospital_city)) {
            $errors['hospital_city'] = 'Hospital and city details cannot be empty.';
        }

        if (empty($errors)) {
            try {
                $upd = $pdo->prepare("UPDATE recipients SET mobile = ?, hospital_city = ?, medical_notes = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$mobile, $hospital_city, $medical_notes, $recipient['id']]);

                // Refresh recipient
                $stmt->execute([$user['id'], $user['email']]);
                $recipient = $stmt->fetch();

                set_flash_message('success', 'Your recipient profile details have been successfully updated.');
                $update_success = true;
            } catch (Exception $e) {
                $errors[] = 'Failed to update details: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Fetch anonymous matches for this recipient
$matchStmt = $pdo->prepare("
    SELECT m.*, 
           d.organ_donated, 
           d.blood_group AS donor_blood, 
           d.age AS donor_age,
           d.gender AS donor_gender,
           d.address_city AS donor_city
    FROM matches m 
    JOIN donors d ON m.donor_id = d.id 
    WHERE m.recipient_id = ? 
    ORDER BY CASE 
        WHEN m.status = 'Approved' THEN 1 
        WHEN m.status = 'Contacted' THEN 2 
        WHEN m.status = 'Under Review' THEN 3 
        WHEN m.status = 'Potential' THEN 4 
        ELSE 5 
    END, m.compatibility_score DESC, m.matched_at DESC
");
$matchStmt->execute([$recipient['id']]);
$recipientMatches = $matchStmt->fetchAll();

$approvedMatch  = null;
$contactedMatch = null;
$inReviewMatch  = null;
$potentialMatch = null;
foreach ($recipientMatches as $m) {
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
      <h1>Recipient Profile &amp; Waitlist Status</h1>
      <p>Monitor your verified organ requirement, hospital coordination status, and preliminary donor matches.</p>
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

  <!-- Medical Disclaimer Banner -->
  <div class="disclaimer-banner">
    <div class="icon">🛡️</div>
    <div>
      <h4>Academic Demonstration &amp; Ethical Clinical Protocol</h4>
      <p>
        <?php echo MEDICAL_DISCLAIMER; ?> Donor identities are kept confidential to prevent unauthorized direct contact.
      </p>
    </div>
  </div>

  <!-- Dynamic Match Alert Banner: "You Got a Match!" (Visible immediately upon Admin Matching) -->
  <?php if ($topMatch): ?>
    <?php if ($topMatch['status'] === 'Approved'): ?>
      <div style="background: linear-gradient(135deg, #090d16 0%, #0369a1 100%); color: #ffffff; padding: 1.75rem 2rem; border-radius: var(--radius-xl); margin-bottom: 2rem; box-shadow: 0 15px 35px -5px rgba(2, 132, 199, 0.4); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.2);">
        <div style="max-width: 720px;">
          <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255, 255, 255, 0.2); padding: 0.35rem 0.95rem; border-radius: var(--radius-full); font-size: 0.85rem; font-weight: 800; margin-bottom: 0.65rem; letter-spacing: 0.05em; text-transform: uppercase;">
            <span>🎉</span> YOU GOT A MATCH &bull; COMPATIBLE ORGAN ALLOCATED!
          </div>
          <h2 style="font-size: 1.6rem; font-weight: 800; color: #ffffff; margin-bottom: 0.45rem; line-height: 1.25;">
            A Compatible Organ Has Been Officially Allocated!
          </h2>
          <p style="font-size: 0.96rem; color: #e0f2fe; line-height: 1.55; margin-bottom: 0.75rem;">
            Transplant authorization has been confirmed for your required <strong><?php echo htmlspecialchars($recipient['organ_needed']); ?></strong> (Compatibility Score: <strong><?php echo $topMatch['compatibility_score']; ?>%</strong>). Your attending surgical unit at <strong><?php echo htmlspecialchars($recipient['hospital_city']); ?></strong> has received clinical clearance. Please remain on immediate telephone standby.
          </p>
          <?php if (!empty($topMatch['notes'])): ?>
            <div style="background: rgba(0, 0, 0, 0.25); border-left: 3px solid #38bdf8; padding: 0.65rem 1rem; border-radius: var(--radius-sm); font-size: 0.88rem; color: #ffffff; margin-top: 0.5rem;">
              <strong>📋 Transplant Coordinator Note:</strong> <?php echo htmlspecialchars($topMatch['notes']); ?>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.65rem; align-items: flex-end;">
          <span class="badge" style="background: #ffffff; color: #0284c7; font-size: 0.95rem; font-weight: 800; padding: 0.65rem 1.2rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 0.5rem;">
            <span>✅</span> Allocation: Approved
          </span>
          <a href="<?php echo BASE_URL; ?>/dossier.php?id=<?php echo $topMatch['id']; ?>" class="btn" style="background: #ffffff; color: #0369a1; font-weight: 700; font-size: 0.9rem; padding: 0.6rem 1.2rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 0.4rem;">
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
            Transplant Team In Active Coordination
          </h2>
          <p style="font-size: 0.96rem; color: #e0f2fe; line-height: 1.55; margin-bottom: 0.75rem;">
            Transplant coordinators have initiated preliminary matching coordination for your required <strong><?php echo htmlspecialchars($recipient['organ_needed']); ?></strong> (Compatibility Score: <strong><?php echo $topMatch['compatibility_score']; ?>%</strong>). Your attending hospital in <strong><?php echo htmlspecialchars($recipient['hospital_city']); ?></strong> is preparing preliminary tests.
          </p>
          <?php if (!empty($topMatch['notes'])): ?>
            <div style="background: rgba(0, 0, 0, 0.25); border-left: 3px solid #38bdf8; padding: 0.65rem 1rem; border-radius: var(--radius-sm); font-size: 0.88rem; color: #ffffff; margin-top: 0.5rem;">
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
            Potential Donor Compatibility Under Clinical Review
          </h3>
          <p style="font-size: 0.94rem; margin-bottom: 0.5rem; line-height: 1.5;">
            A compatible organ pledge has been flagged for your case (Compatibility Score: <strong><?php echo $topMatch['compatibility_score']; ?>%</strong>). The clinical transplant board is reviewing immunohematology reports. Please keep your emergency contact phone on standby.
          </p>
          <?php if (!empty($topMatch['notes'])): ?>
            <div style="background: #ffffff; border-left: 3px solid #f59e0b; padding: 0.55rem 0.85rem; border-radius: var(--radius-sm); font-size: 0.86rem; color: #78350f;">
              <strong>📋 Coordinator Note:</strong> <?php echo htmlspecialchars($topMatch['notes']); ?>
            </div>
          <?php endif; ?>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.65rem; align-items: flex-end;">
          <span class="badge badge-pending" style="font-size: 0.9rem; padding: 0.55rem 1rem;">Status: Clinical Review</span>
          <a href="<?php echo BASE_URL; ?>/dossier.php?id=<?php echo $topMatch['id']; ?>" class="btn btn-outline btn-sm" style="font-size: 0.88rem; font-weight: 700; background: #ffffff;">
            <span>📄</span> View Match Dossier
          </a>
        </div>
      </div>

    <?php else: /* Potential */ ?>
      <div style="background: #f0fdfa; border: 1px solid #99f6e4; color: #0f766e; padding: 1.5rem 1.85rem; border-radius: var(--radius-xl); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 4px 15px -2px rgba(13, 148, 136, 0.08);">
        <div style="max-width: 720px;">
          <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: #ccfbf1; color: #0f766e; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 800; margin-bottom: 0.5rem; text-transform: uppercase;">
            <span>⚡</span> YOU GOT A PRELIMINARY DONOR MATCH!
          </div>
          <h3 style="font-size: 1.35rem; font-weight: 800; color: #115e59; margin-bottom: 0.35rem; line-height: 1.3;">
            Compatible Donor Identified by Matching Engine
          </h3>
          <p style="font-size: 0.94rem; margin-bottom: 0.5rem; line-height: 1.5;">
            A preliminary compatible donor pledge has been matched with your profile (Compatibility Score: <strong><?php echo $topMatch['compatibility_score']; ?>%</strong>). Our transplant coordination desk is preparing clinical review.
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
  <?php if ($recipient['verification_status'] === 'Pending'): ?>
    <div class="verification-banner pending">
      <div>
        <h3 style="font-size: 1.15rem; margin-bottom: 0.25rem;">⏳ Profile Awaiting Verification</h3>
        <p style="font-size: 0.92rem; margin-bottom: 0;">
          Your organ requirement has been registered and is pending verification of attending hospital records by our coordinators. Preliminary matches will be evaluated once verified.
        </p>
        <?php if (!empty($recipient['admin_notes'])): ?>
          <div style="margin-top: 0.75rem; padding: 0.6rem 0.85rem; background: rgba(255, 255, 255, 0.8); border-radius: var(--radius-sm); border-left: 3px solid #f59e0b; font-size: 0.85rem; color: #92400e;">
            <strong>📋 Administrator Review Note:</strong> <?php echo htmlspecialchars($recipient['admin_notes']); ?>
          </div>
        <?php endif; ?>
      </div>
      <span class="badge badge-pending" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">Status: Pending Clinical Review</span>
    </div>
  <?php elseif ($recipient['verification_status'] === 'Verified'): ?>
    <div class="verification-banner verified">
      <div>
        <h3 style="font-size: 1.15rem; margin-bottom: 0.25rem;">✅ Verified Patient Requirement</h3>
        <p style="font-size: 0.92rem; margin-bottom: 0;">
          Your medical requirement and attending hospital credentials have been verified. Your case is actively monitored for compatible donor pledges.
        </p>
        <?php if (!empty($recipient['admin_notes'])): ?>
          <div style="margin-top: 0.75rem; padding: 0.6rem 0.85rem; background: rgba(255, 255, 255, 0.85); border-radius: var(--radius-sm); border-left: 3px solid #10b981; font-size: 0.85rem; color: #065f46;">
            <strong>📋 Administrator Verification Note:</strong> <?php echo htmlspecialchars($recipient['admin_notes']); ?>
          </div>
        <?php endif; ?>
      </div>
      <span class="badge badge-verified" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">Status: Verified Waitlist</span>
    </div>
  <?php else: ?>
    <div class="verification-banner rejected">
      <div>
        <h3 style="font-size: 1.15rem; margin-bottom: 0.25rem;">⚠️ Registration Requires Attention</h3>
        <p style="font-size: 0.92rem; margin-bottom: 0;">
          Coordinator notes: <?php echo htmlspecialchars($recipient['admin_notes'] ?? 'Please provide updated medical records or hospital referral letter.'); ?>
        </p>
      </div>
      <span class="badge badge-rejected" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">Status: Review Required</span>
    </div>
  <?php endif; ?>

  <div class="dashboard-grid-2col">
    
    <!-- Left Column: Requirement Profile & Editable Details -->
    <div>
      <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h2>Clinical Requirement Profile</h2>
            <p>Attending physician and contact information.</p>
          </div>
          <?php 
            $urgency = $recipient['urgency_level'];
            $urgencyClass = ($urgency === 'Critical') ? 'badge-critical' : (($urgency === 'High') ? 'badge-high' : 'badge-medium');
          ?>
          <span class="badge <?php echo $urgencyClass; ?>" style="font-size: 0.85rem;">
            Priority: <?php echo htmlspecialchars($urgency); ?>
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
            <span class="profile-item-label">Patient Name</span>
            <span class="profile-item-value"><?php echo htmlspecialchars($recipient['full_name']); ?></span>
          </div>
          <div class="profile-item">
            <span class="profile-item-label">Age &amp; Gender</span>
            <span class="profile-item-value"><?php echo htmlspecialchars($recipient['age']); ?> yrs, <?php echo htmlspecialchars($recipient['gender']); ?></span>
          </div>
          <div class="profile-item">
            <span class="profile-item-label">Patient Blood Group</span>
            <span class="profile-item-value" style="color: var(--danger); font-size: 1.2rem;"><?php echo htmlspecialchars($recipient['blood_group']); ?></span>
          </div>
          <div class="profile-item">
            <span class="profile-item-label">Organ Needed</span>
            <span class="profile-item-value" style="color: var(--secondary); font-size: 1.2rem;"><?php echo htmlspecialchars($recipient['organ_needed']); ?></span>
          </div>
        </div>

        <!-- Editable Form -->
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" data-validate="true">
          <input type="hidden" name="action" value="update_profile">
          <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="recipEmail">Contact Email (Read Only)</label>
              <input type="email" id="recipEmail" class="form-control" value="<?php echo htmlspecialchars($recipient['email']); ?>" disabled style="background-color: var(--bg-subtle);">
            </div>

            <div class="form-group">
              <label class="form-label" for="recipMobile">Mobile Phone <span class="required">*</span></label>
              <input type="tel" id="recipMobile" name="mobile" class="form-control" value="<?php echo htmlspecialchars($recipient['mobile']); ?>" required>
            </div>

            <div class="form-group col-span-2">
              <label class="form-label" for="recipHospital">Attending Hospital &amp; City <span class="required">*</span></label>
              <input type="text" id="recipHospital" name="hospital_city" class="form-control" value="<?php echo htmlspecialchars($recipient['hospital_city']); ?>" required>
              <span class="form-help">Where transplant team is prepared to receive organ transport.</span>
            </div>

            <div class="form-group col-span-2">
              <label class="form-label" for="recipNotes">Clinical Diagnostic Notes &amp; Updates</label>
              <textarea id="recipNotes" name="medical_notes" class="form-control" rows="3"><?php echo htmlspecialchars($recipient['medical_notes'] ?? ''); ?></textarea>
              <span class="form-help">Update dialysis status, MELD score, or attending physician changes.</span>
            </div>
          </div>

          <div style="margin-top: 1.5rem; text-align: right;">
            <button type="submit" class="btn btn-secondary">Save Details</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Right Column: Anonymous Preliminary Match Updates -->
    <div>
      <div class="card">
        <div class="card-header">
          <h2>Preliminary Match Updates</h2>
          <p>Potential compatible donors identified by algorithm.</p>
        </div>

        <?php if (!empty($recipientMatches)): ?>
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($recipientMatches as $rm): ?>
              <div style="background: var(--bg-page); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.35rem; transition: all var(--transition-fast);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                  <div>
                    <div style="font-family: monospace; font-size: 0.76rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.15rem;">
                      REF: LB-MATCH-<?php echo str_pad($rm['id'], 6, '0', STR_PAD_LEFT); ?>
                    </div>
                    <strong style="font-size: 1.05rem; color: var(--text-main); display: block;">
                      Compatible <?php echo htmlspecialchars($rm['organ']); ?> Pledge
                    </strong>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">
                      Matched: <?php echo date('M d, Y', strtotime($rm['matched_at'])); ?>
                      <?php if (!empty($rm['updated_at']) && $rm['updated_at'] !== $rm['matched_at']): ?>
                        &bull; Updated: <?php echo date('M d, Y &bull; H:i', strtotime($rm['updated_at'])); ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <span class="badge <?php echo ($rm['status'] === 'Approved') ? 'badge-approved' : (($rm['status'] === 'Contacted' || $rm['status'] === 'Under Review') ? 'badge-review' : 'badge-potential'); ?>">
                    <?php echo htmlspecialchars($rm['status']); ?>
                  </span>
                </div>

                <div style="background: #ffffff; padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--border); margin: 0.75rem 0;">
                  <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    <span style="font-weight: 600; color: var(--text-main);">Compatibility Score:</span>
                    <strong style="color: var(--primary); font-size: 1.05rem;"><?php echo $rm['compatibility_score']; ?>%</strong>
                  </div>
                  <div style="height: 6px; background: #e2e8f0; border-radius: var(--radius-full); overflow: hidden; margin-bottom: 0.4rem;">
                    <div style="width: <?php echo min(100, max(5, $rm['compatibility_score'])); ?>%; height: 100%; background: linear-gradient(90deg, var(--secondary), var(--primary)); border-radius: var(--radius-full);"></div>
                  </div>
                  <div style="font-size: 0.78rem; color: var(--text-muted);">
                    Transplant coordinators and hospital doctors are conducting preliminary immunohematological review.
                  </div>
                </div>

                <?php if (!empty($rm['notes'])): ?>
                  <div style="background: #f0fdfa; border-left: 3px solid var(--secondary); padding: 0.6rem 0.85rem; border-radius: var(--radius-sm); margin: 0.65rem 0; font-size: 0.82rem; color: #0284c7;">
                    <strong>📋 Coordinator Decision Note:</strong> <?php echo htmlspecialchars($rm['notes']); ?>
                  </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                  <span style="font-size: 0.78rem; color: var(--text-muted);">
                    🔒 <em>Donor identity confidential</em>
                  </span>
                  <a href="<?php echo BASE_URL; ?>/dossier.php?id=<?php echo $rm['id']; ?>" class="btn btn-outline-primary btn-sm" style="font-size: 0.82rem; padding: 0.35rem 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <span>📄</span> View Match Dossier
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">🩺</div>
            <h4 style="color: var(--text-main); font-size: 1.05rem; margin-bottom: 0.25rem;">No Current Compatible Pledges</h4>
            <p style="font-size: 0.875rem;">
              <?php if ($recipient['verification_status'] === 'Verified'): ?>
                Our matching engine actively scans newly registered donors. Potential matches will appear here automatically when identified.
              <?php else: ?>
                Your requirement is pending verification before algorithmic matching begins.
              <?php endif; ?>
            </p>
          </div>
        <?php endif; ?>

        <!-- Support Hotline -->
        <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-subtle); border-radius: var(--radius-md); font-size: 0.82rem; color: var(--text-muted);">
          <strong>Need urgent medical coordination?</strong> Contact your attending hospital transplant team or call the toll-free coordinator desk at <strong>1800-11-4770</strong>.
        </div>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
