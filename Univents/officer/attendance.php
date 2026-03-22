<?php
// officer/attendance.php - QR Code Scanner & Manual Attendance with Toggle
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'org_officer') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Get officer's organization
$org_stmt = $conn->prepare("
    SELECT o.org_id, o.org_name 
    FROM organizations o 
    JOIN organization_officers oo ON o.org_id = oo.org_id 
    WHERE oo.user_id = ? AND oo.status = 'active'
    LIMIT 1
");
$org_stmt->bind_param("i", $user['user_id']);
$org_stmt->execute();
$org = $org_stmt->get_result()->fetch_assoc();

if (!$org) {
    die("You are not assigned to any organization.");
}

// Get event if specified
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$event = null;

if ($event_id) {
    $event_stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND org_id = ?");
    $event_stmt->bind_param("ii", $event_id, $org['org_id']);
    $event_stmt->execute();
    $event = $event_stmt->get_result()->fetch_assoc();
}

// Get all events for dropdown
$events_list = $conn->prepare("
    SELECT event_id, event_title, start_datetime, status 
    FROM events 
    WHERE org_id = ? 
    ORDER BY start_datetime DESC
");
$events_list->bind_param("i", $org['org_id']);
$events_list->execute();
$all_events = $events_list->get_result();

// Handle QR scan (Phase 2 demo)
$scan_message = '';
$scan_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    $qr_data = $_POST['qr_data'];
    $scan_message = "QR Scanner Demo: Scanned data: " . htmlspecialchars($qr_data) . "<br>Full QR attendance functionality coming in Phase 2!";
    $scan_type = "info";
}

// Handle manual attendance marking
if (isset($_GET['mark_attendance']) && isset($_GET['student_id']) && $event_id) {
    $student_id = intval($_GET['student_id']);
    $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'present';
    
    // Check if already marked
    $check = $conn->prepare("SELECT * FROM attendance_logs WHERE event_id = ? AND student_id = ?");
    $check->bind_param("ii", $event_id, $student_id);
    $check->execute();
    
    if ($check->get_result()->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO attendance_logs (event_id, student_id, officer_id, status, verified_at) VALUES (?, ?, ?, ?, NOW())");
        $insert->bind_param("iiis", $event_id, $student_id, $user['user_id'], $status);
        
        if ($insert->execute()) {
            $student_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
            $student_stmt->bind_param("i", $student_id);
            $student_stmt->execute();
            $student = $student_stmt->get_result()->fetch_assoc();
            
            $success = "Attendance marked for " . $student['first_name'] . " " . $student['last_name'];
            $scan_type = "success";
        } else {
            $error = "Failed to mark attendance.";
        }
        $insert->close();
    } else {
        $error = "Student already marked present.";
    }
    $check->close();
}

// Get list of organization members
$members = $conn->prepare("
    SELECT u.user_id, u.id_number, u.first_name, u.last_name, u.course, u.year_level, u.block
    FROM users u
    JOIN organization_memberships om ON u.user_id = om.user_id
    WHERE om.org_id = ? AND om.status = 'active'
    ORDER BY u.last_name ASC
");
$members->bind_param("i", $org['org_id']);
$members->execute();
$members_result = $members->get_result();

// Get already marked attendance for this event
$marked_students = [];
if ($event_id) {
    $marked = $conn->prepare("SELECT student_id FROM attendance_logs WHERE event_id = ?");
    $marked->bind_param("i", $event_id);
    $marked->execute();
    $marked_result = $marked->get_result();
    while($m = $marked_result->fetch_assoc()) {
        $marked_students[] = $m['student_id'];
    }
    $marked->close();
}

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Attendance Management - Univents Officer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_officer.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="mb-4">
                <h2 class="mb-1">
                    <i class="fas fa-qrcode me-2" style="color: var(--primary-mint);"></i>
                    Attendance Management
                </h2>
                <p class="text-muted">Mark attendance for events</p>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if($scan_message): ?>
                <div class="alert alert-<?php echo $scan_type; ?> alert-dismissible fade show">
                    <i class="fas fa-<?php echo $scan_type == 'success' ? 'check-circle' : 'info-circle'; ?> me-2"></i><?php echo $scan_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Event Selector -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Select Event</label>
                            <select name="event_id" class="form-control" required>
                                <option value="">-- Select an event --</option>
                                <?php while($e = $all_events->fetch_assoc()): ?>
                                    <option value="<?php echo $e['event_id']; ?>" <?php echo $event_id == $e['event_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($e['event_title']); ?> 
                                        (<?php echo date('M d, Y', strtotime($e['start_datetime'])); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-calendar-alt me-2"></i>Load Event
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if($event): ?>
                <!-- Event Details -->
                <div class="alert alert-info mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($event['event_title']); ?></h5>
                            <p class="mb-0 small">
                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($event['venue']); ?>
                                <i class="fas fa-clock ms-3 me-1"></i><?php echo date('F j, Y g:i A', strtotime($event['start_datetime'])); ?>
                            </p>
                        </div>
                        <div>
                            <span class="badge bg-<?php 
                                echo $event['status']=='upcoming' ? 'success' : 
                                     ($event['status']=='ongoing' ? 'warning' : 
                                     ($event['status']=='completed' ? 'info' : 'danger')); 
                            ?>">
                                <?php echo strtoupper($event['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- QR Scanner Toggle Button -->
                <div class="text-center mb-3">
                    <button class="qr-scanner-toggle" id="qrToggleBtn">
                        <i class="fas fa-qrcode"></i>
                        Show QR Scanner (Phase 2 Preview)
                    </button>
                </div>
                
                <!-- QR Scanner Mock-up (Hidden by default) -->
                <div class="qr-mockup-card" id="qrMockup">
                    <div class="mockup-badge">
                        <i class="fas fa-microchip me-2"></i>Phase 2 - Coming Soon
                    </div>
                    <h5>QR Code Scanner</h5>
                    <p class="text-muted">Quickly mark attendance by scanning student QR codes</p>
                    <div class="mockup-camera">
                        <div class="camera-placeholder">
                            <i class="fas fa-qrcode"></i>
                            <p class="mb-0 mt-2">Camera Preview</p>
                            <small class="text-muted">QR Scanner will be available in Phase 2</small>
                            <div class="mt-3">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="text" name="qr_data" class="form-control d-inline-block w-auto" placeholder="Demo QR input" style="max-width: 200px;">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-play me-1"></i>Demo Scan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Manual Attendance List -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-transparent">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2" style="color: var(--primary-mint);"></i>
                                Organization Members
                            </h5>
                            <div>
                                <span class="badge bg-success"><?php echo count($marked_students); ?> Marked</span>
                                <span class="badge bg-secondary"><?php echo $members_result->num_rows; ?> Total Members</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID Number</th>
                                        <th>Name</th>
                                        <th>Course/Year/Block</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($members_result->num_rows > 0): ?>
                                        <?php while($student = $members_result->fetch_assoc()): ?>
                                            <tr class="student-item">
                                                <td><?php echo htmlspecialchars($student['id_number']); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($student['course']); ?> 
                                                    <?php echo $student['year_level']; ?>-<?php echo htmlspecialchars($student['block']); ?>
                                                </td>
                                                <td>
                                                    <?php if(in_array($student['user_id'], $marked_students)): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i>Present
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-clock me-1"></i>Not Marked
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if(!in_array($student['user_id'], $marked_students)): ?>
                                                        <div class="btn-group">
                                                            <a href="?event_id=<?php echo $event_id; ?>&mark_attendance=1&student_id=<?php echo $student['user_id']; ?>&status=present" 
                                                               class="btn btn-sm btn-success">
                                                                <i class="fas fa-check"></i> Present
                                                            </a>
                                                            <a href="?event_id=<?php echo $event_id; ?>&mark_attendance=1&student_id=<?php echo $student['user_id']; ?>&status=late" 
                                                               class="btn btn-sm btn-warning">
                                                                <i class="fas fa-clock"></i> Late
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled>
                                                            <i class="fas fa-check-double"></i> Marked
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No members found in this organization.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif($event_id == 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-alt fa-4x text-muted mb-3"></i>
                    <h4>Select an Event</h4>
                    <p class="text-muted">Choose an event from the dropdown above to start marking attendance.</p>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Event not found or you don't have permission to manage this event.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
// QR Scanner Toggle
const qrToggleBtn = document.getElementById('qrToggleBtn');
const qrMockup = document.getElementById('qrMockup');

if (qrToggleBtn && qrMockup) {
    qrToggleBtn.addEventListener('click', function() {
        qrMockup.classList.toggle('show');
        this.classList.toggle('active');
        
        if (this.classList.contains('active')) {
            this.innerHTML = '<i class="fas fa-qrcode"></i> Hide QR Scanner';
        } else {
            this.innerHTML = '<i class="fas fa-qrcode"></i> Show QR Scanner (Phase 2 Preview)';
        }
    });
}
</script>
</body>
</html>