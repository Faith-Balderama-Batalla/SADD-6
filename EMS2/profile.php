<?php
// profile.php - User Profile Page
require_once 'config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login-signup.php');
    exit();
}

$user = getCurrentUser($conn);
$user_id = $user['user_id'];
$success = '';
$error = '';

// Determine which sidebar to use based on role
$sidebar_file = '';
switch($user['role']) {
    case 'admin':
        $sidebar_file = 'Admin/nav_sidebar_admin.php';
        break;
    case 'org_officer':
        $sidebar_file = 'Officer/nav_sidebar_officer.php';
        break;
    default:
        $sidebar_file = 'Student/nav_sidebar_student.php';
        break;
}

// Get organizations for sidebar dropdown (for student sidebar)
$my_organizations = null;
if ($user['role'] !== 'admin') {
    $orgs_sql = "SELECT o.* FROM organizations o
                 JOIN organization_memberships om ON o.org_id = om.org_id
                 WHERE om.user_id = ? AND om.status = 'active'
                 ORDER BY o.org_name ASC";
    $orgs_stmt = $conn->prepare($orgs_sql);
    $orgs_stmt->bind_param("i", $user_id);
    $orgs_stmt->execute();
    $my_organizations = $orgs_stmt->get_result();
}

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

    // Handle class officer application (only for students)
    if (isset($_POST['submit_officer_application']) && $user['role'] === 'student_member') {
        $reason = mysqli_real_escape_string($conn, $_POST['application_reason']);
        
        $insert_app_sql = "INSERT INTO pending_officers (user_id, requested_role, course, year_level, block, reason) 
                           VALUES (?, 'class_officer', ?, ?, ?, ?)";
        $insert_app_stmt = $conn->prepare($insert_app_sql);
        $insert_app_stmt->bind_param("isiss", $user_id, $user['course'], $user['year_level'], $user['block'], $reason);
        
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
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary-mint);
            box-shadow: 0 5px 15px rgba(79, 209, 197, 0.3);
            margin: 0 auto;
        }
        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-initials-large {
            width: 100%;
            height: 100%;
            background: var(--gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 600;
        }
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(79, 209, 197, 0.1);
            transition: all 0.3s ease;
        }
        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(79, 209, 197, 0.1);
        }
        .info-label {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-dark);
        }
        .divider-custom {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary-mint), transparent);
            margin: 20px 0;
        }
    </style>
</head>
<body class="dashboard-body">
    
    <?php include $sidebar_file; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">My Profile</h2>
                    <p class="text-muted">Manage your personal information and account settings</p>
                </div>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
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
                <!-- Left Column - Profile Picture & Info -->
                <div class="col-lg-4 mb-4">
                    <!-- Profile Picture Card -->
                    <div class="card border-0 shadow-sm mb-4">
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
                            
                            <div class="divider-custom"></div>
                            
                            <!-- Quick Info -->
                            <div class="text-start">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="info-label">Member Since</div>
                                        <div class="info-value"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Status</div>
                                        <div class="info-value">
                                            <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-label">Role</div>
                                <div class="info-value mb-3">
                                    <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                                </div>
                                <div class="info-label">Email</div>
                                <div class="info-value mb-3">
                                    <i class="fas fa-envelope me-2 text-muted"></i><?php echo $user['email']; ?>
                                </div>
                                <div class="info-label">Contact Number</div>
                                <div class="info-value mb-3">
                                    <i class="fas fa-phone me-2 text-muted"></i><?php echo $user['contact_number'] ?? 'Not provided'; ?>
                                </div>
                                <div class="info-label">Course / Year / Block</div>
                                <div class="info-value">
                                    <i class="fas fa-graduation-cap me-2 text-muted"></i>
                                    <?php echo $user['course'] ?? 'N/A'; ?> - 
                                    Year <?php echo $user['year_level'] ?? 'N/A'; ?> - 
                                    Block <?php echo $user['block'] ?? 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Status Card -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h5 class="mb-3"><i class="fas fa-shield-alt me-2 text-primary"></i>Account Status</h5>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Account Verified</span>
                                <i class="fas fa-check-circle text-success"></i>
                            </div>
                            <div class="progress mb-3" style="height: 5px;">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Profile Complete</span>
                                <span class="text-muted small">
                                    <?php 
                                    $complete = 0;
                                    if($user['contact_number']) $complete += 25;
                                    if($user['course']) $complete += 25;
                                    if($user['year_level']) $complete += 25;
                                    if($user['block']) $complete += 25;
                                    echo $complete . '%';
                                    ?>
                                </span>
                            </div>
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar" style="width: <?php echo $complete; ?>%; background: var(--gradient);"></div>
                            </div>
                            <?php if($complete < 100): ?>
                                <p class="text-muted small mt-3">Complete your profile to get the full experience</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Edit Forms -->
                <div class="col-lg-8">
                    <!-- Personal Information Form -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0"><i class="fas fa-user-edit me-2 text-primary"></i>Personal Information</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="">
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
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">ID Number</label>
                                        <input type="text" class="form-control" value="<?php echo $user['id_number']; ?>" readonly disabled>
                                        <small class="text-muted">ID number cannot be changed</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" value="<?php echo $user['email']; ?>" readonly disabled>
                                        <small class="text-muted">Email cannot be changed</small>
                                    </div>
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
                                            <option value="BSBA" <?php echo ($user['course'] ?? '') == 'BSBA' ? 'selected' : ''; ?>>BS Business Administration</option>
                                            <option value="BSED" <?php echo ($user['course'] ?? '') == 'BSED' ? 'selected' : ''; ?>>BS Education</option>
                                            <option value="BEED" <?php echo ($user['course'] ?? '') == 'BEED' ? 'selected' : ''; ?>>BEED</option>
                                            <option value="BSN" <?php echo ($user['course'] ?? '') == 'BSN' ? 'selected' : ''; ?>>BS Nursing</option>
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
                                
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" name="update_profile" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Change Password Form -->
                    <div class="card border-0 shadow-sm mb-4">
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
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="change_password" class="btn btn-warning px-4">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Class Officer Application (only for student members) -->
                    <?php if($user['role'] === 'student_member'): ?>
                    <div class="card border-0 shadow-sm">
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
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" name="submit_officer_application" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Application
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
</div> <!-- Close dashboard-container -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Sidebar JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const collapseBtn = document.getElementById('collapseBtn');
        const menuToggle = document.getElementById('menuToggle');
        
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
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-show');
            });
        }
        
        // Organizations dropdown for student/officer
        const orgDropdown = document.getElementById('orgDropdown');
        const orgMenu = document.getElementById('orgMenu');
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
        
        // Notification dropdown
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationMenu = document.getElementById('notificationMenu');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
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
        
        // Profile dropdown
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
        if (profileBtn) {
            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileMenu.classList.toggle('show');
                if (notificationMenu) notificationMenu.classList.remove('show');
            });
        }
        
        document.addEventListener('click', function() {
            if (profileMenu) profileMenu.classList.remove('show');
            if (notificationMenu) notificationMenu.classList.remove('show');
        });
    });
</script>
</body>
</html>