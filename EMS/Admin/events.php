<?php
// Admin/events.php
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

$success = '';
$error = '';

// Handle Add Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $org_id = intval($_POST['org_id']);
    $event_title = mysqli_real_escape_string($conn, $_POST['event_title']);
    $event_description = mysqli_real_escape_string($conn, $_POST['event_description']);
    $event_type = mysqli_real_escape_string($conn, $_POST['event_type']);
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    $start_datetime = $_POST['start_datetime'];
    $end_datetime = $_POST['end_datetime'];
    $collect_feedback = isset($_POST['collect_feedback']) ? 1 : 0;
    
    $insert_sql = "INSERT INTO events (org_id, event_title, event_description, event_type, venue, start_datetime, end_datetime, collect_feedback, created_by, status) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("issssssii", $org_id, $event_title, $event_description, $event_type, $venue, $start_datetime, $end_datetime, $collect_feedback, $user['user_id']);
    
    if ($insert_stmt->execute()) {
        $success = "Event created successfully!";
    } else {
        $error = "Failed to create event.";
    }
    $insert_stmt->close();
}

// Handle Update Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $event_id = intval($_POST['event_id']);
    $org_id = intval($_POST['org_id']);
    $event_title = mysqli_real_escape_string($conn, $_POST['event_title']);
    $event_description = mysqli_real_escape_string($conn, $_POST['event_description']);
    $event_type = mysqli_real_escape_string($conn, $_POST['event_type']);
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    $start_datetime = $_POST['start_datetime'];
    $end_datetime = $_POST['end_datetime'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $collect_feedback = isset($_POST['collect_feedback']) ? 1 : 0;
    
    $update_sql = "UPDATE events SET org_id = ?, event_title = ?, event_description = ?, event_type = ?, venue = ?, start_datetime = ?, end_datetime = ?, status = ?, collect_feedback = ? WHERE event_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("isssssssii", $org_id, $event_title, $event_description, $event_type, $venue, $start_datetime, $end_datetime, $status, $collect_feedback, $event_id);
    
    if ($update_stmt->execute()) {
        $success = "Event updated successfully!";
    } else {
        $error = "Failed to update event.";
    }
    $update_stmt->close();
}

// Handle Delete Event
if (isset($_GET['delete'])) {
    $event_id = intval($_GET['delete']);
    
    $delete_sql = "DELETE FROM events WHERE event_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $event_id);
    
    if ($delete_stmt->execute()) {
        $success = "Event deleted successfully!";
    } else {
        $error = "Failed to delete event.";
    }
    $delete_stmt->close();
}

// Get all organizations for dropdown
$orgs_sql = "SELECT org_id, org_name, org_color FROM organizations WHERE status = 'active' ORDER BY org_name ASC";
$orgs_result = $conn->query($orgs_sql);

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_org = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;

// Build events query with filters
$events_sql = "SELECT e.*, o.org_name, o.org_color,
                      (SELECT COUNT(*) FROM attendance_logs WHERE event_id = e.event_id) as attendance_count,
                      (SELECT COUNT(*) FROM event_feedback WHERE event_id = e.event_id) as feedback_count
               FROM events e
               JOIN organizations o ON e.org_id = o.org_id
               WHERE 1=1";

if ($filter_status !== 'all') {
    $events_sql .= " AND e.status = '$filter_status'";
}

if ($filter_org > 0) {
    $events_sql .= " AND e.org_id = $filter_org";
}

$events_sql .= " ORDER BY 
                  CASE e.status
                    WHEN 'upcoming' THEN 1
                    WHEN 'ongoing' THEN 2
                    WHEN 'completed' THEN 3
                    ELSE 4
                  END, e.start_datetime DESC";

$events_result = $conn->query($events_sql);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
              FROM events";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get unread notifications count
