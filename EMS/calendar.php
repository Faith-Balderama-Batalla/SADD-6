<?php
// calendar.php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login-signup.php');
    exit();
}

$user = getCurrentUser($conn);
$user_id = $user['user_id'];

// Get organizations for sidebar (needed for the dropdown)
$orgs_sql = "SELECT o.* FROM organizations o
             JOIN organization_memberships om ON o.org_id = om.org_id
             WHERE om.user_id = ? AND om.status = 'active'
             ORDER BY o.org_name ASC";
$orgs_stmt = $conn->prepare($orgs_sql);
$orgs_stmt->bind_param("i", $user_id);
$orgs_stmt->execute();
$my_organizations = $orgs_stmt->get_result();

// Get unread notifications count (for the bell icon)
$notif_sql = "SELECT COUNT(*) as unread FROM notifications 
              WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$unread_count = $notif_result->fetch_assoc()['unread'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Univents</title>
    
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
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <!-- Page-specific content here -->
            <h1>Calendar Page</h1>
            <p>This is the calendar view - coming soon!</p>
        </div>
    </div>
    
</div> <!-- Close dashboard-container -->

    <!-- Same JavaScript -->
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