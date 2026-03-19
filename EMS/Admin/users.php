<?php
// Admin/users.php
require_once '../config.php';

if (!isLoggedIn()) {
    header('Location: ../login-signup.php');
    exit();
}

$user = getCurrentUser($conn);

if ($user['role'] !== 'admin') {
    header('Location: ../StudentMembers/dashboard.php');
    exit();
}

// Handle user actions (edit, delete, change role)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_role'])) {
        $target_user_id = intval($_POST['user_id']);
        $new_role = mysqli_real_escape_string($conn, $_POST['role']);
        
        $update_sql = "UPDATE users SET role = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_role, $target_user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $success = "User role updated successfully!";
    }
    
    if (isset($_POST['toggle_status'])) {
        $target_user_id = intval($_POST['user_id']);
        $new_status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $update_sql = "UPDATE users SET status = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $target_user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $success = "User status updated successfully!";
    }
}

// Get all users
$users_sql = "SELECT * FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_sql);

// Get unread notifications count
$notif_sql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user['user_id']);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$unread_count = $notif_result->fetch_assoc()['unread'] ?? 0;
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
    <link rel="stylesheet" href="../style3.css">
</head>
<body class="dashboard-body">
    
    <?php include 'nav_sidebar_admin.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">User Management</h2>
                    <p class="text-muted">Manage all system users</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus me-2"></i>Add New User
                </button>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>ID Number</th>
                                    <th>Email</th>
                                    <th>Course/Year/Block</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($u = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $u['user_id']; ?></td>
                                    <td><?php echo $u['first_name'] . ' ' . $u['last_name']; ?></td>
                                    <td><?php echo $u['id_number']; ?></td>
                                    <td><?php echo $u['email']; ?></td>
                                    <td><?php echo $u['course'] . ' ' . $u['year_level'] . $u['block']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $u['role'] == 'admin' ? 'danger' : 
                                                ($u['role'] == 'org_officer' ? 'primary' : 
                                                ($u['role'] == 'class_officer' ? 'warning' : 'secondary')); 
                                        ?>">
                                            <?php echo str_replace('_', ' ', $u['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $u['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo $u['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $u['user_id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo $u['user_id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Edit User Modal -->
                                <div class="modal fade" id="editUserModal<?php echo $u['user_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit User</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Role</label>
                                                        <select name="role" class="form-control">
                                                            <option value="student_member" <?php echo $u['role'] == 'student_member' ? 'selected' : ''; ?>>Student Member</option>
                                                            <option value="class_officer" <?php echo $u['role'] == 'class_officer' ? 'selected' : ''; ?>>Class Officer</option>
                                                            <option value="org_officer" <?php echo $u['role'] == 'org_officer' ? 'selected' : ''; ?>>Organization Officer</option>
                                                            <option value="admin" <?php echo $u['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select name="status" class="form-control">
                                                            <option value="active" <?php echo $u['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                            <option value="inactive" <?php echo $u['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                            <option value="pending" <?php echo $u['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="change_role" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="add_user.php">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ID Number</label>
                        <input type="text" name="id_number" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Course</label>
                            <input type="text" name="course" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Year Level</label>
                            <input type="number" name="year_level" class="form-control" min="1" max="4">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Block</label>
                            <input type="text" name="block" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="student_member">Student Member</option>
                            <option value="class_officer">Class Officer</option>
                            <option value="org_officer">Organization Officer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>