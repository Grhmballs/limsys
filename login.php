<?php
/**
 * LIMSys Login Page
 * Handles user authentication and role-based redirection
 */

session_start();

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'user':
            header('Location: user_dashboard.php');
            break;
        default:
            header('Location: user_dashboard.php');
    }
    exit();
}

require_once 'db.php';
$error_message = '';

// Process login form submission
if ($_POST) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        try {
            // Prepare statement to check user credentials
            $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Valid credentials - start session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin_dashboard.php');
                        break;
                    case 'user':
                        header('Location: user_dashboard.php');
                        break;
                    default:
                        header('Location: user_dashboard.php');
                }
                exit();
            } else {
                $error_message = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIMSys - Login</title>
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .login-btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 0.75rem;
            border-radius: 5px;
            border-left: 4px solid #c33;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.8rem;
        }
        
        .forgot-password-link {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .forgot-password-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .dashboard-link {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .dashboard-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .text-right {
            text-align: right;
        }
        
        .mt-2 {
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>LIMSys</h1>
            <p>Legislative Information Management System</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    placeholder="Enter your email"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    placeholder="Enter your password"
                >
                <div class="text-right mt-2">
                    <a href="forgot_password.php" class="forgot-password-link">Forgot Password?</a>
                </div>
            </div>
            
            <button type="submit" class="login-btn">Login</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="index.php" class="dashboard-link">Back to Public Dashboard</a>
        </div>
        
        <div class="footer">
            <p>&copy; 2024 LIMSys. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
