<?php
// student/dashboard.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

// Get user's organizations
$my_orgs = $conn->prepare("
    SELECT o.*, om.membership_date 
    FROM organizations o 
    JOIN organization_memberships om ON o.org_id = om.org_id 
    WHERE om.user_id = ? AND om.status = 'active'
    ORDER BY om.membership_date DESC
");
$my_orgs->bind_param("i", $user['user_id']);
$my_orgs->execute();
$my_organizations = $my_orgs->get_result();

// Get upcoming events from user's organizations
$events = $conn->prepare("
    SELECT DISTINCT e.*, o.org_name, o.org_color,
           (SELECT COUNT(*) FROM attendance_logs WHERE event_id = e.event_id AND student_id = ?) as attended
    FROM events e 
    JOIN organizations o ON e.org_id = o.org_id 
    JOIN organization_memberships om ON o.org_id = om.org_id 
    WHERE om.user_id = ? AND om.status = 'active' 
      AND e.status = 'upcoming' 
      AND e.start_datetime > NOW()
    ORDER BY e.start_datetime ASC 
    LIMIT 5
");
$events->bind_param("ii", $user['user_id'], $user['user_id']);
$events->execute();
$upcoming_events = $events->get_result();

// Get attendance statistics
$attendance = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
    FROM attendance_logs 
    WHERE student_id = ?
");
$attendance->bind_param("i", $user['user_id']);
$attendance->execute();
$attendance_stats = $attendance->get_result()->fetch_assoc();

// Get recent announcements
$announcements = $conn->prepare("
    SELECT a.*, o.org_name, o.org_color 
    FROM announcements a 
    JOIN organizations o ON a.org_id = o.org_id 
    JOIN organization_memberships om ON o.org_id = om.org_id 
    WHERE om.user_id = ? AND om.status = 'active'
    ORDER BY a.is_pinned DESC, a.created_at DESC 
    LIMIT 5
");
$announcements->bind_param("i", $user['user_id']);
$announcements->execute();
$recent_announcements = $announcements->get_result();

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Student Dashboard - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .welcome-section {
            background: linear-gradient(135deg, rgba(79, 209, 197, 0.1), rgba(56, 178, 172, 0.05));
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .stat-card-student {
            background: white;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .stat-card-student:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .attendance-progress {
            height: 8px;
            border-radius: 4px;
            background: #e0e0e0;
            overflow: hidden;
        }
        .attendance-progress-bar {
            height: 100%;
            background: var(--gradient);
            transition: width 0.5s ease;
        }
        .event-card-small {
            transition: all 0.3s ease;
        }
        .event-card-small:hover {
            transform: translateX(5px);
            background: rgba(79, 209, 197, 0.05);
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="mb-2">
                            Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 👋
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="fas fa-graduation-cap me-2"></i>
                            <?php echo htmlspecialchars($user['course']); ?> - Year <?php echo $user['year_level']; ?> Block <?php echo htmlspecialchars($user['block']); ?>
                        </p>
                    </div>
                    <div>
                        <span class="badge bg-primary">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo date('F j, Y'); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card-student" onclick="window.location.href='organizations.php'">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-icon mb-2" style="background: rgba(79, 209, 197, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-building fa-2x" style="color: var(--primary-mint);"></i>
                                </div>
                                <h3 class="mb-0 mt-2"><?php echo $my_organizations->num_rows; ?></h3>
                                <p class="text-muted mb-0">Organizations</p>
                            </div>
                            <div class="text-end">
                                <i class="fas fa-arrow-right text-muted"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-student" onclick="window.location.href='events.php'">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-icon mb-2" style="background: rgba(79, 209, 197, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-calendar-alt fa-2x" style="color: var(--primary-mint);"></i>
                                </div>
                                <h3 class="mb-0 mt-2"><?php echo $upcoming_events->num_rows; ?></h3>
                                <p class="text-muted mb-0">Upcoming Events</p>
                            </div>
                            <div class="text-end">
                                <i class="fas fa-arrow-right text-muted"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-student" onclick="window.location.href='attendance.php'">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-icon mb-2" style="background: rgba(79, 209, 197, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-check-circle fa-2x" style="color: var(--primary-mint);"></i>
                                </div>
                                <h3 class="mb-0 mt-2"><?php echo $attendance_stats['present'] ?? 0; ?></h3>
                                <p class="text-muted mb-0">Events Attended</p>
                            </div>
                            <div class="text-end">
                                <small class="text-success">
                                    <i class="fas fa-chart-line"></i> <?php echo $attendance_stats['total'] ?? 0; ?> total
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-student" onclick="window.location.href='notifications.php'">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-icon mb-2" style="background: rgba(79, 209, 197, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-bell fa-2x" style="color: var(--primary-mint);"></i>
                                </div>
                                <h3 class="mb-0 mt-2"><?php echo $unread_count; ?></h3>
                                <p class="text-muted mb-0">Notifications</p>
                            </div>
                            <div class="text-end">
                                <?php if($unread_count > 0): ?>
                                    <span class="badge bg-danger">New</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Progress -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2" style="color: var(--primary-mint);"></i>
                        Attendance Overview
                    </h5>
                    <?php 
                    $total_attendance = ($attendance_stats['present'] ?? 0) + ($attendance_stats['late'] ?? 0) + ($attendance_stats['excused'] ?? 0);
                    $attendance_rate = $total_attendance > 0 ? (($attendance_stats['present'] ?? 0) / $total_attendance) * 100 : 0;
                    ?>
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="attendance-progress mb-2">
                                <div class="attendance-progress-bar" style="width: <?php echo $attendance_rate; ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between text-muted small">
                                <span>Present: <?php echo $attendance_stats['present'] ?? 0; ?></span>
                                <span>Late: <?php echo $attendance_stats['late'] ?? 0; ?></span>
                                <span>Excused: <?php echo $attendance_stats['excused'] ?? 0; ?></span>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <h3 class="mb-0"><?php echo round($attendance_rate); ?>%</h3>
                            <small class="text-muted">Attendance Rate</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Upcoming Events -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-week me-2" style="color: var(--primary-mint);"></i>
                                    Upcoming Events
                                </h5>
                                <a href="events.php" class="btn btn-sm btn-link">View All <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if($upcoming_events->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while($event = $upcoming_events->fetch_assoc()): ?>
                                        <div class="list-group-item event-card-small">
                                            <div class="d-flex gap-3">
                                                <div class="event-date-badge">
                                                    <div class="day"><?php echo date('d', strtotime($event['start_datetime'])); ?></div>
                                                    <div class="month"><?php echo date('M', strtotime($event['start_datetime'])); ?></div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($event['event_title']); ?></h6>
                                                    <p class="text-muted small mb-1">
                                                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($event['org_name']); ?>
                                                        <i class="fas fa-map-marker-alt ms-2 me-1"></i><?php echo htmlspecialchars($event['venue']); ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        <i class="far fa-clock me-1"></i>
                                                        <?php echo date('M d, Y g:i A', strtotime($event['start_datetime'])); ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <a href="events.php?register=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        Register
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No upcoming events in your organizations.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Announcements -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-bullhorn me-2" style="color: var(--primary-mint);"></i>
                                    Recent Announcements
                                </h5>
                                <a href="announcements.php" class="btn btn-sm btn-link">View All <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if($recent_announcements->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while($announcement = $recent_announcements->fetch_assoc()): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <?php if($announcement['is_pinned']): ?>
                                                            <i class="fas fa-thumbtack text-warning me-1"></i>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                                    </h6>
                                                    <p class="text-muted small mb-1">
                                                        <?php echo htmlspecialchars($announcement['org_name']); ?>
                                                        • <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                                    </p>
                                                    <p class="small mb-0">
                                                        <?php echo substr(htmlspecialchars($announcement['content']), 0, 100); ?>
                                                        <?php if(strlen($announcement['content']) > 100): ?>...<?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No announcements yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- My Organizations -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-transparent">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-building me-2" style="color: var(--primary-mint);"></i>
                            My Organizations
                        </h5>
                        <a href="organizations.php" class="btn btn-sm btn-link">Browse More <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if($my_organizations->num_rows > 0): ?>
                            <?php while($org = $my_organizations->fetch_assoc()): ?>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center p-3 border rounded hover-lift">
                                        <div class="org-logo-placeholder me-3" style="width: 50px; height: 50px; background: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-users" style="color: white; font-size: 1.2rem;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($org['org_name']); ?></h6>
                                            <small class="text-muted">Member since <?php echo date('M Y', strtotime($org['membership_date'])); ?></small>
                                        </div>
                                        <a href="organizations.php?id=<?php echo $org['org_id']; ?>" class="btn btn-sm btn-link">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">You haven't joined any organizations yet.</p>
                                <a href="organizations.php" class="btn btn-primary btn-sm">Browse Organizations</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>