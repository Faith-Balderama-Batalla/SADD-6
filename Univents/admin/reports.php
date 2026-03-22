<?php
// admin/reports.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Date range for reports
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

// Get statistics
$date_condition = "AND DATE(created_at) BETWEEN '$date_from' AND '$date_to'";

// User statistics
$users_by_role = $conn->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
$users_by_month = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month");

// Event statistics
$events_by_status = $conn->query("SELECT status, COUNT(*) as count FROM events WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to' GROUP BY status");
$events_by_month = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month");

// Attendance statistics
$attendance = $conn->query("
    SELECT 
        COUNT(DISTINCT al.student_id) as total_students,
        COUNT(*) as total_attendances,
        SUM(CASE WHEN al.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN al.status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN al.status = 'excused' THEN 1 ELSE 0 END) as excused
    FROM attendance_logs al 
    JOIN events e ON al.event_id = e.event_id 
    WHERE DATE(e.start_datetime) BETWEEN '$date_from' AND '$date_to'
")->fetch_assoc();

// Organization statistics
$org_stats = $conn->query("
    SELECT 
        COUNT(*) as total_orgs,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_orgs,
        (SELECT COUNT(*) FROM organization_memberships WHERE status = 'active') as total_memberships
    FROM organizations
")->fetch_assoc();

// Top organizations by events
$top_orgs = $conn->query("
    SELECT o.org_id, o.org_name, o.org_color, 
           COUNT(DISTINCT e.event_id) as event_count,
           COUNT(DISTINCT al.student_id) as attendee_count
    FROM organizations o 
    LEFT JOIN events e ON o.org_id = e.org_id 
    LEFT JOIN attendance_logs al ON e.event_id = al.event_id
    WHERE o.status = 'active'
    GROUP BY o.org_id 
    ORDER BY event_count DESC 
    LIMIT 5
");

// Top events by attendance
$top_events = $conn->query("
    SELECT e.event_id, e.event_title, o.org_name,
           COUNT(al.attendance_id) as attendance_count
    FROM events e
    JOIN organizations o ON e.org_id = o.org_id
    LEFT JOIN attendance_logs al ON e.event_id = al.event_id
    WHERE e.status = 'completed'
    GROUP BY e.event_id
    ORDER BY attendance_count DESC
    LIMIT 5
");

// Recent activity
$recent_activity = $conn->query("
    (SELECT 'user' as type, user_id as id, CONCAT(first_name,' ',last_name) as title, 'registered' as action, created_at as date 
     FROM users 
     WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to')
    UNION ALL 
    (SELECT 'event' as type, event_id as id, event_title as title, 'created' as action, created_at as date 
     FROM events 
     WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to')
    UNION ALL
    (SELECT 'attendance' as type, attendance_id as id, CONCAT('Attendance recorded') as title, 'marked' as action, verified_at as date 
     FROM attendance_logs 
     WHERE DATE(verified_at) BETWEEN '$date_from' AND '$date_to')
    ORDER BY date DESC 
    LIMIT 20
");

// Prepare data for charts
$users_data = ['student_member' => 0, 'class_officer' => 0, 'org_officer' => 0, 'admin' => 0];
while($row = $users_by_role->fetch_assoc()) {
    $users_data[$row['role']] = $row['count'];
}

$events_data = ['upcoming' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
while($row = $events_by_status->fetch_assoc()) {
    $events_data[$row['status']] = $row['count'];
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
    <title>Reports - Univents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../style.css">
    <style>
        .report-header {
            background: linear-gradient(135deg, rgba(79, 209, 197, 0.1), rgba(56, 178, 172, 0.05));
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .stat-card-report {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card-report:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-mint);
        }
        .export-btn {
            background: var(--gradient);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 209, 197, 0.3);
        }
        @media print {
            .dashboard-navbar, .sidebar, .filter-bar, .export-btn, .btn {
                display: none !important;
            }
            .main-content {
                margin: 0;
                padding: 0;
            }
            .dashboard-content {
                margin: 0;
                padding: 20px;
            }
            .card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="report-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-chart-bar me-2" style="color: var(--primary-mint);"></i>
                            Reports & Analytics
                        </h2>
                        <p class="text-muted">System performance metrics and activity insights</p>
                    </div>
                    <div>
                        <button class="export-btn me-2" onclick="exportToCSV('full-report', 'univents_report.csv')">
                            <i class="fas fa-download me-2"></i>Export CSV
                        </button>
                        <button class="export-btn" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Date Range Filter -->
            <div class="filter-bar mb-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-calendar-alt me-2"></i>Apply
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Key Metrics -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card-report">
                        <div class="stat-value"><?php echo number_format($users_data['student_member'] + $users_data['class_officer'] + $users_data['org_officer'] + $users_data['admin']); ?></div>
                        <p class="text-muted mb-0">Total Users</p>
                        <small class="text-success">
                            <i class="fas fa-arrow-up"></i> Active accounts
                        </small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-report">
                        <div class="stat-value"><?php echo number_format($org_stats['active_orgs']); ?></div>
                        <p class="text-muted mb-0">Active Organizations</p>
                        <small class="text-muted"><?php echo number_format($org_stats['total_memberships']); ?> memberships</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-report">
                        <div class="stat-value"><?php echo number_format($attendance['total_attendances'] ?? 0); ?></div>
                        <p class="text-muted mb-0">Total Attendance</p>
                        <small class="text-muted"><?php echo number_format($attendance['total_students'] ?? 0); ?> unique students</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-report">
                        <div class="stat-value"><?php echo number_format($events_data['upcoming'] + $events_data['ongoing']); ?></div>
                        <p class="text-muted mb-0">Active Events</p>
                        <small class="text-muted">Upcoming + Ongoing</small>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-pie me-2" style="color: var(--primary-mint);"></i>
                                User Distribution
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="userChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2" style="color: var(--primary-mint);"></i>
                                Event Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="eventChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Breakdown -->
            <div class="row g-4 mb-4">
                <div class="col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-check-circle me-2" style="color: var(--primary-mint);"></i>
                                Attendance Breakdown (<?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="p-3">
                                        <h3 class="text-success"><?php echo number_format($attendance['present'] ?? 0); ?></h3>
                                        <p class="text-muted">Present</p>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: <?php echo $attendance['total_attendances'] ? (($attendance['present'] / $attendance['total_attendances']) * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3">
                                        <h3 class="text-warning"><?php echo number_format($attendance['late'] ?? 0); ?></h3>
                                        <p class="text-muted">Late</p>
                                        <div class="progress">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $attendance['total_attendances'] ? (($attendance['late'] / $attendance['total_attendances']) * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3">
                                        <h3 class="text-info"><?php echo number_format($attendance['excused'] ?? 0); ?></h3>
                                        <p class="text-muted">Excused</p>
                                        <div class="progress">
                                            <div class="progress-bar bg-info" style="width: <?php echo $attendance['total_attendances'] ? (($attendance['excused'] / $attendance['total_attendances']) * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3">
                                        <h3 class="text-secondary"><?php echo number_format(($attendance['total_attendances'] ?? 0) - (($attendance['present'] ?? 0) + ($attendance['late'] ?? 0) + ($attendance['excused'] ?? 0))); ?></h3>
                                        <p class="text-muted">Absent</p>
                                        <div class="progress">
                                            <div class="progress-bar bg-secondary" style="width: <?php echo $attendance['total_attendances'] ? ((($attendance['total_attendances'] - ($attendance['present'] + $attendance['late'] + $attendance['excused'])) / $attendance['total_attendances']) * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Performers -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-trophy me-2" style="color: var(--primary-mint);"></i>
                                Top Organizations by Events
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php while($org = $top_orgs->fetch_assoc()): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="d-flex align-items-center">
                                                <div style="width: 12px; height: 12px; background: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>; border-radius: 3px; margin-right: 10px;"></div>
                                                <strong><?php echo htmlspecialchars($org['org_name']); ?></strong>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($org['attendee_count']); ?> total attendees</small>
                                        </div>
                                        <span class="badge bg-primary rounded-pill"><?php echo $org['event_count']; ?> events</span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-star me-2" style="color: var(--primary-mint);"></i>
                                Top Events by Attendance
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php while($event = $top_events->fetch_assoc()): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($event['org_name']); ?></small>
                                        </div>
                                        <span class="badge bg-success rounded-pill"><?php echo number_format($event['attendance_count']); ?> attendees</span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2" style="color: var(--primary-mint);"></i>
                        Recent Activity
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline" style="max-height: 400px; overflow-y: auto;">
                        <?php while($activity = $recent_activity->fetch_assoc()): ?>
                            <div class="d-flex align-items-start gap-3 mb-3 p-2 rounded hover-lift">
                                <div class="activity-icon p-2 rounded-circle" style="background: rgba(79, 209, 197, 0.1);">
                                    <i class="fas fa-<?php 
                                        echo $activity['type'] == 'user' ? 'user-plus' : 
                                             ($activity['type'] == 'event' ? 'calendar-plus' : 'check-circle'); 
                                    ?>" style="color: var(--primary-mint);"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-0">
                                        <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                        <span class="text-muted"><?php echo $activity['action']; ?></span>
                                    </p>
                                    <small class="text-muted">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo date('F j, Y g:i A', strtotime($activity['date'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
// User Distribution Chart
const userCtx = document.getElementById('userChart').getContext('2d');
new Chart(userCtx, {
    type: 'doughnut',
    data: {
        labels: ['Students', 'Class Officers', 'Organization Officers', 'Admins'],
        datasets: [{
            data: [<?php echo $users_data['student_member']; ?>, <?php echo $users_data['class_officer']; ?>, <?php echo $users_data['org_officer']; ?>, <?php echo $users_data['admin']; ?>],
            backgroundColor: ['#4FD1C5', '#F6AD55', '#9F7AEA', '#F56565'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Event Status Chart
const eventCtx = document.getElementById('eventChart').getContext('2d');
new Chart(eventCtx, {
    type: 'bar',
    data: {
        labels: ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'],
        datasets: [{
            label: 'Number of Events',
            data: [<?php echo $events_data['upcoming']; ?>, <?php echo $events_data['ongoing']; ?>, <?php echo $events_data['completed']; ?>, <?php echo $events_data['cancelled']; ?>],
            backgroundColor: ['#4FD1C5', '#F6AD55', '#48BB78', '#F56565'],
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' }
            }
        }
    }
});

// Export full report to CSV
function exportToCSV(reportName, filename) {
    // This would collect all data from the page
    // For now, we'll export a simple version
    let csv = [];
    csv.push(['Univents System Report', 'Generated: ' + new Date().toLocaleString()]);
    csv.push([]);
    csv.push(['User Distribution']);
    csv.push(['Role', 'Count']);
    csv.push(['Students', <?php echo $users_data['student_member']; ?>]);
    csv.push(['Class Officers', <?php echo $users_data['class_officer']; ?>]);
    csv.push(['Organization Officers', <?php echo $users_data['org_officer']; ?>]);
    csv.push(['Admins', <?php echo $users_data['admin']; ?>]);
    csv.push([]);
    csv.push(['Event Status']);
    csv.push(['Status', 'Count']);
    csv.push(['Upcoming', <?php echo $events_data['upcoming']; ?>]);
    csv.push(['Ongoing', <?php echo $events_data['ongoing']; ?>]);
    csv.push(['Completed', <?php echo $events_data['completed']; ?>]);
    csv.push(['Cancelled', <?php echo $events_data['cancelled']; ?>]);
    csv.push([]);
    csv.push(['Attendance Summary']);
    csv.push(['Metric', 'Count']);
    csv.push(['Total Attendances', <?php echo $attendance['total_attendances'] ?? 0; ?>]);
    csv.push(['Present', <?php echo $attendance['present'] ?? 0; ?>]);
    csv.push(['Late', <?php echo $attendance['late'] ?? 0; ?>]);
    csv.push(['Excused', <?php echo $attendance['excused'] ?? 0; ?>]);
    
    const blob = new Blob([csv.map(row => row.join(',')).join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
    
    showToast('Report exported successfully!', 'success');
}
</script>
</body>
</html>