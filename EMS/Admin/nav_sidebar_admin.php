<?php
// nav_sidebar_admin.php - Admin Navigation Sidebar
// Make sure $user is defined before including this file
?>
<!-- TOP NAVBAR - Full Width -->
<nav class="dashboard-navbar">
    <div class="nav-left">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="navbar-brand">
            <i class="fas fa-calendar-check brand-icon"></i>
            <span class="brand-text">Univents Admin</span>
        </div>
    </div>
    
    <div class="nav-right">
        <!-- Notifications -->
        <div class="notification-dropdown" id="notificationDropdown">
            <button class="notification-btn" id="notificationBtn">
                <i class="fas fa-bell"></i>
                <?php if(isset($unread_count) && $unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </button>
            <div class="notification-menu" id="notificationMenu">
                <div class="notification-header">
                    <h6>Notifications</h6>
                    <a href="notifications.php" class="mark-all-read">View all</a>
                </div>
                <div class="notification-list">
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="notification-content">
                            <p>No new notifications</p>
                        </div>
                    </div>
                </div>
                <div class="notification-footer">
                    <a href="notifications.php">See all notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Admin Profile -->
        <div class="user-profile-dropdown">
            <button class="user-profile-btn" id="profileBtn">
                <div class="profile-image">
                    <?php if(isset($user) && $user['profile_picture']): ?>
                        <img src="<?php echo $user['profile_picture']; ?>" alt="Profile">
                    <?php else: ?>
                        <div class="profile-initials">
                            <?php echo isset($user) ? getUserInitials($user) : 'A'; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="welcome-text"><?php echo isset($user) ? $user['first_name'] : 'Admin'; ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="profile-menu" id="profileMenu">
                <a href="profile.php" class="profile-menu-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="settings.php" class="profile-menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="../logout.php" class="profile-menu-item text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="dashboard-container">
    <!-- LEFT SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="collapse-btn" id="collapseBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <!-- Dashboard -->
            <a href="admindashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admindashboard.php' ? 'active' : ''; ?>" data-tooltip="Dashboard">
                <div class="menu-icon">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <span class="menu-text">Dashboard</span>
                <div class="active-indicator"></div>
            </a>
            
            <!-- Users Management -->
            <a href="users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" data-tooltip="Users">
                <div class="menu-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <span class="menu-text">Users</span>
                <div class="active-indicator"></div>
            </a>
            
            <!-- Organizations Management -->
            <a href="organizations.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'organizations.php' ? 'active' : ''; ?>" data-tooltip="Organizations">
                <div class="menu-icon">
                    <i class="fas fa-building"></i>
                </div>
                <span class="menu-text">Organizations</span>
                <div class="active-indicator"></div>
            </a>
            
            <!-- Pending Approvals -->
            <a href="pending.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'pending.php' ? 'active' : ''; ?>" data-tooltip="Pending Approvals">
                <div class="menu-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <span class="menu-text">Pending</span>
                <?php
                // Count pending approvals
                $pending_count = 0;
                if (isset($conn)) {
                    $count_sql = "SELECT COUNT(*) as count FROM pending_officers WHERE status = 'pending'";
                    $count_result = $conn->query($count_sql);
                    if ($count_result) {
                        $pending_count = $count_result->fetch_assoc()['count'];
                    }
                }
                ?>
                <?php if($pending_count > 0): ?>
                    <span class="badge bg-danger ms-auto"><?php echo $pending_count; ?></span>
                <?php endif; ?>
                <div class="active-indicator"></div>
            </a>
            
            <!-- Events -->
            <a href="events.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>" data-tooltip="Events">
                <div class="menu-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <span class="menu-text">Events</span>
                <div class="active-indicator"></div>
            </a>
            
            <!-- Reports -->
            <a href="reports.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" data-tooltip="Reports">
                <div class="menu-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span class="menu-text">Reports</span>
                <div class="active-indicator"></div>
            </a>
            
            <!-- Settings -->
            <a href="settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" data-tooltip="Settings">
                <div class="menu-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <span class="menu-text">Settings</span>
                <div class="active-indicator"></div>
            </a>
        </div>
        
        <!-- User Profile at Bottom -->
        <a href="profile.php" class="sidebar-footer-link">
            <div class="sidebar-footer">
                <div class="user-info-mini">
                    <div class="user-avatar-mini">
                        <?php if(isset($user) && $user['profile_picture']): ?>
                            <img src="<?php echo $user['profile_picture']; ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo isset($user) ? getUserInitials($user) : 'A'; ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-details-mini">
                        <span class="user-name-mini"><?php echo isset($user) ? $user['first_name'] . ' ' . $user['last_name'] : 'Admin'; ?></span>
                        <span class="user-role-mini">Administrator</span>
                    </div>
                    <i class="fas fa-chevron-right profile-arrow"></i>
                </div>
            </div>
        </a>
    </div>
    <!-- NOTE: No closing div - continues in main content -->