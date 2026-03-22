<?php
// student/organization.php - View single organization details
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

$org_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$org_id) {
    header('Location: organizations.php');
    exit();
}

// Get organization details
$org_stmt = $conn->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM organization_memberships WHERE org_id = o.org_id AND status = 'active') as member_count,
           (SELECT COUNT(*) FROM events WHERE org_id = o.org_id) as event_count
    FROM organizations o 
    WHERE o.org_id = ? AND o.status = 'active'
");
$org_stmt->bind_param("i", $org_id);
$org_stmt->execute();
$org = $org_stmt->get_result()->fetch_assoc();

if (!$org) {
    header('Location: organizations.php');
    exit();
}

// Check if user is a member
$member_stmt = $conn->prepare("SELECT * FROM organization_memberships WHERE user_id = ? AND org_id = ?");
$member_stmt->bind_param("ii", $user['user_id'], $org_id);
$member_stmt->execute();
$is_member = $member_stmt->get_result()->num_rows > 0;
$member_stmt->close();

// Handle join/leave
if (isset($_GET['join']) && isset($_GET['csrf_token']) && verifyCSRFToken($_GET['csrf_token'])) {
    if (!$is_member) {
        $insert = $conn->prepare("INSERT INTO organization_memberships (user_id, org_id, status) VALUES (?, ?, 'pending')");
        $insert->bind_param("ii", $user['user_id'], $org_id);
        if ($insert->execute()) {
            $success = "Membership request submitted successfully!";
            $is_member = true;
        }
        $insert->close();
    }
}

if (isset($_GET['leave']) && isset($_GET['csrf_token']) && verifyCSRFToken($_GET['csrf_token'])) {
    $update = $conn->prepare("UPDATE organization_memberships SET status = 'inactive' WHERE user_id = ? AND org_id = ?");
    $update->bind_param("ii", $user['user_id'], $org_id);
    if ($update->execute()) {
        $success = "You have left the organization.";
        $is_member = false;
    }
    $update->close();
}

// Get events from this organization
$events_stmt = $conn->prepare("
    SELECT * FROM events 
    WHERE org_id = ? 
    ORDER BY start_datetime ASC 
    LIMIT 10
");
$events_stmt->bind_param("i", $org_id);
$events_stmt->execute();
$events = $events_stmt->get_result();

// Get announcements from this organization
$announcements_stmt = $conn->prepare("
    SELECT * FROM announcements 
    WHERE org_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$announcements_stmt->bind_param("i", $org_id);
$announcements_stmt->execute();
$announcements = $announcements_stmt->get_result();

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title><?php echo htmlspecialchars($org['org_name']); ?> - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .org-header {
            background: linear-gradient(135deg, <?php echo $org['org_color'] ?? '#4FD1C5'; ?>, <?php echo $org['org_color'] ?? '#38B2AC'; ?>);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .org-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
        }
        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        .org-logo-large {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .stat-badge {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
        }
        .tab-content {
            padding-top: 20px;
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Organization Header -->
            <div class="org-header">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center text-md-start">
                        <div class="org-logo-large mx-auto mx-md-0">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="col-md-6 text-white">
                        <h1 class="mb-2"><?php echo htmlspecialchars($org['org_name']); ?></h1>
                        <p class="mb-3 opacity-75"><?php echo htmlspecialchars($org['org_description'] ?? 'No description available.'); ?></p>
                        <div class="d-flex gap-3 flex-wrap">
                            <div class="stat-badge">
                                <i class="fas fa-users me-1"></i> <?php echo $org['member_count']; ?> Members
                            </div>
                            <div class="stat-badge">
                                <i class="fas fa-calendar-alt me-1"></i> <?php echo $org['event_count']; ?> Events
                            </div>
                            <?php if($org['contact_email']): ?>
                                <div class="stat-badge">
                                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($org['contact_email']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3 text-center text-md-end mt-3 mt-md-0">
                        <?php if($is_member): ?>
                            <a href="?leave=1&csrf_token=<?php echo $csrf_token; ?>" class="btn btn-outline-light" onclick="return confirm('Are you sure you want to leave this organization?')">
                                <i class="fas fa-sign-out-alt me-2"></i>Leave Organization
                            </a>
                        <?php else: ?>
                            <a href="?join=1&csrf_token=<?php echo $csrf_token; ?>" class="btn btn-light">
                                <i class="fas fa-plus-circle me-2"></i>Join Organization
                            </a>
                            <small class="d-block text-white-50 mt-2">* Membership requires admin approval</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <ul class="nav nav-tabs" id="orgTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab">
                        <i class="fas fa-calendar-alt me-2"></i>Events
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="announcements-tab" data-bs-toggle="tab" data-bs-target="#announcements" type="button" role="tab">
                        <i class="fas fa-bullhorn me-2"></i>Announcements
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="about-tab" data-bs-toggle="tab" data-bs-target="#about" type="button" role="tab">
                        <i class="fas fa-info-circle me-2"></i>About
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="orgTabsContent">
                <!-- Events Tab -->
                <div class="tab-pane fade show active" id="events" role="tabpanel">
                    <?php if($events->num_rows > 0): ?>
                        <div class="row g-4 mt-2">
                            <?php while($event = $events->fetch_assoc()): ?>
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex gap-3">
                                                <div class="event-date-badge">
                                                    <div class="day"><?php echo date('d', strtotime($event['start_datetime'])); ?></div>
                                                    <div class="month"><?php echo date('M', strtotime($event['start_datetime'])); ?></div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($event['event_title']); ?></h5>
                                                    <p class="text-muted small mb-1">
                                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($event['venue']); ?>
                                                    </p>
                                                    <p class="text-muted small">
                                                        <i class="far fa-clock me-1"></i><?php echo date('M d, Y g:i A', strtotime($event['start_datetime'])); ?>
                                                    </p>
                                                    <a href="events.php?register=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-primary mt-2">
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <p class="text-muted">No upcoming events from this organization.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Announcements Tab -->
                <div class="tab-pane fade" id="announcements" role="tabpanel">
                    <?php if($announcements->num_rows > 0): ?>
                        <?php while($ann = $announcements->fetch_assoc()): ?>
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <h5 class="mb-1">
                                        <?php if($ann['is_pinned']): ?>
                                            <i class="fas fa-thumbtack text-warning me-2"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($ann['title']); ?>
                                    </h5>
                                    <p class="text-muted small mb-2">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo date('F j, Y g:i A', strtotime($ann['created_at'])); ?>
                                    </p>
                                    <p><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                            <p class="text-muted">No announcements from this organization.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- About Tab -->
                <div class="tab-pane fade" id="about" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5>About <?php echo htmlspecialchars($org['org_name']); ?></h5>
                            <p class="mt-3"><?php echo nl2br(htmlspecialchars($org['org_description'] ?? 'No description available.')); ?></p>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-envelope me-2"></i>Contact Information</h6>
                                    <?php if($org['contact_email']): ?>
                                        <p class="mb-1">Email: <?php echo htmlspecialchars($org['contact_email']); ?></p>
                                    <?php endif; ?>
                                    <?php if($org['contact_number']): ?>
                                        <p>Phone: <?php echo htmlspecialchars($org['contact_number']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-chart-line me-2"></i>Statistics</h6>
                                    <p class="mb-1">Total Members: <?php echo $org['member_count']; ?></p>
                                    <p>Total Events: <?php echo $org['event_count']; ?></p>
                                </div>
                            </div>
                        </div>
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