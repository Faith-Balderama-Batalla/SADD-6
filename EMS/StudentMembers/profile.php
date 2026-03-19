<?php
// profile.php - User Profile Page
require_once '../config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ../login-signup.php');
    exit();
}

$user = getCurrentUser($conn);
$user_id = $user['user_id'];
$success = '';
$error = '';

// Get organizations for sidebar dropdown
$orgs_sql = "SELECT o.* FROM organizations o
             JOIN organization_memberships om ON o.org_id = om.org_id
             WHERE om.user_id = ? AND om.status = 'active'
             ORDER BY o.org_name ASC";
$orgs_stmt = $conn->prepare($orgs_sql);
$orgs_stmt->bind_param("i", $user_id);
$orgs_stmt->execute();
$my_organizations = $orgs_stmt->get_result();

// Get unread notifications count
$notif_sql = "SELECT COUNT(*) as unread FROM notifications 
              WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$unread_count = $notif_result->fetch_assoc()['unread'] ?? 0;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
        $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
        $contact_number = mysqli_real_escape_string($conn, trim($_POST['contact_number']));
        $course = mysqli_real_escape_string($conn, $_POST['course']);
        $year_level = intval($_POST['year_level']);
        $block = mysqli_real_escape_string($conn, $_POST['block']);
        
        $update_sql = "UPDATE users SET 
                       first_name = ?, 
                       last_name = ?, 
                       contact_number = ?,
                       course = ?, 
                       year_level = ?, 
                       block = ?
                       WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssisi", $first_name, $last_name, $contact_number, $course, $year_level, $block, $user_id);
        
        if ($update_stmt->execute()) {
            $success = "Profile updated successfully!";
            // Refresh user data
            $user = getCurrentUser($conn);
        } else {
            $error = "Failed to update profile.";
        }
        $update_stmt->close();
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $pwd_sql = "UPDATE users SET password = ? WHERE user_id = ?";
                    $pwd_stmt = $conn->prepare($pwd_sql);
                    $pwd_stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($pwd_stmt->execute()) {
                        $success = "Password changed successfully!";
                    } else {
                        $error = "Failed to change password.";
                    }
                    $pwd_stmt->close();
                } else {
                    $error = "New password must be at least 8 characters.";
                }
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }

    // Handle class officer application
    if (isset($_POST['submit_officer_application'])) {
        $reason = mysqli_real_escape_string($conn, $_POST['application_reason']);
        
        $insert_app_sql = "INSERT INTO pending_officers (user_id, requested_role, course, year_level, block, request_date) 
                           VALUES (?, 'class_officer', ?, ?, ?, NOW())";
        $insert_app_stmt = $conn->prepare($insert_app_sql);
        $insert_app_stmt->bind_param("isis", $user_id, $user['course'], $user['year_level'], $user['block']);
        
        if ($insert_app_stmt->execute()) {
            $success = "Your application has been submitted for approval!";
        } else {
            $error = "Failed to submit application.";
        }
        $insert_app_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Univents</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    
    <?php include 'nav_sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">My Profile</h2>
                    <p class="text-muted">Manage your personal information and settings</p>
                </div>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Profile Picture Column -->
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="profile-avatar-large mx-auto mb-3">
                                <?php if($user['profile_picture']): ?>
                                    <img src="<?php echo $user['profile_picture']; ?>" alt="Profile">
                                <?php else: ?>
                                    <div class="avatar-initials-large">
                                        <?php echo getUserInitials($user); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h4 class="mb-1"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h4>
                            <p class="text-muted mb-3"><?php echo $user['id_number']; ?></p>
                            
                            <button class="btn btn-outline-primary w-100 mb-2" disabled>
                                <i class="fas fa-camera me-2"></i>Change Photo
                            </button>
                            <small class="text-muted">(Coming soon)</small>
                            
                            <hr class="my-4">
                            
                            <div class="text-start">
                                <p class="mb-2"><i class="fas fa-envelope me-2 text-primary"></i> <?php echo $user['email']; ?></p>
                                <p class="mb-2"><i class="fas fa-phone me-2 text-primary"></i> <?php echo $user['contact_number'] ?? 'Not provided'; ?></p>
                                <p class="mb-2"><i class="fas fa-graduation-cap me-2 text-primary"></i> <?php echo $user['course'] ?? 'N/A'; ?> - Year <?php echo $user['year_level'] ?? 'N/A'; ?></p>
                                <p class="mb-2"><i class="fas fa-layer-group me-2 text-primary"></i> Block <?php echo $user['block'] ?? 'N/A'; ?></p>
                                <p class="mb-2"><i class="fas fa-id-card me-2 text-primary"></i> Role: <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
                                <p class="mb-2"><i class="fas fa-clock me-2 text-primary"></i> Member since: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Profile Column -->
                <div class="col-md-8">
                    <!-- Personal Information -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0"><i class="fas fa-user-edit me-2 text-primary"></i>Personal Information</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="first_name" class="form-control" value="<?php echo $user['first_name']; ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" value="<?php echo $user['last_name']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ID Number</label>
                                    <input type="text" class="form-control" value="<?php echo $user['id_number']; ?>" readonly disabled>
                                    <small class="text-muted">ID number cannot be changed</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo $user['email']; ?>" readonly disabled>
                                    <small class="text-muted">Email cannot be changed</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="contact_number" class="form-control" value="<?php echo $user['contact_number'] ?? ''; ?>" placeholder="e.g., 09123456789">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Course</label>
                                        <select name="course" class="form-control" required>
                                            <option value="">Select Course</option>
                                            <option value="BSIT" <?php echo ($user['course'] ?? '') == 'BSIT' ? 'selected' : ''; ?>>BS Information Technology</option>
                                            <option value="BSCS" <?php echo ($user['course'] ?? '') == 'BSCS' ? 'selected' : ''; ?>>BS Computer Science</option>
                                            <option value="BSIS" <?php echo ($user['course'] ?? '') == 'BSIS' ? 'selected' : ''; ?>>BS Information Systems</option>
                                            <option value="BSED" <?php echo ($user['course'] ?? '') == 'BSED' ? 'selected' : ''; ?>>BS Education</option>
                                            <option value="BEED" <?php echo ($user['course'] ?? '') == 'BEED' ? 'selected' : ''; ?>>BEED</option>
                                            <option value="BSA" <?php echo ($user['course'] ?? '') == 'BSN' ? 'selected' : ''; ?>>BS Nursing</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Year Level</label>
                                        <select name="year_level" class="form-control" required>
                                            <option value="">Select</option>
                                            <option value="1" <?php echo ($user['year_level'] ?? '') == 1 ? 'selected' : ''; ?>>1st Year</option>
                                            <option value="2" <?php echo ($user['year_level'] ?? '') == 2 ? 'selected' : ''; ?>>2nd Year</option>
                                            <option value="3" <?php echo ($user['year_level'] ?? '') == 3 ? 'selected' : ''; ?>>3rd Year</option>
                                            <option value="4" <?php echo ($user['year_level'] ?? '') == 4 ? 'selected' : ''; ?>>4th Year</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Block</label>
                                        <select name="block" class="form-control">
                                            <option value="">Select</option>
                                            <option value="A" <?php echo ($user['block'] ?? '') == 'A' ? 'selected' : ''; ?>>Block A</option>
                                            <option value="B" <?php echo ($user['block'] ?? '') == 'B' ? 'selected' : ''; ?>>Block B</option>
                                            <option value="C" <?php echo ($user['block'] ?? '') == 'C' ? 'selected' : ''; ?>>Block C</option>
                                            <option value="D" <?php echo ($user['block'] ?? '') == 'D' ? 'selected' : ''; ?>>Block D</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0"><i class="fas fa-lock me-2 text-primary"></i>Change Password</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_password" class="form-control" required minlength="8">
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Class Officer Application (if not already officer) -->
<?php if($user['role'] !== 'class_officer'): ?>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0"><i class="fas fa-user-graduate me-2 text-primary"></i>Become a Class Officer</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3">Apply to become a class officer and get permission to mark attendance for your classmates.</p>
        
        <?php
        // Check if already applied
        $check_app_sql = "SELECT * FROM pending_officers WHERE user_id = ? AND requested_role = 'class_officer' AND status = 'pending'";
        $check_app_stmt = $conn->prepare($check_app_sql);
        $check_app_stmt->bind_param("i", $user_id);
        $check_app_stmt->execute();
        $existing_app = $check_app_stmt->get_result();
        
        if($existing_app->num_rows > 0): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>You have a pending application. Please wait for admin approval.
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="apply_class_officer" value="1">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Course</label>
                        <input type="text" class="form-control" value="<?php echo $user['course']; ?>" readonly>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Year Level</label>
                        <input type="text" class="form-control" value="<?php echo $user['year_level']; ?>" readonly>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Block</label>
                        <input type="text" class="form-control" value="<?php echo $user['block']; ?>" readonly>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason for Applying</label>
                    <textarea name="application_reason" class="form-control" rows="3" required placeholder="Why do you want to become a class officer?"></textarea>
                </div>
                <button type="submit" name="submit_officer_application" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
    
</div> <!-- Close dashboard-container -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Same sidebar JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const collapseBtn = document.getElementById('collapseBtn');
        const menuToggle = document.getElementById('menuToggle');
        const orgDropdown = document.getElementById('orgDropdown');
        const orgMenu = document.getElementById('orgMenu');
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationMenu = document.getElementById('notificationMenu');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        // Collapse/Expand sidebar
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });
        }
        
        // Mobile menu toggle
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-show');
            });
        }
        
        // Organizations dropdown
        if (orgDropdown) {
            orgDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                orgMenu.classList.toggle('show');
                const arrow = this.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('fa-chevron-down');
                    arrow.classList.toggle('fa-chevron-up');
                }
            });
        }
        
        // Notification hover/click behavior
        let notificationTimeout;
        
        if (notificationBtn && notificationMenu) {
            notificationBtn.addEventListener('mouseenter', function() {
                clearTimeout(notificationTimeout);
                notificationMenu.classList.add('show');
            });
            
            notificationBtn.addEventListener('mouseleave', function() {
                notificationTimeout = setTimeout(() => {
                    if (!notificationMenu.matches(':hover')) {
                        notificationMenu.classList.remove('show');
                    }
                }, 300);
            });
            
            notificationMenu.addEventListener('mouseenter', function() {
                clearTimeout(notificationTimeout);
            });
            
            notificationMenu.addEventListener('mouseleave', function() {
                notificationTimeout = setTimeout(() => {
                    if (!notificationMenu.classList.contains('stay')) {
                        notificationMenu.classList.remove('show');
                    }
                }, 300);
            });
            
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationMenu.classList.toggle('stay');
                notificationMenu.classList.add('show');
            });
            
            document.addEventListener('click', function(e) {
                if (!notificationDropdown.contains(e.target)) {
                    notificationMenu.classList.remove('show', 'stay');
                }
            });
        }
    });
</script>
</body>
</html>