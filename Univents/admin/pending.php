<?php
// admin/pending.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    if (isset($_POST['approve'])) {
        $pending_id = intval($_POST['pending_id']);
        $target_id = intval($_POST['user_id']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $organization_id = isset($_POST['organization_id']) ? intval($_POST['organization_id']) : null;
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
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
            
            // If org officer, create organization_officers record
            if ($role == 'org_officer' && $organization_id) {
                // Get user info for term dates
                $term_start = date('Y-m-d');
                $term_end = date('Y-m-d', strtotime('+1 year'));
                
                $stmt3 = $conn->prepare("INSERT INTO organization_officers (user_id, org_id, position, term_start, term_end, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $position = 'Officer'; // Default position, can be customized
                $stmt3->bind_param("iisss", $target_id, $organization_id, $position, $term_start, $term_end);
                $stmt3->execute();
                $stmt3->close();
                
                // Also add as organization member
                $stmt4 = $conn->prepare("INSERT INTO organization_memberships (user_id, org_id, status) VALUES (?, ?, 'active')");
                $stmt4->bind_param("ii", $target_id, $organization_id);
                $stmt4->execute();
                $stmt4->close();
            }
            
            $conn->commit();
            
            // Create notification
            $message = "Your application to become " . str_replace('_', ' ', $role) . " has been approved!";
            createNotification($conn, $target_id, 'Application Approved', $message, 'system');
            
            $success = "Application approved successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to approve application: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['reject'])) {
        $pending_id = intval($_POST['pending_id']);
        $target_id = intval($_POST['user_id']);
        $reason = isset($_POST['reason']) ? mysqli_real_escape_string($conn, $_POST['reason']) : 'No reason provided';
        
        $stmt1 = $conn->prepare("UPDATE pending_officers SET status = 'rejected', reviewed_by = ?, review_date = NOW(), rejection_reason = ? WHERE pending_id = ?");
        $stmt1->bind_param("isi", $user['user_id'], $reason, $pending_id);
        
        if ($stmt1->execute()) {
            // Update user status to active (they can still use as student)
            $stmt2 = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
            $stmt2->bind_param("i", $target_id);
            $stmt2->execute();
            $stmt2->close();
            
            $message = "Your officer application was not approved. Reason: " . $reason;
            createNotification($conn, $target_id, 'Application Update', $message, 'system');
            
            $success = "Application rejected.";
        } else {
            $error = "Failed to reject application.";
        }
        $stmt1->close();
    }
}

// Get pending applications
$sql = "SELECT p.*, u.first_name, u.last_name, u.email, u.id_number, u.course, u.year_level, u.block,
        o.org_name as organization_name
        FROM pending_officers p 
        JOIN users u ON p.user_id = u.user_id 
        LEFT JOIN organizations o ON p.organization_id = o.org_id
        WHERE p.status = 'pending' 
        ORDER BY p.request_date ASC";

$pending = $conn->query($sql);

