<?php
// settings.php - User Settings Page
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

// Get organizations for sidebar dropdown (for student/officer)
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

// Get user settings (for display)
$user_settings = [
    'email_notifications' => $_SESSION['notify_prefs']['email'] ?? true,
    'push_notifications' => $_SESSION['notify_prefs']['push'] ?? true,
    'notify_events' => $_SESSION['notify_prefs']['events'] ?? true,
    'notify_announcements' => $_SESSION['notify_prefs']['announcements'] ?? true,
    'notify_attendance' => $_SESSION['notify_prefs']['attendance'] ?? true,
    'show_profile' => $_SESSION['privacy']['profile'] ?? true,
    'show_attendance' => $_SESSION['privacy']['attendance'] ?? false,
    'show_organizations' => $_SESSION['privacy']['organizations'] ?? true,
    'language' => $_SESSION['prefs']['language'] ?? 'en',
    'timezone' => $_SESSION['prefs']['timezone'] ?? 'Asia/Manila',
    'date_format' => $_SESSION['prefs']['date_format'] ?? 'M d, Y',
    'time_format' => $_SESSION['prefs']['time_format'] ?? '12h'
];

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Notification Preferences
    if (isset($_POST['update_notification_prefs'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        $notify_events = isset($_POST['notify_events']) ? 1 : 0;
        $notify_announcements = isset($_POST['notify_announcements']) ? 1 : 0;
        $notify_attendance = isset($_POST['notify_attendance']) ? 1 : 0;
        
        $_SESSION['notify_prefs'] = [
            'email' => $email_notifications,
            'push' => $push_notifications,
            'events' => $notify_events,
            'announcements' => $notify_announcements,
            'attendance' => $notify_attendance
        ];
        
        // Update user_settings array for display
        $user_settings['email_notifications'] = $email_notifications;
        $user_settings['push_notifications'] = $push_notifications;
        $user_settings['notify_events'] = $notify_events;
        $user_settings['notify_announcements'] = $notify_announcements;
        $user_settings['notify_attendance'] = $notify_attendance;
        
        $success = "Notification preferences updated successfully!";
    }
    
    // Privacy Settings
    if (isset($_POST['update_privacy'])) {
        $show_profile = isset($_POST['show_profile']) ? 1 : 0;
        $show_attendance = isset($_POST['show_attendance']) ? 1 : 0;
        $show_organizations = isset($_POST['show_organizations']) ? 1 : 0;
        
        $_SESSION['privacy'] = [
            'profile' => $show_profile,
            'attendance' => $show_attendance,
            'organizations' => $show_organizations
        ];
        
        $user_settings['show_profile'] = $show_profile;
        $user_settings['show_attendance'] = $show_attendance;
        $user_settings['show_organizations'] = $show_organizations;
        
        $success = "Privacy settings updated successfully!";
    }
    
    // Account Preferences
    if (isset($_POST['update_preferences'])) {
        $language = mysqli_real_escape_string($conn, $_POST['language']);
        $timezone = mysqli_real_escape_string($conn, $_POST['timezone']);
        $date_format = mysqli_real_escape_string($conn, $_POST['date_format']);
        $time_format = mysqli_real_escape_string($conn, $_POST['time_format']);
        
        $_SESSION['prefs'] = [
            'language' => $language,
            'timezone' => $timezone,
            'date_format' => $date_format,
            'time_format' => $time_format
        ];
        
        $user_settings['language'] = $language;
        $user_settings['timezone'] = $timezone;
        $user_settings['date_format'] = $date_format;
        $user_settings['time_format'] = $time_format;
        
        $success = "Account preferences updated successfully!";
    }
    
    // Delete Account
    if (isset($_POST['delete_account'])) {
        $confirm_password = $_POST['confirm_password'];
        
        if (password_verify($confirm_password, $user['password'])) {
            // Soft delete
            $delete_sql = "UPDATE users SET status = 'inactive', email = CONCAT(email, '_deleted_', user_id) WHERE user_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $user_id);
            
            if ($delete_stmt->execute()) {
                session_destroy();
                header('Location: index.php?message=account_deleted');
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
    <link rel="stylesheet" href="style.css">
    <style>
        .settings-card {
            background: white;
            border-radius: 20px;
            border: 1px solid rgba(79, 209, 197, 0.1);
            transition: all 0.3s ease;
        }
        .settings-card:hover {
            box-shadow: 0 10px 25px rgba(79, 209, 197, 0.1);
        }
        .settings-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(79, 209, 197, 0.1);
        }
        .settings-body {
            padding: 25px;
        }
        .form-switch-custom {
            padding-left: 3rem;
        }
        .form-switch-custom .form-check-input {
            width: 2.5rem;
            height: 1.3rem;
            cursor: pointer;
        }
        .form-switch-custom .form-check-input:checked {
            background-color: var(--primary-mint);
            border-color: var(--primary-mint);
        }
        .setting-item {
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .setting-item:last-child {
            border-bottom: none;
        }
        .setting-label {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .setting-description {
            font-size: 0.85rem;
            color: var(--text-light);
        }
        .danger-zone {
            border: 1px solid #F56565;
            background: rgba(245, 101, 101, 0.02);
        }
        .danger-zone .settings-header {
            border-bottom-color: rgba(245, 101, 101, 0.2);
        }
        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: transparent;
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
        }
        .tab-btn.active {
            color: var(--primary-mint);
            border-bottom-color: var(--primary-mint);
        }
        .tab-btn:hover {
            color: var(--primary-mint);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
                    <h2 class="mb-1">Settings</h2>
                    <p class="text-muted">Manage your account preferences and configurations</p>
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
            
            <!-- Settings Tabs -->
            <div class="settings-card mb-4">
                <div class="settings-header">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="tab-btn active" data-tab="profile">Profile Settings</button>
                        <button class="tab-btn" data-tab="notifications">Notifications</button>
                        <button class="tab-btn" data-tab="privacy">Privacy</button>
                        <button class="tab-btn" data-tab="preferences">Preferences</button>
                        <button class="tab-btn" data-tab="security">Security</button>
                    </div>
                </div>
            </div>
            
            <!-- Tab Contents -->
            
            <!-- Profile Settings Tab -->
            <div class="tab-content active" id="tab-profile">
                <div class="settings-card">
                    <div class="settings-header">
                        <h5 class="mb-0"><i class="fas fa-user-edit me-2 text-primary"></i>Profile Information</h5>
                    </div>
                    <div class="settings-body">
                        <p class="text-muted mb-4">Your profile information is managed in your profile page.</p>
                        <a href="profile.php" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i>Go to Profile Page
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Tab -->
            <div class="tab-content" id="tab-notifications">
                <div class="settings-card">
                    <div class="settings-header">
                        <h5 class="mb-0"><i class="fas fa-bell me-2 text-primary"></i>Notification Preferences</h5>
                    </div>
                    <div class="settings-body">
                        <form method="POST">
                            <h6 class="fw-bold mb-3">Notification Channels</h6>
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Email Notifications</div>
                                        <div class="setting-description">Receive notifications via email</div>
                                    </div>
                                    <div class="form-check form-switch form-switch-custom">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo $user_settings['email_notifications'] ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Push Notifications</div>
                                        <div class="setting-description">Receive browser notifications</div>
                                    </div>
                                    <div class="form-check form-switch form-switch-custom">
                                        <input class="form-check-input" type="checkbox" id="push_notifications" name="push_notifications" <?php echo $user_settings['push_notifications'] ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <h6 class="fw-bold mt-4 mb-3">Notify me about</h6>
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Events</div>
                                        <div class="setting-description">New events, reminders, and updates</div>
                                    </div>
                                    <div class="form-check form-switch form-switch-custom">
                                        <input class="form-check-input" type="checkbox" id="notify_events" name="notify_events" <?php echo $user_settings['notify_events'] ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Announcements</div>
                                        <div class="setting-description">Organization announcements and news</div>
                                    </div>
                                    <div class="form-check form-switch form-switch-custom">
                                        <input class="form-check-input" type="checkbox" id="notify_announcements" name="notify_announcements" <?php echo $user_settings['notify_announcements'] ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Attendance</div>
                                        <div class="setting-description">Attendance verification and reminders</div>
                                    </div>
                                    <div class="form-check form-switch form-switch-custom">
                                        <input class="form-check-input" type="checkbox" id="notify_attendance" name="notify_attendance" <?php echo $user_settings['notify_attendance'] ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" name="update_notification_prefs" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-2"></i>Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Privacy Tab -->
            <div class="tab-content" id="tab-privacy">
                <div class="settings-card">
                    <div class="settings-header">
                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2 text-primary"></i>Privacy Settings</h5>
                    </div>
                    <div class="settings-body">
                        <form method="POST">
                            <h6 class="fw-bold mb-3">Profile Visibility</h6>
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Show my profile to other students</div>
                                    </div>
                                    <div class="form-check form-switch form-switch-custom">
                                        <input class="form-check-input" type="checkbox" id="show_profile" name="show_profile" <?php echo $user_settings['show_profile'] ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Show my attendance history</div>
                                    </div>
                                    <div class="form-check form-switch form-switch-custom">
                                        <input class="form-check-input" type="checkbox" id="show_attendance" name="show_attendance" <?php echo $user_settings['show_attendance'] ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Show my organization memberships</div>
                                    </div>
                                    <div class="form-check form-switch form-switch-custom">
                                        <input class="form-check-input" type="checkbox" id="show_organizations" name="show_organizations" <?php echo $user_settings['show_organizations'] ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <h6 class="fw-bold mt-4 mb-3">Data & Sharing</h6>
                            <div class="setting-item">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="data_sharing" id="share_all" value="all" checked>
                                    <label class="form-check-label" for="share_all">
                                        Share data with all organizations I join
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="data_sharing" id="share_limited" value="limited">
                                    <label class="form-check-label" for="share_limited">
                                        Limited sharing (only basic information)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" name="update_privacy" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-2"></i>Save Privacy Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Preferences Tab -->
            <div class="tab-content" id="tab-preferences">
                <div class="settings-card">
                    <div class="settings-header">
                        <h5 class="mb-0"><i class="fas fa-sliders-h me-2 text-primary"></i>Account Preferences</h5>
                    </div>
                    <div class="settings-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Language</label>
                                    <select name="language" class="form-control">
                                        <option value="en" <?php echo $user_settings['language'] == 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="fil" <?php echo $user_settings['language'] == 'fil' ? 'selected' : ''; ?>>Filipino</option>
                                        <option value="bicol" <?php echo $user_settings['language'] == 'bicol' ? 'selected' : ''; ?>>Bicolano</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Timezone</label>
                                    <select name="timezone" class="form-control">
                                        <option value="Asia/Manila" <?php echo $user_settings['timezone'] == 'Asia/Manila' ? 'selected' : ''; ?>>Asia/Manila (GMT+8)</option>
                                        <option value="Asia/Tokyo" <?php echo $user_settings['timezone'] == 'Asia/Tokyo' ? 'selected' : ''; ?>>Asia/Tokyo (GMT+9)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date Format</label>
                                    <select name="date_format" class="form-control">
                                        <option value="M d, Y" <?php echo $user_settings['date_format'] == 'M d, Y' ? 'selected' : ''; ?>>Jan 15, 2026</option>
                                        <option value="d M Y" <?php echo $user_settings['date_format'] == 'd M Y' ? 'selected' : ''; ?>>15 Jan 2026</option>
                                        <option value="Y-m-d" <?php echo $user_settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>2026-01-15</option>
                                        <option value="d/m/Y" <?php echo $user_settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>15/01/2026</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Time Format</label>
                                    <select name="time_format" class="form-control">
                                        <option value="12h" <?php echo $user_settings['time_format'] == '12h' ? 'selected' : ''; ?>>12-hour (3:30 PM)</option>
                                        <option value="24h" <?php echo $user_settings['time_format'] == '24h' ? 'selected' : ''; ?>>24-hour (15:30)</option>
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
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" name="update_preferences" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-2"></i>Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Security Tab -->
            <div class="tab-content" id="tab-security">
                <div class="settings-card mb-4">
                    <div class="settings-header">
                        <h5 class="mb-0"><i class="fas fa-key me-2 text-primary"></i>Change Password</h5>
                    </div>
                    <div class="settings-body">
                        <p class="text-muted mb-4">Change your password on the profile page.</p>
                        <a href="profile.php" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Go to Change Password
                        </a>
                    </div>
                </div>
                
                <div class="settings-card danger-zone">
                    <div class="settings-header">
                        <h5 class="mb-0 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                    </div>
                    <div class="settings-body">
                        <h6 class="fw-bold mb-3">Delete Account</h6>
                        <p class="text-muted mb-3">Once you delete your account, there is no going back. Please be certain.</p>
                        
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="fas fa-trash me-2"></i>Delete My Account
                        </button>
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

<!-- Tab Switching Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Update active tab button
                tabBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Show active tab content
                tabContents.forEach(content => content.classList.remove('active'));
                document.getElementById(`tab-${tabId}`).classList.add('active');
            });
        });
        
        // Sidebar functionality
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