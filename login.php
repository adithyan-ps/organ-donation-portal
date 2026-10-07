<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Unified Authentication & Login Portal
 */

$page_title = "Sign In to Portal";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') {
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    } elseif ($role === 'donor') {
        header('Location: ' . BASE_URL . '/donor_dashboard.php');
        exit;
    } elseif ($role === 'recipient') {
        header('Location: ' . BASE_URL . '/recipient_dashboard.php');
        exit;
    }
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = $_POST['password'] ?? '';
        $expected_role = trim($_POST['role'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            try {
                $pdo = get_db_connection();
                if (!$pdo) {
                    throw new Exception("Database is currently unreachable. If running on Vercel, please connect your cloud MySQL provider via DATABASE_URL.");
                }
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Optional role check if user selected a specific tab
                    if (!empty($expected_role) && $user['role'] !== $expected_role) {
                        $error = "This account is registered as a " . ucfirst($user['role']) . ", not as a " . ucfirst($expected_role) . ".";
                    } else {
                        // Successful login
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];

                        set_flash_message('success', "Welcome back, {$user['name']}! You have signed in as " . ucfirst($user['role']) . ".");

                        if ($user['role'] === 'admin') {
                            header('Location: ' . BASE_URL . '/admin/index.php');
                        } elseif ($user['role'] === 'donor') {
                            header('Location: ' . BASE_URL . '/donor_dashboard.php');
                        } elseif ($user['role'] === 'recipient') {
                            header('Location: ' . BASE_URL . '/recipient_dashboard.php');
                        } else {
                            header('Location: ' . BASE_URL . '/index.php');
                        }
                        exit;
                    }
                } else {
                    $error = 'Invalid email address or password. Please verify your credentials.';
                }
            } catch (Exception $e) {
                $error = 'Database connection error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-narrow" style="padding-top: 4rem; padding-bottom: 5rem;">
  
  <!-- Bespoke Modern Sign In Card -->
  <div class="card" style="max-width: 520px; margin: 0 auto; box-shadow: 0 20px 50px -10px rgba(15, 23, 42, 0.12); border-radius: var(--radius-xl); border: 1px solid rgba(226, 232, 240, 0.9); overflow: hidden; position: relative;">
    
    <!-- Top Gradient Bar -->
    <div style="position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent-rose));"></div>

    <div style="text-align: center; margin-bottom: 1.75rem; padding-top: 0.5rem;">
      <a href="<?php echo BASE_URL; ?>/index.php" style="display: inline-block; text-decoration: none;">
        <img src="<?php echo BASE_URL; ?>/assets/images/logo.svg" alt="Portal Logo" width="58" height="58" style="margin-bottom: 0.85rem; transition: transform var(--transition-normal);">
      </a>
      <h2 style="font-size: 1.75rem; margin-bottom: 0.35rem; letter-spacing: -0.03em;">Portal Sign In</h2>
      <p style="color: var(--text-muted); font-size: 0.92rem; max-width: 380px; margin: 0 auto;">
        Access your dashboard, manage health pledges, or evaluate clinical compatibility.
      </p>
    </div>

    <?php if ($error): ?>
      <div class="flash-alert error" style="margin-bottom: 1.5rem; position: static; max-width: 100%;">
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <!-- 1-Click Evaluation Accounts Pill Switcher -->
    <div style="background: var(--bg-subtle); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1rem; margin-bottom: 1.75rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
        <span style="font-size: 0.8rem; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.05em;">
          ⚡ 1-Click Demo Logins
        </span>
        <span style="font-size: 0.75rem; color: var(--primary); font-weight: 600;">Academic Evaluation</span>
      </div>
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem;" id="roleDemoPills">
        <button type="button" class="btn btn-outline btn-sm active" id="pillAdmin" onclick="selectRoleDemo('admin', 'admin@organportal.com', 'admin123')" style="font-size: 0.82rem; font-weight: 700; border-radius: var(--radius-md);">
          👑 Admin
        </button>
        <button type="button" class="btn btn-outline btn-sm" id="pillDonor" onclick="selectRoleDemo('donor', 'donor@demo.com', 'donor123')" style="font-size: 0.82rem; font-weight: 700; border-radius: var(--radius-md);">
          ❤️ Donor
        </button>
        <button type="button" class="btn btn-outline btn-sm" id="pillRecipient" onclick="selectRoleDemo('recipient', 'recipient@demo.com', 'recipient123')" style="font-size: 0.82rem; font-weight: 700; border-radius: var(--radius-md);">
          📋 Recipient
        </button>
      </div>
    </div>

    <!-- Login Form -->
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" data-validate="true" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="role" id="selectedRole" value="admin">

      <!-- Email Field -->
      <div class="form-group" style="margin-bottom: 1.25rem;">
        <label class="form-label" for="loginEmail">Email Address <span class="required">*</span></label>
        <div style="position: relative;">
          <input type="email" id="loginEmail" name="email" class="form-control" value="<?php echo htmlspecialchars($email ?: 'admin@organportal.com'); ?>" placeholder="e.g. admin@organportal.com" required autofocus style="padding-left: 2.6rem;">
          <span style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); font-size: 1.1rem; color: #94a3b8; pointer-events: none;">✉️</span>
        </div>
      </div>

      <!-- Password Field -->
      <div class="form-group" style="margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <label class="form-label" for="loginPassword" style="margin-bottom: 0;">Password <span class="required">*</span></label>
          <span id="roleBadgeTag" class="badge badge-low" style="font-size: 0.72rem;">Role: Admin</span>
        </div>
        <div style="position: relative; margin-top: 0.45rem;">
          <input type="password" id="loginPassword" name="password" class="form-control" value="admin123" placeholder="Enter your password" required style="padding-left: 2.6rem; padding-right: 2.6rem;">
          <span style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); font-size: 1.1rem; color: #94a3b8; pointer-events: none;">🔒</span>
          <button type="button" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #94a3b8; font-size: 1.1rem; padding: 0.25rem;">
            👁️
          </button>
        </div>
      </div>

      <div style="margin: 1.75rem 0 1rem 0;">
        <button type="submit" id="loginSubmitBtn" class="btn btn-primary btn-block btn-lg">
          Sign In to Dashboard &rarr;
        </button>
      </div>
    </form>

    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border); text-align: center; font-size: 0.9rem; color: var(--text-muted);">
      Need to register a new health profile?
      <div style="margin-top: 0.65rem; display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
        <a href="<?php echo BASE_URL; ?>/donor_register.php" style="font-weight: 700; color: var(--accent-rose-dark);">❤️ Register as Donor</a>
        <span>&bull;</span>
        <a href="<?php echo BASE_URL; ?>/recipient_register.php" style="font-weight: 700; color: var(--secondary);">🔍 Register as Recipient</a>
      </div>
    </div>
  </div>

  <!-- Medical & Cryptographic Notice -->
  <div style="max-width: 520px; margin: 1.75rem auto 0 auto; text-align: center; font-size: 0.82rem; color: var(--text-muted); line-height: 1.5;">
    🛡️ Session credentials protected with SHA-256 and PHP bcrypt encryption. All access restricted by role privileges.
  </div>

