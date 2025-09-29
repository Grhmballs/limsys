<?php
/**
 * LIMSys Registration Page
 * Handles user registration with validation and password hashing
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

$error_message = '';
$success_message = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        // Include database connection
        require_once 'db.php';
        
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error_message = 'Email address already exists. Please use a different email.';
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                
                if ($stmt->execute([$name, $email, $hashed_password, 'user', 'active'])) {
                    $success_message = 'Registration successful! Redirecting to login page...';
                    // Redirect to login page after 2 seconds
                    header("refresh:2;url=login.php");
                } else {
                    $error_message = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Database error. Please try again later.';
            // Log error for debugging (optional)
            // error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIMSys - Register</title>
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Simple Municipal Color Scheme - Blue Main with Red/Yellow Accents */
        :root {
            --primary-blue: #2563EB;
            --primary-blue-dark: #1D4ED8;
            --primary-blue-light: #DBEAFE;
            --accent-red: #DC2626;
            --accent-red-light: #FEE2E2;
            --accent-yellow: #F59E0B;
            --accent-yellow-light: #FEF3C7;
            --neutral-gray: #6B7280;
            --neutral-light: #F9FAFB;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--primary-blue);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .register-container {
            background: white;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 450px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h1 {
            color: var(--primary-blue-dark);
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .register-header p {
            color: var(--neutral-gray);
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--neutral-gray);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px var(--primary-blue-light);
        }
        
        .register-btn {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin-top: 0.5rem;
        }
        
        .register-btn:hover {
            background: var(--primary-blue-dark);
        }
        
        .error-message {
            background: var(--accent-red-light);
            color: var(--accent-red);
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            border: 1px solid var(--accent-red);
            font-size: 0.9rem;
        }
        
        .success-message {
            background: #D1FAE5;
            color: #065F46;
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            border: 1px solid #10B981;
            font-size: 0.9rem;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #E5E7EB;
        }
        
        .login-link a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            color: var(--accent-yellow);
            text-decoration: underline;
        }
        
        .dashboard-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }
        
        .dashboard-link:hover {
            color: var(--primary-blue-dark);
            text-decoration: underline;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--neutral-gray);
            font-size: 0.8rem;
        }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Register - LIMSys</h1>
            <p>Create your Laboratory Information Management System account</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    required 
                    value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                    placeholder="Enter your full name"
                >
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    placeholder="Enter your email address"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    placeholder="Create a password"
                >
                <div class="password-requirements">
                    Password must be at least 6 characters long
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    required 
                    placeholder="Confirm your password"
                >
            </div>
            
            <button type="submit" class="register-btn">Create Account</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="index.php" class="dashboard-link">Back to Public Dashboard</a>
        </div>
        
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
        
        <div class="footer">
            <p>&copy; 2024 LIMSys. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
