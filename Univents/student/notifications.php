<?php
// notifications.php - User Notifications Page
require_once '../config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ../login-signup.php');
    exit();
}

$user = getCurrentUser($conn);
$user_id = $user['user_id'];

// Get organizations for sidebar dropdown
$orgs_sql = "SELECT o.* FROM organizations o
             JOIN organization_memberships om ON o.org_id = om.org_id
             WHERE om.user_id = ? AND om.status = 'active'
             ORDER BY o.org_name ASC";
$orgs_stmt = $conn->prepare($orgs_sql);
$orgs_stmt->bind_param("i", $user_id);
$orgs_stmt->execute();
$my_organizations = $orgs_stmt->get_result();

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $notification_id = intval($_POST['notification_id']);
    $update_sql = "UPDATE notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $notification_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $update_sql = "UPDATE notifications SET is_read = TRUE WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Handle delete notification
if (isset($_POST['delete_notification'])) {
    $notification_id = intval($_POST['notification_id']);
    $delete_sql = "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $notification_id, $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
}

// Get filter (all, unread, read)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$notif_sql = "SELECT * FROM notifications WHERE user_id = ?";
if ($filter === 'unread') {
    $notif_sql .= " AND is_read = FALSE";
} elseif ($filter === 'read') {
    $notif_sql .= " AND is_read = TRUE";
}
$notif_sql .= " ORDER BY created_at DESC";

$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();

// Get counts for badges
$count_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN is_read = TRUE THEN 1 ELSE 0 END) as read_count
              FROM notifications WHERE user_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$counts = $count_stmt->get_result()->fetch_assoc();

// Unread count for the bell icon (still needed)
$unread_count = $counts['unread'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Univents</title>
    
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
                    <h2 class="mb-1">Notifications</h2>
                    <p class="text-muted">Stay updated with your organizations</p>
                </div>
                <div>
                    <span class="badge bg-primary me-2">Total: <?php echo $counts['total'] ?? 0; ?></span>
                    <span class="badge bg-warning me-2">Unread: <?php echo $counts['unread'] ?? 0; ?></span>
                    <span class="badge bg-secondary">Read: <?php echo $counts['read_count'] ?? 0; ?></span>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <ul class="nav nav-tabs border-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                           href="?filter=all">
                            All <span class="badge bg-secondary ms-1"><?php echo $counts['total'] ?? 0; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'unread' ? 'active' : ''; ?>" 
                           href="?filter=unread">
                            Unread <span class="badge bg-warning ms-1"><?php echo $counts['unread'] ?? 0; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'read' ? 'active' : ''; ?>" 
                           href="?filter=read">
                            Read <span class="badge bg-secondary ms-1"><?php echo $counts['read_count'] ?? 0; ?></span>
                        </a>
                    </li>
                </ul>
                
                <?php if($counts['unread'] > 0): ?>
                <form method="POST" class="d-inline">
                    <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-check-double me-2"></i>Mark All as Read
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <!-- Notifications List -->
            <?php if($notifications && $notifications->num_rows > 0): ?>
                <div class="notifications-list">
                    <?php while($notif = $notifications->fetch_assoc()): ?>
                        <div class="notification-card <?php echo !$notif['is_read'] ? 'unread' : ''; ?>" 
                             id="notification-<?php echo $notif['notification_id']; ?>">
                            
                            <div class="notification-card-content">
                                <div class="d-flex align-items-start gap-3">
                                    <!-- Icon based on type -->
                                    <div class="notification-card-icon <?php echo $notif['notification_type']; ?>">
                                        <?php
                                        $icon = 'fa-bell';
                                        switch($notif['notification_type']) {
                                            case 'event':
                                                $icon = 'fa-calendar-check';
                                                break;
                                            case 'announcement':
                                                $icon = 'fa-bullhorn';
                                                break;
                                            case 'attendance':
                                                $icon = 'fa-check-circle';
                                                break;
                                            case 'merchandise':
                                                $icon = 'fa-tshirt';
                                                break;
                                            case 'system':
                                                $icon = 'fa-cog';
                                                break;
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="notification-card-title">
                                                    <?php echo htmlspecialchars($notif['title']); ?>
                                                    <?php if(!$notif['is_read']): ?>
                                                        <span class="badge bg-warning ms-2">New</span>
                                                    <?php endif; ?>
                                                </h5>
                                                <p class="notification-card-message">
                                                    <?php echo htmlspecialchars($notif['message']); ?>
                                                </p>
                                                <small class="notification-card-time">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php echo date('F j, Y \a\t g:i A', strtotime($notif['created_at'])); ?>
                                                </small>
                                            </div>
                                            
                                            <div class="notification-card-actions">
                                                <?php if(!$notif['is_read']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="notification_id" 
                                                               value="<?php echo $notif['notification_id']; ?>">
                                                        <button type="submit" name="mark_read" 
                                                                class="btn btn-sm btn-outline-success me-1"
                                                                title="Mark as read">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if($notif['reference_id']): ?>
                                                    <a href="<?php 
                                                        switch($notif['notification_type']) {
                                                            case 'event':
                                                                echo 'event.php?id=' . $notif['reference_id'];
                                                                break;
                                                            case 'announcement':
                                                                echo 'announcement.php?id=' . $notif['reference_id'];
                                                                break;
                                                            default:
                                                                echo '#';
                                                        }
                                                    ?>" class="btn btn-sm btn-outline-primary me-1" 
                                                       title="View details">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Delete this notification?');">
                                                    <input type="hidden" name="notification_id" 
                                                           value="<?php echo $notif['notification_id']; ?>">
                                                    <button type="submit" name="delete_notification" 
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state text-center py-5">
                    <div class="empty-state-icon mb-3">
                        <i class="fas fa-bell-slash fa-4x text-muted"></i>
                    </div>
                    <h4>No notifications yet</h4>
                    <p class="text-muted">When you get notifications, they'll appear here</p>
                    <?php if($filter !== 'all'): ?>
                        <a href="?filter=all" class="btn btn-outline-primary mt-3">
                            <i class="fas fa-arrow-left me-2"></i>View all notifications
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
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
        
        // Notification hover/click behavior (still works on this page)
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