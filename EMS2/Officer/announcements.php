<?php
// Officer/announcements.php
require_once '../config.php';
requireLogin();

$user = getCurrentUser($conn);
if ($user['role'] !== 'org_officer') {
    header('Location: ../Student/dashboard.php');
    exit();
}

// Get organization for this officer
$org = $conn->query("SELECT o.* FROM organizations o 
                     JOIN organization_officers oo ON o.org_id = oo.org_id 
                     WHERE oo.user_id = {$user['user_id']} AND oo.status = 'active'")->fetch_assoc();

if (!$org) {
    die("You are not assigned to any organization.");
}

// Handle create announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $type = mysqli_real_escape_string($conn, $_POST['announcement_type']);
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO announcements (org_id, title, content, announcement_type, is_pinned, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssii", $org['org_id'], $title, $content, $type, $is_pinned, $user['user_id']);
    
    if ($stmt->execute()) {
        $success = "Announcement posted successfully!";
    } else {
        $error = "Failed to post announcement.";
    }
    $stmt->close();
}

// Handle delete announcement
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ? AND org_id = ?");
    $stmt->bind_param("ii", $id, $org['org_id']);
    $stmt->execute();
    $success = "Announcement deleted!";
    $stmt->close();
}

// Get announcements
$announcements = $conn->query("SELECT * FROM announcements WHERE org_id = {$org['org_id']} ORDER BY is_pinned DESC, created_at DESC");

// Get unread notifications
$notif_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE");
$notif_stmt->bind_param("i", $user['user_id']);
$notif_stmt->execute();
$unread_count = $notif_stmt->get_result()->fetch_assoc()['unread'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements - Univents Officer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_officer.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Announcements</h2>
                    <p class="text-muted">Manage announcements for <?php echo $org['org_name']; ?></p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="fas fa-plus-circle me-2"></i>Post Announcement
                </button>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="row g-4">
                <?php if($announcements->num_rows > 0): ?>
                    <?php while($a = $announcements->fetch_assoc()): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm <?php if($a['is_pinned']): ?>border-start border-4 border-primary<?php endif; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <h5 class="mb-0"><?php echo htmlspecialchars($a['title']); ?></h5>
                                                <?php if($a['is_pinned']): ?>
                                                    <span class="badge bg-primary"><i class="fas fa-thumbtack me-1"></i>Pinned</span>
                                                <?php endif; ?>
                                                <span class="badge bg-secondary"><?php echo ucfirst($a['announcement_type']); ?></span>
                                            </div>
                                            <p class="text-muted small mb-2">
                                                <i class="far fa-calendar me-1"></i>Posted on <?php echo date('F j, Y g:i A', strtotime($a['created_at'])); ?>
                                            </p>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($a['content'])); ?></p>
                                        </div>
                                        <div class="ms-3">
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $a['announcement_id']; ?>, '<?php echo addslashes($a['title']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                        <h4>No Announcements Yet</h4>
                        <p class="text-muted">Post your first announcement to engage with members.</p>
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
                    <h5 class="modal-title">Post New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Announcement Type</label>
                            <select name="announcement_type" class="form-control">
                                <option value="general">General</option>
                                <option value="event_update">Event Update</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="is_pinned" class="form-check-input" id="is_pinned">
                                <label class="form-check-label" for="is_pinned">
                                    Pin this announcement
                                </label>
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
<script>
function confirmDelete(id, title) {
    if(confirm(`Delete announcement "${title}"? This cannot be undone.`)) {
        window.location.href = `announcements.php?delete=${id}`;
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar'), collapseBtn = document.getElementById('collapseBtn'), menuToggle = document.getElementById('menuToggle'), profileBtn = document.getElementById('profileBtn'), profileMenu = document.getElementById('profileMenu'), notificationBtn = document.getElementById('notificationBtn'), notificationMenu = document.getElementById('notificationMenu');
    if(collapseBtn) collapseBtn.addEventListener('click', function(){ sidebar.classList.toggle('collapsed'); const icon = this.querySelector('i'); if(sidebar.classList.contains('collapsed')){ icon.classList.remove('fa-chevron-left'); icon.classList.add('fa-chevron-right'); } else { icon.classList.remove('fa-chevron-right'); icon.classList.add('fa-chevron-left'); } });
    if(menuToggle) menuToggle.addEventListener('click', function(){ sidebar.classList.toggle('mobile-show'); });
    if(profileBtn) profileBtn.addEventListener('click', function(e){ e.stopPropagation(); profileMenu.classList.toggle('show'); if(notificationMenu) notificationMenu.classList.remove('show'); });
    if(notificationBtn) notificationBtn.addEventListener('click', function(e){ e.stopPropagation(); notificationMenu.classList.toggle('show'); if(profileMenu) profileMenu.classList.remove('show'); });
    document.addEventListener('click', function(){ if(profileMenu) profileMenu.classList.remove('show'); if(notificationMenu) notificationMenu.classList.remove('show'); });
});
</script>
</body>
</html>