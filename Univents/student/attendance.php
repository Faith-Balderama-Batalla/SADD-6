<?php
// student/attendance.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

// Get attendance records
$attendance = $conn->prepare("
    SELECT al.*, e.event_title, e.start_datetime, e.venue, o.org_name
    FROM attendance_logs al 
    JOIN events e ON al.event_id = e.event_id 
    JOIN organizations o ON e.org_id = o.org_id 
    WHERE al.student_id = ? 
    ORDER BY e.start_datetime DESC
");
$attendance->bind_param("i", $user['user_id']);
$attendance->execute();
$attendance_result = $attendance->get_result();

// Get statistics
$stats = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused,
        COUNT(DISTINCT e.org_id) as organizations,
        COUNT(DISTINCT DATE_FORMAT(e.start_datetime, '%Y-%m')) as months_active
    FROM attendance_logs al 
    JOIN events e ON al.event_id = e.event_id 
    WHERE al.student_id = ?
");
$stats->bind_param("i", $user['user_id']);
$stats->execute();
$attendance_stats = $stats->get_result()->fetch_assoc();

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>My Attendance - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .stat-card-attendance {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card-attendance:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .attendance-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
        }
        .badge-present {
            background: rgba(72, 187, 120, 0.1);
            color: #48BB78;
        }
        .badge-late {
            background: rgba(246, 173, 85, 0.1);
            color: #F6AD55;
        }
        .badge-excused {
            background: rgba(159, 122, 234, 0.1);
            color: #9F7AEA;
        }
        .attendance-rate {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-mint);
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="mb-4">
                <h2 class="mb-1">
                    <i class="fas fa-check-circle me-2" style="color: var(--primary-mint);"></i>
                    My Attendance Record
                </h2>
                <p class="text-muted">View your attendance history for events</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card-attendance">
                        <div class="attendance-badge badge-present">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="mb-0"><?php echo $attendance_stats['present'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Present</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-attendance">
                        <div class="attendance-badge badge-late">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="mb-0"><?php echo $attendance_stats['late'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Late</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-attendance">
                        <div class="attendance-badge badge-excused">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="mb-0"><?php echo $attendance_stats['excused'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Excused</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-attendance">
                        <div class="attendance-rate">
                            <?php 
                            $total = ($attendance_stats['present'] ?? 0) + ($attendance_stats['late'] ?? 0) + ($attendance_stats['excused'] ?? 0);
                            $rate = $total > 0 ? round(($attendance_stats['present'] ?? 0) / $total * 100) : 0;
                            echo $rate . '%';
                            ?>
                        </div>
                        <p class="text-muted mb-0">Attendance Rate</p>
                    </div>
                </div>
            </div>
            
            <!-- Additional Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="mb-3">
                                <i class="fas fa-chart-simple me-2" style="color: var(--primary-mint);"></i>
                                Attendance Summary
                            </h6>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Present</span>
                                    <span><?php echo $attendance_stats['present'] ?? 0; ?> events</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: <?php echo $total > 0 ? (($attendance_stats['present'] ?? 0) / $total * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Late</span>
                                    <span><?php echo $attendance_stats['late'] ?? 0; ?> events</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $total > 0 ? (($attendance_stats['late'] ?? 0) / $total * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Excused</span>
                                    <span><?php echo $attendance_stats['excused'] ?? 0; ?> events</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: <?php echo $total > 0 ? (($attendance_stats['excused'] ?? 0) / $total * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="mb-3">
                                <i class="fas fa-chart-line me-2" style="color: var(--primary-mint);"></i>
                                Engagement Metrics
                            </h6>
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <h3 class="mb-0"><?php echo $attendance_stats['organizations'] ?? 0; ?></h3>
                                    <small class="text-muted">Organizations Joined</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <h3 class="mb-0"><?php echo $attendance_stats['months_active'] ?? 0; ?></h3>
                                    <small class="text-muted">Months Active</small>
                                </div>
                                <div class="col-12">
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <small class="text-muted">Total Events</small>
                                            <h5 class="mb-0"><?php echo $attendance_stats['total'] ?? 0; ?></h5>
                                        </div>
                                        <div>
                                            <small class="text-muted">Avg per Month</small>
                                            <h5 class="mb-0">
                                                <?php 
                                                $months = max(1, $attendance_stats['months_active'] ?? 1);
                                                echo round(($attendance_stats['total'] ?? 0) / $months, 1);
                                                ?>
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance History Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2" style="color: var(--primary-mint);"></i>
                        Attendance History
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Event</th>
                                    <th>Organization</th>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Status</th>
                                    <th>Verified At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($attendance_result->num_rows > 0): ?>
                                    <?php while($a = $attendance_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($a['event_title']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($a['org_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($a['start_datetime'])); ?></td>
                                            <td><?php echo htmlspecialchars($a['venue']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $a['status'] == 'present' ? 'success' : 
                                                         ($a['status'] == 'late' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo strtoupper($a['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('g:i A', strtotime($a['verified_at'])); ?></small>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No attendance records found.</p>
                                            <a href="events.php" class="btn btn-primary btn-sm">
                                                <i class="fas fa-calendar-alt me-1"></i>Browse Events
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Showing <?php echo $attendance_result->num_rows; ?> records
                        </small>
                        <button class="btn btn-sm btn-outline-secondary" onclick="exportAttendance()">
                            <i class="fas fa-download me-1"></i>Export CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
function exportAttendance() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    window.location.href = 'export-attendance.php?csrf_token=' + csrfToken;
}
</script>
</body>
</html>