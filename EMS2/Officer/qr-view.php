<?php
// Officer/qr-view.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'org_officer') { header('Location: ../Student/dashboard.php'); exit(); }

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if (!$event_id) { header('Location: events.php'); exit(); }

$event = $conn->prepare("SELECT e.*, o.org_name FROM events e JOIN organizations o ON e.org_id = o.org_id WHERE e.event_id = ?");
$event->bind_param("i", $event_id);
$event->execute();
$event = $event->get_result()->fetch_assoc();

// Generate or retrieve QR code
$qr = $conn->prepare("SELECT * FROM qr_codes WHERE event_id = ? AND is_active = TRUE");
$qr->bind_param("i", $event_id);
$qr->execute();
$qr = $qr->get_result()->fetch_assoc();

if (!$qr) {
    $qr_string = hash('sha256', $event_id . time() . uniqid());
    $valid_from = date('Y-m-d H:i:s', strtotime($event['start_datetime']) - 7200);
    $valid_until = date('Y-m-d H:i:s', strtotime($event['end_datetime']) + 7200);
    $insert = $conn->prepare("INSERT INTO qr_codes (event_id, qr_string, valid_from, valid_until) VALUES (?, ?, ?, ?)");
    $insert->bind_param("isss", $event_id, $qr_string, $valid_from, $valid_until);
    $insert->execute();
    $qr_string = $qr_string;
} else {
    $qr_string = $qr['qr_string'];
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
    <title>Event QR Code - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs2-fix/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_officer.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Event QR Code</h2><p class="text-muted">Display this QR code for attendees to scan</p></div><a href="events.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-2"></i>Back to Events</a></div>
            <div class="row justify-content-center"><div class="col-md-6"><div class="card border-0 shadow-sm text-center"><div class="card-body"><div class="mb-4"><div id="qrcode" style="display:flex;justify-content:center;"></div></div><h4><?php echo htmlspecialchars($event['event_title']); ?></h4><p class="text-muted"><?php echo $event['org_name']; ?></p><p><i class="fas fa-map-marker-alt me-2"></i><?php echo $event['venue']; ?><br><i class="far fa-calendar me-2"></i><?php echo date('F j, Y g:i A', strtotime($event['start_datetime'])); ?></p><div class="alert alert-info mt-3"><i class="fas fa-info-circle me-2"></i>Valid: 2 hours before to 2 hours after event</div><button class="btn btn-primary mt-3" onclick="window.print()"><i class="fas fa-print me-2"></i>Print QR Code</button></div></div></div></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
new QRCode(document.getElementById("qrcode"), { text: "<?php echo $qr_string; ?>", width: 200, height: 200, colorDark: "#2C7A7B", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.H });
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