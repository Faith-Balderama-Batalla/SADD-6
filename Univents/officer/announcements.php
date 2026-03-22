<?php
// officer/announcements.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'org_officer') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Get officer's organization
$org_stmt = $conn->prepare("
    SELECT o.org_id, o.org_name 
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

// Handle announcement actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    if (isset($_POST['create_announcement'])) {
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $content = mysqli_real_escape_string($conn, $_POST['content']);
        $type = mysqli_real_escape_string($conn, $_POST['announcement_type']);
        $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
        $pinned_until = $is_pinned ? date('Y-m-d H:i:s', strtotime('+7 days')) : null;
        
        $stmt = $conn->prepare("INSERT INTO announcements (org_id, title, content, announcement_type, is_pinned, pinned_until, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssisi", $org['org_id'], $title, $content, $type, $is_pinned, $pinned_until, $user['user_id']);
        
        if ($stmt->execute()) {
            $success = "Announcement posted successfully!";
            
            // Notify all organization members
            $members = $conn->prepare("SELECT user_id FROM organization_memberships WHERE org_id = ? AND status = 'active'");
            $members->bind_param("i", $org['org_id']);
            $members->execute();
            $members_result = $members->get_result();
            
            while($member = $members_result->fetch_assoc()) {
                createNotification($conn, $member['user_id'], 'New Announcement', $title . ' - ' . substr($content, 0, 100), 'announcement', $stmt->insert_id);
            }
            $members->close();
        } else {
            $error = "Failed to post announcement.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_announcement'])) {
        $id = intval($_POST['announcement_id']);
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $content = mysqli_real_escape_string($conn, $_POST['content']);
        $type = mysqli_real_escape_string($conn, $_POST['announcement_type']);
        $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
        $pinned_until = $is_pinned ? date('Y-m-d H:i:s', strtotime('+7 days')) : null;
        
        $stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, announcement_type=?, is_pinned=?, pinned_until=? WHERE announcement_id=? AND org_id=?");
        $stmt->bind_param("sssisi", $title, $content, $type, $is_pinned, $pinned_until, $id, $org['org_id']);
        
        if ($stmt->execute()) {
            $success = "Announcement updated successfully!";
        } else {
            $error = "Failed to update announcement.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['delete_announcement'])) {
        $id = intval($_POST['announcement_id']);
        
        $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id=? AND org_id=?");
        $stmt->bind_param("ii", $id, $org['org_id']);
        
        if ($stmt->execute()) {
            $success = "Announcement deleted successfully!";
        } else {
            $error = "Failed to delete announcement.";
        }
        $stmt->close();
    }
}

// Get announcements
$announcements = $conn->prepare("
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a 
    JOIN users u ON a.created_by = u.user_id 
    WHERE a.org_id = ? 
    ORDER BY a.is_pinned DESC, a.created_at DESC
");
$announcements->bind_param("i", $org['org_id']);
$announcements->execute();
$announcements_result = $announcements->get_result();

// Get announcement types for filtering
$types = ['general', 'event_update', 'merchandise', 'urgent'];

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Announcements - Univents Officer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .announcement-card {
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .announcement-card.pinned {
            border-left: 4px solid #F6AD55;
            background: rgba(246, 173, 85, 0.05);
        }
        .announcement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .announcement-type-badge {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        .announcement-content {
            max-height: 150px;
            overflow-y: auto;
        }
        .announcement-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .announcement-card:hover .announcement-actions {
            opacity: 1;
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
                        <i class="fas fa-bullhorn me-2" style="color: var(--primary-mint);"></i>
                        Announcements
                    </h2>
                    <p class="text-muted">Manage announcements for <?php echo htmlspecialchars($org['org_name']); ?></p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="fas fa-plus-circle me-2"></i>Post Announcement
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
            
            <!-- Announcements List -->
            <div class="announcements-list">
                <?php if($announcements_result->num_rows > 0): ?>
                    <?php while($a = $announcements_result->fetch_assoc()): ?>
                        <div class="card border-0 shadow-sm announcement-card <?php echo $a['is_pinned'] ? 'pinned' : ''; ?> position-relative">
                            <div class="card-body">
                                <div class="announcement-type-badge">
                                    <span class="badge bg-<?php 
                                        echo $a['announcement_type'] == 'urgent' ? 'danger' : 
                                             ($a['announcement_type'] == 'event_update' ? 'info' : 
                                             ($a['announcement_type'] == 'merchandise' ? 'warning' : 'secondary')); 
                                    ?>">
                                        <?php echo strtoupper(str_replace('_', ' ', $a['announcement_type'])); ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex align-items-start gap-3">
                                    <div class="user-avatar-mini" style="width: 45px; height: 45px;">
                                        <?php echo strtoupper(substr($a['first_name'],0,1) . substr($a['last_name'],0,1)); ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-1">
                                                    <?php if($a['is_pinned']): ?>
                                                        <i class="fas fa-thumbtack text-warning me-2"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($a['title']); ?>
                                                </h5>
                                                <p class="text-muted small mb-2">
                                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?>
                                                    <i class="fas fa-clock ms-3 me-1"></i><?php echo date('F j, Y g:i A', strtotime($a['created_at'])); ?>
                                                </p>
                                            </div>
                                            <div class="announcement-actions">
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal<?php echo $a['announcement_id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $a['announcement_id']; ?>, '<?php echo addslashes($a['title']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="announcement-content">
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($a['content'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Announcement Modal -->
                        <div class="modal fade" id="editAnnouncementModal<?php echo $a['announcement_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Announcement</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="announcement_id" value="<?php echo $a['announcement_id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Title</label>
                                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($a['title']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Content</label>
                                                <textarea name="content" class="form-control" rows="5" required><?php echo htmlspecialchars($a['content']); ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Announcement Type</label>
                                                    <select name="announcement_type" class="form-control">
                                                        <option value="general" <?php echo $a['announcement_type'] == 'general' ? 'selected' : ''; ?>>General</option>
                                                        <option value="event_update" <?php echo $a['announcement_type'] == 'event_update' ? 'selected' : ''; ?>>Event Update</option>
                                                        <option value="merchandise" <?php echo $a['announcement_type'] == 'merchandise' ? 'selected' : ''; ?>>Merchandise</option>
                                                        <option value="urgent" <?php echo $a['announcement_type'] == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <div class="form-check mt-4">
                                                        <input type="checkbox" name="is_pinned" class="form-check-input" id="edit_pinned_<?php echo $a['announcement_id']; ?>" <?php echo $a['is_pinned'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="edit_pinned_<?php echo $a['announcement_id']; ?>">
                                                            Pin this announcement
                                                        </label>
                                                        <small class="d-block text-muted">Pinned announcements appear at the top</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_announcement" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                        <h4>No Announcements Yet</h4>
                        <p class="text-muted">Create your first announcement to keep members updated.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                            <i class="fas fa-plus-circle me-2"></i>Post Announcement
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" placeholder="Enter announcement title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="5" placeholder="Write your announcement here..." required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Announcement Type</label>
                            <select name="announcement_type" class="form-control">
                                <option value="general">General</option>
                                <option value="event_update">Event Update</option>
                                <option value="merchandise">Merchandise</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="is_pinned" class="form-check-input" id="pin_announcement">
                                <label class="form-check-label" for="pin_announcement">
                                    Pin this announcement
                                </label>
                                <small class="d-block text-muted">Pinned announcements appear at the top for 7 days</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_announcement" class="btn btn-primary">Post Announcement</button>
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
    if (confirm(`Delete announcement "${name}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <input type="hidden" name="announcement_id" value="${id}">
            <input type="hidden" name="delete_announcement" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
</body>
</html>