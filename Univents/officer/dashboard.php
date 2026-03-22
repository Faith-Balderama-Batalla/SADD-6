<?php
// officer/dashboard.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'org_officer') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Get officer's organizations
$orgs = $conn->prepare("
    SELECT o.*, oo.position, oo.term_start, oo.term_end 
    FROM organizations o 
    JOIN organization_officers oo ON o.org_id = oo.org_id 
    WHERE oo.user_id = ? AND oo.status = 'active'
");
$orgs->bind_param("i", $user['user_id']);
$orgs->execute();
$my_organizations = $orgs->get_result();

// Get current organization (first one if multiple)
$current_org = $my_organizations->fetch_assoc();
$my_organizations->data_seek(0); // Reset pointer

if (!$current_org) {
    $error = "You are not assigned to any organization. Please contact the administrator.";
}

// Get statistics for current organization
if ($current_org) {
    // Total events
    $events_stmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE org_id = ?");
    $events_stmt->bind_param("i", $current_org['org_id']);
    $events_stmt->execute();
    $stats['total_events'] = $events_stmt->get_result()->fetch_assoc()['total'];
    
    // Upcoming events
    $upcoming_stmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE org_id = ? AND status = 'upcoming' AND start_datetime > NOW()");
    $upcoming_stmt->bind_param("i", $current_org['org_id']);
    $upcoming_stmt->execute();
    $stats['upcoming_events'] = $upcoming_stmt->get_result()->fetch_assoc()['total'];
    
    // Total members
    $members_stmt = $conn->prepare("SELECT COUNT(*) as total FROM organization_memberships WHERE org_id = ? AND status = 'active'");
    $members_stmt->bind_param("i", $current_org['org_id']);
    $members_stmt->execute();
    $stats['total_members'] = $members_stmt->get_result()->fetch_assoc()['total'];
    
    // Total attendance this month
    $attendance_stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM attendance_logs al 
        JOIN events e ON al.event_id = e.event_id 
        WHERE e.org_id = ? AND MONTH(al.verified_at) = MONTH(NOW())
    ");
    $attendance_stmt->bind_param("i", $current_org['org_id']);
    $attendance_stmt->execute();
    $stats['monthly_attendance'] = $attendance_stmt->get_result()->fetch_assoc()['total'];
    
    // Recent events
    $recent_events = $conn->prepare("
        SELECT * FROM events 
        WHERE org_id = ? 
        ORDER BY start_datetime DESC 
        LIMIT 5
    ");
    $recent_events->bind_param("i", $current_org['org_id']);
    $recent_events->execute();
    $recent_events_result = $recent_events->get_result();
    
    // Upcoming events
    $upcoming_events = $conn->prepare("
        SELECT * FROM events 
        WHERE org_id = ? AND status = 'upcoming' AND start_datetime > NOW()
        ORDER BY start_datetime ASC 
        LIMIT 5
    ");
    $upcoming_events->bind_param("i", $current_org['org_id']);
    $upcoming_events->execute();
    $upcoming_events_result = $upcoming_events->get_result();
    
    // Recent announcements
    $announcements = $conn->prepare("
        SELECT * FROM announcements 
        WHERE org_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $announcements->bind_param("i", $current_org['org_id']);
    $announcements->execute();
    $announcements_result = $announcements->get_result();
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
    <title>Officer Dashboard - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .org-banner-card {
            background: linear-gradient(135deg, rgba(79, 209, 197, 0.1), rgba(56, 178, 172, 0.05));
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .org-banner-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(79, 209, 197, 0.1), transparent);
            border-radius: 50%;
        }
        .stat-card-officer {
            background: white;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .stat-card-officer:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .quick-action {
            background: white;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .quick-action:hover {
            transform: translateY(-5px);
            background: var(--light-mint);
        }
        .quick-action i {
            font-size: 2rem;
            color: var(--primary-mint);
            margin-bottom: 10px;
        }
        .welcome-message {
            background: var(--gradient);
            color: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_officer.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($current_org): ?>
                <!-- Welcome Banner -->
                <div class="welcome-message">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h2 class="mb-1">
                                Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 👋
                            </h2>
                            <p class="mb-0 opacity-75">
                                Managing <strong><?php echo htmlspecialchars($current_org['org_name']); ?></strong> as <?php echo htmlspecialchars($current_org['position'] ?? 'Officer'); ?>
                            </p>
                        </div>
                        <div>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Term: <?php echo date('M Y', strtotime($current_org['term_start'])); ?> - 
                                <?php echo $current_org['term_end'] ? date('M Y', strtotime($current_org['term_end'])) : 'Present'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card-officer" onclick="window.location.href='events.php'">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-icon mb-2" style="background: rgba(79, 209, 197, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-calendar-alt fa-2x" style="color: var(--primary-mint);"></i>
                                    </div>
                                    <h3 class="mb-0 mt-2"><?php echo number_format($stats['total_events']); ?></h3>
                                    <p class="text-muted mb-0">Total Events</p>
                                </div>
                                <div class="text-end">
                                    <small class="text-success">
                                        <i class="fas fa-plus"></i> <?php echo $stats['upcoming_events']; ?> upcoming
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-officer" onclick="window.location.href='members.php'">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-icon mb-2" style="background: rgba(79, 209, 197, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-users fa-2x" style="color: var(--primary-mint);"></i>
                                    </div>
                                    <h3 class="mb-0 mt-2"><?php echo number_format($stats['total_members']); ?></h3>
                                    <p class="text-muted mb-0">Active Members</p>
                                </div>
                                <div class="text-end">
                                    <i class="fas fa-user-plus text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-officer" onclick="window.location.href='attendance.php'">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-icon mb-2" style="background: rgba(79, 209, 197, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-check-circle fa-2x" style="color: var(--primary-mint);"></i>
                                    </div>
                                    <h3 class="mb-0 mt-2"><?php echo number_format($stats['monthly_attendance']); ?></h3>
                                    <p class="text-muted mb-0">This Month</p>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">Attendance Records</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-officer" onclick="window.location.href='announcements.php'">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-icon mb-2" style="background: rgba(79, 209, 197, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-bullhorn fa-2x" style="color: var(--primary-mint);"></i>
                                    </div>
                                    <h3 class="mb-0 mt-2"><?php echo $announcements_result->num_rows; ?></h3>
                                    <p class="text-muted mb-0">Recent Posts</p>
                                </div>
                                <div class="text-end">
                                    <button class="btn btn-sm btn-link" onclick="window.location.href='announcements.php?action=new'">Post New</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <h5 class="mb-3">Quick Actions</h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="quick-action" onclick="window.location.href='events.php?action=create'">
                                    <i class="fas fa-calendar-plus"></i>
                                    <h6 class="mb-0">Create Event</h6>
                                    <small class="text-muted">Schedule new event</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="quick-action" onclick="window.location.href='attendance.php'">
                                    <i class="fas fa-qrcode"></i>
                                    <h6 class="mb-0">Take Attendance</h6>
                                    <small class="text-muted">Mark attendance</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="quick-action" onclick="window.location.href='announcements.php?action=new'">
                                    <i class="fas fa-bullhorn"></i>
                                    <h6 class="mb-0">Post Announcement</h6>
                                    <small class="text-muted">Share updates</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="quick-action" onclick="window.location.href='reports.php'">
                                    <i class="fas fa-chart-line"></i>
                                    <h6 class="mb-0">View Reports</h6>
                                    <small class="text-muted">Analytics</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming Events & Recent Activity -->
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-week me-2" style="color: var(--primary-mint);"></i>
                                    Upcoming Events
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if($upcoming_events_result->num_rows > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php while($event = $upcoming_events_result->fetch_assoc()): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="event-date-badge">
                                                        <div class="day"><?php echo date('d', strtotime($event['start_datetime'])); ?></div>
                                                        <div class="month"><?php echo date('M', strtotime($event['start_datetime'])); ?></div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($event['event_title']); ?></h6>
                                                        <p class="text-muted small mb-0">
                                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($event['venue']); ?>
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <a href="attendance.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-check-circle"></i> Take Attendance
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No upcoming events scheduled.</p>
                                        <button class="btn btn-sm btn-primary" onclick="window.location.href='events.php?action=create'">
                                            Create Event
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent text-end">
                                <a href="events.php" class="btn btn-sm btn-link">View All Events <i class="fas fa-arrow-right ms-2"></i></a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent">
                                <h5 class="mb-0">
                                    <i class="fas fa-bullhorn me-2" style="color: var(--primary-mint);"></i>
                                    Recent Announcements
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if($announcements_result->num_rows > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php while($announcement = $announcements_result->fetch_assoc()): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1">
                                                            <?php if($announcement['is_pinned']): ?>
                                                                <i class="fas fa-thumbtack text-warning me-1"></i>
                                                            <?php endif; ?>
                                                            <?php echo htmlspecialchars($announcement['title']); ?>
                                                        </h6>
                                                        <p class="text-muted small mb-0">
                                                            <?php echo substr(htmlspecialchars($announcement['content']), 0, 100); ?>
                                                            <?php if(strlen($announcement['content']) > 100): ?>...<?php endif; ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            <i class="far fa-clock me-1"></i>
                                                            <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <a href="announcements.php?edit=<?php echo $announcement['announcement_id']; ?>" class="btn btn-sm btn-link">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No announcements yet.</p>
                                        <button class="btn btn-sm btn-primary" onclick="window.location.href='announcements.php?action=new'">
                                            Create Announcement
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent text-end">
                                <a href="announcements.php" class="btn btn-sm btn-link">View All <i class="fas fa-arrow-right ms-2"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Organization Stats -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2" style="color: var(--primary-mint);"></i>
                                    Organization Performance
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="attendanceChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
// Attendance Chart
const ctx = document.getElementById('attendanceChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Attendance Records',
            data: [12, 19, 15, 17, 14, 20, 25, 22, 28, 30, 35, 42],
            borderColor: '#4FD1C5',
            backgroundColor: 'rgba(79, 209, 197, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'top' }
        }
    }
});
</script>
</body>
</html>