<?php
// Admin/events.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { header('Location: ../Student/dashboard.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_event'])) {
        $org_id = intval($_POST['org_id']);
        $title = mysqli_real_escape_string($conn, $_POST['event_title']);
        $desc = mysqli_real_escape_string($conn, $_POST['event_description']);
        $type = mysqli_real_escape_string($conn, $_POST['event_type']);
        $venue = mysqli_real_escape_string($conn, $_POST['venue']);
        $start = $_POST['start_datetime'];
        $end = $_POST['end_datetime'];
        $reg_required = isset($_POST['registration_required']) ? 1 : 0;
        $max_active = $reg_required ? intval($_POST['max_active_participants']) : null;
        $stmt = $conn->prepare("INSERT INTO events (org_id, event_title, event_description, event_type, venue, start_datetime, end_datetime, registration_required, max_active_participants, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssiii", $org_id, $title, $desc, $type, $venue, $start, $end, $reg_required, $max_active, $user['user_id']);
        $stmt->execute();
        $success = "Event created successfully!";
        $stmt->close();
    }
    if (isset($_POST['update_event'])) {
        $id = intval($_POST['event_id']);
        $org_id = intval($_POST['org_id']);
        $title = mysqli_real_escape_string($conn, $_POST['event_title']);
        $desc = mysqli_real_escape_string($conn, $_POST['event_description']);
        $type = mysqli_real_escape_string($conn, $_POST['event_type']);
        $venue = mysqli_real_escape_string($conn, $_POST['venue']);
        $start = $_POST['start_datetime'];
        $end = $_POST['end_datetime'];
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $stmt = $conn->prepare("UPDATE events SET org_id=?, event_title=?, event_description=?, event_type=?, venue=?, start_datetime=?, end_datetime=?, status=? WHERE event_id=?");
        $stmt->bind_param("isssssssi", $org_id, $title, $desc, $type, $venue, $start, $end, $status, $id);
        $stmt->execute();
        $success = "Event updated successfully!";
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $success = "Event deleted successfully!";
    $stmt->close();
}

