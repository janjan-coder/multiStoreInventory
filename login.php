<?php
session_start();
require_once 'db.php';

// Redirect if already logged in
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] === 'superadmin') {
        header("Location: superadmin_dashboard.php");
    } else if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: view_inventory.php");
    }
    exit();
}

if (isset($_POST['login'])) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = trim($_POST['password']); // Don't escape password for comparison

    // First check if user exists
    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Compare passwords (since we're using plain text as per your setup)
        if ($password === $user['password']) {
            // Get store information if user is not admin
            if ($user['role'] !== 'admin' && $user['store_id']) {
                $store_sql = "SELECT name FROM stores WHERE id = " . intval($user['store_id']);
                $store_result = $conn->query($store_sql);
                $store = $store_result->fetch_assoc();
                $_SESSION['store_name'] = $store['name'];
            }

            // Set session variables
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['store_id'] = $user['store_id'];

            // Comprehensive debug logging
            error_log('=== Login Session Debug ===');
            error_log('Username: ' . $user['username']);
            error_log('Role: ' . $user['role']);
            error_log('User ID: ' . $user['id']);
            error_log('Store ID: ' . $user['store_id']);
            error_log('Current Page: login.php');
            error_log('=== End Login Debug ===');

            // Clear any existing redirects
            while (ob_get_level()) ob_end_clean();

            // Handle role-based navigation
            if ($user['role'] === 'superadmin') {
                error_log('Redirecting superadmin to superadmin_dashboard.php');
                header("Location: superadmin_dashboard.php");
            } else if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error_message = "Invalid password. Please try again.";
        }
    } else {
        $error_message = "Username not found. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory Management System</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }

        .login-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            padding: 2rem;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: var(--primary-color);
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: var(--secondary-color);
            font-size: 0.9rem;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-floating > .form-control {
            padding: 1rem 0.75rem;
            height: calc(3.5rem + 2px);
            line-height: 1.25;
        }

        .form-floating > label {
            padding: 1rem 0.75rem;
        }

        .btn-login {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
            padding: 0.75rem 1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.2s ease-in-out;
        }

        .btn-login:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .input-group-text {
            background-color: #f8f9fc;
            border-right: none;
        }

        .form-control {
            border-left: none;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #d1d3e2;
        }

        .input-group:focus-within {
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
            border-radius: 0.5rem;
        }

        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control {
            border-color: #bac8f3;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Welcome Back!</h1>
                <p>Please login to your account</p>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error_message; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group mb-3">
                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>
                    <div class="form-floating flex-grow-1">
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <label for="username">Username</label>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text">
                        <i class="bi bi-lock"></i>
                    </span>
                    <div class="form-floating flex-grow-1">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Password" required>
                        <label for="password">Password</label>
                    </div>
                </div>

                <button type="submit" name="login" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
