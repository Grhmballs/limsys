<?php
/**
 * LIMSys User Profile Management
 * Allows users to edit their profile details and change password
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$message = '';
$message_type = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validation
        if (empty($name)) {
            $message = 'Name is required.';
            $message_type = 'danger';
        } elseif (empty($email)) {
            $message = 'Email is required.';
            $message_type = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $message_type = 'danger';
        } else {
            try {
                // Check if email is already taken by another user
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_stmt->execute([$email, $user_id]);
                
                if ($check_stmt->fetch()) {
                    $message = 'This email address is already taken by another user.';
                    $message_type = 'danger';
                } else {
                    // Update profile
                    $update_stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                    if ($update_stmt->execute([$name, $email, $user_id])) {
                        $_SESSION['email'] = $email; // Update session
                        $message = 'Profile updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update profile. Please try again.';
                        $message_type = 'danger';
                    }
                }
            } catch (PDOException $e) {
                $message = 'Database error. Please try again later.';
                $message_type = 'danger';
                error_log("Profile update error: " . $e->getMessage());
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password)) {
            $message = 'Current password is required.';
            $message_type = 'danger';
        } elseif (empty($new_password)) {
            $message = 'New password is required.';
            $message_type = 'danger';
        } elseif (strlen($new_password) < 6) {
            $message = 'New password must be at least 6 characters long.';
            $message_type = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match.';
            $message_type = 'danger';
        } else {
            try {
                // Verify current password
                $verify_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $verify_stmt->execute([$user_id]);
                $user_data = $verify_stmt->fetch();
                
                if (!$user_data || !password_verify($current_password, $user_data['password'])) {
                    $message = 'Current password is incorrect.';
                    $message_type = 'danger';
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    
                    if ($update_stmt->execute([$hashed_password, $user_id])) {
                        $message = 'Password changed successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to change password. Please try again.';
                        $message_type = 'danger';
                    }
                }
            } catch (PDOException $e) {
                $message = 'Database error. Please try again later.';
                $message_type = 'danger';
                error_log("Password change error: " . $e->getMessage());
            }
        }
    }
}

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT name, email, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: logout.php');
        exit();
    }
} catch (PDOException $e) {
    header('Location: logout.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - LIMSys</title>
    
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

        .sidebar {
            min-height: 100vh;
            background: var(--primary-blue);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 0.25rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link.active {
            color: white;
            background: var(--accent-red);
        }
        
        .main-content {
            padding: 2rem;
            background: var(--neutral-light);
        }
        
        .profile-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }
        
        .profile-header {
            background: var(--primary-blue);
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }
        
        .btn-custom {
            background: var(--primary-blue);
            border: none;
            color: white;
            font-weight: 500;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }
        
        .btn-custom:hover {
            background: var(--primary-blue-dark);
            color: white;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px var(--primary-blue-light);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .card-header {
            background: var(--primary-blue-light);
            border-bottom: 1px solid var(--primary-blue);
            font-weight: 600;
            color: var(--primary-blue-dark);
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .btn-primary:hover {
            background-color: var(--primary-blue-dark);
            border-color: var(--primary-blue-dark);
        }

        .btn-outline-secondary {
            color: var(--neutral-gray);
            border-color: var(--neutral-gray);
        }

        .btn-outline-secondary:hover {
            background-color: var(--neutral-gray);
            border-color: var(--neutral-gray);
        }

        /* Sidebar logo styling */
        .sidebar-logo {
            height: 50px;
            width: auto;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar p-3">
                    <div class="text-center mb-4">
                        <div class="mb-2">
                            <img src="logo.png" alt="LIMSys" class="sidebar-logo">
                        </div>
                        <small class="text-white-50"><?php echo ucfirst($user_role); ?> Panel</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <?php if ($user_role !== 'guest'): ?>
                        <a class="nav-link" href="upload.php">
                            <i class="bi bi-cloud-upload me-2"></i>Upload Document
                        </a>
                        <?php endif; ?>
                        <a class="nav-link active" href="profile.php">
                            <i class="bi bi-person-circle me-2"></i>My Profile
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house me-2"></i>Home
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3">My Profile</h1>
                            <p class="text-muted mb-0">Manage your account settings and preferences</p>
                        </div>
                        <a href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>" 
                           class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                    
                    <!-- Alert Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Profile Card -->
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card profile-card">
                                <div class="profile-header">
                                    <div class="profile-avatar">
                                        <i class="bi bi-person"></i>
                                    </div>
                                    <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                                    <p class="mb-0 opacity-75"><?php echo htmlspecialchars($user['email']); ?></p>
                                    <span class="badge bg-light text-dark mt-2"><?php echo ucfirst($user['role']); ?></span>
                                    <p class="mt-2 mb-0 opacity-75">
                                        <small>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></small>
                                    </p>
                                </div>
                                
                                <div class="card-body p-4">
                                    <!-- Profile Information Form -->
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="mb-0">
                                                <i class="bi bi-person-badge me-2"></i>Profile Information
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="name" class="form-label">Full Name</label>
                                                        <input type="text" 
                                                               class="form-control" 
                                                               id="name" 
                                                               name="name" 
                                                               value="<?php echo htmlspecialchars($user['name']); ?>" 
                                                               required>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="email" class="form-label">Email Address</label>
                                                        <input type="email" 
                                                               class="form-control" 
                                                               id="email" 
                                                               name="email" 
                                                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                                                               required>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="role" class="form-label">Role</label>
                                                        <input type="text" 
                                                               class="form-control" 
                                                               id="role" 
                                                               value="<?php echo ucfirst($user['role']); ?>" 
                                                               readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="member_since" class="form-label">Member Since</label>
                                                        <input type="text" 
                                                               class="form-control" 
                                                               id="member_since" 
                                                               value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" 
                                                               readonly>
                                                    </div>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="submit" name="update_profile" class="btn btn-custom">
                                                        <i class="bi bi-check-circle me-2"></i>Update Profile
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <!-- Change Password Form -->
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">
                                                <i class="bi bi-shield-lock me-2"></i>Change Password
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" id="passwordForm">
                                                <div class="mb-3">
                                                    <label for="current_password" class="form-label">Current Password</label>
                                                    <input type="password" 
                                                           class="form-control" 
                                                           id="current_password" 
                                                           name="current_password" 
                                                           required
                                                           placeholder="Enter your current password">
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="new_password" class="form-label">New Password</label>
                                                        <input type="password" 
                                                               class="form-control" 
                                                               id="new_password" 
                                                               name="new_password" 
                                                               required
                                                               minlength="6"
                                                               placeholder="Enter new password">
                                                        <div class="form-text">Minimum 6 characters</div>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                        <input type="password" 
                                                               class="form-control" 
                                                               id="confirm_password" 
                                                               name="confirm_password" 
                                                               required
                                                               placeholder="Confirm new password">
                                                        <div class="form-text" id="passwordMatch"></div>
                                                    </div>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="submit" name="change_password" class="btn btn-warning">
                                                        <i class="bi bi-key me-2"></i>Change Password
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    matchDiv.innerHTML = '<span class="text-success">✓ Passwords match</span>';
                } else {
                    matchDiv.innerHTML = '<span class="text-danger">✗ Passwords do not match</span>';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        });
        
        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long!');
                return false;
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
