<?php
// officer/events.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'org_officer') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Get officer's organization
$org_stmt = $conn->prepare("
    SELECT o.org_id, o.org_name, o.org_color 
    FROM organizations o 
    JOIN organization_officers oo ON o.org_id = oo.org_id 
    WHERE oo.user_id = ? AND oo.status = 'active'
    LIMIT 1
");
$org_stmt->bind_param("i", $user['user_id']);
$org_stmt->execute();
$org = $org_stmt->get_result()->fetch_assoc();

if (!$org) {
    die("You are not assigned to any organization.");
}

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    if (isset($_POST['create_event'])) {
        $title = mysqli_real_escape_string($conn, $_POST['event_title']);
        $desc = mysqli_real_escape_string($conn, $_POST['event_description']);
        $type = mysqli_real_escape_string($conn, $_POST['event_type']);
        $venue = mysqli_real_escape_string($conn, $_POST['venue']);
        $start = $_POST['start_datetime'];
        $end = $_POST['end_datetime'];
        $reg_required = isset($_POST['registration_required']) ? 1 : 0;
        $max_active = $reg_required ? intval($_POST['max_active_participants']) : null;
        
        $stmt = $conn->prepare("INSERT INTO events (org_id, event_title, event_description, event_type, venue, start_datetime, end_datetime, registration_required, max_active_participants, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssiii", $org['org_id'], $title, $desc, $type, $venue, $start, $end, $reg_required, $max_active, $user['user_id']);
        
        if ($stmt->execute()) {
            $event_id = $stmt->insert_id;
            
            // Generate QR code for the event
            $qr_string = generateQRString($event_id);
            $valid_from = date('Y-m-d H:i:s', strtotime($start) - 7200); // 2 hours before
            $valid_until = date('Y-m-d H:i:s', strtotime($end) + 7200); // 2 hours after
            
            $qr_stmt = $conn->prepare("INSERT INTO qr_codes (event_id, qr_string, valid_from, valid_until) VALUES (?, ?, ?, ?)");
            $qr_stmt->bind_param("isss", $event_id, $qr_string, $valid_from, $valid_until);
            $qr_stmt->execute();
            $qr_stmt->close();
            
            $success = "Event created successfully!";
        } else {
            $error = "Failed to create event.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_event'])) {
        $id = intval($_POST['event_id']);
        $title = mysqli_real_escape_string($conn, $_POST['event_title']);
        $desc = mysqli_real_escape_string($conn, $_POST['event_description']);
        $type = mysqli_real_escape_string($conn, $_POST['event_type']);
        $venue = mysqli_real_escape_string($conn, $_POST['venue']);
        $start = $_POST['start_datetime'];
        $end = $_POST['end_datetime'];
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $stmt = $conn->prepare("UPDATE events SET event_title=?, event_description=?, event_type=?, venue=?, start_datetime=?, end_datetime=?, status=? WHERE event_id=? AND org_id=?");
        $stmt->bind_param("sssssssii", $title, $desc, $type, $venue, $start, $end, $status, $id, $org['org_id']);
        
        if ($stmt->execute()) {
            $success = "Event updated successfully!";
        } else {
            $error = "Failed to update event.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['delete_event'])) {
        $id = intval($_POST['event_id']);
        
        // Check if event has attendance
        $check = $conn->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE event_id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $error = "Cannot delete event with attendance records. Cancel it instead.";
        } else {
            $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ? AND org_id = ?");
            $stmt->bind_param("ii", $id, $org['org_id']);
            if ($stmt->execute()) {
                $success = "Event deleted successfully!";
            } else {
                $error = "Failed to delete event.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Get events
$events = $conn->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM attendance_logs WHERE event_id = e.event_id) as attendance_count,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registration_count
    FROM events e 
    WHERE e.org_id = ? 
    ORDER BY e.start_datetime DESC
");
$events->bind_param("i", $org['org_id']);
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
    <title>My Events - Univents Officer</title>
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
        .event-stats {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        .event-stat {
            text-align: center;
            flex: 1;
        }
        .event-stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-mint);
        }
        .event-stat-label {
            font-size: 0.7rem;
            color: var(--text-light);
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_officer.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-calendar-alt me-2" style="color: var(--primary-mint);"></i>
                        My Events
                    </h2>
                    <p class="text-muted">Manage events for <?php echo htmlspecialchars($org['org_name']); ?></p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal">
                    <i class="fas fa-plus-circle me-2"></i>Create Event
                </button>
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
            
            <!-- Events Grid -->
            <div class="row g-4">
                <?php if($events_result->num_rows > 0): ?>
                    <?php while($e = $events_result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-0 shadow-sm event-card h-100">
                                <div class="card-body">
                                    <div class="d-flex gap-3 mb-3">
                                        <div class="event-date-badge">
                                            <div class="day"><?php echo date('d', strtotime($e['start_datetime'])); ?></div>
                                            <div class="month"><?php echo date('M', strtotime($e['start_datetime'])); ?></div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h5 class="mb-1"><?php echo htmlspecialchars($e['event_title']); ?></h5>
                                                <span class="badge bg-<?php 
                                                    echo $e['status']=='upcoming' ? 'success' : 
                                                         ($e['status']=='ongoing' ? 'warning' : 
                                                         ($e['status']=='completed' ? 'info' : 'danger')); 
                                                ?>">
                                                    <?php echo $e['status']; ?>
                                                </span>
                                            </div>
                                            <p class="text-muted small mb-1">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($e['venue']); ?>
                                            </p>
                                            <p class="text-muted small">
                                                <i class="far fa-clock me-1"></i><?php echo date('M d, Y g:i A', strtotime($e['start_datetime'])); ?>
                                            </p>
                                            
                                            <div class="event-stats">
                                                <div class="event-stat">
                                                    <div class="event-stat-value"><?php echo $e['attendance_count']; ?></div>
                                                    <div class="event-stat-label">Attended</div>
                                                </div>
                                                <?php if($e['registration_required']): ?>
                                                    <div class="event-stat">
                                                        <div class="event-stat-value"><?php echo $e['registration_count']; ?> / <?php echo $e['max_active_participants'] ?? '∞'; ?></div>
                                                        <div class="event-stat-label">Registered</div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <a href="qr-view.php?event_id=<?php echo $e['event_id']; ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
                                            <i class="fas fa-qrcode me-1"></i> QR Code
                                        </a>
                                        <a href="attendance.php?event_id=<?php echo $e['event_id']; ?>" class="btn btn-sm btn-outline-success flex-grow-1">
                                            <i class="fas fa-check-circle me-1"></i> Attendance
                                        </a>
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editEventModal<?php echo $e['event_id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $e['event_id']; ?>, '<?php echo addslashes($e['event_title']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Event Modal -->
                        <div class="modal fade" id="editEventModal<?php echo $e['event_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Event</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="event_id" value="<?php echo $e['event_id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Event Title</label>
                                                <input type="text" name="event_title" class="form-control" value="<?php echo htmlspecialchars($e['event_title']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea name="event_description" class="form-control" rows="3"><?php echo htmlspecialchars($e['event_description']); ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Event Type</label>
                                                    <select name="event_type" class="form-control">
                                                        <option value="workshop" <?php echo $e['event_type'] == 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                                        <option value="seminar" <?php echo $e['event_type'] == 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                                                        <option value="meeting" <?php echo $e['event_type'] == 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                                                        <option value="social" <?php echo $e['event_type'] == 'social' ? 'selected' : ''; ?>>Social</option>
                                                        <option value="fundraising" <?php echo $e['event_type'] == 'fundraising' ? 'selected' : ''; ?>>Fundraising</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Venue</label>
                                                    <input type="text" name="venue" class="form-control" value="<?php echo htmlspecialchars($e['venue']); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Start Date & Time</label>
                                                    <input type="datetime-local" name="start_datetime" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($e['start_datetime'])); ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">End Date & Time</label>
                                                    <input type="datetime-local" name="end_datetime" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($e['end_datetime'])); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-control">
                                                    <option value="upcoming" <?php echo $e['status'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                                    <option value="ongoing" <?php echo $e['status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                                    <option value="completed" <?php echo $e['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $e['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_event" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <h4>No Events Yet</h4>
                            <p class="text-muted">Create your first event to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal">
                                <i class="fas fa-plus-circle me-2"></i>Create Event
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Event Modal -->
<div class="modal fade" id="createEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Event Title *</label>
                        <input type="text" name="event_title" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="event_description" class="form-control" rows="3" placeholder="Describe the event, its purpose, and what attendees can expect..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Type</label>
                            <select name="event_type" class="form-control">
                                <option value="workshop">Workshop</option>
                                <option value="seminar">Seminar</option>
                                <option value="meeting">Meeting</option>
                                <option value="social">Social</option>
                                <option value="fundraising">Fundraising</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Venue *</label>
                            <input type="text" name="venue" class="form-control" placeholder="e.g., University Auditorium, Room 201" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date & Time *</label>
                            <input type="datetime-local" name="start_datetime" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date & Time *</label>
                            <input type="datetime-local" name="end_datetime" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="registration_required" class="form-check-input" id="reg_required">
                                <label class="form-check-label" for="reg_required">
                                    Require Registration for Active Participants
                                </label>
                            </div>
                            <small class="text-muted">Only active participants need to register. All students can attend.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Active Participants</label>
                            <input type="number" name="max_active_participants" class="form-control" id="max_participants" placeholder="Leave empty for unlimited" disabled>
                            <small class="text-muted">Limit for active participant registration</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_event" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
function confirmDelete(id, name) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    if (confirm(`Delete "${name}"? This action cannot be undone.\n\nNote: Events with attendance records cannot be deleted.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <input type="hidden" name="event_id" value="${id}">
            <input type="hidden" name="delete_event" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Toggle max participants field
const regRequired = document.getElementById('reg_required');
const maxParticipants = document.getElementById('max_participants');

if (regRequired) {
    regRequired.addEventListener('change', function() {
        maxParticipants.disabled = !this.checked;
        if (!this.checked) {
            maxParticipants.value = '';
        }
    });
}
</script>
</body>
</html>