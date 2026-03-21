<?php
// Student/announcements.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

$announcements = $conn->prepare("SELECT a.*, o.org_name, o.org_color FROM announcements a JOIN organizations o ON a.org_id = o.org_id JOIN organization_memberships om ON o.org_id = om.org_id WHERE om.user_id = ? AND om.status = 'active' ORDER BY a.is_pinned DESC, a.created_at DESC");
$announcements->bind_param("i", $user['user_id']);
$announcements->execute();
$announcements_result = $announcements->get_result();

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
    <title>Announcements - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div><h2 class="mb-1">Announcements</h2><p class="text-muted">Latest updates from your organizations</p></div>
            <div class="row g-4 mt-2"><?php if($announcements_result->num_rows > 0): while($a = $announcements_result->fetch_assoc()): ?><div class="col-12"><div class="card border-0 shadow-sm <?php if($a['is_pinned']): ?>border-start border-4 border-primary<?php endif; ?>"><div class="card-body"><div class="d-flex justify-content-between"><div><h5 class="mb-1"><?php echo htmlspecialchars($a['title']); ?></h5><p class="text-muted small mb-2"><i class="fas fa-building me-1"></i><?php echo $a['org_name']; ?> • <?php echo date('F j, Y g:i A', strtotime($a['created_at'])); ?></p><p><?php echo nl2br(htmlspecialchars($a['content'])); ?></p></div><?php if($a['is_pinned']): ?><span class="badge bg-primary"><i class="fas fa-thumbtack me-1"></i>Pinned</span><?php endif; ?></div></div></div></div><?php endwhile; else: ?><div class="col-12 text-center py-5"><i class="fas fa-bullhorn fa-4x text-muted mb-3"></i><h4>No Announcements Yet</h4><p>Check back later for updates from your organizations.</p></div><?php endif; ?></div>
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