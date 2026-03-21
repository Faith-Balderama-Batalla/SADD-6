<?php
// Officer/attendance.php - QR Code Scanner for Attendance
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'org_officer') { header('Location: ../Student/dashboard.php'); exit(); }

$org = $conn->query("SELECT org_id FROM organizations o JOIN organization_officers oo ON o.org_id = oo.org_id WHERE oo.user_id = {$user['user_id']}")->fetch_assoc();
if (!$org) { die("Organization not found"); }

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$event = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND org_id = ?");
$event->bind_param("ii", $event_id, $org['org_id']);
$event->execute();
$event = $event->get_result()->fetch_assoc();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    $qr_data = $_POST['qr_data'];
    $qr = $conn->prepare("SELECT * FROM qr_codes WHERE qr_string = ? AND is_active = TRUE AND NOW() BETWEEN valid_from AND valid_until");
    $qr->bind_param("s", $qr_data);
    $qr->execute();
    $qr_result = $qr->get_result()->fetch_assoc();
    
    if ($qr_result && $qr_result['event_id'] == $event_id) {
        // Find student by scanning student ID QR
        $student = $conn->prepare("SELECT * FROM users WHERE id_number = ? AND status = 'active'");
        $student->bind_param("s", $qr_data);
        $student->execute();
        $student_data = $student->get_result()->fetch_assoc();
        
        if ($student_data) {
            // Check if already marked
            $check = $conn->prepare("SELECT * FROM attendance_logs WHERE event_id = ? AND student_id = ?");
            $check->bind_param("ii", $event_id, $student_data['user_id']);
            $check->execute();
            
            if ($check->get_result()->num_rows == 0) {
                $insert = $conn->prepare("INSERT INTO attendance_logs (event_id, student_id, officer_id, status, marked_via) VALUES (?, ?, ?, 'present', 'qr')");
                $insert->bind_param("iii", $event_id, $student_data['user_id'], $user['user_id']);
                if ($insert->execute()) {
                    $message = "Attendance marked for " . $student_data['first_name'] . " " . $student_data['last_name'];
                    $message_type = "success";
                } else {
                    $message = "Failed to mark attendance.";
                    $message_type = "danger";
                }
            } else {
                $message = "Student already marked present.";
                $message_type = "warning";
            }
        } else {
            $message = "Student not found.";
            $message_type = "danger";
        }
    } else {
        $message = "Invalid or expired QR code.";
        $message_type = "danger";
    }
}

$class_list = $conn->query("SELECT u.user_id, u.id_number, u.first_name, u.last_name, u.course, u.year_level, u.block FROM users u JOIN class_members cm ON u.user_id = cm.user_id JOIN classes c ON cm.class_id = c.class_id WHERE c.class_officer_id = {$user['user_id']}");
$attendance_list = $conn->prepare("SELECT * FROM attendance_logs WHERE event_id = ?");
$attendance_list->bind_param("i", $event_id);
$attendance_list->execute();
$attendance_data = $attendance_list->get_result();
$attended_ids = [];
while($a = $attendance_data->fetch_assoc()) { $attended_ids[] = $a['student_id']; }

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
    <title>QR Attendance - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_officer.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">QR Attendance Scanner</h2><p class="text-muted">Scan student QR codes to mark attendance</p></div><a href="events.php" class="btn btn-outline-primary">Back to Events</a></div>
            <?php if($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <div class="row g-4">
                <div class="col-md-5"><div class="card border-0 shadow-sm"><div class="card-body"><h5>Event: <?php echo $event['event_title']; ?></h5><p class="text-muted"><?php echo $event['venue']; ?> | <?php echo date('M d, Y g:i A', strtotime($event['start_datetime'])); ?></p><hr><div class="text-center"><div id="reader" style="width:100%;max-width:400px;margin:0 auto;"></div><p class="text-muted mt-3">Position the student's QR code in front of the camera</p></div></div></div></div>
                <div class="col-md-7"><div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0">Class List - Mark Attendance Manually</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>ID Number</th><th>Name</th><th>Status</th><th>Action</th></tr></thead><tbody><?php while($s = $class_list->fetch_assoc()): ?><tr><td><?php echo $s['id_number']; ?></td><td><?php echo $s['last_name'].', '.$s['first_name']; ?></td><td><?php if(in_array($s['user_id'], $attended_ids)): ?><span class="badge bg-success">Present</span><?php else: ?><span class="badge bg-secondary">Not Marked</span><?php endif; ?></td><td><?php if(!in_array($s['user_id'], $attended_ids)): ?><a href="mark-attendance.php?event_id=<?php echo $event_id; ?>&student_id=<?php echo $s['user_id']; ?>" class="btn btn-sm btn-primary">Mark Present</a><?php endif; ?></td></tr><?php endwhile; ?></tbody></table></div></div></div></div>
            </div>
        </div>
    </div>
</div>
<script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>
<script>
const html5QrCode = new Html5Qrcode("reader");
html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } }, (decodedText) => { html5QrCode.stop(); document.getElementById("qrResult").value = decodedText; document.getElementById("qrForm").submit(); }, (error) => {});
</script>
<form id="qrForm" method="POST" style="display:none;"><input type="hidden" name="qr_data" id="qrResult"><input type="hidden" name="event_id" value="<?php echo $event_id; ?>"></form>
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