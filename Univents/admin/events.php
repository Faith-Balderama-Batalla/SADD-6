<?php
// admin/events.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    if (isset($_POST['add_event'])) {
        $org_id = intval($_POST['org_id']);
        $title = mysqli_real_escape_string($conn, $_POST['event_title']);
        $desc = mysqli_real_escape_string($conn, $_POST['event_description']);
        $type = mysqli_real_escape_string($conn, $_POST['event_type']);
        $venue = mysqli_real_escape_string($conn, $_POST['venue']);
        $start = $_POST['start_datetime'];
        $end = $_POST['end_datetime'];
        $reg_required = isset($_POST['registration_required']) ? 1 : 0;
        $max_active = $reg_required ? intval($_POST['max_active_participants']) : null;
        
        $stmt = $conn->prepare("INSERT INTO events (org_id, event_title, event_description, event_type, venue, start_datetime, end_datetime, registration_required, max_active_participants, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssiii", $org_id, $title, $desc, $type, $venue, $start, $end, $reg_required, $max_active, $user['user_id']);
        
        if ($stmt->execute()) {
            $success = "Event created successfully!";
        } else {
            $error = "Failed to create event.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_event'])) {
        $id = intval($_POST['event_id']);
        $org_id = intval($_POST['org_id']);
        $title = mysqli_real_escape_string($conn, $_POST['event_title']);
        $desc = mysqli_real_escape_string($conn, $_POST['event_description']);
        $type = mysqli_real_escape_string($conn, $_POST['event_type']);
        $venue = mysqli_real_escape_string($conn, $_POST['venue']);
        $start = $_POST['start_datetime'];
        $end = $_POST['end_datetime'];
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $stmt = $conn->prepare("UPDATE events SET org_id=?, event_title=?, event_description=?, event_type=?, venue=?, start_datetime=?, end_datetime=?, status=? WHERE event_id=?");
        $stmt->bind_param("isssssssi", $org_id, $title, $desc, $type, $venue, $start, $end, $status, $id);
        
        if ($stmt->execute()) {
            $success = "Event updated successfully!";
        } else {
            $error = "Failed to update event.";
        }
        $stmt->close();
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && verifyCSRFToken($_GET['csrf_token'])) {
    $id = intval($_GET['delete']);
    
    // Check if event has attendance records
    $check = $conn->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE event_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $error = "Cannot delete event with attendance records. Cancel it instead.";
    } else {
        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Event deleted successfully!";
        } else {
            $error = "Failed to delete event.";
        }
        $stmt->close();
    }
    $check->close();
}

// Get organizations for dropdown
$orgs = $conn->query("SELECT org_id, org_name FROM organizations WHERE status = 'active' ORDER BY org_name");

// Search and filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$org_filter = isset($_GET['org_id']) ? intval($_GET['org_id']) : '';

$sql = "SELECT e.*, o.org_name, o.org_color,
        (SELECT COUNT(*) FROM attendance_logs WHERE event_id = e.event_id) as attendance_count,
        (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registration_count
        FROM events e 
        JOIN organizations o ON e.org_id = o.org_id 
        WHERE 1=1";
if ($search) {
    $sql .= " AND (e.event_title LIKE '%$search%' OR e.venue LIKE '%$search%')";
}
if ($status_filter) {
    $sql .= " AND e.status = '$status_filter'";
}
if ($org_filter) {
    $sql .= " AND e.org_id = $org_filter";
}
$sql .= " ORDER BY e.start_datetime DESC";

$events = $conn->query($sql);
$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Events - Univents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-calendar-alt me-2" style="color: var(--primary-mint);"></i>
                        Event Management
                    </h2>
                    <p class="text-muted">Manage all events across organizations</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
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
            
            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search events by title or venue..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="org_id" class="form-control">
                                <option value="">All Organizations</option>
                                <?php 
                                $orgs->data_seek(0);
                                while($o = $orgs->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $o['org_id']; ?>" <?php echo $org_filter == $o['org_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($o['org_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="upcoming" <?php echo $status_filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="ongoing" <?php echo $status_filter == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Events Grid -->
            <div class="row g-4">
                <?php if($events && $events->num_rows > 0): ?>
                    <?php while($e = $events->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-0 shadow-sm h-100">
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
                                                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($e['org_name']); ?>
                                            </p>
                                            <p class="text-muted small mb-1">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($e['venue']); ?>
                                            </p>
                                            <p class="text-muted small mb-3">
                                                <i class="far fa-clock me-1"></i><?php echo date('M d, Y g:i A', strtotime($e['start_datetime'])); ?>
                                            </p>
                                            
                                            <div class="d-flex gap-2 mb-3">
                                                <div class="bg-light rounded p-2 text-center flex-grow-1">
                                                    <small class="text-muted d-block">Attendees</small>
                                                    <strong><?php echo $e['attendance_count']; ?></strong>
                                                </div>
                                                <?php if($e['registration_required']): ?>
                                                    <div class="bg-light rounded p-2 text-center flex-grow-1">
                                                        <small class="text-muted d-block">Registered</small>
                                                        <strong><?php echo $e['registration_count']; ?> / <?php echo $e['max_active_participants'] ?? '∞'; ?></strong>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editEventModal<?php echo $e['event_id']; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $e['event_id']; ?>, '<?php echo addslashes($e['event_title']); ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                                <?php if($e['status'] == 'upcoming'): ?>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="cancelEvent(<?php echo $e['event_id']; ?>)">
                                                        <i class="fas fa-ban"></i> Cancel
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
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
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Organization</label>
                                                    <select name="org_id" class="form-control">
                                                        <?php 
                                                        $orgs->data_seek(0);
                                                        while($o = $orgs->fetch_assoc()): 
                                                        ?>
                                                            <option value="<?php echo $o['org_id']; ?>" <?php echo $e['org_id'] == $o['org_id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($o['org_name']); ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Event Title</label>
                                                    <input type="text" name="event_title" class="form-control" value="<?php echo htmlspecialchars($e['event_title']); ?>" required>
                                                </div>
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
                            <h4>No Events Found</h4>
                            <p class="text-muted">Click "Create Event" to add your first event.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Organization *</label>
                            <select name="org_id" class="form-control" required>
                                <option value="">Select Organization</option>
                                <?php 
                                $orgs->data_seek(0);
                                while($o = $orgs->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $o['org_id']; ?>"><?php echo htmlspecialchars($o['org_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Title *</label>
                            <input type="text" name="event_title" class="form-control" required>
                        </div>
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
                            <input type="number" name="max_active_participants" class="form-control" placeholder="Leave empty for unlimited" id="max_participants" disabled>
                            <small class="text-muted">Limit for active participant registration</small>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
function confirmDelete(id, name) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    if (confirm(`Delete "${name}"? This action cannot be undone.\n\nNote: Events with attendance records cannot be deleted.`)) {
        window.location.href = `events.php?delete=${id}&csrf_token=${csrfToken}`;
    }
}

function cancelEvent(id) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    if (confirm('Cancel this event? It will be marked as cancelled.')) {
        // Create a hidden form to update status
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <input type="hidden" name="event_id" value="${id}">
            <input type="hidden" name="org_id" value="">
            <input type="hidden" name="event_title" value="">
            <input type="hidden" name="event_description" value="">
            <input type="hidden" name="event_type" value="">
            <input type="hidden" name="venue" value="">
            <input type="hidden" name="start_datetime" value="">
            <input type="hidden" name="end_datetime" value="">
            <input type="hidden" name="status" value="cancelled">
            <input type="hidden" name="update_event" value="1">
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