</div>

<script>
function selectRoleDemo(role, email, pass) {
  document.getElementById('loginEmail').value = email;
  document.getElementById('loginPassword').value = pass;
  document.getElementById('selectedRole').value = role;

  // Toggle active button style
  document.querySelectorAll('#roleDemoPills button').forEach(b => {
    b.classList.remove('active', 'btn-primary', 'btn-rose', 'btn-secondary');
    b.classList.add('btn-outline');
  });

  const btn = document.getElementById('pill' + role.charAt(0).toUpperCase() + role.slice(1));
  const badge = document.getElementById('roleBadgeTag');
  const submitBtn = document.getElementById('loginSubmitBtn');

  if (role === 'admin') {
    if (btn) { btn.classList.remove('btn-outline'); btn.classList.add('active'); }
    if (badge) { badge.className = 'badge badge-low'; badge.textContent = 'Role: Admin'; }
    if (submitBtn) { submitBtn.className = 'btn btn-primary btn-block btn-lg'; }
  } else if (role === 'donor') {
    if (btn) { btn.classList.remove('btn-outline'); btn.classList.add('active'); }
    if (badge) { badge.className = 'badge badge-approved'; badge.textContent = 'Role: Donor'; }
    if (submitBtn) { submitBtn.className = 'btn btn-rose btn-block btn-lg'; }
  } else if (role === 'recipient') {
    if (btn) { btn.classList.remove('btn-outline'); btn.classList.add('active'); }
    if (badge) { badge.className = 'badge badge-potential'; badge.textContent = 'Role: Recipient'; }
    if (submitBtn) { submitBtn.className = 'btn btn-secondary btn-block btn-lg'; }
  }
}

function togglePasswordVisibility() {
  const input = document.getElementById('loginPassword');
  if (input.type === 'password') {
    input.type = 'text';
  } else {
    input.type = 'password';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
