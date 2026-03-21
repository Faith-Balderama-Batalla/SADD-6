<?php
// Admin/organizations.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { header('Location: ../Student/dashboard.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_organization'])) {
        $name = mysqli_real_escape_string($conn, $_POST['org_name']);
        $desc = mysqli_real_escape_string($conn, $_POST['org_description']);
        $email = mysqli_real_escape_string($conn, $_POST['contact_email']);
        $phone = mysqli_real_escape_string($conn, $_POST['contact_number']);
        $color = mysqli_real_escape_string($conn, $_POST['org_color']);
        $stmt = $conn->prepare("INSERT INTO organizations (org_name, org_description, contact_email, contact_number, org_color) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $desc, $email, $phone, $color);
        $stmt->execute();
        $success = "Organization added successfully!";
    }
    if (isset($_POST['update_organization'])) {
        $id = intval($_POST['org_id']);
        $name = mysqli_real_escape_string($conn, $_POST['org_name']);
        $desc = mysqli_real_escape_string($conn, $_POST['org_description']);
        $email = mysqli_real_escape_string($conn, $_POST['contact_email']);
        $phone = mysqli_real_escape_string($conn, $_POST['contact_number']);
        $color = mysqli_real_escape_string($conn, $_POST['org_color']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $stmt = $conn->prepare("UPDATE organizations SET org_name=?, org_description=?, contact_email=?, contact_number=?, org_color=?, status=? WHERE org_id=?");
        $stmt->bind_param("ssssssi", $name, $desc, $email, $phone, $color, $status, $id);
        $stmt->execute();
        $success = "Organization updated successfully!";
    }
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $stmt = $conn->prepare("DELETE FROM organizations WHERE org_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $success = "Organization deleted successfully!";
    }
}

$orgs = $conn->query("SELECT * FROM organizations ORDER BY org_name ASC");
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
    <title>Organizations - Univents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Organizations</h2><p class="text-muted">Manage student organizations</p></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOrgModal"><i class="fas fa-plus-circle me-2"></i>Add Organization</button></div>
            <?php if(isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <div class="card border-0 shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Contact</th><th>Color</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php while($org = $orgs->fetch_assoc()): ?><tr><td><?php echo $org['org_id']; ?></td><td><strong><?php echo htmlspecialchars($org['org_name']); ?></strong></td><td><?php echo substr(htmlspecialchars($org['org_description'] ?? 'No description'), 0, 50); ?>...</td><td><?php if($org['contact_email']): ?><i class="fas fa-envelope me-1"></i><?php echo $org['contact_email']; ?><br><?php endif; ?><?php if($org['contact_number']): ?><i class="fas fa-phone me-1"></i><?php echo $org['contact_number']; ?><?php endif; ?></td><td><span class="badge" style="background: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>; color: white; padding: 8px 12px;"><?php echo $org['org_color'] ?? '#4FD1C5'; ?></span></td><td><span class="badge bg-<?php echo $org['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo $org['status']; ?></span></td><td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editOrgModal<?php echo $org['org_id']; ?>"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-outline-danger ms-1" onclick="confirmDelete(<?php echo $org['org_id']; ?>, '<?php echo addslashes($org['org_name']); ?>')"><i class="fas fa-trash"></i></button></td></tr>
<div class="modal fade" id="editOrgModal<?php echo $org['org_id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Edit Organization</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="org_id" value="<?php echo $org['org_id']; ?>"><div class="mb-3"><label>Organization Name</label><input type="text" name="org_name" class="form-control" value="<?php echo htmlspecialchars($org['org_name']); ?>" required></div><div class="mb-3"><label>Description</label><textarea name="org_description" class="form-control" rows="3"><?php echo htmlspecialchars($org['org_description']); ?></textarea></div><div class="mb-3"><label>Contact Email</label><input type="email" name="contact_email" class="form-control" value="<?php echo $org['contact_email']; ?>"></div><div class="mb-3"><label>Contact Number</label><input type="text" name="contact_number" class="form-control" value="<?php echo $org['contact_number']; ?>"></div><div class="mb-3"><label>Brand Color</label><input type="color" name="org_color" class="form-control form-control-color" value="<?php echo $org['org_color'] ?? '#4FD1C5'; ?>"></div><div class="mb-3"><label>Status</label><select name="status" class="form-control"><option value="active" <?php echo $org['status']=='active'?'selected':''; ?>>Active</option><option value="inactive" <?php echo $org['status']=='inactive'?'selected':''; ?>>Inactive</option></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="update_organization" class="btn btn-primary">Save</button></div></form></div></div></div>
<?php endwhile; ?></tbody></table></div></div></div>
        </div>
    </div>
</div>
<div class="modal fade" id="addOrgModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Add Organization</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><input type="text" name="org_name" class="form-control" placeholder="Organization Name" required></div><div class="mb-3"><textarea name="org_description" class="form-control" rows="3" placeholder="Description"></textarea></div><div class="mb-3"><input type="email" name="contact_email" class="form-control" placeholder="Contact Email"></div><div class="mb-3"><input type="text" name="contact_number" class="form-control" placeholder="Contact Number"></div><div class="mb-3"><label>Brand Color</label><input type="color" name="org_color" class="form-control form-control-color" value="#4FD1C5"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_organization" class="btn btn-primary">Add</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id, name) { if(confirm(`Delete "${name}"? This cannot be undone.`)) window.location.href = `organizations.php?delete=${id}`; }
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