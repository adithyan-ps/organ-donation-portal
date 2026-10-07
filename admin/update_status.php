<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Master Administrator Action & Status Controller
 * Complete Suite: CRUD, Bulk Actions, Governance, Matching, System Diagnostics & Backups
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/matching_engine.php';

require_admin();
$admin = current_user();
$pdo = get_db_connection();

// Allow CSV export & DB backup via GET if valid token passed, otherwise require POST
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
$redirect = $_POST['redirect_to'] ?? ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/index.php');

// ==========================================
// 1. FILE DOWNLOADS (CSV & SQL BACKUP)
// ==========================================
if ($action === 'export_csv') {
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('error', 'Session token invalid or expired. Please try again.');
        header('Location: ' . $redirect);
        exit;
    }

    $export_type = trim($_GET['type'] ?? $_POST['type'] ?? 'donors');
    $filename = "lifebridge_{$export_type}_" . date('Y-m-d_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    if ($export_type === 'donors') {
        fputcsv($output, ['ID', 'Full Name', 'Age', 'Gender', 'Email', 'Mobile', 'City/State', 'Blood Group', 'Organ Pledged', 'Availability Status', 'Verification Status', 'Medical Notes', 'Admin Notes', 'Pledge Date']);
        $stmt = $pdo->query("SELECT id, full_name, age, gender, email, mobile, address_city, blood_group, organ_donated, availability_status, verification_status, medical_notes, admin_notes, created_at FROM donors ORDER BY id DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        log_admin_action($admin['id'], 'Export Data', "Exported donors dataset to CSV ($filename).");

    } elseif ($export_type === 'recipients') {
        fputcsv($output, ['ID', 'Full Name', 'Age', 'Gender', 'Email', 'Mobile', 'Hospital/City', 'Blood Group', 'Organ Needed', 'Urgency Level', 'Verification Status', 'Medical Notes', 'Admin Notes', 'Registration Date']);
        $stmt = $pdo->query("SELECT id, full_name, age, gender, email, mobile, hospital_city, blood_group, organ_needed, urgency_level, verification_status, medical_notes, admin_notes, created_at FROM recipients ORDER BY id DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        log_admin_action($admin['id'], 'Export Data', "Exported recipients dataset to CSV ($filename).");

    } elseif ($export_type === 'matches') {
        fputcsv($output, ['Match ID', 'Organ', 'Donor Name', 'Donor Blood', 'Donor City', 'Recipient Name', 'Recipient Blood', 'Hospital/City', 'Urgency', 'Score', 'Status', 'Breakdown', 'Notes', 'Date Matched']);
        $stmt = $pdo->query("
            SELECT m.id, m.organ, d.full_name as donor_name, d.blood_group as donor_blood, d.address_city as donor_city,
                   r.full_name as recipient_name, r.blood_group as recipient_blood, r.hospital_city, r.urgency_level,
                   m.compatibility_score, m.status, m.score_breakdown, m.notes, m.matched_at
            FROM matches m
            JOIN donors d ON m.donor_id = d.id
            JOIN recipients r ON m.recipient_id = r.id
            ORDER BY m.compatibility_score DESC, m.id DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        log_admin_action($admin['id'], 'Export Data', "Exported preliminary matches to CSV ($filename).");

    } elseif ($export_type === 'logs') {
        fputcsv($output, ['Log ID', 'Timestamp', 'Admin Name', 'Action', 'Description']);
        $stmt = $pdo->query("
            SELECT l.id, l.created_at, COALESCE(u.name, 'System') as admin_name, l.action, l.description
            FROM admin_logs l
            LEFT JOIN users u ON l.admin_id = u.id
            ORDER BY l.id DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        log_admin_action($admin['id'], 'Export Data', "Exported audit logs to CSV ($filename).");

    } elseif ($export_type === 'users') {
        fputcsv($output, ['User ID', 'Name', 'Email', 'Role', 'Registered Date']);
        $stmt = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY id DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        log_admin_action($admin['id'], 'Export Data', "Exported user accounts to CSV ($filename).");
    }

    fclose($output);
    exit;
}

if ($action === 'download_db_backup') {
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('error', 'Session token invalid or expired. Please try again.');
        header('Location: ' . $redirect);
        exit;
    }

    $filename = "organ_donation_backup_" . date('Y-m-d_His') . ".sql";
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "-- =======================================================\n";
    echo "-- Organ Donation – Recipient Matching Portal\n";
    echo "-- Live Database Backup Snapshot\n";
    echo "-- Date: " . date('Y-m-d H:i:s') . "\n";
    echo "-- =======================================================\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = ['users', 'donors', 'recipients', 'matches', 'admin_logs'];
    foreach ($tables as $tbl) {
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch(PDO::FETCH_NUM);
        echo "DROP TABLE IF EXISTS `{$tbl}`;\n";
        echo $createStmt[1] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            echo "INSERT INTO `{$tbl}` VALUES\n";
            $valRows = [];
            foreach ($rows as $row) {
                $quoted = array_map(function ($val) use ($pdo) {
                    return ($val === null) ? 'NULL' : $pdo->quote($val);
                }, array_values($row));
                $valRows[] = "(" . implode(", ", $quoted) . ")";
            }
            echo implode(",\n", $valRows) . ";\n\n";
        }
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";

    log_admin_action($admin['id'], 'Database Backup', "Admin downloaded SQL database backup snapshot ($filename).");
    exit;
}

// All modifying actions require POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!verify_csrf_token($csrf_token)) {
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Session security token invalid or expired. Please refresh the page and try again.'
        ]);
        exit;
    }
    set_flash_message('error', 'Session token invalid or expired. Please try again.');
    header('Location: ' . $redirect);
    exit;
}