$orgs = $conn->query("SELECT org_id, org_name FROM organizations WHERE status = 'active'");
$events = $conn->query("SELECT e.*, o.org_name, o.org_color FROM events e JOIN organizations o ON e.org_id = o.org_id ORDER BY e.start_datetime DESC");
$notif_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE");
$notif_stmt->bind_param("i", $user['user_id']);
$notif_stmt->execute();
$unread_count = $notif_stmt->get_result()->fetch_assoc()['unread'] ?? 0;
$notif_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Univents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Event Management</h2><p class="text-muted">Manage all events</p></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal"><i class="fas fa-plus-circle me-2"></i>Create Event</button></div>
            <?php if(isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <div class="row g-4"><?php if($events && $events->num_rows > 0): while($e = $events->fetch_assoc()): ?><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex gap-3"><div class="event-date-badge"><div class="day"><?php echo date('d', strtotime($e['start_datetime'])); ?></div><div class="month"><?php echo date('M', strtotime($e['start_datetime'])); ?></div></div><div class="flex-grow-1"><div class="d-flex justify-content-between"><h5 class="mb-1"><?php echo htmlspecialchars($e['event_title']); ?></h5><span class="badge bg-<?php echo $e['status']=='upcoming'?'success':($e['status']=='ongoing'?'warning':($e['status']=='completed'?'info':'danger')); ?>"><?php echo $e['status']; ?></span></div><p class="text-muted small mb-1"><i class="fas fa-building me-1"></i><?php echo $e['org_name']; ?></p><p class="text-muted small mb-1"><i class="fas fa-map-marker-alt me-1"></i><?php echo $e['venue']; ?></p><p class="text-muted small mb-3"><i class="far fa-clock me-1"></i><?php echo date('M d, Y g:i A', strtotime($e['start_datetime'])); ?></p><div><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editEventModal<?php echo $e['event_id']; ?>"><i class="fas fa-edit"></i> Edit</button><button class="btn btn-sm btn-outline-danger ms-2" onclick="confirmDelete(<?php echo $e['event_id']; ?>, '<?php echo addslashes($e['event_title']); ?>')"><i class="fas fa-trash"></i> Delete</button></div></div></div></div></div></div>
<div class="modal fade" id="editEventModal<?php echo $e['event_id']; ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Edit Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="event_id" value="<?php echo $e['event_id']; ?>"><div class="row"><div class="col-md-6 mb-3"><label>Organization</label><select name="org_id" class="form-control"><?php $orgs->data_seek(0); while($o = $orgs->fetch_assoc()): ?><option value="<?php echo $o['org_id']; ?>" <?php echo $e['org_id']==$o['org_id']?'selected':''; ?>><?php echo $o['org_name']; ?></option><?php endwhile; ?></select></div><div class="col-md-6 mb-3"><label>Title</label><input type="text" name="event_title" class="form-control" value="<?php echo htmlspecialchars($e['event_title']); ?>" required></div></div><div class="mb-3"><label>Description</label><textarea name="event_description" class="form-control" rows="3"><?php echo htmlspecialchars($e['event_description']); ?></textarea></div><div class="row"><div class="col-md-6 mb-3"><label>Type</label><select name="event_type" class="form-control"><option value="workshop" <?php echo $e['event_type']=='workshop'?'selected':''; ?>>Workshop</option><option value="seminar" <?php echo $e['event_type']=='seminar'?'selected':''; ?>>Seminar</option><option value="meeting" <?php echo $e['event_type']=='meeting'?'selected':''; ?>>Meeting</option><option value="social" <?php echo $e['event_type']=='social'?'selected':''; ?>>Social</option><option value="fundraising" <?php echo $e['event_type']=='fundraising'?'selected':''; ?>>Fundraising</option></select></div><div class="col-md-6 mb-3"><label>Venue</label><input type="text" name="venue" class="form-control" value="<?php echo $e['venue']; ?>" required></div></div><div class="row"><div class="col-md-6 mb-3"><label>Start</label><input type="datetime-local" name="start_datetime" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($e['start_datetime'])); ?>" required></div><div class="col-md-6 mb-3"><label>End</label><input type="datetime-local" name="end_datetime" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($e['end_datetime'])); ?>" required></div></div><div class="mb-3"><label>Status</label><select name="status" class="form-control"><option value="upcoming" <?php echo $e['status']=='upcoming'?'selected':''; ?>>Upcoming</option><option value="ongoing" <?php echo $e['status']=='ongoing'?'selected':''; ?>>Ongoing</option><option value="completed" <?php echo $e['status']=='completed'?'selected':''; ?>>Completed</option><option value="cancelled" <?php echo $e['status']=='cancelled'?'selected':''; ?>>Cancelled</option></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="update_event" class="btn btn-primary">Save</button></div></form></div></div></div>
<?php endwhile; endif; ?></div>
        </div>
    </div>
</div>
<div class="modal fade" id="addEventModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Create Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row"><div class="col-md-6 mb-3"><label>Organization</label><select name="org_id" class="form-control" required><option value="">Select Organization</option><?php $orgs->data_seek(0); while($o = $orgs->fetch_assoc()): ?><option value="<?php echo $o['org_id']; ?>"><?php echo $o['org_name']; ?></option><?php endwhile; ?></select></div><div class="col-md-6 mb-3"><label>Event Title</label><input type="text" name="event_title" class="form-control" required></div></div><div class="mb-3"><label>Description</label><textarea name="event_description" class="form-control" rows="3"></textarea></div><div class="row"><div class="col-md-6 mb-3"><label>Type</label><select name="event_type" class="form-control"><option value="workshop">Workshop</option><option value="seminar">Seminar</option><option value="meeting">Meeting</option><option value="social">Social</option><option value="fundraising">Fundraising</option></select></div><div class="col-md-6 mb-3"><label>Venue</label><input type="text" name="venue" class="form-control" required></div></div><div class="row"><div class="col-md-6 mb-3"><label>Start Date & Time</label><input type="datetime-local" name="start_datetime" class="form-control" required></div><div class="col-md-6 mb-3"><label>End Date & Time</label><input type="datetime-local" name="end_datetime" class="form-control" required></div></div><div class="row"><div class="col-md-6 mb-3"><div class="form-check mt-4"><input type="checkbox" name="registration_required" class="form-check-input" id="reg_required"><label class="form-check-label" for="reg_required">Require Registration</label></div></div><div class="col-md-6 mb-3"><input type="number" name="max_active_participants" class="form-control" placeholder="Max Active Participants (optional)"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_event" class="btn btn-primary">Create Event</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id, name) { if(confirm(`Delete "${name}"? This cannot be undone.`)) window.location.href = `events.php?delete=${id}`; }
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