<?php
session_start();
require_once 'db.php';

// Debug session state
error_log('=== manage_users.php Session Debug ===');
error_log('Session username: ' . (isset($_SESSION['username']) ? $_SESSION['username'] : 'not set'));
error_log('Session role: ' . (isset($_SESSION['role']) ? $_SESSION['role'] : 'not set'));
error_log('Current script: ' . basename($_SERVER['PHP_SELF']));

// Debug session before any checks
error_log('=== manage_users.php Access Check ===');
error_log('Session status: ' . session_status());
error_log('Session ID: ' . session_id());
error_log('Username set: ' . (isset($_SESSION['username']) ? 'yes' : 'no'));
error_log('Role set: ' . (isset($_SESSION['role']) ? $_SESSION['role'] : 'no'));
error_log('Current page: ' . basename($_SERVER['PHP_SELF']));

// Clear any existing output
@ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    error_log('No session - redirecting to login');
    header("Location: login.php");
    exit();
}

// Restrict access based on role
if ($_SESSION['role'] === 'superadmin') {
    // Superadmins have full access
    error_log('Superadmin access granted');
} else if ($_SESSION['role'] === 'admin') {
    // Admins can only view the page
    error_log('Admin view-only access granted');
} else {
    // All other roles redirect to view_inventory
    error_log('Access denied for role: ' . $_SESSION['role']);
    header("Location: view_inventory.php");
    exit();
}

// We've passed all checks, log success
error_log('Access granted to ' . $_SESSION['username'] . ' with role ' . $_SESSION['role']);
error_log('=== End Access Check ===');

$success_message = '';
$error_message = '';

// Handle password change
if (isset($_POST['change_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        $sql = "UPDATE users SET password = '" . $conn->real_escape_string($new_password) . "' WHERE id = $user_id";
        if ($conn->query($sql)) {
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Error changing password: " . $conn->error;
        }
    }
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    $can_delete = false;
    
    // Only superadmin can delete accounts
    if ($_SESSION['role'] === 'superadmin') {
        // Prevent deleting self
        if ($user_id !== $_SESSION['user_id']) {
            // Check if target is not another superadmin
            $target = $conn->query("SELECT role FROM users WHERE id = $user_id")->fetch_assoc();
            if ($target && $target['role'] !== 'superadmin') {
                $can_delete = true;
            }
        }
    }    if ($can_delete) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update all sales records to set user_id to NULL
            $sql_update_sales = "UPDATE sales SET user_id = NULL WHERE user_id = $user_id";
            $conn->query($sql_update_sales);

            // Delete the user
            $sql_delete_user = "DELETE FROM users WHERE id = $user_id";
            $conn->query($sql_delete_user);

            // If we got here, commit the transaction
            $conn->commit();
            $success_message = "User deleted successfully!";
        } catch (Exception $e) {
            // Something went wrong, rollback the transaction
            $conn->rollback();
            $error_message = "Error deleting user: " . $e->getMessage();
        }
    } else {
        $error_message = "You do not have permission to delete this user.";
    }
}

// Handle user updates
if (isset($_POST['update_user'])) {
    $user_id = intval($_POST['user_id']);
    $username = $conn->real_escape_string(trim($_POST['username']));
    $role = $conn->real_escape_string(trim($_POST['role']));
    $store_id = ($role === 'admin') ? null : intval($_POST['store_id']);

    // Check if username already exists for other users
    $check_sql = "SELECT id FROM users WHERE username = '$username' AND id != $user_id";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows > 0) {
        $error_message = "Username already exists. Please choose a different username.";
    } else {
        // Update user
        $sql = "UPDATE users SET 
                username = '$username',
                role = '$role',
                store_id = " . ($store_id ? $store_id : "NULL") . "
                WHERE id = $user_id";

        if ($conn->query($sql)) {
            $success_message = "User updated successfully!";
        } else {
            $error_message = "Error updating user: " . $conn->error;
        }
    }
}

// Fetch users with their store information
$sql = "SELECT u.*, s.name as store_name 
        FROM users u 
        LEFT JOIN stores s ON u.store_id = s.id";

// If admin, only show users from their store
if ($_SESSION['role'] === 'admin') {
    $sql .= " WHERE u.store_id = " . $_SESSION['store_id'];
}

$sql .= " ORDER BY u.username";
$users = $conn->query($sql);

// Fetch stores for dropdown
$stores = $conn->query("SELECT id, name FROM stores");

