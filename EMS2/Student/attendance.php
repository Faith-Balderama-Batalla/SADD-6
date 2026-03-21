<?php
// Student/attendance.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

$attendance = $conn->prepare("SELECT al.*, e.event_title, e.start_datetime, o.org_name FROM attendance_logs al JOIN events e ON al.event_id = e.event_id JOIN organizations o ON e.org_id = o.org_id WHERE al.student_id = ? ORDER BY e.start_datetime DESC");
$attendance->bind_param("i", $user['user_id']);
$attendance->execute();
$attendance_result = $attendance->get_result();

$summary = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late, SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused FROM attendance_logs WHERE student_id = ?");
$summary->bind_param("i", $user['user_id']);
$summary->execute();
$stats = $summary->get_result()->fetch_assoc();

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
    <title>My Attendance - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div><h2 class="mb-1">My Attendance Record</h2><p class="text-muted">View your attendance history for events</p></div>
            <div class="stats-grid mb-4"><div class="stat-card"><div class="stat-icon" style="background:rgba(72,187,120,0.1);"><i class="fas fa-check-circle" style="color:#48BB78;"></i></div><div class="stat-details"><h3><?php echo $stats['present'] ?? 0; ?></h3><p>Present</p></div></div><div class="stat-card"><div class="stat-icon" style="background:rgba(246,173,85,0.1);"><i class="fas fa-clock" style="color:#F6AD55;"></i></div><div class="stat-details"><h3><?php echo $stats['late'] ?? 0; ?></h3><p>Late</p></div></div><div class="stat-card"><div class="stat-icon" style="background:rgba(159,122,234,0.1);"><i class="fas fa-file-excuse" style="color:#9F7AEA;"></i></div><div class="stat-details"><h3><?php echo $stats['excused'] ?? 0; ?></h3><p>Excused</p></div></div><div class="stat-card"><div class="stat-icon" style="background:rgba(245,101,101,0.1);"><i class="fas fa-times-circle" style="color:#F56565;"></i></div><div class="stat-details"><h3><?php echo ($stats['total'] ?? 0) - (($stats['present'] ?? 0) + ($stats['late'] ?? 0) + ($stats['excused'] ?? 0)); ?></h3><p>Absent</p></div></div></div>
            <div class="card border-0 shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Event</th><th>Organization</th><th>Date</th><th>Status</th><th>Verified At</th></tr></thead><tbody><?php if($attendance_result->num_rows > 0): while($a = $attendance_result->fetch_assoc()): ?><tr><td><?php echo $a['event_title']; ?></td><td><?php echo $a['org_name']; ?></td><td><?php echo date('M d, Y', strtotime($a['start_datetime'])); ?></td><td><span class="badge bg-<?php echo $a['status']=='present'?'success':($a['status']=='late'?'warning':'info'); ?>"><?php echo strtoupper($a['status']); ?></span></td><td><?php echo date('g:i A', strtotime($a['verified_at'])); ?></td></tr><?php endwhile; else: ?><tr><td colspan="5" class="text-center py-4">No attendance records found.</td></tr><?php endif; ?></tbody></table></div></div></div>
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