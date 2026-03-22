<?php
// admin/dashboard.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Get statistics
$stats = [];

// Total users
$users_stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE status != 'inactive'");
$stats['total_users'] = $users_stmt->fetch_assoc()['total'];

// Active organizations
$orgs_stmt = $conn->query("SELECT COUNT(*) as total FROM organizations WHERE status = 'active'");
$stats['active_orgs'] = $orgs_stmt->fetch_assoc()['total'];

// Upcoming events
$events_stmt = $conn->query("SELECT COUNT(*) as total FROM events WHERE status = 'upcoming'");
$stats['upcoming_events'] = $events_stmt->fetch_assoc()['total'];

// Pending approvals
$pending_stmt = $conn->query("SELECT COUNT(*) as total FROM pending_officers WHERE status = 'pending'");
$stats['pending_approvals'] = $pending_stmt->fetch_assoc()['total'];

// Recent users
$recent_users = $conn->query("SELECT user_id, first_name, last_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");

// Recent events
$recent_events = $conn->query("SELECT e.*, o.org_name FROM events e JOIN organizations o ON e.org_id = o.org_id ORDER BY e.created_at DESC LIMIT 5");

// User role distribution
$role_distribution = $conn->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
$roles = ['student_member' => 0, 'org_officer' => 0, 'class_officer' => 0, 'admin' => 0];
while($row = $role_distribution->fetch_assoc()) {
    $roles[$row['role']] = $row['count'];
}

