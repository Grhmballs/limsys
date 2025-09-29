<?php
/**
 * LIMSys Admin Password Reset Management
 * Allows admins to view and process password reset requests
 */

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$message = '';
$message_type = '';

// Handle password reset processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_password'])) {
        $reset_id = intval($_POST['reset_id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        
        // Validation
        if (empty($new_password)) {
            $message = 'New password is required.';
            $message_type = 'danger';
        } elseif (strlen($new_password) < 6) {
            $message = 'New password must be at least 6 characters long.';
            $message_type = 'danger';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Get reset request details
                $reset_stmt = $pdo->prepare("SELECT pr.*, u.name, u.email FROM password_resets pr 
                                           JOIN users u ON pr.user_id = u.id 
                                           WHERE pr.id = ? AND pr.status = 'pending'");
                $reset_stmt->execute([$reset_id]);
                $reset_request = $reset_stmt->fetch();
                
                if (!$reset_request) {
                    throw new Exception('Invalid or already processed reset request.');
                }
                
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update user password
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if (!$update_stmt->execute([$hashed_password, $reset_request['user_id']])) {
                    throw new Exception('Failed to update user password.');
                }
                
                // Mark reset request as completed
                $complete_stmt = $pdo->prepare("UPDATE password_resets SET 
                                              status = 'completed', 
                                              admin_notes = ?, 
                                              processed_by = ?, 
                                              processed_at = NOW() 
                                              WHERE id = ?");
                if (!$complete_stmt->execute([$admin_notes, $_SESSION['user_id'], $reset_id])) {
                    throw new Exception('Failed to update reset request status.');
                }
                
                $pdo->commit();
                
                $message = "Password reset completed successfully for {$reset_request['name']} ({$reset_request['email']}).";
                $message_type = 'success';
                
            } catch (Exception $e) {
                $pdo->rollback();
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'danger';
            } catch (PDOException $e) {
                $pdo->rollback();
                $message = 'Database error. Please try again later.';
                $message_type = 'danger';
                error_log("Admin password reset error: " . $e->getMessage());
            }
        }
    }
    
    if (isset($_POST['cancel_request'])) {
        $reset_id = intval($_POST['reset_id'] ?? 0);
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        
        try {
            $cancel_stmt = $pdo->prepare("UPDATE password_resets SET 
                                        status = 'cancelled', 
                                        admin_notes = ?, 
                                        processed_by = ?, 
                                        processed_at = NOW() 
                                        WHERE id = ? AND status = 'pending'");
            
            if ($cancel_stmt->execute([$admin_notes, $_SESSION['user_id'], $reset_id])) {
                $message = 'Password reset request cancelled successfully.';
                $message_type = 'warning';
            } else {
                $message = 'Failed to cancel reset request.';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Database error. Please try again later.';
            $message_type = 'danger';
            error_log("Admin cancel reset error: " . $e->getMessage());
        }
    }
}

// Fetch all password reset requests
try {
    // Pending requests
    $pending_stmt = $pdo->prepare("SELECT pr.*, u.name, u.email, u.role 
                                 FROM password_resets pr 
                                 JOIN users u ON pr.user_id = u.id 
                                 WHERE pr.status = 'pending' 
                                 ORDER BY pr.requested_at ASC");
    $pending_stmt->execute();
    $pending_requests = $pending_stmt->fetchAll();
    
    // Recent processed requests (last 30 days)
    $processed_stmt = $pdo->prepare("SELECT pr.*, u.name, u.email, u.role, 
                                   admin.name as admin_name 
                                   FROM password_resets pr 
                                   JOIN users u ON pr.user_id = u.id 
                                   LEFT JOIN users admin ON pr.processed_by = admin.id 
                                   WHERE pr.status IN ('completed', 'cancelled') 
                                   AND pr.processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                   ORDER BY pr.processed_at DESC");
    $processed_stmt->execute();
    $processed_requests = $processed_stmt->fetchAll();
    
} catch (PDOException $e) {
    $pending_requests = [];
    $processed_requests = [];
    error_log("Admin fetch reset requests error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Management - LIMSys Admin</title>
    
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
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .request-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease;
        }
        
        .request-card:hover {
            transform: translateY(-2px);
        }
        
        .request-header {
            background: var(--primary-blue);
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .btn-custom {
            background: var(--primary-blue);
            border: none;
            color: white;
            font-weight: 500;
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }
        
        .btn-custom:hover {
            background: var(--primary-blue-dark);
            color: white;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .time-ago {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
        }
        
        .stats-card {
            background: var(--primary-blue);
            color: white;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
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
                        <small class="text-white-50">Admin Panel</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <a class="nav-link active" href="admin_reset_password.php">
                            <i class="bi bi-key me-2"></i>Password Resets
                        </a>
                        <a class="nav-link" href="upload.php">
                            <i class="bi bi-cloud-upload me-2"></i>Upload Document
                        </a>
                        <a class="nav-link" href="profile.php">
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
                            <h1 class="h3">Password Reset Management</h1>
                            <p class="text-muted mb-0">Manage user password reset requests</p>
                        </div>
                        <a href="admin_dashboard.php" class="btn btn-outline-secondary">
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
                    
                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo count($pending_requests); ?></div>
                                <div>Pending Requests</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo count($processed_requests); ?></div>
                                <div>Processed (30 days)</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Requests -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-hourglass-split me-2"></i>Pending Password Reset Requests
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pending_requests)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                                    <h4 class="mt-3">No Pending Requests</h4>
                                    <p class="text-muted">All password reset requests have been processed.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pending_requests as $request): ?>
                                    <div class="request-card">
                                        <div class="request-header">
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3">
                                                    <i class="bi bi-person"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($request['name']); ?></h6>
                                                    <small class="time-ago">
                                                        <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($request['email']); ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="bi bi-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($request['requested_at'])); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-warning">
                                                    <?php echo ucfirst($request['role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" class="row g-3">
                                                <input type="hidden" name="reset_id" value="<?php echo $request['id']; ?>">
                                                
                                                <div class="col-md-6">
                                                    <label for="new_password_<?php echo $request['id']; ?>" class="form-label">New Password</label>
                                                    <input type="password" 
                                                           class="form-control" 
                                                           id="new_password_<?php echo $request['id']; ?>" 
                                                           name="new_password" 
                                                           required
                                                           minlength="6"
                                                           placeholder="Enter new password">
                                                    <div class="form-text">Minimum 6 characters</div>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <label for="admin_notes_<?php echo $request['id']; ?>" class="form-label">Admin Notes (Optional)</label>
                                                    <textarea class="form-control" 
                                                            id="admin_notes_<?php echo $request['id']; ?>" 
                                                            name="admin_notes" 
                                                            rows="2"
                                                            placeholder="Add any notes about this reset"></textarea>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <div class="d-flex gap-2">
                                                        <button type="submit" name="reset_password" class="btn btn-success">
                                                            <i class="bi bi-check-circle me-2"></i>Reset Password
                                                        </button>
                                                        <button type="submit" name="cancel_request" class="btn btn-outline-danger" 
                                                                onclick="return confirm('Are you sure you want to cancel this request?')">
                                                            <i class="bi bi-x-circle me-2"></i>Cancel Request
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Processed Requests -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-check-circle me-2"></i>Recently Processed Requests (Last 30 Days)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($processed_requests)): ?>
                                <div class="text-center py-3">
                                    <p class="text-muted mb-0">No processed requests in the last 30 days.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Email</th>
                                                <th>Status</th>
                                                <th>Processed By</th>
                                                <th>Processed Date</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($processed_requests as $request): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($request['name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo ucfirst($request['role']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($request['email']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $request['status'] === 'completed' ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo ucfirst($request['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $request['admin_name'] ? htmlspecialchars($request['admin_name']) : 'N/A'; ?></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($request['processed_at'])); ?></td>
                                                    <td>
                                                        <?php if ($request['admin_notes']): ?>
                                                            <small><?php echo htmlspecialchars($request['admin_notes']); ?></small>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Password strength indicator
        document.querySelectorAll('input[type="password"]').forEach(function(input) {
            input.addEventListener('input', function() {
                const password = this.value;
                const helpText = this.nextElementSibling;
                
                if (password.length === 0) {
                    helpText.textContent = 'Minimum 6 characters';
                    helpText.className = 'form-text';
                } else if (password.length < 6) {
                    helpText.textContent = 'Password too short';
                    helpText.className = 'form-text text-danger';
                } else {
                    helpText.textContent = 'Password strength: Good';
                    helpText.className = 'form-text text-success';
                }
            });
        });
        
        // Form submission confirmation for password reset
        document.querySelectorAll('button[name="reset_password"]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                const form = this.closest('form');
                const userName = form.closest('.request-card').querySelector('h6').textContent;
                
                if (!confirm(`Are you sure you want to reset the password for ${userName}?`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
