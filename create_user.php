<?php
include 'db.php';
session_start();

if (isset($_POST['register'])) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password']; // plain text, as requested
    $role = $conn->real_escape_string(trim($_POST['role']));
    $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : null;

    // For superadmin, store_id is always NULL
    if ($role === 'superadmin') {
        $store_id = null;
    }

    // Validation: store required for admin/user, not for superadmin
    if (($role === 'admin' || $role === 'user') && !$store_id) {
        $error = "Store selection is required for admin and user roles.";
    } else {
        $sql = "INSERT INTO users (username, password, role, store_id)
                VALUES ('$username', '$password', '$role', " . ($store_id ? $store_id : "NULL") . ")";

        if ($conn->query($sql)) {
            $success = "User registered successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
        }

        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #e3e6f0 100%);
            min-height: 100vh;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
        }

        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            border-radius: 1rem 1rem 0 0 !important;
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

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d3e2;
            transition: all 0.2s ease-in-out;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.5rem;
            transition: all 0.2s ease-in-out;
        }

        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
            transform: translateY(-1px);
        }

        .back-link {
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
        }

        .back-link:hover {
            color: #2e59d9;
            transform: translateX(-5px);
        }

        .input-group-text {
            background-color: #f8f9fc;
            border-right: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin_dashboard.php" class="back-link">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
        </a>

        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-person-plus me-2"></i>Create New User</h3>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="userForm">
                    <div class="mb-4">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Enter username" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-key"></i>
                            </span>
                            <input type="text" class="form-control" id="password" name="password" 
                                   placeholder="Enter password" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="role" class="form-label">Role</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person-badge"></i>
                            </span>
                            <select class="form-select" id="role" name="role" required onchange="toggleStoreField()">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                                <option value="superadmin">Superadmin</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4" id="storeField">
                        <label for="store_id" class="form-label">Store</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-shop"></i>
                            </span>
                            <select class="form-select" id="store_id" name="store_id" required>
                                <option value="">Select a store</option>
                                <?php
                                $result = $conn->query("SELECT id, name FROM stores");
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="register" class="btn btn-primary">
                            <i class="bi bi-person-plus me-2"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleStoreField() {
            const role = document.getElementById('role').value;
            const storeField = document.getElementById('storeField');
            const storeSelect = document.getElementById('store_id');
            if (role === 'superadmin') {
                storeField.style.display = 'none';
                storeSelect.removeAttribute('required');
            } else {
                storeField.style.display = 'block';
                storeSelect.setAttribute('required', 'required');
            }
        }

        // Initialize the store field visibility on page load
        document.addEventListener('DOMContentLoaded', toggleStoreField);
        document.getElementById('role').addEventListener('change', toggleStoreField);
    </script>
</body>
</html>
