<?php
// Student/organizations.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

// Handle join organization
if (isset($_GET['join'])) {
    $org_id = intval($_GET['join']);
    
    // Check if already a member
    $check = $conn->prepare("SELECT * FROM organization_memberships WHERE user_id = ? AND org_id = ?");
    $check->bind_param("ii", $user['user_id'], $org_id);
    $check->execute();
    
    if ($check->get_result()->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO organization_memberships (user_id, org_id, status) VALUES (?, ?, 'pending')");
        $insert->bind_param("ii", $user['user_id'], $org_id);
        $insert->execute();
        $success = "Membership request submitted!";
    } else {
        $error = "You are already a member or have a pending request.";
    }
}

// Handle leave organization
if (isset($_GET['leave'])) {
    $org_id = intval($_GET['leave']);
    $update = $conn->prepare("UPDATE organization_memberships SET status = 'inactive' WHERE user_id = ? AND org_id = ?");
    $update->bind_param("ii", $user['user_id'], $org_id);
    $update->execute();
    $success = "You have left the organization.";
}

// Get my organizations
$my_orgs = $conn->prepare("SELECT o.*, om.status as membership_status FROM organizations o JOIN organization_memberships om ON o.org_id = om.org_id WHERE om.user_id = ? ORDER BY o.org_name ASC");
$my_orgs->bind_param("i", $user['user_id']);
$my_orgs->execute();
$my_organizations = $my_orgs->get_result();

// Get available organizations
$available = $conn->prepare("SELECT o.*, (SELECT COUNT(*) FROM organization_memberships WHERE org_id = o.org_id AND status = 'active') as member_count FROM organizations o WHERE o.status = 'active' AND o.org_id NOT IN (SELECT org_id FROM organization_memberships WHERE user_id = ?) ORDER BY o.org_name ASC");
$available->bind_param("i", $user['user_id']);
$available->execute();
$available_orgs = $available->get_result();

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
    <title>Organizations - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div><h2 class="mb-1">Organizations</h2><p class="text-muted">Discover and join student organizations</p></div>
            
            <?php if(isset($success)): ?><div class="alert alert-success mt-3"><?php echo $success; ?></div><?php endif; ?>
            <?php if(isset($error)): ?><div class="alert alert-danger mt-3"><?php echo $error; ?></div><?php endif; ?>
            
            <!-- My Organizations -->
            <?php if($my_organizations->num_rows > 0): ?>
                <h4 class="mt-4 mb-3">My Organizations</h4>
                <div class="row g-4">
                    <?php while($org = $my_organizations->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div style="width: 50px; height: 50px; background: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h5 class="mb-0"><?php echo $org['org_name']; ?></h5>
                                            <span class="badge bg-<?php echo $org['membership_status'] == 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($org['membership_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-muted small"><?php echo substr($org['org_description'] ?? 'No description', 0, 100); ?></p>
                                    <div class="d-flex gap-2 mt-3">
                                        <a href="organization.php?id=<?php echo $org['org_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php if($org['membership_status'] == 'active'): ?>
                                            <a href="?leave=<?php echo $org['org_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Leave this organization?')">Leave</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
            
            <!-- Available Organizations -->
            <h4 class="mt-5 mb-3">Discover Organizations</h4>
            <div class="row g-4">
                <?php if($available_orgs->num_rows > 0): ?>
                    <?php while($org = $available_orgs->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div style="width: 50px; height: 50px; background: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h5 class="mb-0"><?php echo $org['org_name']; ?></h5>
                                            <small class="text-muted"><i class="fas fa-users me-1"></i><?php echo $org['member_count']; ?> members</small>
                                        </div>
                                    </div>
                                    <p class="text-muted small"><?php echo substr($org['org_description'] ?? 'No description', 0, 100); ?></p>
                                    <a href="?join=<?php echo $org['org_id']; ?>" class="btn btn-sm btn-primary w-100">Join Organization</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4>You've joined all organizations!</h4>
                        <p class="text-muted">Check back later for new organizations.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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