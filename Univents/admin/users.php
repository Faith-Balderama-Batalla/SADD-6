<?php
// admin/users.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    if (isset($_POST['update_user'])) {
        $target_id = intval($_POST['user_id']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $stmt = $conn->prepare("UPDATE users SET role = ?, status = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $role, $status, $target_id);
        
        if ($stmt->execute()) {
            createNotification($conn, $target_id, 'Account Updated', 'Your account role/status has been updated by an administrator.', 'system');
            $success = "User updated successfully!";
        } else {
            $error = "Failed to update user.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['delete_user'])) {
        $target_id = intval($_POST['user_id']);
        
        // Don't allow deleting self
        if ($target_id == $user['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $target_id);
            
            if ($stmt->execute()) {
                $success = "User deleted successfully!";
            } else {
                $error = "Failed to delete user.";
            }
            $stmt->close();
        }
    }
    
    if (isset($_POST['reset_password'])) {
        $target_id = intval($_POST['user_id']);
        $new_password = bin2hex(random_bytes(4)); // Generate 8-character random password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $target_id);
        
        if ($stmt->execute()) {
            $user_info = $conn->query("SELECT first_name, last_name, email FROM users WHERE user_id = $target_id")->fetch_assoc();
            createNotification($conn, $target_id, 'Password Reset', 'Your password has been reset by an administrator. New password: ' . $new_password, 'system');
            $success = "Password reset successfully. New password: " . $new_password;
        } else {
            $error = "Failed to reset password.";
        }
        $stmt->close();
    }
}

// Search and filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$role_filter = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$sql = "SELECT * FROM users WHERE 1=1";
if ($search) {
    $sql .= " AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR email LIKE '%$search%' OR id_number LIKE '%$search%')";
}
if ($role_filter) {
    $sql .= " AND role = '$role_filter'";
}
if ($status_filter) {
    $sql .= " AND status = '$status_filter'";
}
$sql .= " ORDER BY created_at DESC";

$users = $conn->query($sql);
$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
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
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-users-cog me-2" style="color: var(--primary-mint);"></i>
                        User Management
                    </h2>
                    <p class="text-muted">Manage all system users, their roles, and account status</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus me-2"></i>Add New User
                </button>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search by name, email, or ID number..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="role" class="form-control">
                                <option value="">All Roles</option>
                                <option value="student_member" <?php echo $role_filter == 'student_member' ? 'selected' : ''; ?>>Student</option>
                                <option value="org_officer" <?php echo $role_filter == 'org_officer' ? 'selected' : ''; ?>>Organization Officer</option>
                                <option value="class_officer" <?php echo $role_filter == 'class_officer' ? 'selected' : ''; ?>>Class Officer</option>
                                <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
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
            
            <!-- Users Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>ID Number</th>
                                    <th>Email</th>
                                    <th>Course/Year/Block</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($users && $users->num_rows > 0): ?>
                                    <?php while($u = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $u['user_id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar-mini me-2" style="width: 32px; height: 32px; font-size: 12px;">
                                                        <?php echo strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($u['id_number']); ?></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td>
                                                <?php if($u['course']): ?>
                                                    <?php echo htmlspecialchars($u['course']); ?> 
                                                    <?php echo $u['year_level']; ?>-<?php echo htmlspecialchars($u['block']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $u['role']=='admin' ? 'danger' : 
                                                         ($u['role']=='org_officer' ? 'primary' : 
                                                         ($u['role']=='class_officer' ? 'warning' : 'secondary')); 
                                                ?>">
                                                    <?php echo str_replace('_',' ', $u['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['status']=='active' ? 'success' : ($u['status']=='pending' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($u['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $u['user_id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo $u['user_id']; ?>">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <?php if($u['user_id'] != $user['user_id']): ?>
                                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo $u['user_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
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
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Role</label>
                                                                <select name="role" class="form-control">
                                                                    <option value="student_member" <?php echo $u['role']=='student_member' ? 'selected' : ''; ?>>Student</option>
                                                                    <option value="class_officer" <?php echo $u['role']=='class_officer' ? 'selected' : ''; ?>>Class Officer</option>
                                                                    <option value="org_officer" <?php echo $u['role']=='org_officer' ? 'selected' : ''; ?>>Organization Officer</option>
                                                                    <option value="admin" <?php echo $u['role']=='admin' ? 'selected' : ''; ?>>Admin</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Status</label>
                                                                <select name="status" class="form-control">
                                                                    <option value="active" <?php echo $u['status']=='active' ? 'selected' : ''; ?>>Active</option>
                                                                    <option value="inactive" <?php echo $u['status']=='inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                                    <option value="pending" <?php echo $u['status']=='pending' ? 'selected' : ''; ?>>Pending</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="update_user" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Reset Password Modal -->
                                        <div class="modal fade" id="resetPasswordModal<?php echo $u['user_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Reset Password</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                            
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                                Are you sure you want to reset the password for <strong><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></strong>?
                                                                <br><small>A new random password will be generated and sent as a notification.</small>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Delete User Modal -->
                                        <div class="modal fade" id="deleteUserModal<?php echo $u['user_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title text-danger">Delete User</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                            
                                                            <div class="alert alert-danger">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                                Are you sure you want to delete <strong><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></strong>?
                                                                <br><small>This action cannot be undone. All associated data will be permanently removed.</small>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5">
                                            <i class="fas fa-users fa-3x text-muted mb-3 d-block"></i>
                                            <h5>No users found</h5>
                                            <p class="text-muted">Try adjusting your search filters or add a new user.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Showing <?php echo $users ? $users->num_rows : 0; ?> users
                        </small>
                        <button class="btn btn-sm btn-outline-secondary" onclick="exportToCSV('users-table', 'users_export.csv')">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="add_user.php">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ID Number *</label>
                            <input type="text" name="id_number" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required>
                            <small class="text-muted">Min. 8 characters with uppercase and number</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-control" required>
                                <option value="student_member">Student Member</option>
                                <option value="class_officer">Class Officer</option>
                                <option value="org_officer">Organization Officer</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Course</label>
                            <input type="text" name="course" class="form-control" placeholder="e.g., BSIT">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Year Level</label>
                            <select name="year_level" class="form-control">
                                <option value="">Select Year</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Block</label>
                            <input type="text" name="block" class="form-control" placeholder="e.g., A">
                        </div>
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
<script src="../assets/js/dashboard.js"></script>
</body>
</html>