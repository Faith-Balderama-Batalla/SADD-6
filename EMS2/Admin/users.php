<?php
// Admin/users.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { header('Location: ../Student/dashboard.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_user'])) {
        $target_id = intval($_POST['user_id']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $stmt = $conn->prepare("UPDATE users SET role = ?, status = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $role, $status, $target_id);
        $stmt->execute();
        $success = "User updated successfully!";
    }
    if (isset($_POST['delete_user'])) {
        $target_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $success = "User deleted successfully!";
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
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
    <title>User Management - Univents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">User Management</h2><p class="text-muted">Manage all system users</p></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fas fa-user-plus me-2"></i>Add User</button></div>
            <?php if(isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <div class="card border-0 shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>ID</th><th>Name</th><th>ID Number</th><th>Email</th><th>Course/Year/Block</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php while($u = $users->fetch_assoc()): ?><tr><td><?php echo $u['user_id']; ?></td><td><?php echo $u['first_name'].' '.$u['last_name']; ?></td><td><?php echo $u['id_number']; ?></td><td><?php echo $u['email']; ?></td><td><?php echo $u['course'].' '.$u['year_level'].$u['block']; ?></td><td><span class="badge bg-<?php echo $u['role']=='admin'?'danger':($u['role']=='org_officer'?'primary':($u['role']=='class_officer'?'warning':'secondary')); ?>"><?php echo str_replace('_',' ',$u['role']); ?></span></td><td><span class="badge bg-<?php echo $u['status']=='active'?'success':'secondary'; ?>"><?php echo $u['status']; ?></span></td><td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $u['user_id']; ?>"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo $u['user_id']; ?>"><i class="fas fa-trash"></i></button></td></tr>
<div class="modal fade" id="editUserModal<?php echo $u['user_id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>"><div class="mb-3"><label>Role</label><select name="role" class="form-control"><option value="student_member" <?php echo $u['role']=='student_member'?'selected':''; ?>>Student</option><option value="class_officer" <?php echo $u['role']=='class_officer'?'selected':''; ?>>Class Officer</option><option value="org_officer" <?php echo $u['role']=='org_officer'?'selected':''; ?>>Org Officer</option><option value="admin" <?php echo $u['role']=='admin'?'selected':''; ?>>Admin</option></select></div><div class="mb-3"><label>Status</label><select name="status" class="form-control"><option value="active" <?php echo $u['status']=='active'?'selected':''; ?>>Active</option><option value="inactive" <?php echo $u['status']=='inactive'?'selected':''; ?>>Inactive</option><option value="pending" <?php echo $u['status']=='pending'?'selected':''; ?>>Pending</option></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="update_user" class="btn btn-primary">Save</button></div></form></div></div></div>
<div class="modal fade" id="deleteUserModal<?php echo $u['user_id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title text-danger">Delete User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>"><p>Are you sure you want to delete <strong><?php echo $u['first_name'].' '.$u['last_name']; ?></strong>? This action cannot be undone.</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="delete_user" class="btn btn-danger">Delete</button></div></form></div></div></div>
<?php endwhile; ?></tbody></table></div></div></div>
        </div>
    </div>
</div>
<div class="modal fade" id="addUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="add_user.php"><div class="modal-header"><h5 class="modal-title">Add New User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><input type="text" name="id_number" class="form-control" placeholder="ID Number" required></div><div class="row"><div class="col-md-6 mb-3"><input type="text" name="first_name" class="form-control" placeholder="First Name" required></div><div class="col-md-6 mb-3"><input type="text" name="last_name" class="form-control" placeholder="Last Name" required></div></div><div class="mb-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div><div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Password" required></div><div class="row"><div class="col-md-4 mb-3"><input type="text" name="course" class="form-control" placeholder="Course"></div><div class="col-md-4 mb-3"><input type="number" name="year_level" class="form-control" placeholder="Year"></div><div class="col-md-4 mb-3"><input type="text" name="block" class="form-control" placeholder="Block"></div></div><div class="mb-3"><select name="role" class="form-control"><option value="student_member">Student</option><option value="class_officer">Class Officer</option><option value="org_officer">Org Officer</option><option value="admin">Admin</option></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_user" class="btn btn-primary">Add User</button></div></form></div></div></div>
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