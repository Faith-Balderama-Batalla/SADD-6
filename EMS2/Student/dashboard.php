<?php
// Student/dashboard.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

$my_orgs = $conn->prepare("SELECT o.* FROM organizations o JOIN organization_memberships om ON o.org_id = om.org_id WHERE om.user_id = ? AND om.status = 'active'");
$my_orgs->bind_param("i", $user['user_id']);
$my_orgs->execute();
$my_organizations = $my_orgs->get_result();

$events = $conn->query("SELECT e.*, o.org_name FROM events e JOIN organizations o ON e.org_id = o.org_id WHERE e.status = 'upcoming' ORDER BY e.start_datetime ASC LIMIT 5");

$attendance = $conn->prepare("SELECT COUNT(*) as present FROM attendance_logs WHERE student_id = ? AND status = 'present'");
$attendance->bind_param("i", $user['user_id']);
$attendance->execute();
$present_count = $attendance->get_result()->fetch_assoc()['present'];

$notif_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE");
$notif_stmt->bind_param("i", $user['user_id']);
$notif_stmt->execute();
$unread_count = $notif_stmt->get_result()->fetch_assoc()['unread'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="welcome-section"><h2>Welcome back, <?php echo $user['first_name']; ?>! 👋</h2><p>Here's what's happening with your organizations</p></div>
            <div class="stats-grid"><div class="stat-card"><div class="stat-icon" style="background:rgba(79,209,197,0.1);"><i class="fas fa-calendar-check" style="color:var(--primary-mint);"></i></div><div class="stat-details"><h3><?php echo $my_organizations->num_rows; ?></h3><p>Organizations</p></div></div><div class="stat-card"><div class="stat-icon" style="background:rgba(72,187,120,0.1);"><i class="fas fa-check-circle" style="color:#48BB78;"></i></div><div class="stat-details"><h3><?php echo $present_count; ?></h3><p>Events Attended</p></div></div><div class="stat-card"><div class="stat-icon" style="background:rgba(79,209,197,0.1);"><i class="fas fa-bell" style="color:var(--primary-mint);"></i></div><div class="stat-details"><h3><?php echo $unread_count; ?></h3><p>Notifications</p></div></div><div class="stat-card"><div class="stat-icon" style="background:rgba(79,209,197,0.1);"><i class="fas fa-clock" style="color:var(--primary-mint);"></i></div><div class="stat-details"><h3><?php echo $events->num_rows; ?></h3><p>Upcoming Events</p></div></div></div>
            <div class="section-header"><h3>My Organizations</h3><a href="organizations.php" class="view-all-link">View All</a></div>
            <div class="organizations-grid"><?php while($org = $my_organizations->fetch_assoc()): ?><div class="org-card-dashboard"><div class="org-banner" style="background:<?php echo $org['org_color'] ?? '#E6FFFA'; ?>;"><div class="org-logo-wrapper"><div class="org-logo-placeholder"><i class="fas fa-users"></i></div></div></div><div class="org-card-content"><h4><?php echo $org['org_name']; ?></h4><p class="org-description"><?php echo substr($org['org_description'] ?? 'Student organization', 0, 60); ?>...</p><a href="organization.php?id=<?php echo $org['org_id']; ?>" class="btn-view-org">View <i class="fas fa-arrow-right"></i></a></div></div><?php endwhile; ?><div class="org-card-dashboard join-card"><div class="join-card-content"><div class="join-icon"><i class="fas fa-plus-circle"></i></div><h4>Join a New Organization</h4><p>Connect with more student organizations</p><a href="organizations.php" class="btn-join-org">Browse Organizations</a></div></div></div>
            <div class="section-header"><h3>Upcoming Events</h3><a href="events.php" class="view-all-link">View Calendar</a></div>
            <div class="events-preview-grid"><?php while($e = $events->fetch_assoc()): ?><div class="event-preview-card"><div class="event-date-badge"><div class="day"><?php echo date('d', strtotime($e['start_datetime'])); ?></div><div class="month"><?php echo date('M', strtotime($e['start_datetime'])); ?></div></div><div class="event-preview-details"><h5><?php echo $e['event_title']; ?></h5><p class="event-org"><?php echo $e['org_name']; ?></p><p class="event-time"><i class="far fa-clock"></i> <?php echo date('g:i A', strtotime($e['start_datetime'])); ?></p></div></div><?php endwhile; ?></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar'), collapseBtn = document.getElementById('collapseBtn'), menuToggle = document.getElementById('menuToggle'), profileBtn = document.getElementById('profileBtn'), profileMenu = document.getElementById('profileMenu'), notificationBtn = document.getElementById('notificationBtn'), notificationMenu = document.getElementById('notificationMenu');
    if(collapseBtn) collapseBtn.addEventListener('click', function(){ sidebar.classList.toggle('collapsed'); const icon = this.querySelector('i'); if(sidebar.classList.contains('collapsed')){ icon.classList.remove('fa-chevron-left'); icon.classList.add('fa-chevron-right'); } else { icon.classList.remove('fa-chevron-right'); icon.classList.add('fa-chevron-left'); } });
    if(menuToggle) menuToggle.addEventListener('click', function(){ sidebar.classList.toggle('mobile-show'); });
    if(profileBtn) profileBtn.addEventListener('click', function(e){ e.stopPropagation(); profileMenu.classList.toggle('show'); if(notificationMenu) notificationMenu.classList.remove('show'); });
    if(notificationBtn) notificationBtn.addEventListener('click', function(e){ e.stopPropagation(); notificationMenu.classList.toggle('show'); if(profileMenu) profileMenu.classList.remove('show'); });
    document.addEventListener('click', function(){ if(profileMenu) profileMenu.classList.remove('show'); if(notificationMenu) notificationMenu.classList.remove('show'); });
});
</script>
</body>
</html>