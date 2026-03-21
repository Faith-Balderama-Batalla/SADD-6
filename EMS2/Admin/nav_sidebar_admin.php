<?php
// Admin/nav_sidebar_admin.php
// Make sure $user is defined before including this file
?>
<nav class="dashboard-navbar">
    <div class="nav-left">
        <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        <div class="navbar-brand">
            <i class="fas fa-calendar-check brand-icon"></i>
            <span class="brand-text">Univents Admin</span>
        </div>
    </div>
    <div class="nav-right">
        <div class="notification-dropdown" id="notificationDropdown">
            <button class="notification-btn" id="notificationBtn">
                <i class="fas fa-bell"></i>
                <?php if(isset($unread_count) && $unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </button>
            <div class="notification-menu" id="notificationMenu">
                <div class="notification-header"><h6>Notifications</h6><a href="notifications.php">View all</a></div>
                <div class="notification-list"><div class="notification-item"><div class="notification-icon"><i class="fas fa-info-circle"></i></div><div class="notification-content"><p>No new notifications</p></div></div></div>
            </div>
        </div>
        <div class="user-profile-dropdown">
            <button class="user-profile-btn" id="profileBtn">
                <div class="profile-image"><div class="profile-initials"><?php echo getUserInitials($user); ?></div></div>
                <span><?php echo $user['first_name']; ?></span><i class="fas fa-chevron-down"></i>
            </button>
            <div class="profile-menu" id="profileMenu">
                <a href="../profile.php" class="profile-menu-item"><i class="fas fa-user"></i><span>Profile</span></a>
                <a href="../settings.php" class="profile-menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
                <div class="dropdown-divider"></div>
                <a href="../logout.php" class="profile-menu-item text-danger"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </div>
        </div>
    </div>
</nav>

<div class="dashboard-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><button class="collapse-btn" id="collapseBtn"><i class="fas fa-chevron-left"></i></button></div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" data-tooltip="Dashboard">
                <i class="fas fa-tachometer-alt menu-icon"></i><span class="menu-text">Dashboard</span><div class="active-indicator"></div>
            </a>
            <a href="users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" data-tooltip="Users">
                <i class="fas fa-users-cog menu-icon"></i><span class="menu-text">Users</span><div class="active-indicator"></div>
            </a>
            <a href="organizations.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'organizations.php' ? 'active' : ''; ?>" data-tooltip="Organizations">
                <i class="fas fa-building menu-icon"></i><span class="menu-text">Organizations</span><div class="active-indicator"></div>
            </a>
            <a href="pending.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'pending.php' ? 'active' : ''; ?>" data-tooltip="Pending Approvals">
                <i class="fas fa-clock menu-icon"></i><span class="menu-text">Pending</span>
                <?php
                $pending_count = 0;
                if (isset($conn)) {
                    $count = $conn->query("SELECT COUNT(*) as c FROM pending_officers WHERE status = 'pending'");
                    if ($count) $pending_count = $count->fetch_assoc()['c'];
                }
                if($pending_count > 0): ?><span class="badge bg-danger ms-auto"><?php echo $pending_count; ?></span><?php endif; ?>
                <div class="active-indicator"></div>
            </a>
            <a href="events.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>" data-tooltip="Events">
                <i class="fas fa-calendar-alt menu-icon"></i><span class="menu-text">Events</span><div class="active-indicator"></div>
            </a>
            <a href="reports.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" data-tooltip="Reports">
                <i class="fas fa-chart-bar menu-icon"></i><span class="menu-text">Reports</span><div class="active-indicator"></div>
            </a>
        </div>
        <a href="../profile.php" class="sidebar-footer-link">
            <div class="sidebar-footer">
                <div class="user-info-mini">
                    <div class="user-avatar-mini"><?php echo getUserInitials($user); ?></div>
                    <div class="user-details-mini"><span class="user-name-mini"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></span><span class="user-role-mini">Administrator</span></div>
                    <i class="fas fa-chevron-right profile-arrow"></i>
                </div>
            </div>
        </a>
        <div class="sidebar-logout">
            <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i><span class="menu-text">Logout</span></a>
        </div>
    </div>