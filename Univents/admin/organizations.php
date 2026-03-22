<?php
// admin/organizations.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Handle organization actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    if (isset($_POST['add_organization'])) {
        $name = mysqli_real_escape_string($conn, $_POST['org_name']);
        $desc = mysqli_real_escape_string($conn, $_POST['org_description']);
        $email = mysqli_real_escape_string($conn, $_POST['contact_email']);
        $phone = mysqli_real_escape_string($conn, $_POST['contact_number']);
        $color = mysqli_real_escape_string($conn, $_POST['org_color']);
        
        $stmt = $conn->prepare("INSERT INTO organizations (org_name, org_description, contact_email, contact_number, org_color) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $desc, $email, $phone, $color);
        
        if ($stmt->execute()) {
            $success = "Organization added successfully!";
        } else {
            $error = "Failed to add organization.";
        }
        $stmt->close();
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
        
        if ($stmt->execute()) {
            $success = "Organization updated successfully!";
        } else {
            $error = "Failed to update organization.";
        }
        $stmt->close();
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && verifyCSRFToken($_GET['csrf_token'])) {
    $id = intval($_GET['delete']);
    
    // Check if organization has events
    $check = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE org_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $error = "Cannot delete organization with existing events. Archive it instead.";
    } else {
        $stmt = $conn->prepare("DELETE FROM organizations WHERE org_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Organization deleted successfully!";
        } else {
            $error = "Failed to delete organization.";
        }
        $stmt->close();
    }
    $check->close();
}

// Search and filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$sql = "SELECT o.*, 
        (SELECT COUNT(*) FROM events WHERE org_id = o.org_id) as event_count,
        (SELECT COUNT(*) FROM organization_memberships WHERE org_id = o.org_id AND status = 'active') as member_count
        FROM organizations o WHERE 1=1";
if ($search) {
    $sql .= " AND (org_name LIKE '%$search%' OR org_description LIKE '%$search%')";
}
if ($status_filter) {
    $sql .= " AND status = '$status_filter'";
}
$sql .= " ORDER BY org_name ASC";

$orgs = $conn->query($sql);
$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
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
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-building me-2" style="color: var(--primary-mint);"></i>
                        Organizations
                    </h2>
                    <p class="text-muted">Manage student organizations, their details, and status</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOrgModal">
                    <i class="fas fa-plus-circle me-2"></i>Add Organization
                </button>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <input type="text" name="search" class="form-control" placeholder="Search organizations by name or description..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Organizations Grid -->
            <div class="row g-4">
                <?php if($orgs && $orgs->num_rows > 0): ?>
                    <?php while($org = $orgs->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="org-logo-placeholder me-3" style="width: 50px; height: 50px; background: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-users" style="color: white; font-size: 1.5rem;"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($org['org_name']); ?></h5>
                                                <span class="badge bg-<?php echo $org['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($org['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-link text-muted" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editOrgModal<?php echo $org['org_id']; ?>">
                                                        <i class="fas fa-edit me-2"></i>Edit
                                                    </button>
                                                </li>
                                                <?php if($org['status'] == 'active'): ?>
                                                    <li>
                                                        <button class="dropdown-item text-warning" onclick="archiveOrg(<?php echo $org['org_id']; ?>)">
                                                            <i class="fas fa-archive me-2"></i>Archive
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <button class="dropdown-item text-danger" onclick="confirmDelete(<?php echo $org['org_id']; ?>, '<?php echo addslashes($org['org_name']); ?>')">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <p class="text-muted small mb-3">
                                        <?php echo nl2br(htmlspecialchars(substr($org['org_description'] ?? 'No description provided', 0, 100))); ?>
                                        <?php if(strlen($org['org_description'] ?? '') > 100): ?>...<?php endif; ?>
                                    </p>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="bg-light rounded p-2 text-center">
                                                <small class="text-muted d-block">Events</small>
                                                <strong><?php echo $org['event_count']; ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="bg-light rounded p-2 text-center">
                                                <small class="text-muted d-block">Members</small>
                                                <strong><?php echo $org['member_count']; ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if($org['contact_email'] || $org['contact_number']): ?>
                                        <hr>
                                        <div class="small text-muted">
                                            <?php if($org['contact_email']): ?>
                                                <div><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($org['contact_email']); ?></div>
                                            <?php endif; ?>
                                            <?php if($org['contact_number']): ?>
                                                <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($org['contact_number']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Organization Modal -->
                        <div class="modal fade" id="editOrgModal<?php echo $org['org_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Organization</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="org_id" value="<?php echo $org['org_id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Organization Name</label>
                                                <input type="text" name="org_name" class="form-control" value="<?php echo htmlspecialchars($org['org_name']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea name="org_description" class="form-control" rows="4"><?php echo htmlspecialchars($org['org_description']); ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Contact Email</label>
                                                    <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($org['contact_email']); ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Contact Number</label>
                                                    <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($org['contact_number']); ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Brand Color</label>
                                                    <input type="color" name="org_color" class="form-control form-control-color" value="<?php echo $org['org_color'] ?? '#4FD1C5'; ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-control">
                                                        <option value="active" <?php echo $org['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $org['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </div>
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
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-building fa-4x text-muted mb-3"></i>
                            <h4>No Organizations Found</h4>
                            <p class="text-muted">Click "Add Organization" to get started.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Organization Modal -->
<div class="modal fade" id="addOrgModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Organization</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Organization Name *</label>
                        <input type="text" name="org_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="org_description" class="form-control" rows="4" placeholder="Describe the organization's purpose, activities, and goals..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Email</label>
                            <input type="email" name="contact_email" class="form-control" placeholder="organization@bu.edu.ph">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" placeholder="+63 912 345 6789">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Brand Color</label>
                        <input type="color" name="org_color" class="form-control form-control-color" value="#4FD1C5">
                        <small class="text-muted">This color will be used for organization branding throughout the system.</small>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
function confirmDelete(id, name) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    if (confirm(`Delete "${name}"? This action cannot be undone.\n\nNote: Organizations with existing events cannot be deleted.`)) {
        window.location.href = `organizations.php?delete=${id}&csrf_token=${csrfToken}`;
    }
}

function archiveOrg(id) {
    // This would archive the organization (set status to inactive)
    // For now, we'll use the edit modal
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    if (confirm('Archive this organization? It will no longer be visible to students.')) {
        // Create a hidden form to update status
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <input type="hidden" name="org_id" value="${id}">
            <input type="hidden" name="org_name" value="">
            <input type="hidden" name="org_description" value="">
            <input type="hidden" name="contact_email" value="">
            <input type="hidden" name="contact_number" value="">
            <input type="hidden" name="org_color" value="">
            <input type="hidden" name="status" value="inactive">
            <input type="hidden" name="update_organization" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
</body>
</html>