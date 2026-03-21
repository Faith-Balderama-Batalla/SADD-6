<?php
// Admin/pending.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { header('Location: ../Student/dashboard.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $pending_id = intval($_POST['pending_id']);
        $target_id = intval($_POST['user_id']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        
        // Update pending record
        $stmt1 = $conn->prepare("UPDATE pending_officers SET status = 'approved', reviewed_by = ?, review_date = NOW() WHERE pending_id = ?");
        $stmt1->bind_param("ii", $user['user_id'], $pending_id);
        $stmt1->execute();
        $stmt1->close();
        
        // Update user role and status
        $stmt2 = $conn->prepare("UPDATE users SET role = ?, status = 'active' WHERE user_id = ?");
        $stmt2->bind_param("si", $role, $target_id);
        $stmt2->execute();
        $stmt2->close();
        
        // If class officer, create class record
        if ($role == 'class_officer') {
            $user_data_result = $conn->query("SELECT course, year_level, block FROM users WHERE user_id = $target_id");
            $user_data = $user_data_result->fetch_assoc();
            if ($user_data) {
                $stmt3 = $conn->prepare("INSERT INTO classes (course, year_level, block, class_officer_id, academic_year, semester) VALUES (?, ?, ?, ?, '2025-2026', '2nd')");
                $stmt3->bind_param("siii", $user_data['course'], $user_data['year_level'], $user_data['block'], $target_id);
                $stmt3->execute();
                $stmt3->close();
            }
        }
        
        // Create notification
        $message = "Your application to become " . str_replace('_', ' ', $role) . " has been approved!";
        $stmt4 = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, 'Application Approved', ?, 'system')");
        $stmt4->bind_param("is", $target_id, $message);
        $stmt4->execute();
        $stmt4->close();
        
        $success = "Application approved!";
    }
    if (isset($_POST['reject'])) {
        $pending_id = intval($_POST['pending_id']);
        $target_id = intval($_POST['user_id']);
        $reason = isset($_POST['reason']) ? mysqli_real_escape_string($conn, $_POST['reason']) : 'No reason provided';
        
        $stmt1 = $conn->prepare("UPDATE pending_officers SET status = 'rejected', reviewed_by = ?, review_date = NOW(), rejection_reason = ? WHERE pending_id = ?");
        $stmt1->bind_param("isi", $user['user_id'], $reason, $pending_id);
        $stmt1->execute();
        $stmt1->close();
        
        $stmt2 = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
        $stmt2->bind_param("i", $target_id);
        $stmt2->execute();
        $stmt2->close();
        
        $message = "Your application was not approved. Reason: $reason";
        $stmt3 = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, 'Application Update', ?, 'system')");
        $stmt3->bind_param("is", $target_id, $message);
        $stmt3->execute();
        $stmt3->close();
        
        $success = "Application rejected.";
    }
}

$pending = $conn->query("SELECT p.*, u.first_name, u.last_name, u.email, u.id_number FROM pending_officers p JOIN users u ON p.user_id = u.user_id WHERE p.status = 'pending' ORDER BY p.request_date ASC");
$notif_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE");
$notif_stmt->bind_param("i", $user['user_id']);
$notif_stmt->execute();
$unread_count = $notif_stmt->get_result()->fetch_assoc()['unread'] ?? 0;
$notif_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals - Univents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div><h2 class="mb-1">Pending Approvals</h2><p class="text-muted">Review officer applications</p></div>
            <?php if(isset($success)): ?><div class="alert alert-success mt-3"><?php echo $success; ?></div><?php endif; ?>
            <div class="card border-0 shadow-sm mt-4"><div class="card-body"><?php if($pending && $pending->num_rows > 0): ?><table class="table table-hover"><thead><tr><th>Name</th><th>ID Number</th><th>Email</th><th>Requested Role</th><th>Course/Year/Block</th><th>Request Date</th><th>Actions</th></tr></thead><tbody><?php while($p = $pending->fetch_assoc()): ?><tr><td><?php echo $p['first_name'].' '.$p['last_name']; ?></td><td><?php echo $p['id_number']; ?></td><td><?php echo $p['email']; ?></td><td><span class="badge bg-warning"><?php echo str_replace('_',' ',$p['requested_role']); ?></span></td><td><?php echo $p['course'].' '.$p['year_level'].$p['block']; ?></td><td><?php echo date('M d, Y', strtotime($p['request_date'])); ?></td><td><button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $p['pending_id']; ?>">Approve</button><button class="btn btn-sm btn-danger ms-1" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $p['pending_id']; ?>">Reject</button></td></tr>
<div class="modal fade" id="approveModal<?php echo $p['pending_id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Approve Application</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="pending_id" value="<?php echo $p['pending_id']; ?>"><input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>"><p>Approve <strong><?php echo $p['first_name'].' '.$p['last_name']; ?></strong> as <?php echo str_replace('_',' ',$p['requested_role']); ?>?</p><div class="mb-3"><label>Role</label><select name="role" class="form-control"><option value="class_officer" <?php echo $p['requested_role']=='class_officer'?'selected':''; ?>>Class Officer</option><option value="org_officer" <?php echo $p['requested_role']=='org_officer'?'selected':''; ?>>Organization Officer</option></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="approve" class="btn btn-success">Approve</button></div></form></div></div></div>
<div class="modal fade" id="rejectModal<?php echo $p['pending_id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Reject Application</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="pending_id" value="<?php echo $p['pending_id']; ?>"><input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>"><p>Reject <strong><?php echo $p['first_name'].' '.$p['last_name']; ?></strong>'s application?</p><div class="mb-3"><label>Reason (optional)</label><textarea name="reason" class="form-control" rows="3"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="reject" class="btn btn-danger">Reject</button></div></form></div></div></div>
<?php endwhile; ?></tbody></table><?php else: ?><div class="text-center py-5"><i class="fas fa-check-circle fa-4x text-success mb-3"></i><h4>No Pending Applications</h4><p class="text-muted">All caught up!</p></div><?php endif; ?></div></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar'), collapseBtn = document.getElementById('collapseBtn'), menuToggle = document.getElementById('menuToggle'), profileBtn = document.getElementById('profileBtn'), profileMenu = document.getElementById('profileMenu');
    if(collapseBtn) collapseBtn.addEventListener('click', function(){ sidebar.classList.toggle('collapsed'); const icon = this.querySelector('i'); if(sidebar.classList.contains('collapsed')){ icon.classList.remove('fa-chevron-left'); icon.classList.add('fa-chevron-right'); } else { icon.classList.remove('fa-chevron-right'); icon.classList.add('fa-chevron-left'); } });
    if(menuToggle) menuToggle.addEventListener('click', function(){ sidebar.classList.toggle('mobile-show'); });
    if(profileBtn) profileBtn.addEventListener('click', function(e){ e.stopPropagation(); profileMenu.classList.toggle('show'); });
    document.addEventListener('click', function(){ if(profileMenu) profileMenu.classList.remove('show'); });
});
</script>
</body>
</html>