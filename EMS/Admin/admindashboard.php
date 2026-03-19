<?php
// Admin/admindashboard.php
require_once '../config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ../login-signup.php');
    exit();
}

$user = getCurrentUser($conn);

// Check if user is admin
if ($user['role'] !== 'admin') {
    header('Location: ../StudentMembers/dashboard.php');
    exit();
}

// Get counts for dashboard widgets
$stats = [];

// Total users
$users_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

// Pending officers
$pending_count = $conn->query("SELECT COUNT(*) as count FROM pending_officers WHERE status = 'pending'")->fetch_assoc()['count'];

// Total organizations
$orgs_count = $conn->query("SELECT COUNT(*) as count FROM organizations")->fetch_assoc()['count'];

// Total events
$events_count = $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'];

// Recent users
$recent_users = $conn->query("SELECT user_id, first_name, last_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");

// Recent pending applications
$pending_sql = "SELECT p.*, u.first_name, u.last_name, u.email, u.id_number
                FROM pending_officers p
                JOIN users u ON p.user_id = u.user_id
                WHERE p.status = 'pending'
                ORDER BY p.request_date DESC
                LIMIT 5";
$recent_pending = $conn->query($pending_sql);

// Get unread notifications count
$notif_sql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user['user_id']);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$unread_count = $notif_result->fetch_assoc()['unread'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Univents</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Main CSS -->
    <link rel="stylesheet" href="../style.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../style3.css">
</head>
<body class="dashboard-body">
    
    <?php include 'nav_sidebar_admin.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <!-- Welcome Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Welcome, <?php echo $user['first_name']; ?>! 👋</h2>
                    <p class="text-muted">Here's what's happening in your system today</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt me-2"></i>Administrator
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card-admin">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $users_count; ?></h3>
                                <p class="text-muted mb-0">Total Users</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card-admin">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $pending_count; ?></h3>
                                <p class="text-muted mb-0">Pending Approvals</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card-admin">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon me-3">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $orgs_count; ?></h3>
                                <p class="text-muted mb-0">Organizations</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card-admin">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon me-3">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $events_count; ?></h3>
                                <p class="text-muted mb-0">Total Events</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Quick Actions</h5>
                            <a href="pending.php" class="btn btn-primary me-2">
                                <i class="fas fa-clock me-2"></i>Review Pending Applications
                            </a>
                            <a href="users.php" class="btn btn-outline-primary me-2">
                                <i class="fas fa-user-plus me-2"></i>Add New User
                            </a>
                            <a href="organizations.php" class="btn btn-outline-primary me-2">
                                <i class="fas fa-plus-circle me-2"></i>Create Organization
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Recent Users -->
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0">Recent Users</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if($recent_users && $recent_users->num_rows > 0): ?>
                                    <?php while($user_row = $recent_users->fetch_assoc()): ?>
                                        <div class="list-group-item border-0 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar-mini me-3">
                                                    <?php echo strtoupper(substr($user_row['first_name'], 0, 1) . substr($user_row['last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?php echo $user_row['first_name'] . ' ' . $user_row['last_name']; ?></h6>
                                                    <small class="text-muted"><?php echo $user_row['email']; ?></small>
                                                </div>
                                                <span class="badge bg-<?php 
                                                    echo $user_row['role'] == 'admin' ? 'danger' : 
                                                        ($user_row['role'] == 'org_officer' ? 'primary' : 
                                                        ($user_row['role'] == 'class_officer' ? 'warning' : 'secondary')); 
                                                ?>">
                                                    <?php echo str_replace('_', ' ', $user_row['role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="text-muted mb-0">No users found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0 text-end">
                            <a href="users.php" class="btn btn-sm btn-link">View All Users <i class="fas fa-arrow-right ms-2"></i></a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Pending Applications -->
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0">Pending Applications</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if($recent_pending && $recent_pending->num_rows > 0): ?>
                                    <?php while($app = $recent_pending->fetch_assoc()): ?>
                                        <div class="list-group-item border-0 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar-mini me-3" style="background: #F56565;">
                                                    <?php echo strtoupper(substr($app['first_name'], 0, 1) . substr($app['last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?php echo $app['first_name'] . ' ' . $app['last_name']; ?></h6>
                                                    <small class="text-muted"><?php echo $app['id_number']; ?></small>
                                                </div>
                                                <span class="badge bg-warning"><?php echo str_replace('_', ' ', $app['requested_role']); ?></span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <p class="text-muted mb-0">No pending applications</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0 text-end">
                            <a href="pending.php" class="btn btn-sm btn-link">Review All <i class="fas fa-arrow-right ms-2"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    </div> <!-- Close main-content -->
    </div> <!-- Close dashboard-container (opened in nav_sidebar_admin.php) -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Sidebar JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const collapseBtn = document.getElementById('collapseBtn');
        const menuToggle = document.getElementById('menuToggle');
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
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
        
        // Profile dropdown
        if (profileBtn) {
            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileMenu.classList.toggle('show');
                if (notificationMenu) notificationMenu.classList.remove('show');
            });
        }
        
        // Notification dropdown
        if (notificationBtn) {
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationMenu.classList.toggle('show');
                if (profileMenu) profileMenu.classList.remove('show');
            });
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            if (profileMenu) profileMenu.classList.remove('show');
            if (notificationMenu) notificationMenu.classList.remove('show');
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 992) {
                if (sidebar && !sidebar.contains(e.target) && menuToggle && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('mobile-show');
                }
            }
        });
    });
</script>
</body>
</html>