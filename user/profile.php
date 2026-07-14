<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mongo_db.php';
requireLogin();

$pageTitle = 'My Profile';
$conn = getDBConnection();

$success = '';
$error   = '';

$stmt = $conn->prepare("
    SELECT u.*, r.name as role_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$profileUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            $error = 'Name and email cannot be empty.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->bind_param("si", $email, $_SESSION['user_id']);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = 'That email is already used by another account.';
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $email, $_SESSION['user_id']);
                $stmt->execute();
                $stmt->close();

                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;

                $success = 'Profile updated successfully.';

                $stmt = $conn->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $profileUser = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
            $check->close();
        }
    }

    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            $error = 'Please fill in all password fields.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (!password_verify($current, $profileUser['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt   = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            $success = 'Password changed successfully.';
        }
    }
}

$reports = $conn->query("
    SELECT * FROM reports
    WHERE user_id = {$_SESSION['user_id']}
    ORDER BY generated_at DESC
    LIMIT 10
");

try {
    $readingCount = getMongoCollection()->countDocuments([]);
} catch (Exception $e) {
    $readingCount = 0;
}

$conn->close();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">

    <div class="page-header">
        <h2><i class="bi bi-person-circle text-success" style="margin-right:8px;"></i>My Profile</h2>
        <p class="text-muted mb-0">Manage your account details and password</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle" style="margin-right:8px;"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle" style="margin-right:8px;"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>

    <div class="row">

        <div class="col-4">

            <div class="card mb-4">
                <div class="card-body text-center" style="padding:40px 20px;">
                    <div class="mb-3 text-white"
                         style="width:80px;height:80px;font-size:32px;font-weight:700;border-radius:50%;background-color:var(--color-primary);display:flex;align-items:center;justify-content:center;margin:0 auto;">
                        <?php echo strtoupper(substr($profileUser['name'], 0, 1)); ?>
                    </div>
                    <h5 class="mb-1" style="font-weight:700;"><?php echo htmlspecialchars($profileUser['name']); ?></h5>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($profileUser['email']); ?></p>
                    <span class="badge <?php echo $profileUser['role_name'] === 'admin' ? 'badge-danger' : 'badge-info'; ?>" style="padding:6.4px 14.4px;">
                        <i class="bi bi-<?php echo $profileUser['role_name'] === 'admin' ? 'shield-fill' : 'person-fill'; ?>" style="margin-right:4px;"></i>
                        <?php echo ucfirst($profileUser['role_name']); ?>
                    </span>
                    <hr style="margin:24px 0;border-top:1px solid var(--color-border);border-bottom:none;">
                    <div class="row text-center">
                        <div class="col-6" style="border-right:1px solid var(--color-border);">
                            <div class="text-success" style="font-weight:700;font-size:20px;"><?php echo number_format($readingCount); ?></div>
                            <div class="text-muted" style="font-size:13.6px;">Total Readings</div>
                        </div>
                        <div class="col-6">
                            <div style="font-weight:700;font-size:20px;color:var(--color-info);"><?php echo $reports->num_rows; ?></div>
                            <div class="text-muted" style="font-size:13.6px;">Reports Run</div>
                        </div>
                    </div>
                </div>
                <div class="text-center" style="background-color:var(--color-light-bg);padding:9.6px;border-top:1px solid var(--color-border);">
                    <small class="text-muted">
                        <i class="bi bi-calendar3" style="margin-right:4px;"></i>
                        Member since <?php echo date('F Y', strtotime($profileUser['created_at'])); ?>
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle" style="color:var(--color-info);margin-right:8px;"></i>Account Info
                    </h6>
                </div>
                <div class="card-body" style="padding:0;">
                    <div class="flex justify-between" style="padding:12px 20px;border-bottom:1px solid var(--color-border);">
                        <span class="text-muted" style="font-size:13.6px;">Account ID</span>
                        <span style="font-weight:600;font-size:13.6px;">#<?php echo $profileUser['id']; ?></span>
                    </div>
                    <div class="flex justify-between" style="padding:12px 20px;border-bottom:1px solid var(--color-border);">
                        <span class="text-muted" style="font-size:13.6px;">Status</span>
                        <span class="badge <?php echo $profileUser['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                            <?php echo ucfirst($profileUser['status']); ?>
                        </span>
                    </div>
                    <div class="flex justify-between" style="padding:12px 20px;border-bottom:1px solid var(--color-border);">
                        <span class="text-muted" style="font-size:13.6px;">Role</span>
                        <span style="font-weight:600;font-size:13.6px;"><?php echo ucfirst($profileUser['role_name']); ?></span>
                    </div>
                    <div class="flex justify-between" style="padding:12px 20px;border-bottom:1px solid var(--color-border);">
                        <span class="text-muted" style="font-size:13.6px;">Joined</span>
                        <span style="font-weight:600;font-size:13.6px;"><?php echo date('M j, Y', strtotime($profileUser['created_at'])); ?></span>
                    </div>
                    <div class="flex justify-between" style="padding:12px 20px;">
                        <span class="text-muted" style="font-size:13.6px;">Last Updated</span>
                        <span style="font-weight:600;font-size:13.6px;"><?php echo date('M j, Y', strtotime($profileUser['updated_at'])); ?></span>
                    </div>
                </div>
            </div>

        </div>

        <div class="col-8">

            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-pencil text-success" style="margin-right:8px;"></i>Edit Profile
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" name="name" class="form-control"
                                           value="<?php echo htmlspecialchars($profileUser['name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control"
                                           value="<?php echo htmlspecialchars($profileUser['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save" style="margin-right:4px;"></i>Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-lock text-warning" style="margin-right:8px;"></i>Change Password
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="row">
                            <div class="col-4">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password"
                                       class="form-control" placeholder="Current password" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password"
                                       class="form-control" placeholder="Min 6 characters" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password"
                                       class="form-control" placeholder="Repeat new password" required>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="bi bi-key" style="margin-right:4px;"></i>Change Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card table-card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-clock-history text-muted" style="margin-right:8px;"></i>
                        My Report History
                    </h6>
                </div>
                <div class="card-body" style="padding:0;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Report Title</th>
                                    <th>Type</th>
                                    <th>Generated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $reports->data_seek(0);
                                if ($reports->num_rows > 0):
                                    while ($row = $reports->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['report_type'] === 'environmental' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo ucfirst($row['report_type']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo date('M j, Y H:i', strtotime($row['generated_at'])); ?>
                                    </td>
                                </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted" style="padding:32px 0;">
                                        <i class="bi bi-file-earmark" style="font-size:22.4px;display:block;margin-bottom:8px;"></i>
                                        No reports generated yet.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>