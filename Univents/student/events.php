<?php
// student/events.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

// Handle event registration
if (isset($_GET['register']) && isset($_GET['csrf_token']) && verifyCSRFToken($_GET['csrf_token'])) {
    $event_id = intval($_GET['register']);
    $type = isset($_GET['type']) && $_GET['type'] == 'active' ? 'active' : 'spectator';
    
    // Check if event exists and user is a member of the organization
    $check = $conn->prepare("
        SELECT e.*, o.org_id 
        FROM events e 
        JOIN organizations o ON e.org_id = o.org_id 
        JOIN organization_memberships om ON o.org_id = om.org_id 
        WHERE e.event_id = ? AND om.user_id = ? AND om.status = 'active'
    ");
    $check->bind_param("ii", $event_id, $user['user_id']);
    $check->execute();
    $event = $check->get_result()->fetch_assoc();
    
    if ($event) {
        // Check if already registered
        $reg_check = $conn->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?");
        $reg_check->bind_param("ii", $event_id, $user['user_id']);
        $reg_check->execute();
        
        if ($reg_check->get_result()->num_rows == 0) {
            // Check active participant limit if registering as active
            if ($type == 'active' && $event['max_active_participants'] > 0) {
                $count_check = $conn->prepare("SELECT COUNT(*) as count FROM event_registrations WHERE event_id = ? AND registration_type = 'active'");
                $count_check->bind_param("i", $event_id);
                $count_check->execute();
                $current_count = $count_check->get_result()->fetch_assoc()['count'];
                
                if ($current_count >= $event['max_active_participants']) {
                    $error = "Sorry, active participant slots for this event are full.";
                } else {
                    $insert = $conn->prepare("INSERT INTO event_registrations (event_id, user_id, registration_type) VALUES (?, ?, ?)");
                    $insert->bind_param("iis", $event_id, $user['user_id'], $type);
                    if ($insert->execute()) {
                        $success = "Successfully registered as " . ($type == 'active' ? 'Active Participant' : 'Spectator') . "!";
                    } else {
                        $error = "Failed to register for event.";
                    }
                    $insert->close();
                }
                $count_check->close();
            } else {
                $insert = $conn->prepare("INSERT INTO event_registrations (event_id, user_id, registration_type) VALUES (?, ?, ?)");
                $insert->bind_param("iis", $event_id, $user['user_id'], $type);
                if ($insert->execute()) {
                    $success = "Successfully registered as " . ($type == 'active' ? 'Active Participant' : 'Spectator') . "!";
                } else {
                    $error = "Failed to register for event.";
                }
                $insert->close();
            }
        } else {
            $error = "You are already registered for this event.";
        }
        $reg_check->close();
    } else {
        $error = "Event not found or you are not a member of this organization.";
    }
    $check->close();
}

// Get events from user's organizations
$events = $conn->prepare("
    SELECT e.*, o.org_name, o.org_color,
           (SELECT registration_type FROM event_registrations WHERE event_id = e.event_id AND user_id = ?) as my_registration,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id AND registration_type = 'active') as active_count,
           (SELECT COUNT(*) FROM attendance_logs WHERE event_id = e.event_id AND student_id = ?) as attended
    FROM events e 
    JOIN organizations o ON e.org_id = o.org_id 
    JOIN organization_memberships om ON o.org_id = om.org_id 
    WHERE om.user_id = ? AND om.status = 'active'
    ORDER BY e.start_datetime ASC
");
$events->bind_param("iii", $user['user_id'], $user['user_id'], $user['user_id']);
$events->execute();
$events_result = $events->get_result();

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Events - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .event-card {
            transition: all 0.3s ease;
        }
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .registration-badge {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        .slot-info {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 5px;
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="mb-4">
                <h2 class="mb-1">
                    <i class="fas fa-calendar-alt me-2" style="color: var(--primary-mint);"></i>
                    Upcoming Events
                </h2>
                <p class="text-muted">Browse and register for events from your organizations</p>
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
            
            <div class="row g-4">
                <?php if($events_result->num_rows > 0): ?>
                    <?php while($e = $events_result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-0 shadow-sm event-card h-100 position-relative">
                                <?php if($e['my_registration']): ?>
                                    <div class="registration-badge">
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            Registered as <?php echo ucfirst($e['my_registration']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <div class="d-flex gap-3 mb-3">
                                        <div class="event-date-badge">
                                            <div class="day"><?php echo date('d', strtotime($e['start_datetime'])); ?></div>
                                            <div class="month"><?php echo date('M', strtotime($e['start_datetime'])); ?></div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($e['event_title']); ?></h5>
                                            <p class="text-muted small mb-1">
                                                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($e['org_name']); ?>
                                            </p>
                                            <p class="text-muted small mb-1">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($e['venue']); ?>
                                            </p>
                                            <p class="text-muted small">
                                                <i class="far fa-clock me-1"></i><?php echo date('F j, Y g:i A', strtotime($e['start_datetime'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if($e['event_description']): ?>
                                        <p class="small text-muted mb-3">
                                            <?php echo substr(htmlspecialchars($e['event_description']), 0, 100); ?>
                                            <?php if(strlen($e['event_description']) > 100): ?>...<?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if($e['registration_required']): ?>
                                        <div class="slot-info mb-3">
                                            <i class="fas fa-users me-1"></i>
                                            Active Participants: <?php echo $e['active_count']; ?> / <?php echo $e['max_active_participants'] ?? '∞'; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if(!$e['my_registration'] && strtotime($e['start_datetime']) > time()): ?>
                                        <div class="d-flex gap-2">
                                            <a href="?register=<?php echo $e['event_id']; ?>&type=spectator&csrf_token=<?php echo $csrf_token; ?>" 
                                               class="btn btn-sm btn-outline-primary flex-grow-1">
                                                <i class="fas fa-eye me-1"></i>Register as Spectator
                                            </a>
                                            <?php if($e['registration_required'] && $e['active_count'] < ($e['max_active_participants'] ?? PHP_INT_MAX)): ?>
                                                <a href="?register=<?php echo $e['event_id']; ?>&type=active&csrf_token=<?php echo $csrf_token; ?>" 
                                                   class="btn btn-sm btn-primary flex-grow-1">
                                                    <i class="fas fa-running me-1"></i>Join as Active
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif($e['my_registration']): ?>
                                        <div class="alert alert-info small mb-0">
                                            <i class="fas fa-info-circle me-1"></i>
                                            You are registered for this event. Please attend on time!
                                        </div>
                                    <?php elseif(strtotime($e['start_datetime']) <= time()): ?>
                                        <div class="alert alert-secondary small mb-0">
                                            <i class="fas fa-clock me-1"></i>
                                            This event has already passed.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <h4>No Upcoming Events</h4>
                            <p class="text-muted">Check back later for events from your organizations.</p>
                            <a href="organizations.php" class="btn btn-primary">
                                <i class="fas fa-building me-2"></i>Browse Organizations
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>