$notif_sql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user['user_id']);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$unread_count = $notif_result->fetch_assoc()['unread'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Univents Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Main CSS -->
    <link rel="stylesheet" href="../style.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../style3.css">
    <style>
        .event-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
            border: 1px solid rgba(79, 209, 197, 0.1);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(79, 209, 197, 0.1);
            border-color: var(--primary-mint);
        }
        
        .event-color-strip {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .event-date-badge {
            background: rgba(79, 209, 197, 0.1);
            border-radius: 12px;
            padding: 10px;
            text-align: center;
            min-width: 70px;
        }
        
        .event-date-badge .day {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-mint);
            line-height: 1;
        }
        
        .event-date-badge .month {
            font-size: 0.8rem;
            color: var(--text-light);
            text-transform: uppercase;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.upcoming {
            background: rgba(79, 209, 197, 0.1);
            color: var(--primary-mint);
        }
        
        .status-badge.ongoing {
            background: rgba(246, 173, 85, 0.1);
            color: #F6AD55;
        }
        
        .status-badge.completed {
            background: rgba(72, 187, 120, 0.1);
            color: #48BB78;
        }
        
        .status-badge.cancelled {
            background: rgba(245, 101, 101, 0.1);
            color: #F56565;
        }
        
        .filter-btn {
            border: 1px solid rgba(79, 209, 197, 0.2);
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            color: var(--text-dark);
            transition: all 0.3s ease;
            margin-right: 5px;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: var(--primary-mint);
            color: white;
            border-color: var(--primary-mint);
        }
        
        .stat-card-small {
            background: white;
            border-radius: 12px;
            padding: 15px;
            border: 1px solid rgba(79, 209, 197, 0.1);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-mint);
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
                    <h2 class="mb-1">Event Management</h2>
                    <p class="text-muted">Create and manage all events across organizations</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="fas fa-plus-circle me-2"></i>Create New Event
                </button>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card-small">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon me-3">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                                <div class="text-muted">Total Events</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-small">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon me-3" style="color: #4FD1C5;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div class="stat-number" style="color: #4FD1C5;"><?php echo $stats['upcoming'] ?? 0; ?></div>
                                <div class="text-muted">Upcoming</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-small">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon me-3" style="color: #F6AD55;">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div>
                                <div class="stat-number" style="color: #F6AD55;"><?php echo $stats['ongoing'] ?? 0; ?></div>
                                <div class="text-muted">Ongoing</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-small">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon me-3" style="color: #48BB78;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <div class="stat-number" style="color: #48BB78;"><?php echo $stats['completed'] ?? 0; ?></div>
                                <div class="text-muted">Completed</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="?status=all" class="filter-btn <?php echo $filter_status == 'all' ? 'active' : ''; ?>">All Events</a>
                                <a href="?status=upcoming" class="filter-btn <?php echo $filter_status == 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
                                <a href="?status=ongoing" class="filter-btn <?php echo $filter_status == 'ongoing' ? 'active' : ''; ?>">Ongoing</a>
                                <a href="?status=completed" class="filter-btn <?php echo $filter_status == 'completed' ? 'active' : ''; ?>">Completed</a>
                                <a href="?status=cancelled" class="filter-btn <?php echo $filter_status == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-control" onchange="location.href = '?org_id=' + this.value + '&status=<?php echo $filter_status; ?>'">
                                <option value="0">All Organizations</option>
                                <?php 
                                $orgs_result->data_seek(0);
                                while($org = $orgs_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $org['org_id']; ?>" <?php echo $filter_org == $org['org_id'] ? 'selected' : ''; ?>>
                                        <?php echo $org['org_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Events Grid -->
            <?php if($events_result && $events_result->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while($event = $events_result->fetch_assoc()): ?>
                        <div class="col-lg-6">
                            <div class="event-card">
                                <div class="event-color-strip" style="background: <?php echo $event['org_color'] ?? '#4FD1C5'; ?>;"></div>
                                
                                <div class="d-flex gap-3">
                                    <!-- Date Badge -->
                                    <div class="event-date-badge">
                                        <div class="day"><?php echo date('d', strtotime($event['start_datetime'])); ?></div>
                                        <div class="month"><?php echo date('M', strtotime($event['start_datetime'])); ?></div>
                                    </div>
                                    
                                    <!-- Event Details -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($event['event_title']); ?></h5>
                                                <p class="text-muted small mb-2">
                                                    <i class="fas fa-building me-1"></i><?php echo $event['org_name']; ?>
                                                </p>
                                            </div>
                                            <span class="status-badge <?php echo $event['status']; ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo $event['venue']; ?>
                                        </p>
                                        
                                        <p class="text-muted small mb-2">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo date('F j, Y', strtotime($event['start_datetime'])); ?> | 
                                            <?php echo date('g:i A', strtotime($event['start_datetime'])); ?> - 
                                            <?php echo date('g:i A', strtotime($event['end_datetime'])); ?>
                                        </p>
                                        
                                        <p class="text-muted small mb-3">
                                            <?php echo substr(htmlspecialchars($event['event_description'] ?? 'No description'), 0, 100); ?>...
                                        </p>
                                        
                                        <!-- Stats -->
                                        <div class="d-flex gap-3 mb-3">
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-users me-1"></i> <?php echo $event['attendance_count']; ?> attended
                                            </span>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-star me-1"></i> <?php echo $event['feedback_count']; ?> feedback
                                            </span>
                                            <?php if($event['collect_feedback']): ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-check-circle text-success me-1"></i> Feedback enabled
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editEventModal<?php echo $event['event_id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="event_details.php?id=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmDelete(<?php echo $event['event_id']; ?>, '<?php echo htmlspecialchars($event['event_title']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Event Modal -->
                        <div class="modal fade" id="editEventModal<?php echo $event['event_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Event</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Organization</label>
                                                    <select name="org_id" class="form-control" required>
                                                        <?php 
                                                        $orgs_result->data_seek(0);
                                                        while($org = $orgs_result->fetch_assoc()): 
                                                        ?>
                                                            <option value="<?php echo $org['org_id']; ?>" <?php echo $event['org_id'] == $org['org_id'] ? 'selected' : ''; ?>>
                                                                <?php echo $org['org_name']; ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Event Title</label>
                                                    <input type="text" name="event_title" class="form-control" value="<?php echo htmlspecialchars($event['event_title']); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea name="event_description" class="form-control" rows="3"><?php echo htmlspecialchars($event['event_description']); ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Event Type</label>
                                                    <select name="event_type" class="form-control">
                                                        <option value="workshop" <?php echo $event['event_type'] == 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                                        <option value="meeting" <?php echo $event['event_type'] == 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                                                        <option value="social" <?php echo $event['event_type'] == 'social' ? 'selected' : ''; ?>>Social</option>
                                                        <option value="fundraising" <?php echo $event['event_type'] == 'fundraising' ? 'selected' : ''; ?>>Fundraising</option>
                                                        <option value="other" <?php echo $event['event_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Venue</label>
                                                    <input type="text" name="venue" class="form-control" value="<?php echo $event['venue']; ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Start Date & Time</label>
                                                    <input type="datetime-local" name="start_datetime" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($event['start_datetime'])); ?>" required>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">End Date & Time</label>
                                                    <input type="datetime-local" name="end_datetime" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($event['end_datetime'])); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-control">
                                                        <option value="upcoming" <?php echo $event['status'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                                        <option value="ongoing" <?php echo $event['status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                                        <option value="completed" <?php echo $event['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                        <option value="cancelled" <?php echo $event['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <div class="form-check mt-4">
                                                        <input type="checkbox" name="collect_feedback" class="form-check-input" id="collect_feedback<?php echo $event['event_id']; ?>" <?php echo $event['collect_feedback'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="collect_feedback<?php echo $event['event_id']; ?>">
                                                            Collect Feedback
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_event" class="btn btn-primary">Update Event</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h4>No Events Found</h4>
                    <p class="text-muted mb-4">Get started by creating your first event</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                        <i class="fas fa-plus-circle me-2"></i>Create New Event
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</div> <!-- Close dashboard-container -->

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Organization</label>
                            <select name="org_id" class="form-control" required>
                                <option value="">Select Organization</option>
                                <?php 
                                $orgs_result->data_seek(0);
                                while($org = $orgs_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $org['org_id']; ?>">
                                        <?php echo $org['org_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Title</label>
                            <input type="text" name="event_title" class="form-control" placeholder="e.g., Tech Talk 2026" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="event_description" class="form-control" rows="3" placeholder="Describe the event..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Type</label>
                            <select name="event_type" class="form-control">
                                <option value="workshop">Workshop</option>
                                <option value="meeting">Meeting</option>
                                <option value="social">Social</option>
                                <option value="fundraising">Fundraising</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Venue</label>
                            <input type="text" name="venue" class="form-control" placeholder="e.g., CS Lecture Hall" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date & Time</label>
                            <input type="datetime-local" name="start_datetime" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date & Time</label>
                            <input type="datetime-local" name="end_datetime" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="collect_feedback" class="form-check-input" id="collect_feedback" checked>
                            <label class="form-check-label" for="collect_feedback">
                                Collect feedback after event
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_event" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Delete Confirmation Script -->
<script>
function confirmDelete(eventId, eventTitle) {
    if (confirm(`Are you sure you want to delete "${eventTitle}"? This action cannot be undone.`)) {
        window.location.href = `events.php?delete=${eventId}`;
    }
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