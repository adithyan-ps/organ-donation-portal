<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Recipient Registration Page
 */

$page_title = "Register as Organ Recipient";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$success_message = '';
$form_data = [
    'full_name'     => '',
    'age'           => '',
    'gender'        => '',
    'email'         => '',
    'mobile'        => '',
    'hospital_city' => '',
    'blood_group'   => '',
    'organ_needed'  => '',
    'urgency_level' => 'Medium',
    'medical_notes' => '',
    'consent_given' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session security token expired. Please try submitting again.';
    }

    // Sanitize inputs
    $form_data['full_name']     = trim($_POST['full_name'] ?? '');
    $form_data['age']           = (int)($_POST['age'] ?? 0);
    $form_data['gender']        = trim($_POST['gender'] ?? '');
    $form_data['email']         = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $form_data['mobile']        = trim($_POST['mobile'] ?? '');
    $form_data['hospital_city'] = trim($_POST['hospital_city'] ?? '');
    $form_data['blood_group']   = trim($_POST['blood_group'] ?? '');
    $form_data['organ_needed']  = trim($_POST['organ_needed'] ?? '');
    $form_data['urgency_level'] = trim($_POST['urgency_level'] ?? 'Medium');
    $form_data['medical_notes'] = trim($_POST['medical_notes'] ?? '');
    $form_data['consent_given'] = isset($_POST['consent_given']) ? 1 : 0;
    $password                   = $_POST['password'] ?? '';
    $confirm_password           = $_POST['confirm_password'] ?? '';

    // Server-side Validations
    if (empty($form_data['full_name']) || strlen($form_data['full_name']) < 2) {
        $errors['full_name'] = 'Full Name is required (minimum 2 characters).';
    }

    if ($form_data['age'] < 1 || $form_data['age'] > 100) {
        $errors['age'] = 'Please enter a valid age between 1 and 100.';
    }

    if (!in_array($form_data['gender'], ['Male', 'Female', 'Other'], true)) {
        $errors['gender'] = 'Please select a valid gender option.';
    }

    if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($form_data['mobile']) || strlen(preg_replace('/[^0-9]/', '', $form_data['mobile'])) < 10) {
        $errors['mobile'] = 'A valid 10-digit mobile contact number is required.';
    }

    if (empty($form_data['hospital_city'])) {
        $errors['hospital_city'] = 'Attending hospital and city name are required for transplant allocation.';
    }

    if (!in_array($form_data['blood_group'], $GLOBALS['BLOOD_GROUPS'], true)) {
        $errors['blood_group'] = 'Please select a recognized recipient blood group.';
    }

    if (!in_array($form_data['organ_needed'], $GLOBALS['SUPPORTED_ORGANS'], true)) {
        $errors['organ_needed'] = 'Please select the organ or tissue needed.';
    }

    if (!array_key_exists($form_data['urgency_level'], $GLOBALS['URGENCY_LEVELS'])) {
        $errors['urgency_level'] = 'Please select a valid clinical urgency level.';
    }

    if (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Password confirmation does not match.';
    }

    if (!$form_data['consent_given']) {
        $errors['consent_given'] = 'You must review and accept the medical waitlist declaration.';
    }

    // Check if email exists
    if (empty($errors)) {
        $pdo = get_db_connection();
        if (!$pdo) {
            $errors['general'] = 'Database is currently unreachable. If running on Vercel, please connect your cloud MySQL provider via DATABASE_URL.';
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$form_data['email']]);
            if ($checkStmt->fetch()) {
                $errors['email'] = 'An account with this email address already exists. Please log in instead.';
            }
        }
    }

    // Insert recipient and user record
    if (empty($errors)) {
        try {
            $pdo = get_db_connection();
            $pdo->beginTransaction();

            // 1. Create user account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $userStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'recipient', NOW())");
            $userStmt->execute([$form_data['full_name'], $form_data['email'], $password_hash]);
            $user_id = $pdo->lastInsertId();

            // 2. Insert Recipient with 'Pending' verification status
            $recipStmt = $pdo->prepare("INSERT INTO recipients 
                (user_id, full_name, age, gender, email, mobile, hospital_city, blood_group, organ_needed, urgency_level, medical_notes, consent_given, verification_status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
            $recipStmt->execute([
                $user_id,
                $form_data['full_name'],
                $form_data['age'],
                $form_data['gender'],
                $form_data['email'],
                $form_data['mobile'],
                $form_data['hospital_city'],
                $form_data['blood_group'],
                $form_data['organ_needed'],
                $form_data['urgency_level'],
                $form_data['medical_notes'],
                $form_data['consent_given']
            ]);

            // 3. Log into Admin audit trail
            $recip_id = $pdo->lastInsertId();
            $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, description, created_at) VALUES (NULL, 'Recipient Registration', ?, NOW())");
            $logStmt->execute(["New recipient #{$recip_id} ({$form_data['full_name']}, Organ: {$form_data['organ_needed']}, Urgency: {$form_data['urgency_level']}) registered. Pending verification."]);

            $pdo->commit();

            $success_message = "Registration successful for {$form_data['full_name']}. Your organ transplant requirement has been recorded with status <strong>Pending Administrator Verification</strong>. Once your attending hospital details are verified by our clinical coordinators, your case will automatically appear in the preliminary matching engine.";

            // Reset form
            $form_data = array_fill_keys(array_keys($form_data), '');
            $form_data['urgency_level'] = 'Medium';

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'A database error occurred while registering recipient: ' . htmlspecialchars($e->getMessage());
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-narrow" style="padding-top: 3rem; padding-bottom: 4rem;">
  
  <!-- Breadcrumb -->
  <div style="margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--text-muted);">
    <a href="<?php echo BASE_URL; ?>/index.php">Home</a> &gt; <span>Recipient Registration</span>
  </div>

  <!-- Medical & Legal Notice -->
  <div class="disclaimer-banner info-style">
    <div class="icon">ℹ️</div>
    <div>
      <h4>Preliminary Waitlist Registry Notice</h4>
      <p>
        Registration on this portal identifies potential compatibility with registered donors. It does not replace official national or hospital transplant waitlists. Clinical triage and official surgical scheduling remain the exclusive domain of licensed medical centers.
      </p>
    </div>
  </div>

  <?php if ($success_message): ?>
    <div class="card" style="border-left: 5px solid var(--secondary); background-color: #f0fdfa;">
      <div style="display: flex; align-items: flex-start; gap: 1rem;">
        <div style="font-size: 2.2rem; color: var(--secondary); line-height: 1;">📋</div>
        <div>
          <h2 style="font-size: 1.4rem; color: var(--secondary-dark); margin-bottom: 0.5rem;">Requirement Registered Successfully</h2>
          <p style="color: #047857; font-size: 0.95rem; line-height: 1.6;">
            <?php echo $success_message; ?>
          </p>
          <div style="margin-top: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-secondary">Sign In to Recipient Portal &rarr;</a>
            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline">Back to Home</a>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>

    <div class="card">
      <div class="card-header">
        <h2>Register an Organ Requirement</h2>
        <p>Submit recipient details for algorithmic preliminary compatibility matching.</p>
      </div>

      <?php if (!empty($errors) && is_array($errors)): ?>
        <div class="flash-alert error" style="margin-bottom: 1.5rem; position: static; max-width: 100%;">
          <span><?php $firstErr = reset($errors); echo htmlspecialchars($firstErr); ?></span>
        </div>
      <?php endif; ?>

      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" data-validate="true" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1.25rem;">
          <span class="badge badge-potential" style="font-size: 0.8rem;">Step 1</span>
          <h3 style="font-size: 1.15rem; color: var(--text-main); margin: 0;">
            Patient &amp; Hospital Details
          </h3>
        </div>

        <div class="form-grid">
          <!-- Full Name -->
          <div class="form-group col-span-2">
            <label class="form-label" for="full_name">Patient Full Legal Name <span class="required">*</span></label>
            <input type="text" id="full_name" name="full_name" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['full_name']); ?>" placeholder="e.g. David Chen" required>
            <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?php echo $errors['full_name']; ?></div><?php endif; ?>
          </div>

          <!-- Age -->
          <div class="form-group">
            <label class="form-label" for="age">Patient Age (Years) <span class="required">*</span></label>
            <input type="number" id="age" name="age" min="1" max="100" class="form-control <?php echo isset($errors['age']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars((string)$form_data['age']); ?>" placeholder="Age in years" required>
            <?php if (isset($errors['age'])): ?><div class="invalid-feedback"><?php echo $errors['age']; ?></div><?php endif; ?>
          </div>

          <!-- Gender -->
          <div class="form-group">
            <label class="form-label" for="gender">Gender <span class="required">*</span></label>
            <select id="gender" name="gender" class="form-control <?php echo isset($errors['gender']) ? 'is-invalid' : ''; ?>" required>
              <option value="">-- Select Gender --</option>
              <option value="Male" <?php echo ($form_data['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
              <option value="Female" <?php echo ($form_data['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
              <option value="Other" <?php echo ($form_data['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>
            <?php if (isset($errors['gender'])): ?><div class="invalid-feedback"><?php echo $errors['gender']; ?></div><?php endif; ?>
          </div>

          <!-- Email -->
          <div class="form-group">
            <label class="form-label" for="email">Contact Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['email']); ?>" placeholder="patient.care@example.com" required>
            <span class="form-help">Used for portal access &amp; coordinator communication.</span>
            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
          </div>

          <!-- Mobile -->
          <div class="form-group">
            <label class="form-label" for="mobile">Patient / Caregiver Mobile <span class="required">*</span></label>
            <input type="tel" id="mobile" name="mobile" class="form-control <?php echo isset($errors['mobile']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['mobile']); ?>" placeholder="+91 9811223344" required>
            <?php if (isset($errors['mobile'])): ?><div class="invalid-feedback"><?php echo $errors['mobile']; ?></div><?php endif; ?>
          </div>

          <!-- Hospital / City -->
          <div class="form-group col-span-2">
            <label class="form-label" for="hospital_city">Attending Hospital &amp; City <span class="required">*</span></label>
            <input type="text" id="hospital_city" name="hospital_city" class="form-control <?php echo isset($errors['hospital_city']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['hospital_city']); ?>" placeholder="e.g. Aster Medcity, Kochi, Kerala" required>
            <span class="form-help">Hospital transplant department coordinating patient surgical care.</span>
            <?php if (isset($errors['hospital_city'])): ?><div class="invalid-feedback"><?php echo $errors['hospital_city']; ?></div><?php endif; ?>
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.6rem; margin: 2.25rem 0 1.25rem 0; padding-top: 1.5rem; border-top: 1px solid var(--border);">
          <span class="badge badge-potential" style="font-size: 0.8rem;">Step 2</span>
          <h3 style="font-size: 1.15rem; color: var(--text-main); margin: 0;">
            Organ Requirement &amp; Clinical Priority
          </h3>
        </div>

        <div class="form-grid">
          <!-- Blood Group (Interactive Chips) -->
          <div class="form-group col-span-2">
            <label class="form-label">Recipient Blood Group <span class="required">*</span></label>
            <input type="hidden" id="blood_group" name="blood_group" value="<?php echo htmlspecialchars($form_data['blood_group']); ?>" required>
            <div class="chip-selector-grid" data-target-input="blood_group">
              <?php foreach ($GLOBALS['BLOOD_GROUPS'] as $bg): ?>
                <div class="selector-chip <?php echo ($form_data['blood_group'] === $bg) ? 'active' : ''; ?>" data-value="<?php echo $bg; ?>">
                  <?php echo $bg; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <span class="form-help">Click the patient's verified laboratory blood group.</span>
            <?php if (isset($errors['blood_group'])): ?><div class="invalid-feedback"><?php echo $errors['blood_group']; ?></div><?php endif; ?>
          </div>

          <!-- Organ Needed (Interactive Cards) -->
          <div class="form-group col-span-2">
            <label class="form-label">Required Organ / Tissue <span class="required">*</span></label>
            <input type="hidden" id="organ_needed" name="organ_needed" value="<?php echo htmlspecialchars($form_data['organ_needed']); ?>" required>
            <div class="chip-selector-grid" data-target-input="organ_needed" style="grid-template-columns: repeat(3, 1fr);">
              <?php foreach ($GLOBALS['SUPPORTED_ORGANS'] as $organ): 
                $organEmoji = match(strtolower($organ)) {
                  'heart' => '🫀',
                  'kidney', 'kidneys' => '🫘',
                  'liver' => '🩸',
                  'lungs', 'lung' => '🫁',
                  'pancreas' => '🩺',
                  default => '👁️'
                };
              ?>
                <div class="selector-chip <?php echo ($form_data['organ_needed'] === $organ) ? 'active' : ''; ?>" data-value="<?php echo $organ; ?>" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.95rem;">
                  <span><?php echo $organEmoji; ?></span>
                  <span><?php echo $organ; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <span class="form-help">Click the organ type needed by the patient.</span>
            <?php if (isset($errors['organ_needed'])): ?><div class="invalid-feedback"><?php echo $errors['organ_needed']; ?></div><?php endif; ?>
          </div>

          <!-- Urgency Level -->
          <div class="form-group col-span-2">
            <label class="form-label" for="urgency_level">Medical Urgency Level <span class="required">*</span></label>
            <select id="urgency_level" name="urgency_level" class="form-control <?php echo isset($errors['urgency_level']) ? 'is-invalid' : ''; ?>" required>
              <?php foreach ($GLOBALS['URGENCY_LEVELS'] as $key => $u): ?>
                <option value="<?php echo $key; ?>" <?php echo ($form_data['urgency_level'] === $key) ? 'selected' : ''; ?>>
                  <?php echo $u['label']; ?> (+<?php echo $u['points']; ?> match score points)
                </option>
              <?php endforeach; ?>
            </select>
            <span class="form-help">Critical: Active ICU/decompensated status; High: Rapidly deteriorating; Medium: Stable on dialysis/medication; Low: Elective/early listing.</span>
          </div>

          <!-- Medical Notes -->
          <div class="form-group col-span-2">
            <label class="form-label" for="medical_notes">Clinical Diagnostic Summary &amp; Attending Physician Notes</label>
            <textarea id="medical_notes" name="medical_notes" class="form-control" rows="3" placeholder="Primary diagnosis, dialysis frequency, MELD score, attending nephrologist/cardiologist name..."><?php echo htmlspecialchars($form_data['medical_notes']); ?></textarea>
            <span class="form-help">Optional: Assists hospital review board in prioritizing preliminary compatibility evaluations.</span>
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.6rem; margin: 2.25rem 0 1.25rem 0; padding-top: 1.5rem; border-top: 1px solid var(--border);">
          <span class="badge badge-potential" style="font-size: 0.8rem;">Step 3</span>
          <h3 style="font-size: 1.15rem; color: var(--text-main); margin: 0;">
            Security &amp; Waitlist Consent
          </h3>
        </div>

        <div class="form-grid">
          <!-- Password -->
          <div class="form-group">
            <label class="form-label" for="password">Create Portal Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" placeholder="At least 6 characters" required>
            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?>
          </div>

          <!-- Confirm Password -->
          <div class="form-group">
            <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" placeholder="Re-type password" required>
            <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div><?php endif; ?>
          </div>
        </div>

        <!-- Consent Checkbox -->
        <div class="form-check" style="background: var(--bg-subtle); padding: 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border); margin: 1.5rem 0;">
          <input type="checkbox" id="consent_given" name="consent_given" value="1" <?php echo $form_data['consent_given'] ? 'checked' : ''; ?> required style="width: 20px; height: 20px; accent-color: var(--secondary);">
          <label for="consent_given" style="font-size: 0.9rem; line-height: 1.55; color: #334155;">
            <strong>Consent &amp; Clinical Authorization:</strong> I authorize the portal administrator and medical coordinators to compare these requirements against registered donors. I acknowledge that all matches are preliminary digital screenings and do not constitute surgical guarantees or medical clearance. <span class="required">*</span>
          </label>
        </div>
        <?php if (isset($errors['consent_given'])): ?><div class="invalid-feedback" style="display: block; margin-top: -0.5rem; margin-bottom: 1rem;"><?php echo $errors['consent_given']; ?></div><?php endif; ?>

        <!-- Submit Button -->
        <div style="margin-top: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
          <a href="<?php echo BASE_URL; ?>/login.php" style="font-size: 0.92rem; font-weight: 600;">Already registered? <strong>Log In Here &rarr;</strong></a>
          <button type="submit" class="btn btn-secondary btn-lg">Submit Recipient Requirement &rarr;</button>
        </div>
      </form>
    </div>

  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
