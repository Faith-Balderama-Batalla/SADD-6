<?php
// attendance.php - Attendance Page
require_once 'config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login-signup.php');
    exit();
}

$user = getCurrentUser($conn);
$user_id = $user['user_id'];
$user_role = $user['role'];
$success = '';
$error = '';

// Get organizations for sidebar dropdown
$orgs_sql = "SELECT o.* FROM organizations o
             JOIN organization_memberships om ON o.org_id = om.org_id
             WHERE om.user_id = ? AND om.status = 'active'
             ORDER BY o.org_name ASC";
$orgs_stmt = $conn->prepare($orgs_sql);
$orgs_stmt->bind_param("i", $user_id);
$orgs_stmt->execute();
$my_organizations = $orgs_stmt->get_result();

// Get unread notifications count
$notif_sql = "SELECT COUNT(*) as unread FROM notifications 
              WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$unread_count = $notif_result->fetch_assoc()['unread'] ?? 0;

// Check if user is a class officer
$is_class_officer = false;
$class_id = null;

if ($user_role === 'class_officer') {
    $officer_sql = "SELECT class_id FROM classes WHERE class_officer_id = ?";
    $officer_stmt = $conn->prepare($officer_sql);
    $officer_stmt->bind_param("i", $user_id);
    $officer_stmt->execute();
    $officer_result = $officer_stmt->get_result();
    
    if ($officer_result->num_rows > 0) {
        $is_class_officer = true;
        $class_data = $officer_result->fetch_assoc();
        $class_id = $class_data['class_id'];
    }
    $officer_stmt->close();
}

// Get class information if applicable
$class_info = null;
if ($class_id) {
    $class_sql = "SELECT c.*, 
                  CONCAT(u.first_name, ' ', u.last_name) as officer_name
                  FROM classes c
                  LEFT JOIN users u ON c.class_officer_id = u.user_id
                  WHERE c.class_id = ?";
    $class_stmt = $conn->prepare($class_sql);
    $class_stmt->bind_param("i", $class_id);
    $class_stmt->execute();
    $class_info = $class_stmt->get_result()->fetch_assoc();
    $class_stmt->close();
}

// Get all events for filtering
$events_sql = "SELECT e.*, o.org_name 
               FROM events e
               JOIN organizations o ON e.org_id = o.org_id
               WHERE e.status IN ('upcoming', 'ongoing', 'completed')
               ORDER BY e.start_datetime DESC";
$events_result = $conn->query($events_sql);

// Handle event selection
$selected_event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;

// Get attendance for selected event
$attendance_data = [];
$total_present = 0;
$total_absent = 0;
$total_late = 0;
$total_excused = 0;
$class_members = [];

if ($selected_event_id) {
    // Get event details
    $event_detail_sql = "SELECT e.*, o.org_name 
                         FROM events e
                         JOIN organizations o ON e.org_id = o.org_id
                         WHERE e.event_id = ?";
    $event_detail_stmt = $conn->prepare($event_detail_sql);
    $event_detail_stmt->bind_param("i", $selected_event_id);
    $event_detail_stmt->execute();
    $selected_event = $event_detail_stmt->get_result()->fetch_assoc();
    $event_detail_stmt->close();
    
    // Get class members if class officer
    if ($is_class_officer && $class_id) {
        $members_sql = "SELECT u.user_id, u.id_number, u.first_name, u.last_name, 
                               u.course, u.year_level, u.block,
                               cm.status as enrollment_status
                        FROM class_members cm
                        JOIN users u ON cm.user_id = u.user_id
                        WHERE cm.class_id = ? AND cm.status = 'enrolled'
                        ORDER BY u.last_name ASC";
        $members_stmt = $conn->prepare($members_sql);
        $members_stmt->bind_param("i", $class_id);
        $members_stmt->execute();
        $class_members = $members_stmt->get_result();
        
        // Get attendance records for these members
        $attendance_sql = "SELECT al.*, u.first_name, u.last_name, u.id_number
                          FROM attendance_logs al
                          JOIN users u ON al.student_id = u.user_id
                          WHERE al.event_id = ?";
        $attendance_stmt = $conn->prepare($attendance_sql);
        $attendance_stmt->bind_param("i", $selected_event_id);
        $attendance_stmt->execute();
        $attendance_records = $attendance_stmt->get_result();
        
        // Organize attendance by student_id
        while($record = $attendance_records->fetch_assoc()) {
            $attendance_data[$record['student_id']] = $record;
            
            // Count totals
            switch($record['status']) {
                case 'present':
                    $total_present++;
                    break;
                case 'late':
                    $total_late++;
                    break;
                case 'excused':
                    $total_excused++;
                    break;
            }
        }
        $attendance_stmt->close();
    } else {
        // For regular students, just get their own attendance
        $my_attendance_sql = "SELECT al.*, e.event_title, e.start_datetime
                             FROM attendance_logs al
                             JOIN events e ON al.event_id = e.event_id
                             WHERE al.student_id = ? AND al.event_id = ?";
        $my_attendance_stmt = $conn->prepare($my_attendance_sql);
        $my_attendance_stmt->bind_param("ii", $user_id, $selected_event_id);
        $my_attendance_stmt->execute();
        $my_attendance = $my_attendance_stmt->get_result()->fetch_assoc();
        $my_attendance_stmt->close();
    }
}

