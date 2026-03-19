<?php
// Admin/reports.php
require_once '../config.php';

if (!isLoggedIn()) {
    header('Location: ../login-signup.php');
    exit();
}

$user = getCurrentUser($conn);

if ($user['role'] !== 'admin') {
    header('Location: ../StudentMembers/dashboard.php');
    exit();
}

// Get date range filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$report_type = isset($_GET['type']) ? $_GET['type'] : 'overview';

// Get organizations for filter
$orgs_sql = "SELECT org_id, org_name FROM organizations WHERE status = 'active' ORDER BY org_name ASC";
$orgs_result = $conn->query($orgs_sql);

// Get unread notifications count
$notif_sql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user['user_id']);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$unread_count = $notif_result->fetch_assoc()['unread'] ?? 0;

// Overview Statistics
$stats = [];

// Total users by role
$users_by_role = $conn->query("
    SELECT 
        role, 
        COUNT(*) as count 
    FROM users 
    WHERE status = 'active'
    GROUP BY role
");

while($row = $users_by_role->fetch_assoc()) {
    $stats['users'][$row['role']] = $row['count'];
}

// Events statistics
$events_stats = $conn->query("
    SELECT 
        events.status,
        COUNT(*) as count
    FROM events
    WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
    GROUP BY status
");

while($row = $events_stats->fetch_assoc()) {
    $stats['events'][$row['status']] = $row['count'];
}

// Attendance statistics
$attendance_stats = $conn->query("
    SELECT 
        COUNT(DISTINCT al.student_id) as total_students,
        COUNT(*) as total_attendances,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count
    FROM attendance_logs al
    JOIN events e ON al.event_id = e.event_id
    WHERE DATE(e.start_datetime) BETWEEN '$date_from' AND '$date_to'
");

$stats['attendance'] = $attendance_stats->fetch_assoc();

// Organization statistics
$org_stats = $conn->query("
    SELECT 
        COUNT(*) as total_orgs,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_orgs
    FROM organizations
");

$stats['organizations'] = $org_stats->fetch_assoc();

// Get top organizations by events
$top_orgs = $conn->query("
    SELECT 
        o.org_name,
        o.org_color,
        COUNT(e.event_id) as event_count,
        COUNT(DISTINCT al.student_id) as total_attendees
    FROM organizations o
    LEFT JOIN events e ON o.org_id = e.org_id
    LEFT JOIN attendance_logs al ON e.event_id = al.event_id
    WHERE DATE(e.start_datetime) BETWEEN '$date_from' AND '$date_to' OR e.event_id IS NULL
    GROUP BY o.org_id
    ORDER BY event_count DESC
    LIMIT 5
");

// Get recent activities
$recent_activities = $conn->query("
    (SELECT 
        'user' as type,
        user_id as id,
        CONCAT(first_name, ' ', last_name) as title,
        'New user registered' as description,
        created_at as date
    FROM users
    WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to')
    
    UNION ALL
    
    (SELECT 
        'event' as type,
        event_id as id,
        event_title as title,
        CONCAT('Event created by ', org_id) as description,
        created_at as date
    FROM events
    WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to')
    
    UNION ALL
    
    (SELECT 
        'attendance' as type,
        attendance_id as id,
        CONCAT('Attendance marked for event ', event_id) as title,
        status as description,
        verified_at as date
    FROM attendance_logs
    WHERE DATE(verified_at) BETWEEN '$date_from' AND '$date_to')
    
    ORDER BY date DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Univents Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Main CSS -->
    <link rel="stylesheet" href="../style.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../style3.css">
    <style>
        .report-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
            border: 1px solid rgba(79, 209, 197, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(79, 209, 197, 0.1);
        }
        
        .report-title {
            font-size: 1rem;
            color: var(--text-light);
            margin-bottom: 10px;
        }
        
        .report-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .trend-up {
            color: #48BB78;
            font-size: 0.9rem;
        }
        
        .trend-down {
            color: #F56565;
            font-size: 0.9rem;
        }
        
        .activity-item {
            padding: 12px;
            border-bottom: 1px solid rgba(79, 209, 197, 0.1);
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            background: rgba(79, 209, 197, 0.05);
        }
        
        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .activity-icon.user {
            background: rgba(79, 209, 197, 0.1);
            color: var(--primary-mint);
        }
        
        .activity-icon.event {
            background: rgba(246, 173, 85, 0.1);
            color: #F6AD55;
        }
        
        .activity-icon.attendance {
            background: rgba(72, 187, 120, 0.1);
            color: #48BB78;
        }
        
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid rgba(79, 209, 197, 0.1);
        }
        
        .export-btn {
            border: 1px solid rgba(79, 209, 197, 0.2);
            padding: 8px 16px;
            border-radius: 8px;
            color: var(--text-dark);
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            background: var(--primary-mint);
            color: white;
            border-color: var(--primary-mint);
        }
        
        .type-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .type-badge.user {
            background: rgba(79, 209, 197, 0.1);
            color: var(--primary-mint);
        }
        
        .type-badge.event {
            background: rgba(246, 173, 85, 0.1);
            color: #F6AD55;
        }
        
        .type-badge.attendance {
            background: rgba(72, 187, 120, 0.1);
            color: #48BB78;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body class="dashboard-body">
    
    <?php include 'nav_sidebar_admin.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Reports & Analytics</h2>
                    <p class="text-muted">Track system performance and engagement metrics</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="export-btn" onclick="exportReport()">
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                </div>
            </div>
            
            <!-- Date Range Filter -->
            <div class="filter-bar">
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
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group" role="group">
                            <a href="?type=overview&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn <?php echo $report_type == 'overview' ? 'btn-primary' : 'btn-outline-primary'; ?>">Overview</a>
                            <a href="?type=users&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn <?php echo $report_type == 'users' ? 'btn-primary' : 'btn-outline-primary'; ?>">Users</a>
                            <a href="?type=events&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn <?php echo $report_type == 'events' ? 'btn-primary' : 'btn-outline-primary'; ?>">Events</a>
                            <a href="?type=attendance&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn <?php echo $report_type == 'attendance' ? 'btn-primary' : 'btn-outline-primary'; ?>">Attendance</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Overview Statistics -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="report-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="report-title">Total Users</div>
                                <div class="report-value"><?php echo array_sum($stats['users'] ?? [0]); ?></div>
                            </div>
                            <div class="admin-stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="trend-up"><i class="fas fa-arrow-up me-1"></i>+12%</span>
                            <span class="text-muted ms-2">from last month</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="report-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="report-title">Total Events</div>
                                <div class="report-value"><?php echo array_sum($stats['events'] ?? [0]); ?></div>
                            </div>
                            <div class="admin-stat-icon" style="color: #F6AD55;">
                                <i class="fas fa-calendar"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="trend-up"><i class="fas fa-arrow-up me-1"></i>+5%</span>
                            <span class="text-muted ms-2">from last month</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="report-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="report-title">Attendance</div>
                                <div class="report-value"><?php echo $stats['attendance']['total_attendances'] ?? 0; ?></div>
                            </div>
                            <div class="admin-stat-icon" style="color: #48BB78;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="trend-up"><i class="fas fa-arrow-up me-1"></i>+8%</span>
                            <span class="text-muted ms-2">from last month</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="report-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="report-title">Organizations</div>
                                <div class="report-value"><?php echo $stats['organizations']['active_orgs'] ?? 0; ?></div>
                            </div>
                            <div class="admin-stat-icon" style="color: #9F7AEA;">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="trend-up"><i class="fas fa-arrow-up me-1"></i>+2</span>
                            <span class="text-muted ms-2">new this month</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="report-card">
                        <h5 class="mb-3">User Distribution by Role</h5>
                        <div class="chart-container">
                            <canvas id="userChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="report-card">
                        <h5 class="mb-3">Event Status Distribution</h5>
                        <div class="chart-container">
                            <canvas id="eventChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Breakdown -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="report-card text-center">
                        <h5 class="mb-3">Present</h5>
                        <div class="report-value" style="color: #48BB78;"><?php echo $stats['attendance']['present_count'] ?? 0; ?></div>
                        <p class="text-muted">Students</p>
                        <div class="progress" style="height: 10px;">
                            <?php 
                            $total = $stats['attendance']['total_attendances'] ?? 1;
                            $present_percent = round(($stats['attendance']['present_count'] ?? 0) / $total * 100);
                            ?>
                            <div class="progress-bar bg-success" style="width: <?php echo $present_percent; ?>%"></div>
                        </div>
                        <p class="mt-2"><?php echo $present_percent; ?>% of attendance</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="report-card text-center">
                        <h5 class="mb-3">Late</h5>
                        <div class="report-value" style="color: #F6AD55;"><?php echo $stats['attendance']['late_count'] ?? 0; ?></div>
                        <p class="text-muted">Students</p>
                        <div class="progress" style="height: 10px;">
                            <?php 
                            $late_percent = round(($stats['attendance']['late_count'] ?? 0) / $total * 100);
                            ?>
                            <div class="progress-bar bg-warning" style="width: <?php echo $late_percent; ?>%"></div>
                        </div>
                        <p class="mt-2"><?php echo $late_percent; ?>% of attendance</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="report-card text-center">
                        <h5 class="mb-3">Excused</h5>
                        <div class="report-value" style="color: #9F7AEA;"><?php echo $stats['attendance']['excused_count'] ?? 0; ?></div>
                        <p class="text-muted">Students</p>
                        <div class="progress" style="height: 10px;">
                            <?php 
                            $excused_percent = round(($stats['attendance']['excused_count'] ?? 0) / $total * 100);
                            ?>
                            <div class="progress-bar bg-info" style="width: <?php echo $excused_percent; ?>%"></div>
                        </div>
                        <p class="mt-2"><?php echo $excused_percent; ?>% of attendance</p>
                    </div>
                </div>
            </div>
            
            <!-- Top Organizations -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="report-card">
                        <h5 class="mb-3">Top Organizations by Events</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Organization</th>
                                        <th>Events</th>
                                        <th>Total Attendees</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($org = $top_orgs->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div style="width: 12px; height: 12px; background: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>; border-radius: 4px; margin-right: 8px;"></div>
                                                <?php echo $org['org_name']; ?>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-primary"><?php echo $org['event_count']; ?></span></td>
                                        <td><?php echo $org['total_attendees'] ?? 0; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="col-md-6">
                    <div class="report-card">
                        <h5 class="mb-3">Recent Activity</h5>
                        <div class="activity-feed" style="max-height: 300px; overflow-y: auto;">
                            <?php if($recent_activities && $recent_activities->num_rows > 0): ?>
                                <?php while($activity = $recent_activities->fetch_assoc()): ?>
                                    <div class="activity-item d-flex align-items-center gap-3">
                                        <div class="activity-icon <?php echo $activity['type']; ?>">
                                            <?php if($activity['type'] == 'user'): ?>
                                                <i class="fas fa-user-plus"></i>
                                            <?php elseif($activity['type'] == 'event'): ?>
                                                <i class="fas fa-calendar-plus"></i>
                                            <?php else: ?>
                                                <i class="fas fa-check-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <strong><?php echo $activity['title']; ?></strong>
                                                <small class="text-muted"><?php echo date('M d, g:i A', strtotime($activity['date'])); ?></small>
                                            </div>
                                            <small class="text-muted"><?php echo $activity['description']; ?></small>
                                        </div>
                                        <span class="type-badge <?php echo $activity['type']; ?>"><?php echo $activity['type']; ?></span>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">No recent activity</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
</div> <!-- Close dashboard-container -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // User Chart
    const userCtx = document.getElementById('userChart').getContext('2d');
    new Chart(userCtx, {
        type: 'doughnut',
        data: {
            labels: ['Students', 'Class Officers', 'Organization Officers', 'Admins'],
            datasets: [{
                data: [
                    <?php echo $stats['users']['student_member'] ?? 0; ?>,
                    <?php echo $stats['users']['class_officer'] ?? 0; ?>,
                    <?php echo $stats['users']['org_officer'] ?? 0; ?>,
                    <?php echo $stats['users']['admin'] ?? 0; ?>
                ],
                backgroundColor: [
                    '#4FD1C5',
                    '#F6AD55',
                    '#9F7AEA',
                    '#F56565'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Event Chart
    const eventCtx = document.getElementById('eventChart').getContext('2d');
    new Chart(eventCtx, {
        type: 'bar',
        data: {
            labels: ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'],
            datasets: [{
                label: 'Number of Events',
                data: [
                    <?php echo $stats['events']['upcoming'] ?? 0; ?>,
                    <?php echo $stats['events']['ongoing'] ?? 0; ?>,
                    <?php echo $stats['events']['completed'] ?? 0; ?>,
                    <?php echo $stats['events']['cancelled'] ?? 0; ?>
                ],
                backgroundColor: [
                    '#4FD1C5',
                    '#F6AD55',
                    '#48BB78',
                    '#F56565'
                ],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});

// Export function (placeholder)
function exportReport() {
    alert('Report export feature coming soon!');
}
</script>

<!-- Sidebar JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const collapseBtn = document.getElementById('collapseBtn');
        const menuToggle = document.getElementById('menuToggle');
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationMenu = document.getElementById('notificationMenu');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
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
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-show');
            });
        }
        
        if (profileBtn) {
            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileMenu.classList.toggle('show');
                if (notificationMenu) notificationMenu.classList.remove('show');
            });
        }
        
        if (notificationBtn) {
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationMenu.classList.toggle('show');
                if (profileMenu) profileMenu.classList.remove('show');
            });
        }
        
        document.addEventListener('click', function() {
            if (profileMenu) profileMenu.classList.remove('show');
            if (notificationMenu) notificationMenu.classList.remove('show');
        });
    });
</script>
</body>
</html>