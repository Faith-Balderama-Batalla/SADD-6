<?php
// Admin/dashboard.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { header('Location: ../Student/dashboard.php'); exit(); }

// Get statistics
$stats = [];
$users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc();
$orgs = $conn->query("SELECT COUNT(*) as total FROM organizations WHERE status = 'active'")->fetch_assoc();
$events = $conn->query("SELECT COUNT(*) as total FROM events WHERE status = 'upcoming'")->fetch_assoc();
$pending = $conn->query("SELECT COUNT(*) as total FROM pending_officers WHERE status = 'pending'")->fetch_assoc();

$recent_users = $conn->query("SELECT user_id, first_name, last_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$pending_apps = $conn->query("SELECT p.*, u.first_name, u.last_name FROM pending_officers p JOIN users u ON p.user_id = u.user_id WHERE p.status = 'pending' LIMIT 5");

$notif_sql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user['user_id']);
$notif_stmt->execute();
$unread_count = $notif_stmt->get_result()->fetch_assoc()['unread'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-badge { background: linear-gradient(135deg, #F56565, #C53030); color: white; padding: 8px 16px; border-radius: 20px; font-weight: 600; }
        .stat-card-admin { background: white; border-radius: 16px; padding: 20px; border: 1px solid rgba(79, 209, 197, 0.1); transition: all 0.3s ease; }
        .stat-card-admin:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(79, 209, 197, 0.1); }
        .admin-stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(245, 101, 101, 0.1); color: #F56565; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div><h2 class="mb-1">Welcome, <?php echo $user['first_name']; ?>! 👋</h2><p class="text-muted">System overview and statistics</p></div>
                <div class="admin-badge"><i class="fas fa-shield-alt me-2"></i>Administrator</div>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="stat-card-admin"><div class="d-flex align-items-center"><div class="admin-stat-icon me-3"><i class="fas fa-users"></i></div><div><h3 class="mb-0"><?php echo $users['total']; ?></h3><p class="text-muted mb-0">Total Users</p></div></div></div></div>
                <div class="col-md-3"><div class="stat-card-admin"><div class="d-flex align-items-center"><div class="admin-stat-icon me-3" style="color: #4FD1C5; background: rgba(79, 209, 197, 0.1);"><i class="fas fa-building"></i></div><div><h3 class="mb-0"><?php echo $orgs['total']; ?></h3><p class="text-muted mb-0">Organizations</p></div></div></div></div>
                <div class="col-md-3"><div class="stat-card-admin"><div class="d-flex align-items-center"><div class="admin-stat-icon me-3" style="color: #F6AD55; background: rgba(246, 173, 85, 0.1);"><i class="fas fa-calendar"></i></div><div><h3 class="mb-0"><?php echo $events['total']; ?></h3><p class="text-muted mb-0">Upcoming Events</p></div></div></div></div>
                <div class="col-md-3"><div class="stat-card-admin"><div class="d-flex align-items-center"><div class="admin-stat-icon me-3" style="color: #48BB78; background: rgba(72, 187, 120, 0.1);"><i class="fas fa-clock"></i></div><div><h3 class="mb-0"><?php echo $pending['total']; ?></h3><p class="text-muted mb-0">Pending Approvals</p></div></div></div></div>
            </div>
            <div class="row g-4">
                <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-header bg-transparent border-0 pt-4 px-4"><h5 class="mb-0">Recent Users</h5></div><div class="card-body p-0"><div class="list-group list-group-flush"><?php while($u = $recent_users->fetch_assoc()): ?><div class="list-group-item border-0 py-3"><div class="d-flex align-items-center"><div class="user-avatar-mini me-3"><?php echo strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)); ?></div><div><h6 class="mb-0"><?php echo $u['first_name'].' '.$u['last_name']; ?></h6><small class="text-muted"><?php echo $u['email']; ?></small></div><div class="ms-auto"><span class="badge bg-secondary"><?php echo str_replace('_',' ',$u['role']); ?></span></div></div></div><?php endwhile; ?></div></div><div class="card-footer bg-transparent border-0 text-end"><a href="users.php" class="btn btn-sm btn-link">View All Users <i class="fas fa-arrow-right ms-2"></i></a></div></div></div>
                <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-header bg-transparent border-0 pt-4 px-4"><h5 class="mb-0">Pending Applications</h5></div><div class="card-body p-0"><div class="list-group list-group-flush"><?php if($pending_apps->num_rows > 0): while($p = $pending_apps->fetch_assoc()): ?><div class="list-group-item border-0 py-3"><div class="d-flex align-items-center"><div class="user-avatar-mini me-3" style="background:#F56565;"><?php echo strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)); ?></div><div><h6 class="mb-0"><?php echo $p['first_name'].' '.$p['last_name']; ?></h6><small class="text-muted"><?php echo str_replace('_',' ',$p['requested_role']); ?> application</small></div><div class="ms-auto"><a href="pending.php" class="btn btn-sm btn-primary">Review</a></div></div></div><?php endwhile; else: ?><div class="text-center py-4"><i class="fas fa-check-circle fa-3x text-success mb-3"></i><p class="text-muted mb-0">No pending applications</p></div><?php endif; ?></div></div><div class="card-footer bg-transparent border-0 text-end"><a href="pending.php" class="btn btn-sm btn-link">Review All <i class="fas fa-arrow-right ms-2"></i></a></div></div></div>
            </div>
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