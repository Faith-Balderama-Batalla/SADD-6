<?php
// Officer/events.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'org_officer') { header('Location: ../Student/dashboard.php'); exit(); }

$org = $conn->query("SELECT org_id FROM organizations o JOIN organization_officers oo ON o.org_id = oo.org_id WHERE oo.user_id = {$user['user_id']} AND oo.status = 'active'")->fetch_assoc();
if (!$org) { header('Location: dashboard.php?error=no_org'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title = mysqli_real_escape_string($conn, $_POST['event_title']);
    $desc = mysqli_real_escape_string($conn, $_POST['event_description']);
    $type = mysqli_real_escape_string($conn, $_POST['event_type']);
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    $start = $_POST['start_datetime'];
    $end = $_POST['end_datetime'];
    $reg_required = isset($_POST['registration_required']) ? 1 : 0;
    $max_active = $reg_required ? intval($_POST['max_active_participants']) : null;
    $stmt = $conn->prepare("INSERT INTO events (org_id, event_title, event_description, event_type, venue, start_datetime, end_datetime, registration_required, max_active_participants, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssiii", $org['org_id'], $title, $desc, $type, $venue, $start, $end, $reg_required, $max_active, $user['user_id']);
    $stmt->execute();
    $success = "Event created successfully!";
}

$events = $conn->query("SELECT * FROM events WHERE org_id = {$org['org_id']} ORDER BY start_datetime DESC");
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
    <title>My Events - Univents Officer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_officer.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">My Events</h2><p class="text-muted">Manage your organization's events</p></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal"><i class="fas fa-plus-circle me-2"></i>Create Event</button></div>
            <?php if(isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <div class="row g-4"><?php if($events->num_rows > 0): while($e = $events->fetch_assoc()): ?><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex gap-3"><div class="event-date-badge"><div class="day"><?php echo date('d', strtotime($e['start_datetime'])); ?></div><div class="month"><?php echo date('M', strtotime($e['start_datetime'])); ?></div></div><div class="flex-grow-1"><h5 class="mb-1"><?php echo htmlspecialchars($e['event_title']); ?></h5><p class="text-muted small mb-1"><i class="fas fa-map-marker-alt me-1"></i><?php echo $e['venue']; ?></p><p class="text-muted small mb-3"><i class="far fa-clock me-1"></i><?php echo date('M d, Y g:i A', strtotime($e['start_datetime'])); ?></p><div class="d-flex gap-2"><a href="qr-view.php?event_id=<?php echo $e['event_id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-qrcode me-1"></i>QR Code</a><a href="attendance.php?event_id=<?php echo $e['event_id']; ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-check-circle me-1"></i>Mark Attendance</a></div></div></div></div></div></div><?php endwhile; else: ?><div class="col-12 text-center py-5"><i class="fas fa-calendar-times fa-4x text-muted mb-3"></i><h4>No Events Yet</h4><p>Create your first event to get started.</p></div><?php endif; ?></div>
        </div>
    </div>
</div>
<div class="modal fade" id="createEventModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Create Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label>Event Title</label><input type="text" name="event_title" class="form-control" required></div><div class="mb-3"><label>Description</label><textarea name="event_description" class="form-control" rows="3"></textarea></div><div class="row"><div class="col-md-6 mb-3"><label>Type</label><select name="event_type" class="form-control"><option value="workshop">Workshop</option><option value="seminar">Seminar</option><option value="meeting">Meeting</option><option value="social">Social</option><option value="fundraising">Fundraising</option></select></div><div class="col-md-6 mb-3"><label>Venue</label><input type="text" name="venue" class="form-control" required></div></div><div class="row"><div class="col-md-6 mb-3"><label>Start Date & Time</label><input type="datetime-local" name="start_datetime" class="form-control" required></div><div class="col-md-6 mb-3"><label>End Date & Time</label><input type="datetime-local" name="end_datetime" class="form-control" required></div></div><div class="row"><div class="col-md-6 mb-3"><div class="form-check mt-4"><input type="checkbox" name="registration_required" class="form-check-input" id="reg_required"><label class="form-check-label" for="reg_required">Require Registration</label></div></div><div class="col-md-6 mb-3"><input type="number" name="max_active_participants" class="form-control" placeholder="Max Active Participants"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="create_event" class="btn btn-primary">Create Event</button></div></form></div></div></div>
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