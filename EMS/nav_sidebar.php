<?php
// nav_sidebar.php - Navigation and Sidebar Template
// This file should be included in all dashboard pages
?>
<!-- TOP NAVBAR - Full Width -->
<nav class="dashboard-navbar">
    <div class="nav-left">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="navbar-brand">
            <i class="fas fa-calendar-check brand-icon"></i>
            <span class="brand-text">Univents</span>
        </div>
    </div>
    
    <div class="nav-right">
        <!-- Notifications -->
        <!-- Notifications -->
<div class="notification-dropdown" id="notificationDropdown">
    <button class="notification-btn <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active-page' : ''; ?>" id="notificationBtn">
        <i class="fas fa-bell"></i>
        <?php if(isset($unread_count) && $unread_count > 0): ?>
            <span class="notification-badge"><?php echo $unread_count; ?></span>
        <?php endif; ?>
    </button>
    <div class="notification-menu" id="notificationMenu">
        <div class="notification-header">
            <h6>Notifications</h6>
            <a href="notifications.php?filter=unread" class="mark-all-read">View all</a>
        </div>
        <div class="notification-list">
            <!-- Sample notifications - in a real app, these would be dynamic -->
            <div class="notification-item unread">
                <div class="notification-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="notification-content">
                    <p><strong>CSS</strong> posted a new announcement</p>
                    <small>5 minutes ago</small>
                </div>
            </div>
            <div class="notification-item">
                <div class="notification-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="notification-content">
                    <p>Tech Talk event starts in 2 hours</p>
                    <small>1 hour ago</small>
                </div>
            </div>
        </div>
        <div class="notification-footer">
            <a href="notifications.php">See all notifications</a>
        </div>
    </div>
</div>
    </div>
</nav>

<div class="dashboard-container">
    <!-- LEFT SIDEBAR - Below Navbar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="collapse-btn" id="collapseBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <!-- Dashboard -->
            <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" data-tooltip="Dashboard">
                <div class="menu-icon">
                    <i class="fas fa-th-large"></i>
                </div>
                <span class="menu-text">Dashboard</span>
                <div class="active-indicator"></div>
            </a>
            
            <!-- Calendar -->
            <a href="calendar.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>" data-tooltip="Calendar">
                <div class="menu-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <span class="menu-text">Calendar</span>
                <div class="active-indicator"></div>
            </a>
            
            <!-- Attendance -->
            <a href="attendance.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>" data-tooltip="Attendance">
                <div class="menu-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <span class="menu-text">Attendance</span>
                <div class="active-indicator"></div>
            </a>
            
            <!-- Organizations Dropdown -->
            <div class="menu-item dropdown-trigger" id="orgDropdown" data-tooltip="Organizations">
                <div class="menu-icon">
                    <i class="fas fa-users"></i>
                </div>
                <span class="menu-text">Organizations</span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
                <div class="active-indicator"></div>
            </div>
            
            <!-- Organization Submenu -->
            <div class="dropdown-menu-items" id="orgMenu">
                <?php 
                if(isset($my_organizations) && $my_organizations->num_rows > 0): 
                    $my_organizations->data_seek(0);
                    while($org = $my_organizations->fetch_assoc()): 
                ?>
                <a href="organization.php?id=<?php echo $org['org_id']; ?>" class="submenu-item">
                    <div class="submenu-dot" style="background-color: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>"></div>
                    <span><?php echo htmlspecialchars($org['org_name']); ?></span>
                </a>
                <?php 
                    endwhile; 
                else: 
                ?>
                <div class="submenu-item text-muted">
                    <span>No organizations yet</span>
                </div>
                <?php endif; ?>
                <a href="organizations.php" class="submenu-item view-all">
                    <i class="fas fa-plus-circle"></i>
                    <span>Join Organization</span>
                </a>
            </div>
            
            <!-- Settings -->
            <a href="settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" data-tooltip="Settings">
                <div class="menu-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <span class="menu-text">Settings</span>
                <div class="active-indicator"></div>
            </a>
        </div>
        
        <!-- User Profile at Bottom - Clickable -->
        <a href="profile.php" class="sidebar-footer-link">
            <div class="sidebar-footer">
                <div class="user-info-mini">
                    <div class="user-avatar-mini">
                        <?php if(isset($user) && $user['profile_picture']): ?>
                            <img src="<?php echo $user['profile_picture']; ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo isset($user) ? strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) : 'GU'; ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-details-mini">
                        <span class="user-name-mini"><?php echo isset($user) ? $user['first_name'] . ' ' . $user['last_name'] : 'Guest User'; ?></span>
                        <span class="user-role-mini"><?php echo isset($user) ? ucfirst(str_replace('_', ' ', $user['role'])) : 'Guest'; ?></span>
                    </div>
                    <i class="fas fa-chevron-right profile-arrow"></i>
                </div>
            </div>
        </a>
    </div>
    <!-- NOTE: No closing div here - the dashboard-container continues in the main content -->

    