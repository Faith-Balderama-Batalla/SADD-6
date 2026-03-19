<?php
// Admin/organizations.php
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

$success = '';
$error = '';

// Handle Add Organization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_organization'])) {
    $org_name = mysqli_real_escape_string($conn, $_POST['org_name']);
    $org_description = mysqli_real_escape_string($conn, $_POST['org_description']);
    $contact_email = mysqli_real_escape_string($conn, $_POST['contact_email']);
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $org_color = mysqli_real_escape_string($conn, $_POST['org_color']);
    
    $insert_sql = "INSERT INTO organizations (org_name, org_description, contact_email, contact_number, org_color, status) 
                   VALUES (?, ?, ?, ?, ?, 'active')";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sssss", $org_name, $org_description, $contact_email, $contact_number, $org_color);
    
    if ($insert_stmt->execute()) {
        $success = "Organization added successfully!";
    } else {
        $error = "Failed to add organization.";
    }
    $insert_stmt->close();
}

// Handle Update Organization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_organization'])) {
    $org_id = intval($_POST['org_id']);
    $org_name = mysqli_real_escape_string($conn, $_POST['org_name']);
    $org_description = mysqli_real_escape_string($conn, $_POST['org_description']);
    $contact_email = mysqli_real_escape_string($conn, $_POST['contact_email']);
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $org_color = mysqli_real_escape_string($conn, $_POST['org_color']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $update_sql = "UPDATE organizations SET org_name = ?, org_description = ?, contact_email = ?, contact_number = ?, org_color = ?, status = ? WHERE org_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssssi", $org_name, $org_description, $contact_email, $contact_number, $org_color, $status, $org_id);
    
    if ($update_stmt->execute()) {
        $success = "Organization updated successfully!";
    } else {
        $error = "Failed to update organization.";
    }
    $update_stmt->close();
}

// Handle Delete Organization
if (isset($_GET['delete'])) {
    $org_id = intval($_GET['delete']);
    
    $delete_sql = "DELETE FROM organizations WHERE org_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $org_id);
    
    if ($delete_stmt->execute()) {
        $success = "Organization deleted successfully!";
    } else {
        $error = "Failed to delete organization.";
    }
    $delete_stmt->close();
}

// Get all organizations
$orgs_sql = "SELECT * FROM organizations ORDER BY org_name ASC";
$orgs_result = $conn->query($orgs_sql);

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
    <title>Organization Management - Univents Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Main CSS -->
    <link rel="stylesheet" href="../style.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../style3.css">
