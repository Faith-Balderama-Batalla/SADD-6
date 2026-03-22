<?php
// admin/notifications.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Mark all as read
if (isset($_POST['mark_all_read']) && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $stmt->close();
    $success = "All notifications marked as read.";
}

// Mark single as read
if (isset($_GET['mark_read']) && isset($_GET['csrf_token']) && verifyCSRFToken($_GET['csrf_token'])) {
    $id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user['user_id']);
    $stmt->execute();
    $stmt->close();
    header('Location: notifications.php');
    exit();
}

// Delete notification
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && verifyCSRFToken($_GET['csrf_token'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user['user_id']);
    $stmt->execute();
    $stmt->close();
    $success = "Notification deleted.";
}

// Get notifications
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : 'all';
$sql = "SELECT * FROM notifications WHERE user_id = ?";
if ($filter == 'unread') {
    $sql .= " AND is_read = 0";
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$notifications = $stmt->get_result();

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Notifications - Univents Admin</title>
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
                        <i class="fas fa-bell me-2" style="color: var(--primary-mint);"></i>
                        Notifications
                    </h2>
                    <p class="text-muted">View and manage your notifications</p>
                </div>
                <div>
                    <?php if($unread_count > 0): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                                <i class="fas fa-check-double me-2"></i>Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Filter Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter == 'all' ? 'active' : ''; ?>" href="?filter=all">
                        All <span class="badge bg-secondary ms-1"><?php echo $notifications->num_rows; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter == 'unread' ? 'active' : ''; ?>" href="?filter=unread">
                        Unread <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
                    </a>
                </li>
            </ul>
            
            <!-- Notifications List -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if($notifications->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while($n = $notifications->fetch_assoc()): ?>
                                <div class="list-group-item <?php echo !$n['is_read'] ? 'bg-light' : ''; ?>">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="notification-icon p-2 rounded-circle" style="background: rgba(79, 209, 197, 0.1);">
                                            <i class="fas fa-<?php 
                                                echo $n['notification_type'] == 'event' ? 'calendar-alt' : 
                                                     ($n['notification_type'] == 'announcement' ? 'bullhorn' : 
                                                     ($n['notification_type'] == 'attendance' ? 'check-circle' : 'bell'));
                                            ?>" style="color: var(--primary-mint);"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1 <?php echo !$n['is_read'] ? 'fw-bold' : ''; ?>">
                                                        <?php echo htmlspecialchars($n['title']); ?>
                                                    </h6>
                                                    <p class="mb-1 text-muted small"><?php echo htmlspecialchars($n['message']); ?></p>
                                                    <small class="text-muted">
                                                        <i class="far fa-clock me-1"></i>
                                                        <?php echo date('F j, Y g:i A', strtotime($n['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <?php if(!$n['is_read']): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="?mark_read=<?php echo $n['notification_id']; ?>&csrf_token=<?php echo $csrf_token; ?>">
                                                                    <i class="fas fa-check me-2"></i>Mark as Read
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="?delete=<?php echo $n['notification_id']; ?>&csrf_token=<?php echo $csrf_token; ?>" onclick="return confirm('Delete this notification?')">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                            <h5>No notifications</h5>
                            <p class="text-muted">You're all caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>