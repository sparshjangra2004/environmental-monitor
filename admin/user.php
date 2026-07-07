<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pageTitle = 'User Management';
$conn = getDBConnection();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($action === 'toggle_status' && $user_id) {
        if ($user_id === intval($_SESSION['user_id'])) {
            $error = 'You cannot change your own status.';
        } else {
            $current = $conn->prepare("SELECT status FROM users WHERE id = ?");
            $current->bind_param("i", $user_id);
            $current->execute();
            $currentStatus = $current->get_result()->fetch_assoc()['status'];
            $current->close();

            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $user_id);
            $stmt->execute();
            $stmt->close();
            $success = "User status updated to $newStatus.";
        }
    }

    if ($action === 'change_role' && $user_id) {
        if ($user_id === intval($_SESSION['user_id'])) {
            $error = 'You cannot change your own role.';
        } else {
            $new_role = intval($_POST['role_id'] ?? 2);
            $stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_role, $user_id);
            $stmt->execute();
            $stmt->close();
            $success = 'User role updated.';
        }
    }

    if ($action === 'delete' && $user_id) {
        if ($user_id === intval($_SESSION['user_id'])) {
            $error = 'You cannot delete your own account.';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role_id != 1");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            $success = 'User deleted.';
        }
    }

    if ($action === 'add_user') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role_id  = intval($_POST['role_id'] ?? 2);

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = 'Email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt   = $conn->prepare("INSERT INTO users (name, email, password, role_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $name, $email, $hashed, $role_id);
                $stmt->execute();
                $stmt->close();
                $success = 'User created successfully.';
            }
            $check->close();
        }
    }
}

$search = trim($_GET['search'] ?? '');
if ($search) {
    $like = "%$search%";
    $stmt = $conn->prepare("
        SELECT u.*, r.name as role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.name LIKE ? OR u.email LIKE ?
        ORDER BY u.created_at DESC
    ");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $users = $stmt->get_result();
    $stmt->close();
} else {
    $users = $conn->query("
        SELECT u.*, r.name as role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        ORDER BY u.created_at DESC
    ");
}

$totalUsers    = $conn->query("SELECT COUNT(*) as c FROM users WHERE role_id = 2")->fetch_assoc()['c'];
$activeUsers   = $conn->query("SELECT COUNT(*) as c FROM users WHERE status = 'active'")->fetch_assoc()['c'];
$inactiveUsers = $conn->query("SELECT COUNT(*) as c FROM users WHERE status = 'inactive'")->fetch_assoc()['c'];

$conn->close();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">

    <div class="page-header flex justify-between items-center">
        <div>
            <h2><i class="bi bi-people text-success"></i> User Management</h2>
            <p class="text-muted mb-0">Manage registered users and their roles</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addUserModal')">
            <i class="bi bi-person-plus"></i> Add User
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-4">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number text-success"><?php echo $totalUsers; ?></div>
                        <div class="stat-label">Regular Users</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number text-success"><?php echo $activeUsers; ?></div>
                        <div class="stat-label">Active Accounts</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number text-warning"><?php echo $inactiveUsers; ?></div>
                        <div class="stat-label">Inactive Accounts</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-person-dash-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header flex justify-between items-center">
            <h6 class="mb-0">
                <i class="bi bi-table text-success"></i> All Users
            </h6>
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" class="form-control"
                       placeholder="Search name or email..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i>
                </button>
                <?php if ($search): ?>
                    <a href="/environmental_monitor/admin/users.php" class="btn btn-outline btn-sm">
                        <i class="bi bi-x"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($row = $users->fetch_assoc()): ?>
                            <tr>
                                <td class="text-muted"><?php echo $row['id']; ?></td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="text-success"
                                             style="width:32px;height:32px;font-size:13.6px;font-weight:600;border-radius:50%;background-color:rgba(25,135,84,0.12);display:flex;align-items:center;justify-content:center;">
                                            <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                        </div>
                                        <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $row['role_name'] === 'admin' ? 'badge-danger' : 'badge-info'; ?>">
                                        <?php echo ucfirst($row['role_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $row['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action"  value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline"
                                                    title="<?php echo $row['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="bi bi-<?php echo $row['status'] === 'active' ? 'person-dash' : 'person-check'; ?>"></i>
                                            </button>
                                        </form>

                                        <?php if ($row['id'] !== intval($_SESSION['user_id'])): ?>
                                        <form method="POST" class="flex gap-1" style="display:inline-flex;">
                                            <input type="hidden" name="action"  value="change_role">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <select name="role_id" class="form-select" style="width:auto;font-size:12px;padding:4.8px 8px;">
                                                <option value="1" <?php echo $row['role_name'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <option value="2" <?php echo $row['role_name'] === 'user'  ? 'selected' : ''; ?>>User</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline" title="Change Role">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <?php if ($row['role_name'] !== 'admin'): ?>
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete this user?')">
                                            <input type="hidden" name="action"  value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding:48px 0;">
                                    <i class="bi bi-people" style="font-size:20.8px;display:block;margin-bottom:8px;"></i>
                                    No users found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal-backdrop" id="addUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <h5>
                <i class="bi bi-person-plus text-success"></i> Add New User
            </h5>
            <button type="button" class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Full name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role_id" class="form-select">
                        <option value="2">User</option>
                        <option value="1">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i> Create User
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>