</head>
<body class="dashboard-body">
    
    <?php include 'nav_sidebar_admin.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Organization Management</h2>
                    <p class="text-muted">Create and manage student organizations</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOrgModal">
                    <i class="fas fa-plus-circle me-2"></i>Add Organization
                </button>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Organizations List -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if($orgs_result && $orgs_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Organization</th>
                                        <th>Description</th>
                                        <th>Contact</th>
                                        <th>Color</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($org = $orgs_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $org['org_id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($org['org_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo substr(htmlspecialchars($org['org_description'] ?? 'No description'), 0, 50); ?>...
                                        </td>
                                        <td>
                                            <?php if($org['contact_email']): ?>
                                                <i class="fas fa-envelope me-1"></i><?php echo $org['contact_email']; ?><br>
                                            <?php endif; ?>
                                            <?php if($org['contact_number']): ?>
                                                <i class="fas fa-phone me-1"></i><?php echo $org['contact_number']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>; color: white; padding: 8px 12px;">
                                                <?php echo $org['org_color'] ?? '#4FD1C5'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $org['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo $org['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($org['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editOrgModal<?php echo $org['org_id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmDelete(<?php echo $org['org_id']; ?>, '<?php echo htmlspecialchars($org['org_name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <a href="org_details.php?id=<?php echo $org['org_id']; ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    
                                    <!-- Edit Organization Modal -->
                                    <div class="modal fade" id="editOrgModal<?php echo $org['org_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Organization</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="org_id" value="<?php echo $org['org_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Organization Name</label>
                                                            <input type="text" name="org_name" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($org['org_name']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <textarea name="org_description" class="form-control" rows="3"><?php echo htmlspecialchars($org['org_description']); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Contact Email</label>
                                                            <input type="email" name="contact_email" class="form-control" 
                                                                   value="<?php echo $org['contact_email']; ?>">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Contact Number</label>
                                                            <input type="text" name="contact_number" class="form-control" 
                                                                   value="<?php echo $org['contact_number']; ?>">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Brand Color</label>
                                                            <input type="color" name="org_color" class="form-control form-control-color" 
                                                                   value="<?php echo $org['org_color'] ?? '#4FD1C5'; ?>">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Status</label>
                                                            <select name="status" class="form-control">
                                                                <option value="active" <?php echo $org['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="inactive" <?php echo $org['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_organization" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- Empty State - Show sample data so admin knows what to add -->
                        <div class="text-center py-5">
                            <i class="fas fa-building fa-4x text-muted mb-3"></i>
                            <h4>No Organizations Yet</h4>
                            <p class="text-muted mb-4">Get started by adding your first organization</p>
                            
                            <!-- Sample Data Preview -->
                            <div class="row justify-content-center mt-4">
                                <div class="col-md-8">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">Sample Organizations You Can Add:</h5>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div style="width: 30px; height: 30px; background: #4FD1C5; border-radius: 8px;" class="me-2"></div>
                                                        <div>
                                                            <strong>Computer Science Society</strong><br>
                                                            <small class="text-muted">css@bu.edu.ph</small>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div style="width: 30px; height: 30px; background: #F56565; border-radius: 8px;" class="me-2"></div>
                                                        <div>
                                                            <strong>Student Government</strong><br>
                                                            <small class="text-muted">sg@bu.edu.ph</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div style="width: 30px; height: 30px; background: #9F7AEA; border-radius: 8px;" class="me-2"></div>
                                                        <div>
                                                            <strong>Junior Entrepreneurs</strong><br>
                                                            <small class="text-muted">je@bu.edu.ph</small>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div style="width: 30px; height: 30px; background: #48BB78; border-radius: 8px;" class="me-2"></div>
                                                        <div>
                                                            <strong>Young Photographers</strong><br>
                                                            <small class="text-muted">yp@bu.edu.ph</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addOrgModal">
                                                <i class="fas fa-plus-circle me-2"></i>Add Your First Organization
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
</div> <!-- Close dashboard-container -->

<!-- Add Organization Modal -->
<div class="modal fade" id="addOrgModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Organization</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Organization Name</label>
                        <input type="text" name="org_name" class="form-control" placeholder="e.g., Computer Science Society" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="org_description" class="form-control" rows="3" placeholder="Brief description of the organization..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="contact_email" class="form-control" placeholder="org@bu.edu.ph">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" placeholder="e.g., 09123456789">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Brand Color</label>
                        <input type="color" name="org_color" class="form-control form-control-color" value="#4FD1C5">
                        <small class="text-muted">Choose a color for the organization's theme</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_organization" class="btn btn-primary">Add Organization</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Delete Confirmation Script -->
<script>
function confirmDelete(orgId, orgName) {
    if (confirm(`Are you sure you want to delete "${orgName}"? This action cannot be undone.`)) {
        window.location.href = `organizations.php?delete=${orgId}`;
    }
}
</script>

<!-- Sidebar JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const collapseBtn = document.getElementById('collapseBtn');
        const menuToggle = document.getElementById('menuToggle');
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationMenu = document.getElementById('notificationMenu');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });
        }
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-show');
            });
        }
        
        if (profileBtn) {
            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileMenu.classList.toggle('show');
                if (notificationMenu) notificationMenu.classList.remove('show');
            });
        }
        
        if (notificationBtn) {
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationMenu.classList.toggle('show');
                if (profileMenu) profileMenu.classList.remove('show');
            });
        }
        
        document.addEventListener('click', function() {
            if (profileMenu) profileMenu.classList.remove('show');
            if (notificationMenu) notificationMenu.classList.remove('show');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 992) {
                if (sidebar && !sidebar.contains(e.target) && menuToggle && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('mobile-show');
                }
            }
        });
    });
</script>
</body>
</html>