// Get organizations for dropdown
$orgs = $conn->query("SELECT org_id, org_name FROM organizations WHERE status = 'active' ORDER BY org_name");

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Pending Approvals - Univents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .pending-card {
            transition: all 0.3s ease;
            border-left: 4px solid #F6AD55;
        }
        .pending-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .role-badge {
            background: linear-gradient(135deg, #F6AD55, #ED8936);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .request-date {
            font-size: 0.8rem;
            color: #718096;
        }
        .user-avatar-lg {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4FD1C5, #38B2AC);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="mb-4">
                <h2 class="mb-1">
                    <i class="fas fa-clock me-2" style="color: var(--primary-mint);"></i>
                    Pending Approvals
                </h2>
                <p class="text-muted">Review and process officer applications</p>
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
            
            <?php if($pending && $pending->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while($p = $pending->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-0 shadow-sm pending-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="user-avatar-lg me-3">
                                            <?php echo strtoupper(substr($p['first_name'],0,1) . substr($p['last_name'],0,1)); ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></h5>
                                            <p class="text-muted small mb-1">
                                                <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($p['id_number']); ?>
                                            </p>
                                            <p class="text-muted small mb-1">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($p['email']); ?>
                                            </p>
                                        </div>
                                        <div class="role-badge">
                                            <?php echo str_replace('_', ' ', $p['requested_role']); ?>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="mb-3">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Course</small>
                                                <strong><?php echo htmlspecialchars($p['course'] ?? 'N/A'); ?></strong>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted d-block">Year</small>
                                                <strong><?php echo $p['year_level'] ?? 'N/A'; ?></strong>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted d-block">Block</small>
                                                <strong><?php echo htmlspecialchars($p['block'] ?? 'N/A'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if($p['requested_role'] == 'org_officer' && $p['organization_name']): ?>
                                        <div class="mb-3">
                                            <small class="text-muted d-block">Requested Organization</small>
                                            <strong>
                                                <i class="fas fa-building me-1"></i>
                                                <?php echo htmlspecialchars($p['organization_name']); ?>
                                            </strong>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="request-date mb-3">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        Requested: <?php echo date('F j, Y g:i A', strtotime($p['request_date'])); ?>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-success flex-grow-1" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $p['pending_id']; ?>">
                                            <i class="fas fa-check me-1"></i> Approve
                                        </button>
                                        <button class="btn btn-danger flex-grow-1" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $p['pending_id']; ?>">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Approve Modal -->
                        <div class="modal fade" id="approveModal<?php echo $p['pending_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Approve Application</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="pending_id" value="<?php echo $p['pending_id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                            
                                            <div class="text-center mb-3">
                                                <div class="user-avatar-lg mx-auto mb-3">
                                                    <?php echo strtoupper(substr($p['first_name'],0,1) . substr($p['last_name'],0,1)); ?>
                                                </div>
                                                <h5><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></h5>
                                                <p class="text-muted"><?php echo htmlspecialchars($p['email']); ?></p>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Role to Assign</label>
                                                <select name="role" class="form-control">
                                                    <option value="org_officer" <?php echo $p['requested_role'] == 'org_officer' ? 'selected' : ''; ?>>Organization Officer</option>
                                                    <option value="class_officer" <?php echo $p['requested_role'] == 'class_officer' ? 'selected' : ''; ?>>Class Officer</option>
                                                </select>
                                            </div>
                                            
                                            <?php if($p['requested_role'] == 'org_officer'): ?>
                                                <div class="mb-3">
                                                    <label class="form-label">Organization</label>
                                                    <select name="organization_id" class="form-control" required>
                                                        <option value="">Select Organization</option>
                                                        <?php 
                                                        $orgs->data_seek(0);
                                                        while($org = $orgs->fetch_assoc()): 
                                                        ?>
                                                            <option value="<?php echo $org['org_id']; ?>" <?php echo $p['organization_id'] == $org['org_id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($org['org_name']); ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                The user will receive a notification once approved.
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="approve" class="btn btn-success">Approve</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?php echo $p['pending_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Application</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="pending_id" value="<?php echo $p['pending_id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                            
                                            <div class="text-center mb-3">
                                                <div class="user-avatar-lg mx-auto mb-3">
                                                    <?php echo strtoupper(substr($p['first_name'],0,1) . substr($p['last_name'],0,1)); ?>
                                                </div>
                                                <h5><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></h5>
                                                <p class="text-muted"><?php echo htmlspecialchars($p['email']); ?></p>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Reason for Rejection</label>
                                                <textarea name="reason" class="form-control" rows="3" placeholder="Provide a reason for rejection..."></textarea>
                                                <small class="text-muted">This will be included in the notification to the user.</small>
                                            </div>
                                            
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                The user will still be able to use the system as a regular student.
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="reject" class="btn btn-danger">Reject</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4>No Pending Applications</h4>
                        <p class="text-muted">All caught up! There are no officer applications waiting for review.</p>
                        <div class="mt-3">
                            <i class="fas fa-smile-wink fa-2x text-muted"></i>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>