<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Donor Registration Page
 */

$page_title = "Register as Organ Donor";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$success_message = '';
$form_data = [
    'full_name'           => '',
    'age'                 => '',
    'gender'              => '',
    'email'               => '',
    'mobile'              => '',
    'address_city'        => '',
    'blood_group'         => '',
    'organ_donated'       => '',
    'availability_status' => 'Available',
    'medical_notes'       => '',
    'consent_given'       => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session security token expired. Please try submitting again.';
    }

    // Sanitize inputs
    $form_data['full_name']           = trim($_POST['full_name'] ?? '');
    $form_data['age']                 = (int)($_POST['age'] ?? 0);
    $form_data['gender']              = trim($_POST['gender'] ?? '');
    $form_data['email']               = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $form_data['mobile']              = trim($_POST['mobile'] ?? '');
    $form_data['address_city']        = trim($_POST['address_city'] ?? '');
    $form_data['blood_group']         = trim($_POST['blood_group'] ?? '');
    $form_data['organ_donated']       = trim($_POST['organ_donated'] ?? '');
    $form_data['availability_status'] = trim($_POST['availability_status'] ?? 'Available');
    $form_data['medical_notes']       = trim($_POST['medical_notes'] ?? '');
    $form_data['consent_given']       = isset($_POST['consent_given']) ? 1 : 0;
    $password                         = $_POST['password'] ?? '';
    $confirm_password                 = $_POST['confirm_password'] ?? '';

    // Server-side Validations
    if (empty($form_data['full_name']) || strlen($form_data['full_name']) < 2) {
        $errors['full_name'] = 'Full Name is required (minimum 2 characters).';
    }

    if ($form_data['age'] < 18 || $form_data['age'] > 85) {
        $errors['age'] = 'Donors must be between 18 and 85 years old.';
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

    if (empty($form_data['address_city'])) {
        $errors['address_city'] = 'City and address details are required for proximity matching.';
    }

    if (!in_array($form_data['blood_group'], $GLOBALS['BLOOD_GROUPS'], true)) {
        $errors['blood_group'] = 'Please select a recognized blood group.';
    }

    if (!in_array($form_data['organ_donated'], $GLOBALS['SUPPORTED_ORGANS'], true)) {
        $errors['organ_donated'] = 'Please select an eligible organ to pledge.';
    }

    if (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Password confirmation does not match.';
    }

    if (!$form_data['consent_given']) {
        $errors['consent_given'] = 'You must review and agree to the consent declaration.';
    }

    // Check if email already exists in users table
    if (empty($errors)) {
        $pdo = get_db_connection();
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$form_data['email']]);
        if ($checkStmt->fetch()) {
            $errors['email'] = 'An account with this email address is already registered. Please log in instead.';
        }
    }

    // If all validation passes, store into database inside transaction
    if (empty($errors)) {
        try {
            $pdo = get_db_connection();
            $pdo->beginTransaction();

            // 1. Create User account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $userStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'donor', NOW())");
            $userStmt->execute([$form_data['full_name'], $form_data['email'], $password_hash]);
            $user_id = $pdo->lastInsertId();

            // 2. Insert Donor Record with 'Pending' verification status
            $donorStmt = $pdo->prepare("INSERT INTO donors 
                (user_id, full_name, age, gender, email, mobile, address_city, blood_group, organ_donated, availability_status, medical_notes, consent_given, verification_status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
            $donorStmt->execute([
                $user_id,
                $form_data['full_name'],
                $form_data['age'],
                $form_data['gender'],
                $form_data['email'],
                $form_data['mobile'],
                $form_data['address_city'],
                $form_data['blood_group'],
                $form_data['organ_donated'],
                $form_data['availability_status'],
                $form_data['medical_notes'],
                $form_data['consent_given']
            ]);

            // 3. Log into Admin audit trail
            $donor_id = $pdo->lastInsertId();
            $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, description, created_at) VALUES (NULL, 'Donor Registration', ?, NOW())");
            $logStmt->execute(["New donor #{$donor_id} ({$form_data['full_name']}, {$form_data['blood_group']}, {$form_data['organ_donated']}) registered. Pending verification."]);

            $pdo->commit();

            $success_message = "Thank you, {$form_data['full_name']}! Your organ donation pledge has been securely recorded. Your registration is currently <strong>Pending Administrator Verification</strong>. You can now log into your donor dashboard using your registered email and password to track your verification status.";
            
            // Reset form
            $form_data = array_fill_keys(array_keys($form_data), '');
            $form_data['availability_status'] = 'Available';

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'A database error occurred while processing your registration: ' . htmlspecialchars($e->getMessage());
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-narrow" style="padding-top: 3rem; padding-bottom: 4rem;">
  
  <!-- Breadcrumb -->
  <div style="margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--text-muted);">
    <a href="<?php echo BASE_URL; ?>/index.php">Home</a> &gt; <span>Donor Registration</span>
  </div>

  <!-- Medical & Legal Notice -->
  <div class="disclaimer-banner info-style">
    <div class="icon">ℹ️</div>
    <div>
      <h4>Voluntary Pledge &amp; Preliminary Screening</h4>
      <p>
        Registering as a donor expresses your generous intent to donate organs or tissue. All records undergo administrative verification and preliminary compatibility matching. Final procurement involves formal clinical and legal procedures.
      </p>
    </div>
  </div>

  <?php if ($success_message): ?>
    <div class="card" style="border-left: 5px solid var(--success); background-color: #f0fdf4;">
      <div style="display: flex; align-items: flex-start; gap: 1rem;">
        <div style="font-size: 2.2rem; color: var(--success); line-height: 1;">✅</div>
        <div>
          <h2 style="font-size: 1.4rem; color: #166534; margin-bottom: 0.5rem;">Registration Submitted Successfully</h2>
          <p style="color: #15803d; font-size: 0.95rem; line-height: 1.6;">
            <?php echo $success_message; ?>
          </p>
          <div style="margin-top: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary">Proceed to Donor Sign In &rarr;</a>
            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline">Back to Home</a>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>

    <div class="card">
      <div class="card-header">
        <h2>Register as an Organ Donor</h2>
        <p>Fill in your basic information and organ pledge. Every required input is securely encrypted.</p>
      </div>

      <?php if (!empty($errors) && is_array($errors)): ?>
        <div class="flash-alert error" style="margin-bottom: 1.5rem; position: static; max-width: 100%;">
          <span><?php $firstErr = reset($errors); echo htmlspecialchars($firstErr); ?></span>
        </div>
      <?php endif; ?>

      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" data-validate="true" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1.25rem;">
          <span class="badge badge-verified" style="font-size: 0.8rem;">Step 1</span>
          <h3 style="font-size: 1.15rem; color: var(--text-main); margin: 0;">
            Personal &amp; Contact Information
          </h3>
        </div>

        <div class="form-grid">
          <!-- Full Name -->
          <div class="form-group col-span-2">
            <label class="form-label" for="full_name">Full Legal Name <span class="required">*</span></label>
            <input type="text" id="full_name" name="full_name" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['full_name']); ?>" placeholder="e.g. Adithya Nair" required>
            <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?php echo $errors['full_name']; ?></div><?php endif; ?>
          </div>

          <!-- Age -->
          <div class="form-group">
            <label class="form-label" for="age">Age (Years) <span class="required">*</span></label>
            <input type="number" id="age" name="age" min="18" max="85" class="form-control <?php echo isset($errors['age']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars((string)$form_data['age']); ?>" placeholder="18-85" required>
            <span class="form-help">Donors must be at least 18 years old.</span>
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
            <label class="form-label" for="email">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['email']); ?>" placeholder="you@example.com" required>
            <span class="form-help">Used for account sign-in &amp; status notifications.</span>
            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
          </div>

          <!-- Mobile -->
          <div class="form-group">
            <label class="form-label" for="mobile">Mobile Number <span class="required">*</span></label>
            <input type="tel" id="mobile" name="mobile" class="form-control <?php echo isset($errors['mobile']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['mobile']); ?>" placeholder="+91 9876543210" required>
            <?php if (isset($errors['mobile'])): ?><div class="invalid-feedback"><?php echo $errors['mobile']; ?></div><?php endif; ?>
          </div>

          <!-- Address / City -->
          <div class="form-group col-span-2">
            <label class="form-label" for="address_city">Current Residential City &amp; State <span class="required">*</span></label>
            <input type="text" id="address_city" name="address_city" class="form-control <?php echo isset($errors['address_city']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['address_city']); ?>" placeholder="e.g. Kochi, Kerala" required>
            <span class="form-help">Used by the matching engine to calculate regional proximity score.</span>
            <?php if (isset($errors['address_city'])): ?><div class="invalid-feedback"><?php echo $errors['address_city']; ?></div><?php endif; ?>
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.6rem; margin: 2.25rem 0 1.25rem 0; padding-top: 1.5rem; border-top: 1px solid var(--border);">
          <span class="badge badge-verified" style="font-size: 0.8rem;">Step 2</span>
          <h3 style="font-size: 1.15rem; color: var(--text-main); margin: 0;">
            Organ Pledge &amp; Biological Compatibility
          </h3>
        </div>

        <div class="form-grid">
          <!-- Blood Group (Interactive Chips) -->
          <div class="form-group col-span-2">
            <label class="form-label">Blood Group <span class="required">*</span></label>
            <input type="hidden" id="blood_group" name="blood_group" value="<?php echo htmlspecialchars($form_data['blood_group']); ?>" required>
            <div class="chip-selector-grid" data-target-input="blood_group">
              <?php foreach ($GLOBALS['BLOOD_GROUPS'] as $bg): ?>
                <div class="selector-chip <?php echo ($form_data['blood_group'] === $bg) ? 'active' : ''; ?>" data-value="<?php echo $bg; ?>">
                  <?php echo $bg; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <span class="form-help">Click your verified blood group above.</span>
            <?php if (isset($errors['blood_group'])): ?><div class="invalid-feedback"><?php echo $errors['blood_group']; ?></div><?php endif; ?>
          </div>

          <!-- Organ Willing to Donate (Interactive Cards) -->
          <div class="form-group col-span-2">
            <label class="form-label">Organ / Tissue Willing to Donate <span class="required">*</span></label>
            <input type="hidden" id="organ_donated" name="organ_donated" value="<?php echo htmlspecialchars($form_data['organ_donated']); ?>" required>
            <div class="chip-selector-grid" data-target-input="organ_donated" style="grid-template-columns: repeat(3, 1fr);">
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
                <div class="selector-chip <?php echo ($form_data['organ_donated'] === $organ) ? 'active' : ''; ?>" data-value="<?php echo $organ; ?>" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.95rem;">
                  <span><?php echo $organEmoji; ?></span>
                  <span><?php echo $organ; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <span class="form-help">Click the primary organ or tissue you wish to pledge.</span>
            <?php if (isset($errors['organ_donated'])): ?><div class="invalid-feedback"><?php echo $errors['organ_donated']; ?></div><?php endif; ?>
          </div>

          <!-- Availability Status -->
          <div class="form-group col-span-2">
            <label class="form-label" for="availability_status">Current Availability Status <span class="required">*</span></label>
            <select id="availability_status" name="availability_status" class="form-control" required>
              <option value="Available" <?php echo ($form_data['availability_status'] === 'Available') ? 'selected' : ''; ?>>Available (Active Pledge in Matching Engine)</option>
              <option value="Temporarily Unavailable" <?php echo ($form_data['availability_status'] === 'Temporarily Unavailable') ? 'selected' : ''; ?>>Temporarily Unavailable (Deferred due to travel or health)</option>
            </select>
          </div>

          <!-- Medical Notes -->
          <div class="form-group col-span-2">
            <label class="form-label" for="medical_notes">Basic Health Declaration / Medical Notes</label>
            <textarea id="medical_notes" name="medical_notes" class="form-control" rows="3" placeholder="Describe any relevant health history, routine fitness habits, or non-smoker declaration..."><?php echo htmlspecialchars($form_data['medical_notes']); ?></textarea>
            <span class="form-help">Optional: Helps transplant coordinators perform preliminary medical fitness triage.</span>
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.6rem; margin: 2.25rem 0 1.25rem 0; padding-top: 1.5rem; border-top: 1px solid var(--border);">
          <span class="badge badge-verified" style="font-size: 0.8rem;">Step 3</span>
          <h3 style="font-size: 1.15rem; color: var(--text-main); margin: 0;">
            Security &amp; Legal Consent
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
          <input type="checkbox" id="consent_given" name="consent_given" value="1" <?php echo $form_data['consent_given'] ? 'checked' : ''; ?> required style="width: 20px; height: 20px; accent-color: var(--primary);">
          <label for="consent_given" style="font-size: 0.9rem; line-height: 1.55; color: #334155;">
            <strong>Voluntary Pledge Declaration:</strong> I hereby declare that my organ donation pledge is entirely voluntary. I understand that this web portal performs digital preliminary compatibility comparisons only, and that final medical compatibility, viral testing, legal clearance, and surgical allocation must be authorized by certified medical professionals. <span class="required">*</span>
          </label>
        </div>
        <?php if (isset($errors['consent_given'])): ?><div class="invalid-feedback" style="display: block; margin-top: -0.5rem; margin-bottom: 1rem;"><?php echo $errors['consent_given']; ?></div><?php endif; ?>

        <!-- Submit Button -->
        <div style="margin-top: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
          <a href="<?php echo BASE_URL; ?>/login.php" style="font-size: 0.92rem; font-weight: 600;">Already registered? <strong>Log In Here &rarr;</strong></a>
          <button type="submit" class="btn btn-rose btn-lg">Submit Donor Pledge &rarr;</button>
        </div>
      </form>
    </div>

  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