// Check if there are any stores
$store_count = $stores->num_rows;
if ($store_count === 0) {
    $error_message = "No stores available. Please add a store first before managing users.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }

        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            position: fixed;
            width: 250px;
            z-index: 100;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem;
            margin: 0.2rem 0;
            border-radius: 0.35rem;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }

        .sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 1.5rem;
            text-align: center;
        }

        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
        }

        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            border-radius: 0.35rem 0.35rem 0 0 !important;
            padding: 1.5rem;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .card-body {
            padding: 2rem;
        }

        .table th {
            background-color: #f8f9fc;
            color: var(--primary-color);
            font-weight: 600;
            border-bottom: 2px solid #e3e6f0;
        }

        .table td {
            vertical-align: middle;
        }

        .btn-edit {
            color: var(--primary-color);
            background: none;
            border: none;
            padding: 0.25rem 0.5rem;
            transition: all 0.2s ease-in-out;
        }

        .btn-edit:hover {
            color: #2e59d9;
            transform: scale(1.1);
        }

        .btn-delete {
            color: var(--danger-color);
            background: none;
            border: none;
            padding: 0.25rem 0.5rem;
            transition: all 0.2s ease-in-out;
        }

        .btn-delete:hover {
            color: #be2617;
            transform: scale(1.1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            border-radius: 0.35rem 0.35rem 0 0;
        }

        .modal-content {
            border-radius: 0.35rem;
            border: none;
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .form-control, .form-select {
            border-radius: 0.35rem;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d3e2;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .welcome-text {
            color: #fff;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .system-title {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .user-profile {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
        }

        .user-profile img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-bottom: 0.5rem;
        }

        .btn-logout {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .badge-admin {
            background-color: var(--primary-color);
            color: white;
        }

        .badge-user {
            background-color: var(--secondary-color);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="user-profile text-center">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User Avatar">
            <h6 class="text-white mb-0"><?php echo htmlspecialchars($_SESSION['username']); ?></h6>
            <small class="welcome-text"><?php echo ucfirst($_SESSION['role']); ?></small>
        </div>        <ul class="nav flex-column px-3">
            <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="superadmin_dashboard.php">
                    <i class="bi bi-speedometer2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="manage_users.php">
                    <i class="bi bi-people"></i>Manage Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="add_store.php">
                    <i class="bi bi-shop"></i>Manage Stores
                </a>
            </li>
            <?php else: ?>
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="bi bi-speedometer2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="view_inventory.php">
                    <i class="bi bi-box-seam"></i>View Inventory
                </a>
            </li>            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="add_store.php">
                    <i class="bi bi-shop"></i>Add Store
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="add_product.php">
                    <i class="bi bi-plus-circle"></i>Add Product
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
            <li class="nav-item mt-auto">
                <a class="nav-link btn-logout" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
            <h1 class="h2 mb-0">Manage Users</h1>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-people me-2"></i>User Management</h3>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($store_count === 0): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        No stores available. Please <a href="add_store.php" class="alert-link">add a store</a> first before managing users.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Store</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr data-user-id="<?= $user['id'] ?>">
                                        <td class="username-cell"><?= htmlspecialchars($user['username']) ?></td>
                                        <td>
                                            <span class="badge <?= $user['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?> role-badge">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td class="store-cell"><?= htmlspecialchars($user['store_name'] ?? 'No store assigned') ?></td>
                                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>                                        <td>
                                        <?php if ($_SESSION['role'] === 'superadmin'): ?>
                                            <!-- Superadmin can edit all users except other superadmins -->
                                            <?php if ($user['role'] !== 'superadmin' || $user['id'] === $_SESSION['user_id']): ?>
                                            <button type="button" class="btn-edit" onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['role']; ?>', <?php echo $user['store_id'] ? $user['store_id'] : 'null'; ?>)">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button type="button" class="btn-edit" onclick="openChangePasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="bi bi-key"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <!-- Superadmin can delete all users except themselves and other superadmins -->
                                            <?php if ($user['id'] !== $_SESSION['user_id'] && $user['role'] !== 'superadmin'): ?>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php elseif ($_SESSION['role'] === 'admin' && $user['store_id'] === $_SESSION['store_id']): ?>
                                            <!-- Admin can edit users from their store except other admins -->
                                            <?php if ($user['role'] !== 'admin'): ?>
                                            <button type="button" class="btn-edit" onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['role']; ?>', <?php echo $user['store_id'] ? $user['store_id'] : 'null'; ?>)">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <?php endif; ?>
                                            <!-- Admin can change their own password -->
                                            <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                            <button type="button" class="btn-edit" onclick="openChangePasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="bi bi-key"></i>
                                            </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Edit Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editForm">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required onchange="toggleStoreField()">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div class="mb-3" id="storeField">
                            <label for="edit_store_id" class="form-label">Store</label>
                            <select class="form-select" id="edit_store_id" name="store_id">
                                <option value="">Select a store</option>
                                <?php 
                                $stores->data_seek(0);
                                while ($store = $stores->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $store['id']; ?>">
                                        <?php echo htmlspecialchars($store['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="update_user" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="changePasswordForm">
                        <input type="hidden" name="user_id" id="change_password_user_id">
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="bi bi-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <button type="submit" name="delete_user" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let editModal;
        let deleteModal;
        let changePasswordModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            changePasswordModal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
        });

        function openEditModal(userId, username, role, storeId) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = role;
            
            if (storeId) {
                document.getElementById('edit_store_id').value = storeId;
            } else {
                document.getElementById('edit_store_id').value = '';
            }
            
            toggleStoreField();
            editModal.show();
        }

        function openChangePasswordModal(userId, username) {
            document.getElementById('change_password_user_id').value = userId;
            document.getElementById('changePasswordForm').reset();
            changePasswordModal.show();
        }

        function confirmDelete(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUsername').textContent = username;
            deleteModal.show();
        }

        function toggleStoreField() {
            const role = document.getElementById('edit_role').value;
            const storeField = document.getElementById('storeField');
            const storeSelect = document.getElementById('edit_store_id');
            
            if (role === 'admin') {
                storeField.style.display = 'none';
                storeSelect.removeAttribute('required');
            } else {
                storeField.style.display = 'block';
                storeSelect.setAttribute('required', 'required');
            }
        }

        // Function to show alerts
        function showAlert(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.card-body').insertBefore(alertDiv, document.querySelector('.card-body').firstChild);
            
            // Auto dismiss after 3 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }

        // Function to update table row
        function updateUserRow(userId, username, role, storeName) {
            const row = document.querySelector(`tr[data-user-id="${userId}"]`);
            if (row) {
                // Update username
                const usernameCell = row.querySelector('.username-cell');
                if (usernameCell) {
                    usernameCell.textContent = username;
                }

                // Update role badge
                const roleCell = row.querySelector('.role-badge');
                if (roleCell) {
                    roleCell.textContent = role;
                    roleCell.className = `badge ${role === 'admin' ? 'bg-primary' : 'bg-secondary'} role-badge`;
                }

                // Update store
                const storeCell = row.querySelector('.store-cell');
                if (storeCell) {
                    storeCell.textContent = storeName || 'No store assigned';
                }
            }
        }

        // Handle form submissions with AJAX
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const action = formData.get('action');
                const userId = formData.get('user_id');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Create a temporary div to parse the response
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    // Check for success/error messages
                    const successMsg = tempDiv.querySelector('.alert-success');
                    const errorMsg = tempDiv.querySelector('.alert-danger');
                    
                    if (successMsg) {
                        showAlert(successMsg.textContent.trim());
                        
                        // Close modal if it's open
                        const modal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                        if (modal) {
                            modal.hide();
                        }

                        // If it's an update, update the table row directly
                        if (action === 'update_user') {
                            const username = formData.get('username');
                            const role = formData.get('role');
                            const storeName = formData.get('store_name');
                            updateUserRow(userId, username, role, storeName);
                        } else {
                            // For add/delete operations, reload after a short delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    } else if (errorMsg) {
                        showAlert(errorMsg.textContent.trim(), 'danger');
                    }
                })
                .catch(error => {
                    showAlert('An error occurred. Please try again.', 'danger');
                });
            });
        });

        // Handle delete confirmations
        document.querySelectorAll('[data-bs-target^="#deleteModal"]').forEach(button => {
            button.addEventListener('click', function() {
                const modalId = this.getAttribute('data-bs-target');
                const modal = document.querySelector(modalId);
                const form = modal.querySelector('form');
                
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(html => {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        
                        const successMsg = tempDiv.querySelector('.alert-success');
                        const errorMsg = tempDiv.querySelector('.alert-danger');
                        
                        if (successMsg) {
                            showAlert(successMsg.textContent.trim());
                            const modal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                            if (modal) {
                                modal.hide();
                            }
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else if (errorMsg) {
                            showAlert(errorMsg.textContent.trim(), 'danger');
                        }
                    })
                    .catch(error => {
                        showAlert('An error occurred. Please try again.', 'danger');
                    });
                });
            });
        });
    </script>
</body>
</html>