<?php
/**
 * LIMSys Forgot Password Page
 * Allows users to request password reset from admin
 */

session_start();

// Redirect logged-in users
if (isset($_SESSION['user_id'])) {
    $redirect_url = $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php';
    header("Location: $redirect_url");
    exit();
}

require_once 'db.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    if (empty($email)) {
        $message = 'Email address is required.';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'danger';
    } else {
        try {
            // Check if user exists
            $user_stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active'");
            $user_stmt->execute([$email]);
            $user = $user_stmt->fetch();
            
            if ($user) {
                // Check if there's already a pending request
                $existing_stmt = $pdo->prepare("SELECT id FROM password_resets WHERE user_id = ? AND status = 'pending'");
                $existing_stmt->execute([$user['id']]);
                
                if ($existing_stmt->fetch()) {
                    $message = 'A password reset request is already pending for this email address. Please contact the administrator.';
                    $message_type = 'warning';
                } else {
                    // Create new password reset request
                    $insert_stmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, requested_at, status) VALUES (?, ?, NOW(), 'pending')");
                    
                    if ($insert_stmt->execute([$user['id'], $email])) {
                        $message = 'Password reset request submitted successfully! An administrator will process your request shortly. Please contact the administrator if you need immediate assistance.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to submit password reset request. Please try again later.';
                        $message_type = 'danger';
                    }
                }
            } else {
                // For security, don't reveal if email exists or not
                $message = 'If this email address exists in our system, a password reset request has been submitted to the administrator.';
                $message_type = 'info';
            }
        } catch (PDOException $e) {
            $message = 'Database error. Please try again later.';
            $message_type = 'danger';
            error_log("Forgot password error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - LIMSys</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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

        body {
            background: var(--primary-blue);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .forgot-password-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            margin: 20px;
        }
        
        .forgot-password-header {
            background: var(--primary-blue);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .forgot-password-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .forgot-password-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .forgot-password-form {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .submit-btn {
            background: var(--primary-blue);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            width: 100%;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .submit-btn:hover {
            background: var(--primary-blue-dark);
        }
        
        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background-color: #d1edff;
            color: #0c5460;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .icon-container {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e1e5e9;
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="forgot-password-header">
            <div class="icon-container">
                <i class="bi bi-key"></i>
            </div>
            <h1>Forgot Password?</h1>
            <p>Enter your email address and we'll notify the administrator to reset your password</p>
        </div>
        
        <div class="forgot-password-form">
            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                    <?php if ($message_type === 'success'): ?>
                        <i class="bi bi-check-circle me-2"></i>
                    <?php elseif ($message_type === 'danger'): ?>
                        <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php elseif ($message_type === 'warning'): ?>
                        <i class="bi bi-exclamation-circle me-2"></i>
                    <?php else: ?>
                        <i class="bi bi-info-circle me-2"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="forgotPasswordForm">
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope me-2"></i>Email Address
                    </label>
                    <input 
                        type="email" 
                        class="form-control" 
                        id="email" 
                        name="email" 
                        required 
                        placeholder="Enter your email address"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="bi bi-send me-2"></i>Submit Reset Request
                </button>
            </form>
            
            <div class="footer-text">
                <a href="login.php" class="back-link">
                    <i class="bi bi-arrow-left"></i>Back to Login
                </a>
                <br><br>
                <small>
                    <strong>Need immediate help?</strong><br>
                    Contact your system administrator directly for urgent password reset requests.
                </small>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form submission handling
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const email = document.getElementById('email').value.trim();
            
            if (!email) {
                e.preventDefault();
                alert('Please enter your email address.');
                return;
            }
            
            // Disable submit button to prevent double submission
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Submitting...';
            
            // Re-enable button after 3 seconds in case of errors
            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Reset Request';
            }, 3000);
        });
        
        // Auto-hide success messages after 8 seconds
        setTimeout(function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.transition = 'opacity 0.5s ease';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 500);
            }
        }, 8000);
    </script>
</body>
</html>