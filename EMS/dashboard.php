<?php
// dashboard.php - Student Dashboard
require_once 'config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login-signup.php');
    exit();
}

$user = getCurrentUser($conn);
$user_id = $user['user_id'];

// Get organizations the student is a member of
$orgs_sql = "SELECT o.*, om.status as membership_status 
             FROM organizations o
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

// Get upcoming events
$events_sql = "SELECT e.*, o.org_name, o.org_color 
               FROM events e
               JOIN organizations o ON e.org_id = o.org_id
               WHERE e.start_datetime > NOW() 
               AND e.status = 'upcoming'
               ORDER BY e.start_datetime ASC
               LIMIT 5";
$events_result = $conn->query($events_sql);

// Get attendance summary
$attendance_summary = ['present_count' => 0];
$table_check = $conn->query("SHOW TABLES LIKE 'attendance_logs'");
if ($table_check && $table_check->num_rows > 0) {
    $attendance_sql = "SELECT COUNT(*) as present_count FROM attendance_logs WHERE student_id = ? AND status = 'present'";
    $attendance_stmt = $conn->prepare($attendance_sql);
    if ($attendance_stmt) {
        $attendance_stmt->bind_param("i", $user_id);
        $attendance_stmt->execute();
        $attendance_summary = $attendance_stmt->get_result()->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Univents</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
     <?php include 'nav_sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="main-content" id="mainContent">
            <div class="dashboard-content">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <h2>Welcome back, <?php echo $user['first_name']; ?>! 👋</h2>
                    <p>Here's what's happening with your organizations</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(79, 209, 197, 0.1);">
                            <i class="fas fa-calendar-check" style="color: var(--primary-mint);"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $my_organizations->num_rows; ?></h3>
                            <p>Organizations</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(79, 209, 197, 0.1);">
                            <i class="fas fa-check-circle" style="color: var(--primary-mint);"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $attendance_summary['present_count'] ?? 0; ?></h3>
                            <p>Events Attended</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(79, 209, 197, 0.1);">
                            <i class="fas fa-bell" style="color: var(--primary-mint);"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $unread_count; ?></h3>
                            <p>Notifications</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(79, 209, 197, 0.1);">
                            <i class="fas fa-clock" style="color: var(--primary-mint);"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $events_result->num_rows; ?></h3>
                            <p>Upcoming Events</p>
                        </div>
                    </div>
                </div>

                <!-- My Organizations Cards -->
                <div class="section-header">
                    <h3>My Organizations</h3>
                    <a href="organizations.php" class="view-all-link">View All</a>
                </div>
                
                <div class="organizations-grid">
                    <?php 
                    $my_organizations->data_seek(0);
                    if($my_organizations->num_rows > 0): 
                        while($org = $my_organizations->fetch_assoc()): 
                    ?>
                        <div class="org-card-dashboard">
                            <div class="org-banner" style="background-image: url('<?php echo $org['org_banner'] ?? 'https://via.placeholder.com/300x100/E6FFFA/2C7A7B?text=' . urlencode($org['org_name']); ?>');">
                                <div class="org-logo-wrapper">
                                    <?php if($org['org_logo']): ?>
                                        <img src="<?php echo $org['org_logo']; ?>" alt="<?php echo $org['org_name']; ?>" class="org-logo">
                                    <?php else: ?>
                                        <div class="org-logo-placeholder">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="org-card-content">
                                <h4><?php echo htmlspecialchars($org['org_name']); ?></h4>
                                <p class="org-description"><?php echo substr($org['org_description'] ?? 'Student organization at BU Polangui', 0, 60); ?>...</p>
                                <a href="organization.php?id=<?php echo $org['org_id']; ?>" class="btn-view-org">
                                    View <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php 
                        endwhile; 
                    endif; 
                    ?>
                    
                    <!-- Join New Organization Card -->
                    <div class="org-card-dashboard join-card">
                        <div class="join-card-content">
                            <div class="join-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <h4>Join a New Organization</h4>
                            <p>Connect with more student organizations</p>
                            <a href="organizations.php" class="btn-join-org">
                                Browse Organizations <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="section-header">
                    <h3>Upcoming Events</h3>
                    <a href="calendar.php" class="view-all-link">View Calendar</a>
                </div>
                
                <div class="events-preview-grid">
                    <?php if($events_result && $events_result->num_rows > 0): ?>
                        <?php while($event = $events_result->fetch_assoc()): ?>
                            <div class="event-preview-card">
                                <div class="event-date-badge">
                                    <span class="day"><?php echo date('d', strtotime($event['start_datetime'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($event['start_datetime'])); ?></span>
                                </div>
                                <div class="event-preview-details">
                                    <h5><?php echo htmlspecialchars($event['event_title']); ?></h5>
                                    <p class="event-org"><?php echo htmlspecialchars($event['org_name']); ?></p>
                                    <p class="event-time">
                                        <i class="far fa-clock"></i> 
                                        <?php echo date('g:i A', strtotime($event['start_datetime'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No upcoming events at the moment</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const collapseBtn = document.getElementById('collapseBtn');
    const menuToggle = document.getElementById('menuToggle');
    const orgDropdown = document.getElementById('orgDropdown');
    const orgMenu = document.getElementById('orgMenu');
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationMenu = document.getElementById('notificationMenu');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    // Collapse/Expand sidebar on click only
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
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
        // Show on hover
        notificationBtn.addEventListener('mouseenter', function() {
            clearTimeout(notificationTimeout);
            notificationMenu.classList.add('show');
        });
        
        // Hide after delay when mouse leaves button
        notificationBtn.addEventListener('mouseleave', function() {
            notificationTimeout = setTimeout(() => {
                if (!notificationMenu.matches(':hover')) {
                    notificationMenu.classList.remove('show');
                }
            }, 300);
        });
        
        // Keep open when hovering menu
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
        
        // Click to stay open
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu.classList.toggle('stay');
            notificationMenu.classList.add('show');
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationDropdown.contains(e.target)) {
                notificationMenu.classList.remove('show', 'stay');
            }
        });
    }
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 992) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('mobile-show');
            }
        }
    });
});
    </script>
</body>
</html>