try {
    // ==========================================
    // 2. DONOR ACTIONS
    // ==========================================
    if ($action === 'create_donor') {
        $full_name           = trim($_POST['full_name'] ?? '');
        $age                 = (int)($_POST['age'] ?? 0);
        $gender              = trim($_POST['gender'] ?? 'Male');
        $email               = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $mobile              = trim($_POST['mobile'] ?? '');
        $address_city        = trim($_POST['address_city'] ?? '');
        $blood_group         = trim($_POST['blood_group'] ?? 'O+');
        $organ_donated       = trim($_POST['organ_donated'] ?? 'Kidney');
        $availability_status = trim($_POST['availability_status'] ?? 'Available');
        $verification_status = trim($_POST['verification_status'] ?? 'Verified');
        $medical_notes       = trim($_POST['medical_notes'] ?? '');
        $admin_notes         = trim($_POST['admin_notes'] ?? 'Created directly by Administrator.');
        $password            = trim($_POST['password'] ?? 'donor123');

        if (strlen($full_name) < 2) throw new Exception('Full name must be at least 2 characters.');
        if ($age < 18 || $age > 85) throw new Exception('Donor age must be between 18 and 85 years.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email address provided.');
        if (!in_array($blood_group, $GLOBALS['BLOOD_GROUPS'], true)) throw new Exception('Invalid blood group.');
        if (!in_array($organ_donated, $GLOBALS['SUPPORTED_ORGANS'], true)) throw new Exception('Invalid organ type.');

        $pdo->beginTransaction();

        $userCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $userCheck->execute([$email]);
        $existingUser = $userCheck->fetch();

        if ($existingUser) {
            $user_id = $existingUser['id'];
        } else {
            $pwdHash = password_hash($password, PASSWORD_DEFAULT);
            $uStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'donor', NOW())");
            $uStmt->execute([$full_name, $email, $pwdHash]);
            $user_id = $pdo->lastInsertId();
        }

        $ins = $pdo->prepare("
            INSERT INTO donors 
            (user_id, full_name, age, gender, email, mobile, address_city, blood_group, organ_donated, availability_status, verification_status, medical_notes, admin_notes, consent_given, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $ins->execute([
            $user_id, $full_name, $age, $gender, $email, $mobile, $address_city,
            $blood_group, $organ_donated, $availability_status, $verification_status,
            $medical_notes, $admin_notes
        ]);
        $donor_id = $pdo->lastInsertId();

        log_admin_action($admin['id'], 'Donor Created', "Admin created donor #{$donor_id} ({$full_name}, {$blood_group}, {$organ_donated}) with status {$verification_status}.");
        $pdo->commit();

        if ($verification_status === 'Verified') {
            run_preliminary_matching($pdo);
        }

        set_flash_message('success', "Donor #{$donor_id} ({$full_name}) created successfully!");

    } elseif ($action === 'edit_donor') {
        $donor_id            = (int)($_POST['donor_id'] ?? 0);
        $full_name           = trim($_POST['full_name'] ?? '');
        $age                 = (int)($_POST['age'] ?? 0);
        $gender              = trim($_POST['gender'] ?? 'Male');
        $email               = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $mobile              = trim($_POST['mobile'] ?? '');
        $address_city        = trim($_POST['address_city'] ?? '');
        $blood_group         = trim($_POST['blood_group'] ?? 'O+');
        $organ_donated       = trim($_POST['organ_donated'] ?? 'Kidney');
        $availability_status = trim($_POST['availability_status'] ?? 'Available');
        $verification_status = trim($_POST['verification_status'] ?? 'Verified');
        $medical_notes       = trim($_POST['medical_notes'] ?? '');
        $admin_notes         = trim($_POST['admin_notes'] ?? '');

        if (!$donor_id) throw new Exception('Invalid donor identifier.');
        if (strlen($full_name) < 2) throw new Exception('Full name must be at least 2 characters.');
        if ($age < 18 || $age > 85) throw new Exception('Donor age must be between 18 and 85 years.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email address provided.');

        $pdo->beginTransaction();

        $upd = $pdo->prepare("
            UPDATE donors SET
                full_name = ?, age = ?, gender = ?, email = ?, mobile = ?,
                address_city = ?, blood_group = ?, organ_donated = ?,
                availability_status = ?, verification_status = ?,
                medical_notes = ?, admin_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([
            $full_name, $age, $gender, $email, $mobile,
            $address_city, $blood_group, $organ_donated,
            $availability_status, $verification_status,
            $medical_notes, $admin_notes, $donor_id
        ]);

        try {
            $pdo->prepare("UPDATE users u JOIN donors d ON d.user_id = u.id SET u.name = ?, u.email = ? WHERE d.id = ?")
                ->execute([$full_name, $email, $donor_id]);
        } catch (Exception $ue) {
            error_log("Non-fatal user sync error on edit_donor #{$donor_id}: " . $ue->getMessage());
        }

        log_admin_action($admin['id'], 'Donor Updated', "Admin edited details for donor #{$donor_id} ({$full_name}). Status: {$verification_status}.");
        $pdo->commit();

        run_preliminary_matching($pdo);
        set_flash_message('success', "Donor #{$donor_id} ({$full_name}) profile updated successfully!");

    } elseif ($action === 'delete_donor') {
        $donor_id = (int)($_POST['donor_id'] ?? 0);
        if (!$donor_id) throw new Exception('Invalid donor ID for deletion.');

        $stmt = $pdo->prepare("SELECT full_name, user_id FROM donors WHERE id = ?");
        $stmt->execute([$donor_id]);
        $donor = $stmt->fetch();
        if (!$donor) throw new Exception('Donor record does not exist.');

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM matches WHERE donor_id = ?")->execute([$donor_id]);
        $pdo->prepare("DELETE FROM donors WHERE id = ?")->execute([$donor_id]);
        if ($donor['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'donor'")->execute([$donor['user_id']]);
        }

        log_admin_action($admin['id'], 'Donor Deleted', "Admin permanently deleted donor #{$donor_id} ({$donor['full_name']}) and associated matches.");
        $pdo->commit();

        set_flash_message('success', "Donor #{$donor_id} ({$donor['full_name']}) was permanently deleted.");

    } elseif ($action === 'update_donor_status') {
        $donor_id = (int)($_POST['donor_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        if (!in_array($new_status, ['Pending', 'Verified', 'Rejected'], true)) {
            throw new Exception('Invalid donor verification status.');
        }

        $stmt = $pdo->prepare("SELECT full_name, organ_donated, blood_group FROM donors WHERE id = ?");
        $stmt->execute([$donor_id]);
        $donor = $stmt->fetch();
        if (!$donor) throw new Exception('Donor record not found.');

        $upd = $pdo->prepare("UPDATE donors SET verification_status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$new_status, $admin_notes, $donor_id]);

        log_admin_action($admin['id'], 'Donor Verification Updated', "Donor #{$donor_id} ({$donor['full_name']}) status changed to '{$new_status}'. Notes: {$admin_notes}");
        
        if ($new_status === 'Verified') {
            run_preliminary_matching($pdo);
        }

        set_flash_message('success', "Donor #{$donor_id} ({$donor['full_name']}) status updated to {$new_status}.");

    } elseif ($action === 'bulk_donor_action') {
        $donor_ids = $_POST['donor_ids'] ?? $_POST['selected_ids'] ?? [];
        $sub_action = trim($_POST['sub_action'] ?? $_POST['bulk_action'] ?? '');

        if (empty($donor_ids) || !is_array($donor_ids)) {
            throw new Exception('No donors selected for bulk action.');
        }

        $count = count($donor_ids);
        $inQuery = implode(',', array_fill(0, $count, '?'));

        if ($sub_action === 'verify') {
            $stmt = $pdo->prepare("UPDATE donors SET verification_status = 'Verified', updated_at = NOW() WHERE id IN ($inQuery)");
            $stmt->execute($donor_ids);
            run_preliminary_matching($pdo);
            log_admin_action($admin['id'], 'Bulk Donor Verification', "Admin batch-verified {$count} donor records.");
            set_flash_message('success', "Successfully verified {$count} donors in batch!");

        } elseif ($sub_action === 'reject') {
            $stmt = $pdo->prepare("UPDATE donors SET verification_status = 'Rejected', updated_at = NOW() WHERE id IN ($inQuery)");
            $stmt->execute($donor_ids);
            log_admin_action($admin['id'], 'Bulk Donor Rejection', "Admin batch-rejected {$count} donor records.");
            set_flash_message('success', "Marked {$count} donors as Rejected.");

        } elseif ($sub_action === 'delete') {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM matches WHERE donor_id IN ($inQuery)")->execute($donor_ids);
            $pdo->prepare("DELETE FROM donors WHERE id IN ($inQuery)")->execute($donor_ids);
            log_admin_action($admin['id'], 'Bulk Donor Deletion', "Admin permanently deleted {$count} donor records.");
            $pdo->commit();
            set_flash_message('success', "Successfully deleted {$count} donors.");

        } else {
            throw new Exception('Unrecognized bulk action for donors.');
        }

    // ==========================================
    // 3. RECIPIENT ACTIONS
    // ==========================================
    } elseif ($action === 'create_recipient') {
        $full_name           = trim($_POST['full_name'] ?? '');
        $age                 = (int)($_POST['age'] ?? 0);
        $gender              = trim($_POST['gender'] ?? 'Male');
        $email               = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $mobile              = trim($_POST['mobile'] ?? '');
        $hospital_city       = trim($_POST['hospital_city'] ?? '');
        $blood_group         = trim($_POST['blood_group'] ?? 'O+');
        $organ_needed        = trim($_POST['organ_needed'] ?? 'Kidney');
        $urgency_level       = trim($_POST['urgency_level'] ?? 'High');
        $verification_status = trim($_POST['verification_status'] ?? 'Verified');
        $medical_notes       = trim($_POST['medical_notes'] ?? '');
        $admin_notes         = trim($_POST['admin_notes'] ?? 'Created directly by Administrator.');
        $password            = trim($_POST['password'] ?? 'recipient123');

        if (strlen($full_name) < 2) throw new Exception('Full name must be at least 2 characters.');
        if ($age < 1 || $age > 100) throw new Exception('Invalid age provided.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email address.');
        if (!in_array($blood_group, $GLOBALS['BLOOD_GROUPS'], true)) throw new Exception('Invalid blood group.');
        if (!in_array($organ_needed, $GLOBALS['SUPPORTED_ORGANS'], true)) throw new Exception('Invalid organ type.');
        if (!array_key_exists($urgency_level, $GLOBALS['URGENCY_LEVELS'])) throw new Exception('Invalid urgency level.');

        $pdo->beginTransaction();

        $userCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $userCheck->execute([$email]);
        $existingUser = $userCheck->fetch();

        if ($existingUser) {
            $user_id = $existingUser['id'];
        } else {
            $pwdHash = password_hash($password, PASSWORD_DEFAULT);
            $uStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'recipient', NOW())");
            $uStmt->execute([$full_name, $email, $pwdHash]);
            $user_id = $pdo->lastInsertId();
        }

        $ins = $pdo->prepare("
            INSERT INTO recipients 
            (user_id, full_name, age, gender, email, mobile, hospital_city, blood_group, organ_needed, urgency_level, verification_status, medical_notes, admin_notes, consent_given, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $ins->execute([
            $user_id, $full_name, $age, $gender, $email, $mobile, $hospital_city,
            $blood_group, $organ_needed, $urgency_level, $verification_status,
            $medical_notes, $admin_notes
        ]);
        $recip_id = $pdo->lastInsertId();

        log_admin_action($admin['id'], 'Recipient Created', "Admin created recipient #{$recip_id} ({$full_name}, Need: {$organ_needed}, Urgency: {$urgency_level}) with status {$verification_status}.");
        $pdo->commit();

        if ($verification_status === 'Verified') {
            run_preliminary_matching($pdo);
        }

        set_flash_message('success', "Recipient #{$recip_id} ({$full_name}) registered successfully!");

    } elseif ($action === 'edit_recipient') {
        $recipient_id        = (int)($_POST['recipient_id'] ?? 0);
        $full_name           = trim($_POST['full_name'] ?? '');
        $age                 = (int)($_POST['age'] ?? 0);
        $gender              = trim($_POST['gender'] ?? 'Male');
        $email               = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $mobile              = trim($_POST['mobile'] ?? '');
        $hospital_city       = trim($_POST['hospital_city'] ?? '');
        $blood_group         = trim($_POST['blood_group'] ?? 'O+');
        $organ_needed        = trim($_POST['organ_needed'] ?? 'Kidney');
        $urgency_level       = trim($_POST['urgency_level'] ?? 'High');
        $verification_status = trim($_POST['verification_status'] ?? 'Verified');
        $medical_notes       = trim($_POST['medical_notes'] ?? '');
        $admin_notes         = trim($_POST['admin_notes'] ?? '');

        if (!$recipient_id) throw new Exception('Invalid recipient ID.');
        if (strlen($full_name) < 2) throw new Exception('Full name must be at least 2 characters.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email address.');

        $pdo->beginTransaction();

        $upd = $pdo->prepare("
            UPDATE recipients SET
                full_name = ?, age = ?, gender = ?, email = ?, mobile = ?,
                hospital_city = ?, blood_group = ?, organ_needed = ?,
                urgency_level = ?, verification_status = ?,
                medical_notes = ?, admin_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([
            $full_name, $age, $gender, $email, $mobile,
            $hospital_city, $blood_group, $organ_needed,
            $urgency_level, $verification_status,
            $medical_notes, $admin_notes, $recipient_id
        ]);

        try {
            $pdo->prepare("UPDATE users u JOIN recipients r ON r.user_id = u.id SET u.name = ?, u.email = ? WHERE r.id = ?")
                ->execute([$full_name, $email, $recipient_id]);
        } catch (Exception $ue) {
            error_log("Non-fatal user sync error on edit_recipient #{$recipient_id}: " . $ue->getMessage());
        }

        log_admin_action($admin['id'], 'Recipient Updated', "Admin edited recipient #{$recipient_id} ({$full_name}). Urgency: {$urgency_level}, Status: {$verification_status}.");
        $pdo->commit();

        run_preliminary_matching($pdo);
        set_flash_message('success', "Recipient #{$recipient_id} ({$full_name}) profile updated successfully!");

    } elseif ($action === 'delete_recipient') {
        $recipient_id = (int)($_POST['recipient_id'] ?? 0);
        if (!$recipient_id) throw new Exception('Invalid recipient ID for deletion.');

        $stmt = $pdo->prepare("SELECT full_name, user_id FROM recipients WHERE id = ?");
        $stmt->execute([$recipient_id]);
        $recip = $stmt->fetch();
        if (!$recip) throw new Exception('Recipient record does not exist.');

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM matches WHERE recipient_id = ?")->execute([$recipient_id]);
        $pdo->prepare("DELETE FROM recipients WHERE id = ?")->execute([$recipient_id]);
        if ($recip['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'recipient'")->execute([$recip['user_id']]);
        }

        log_admin_action($admin['id'], 'Recipient Deleted', "Admin permanently deleted recipient #{$recipient_id} ({$recip['full_name']}) and associated matches.");
        $pdo->commit();

        set_flash_message('success', "Recipient #{$recipient_id} ({$recip['full_name']}) was permanently deleted.");

    } elseif ($action === 'update_recipient_status') {
        $recipient_id = (int)($_POST['recipient_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        if (!in_array($new_status, ['Pending', 'Verified', 'Rejected'], true)) {
            throw new Exception('Invalid recipient verification status.');
        }

        $stmt = $pdo->prepare("SELECT full_name, organ_needed, urgency_level FROM recipients WHERE id = ?");
        $stmt->execute([$recipient_id]);
        $recipient = $stmt->fetch();
        if (!$recipient) throw new Exception('Recipient record not found.');

        $upd = $pdo->prepare("UPDATE recipients SET verification_status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$new_status, $admin_notes, $recipient_id]);

        log_admin_action($admin['id'], 'Recipient Verification Updated', "Recipient #{$recipient_id} ({$recipient['full_name']}) status changed to '{$new_status}'. Notes: {$admin_notes}");
        
        if ($new_status === 'Verified') {
            run_preliminary_matching($pdo);
        }

        set_flash_message('success', "Recipient #{$recipient_id} ({$recipient['full_name']}) status updated to {$new_status}.");

    } elseif ($action === 'bulk_recipient_action') {
        $recip_ids = $_POST['recipient_ids'] ?? $_POST['selected_ids'] ?? [];
        $sub_action = trim($_POST['sub_action'] ?? $_POST['bulk_action'] ?? '');

        if (empty($recip_ids) || !is_array($recip_ids)) {
            throw new Exception('No recipients selected for bulk action.');
        }

        $count = count($recip_ids);
        $inQuery = implode(',', array_fill(0, $count, '?'));

        if ($sub_action === 'verify') {
            $stmt = $pdo->prepare("UPDATE recipients SET verification_status = 'Verified', updated_at = NOW() WHERE id IN ($inQuery)");
            $stmt->execute($recip_ids);
            run_preliminary_matching($pdo);
            log_admin_action($admin['id'], 'Bulk Recipient Verification', "Admin batch-verified {$count} recipient waitlist cases.");
            set_flash_message('success', "Successfully verified {$count} recipients in batch!");

        } elseif ($sub_action === 'reject') {
            $stmt = $pdo->prepare("UPDATE recipients SET verification_status = 'Rejected', updated_at = NOW() WHERE id IN ($inQuery)");
            $stmt->execute($recip_ids);
            log_admin_action($admin['id'], 'Bulk Recipient Rejection', "Admin batch-rejected {$count} recipient cases.");
            set_flash_message('success', "Marked {$count} recipients as Rejected.");

        } elseif ($sub_action === 'delete') {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM matches WHERE recipient_id IN ($inQuery)")->execute($recip_ids);
            $pdo->prepare("DELETE FROM recipients WHERE id IN ($inQuery)")->execute($recip_ids);
            log_admin_action($admin['id'], 'Bulk Recipient Deletion', "Admin permanently deleted {$count} recipient cases.");
            $pdo->commit();
            set_flash_message('success', "Successfully deleted {$count} recipients.");

        } else {
            throw new Exception('Unrecognized bulk action for recipients.');
        }

    // ==========================================
    // 4. FAST-TRACK 1-CLICK VERIFICATION (OVERVIEW)
    // ==========================================
    } elseif ($action === 'quick_verify') {
        $type = trim($_POST['type'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($type === 'donor') {
            $pdo->prepare("UPDATE donors SET verification_status = 'Verified', updated_at = NOW() WHERE id = ?")->execute([$id]);
            log_admin_action($admin['id'], 'Quick Verification', "Donor #{$id} 1-click verified from overview queue.");
            run_preliminary_matching($pdo);
            set_flash_message('success', "Donor #{$id} successfully verified & entered into matching engine!");

        } elseif ($type === 'recipient') {
            $pdo->prepare("UPDATE recipients SET verification_status = 'Verified', updated_at = NOW() WHERE id = ?")->execute([$id]);
            log_admin_action($admin['id'], 'Quick Verification', "Recipient #{$id} 1-click verified from overview queue.");
            run_preliminary_matching($pdo);
            set_flash_message('success', "Recipient #{$id} successfully verified & entered into matching engine!");
        } else {
            throw new Exception('Invalid entity type for quick verify.');
        }

    } elseif ($action === 'quick_reject') {
        $type = trim($_POST['type'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($type === 'donor') {
            $pdo->prepare("UPDATE donors SET verification_status = 'Rejected', updated_at = NOW() WHERE id = ?")->execute([$id]);
            log_admin_action($admin['id'], 'Quick Rejection', "Donor #{$id} rejected from overview queue.");
            set_flash_message('success', "Donor #{$id} marked as Rejected.");

        } elseif ($type === 'recipient') {
            $pdo->prepare("UPDATE recipients SET verification_status = 'Rejected', updated_at = NOW() WHERE id = ?")->execute([$id]);
            log_admin_action($admin['id'], 'Quick Rejection', "Recipient #{$id} rejected from overview queue.");
            set_flash_message('success', "Recipient #{$id} marked as Rejected.");
        } else {
            throw new Exception('Invalid entity type for quick reject.');
        }

    // ==========================================
    // 5. USER & CREDENTIAL GOVERNANCE
    // ==========================================
    } elseif ($action === 'create_admin_user') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = trim($_POST['password'] ?? '');

        if (strlen($name) < 2) throw new Exception('Name must be at least 2 characters.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email address.');
        if (strlen($password) < 6) throw new Exception('Password must contain at least 6 characters.');

        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) throw new Exception('An account with this email address already exists.');

        $pwdHash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
        $ins->execute([$name, $email, $pwdHash]);
        $newUserId = $pdo->lastInsertId();

        log_admin_action($admin['id'], 'Administrator Created', "Admin created new coordinator/administrator account #{$newUserId} ({$email}).");
        set_flash_message('success', "New Administrator account ({$email}) created successfully!");

    } elseif ($action === 'reset_user_password') {
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $new_password   = trim($_POST['new_password'] ?? '');

        if (!$target_user_id) throw new Exception('Invalid user identifier.');
        if (strlen($new_password) < 6) throw new Exception('New password must be at least 6 characters long.');

        $stmt = $pdo->prepare("SELECT email, role FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) throw new Exception('User account not found.');

        $pwdHash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $upd->execute([$pwdHash, $target_user_id]);

        log_admin_action($admin['id'], 'Password Reset', "Admin reset password for user #{$target_user_id} ({$targetUser['email']}).");
        set_flash_message('success', "Password for user {$targetUser['email']} successfully updated to '{$new_password}'.");

    } elseif ($action === 'delete_user') {
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        if (!$target_user_id) throw new Exception('Invalid user ID.');

        // Prevent self-deletion
        if ($target_user_id === (int)$admin['id']) {
            throw new Exception('You cannot delete your own active administrator account.');
        }

        $stmt = $pdo->prepare("SELECT name, email, role FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) throw new Exception('User not found.');

        $pdo->beginTransaction();
        // Nullify foreign keys in donors and recipients
        $pdo->prepare("UPDATE donors SET user_id = NULL WHERE user_id = ?")->execute([$target_user_id]);
        $pdo->prepare("UPDATE recipients SET user_id = NULL WHERE user_id = ?")->execute([$target_user_id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_user_id]);

        log_admin_action($admin['id'], 'User Deleted', "Admin removed user #{$target_user_id} ({$targetUser['email']}).");
        $pdo->commit();

        set_flash_message('success', "User account {$targetUser['email']} was removed.");

    // ==========================================
    // 6. MATCHING ENGINE & CLINICAL LIFECYCLE
    // ==========================================
    } elseif ($action === 'create_manual_match') {
        $donor_id = (int)($_POST['donor_id'] ?? 0);
        $recipient_id = (int)($_POST['recipient_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Potential');
        $notes = trim($_POST['notes'] ?? 'Manual clinical pairing by Administrator.');

        if (!$donor_id || !$recipient_id) throw new Exception('Both donor and recipient must be selected.');

        $dStmt = $pdo->prepare("SELECT * FROM donors WHERE id = ?");
        $dStmt->execute([$donor_id]);
        $donor = $dStmt->fetch();

        $rStmt = $pdo->prepare("SELECT * FROM recipients WHERE id = ?");
        $rStmt->execute([$recipient_id]);
        $recipient = $rStmt->fetch();

        if (!$donor || !$recipient) throw new Exception('Selected donor or recipient not found.');

        $analysis = evaluate_compatibility($donor, $recipient);
        $organ = $donor['organ_donated'];
        $score = $analysis['score'];
        $breakdown = $analysis['breakdown_summary'] . " [Manual pairing initiated by Admin]";

        $ins = $pdo->prepare("
            INSERT INTO matches (donor_id, recipient_id, organ, compatibility_score, score_breakdown, status, notes, matched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status), 
                notes = VALUES(notes), 
                compatibility_score = VALUES(compatibility_score), 
                score_breakdown = VALUES(score_breakdown),
                updated_at = NOW()
        ");
        $ins->execute([$donor_id, $recipient_id, $organ, $score, $breakdown, $status, $notes]);

        if ($status === 'Approved') {
            $pdo->prepare("UPDATE donors SET availability_status = 'Donated', admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Manual Match Approved] Organ allocated to Recipient #$recipient_id ({$recipient['full_name']})'), updated_at = NOW() WHERE id = ?")->execute([$donor_id]);
            $pdo->prepare("UPDATE recipients SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Manual Match Approved] Organ allocated from Donor #$donor_id ({$donor['full_name']})'), updated_at = NOW() WHERE id = ?")->execute([$recipient_id]);
            $pdo->prepare("UPDATE matches SET status = 'Closed', notes = CONCAT(COALESCE(notes, ''), ' [Closed: Organ allocated in manual match]') WHERE donor_id = ? AND recipient_id != ? AND status != 'Approved'")->execute([$donor_id, $recipient_id]);
            $pdo->prepare("UPDATE matches SET status = 'Closed', notes = CONCAT(COALESCE(notes, ''), ' [Closed: Recipient allocated organ in manual match]') WHERE recipient_id = ? AND donor_id != ? AND status != 'Approved'")->execute([$recipient_id, $donor_id]);
        }

        log_admin_action($admin['id'], 'Manual Match Created', "Admin manually paired Donor #{$donor_id} ({$donor['full_name']}) with Recipient #{$recipient_id} ({$recipient['full_name']}). Score: {$score}%.");
        set_flash_message('success', "Manual match between {$donor['full_name']} and {$recipient['full_name']} recorded successfully ({$score}% score)!");

    } elseif ($action === 'delete_match') {
        $match_id = (int)($_POST['match_id'] ?? 0);
        if (!$match_id) throw new Exception('Invalid match record ID.');

        $pdo->prepare("DELETE FROM matches WHERE id = ?")->execute([$match_id]);
        log_admin_action($admin['id'], 'Match Deleted', "Admin removed match record #{$match_id}.");

        set_flash_message('success', "Match #{$match_id} successfully removed from the matching list.");

    } elseif ($action === 'update_match_status') {
        $match_id = (int)($_POST['match_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $auto_allocate = isset($_POST['auto_allocate']) ? 1 : 0;

        if (!array_key_exists($new_status, $GLOBALS['MATCH_STATUSES'])) {
            throw new Exception('Invalid match status.');
        }

        $pdo->beginTransaction();

        $upd = $pdo->prepare("UPDATE matches SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$new_status, $notes, $match_id]);

        $mStmt = $pdo->prepare("
            SELECT m.*, 
                   d.full_name as donor_name, d.availability_status as donor_avail,
                   r.full_name as recip_name
            FROM matches m
            JOIN donors d ON m.donor_id = d.id
            JOIN recipients r ON m.recipient_id = r.id
            WHERE m.id = ?
        ");
        $mStmt->execute([$match_id]);
        $mRow = $mStmt->fetch();

        if ($mRow) {
            $donor_id = (int)$mRow['donor_id'];
            $recip_id = (int)$mRow['recipient_id'];
            $donor_name = $mRow['donor_name'];
            $recip_name = $mRow['recip_name'];
            $organ = $mRow['organ'];

            if ($new_status === 'Approved') {
                // 1. Update Donor: availability to Donated and set admin note
                $donorNote = "[Match #{$match_id} APPROVED] Organ ({$organ}) allocated to Recipient #{$recip_id} ({$recip_name}). Transplant authorization active.";
                $pdo->prepare("UPDATE donors SET availability_status = 'Donated', admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', ?), updated_at = NOW() WHERE id = ?")
                    ->execute([$donorNote, $donor_id]);

                // 2. Update Recipient: set admin note
                $recipNote = "[Match #{$match_id} APPROVED] Compatible organ ({$organ}) allocated from Donor #{$donor_id} ({$donor_name}). Transplant protocol confirmed.";
                $pdo->prepare("UPDATE recipients SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', ?), updated_at = NOW() WHERE id = ?")
                    ->execute([$recipNote, $recip_id]);

                // 3. Close other competing matches for this donor
                $pdo->prepare("UPDATE matches SET status = 'Closed', notes = CONCAT(COALESCE(notes, ''), ' [Closed: Organ allocated to Recipient #$recip_id in match #$match_id]') WHERE donor_id = ? AND id != ? AND status != 'Approved'")
                    ->execute([$donor_id, $match_id]);

                // 4. Close other competing matches for this recipient
                $pdo->prepare("UPDATE matches SET status = 'Closed', notes = CONCAT(COALESCE(notes, ''), ' [Closed: Recipient allocated organ from Donor #$donor_id in match #$match_id]') WHERE recipient_id = ? AND id != ? AND status != 'Approved'")
                    ->execute([$recip_id, $match_id]);

                log_admin_action($admin['id'], 'Match Approved & Allocated', "Match #{$match_id} Approved. Donor #{$donor_id} ({$donor_name}) allocated to Recipient #{$recip_id} ({$recip_name}). Both records synchronized.");

            } elseif ($new_status === 'Contacted') {
                $donorNote = "[Match #{$match_id} Contacted] Coordinator outreach regarding preliminary match with Recipient #{$recip_id} ({$recip_name}). Notes: {$notes}";
                $recipNote = "[Match #{$match_id} Contacted] Hospital/patient outreach regarding preliminary match with Donor #{$donor_id} ({$donor_name}). Notes: {$notes}";

                $pdo->prepare("UPDATE donors SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', ?), updated_at = NOW() WHERE id = ?")
                    ->execute([$donorNote, $donor_id]);
                $pdo->prepare("UPDATE recipients SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', ?), updated_at = NOW() WHERE id = ?")
                    ->execute([$recipNote, $recip_id]);

                log_admin_action($admin['id'], 'Match Contacted', "Match #{$match_id} set to Contacted. Donor and recipient records updated.");

            } elseif ($new_status === 'Under Review') {
                $donorNote = "[Match #{$match_id} Under Review] Compatibility metrics under clinical evaluation with Recipient #{$recip_id} ({$recip_name}).";
                $recipNote = "[Match #{$match_id} Under Review] Preliminary match under clinical review with Donor #{$donor_id} ({$donor_name}).";

                $pdo->prepare("UPDATE donors SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', ?), updated_at = NOW() WHERE id = ?")
                    ->execute([$donorNote, $donor_id]);
                $pdo->prepare("UPDATE recipients SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', ?), updated_at = NOW() WHERE id = ?")
                    ->execute([$recipNote, $recip_id]);

                log_admin_action($admin['id'], 'Match Under Review', "Match #{$match_id} set to Under Review. Donor and recipient updated.");

            } elseif ($new_status === 'Closed') {
                // If closing, check if donor has any other approved match
                $chk = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE donor_id = ? AND status = 'Approved' AND id != ?");
                $chk->execute([$donor_id, $match_id]);
                $hasOther = ((int)$chk->fetchColumn()) > 0;

                if (!$hasOther && $mRow['donor_avail'] === 'Donated') {
                    $pdo->prepare("UPDATE donors SET availability_status = 'Available', admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', '[Match #$match_id Closed: Availability restored to Available]'), updated_at = NOW() WHERE id = ?")
                        ->execute([$donor_id]);
                }

                $recipNote = "[Match #$match_id Closed] Allocation deferred / closed. Patient remains active on priority waitlist.";
                $pdo->prepare("UPDATE recipients SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', ?), updated_at = NOW() WHERE id = ?")
                    ->execute([$recipNote, $recip_id]);

                log_admin_action($admin['id'], 'Match Closed', "Match #{$match_id} Closed. Restored availability if applicable.");
            }
        }

        log_admin_action($admin['id'], 'Match Status Updated', "Match record #{$match_id} status changed to '{$new_status}'. Notes: {$notes}");
        $pdo->commit();

        set_flash_message('success', "Match #{$match_id} marked as '{$new_status}'. Donor and recipient profiles synchronized.");

    } elseif ($action === 'recalculate_matches') {
        $matches = run_preliminary_matching($pdo);
        $count = count($matches);
        log_admin_action($admin['id'], 'Matching Engine Re-run', "Admin triggered full matching engine recalculation. {$count} preliminary matches computed.");
        set_flash_message('success', "Matching engine re-evaluated! Found {$count} valid preliminary donor-recipient matches.");

    // ==========================================
    // 7. SYSTEM MAINTENANCE & RE-SEEDING
    // ==========================================
    } elseif ($action === 'reset_demo_data') {
        $sqlPath = __DIR__ . '/../database.sql';
        if (!file_exists($sqlPath)) throw new Exception('database.sql baseline file missing.');

        $sqlContent = file_get_contents($sqlPath);
        // Remove CREATE DATABASE and USE statements to avoid permission errors
        $sqlClean = preg_replace('/CREATE DATABASE.*?;/i', '', $sqlContent);
        $sqlClean = preg_replace('/USE `.*?`;/i', '', $sqlClean);

        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec($sqlClean);
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        log_admin_action($admin['id'], 'System Reset', "Admin reset database to baseline demo records.");
        set_flash_message('success', 'Database cleanly re-seeded to original demo dataset!');

    } elseif ($action === 'clear_logs') {
        $pdo->exec("DELETE FROM admin_logs");
        log_admin_action($admin['id'], 'Audit Logs Cleared', "Admin purged historical audit log entries.");
        set_flash_message('success', 'Audit trail logs cleared successfully.');

    } else {
        throw new Exception('Unrecognized administrative action.');
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Action failed: ' . $e->getMessage()
        ]);
        exit;
    }
    set_flash_message('error', 'Action failed: ' . $e->getMessage());
}

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    $flash = get_flash_message();
    echo json_encode([
        'success' => true,
        'message' => $flash['message'] ?? 'Action completed successfully.',
        'action' => $action,
        'data' => $_POST
    ]);
    exit;
}

header('Location: ' . $redirect);
exit;
