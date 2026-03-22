<?php
// admin/profile.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    if (isset($_POST['update_profile'])) {
        $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
        $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        $contact_number = mysqli_real_escape_string($conn, trim($_POST['contact_number']));
        
        // Check email uniqueness
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->bind_param("si", $email, $user['user_id']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Email already in use by another account.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, contact_number = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $contact_number, $user['user_id']);
            if ($stmt->execute()) {
                $success = "Profile updated successfully!";
                // Refresh user data
                $user = getCurrentUser($conn);
            } else {
                $error = "Failed to update profile.";
            }
            $stmt->close();
        }
        $check->close();
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $error = "New password must contain at least one uppercase letter.";
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $error = "New password must contain at least one number.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $user['user_id']);
            if ($stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password.";
            }
            $stmt->close();
        }
    }
}

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Profile - Univents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="mb-4">
                <h2 class="mb-1">
                    <i class="fas fa-user-circle me-2" style="color: var(--primary-mint);"></i>
                    My Profile
                </h2>
                <p class="text-muted">Manage your account information and security settings</p>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row g-4">
                <!-- Profile Information -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2" style="color: var(--primary-mint);"></i>
                                Profile Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="text-center mb-4">
                                    <div class="profile-image-large mx-auto mb-3" style="width: 100px; height: 100px;">
                                        <div class="profile-initials" style="font-size: 2.5rem;">
                                            <?php echo getUserInitials($user); ?>
                                        </div>
                                    </div>
                                    <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                    <span class="badge bg-primary">Administrator</span>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($user['contact_number']); ?>" placeholder="Optional">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ID Number</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['id_number']); ?>" disabled readonly>
                                    <small class="text-muted">ID number cannot be changed</small>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-key me-2" style="color: var(--primary-mint);"></i>
                                Change Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" id="new_password" required>
                                    <div class="password-strength mt-2">
                                        <div class="strength-bar"></div>
                                        <small class="strength-text"></small>
                                    </div>
                                    <small class="text-muted">Minimum 8 characters, at least one uppercase letter and one number</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-warning w-100">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Account Stats -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2" style="color: var(--primary-mint);"></i>
                                Account Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="stat-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                    <small class="text-muted">Member Since</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="stat-value"><?php echo $unread_count; ?></div>
                                    <small class="text-muted">Unread Notifications</small>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Last login: <?php echo date('F j, Y g:i A', strtotime($user['updated_at'] ?? 'now')); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
// Password strength indicator
const passwordInput = document.getElementById('new_password');
if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.querySelector('.strength-bar');
        const strengthText = document.querySelector('.strength-text');
        
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        const percentage = (strength / 4) * 100;
        strengthBar.style.width = percentage + '%';
        
        if (strength <= 1) {
            strengthBar.style.background = '#F56565';
            strengthText.textContent = 'Weak';
        } else if (strength <= 2) {
            strengthBar.style.background = '#F6AD55';
            strengthText.textContent = 'Fair';
        } else if (strength <= 3) {
            strengthBar.style.background = '#4FD1C5';
            strengthText.textContent = 'Good';
        } else {
            strengthBar.style.background = '#48BB78';
            strengthText.textContent = 'Strong';
        }
    });
}
</script>
</body>
</html>