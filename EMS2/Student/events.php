<?php
// Student/events.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

// Get all events from organizations the student is a member of
$events = $conn->prepare("SELECT e.*, o.org_name, o.org_color FROM events e JOIN organizations o ON e.org_id = o.org_id JOIN organization_memberships om ON o.org_id = om.org_id WHERE om.user_id = ? AND om.status = 'active' ORDER BY e.start_datetime ASC");
$events->bind_param("i", $user['user_id']);
$events->execute();
$events_result = $events->get_result();

// Get user's registrations
$registrations = $conn->prepare("SELECT event_id, registration_type, attended FROM event_registrations WHERE user_id = ?");
$registrations->bind_param("i", $user['user_id']);
$registrations->execute();
$registered = [];
$reg_result = $registrations->get_result();
while($r = $reg_result->fetch_assoc()) { $registered[$r['event_id']] = $r; }

// Handle event registration
if (isset($_GET['register'])) {
    $event_id = intval($_GET['register']);
    $type = isset($_GET['type']) && $_GET['type'] == 'active' ? 'active' : 'spectator';
    
    // Check if already registered
    $check = $conn->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $check->bind_param("ii", $event_id, $user['user_id']);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO event_registrations (event_id, user_id, registration_type) VALUES (?, ?, ?)");
        $insert->bind_param("iis", $event_id, $user['user_id'], $type);
        if ($insert->execute()) {
            $message = "Successfully registered for the event!";
            $message_type = "success";
        }
    } else {
        $message = "You are already registered for this event.";
        $message_type = "warning";
    }
}

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
    <title>Events - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div><h2 class="mb-1">Upcoming Events</h2><p class="text-muted">Browse and register for events from your organizations</p></div>
            <?php if(isset($message)): ?><div class="alert alert-<?php echo $message_type; ?> mt-3"><?php echo $message; ?></div><?php endif; ?>
            <div class="row g-4 mt-2"><?php if($events_result->num_rows > 0): while($e = $events_result->fetch_assoc()): ?><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex gap-3"><div class="event-date-badge"><div class="day"><?php echo date('d', strtotime($e['start_datetime'])); ?></div><div class="month"><?php echo date('M', strtotime($e['start_datetime'])); ?></div></div><div class="flex-grow-1"><h5 class="mb-1"><?php echo htmlspecialchars($e['event_title']); ?></h5><p class="text-muted small mb-1"><i class="fas fa-building me-1"></i><?php echo $e['org_name']; ?></p><p class="text-muted small mb-1"><i class="fas fa-map-marker-alt me-1"></i><?php echo $e['venue']; ?></p><p class="text-muted small mb-3"><i class="far fa-clock me-1"></i><?php echo date('F j, Y g:i A', strtotime($e['start_datetime'])); ?></p><div class="d-flex gap-2"><?php if(isset($registered[$e['event_id']])): ?><span class="badge bg-success"><i class="fas fa-check me-1"></i>Registered as <?php echo $registered[$e['event_id']]['registration_type']; ?></span><?php else: ?><a href="?register=<?php echo $e['event_id']; ?>&type=spectator" class="btn btn-sm btn-outline-primary">Register as Spectator</a><?php if($e['max_active_participants'] > 0 && $e['current_active_participants'] < $e['max_active_participants']): ?><a href="?register=<?php echo $e['event_id']; ?>&type=active" class="btn btn-sm btn-primary">Join as Active Participant</a><?php endif; ?><?php endif; ?></div></div></div></div></div></div><?php endwhile; else: ?><div class="col-12 text-center py-5"><i class="fas fa-calendar-times fa-4x text-muted mb-3"></i><h4>No Upcoming Events</h4><p>Check back later for events from your organizations.</p></div><?php endif; ?></div>
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