// Event status distribution
$event_status = $conn->query("SELECT status, COUNT(*) as count FROM events GROUP BY status");
$statuses = ['upcoming' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
while($row = $event_status->fetch_assoc()) {
    $statuses[$row['status']] = $row['count'];
}

// Monthly activity (last 6 months)
$monthly_activity = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
           COUNT(*) as user_count,
           (SELECT COUNT(*) FROM events WHERE DATE_FORMAT(created_at, '%Y-%m') = month) as event_count
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month DESC
");

$unread_count = getUnreadCount($conn, $user['user_id']);

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Admin Dashboard - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../style.css">
    <style>
        .stat-card-admin {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(79, 209, 197, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .stat-card-admin:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(79, 209, 197, 0.1);
            border-color: var(--primary-mint);
        }
        .admin-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(245, 101, 101, 0.1);
            color: #F56565;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: transform 0.3s ease;
        }
        .stat-card-admin:hover .admin-stat-icon {
            transform: scale(1.1);
        }
        .admin-badge {
            background: linear-gradient(135deg, #F56565, #C53030);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        .welcome-section {
            background: linear-gradient(135deg, rgba(79, 209, 197, 0.1), rgba(56, 178, 172, 0.05));
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        .activity-timeline {
            max-height: 400px;
            overflow-y: auto;
        }
        .timeline-item {
            padding: 15px;
            border-left: 3px solid var(--primary-mint);
            margin-bottom: 15px;
            background: rgba(79, 209, 197, 0.05);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .timeline-item:hover {
            transform: translateX(5px);
            background: rgba(79, 209, 197, 0.1);
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .welcome-badge {
            display: inline-block;
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="mb-2">
                            Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 
                            <span class="welcome-badge">👋</span>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>
                            <?php echo date('l, F j, Y'); ?>
                        </p>
                    </div>
                    <div class="admin-badge">
                        <i class="fas fa-shield-alt me-2"></i>Administrator
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card-admin" onclick="window.location.href='users.php'">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="admin-stat-icon me-3" style="color: #4FD1C5; background: rgba(79, 209, 197, 0.1);">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="text-end">
                                <h3 class="mb-0"><?php echo number_format($stats['total_users']); ?></h3>
                                <p class="text-muted mb-0">Total Users</p>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up"></i> 12% this month
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-admin" onclick="window.location.href='organizations.php'">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="admin-stat-icon me-3" style="color: #F6AD55; background: rgba(246, 173, 85, 0.1);">
                                    <i class="fas fa-building"></i>
                                </div>
                            </div>
                            <div class="text-end">
                                <h3 class="mb-0"><?php echo number_format($stats['active_orgs']); ?></h3>
                                <p class="text-muted mb-0">Active Organizations</p>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up"></i> 3 new
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-admin" onclick="window.location.href='events.php'">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="admin-stat-icon me-3" style="color: #48BB78; background: rgba(72, 187, 120, 0.1);">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                            <div class="text-end">
                                <h3 class="mb-0"><?php echo number_format($stats['upcoming_events']); ?></h3>
                                <p class="text-muted mb-0">Upcoming Events</p>
                                <small class="text-warning">
                                    <i class="fas fa-clock"></i> This week
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-admin" onclick="window.location.href='pending.php'">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="admin-stat-icon me-3" style="color: #F56565; background: rgba(245, 101, 101, 0.1);">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="text-end">
                                <h3 class="mb-0"><?php echo number_format($stats['pending_approvals']); ?></h3>
                                <p class="text-muted mb-0">Pending Approvals</p>
                                <?php if($stats['pending_approvals'] > 0): ?>
                                    <small class="text-danger">
                                        <i class="fas fa-exclamation-circle"></i> Requires action
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
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
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
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

            <!-- Recent Activity Row -->
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0">
                                <i class="fas fa-user-plus me-2" style="color: var(--primary-mint);"></i>
                                Recent Users
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php while($u = $recent_users->fetch_assoc()): ?>
                                    <div class="list-group-item border-0 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar-mini me-3">
                                                <?php echo strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)); ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-secondary"><?php echo str_replace('_',' ',$u['role']); ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo date('M d', strtotime($u['created_at'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0 text-end">
                            <a href="users.php" class="btn btn-sm btn-link">
                                View All Users <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-plus me-2" style="color: var(--primary-mint);"></i>
                                Recent Events
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php while($e = $recent_events->fetch_assoc()): ?>
                                    <div class="list-group-item border-0 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="event-date-badge me-3">
                                                <div class="day"><?php echo date('d', strtotime($e['start_datetime'])); ?></div>
                                                <div class="month"><?php echo date('M', strtotime($e['start_datetime'])); ?></div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($e['event_title']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($e['org_name']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php echo $e['status']=='upcoming'?'success':($e['status']=='ongoing'?'warning':'secondary'); ?>">
                                                    <?php echo $e['status']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0 text-end">
                            <a href="events.php" class="btn btn-sm btn-link">
                                View All Events <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Activity Chart -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2" style="color: var(--primary-mint);"></i>
                                Monthly Activity (Last 6 Months)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="activityChart" height="300"></canvas>
                        </div>
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
window.userChart = new Chart(userCtx, {
    type: 'doughnut',
    data: {
        labels: ['Students', 'Organization Officers', 'Class Officers', 'Admins'],
        datasets: [{
            data: [<?php echo $roles['student_member']; ?>, <?php echo $roles['org_officer']; ?>, <?php echo $roles['class_officer']; ?>, <?php echo $roles['admin']; ?>],
            backgroundColor: ['#4FD1C5', '#F6AD55', '#9F7AEA', '#F56565'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: { size: 12 }
                }
            },
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
window.eventChart = new Chart(eventCtx, {
    type: 'bar',
    data: {
        labels: ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'],
        datasets: [{
            label: 'Number of Events',
            data: [<?php echo $statuses['upcoming']; ?>, <?php echo $statuses['ongoing']; ?>, <?php echo $statuses['completed']; ?>, <?php echo $statuses['cancelled']; ?>],
            backgroundColor: ['#4FD1C5', '#F6AD55', '#48BB78', '#F56565'],
            borderRadius: 8,
            barPercentage: 0.6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: (ctx) => `${ctx.raw} events` } }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { stepSize: 1 }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

// Monthly Activity Chart
const activityData = {
    labels: [<?php 
        $months = [];
        $user_counts = [];
        $event_counts = [];
        while($row = $monthly_activity->fetch_assoc()) {
            array_unshift($months, "'" . date('M Y', strtotime($row['month'] . '-01')) . "'");
            array_unshift($user_counts, $row['user_count']);
            array_unshift($event_counts, $row['event_count']);
        }
        echo implode(',', $months);
    ?>],
    datasets: [
        {
            label: 'New Users',
            data: [<?php echo implode(',', $user_counts); ?>],
            borderColor: '#4FD1C5',
            backgroundColor: 'rgba(79, 209, 197, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#4FD1C5',
            pointBorderColor: '#fff',
            pointRadius: 5,
            pointHoverRadius: 7
        },
        {
            label: 'New Events',
            data: [<?php echo implode(',', $event_counts); ?>],
            borderColor: '#F6AD55',
            backgroundColor: 'rgba(246, 173, 85, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#F6AD55',
            pointBorderColor: '#fff',
            pointRadius: 5,
            pointHoverRadius: 7
        }
    ]
};

const activityCtx = document.getElementById('activityChart').getContext('2d');
new Chart(activityCtx, {
    type: 'line',
    data: activityData,
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            tooltip: { mode: 'index', intersect: false },
            legend: { position: 'top' }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                title: { display: true, text: 'Count' }
            },
            x: {
                grid: { display: false },
                title: { display: true, text: 'Month' }
            }
        }
    }
});

// Auto-refresh pending count every 30 seconds
setInterval(function() {
    fetch('get-pending-count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.sidebar-menu .badge');
            if (data.count > 0) {
                if (!badge) {
                    const pendingLink = document.querySelector('a[href="pending.php"]');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge bg-danger ms-auto';
                    newBadge.textContent = data.count;
                    pendingLink.appendChild(newBadge);
                } else {
                    badge.textContent = data.count;
                }
            } else if (badge) {
                badge.remove();
            }
        })
        .catch(err => console.error('Error fetching pending count:', err));
}, 30000);
</script>
</body>
</html>