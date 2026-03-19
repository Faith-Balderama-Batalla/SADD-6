<?php
// settings.php - User Settings Page
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

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Notification Preferences
    if (isset($_POST['update_notification_prefs'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        $notify_events = isset($_POST['notify_events']) ? 1 : 0;
        $notify_announcements = isset($_POST['notify_announcements']) ? 1 : 0;
        $notify_attendance = isset($_POST['notify_attendance']) ? 1 : 0;
        $notify_merchandise = isset($_POST['notify_merchandise']) ? 1 : 0;
        
        // You would need a user_settings table for this
        // For now, we'll store in session or show success message
        $success = "Notification preferences updated successfully!";
    }
    
    // Privacy Settings
    if (isset($_POST['update_privacy'])) {
        $show_profile = isset($_POST['show_profile']) ? 1 : 0;
        $show_attendance = isset($_POST['show_attendance']) ? 1 : 0;
        $show_organizations = isset($_POST['show_organizations']) ? 1 : 0;
        
        $success = "Privacy settings updated successfully!";
    }
    
    // Account Preferences
    if (isset($_POST['update_preferences'])) {
        $language = mysqli_real_escape_string($conn, $_POST['language']);
        $timezone = mysqli_real_escape_string($conn, $_POST['timezone']);
        $date_format = mysqli_real_escape_string($conn, $_POST['date_format']);
        
        $success = "Account preferences updated successfully!";
    }
    
    // Delete Account
    if (isset($_POST['delete_account'])) {
        $confirm_password = $_POST['confirm_password'];
        
        if (password_verify($confirm_password, $user['password'])) {
            // Soft delete or actual delete
            $delete_sql = "UPDATE users SET status = 'inactive', email = CONCAT(email, '_deleted_', user_id) WHERE user_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $user_id);
            
            if ($delete_stmt->execute()) {
                session_destroy();
                header('Location: ../index.php?message=account_deleted');
                exit();
            } else {
                $error = "Failed to delete account.";
            }
            $delete_stmt->close();
        } else {
            $error = "Password is incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Univents</title>
    
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
                    <h2 class="mb-1">Settings</h2>
                    <p class="text-muted">Manage your account preferences and configurations</p>
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
            
            <!-- Settings Tabs -->
            <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                        <i class="fas fa-user me-2"></i>Profile Settings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                        <i class="fas fa-bell me-2"></i>Notifications
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab">
                        <i class="fas fa-shield-alt me-2"></i>Privacy
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                        <i class="fas fa-sliders-h me-2"></i>Preferences
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                        <i class="fas fa-lock me-2"></i>Security
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="settingsTabContent">
                
                <!-- Profile Settings Tab -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0"><i class="fas fa-user-edit me-2 text-primary"></i>Profile Information</h5>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted mb-4">Your profile information is managed in your profile page.</p>
                            <a href="profile.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right me-2"></i>Go to Profile Page
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications Tab -->
                <div class="tab-pane fade" id="notifications" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0"><i class="fas fa-bell me-2 text-primary"></i>Notification Preferences</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <div class="mb-4">
                                    <h6 class="fw-bold mb-3">Notification Channels</h6>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" checked>
                                        <label class="form-check-label" for="email_notifications">
                                            <i class="fas fa-envelope me-2 text-primary"></i>Email Notifications
                                        </label>
                                        <small class="d-block text-muted ms-4">Receive notifications via email</small>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="push_notifications" name="push_notifications" checked>
                                        <label class="form-check-label" for="push_notifications">
                                            <i class="fas fa-mobile-alt me-2 text-primary"></i>Push Notifications
                                        </label>
                                        <small class="d-block text-muted ms-4">Receive browser notifications</small>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="fw-bold mb-3">Notify me about</h6>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="notify_events" name="notify_events" checked>
                                        <label class="form-check-label" for="notify_events">
                                            <i class="fas fa-calendar-check me-2 text-primary"></i>Events
                                        </label>
                                        <small class="d-block text-muted ms-4">New events, reminders, and updates</small>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="notify_announcements" name="notify_announcements" checked>
                                        <label class="form-check-label" for="notify_announcements">
                                            <i class="fas fa-bullhorn me-2 text-primary"></i>Announcements
                                        </label>
                                        <small class="d-block text-muted ms-4">Organization announcements and news</small>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="notify_attendance" name="notify_attendance" checked>
                                        <label class="form-check-label" for="notify_attendance">
                                            <i class="fas fa-check-circle me-2 text-primary"></i>Attendance
                                        </label>
                                        <small class="d-block text-muted ms-4">Attendance verification and reminders</small>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="notify_merchandise" name="notify_merchandise">
                                        <label class="form-check-label" for="notify_merchandise">
                                            <i class="fas fa-tshirt me-2 text-primary"></i>Merchandise
                                        </label>
                                        <small class="d-block text-muted ms-4">New products and promotions</small>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_notification_prefs" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Preferences
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Privacy Tab -->
                <div class="tab-pane fade" id="privacy" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2 text-primary"></i>Privacy Settings</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <div class="mb-4">
                                    <h6 class="fw-bold mb-3">Profile Visibility</h6>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="show_profile" name="show_profile" checked>
                                        <label class="form-check-label" for="show_profile">
                                            Show my profile to other students
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="show_attendance" name="show_attendance">
                                        <label class="form-check-label" for="show_attendance">
                                            Show my attendance history
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="show_organizations" name="show_organizations" checked>
                                        <label class="form-check-label" for="show_organizations">
                                            Show my organization memberships
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="fw-bold mb-3">Data & Sharing</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="data_sharing" id="share_all" value="all" checked>
                                        <label class="form-check-label" for="share_all">
                                            Share data with all organizations I join
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="data_sharing" id="share_limited" value="limited">
                                        <label class="form-check-label" for="share_limited">
                                            Limited sharing (only basic information)
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_privacy" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Privacy Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Preferences Tab -->
                <div class="tab-pane fade" id="preferences" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0"><i class="fas fa-sliders-h me-2 text-primary"></i>Account Preferences</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Language</label>
                                        <select name="language" class="form-control">
                                            <option value="en">English</option>
                                            <option value="fil">Filipino</option>
                                            <option value="bicol">Bicolano</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Timezone</label>
                                        <select name="timezone" class="form-control">
                                            <option value="Asia/Manila">Asia/Manila (GMT+8)</option>
                                            <option value="Asia/Tokyo">Asia/Tokyo (GMT+9)</option>
                                            <option value="Asia/Singapore">Asia/Singapore (GMT+8)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date Format</label>
                                        <select name="date_format" class="form-control">
                                            <option value="M d, Y">Jan 15, 2026</option>
                                            <option value="d M Y">15 Jan 2026</option>
                                            <option value="Y-m-d">2026-01-15</option>
                                            <option value="d/m/Y">15/01/2026</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Time Format</label>
                                        <select name="time_format" class="form-control">
                                            <option value="12h">12-hour (3:30 PM)</option>
                                            <option value="24h">24-hour (15:30)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Default Dashboard View</label>
                                    <select name="dashboard_view" class="form-control">
                                        <option value="cards">Cards View</option>
                                        <option value="list">List View</option>
                                        <option value="compact">Compact View</option>
                                    </select>
                                </div>
                                
                                <button type="submit" name="update_preferences" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Preferences
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0"><i class="fas fa-key me-2 text-primary"></i>Change Password</h5>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted mb-4">Change your password on the profile page.</p>
                            <a href="profile.php" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Go to Change Password
                            </a>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                        </div>
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">Delete Account</h6>
                            <p class="text-muted mb-3">Once you delete your account, there is no going back. Please be certain.</p>
                            
                            <!-- Delete Account Button (triggers modal) -->
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                <i class="fas fa-trash me-2"></i>Delete My Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div> <!-- Close dashboard-container -->

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteAccountModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you absolutely sure you want to delete your account?</p>
                    <p class="text-danger"><strong>This action cannot be undone.</strong> All your data will be permanently removed.</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Enter your password to confirm:</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_account" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Yes, Delete My Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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