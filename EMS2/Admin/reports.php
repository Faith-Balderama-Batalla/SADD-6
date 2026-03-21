<?php
// Admin/reports.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { header('Location: ../Student/dashboard.php'); exit(); }

$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

// Statistics
$users_by_role = $conn->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
$events_by_status = $conn->query("SELECT events.status, COUNT(*) as count FROM events WHERE DATE(events.created_at) BETWEEN '$date_from' AND '$date_to' GROUP BY events.status");
$attendance = $conn->query("SELECT COUNT(DISTINCT al.student_id) as total_students, COUNT(*) as total_attendances, SUM(CASE WHEN al.status = 'present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN al.status = 'late' THEN 1 ELSE 0 END) as late, SUM(CASE WHEN al.status = 'excused' THEN 1 ELSE 0 END) as excused FROM attendance_logs al JOIN events e ON al.event_id = e.event_id WHERE DATE(e.start_datetime) BETWEEN '$date_from' AND '$date_to'")->fetch_assoc();
$org_stats = $conn->query("SELECT COUNT(*) as total_orgs, SUM(CASE WHEN organizations.status = 'active' THEN 1 ELSE 0 END) as active_orgs FROM organizations")->fetch_assoc();
$top_orgs = $conn->query("SELECT o.org_name, o.org_color, COUNT(DISTINCT e.event_id) as event_count FROM organizations o LEFT JOIN events e ON o.org_id = e.org_id GROUP BY o.org_id ORDER BY event_count DESC LIMIT 5");
$recent = $conn->query("(SELECT 'user' as type, user_id as id, CONCAT(first_name,' ',last_name) as title, 'registered' as action, created_at as date FROM users WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to') UNION ALL (SELECT 'event' as type, event_id as id, event_title as title, 'created' as action, created_at as date FROM events WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to') ORDER BY date DESC LIMIT 20");

$notif_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE");
$notif_stmt->bind_param("i", $user['user_id']);
$notif_stmt->execute();
$unread_count = $notif_stmt->get_result()->fetch_assoc()['unread'] ?? 0;

$users_data = ['student_member'=>0, 'class_officer'=>0, 'org_officer'=>0, 'admin'=>0];
while($row = $users_by_role->fetch_assoc()) $users_data[$row['role']] = $row['count'];
$events_data = ['upcoming'=>0, 'ongoing'=>0, 'completed'=>0, 'cancelled'=>0];
while($row = $events_by_status->fetch_assoc()) $events_data[$row['status']] = $row['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Univents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Reports & Analytics</h2><p class="text-muted">System performance metrics</p></div><button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print Report</button></div>
            <div class="filter-bar mb-4"><form method="GET" class="row g-3"><div class="col-md-3"><input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>"></div><div class="col-md-3"><input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>"></div><div class="col-md-2"><button type="submit" class="btn btn-primary">Apply</button></div></form></div>
            <div class="row g-4 mb-4"><div class="col-md-3"><div class="stat-card-admin"><div class="d-flex"><div class="admin-stat-icon me-3"><i class="fas fa-users"></i></div><div><h3><?php echo array_sum($users_data); ?></h3><p>Total Users</p></div></div></div></div><div class="col-md-3"><div class="stat-card-admin"><div class="d-flex"><div class="admin-stat-icon me-3" style="color:#4FD1C5; background:rgba(79,209,197,0.1);"><i class="fas fa-calendar"></i></div><div><h3><?php echo array_sum($events_data); ?></h3><p>Total Events</p></div></div></div></div><div class="col-md-3"><div class="stat-card-admin"><div class="d-flex"><div class="admin-stat-icon me-3" style="color:#48BB78; background:rgba(72,187,120,0.1);"><i class="fas fa-check-circle"></i></div><div><h3><?php echo $attendance['total_attendances'] ?? 0; ?></h3><p>Total Attendance</p></div></div></div></div><div class="col-md-3"><div class="stat-card-admin"><div class="d-flex"><div class="admin-stat-icon me-3" style="color:#9F7AEA; background:rgba(159,122,234,0.1);"><i class="fas fa-building"></i></div><div><h3><?php echo $org_stats['active_orgs']; ?></h3><p>Active Orgs</p></div></div></div></div></div>
            <div class="row g-4 mb-4"><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body"><h5>User Distribution</h5><canvas id="userChart" height="200"></canvas></div></div></div><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body"><h5>Event Status</h5><canvas id="eventChart" height="200"></canvas></div></div></div></div>
            <div class="row g-4"><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0">Top Organizations</h5></div><div class="card-body"><table class="table"><?php while($o = $top_orgs->fetch_assoc()): ?><tr><td><div class="d-flex align-items-center"><div style="width:12px;height:12px;background:<?php echo $o['org_color']??'#4FD1C5'; ?>;border-radius:4px;margin-right:8px;"></div><?php echo $o['org_name']; ?></div></td><td class="text-end"><?php echo $o['event_count']; ?> events</td></tr><?php endwhile; ?></table></div></div></div>
                <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0">Recent Activity</h5></div><div class="card-body" style="max-height:300px;overflow-y:auto"><?php while($a = $recent->fetch_assoc()): ?><div class="d-flex align-items-center gap-3 mb-3"><div class="activity-icon <?php echo $a['type']; ?>"><i class="fas fa-<?php echo $a['type']=='user'?'user-plus':'calendar-plus'; ?>"></i></div><div><strong><?php echo $a['title']; ?></strong><br><small class="text-muted"><?php echo date('M d, g:i A', strtotime($a['date'])); ?></small></div></div><?php endwhile; ?></div></div></div></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
new Chart(document.getElementById('userChart'), { type: 'doughnut', data: { labels: ['Students', 'Class Officers', 'Org Officers', 'Admins'], datasets: [{ data: [<?php echo $users_data['student_member']; ?>, <?php echo $users_data['class_officer']; ?>, <?php echo $users_data['org_officer']; ?>, <?php echo $users_data['admin']; ?>], backgroundColor: ['#4FD1C5', '#F6AD55', '#9F7AEA', '#F56565'] }] }, options: { responsive: true, maintainAspectRatio: true } });
new Chart(document.getElementById('eventChart'), { type: 'bar', data: { labels: ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'], datasets: [{ label: 'Events', data: [<?php echo $events_data['upcoming']; ?>, <?php echo $events_data['ongoing']; ?>, <?php echo $events_data['completed']; ?>, <?php echo $events_data['cancelled']; ?>], backgroundColor: ['#4FD1C5', '#F6AD55', '#48BB78', '#F56565'] }] }, options: { responsive: true, maintainAspectRatio: true } });
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar'), collapseBtn = document.getElementById('collapseBtn'), menuToggle = document.getElementById('menuToggle'), profileBtn = document.getElementById('profileBtn'), profileMenu = document.getElementById('profileMenu');
    if(collapseBtn) collapseBtn.addEventListener('click', function(){ sidebar.classList.toggle('collapsed'); const icon = this.querySelector('i'); if(sidebar.classList.contains('collapsed')){ icon.classList.remove('fa-chevron-left'); icon.classList.add('fa-chevron-right'); } else { icon.classList.remove('fa-chevron-right'); icon.classList.add('fa-chevron-left'); } });
    if(menuToggle) menuToggle.addEventListener('click', function(){ sidebar.classList.toggle('mobile-show'); });
    if(profileBtn) profileBtn.addEventListener('click', function(e){ e.stopPropagation(); profileMenu.classList.toggle('show'); });
    document.addEventListener('click', function(){ if(profileMenu) profileMenu.classList.remove('show'); });
});
</script>
</body>
</html>