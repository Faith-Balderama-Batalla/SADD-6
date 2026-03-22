<?php
// student/announcements.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

// Get announcements from user's organizations
$announcements = $conn->prepare("
    SELECT a.*, o.org_name, o.org_color, u.first_name, u.last_name
    FROM announcements a 
    JOIN organizations o ON a.org_id = o.org_id 
    JOIN organization_memberships om ON o.org_id = om.org_id 
    JOIN users u ON a.created_by = u.user_id
    WHERE om.user_id = ? AND om.status = 'active'
    ORDER BY a.is_pinned DESC, a.created_at DESC
");
$announcements->bind_param("i", $user['user_id']);
$announcements->execute();
$announcements_result = $announcements->get_result();

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Announcements - Univents</title>
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
        .announcement-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .type-urgent {
            background: #F56565;
            color: white;
        }
        .type-event_update {
            background: #4299E1;
            color: white;
        }
        .type-merchandise {
            background: #F6AD55;
            color: white;
        }
        .type-general {
            background: #718096;
            color: white;
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="mb-4">
                <h2 class="mb-1">
                    <i class="fas fa-bullhorn me-2" style="color: var(--primary-mint);"></i>
                    Announcements
                </h2>
                <p class="text-muted">Latest updates from your organizations</p>
            </div>
            
            <div class="announcements-list">
                <?php if($announcements_result->num_rows > 0): ?>
                    <?php while($a = $announcements_result->fetch_assoc()): ?>
                        <div class="card border-0 shadow-sm announcement-card <?php echo $a['is_pinned'] ? 'pinned' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="user-avatar-mini" style="width: 50px; height: 50px; background: <?php echo $a['org_color'] ?? '#4FD1C5'; ?>;">
                                        <?php echo strtoupper(substr($a['org_name'],0,2)); ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <h5 class="mb-1">
                                                    <?php if($a['is_pinned']): ?>
                                                        <i class="fas fa-thumbtack text-warning me-2"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($a['title']); ?>
                                                </h5>
                                                <div class="mb-2">
                                                    <span class="announcement-type type-<?php echo $a['announcement_type']; ?>">
                                                        <?php echo strtoupper(str_replace('_', ' ', $a['announcement_type'])); ?>
                                                    </span>
                                                </div>
                                                <p class="text-muted small mb-2">
                                                    <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($a['org_name']); ?>
                                                    <i class="fas fa-user ms-3 me-1"></i><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?>
                                                    <i class="far fa-clock ms-3 me-1"></i><?php echo date('F j, Y g:i A', strtotime($a['created_at'])); ?>
                                                </p>
                                                <div class="announcement-content">
                                                    <?php echo nl2br(htmlspecialchars($a['content'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                        <h4>No Announcements Yet</h4>
                        <p class="text-muted">Check back later for updates from your organizations.</p>
                        <a href="organizations.php" class="btn btn-primary">
                            <i class="fas fa-building me-2"></i>Browse Organizations
                        </a>
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