// Handle marking attendance (for class officers)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance']) && $is_class_officer) {
    $event_id = intval($_POST['event_id']);
    $student_id = intval($_POST['student_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $remarks = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));
    
    // Check if attendance already exists
    $check_sql = "SELECT attendance_id FROM attendance_logs 
                  WHERE event_id = ? AND student_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $event_id, $student_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;
    $check_stmt->close();
    
    if ($exists) {
        // Update existing
        $update_sql = "UPDATE attendance_logs 
                       SET status = ?, remarks = ?, verified_at = NOW()
                       WHERE event_id = ? AND student_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssii", $status, $remarks, $event_id, $student_id);
        
        if ($update_stmt->execute()) {
            $success = "Attendance updated successfully!";
        } else {
            $error = "Failed to update attendance.";
        }
        $update_stmt->close();
    } else {
        // Insert new
        $insert_sql = "INSERT INTO attendance_logs 
                       (event_id, student_id, class_officer_id, status, remarks, verification_date, verification_time)
                       VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiiss", $event_id, $student_id, $user_id, $status, $remarks);
        
        if ($insert_stmt->execute()) {
            $success = "Attendance marked successfully!";
        } else {
            $error = "Failed to mark attendance.";
        }
        $insert_stmt->close();
    }
    
    // Redirect to refresh
    header("Location: attendance.php?event_id=$event_id&success=1");
    exit();
}

// Get attendance summary for student
$student_summary_sql = "SELECT 
                        COUNT(CASE WHEN al.status = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN al.status = 'late' THEN 1 END) as late_count,
                        COUNT(CASE WHEN al.status = 'excused' THEN 1 END) as excused_count,
                        COUNT(CASE WHEN al.status = 'absent' OR al.status IS NULL THEN 1 END) as absent_count
                        FROM events e
                        LEFT JOIN attendance_logs al ON e.event_id = al.event_id AND al.student_id = ?
                        WHERE e.status = 'completed'";
$student_summary_stmt = $conn->prepare($student_summary_sql);
$student_summary_stmt->bind_param("i", $user_id);
$student_summary_stmt->execute();
$student_summary = $student_summary_stmt->get_result()->fetch_assoc();
$student_summary_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Univents</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
    
    <?php include 'nav_sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Attendance</h2>
                    <p class="text-muted">
                        <?php if($is_class_officer): ?>
                            Manage and track attendance for your class
                        <?php else: ?>
                            View your attendance records
                        <?php endif; ?>
                    </p>
                </div>
                <?php if($is_class_officer && $class_info): ?>
                    <div class="badge bg-primary p-3">
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        Class Officer: <?php echo $class_info['course'] . ' ' . $class_info['year_level'] . $class_info['block']; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>Attendance updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Student Summary Cards (for all users) -->
            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(72, 187, 120, 0.1);">
                        <i class="fas fa-check-circle" style="color: #48BB78;"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $student_summary['present_count'] ?? 0; ?></h3>
                        <p>Present</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(246, 173, 85, 0.1);">
                        <i class="fas fa-clock" style="color: #F6AD55;"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $student_summary['late_count'] ?? 0; ?></h3>
                        <p>Late</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(159, 122, 234, 0.1);">
                        <i class="fas fa-file-excuse" style="color: #9F7AEA;"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $student_summary['excused_count'] ?? 0; ?></h3>
                        <p>Excused</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 101, 101, 0.1);">
                        <i class="fas fa-times-circle" style="color: #F56565;"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $student_summary['absent_count'] ?? 0; ?></h3>
                        <p>Absent</p>
                    </div>
                </div>
            </div>
            
            <!-- Event Selection -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Select Event</label>
                            <select name="event_id" class="form-control" required>
                                <option value="">-- Choose an event --</option>
                                <?php 
                                if($events_result && $events_result->num_rows > 0):
                                    while($event = $events_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $event['event_id']; ?>" 
                                        <?php echo $selected_event_id == $event['event_id'] ? 'selected' : ''; ?>>
                                        <?php echo $event['event_title']; ?> 
                                        (<?php echo date('M d, Y', strtotime($event['start_datetime'])); ?>)
                                        - <?php echo $event['org_name']; ?>
                                    </option>
                                <?php 
                                    endwhile;
                                endif; 
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>View Attendance
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if($selected_event_id && isset($selected_event)): ?>
                <!-- Event Info -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-8">
                                <h4 class="mb-2"><?php echo $selected_event['event_title']; ?></h4>
                                <p class="text-muted mb-1">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('F j, Y', strtotime($selected_event['start_datetime'])); ?>
                                    | <?php echo date('g:i A', strtotime($selected_event['start_datetime'])); ?> - 
                                    <?php echo date('g:i A', strtotime($selected_event['end_datetime'])); ?>
                                </p>
                                <p class="text-muted mb-1">
                                    <i class="fas fa-map-marker-alt me-2"></i><?php echo $selected_event['venue']; ?>
                                </p>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-building me-2"></i><?php echo $selected_event['org_name']; ?>
                                </p>
                            </div>
                            <?php if($is_class_officer): ?>
                                <div class="col-md-4 text-md-end">
                                    <div class="attendance-summary-stats">
                                        <span class="badge bg-success me-2">Present: <?php echo $total_present; ?></span>
                                        <span class="badge bg-warning me-2">Late: <?php echo $total_late; ?></span>
                                        <span class="badge bg-info me-2">Excused: <?php echo $total_excused; ?></span>
                                        <span class="badge bg-danger">Absent: <?php 
                                            $total_members = $class_members ? $class_members->num_rows : 0;
                                            $total_absent = $total_members - ($total_present + $total_late + $total_excused);
                                            echo $total_absent;
                                        ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if($is_class_officer && $class_members): ?>
                    <!-- Class Officer View: Full Class List -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2 text-primary"></i>
                                Class Masterlist - <?php echo $class_info['course'] . ' ' . $class_info['year_level'] . $class_info['block']; ?>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4">#</th>
                                            <th>ID Number</th>
                                            <th>Student Name</th>
                                            <th>Course/Year/Block</th>
                                            <th>Status</th>
                                            <th>Time Verified</th>
                                            <th>Remarks</th>
                                            <th class="text-end pe-4">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $counter = 1;
                                        while($member = $class_members->fetch_assoc()): 
                                            $attendance = $attendance_data[$member['user_id']] ?? null;
                                            $status = $attendance['status'] ?? 'absent';
                                            $status_class = [
                                                'present' => 'success',
                                                'late' => 'warning',
                                                'excused' => 'info',
                                                'absent' => 'danger'
                                            ];
                                        ?>
                                        <tr>
                                            <td class="ps-4"><?php echo $counter++; ?></td>
                                            <td><code><?php echo $member['id_number']; ?></code></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar-mini me-2" style="width: 30px; height: 30px;">
                                                        <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo $member['last_name'] . ', ' . $member['first_name']; ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo $member['course'] . ' ' . $member['year_level'] . $member['block']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $status_class[$status]; ?>">
                                                    <?php echo strtoupper($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($attendance): ?>
                                                    <small><?php echo date('g:i A', strtotime($attendance['verified_at'])); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">--:--</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($attendance && $attendance['remarks']): ?>
                                                    <span data-bs-toggle="tooltip" title="<?php echo $attendance['remarks']; ?>">
                                                        <i class="fas fa-comment-dots text-muted"></i>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#attendanceModal<?php echo $member['user_id']; ?>">
                                                    <i class="fas fa-pen"></i> Mark
                                                </button>
                                            </td>
                                        </tr>
                                        
                                        <!-- Attendance Modal for each student -->
                                        <div class="modal fade" id="attendanceModal<?php echo $member['user_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-check-circle me-2 text-primary"></i>
                                                            Mark Attendance
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                                                            <input type="hidden" name="student_id" value="<?php echo $member['user_id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Student</label>
                                                                <p class="form-control-static">
                                                                    <?php echo $member['last_name'] . ', ' . $member['first_name']; ?><br>
                                                                    <small class="text-muted"><?php echo $member['id_number']; ?></small>
                                                                </p>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Attendance Status</label>
                                                                <select name="status" class="form-control" required>
                                                                    <option value="present" <?php echo ($attendance['status'] ?? '') == 'present' ? 'selected' : ''; ?>>Present</option>
                                                                    <option value="late" <?php echo ($attendance['status'] ?? '') == 'late' ? 'selected' : ''; ?>>Late</option>
                                                                    <option value="excused" <?php echo ($attendance['status'] ?? '') == 'excused' ? 'selected' : ''; ?>>Excused</option>
                                                                    <option value="absent" <?php echo ($attendance['status'] ?? '') == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Remarks (Optional)</label>
                                                                <textarea name="remarks" class="form-control" rows="2"><?php echo $attendance['remarks'] ?? ''; ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="mark_attendance" class="btn btn-primary">
                                                                <i class="fas fa-save me-2"></i>Save Attendance
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif($selected_event_id): ?>
                    <!-- Regular Student View: Their own attendance -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4 text-center">
                            <?php if(isset($my_attendance) && $my_attendance): ?>
                                <div class="mb-4">
                                    <div class="attendance-status-icon mb-3">
                                        <?php
                                        $status_icons = [
                                            'present' => ['icon' => 'fa-check-circle', 'color' => '#48BB78', 'bg' => 'rgba(72, 187, 120, 0.1)'],
                                            'late' => ['icon' => 'fa-clock', 'color' => '#F6AD55', 'bg' => 'rgba(246, 173, 85, 0.1)'],
                                            'excused' => ['icon' => 'fa-file-excuse', 'color' => '#9F7AEA', 'bg' => 'rgba(159, 122, 234, 0.1)'],
                                            'absent' => ['icon' => 'fa-times-circle', 'color' => '#F56565', 'bg' => 'rgba(245, 101, 101, 0.1)']
                                        ];
                                        $status = $my_attendance['status'];
                                        $icon_data = $status_icons[$status] ?? $status_icons['absent'];
                                        ?>
                                        <div style="width: 80px; height: 80px; background: <?php echo $icon_data['bg']; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                            <i class="fas <?php echo $icon_data['icon']; ?>" style="font-size: 2.5rem; color: <?php echo $icon_data['color']; ?>;"></i>
                                        </div>
                                    </div>
                                    <h3 class="mb-2">You are marked as: 
                                        <span style="color: <?php echo $icon_data['color']; ?>;">
                                            <?php echo strtoupper($status); ?>
                                        </span>
                                    </h3>
                                    <?php if($my_attendance['remarks']): ?>
                                        <p class="text-muted"><strong>Remarks:</strong> <?php echo $my_attendance['remarks']; ?></p>
                                    <?php endif; ?>
                                    <p class="text-muted">
                                        <i class="far fa-clock me-1"></i>
                                        Verified at: <?php echo date('F j, Y \a\t g:i A', strtotime($my_attendance['verified_at'])); ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="empty-state py-5">
                                    <div class="empty-state-icon mb-3">
                                        <i class="fas fa-question-circle fa-4x text-muted"></i>
                                    </div>
                                    <h4>No attendance record yet</h4>
                                    <p class="text-muted">Your attendance for this event hasn't been marked yet.</p>
                                    <p class="text-muted small">Check back later or contact your class officer.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- No event selected -->
                <div class="empty-state text-center py-5">
                    <div class="empty-state-icon mb-3">
                        <i class="fas fa-calendar-plus fa-4x text-muted"></i>
                    </div>
                    <h4>Select an event to view attendance</h4>
                    <p class="text-muted">Choose an event from the dropdown above to see attendance records.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</div> <!-- Close dashboard-container -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Initialize tooltips -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<!-- Same sidebar JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const collapseBtn = document.getElementById('collapseBtn');
        const menuToggle = document.getElementById('menuToggle');
        const orgDropdown = document.getElementById('orgDropdown');
        const orgMenu = document.getElementById('orgMenu');
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationMenu = document.getElementById('notificationMenu');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        // Collapse/Expand sidebar
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });
        }
        
        // Mobile menu toggle
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-show');
            });
        }
        
        // Organizations dropdown
        if (orgDropdown) {
            orgDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                orgMenu.classList.toggle('show');
                const arrow = this.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('fa-chevron-down');
                    arrow.classList.toggle('fa-chevron-up');
                }
            });
        }
        
        // Notification hover/click behavior
        let notificationTimeout;
        
        if (notificationBtn && notificationMenu) {
            notificationBtn.addEventListener('mouseenter', function() {
                clearTimeout(notificationTimeout);
                notificationMenu.classList.add('show');
            });
            
            notificationBtn.addEventListener('mouseleave', function() {
                notificationTimeout = setTimeout(() => {
                    if (!notificationMenu.matches(':hover')) {
                        notificationMenu.classList.remove('show');
                    }
                }, 300);
            });
            
            notificationMenu.addEventListener('mouseenter', function() {
                clearTimeout(notificationTimeout);
            });
            
            notificationMenu.addEventListener('mouseleave', function() {
                notificationTimeout = setTimeout(() => {
                    if (!notificationMenu.classList.contains('stay')) {
                        notificationMenu.classList.remove('show');
                    }
                }, 300);
            });
            
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationMenu.classList.toggle('stay');
                notificationMenu.classList.add('show');
            });
            
            document.addEventListener('click', function(e) {
                if (!notificationDropdown.contains(e.target)) {
                    notificationMenu.classList.remove('show', 'stay');
                }
            });
        }
    });
</script>
</body>
</html>