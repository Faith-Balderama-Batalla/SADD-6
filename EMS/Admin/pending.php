<?php
// Admin/pending.php
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

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $pending_id = intval($_POST['pending_id']);
        $target_user_id = intval($_POST['user_id']);
        
        // Get user details
        $user_sql = "SELECT course, year_level, block FROM users WHERE user_id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $target_user_id);
        $user_stmt->execute();
        $user_data = $user_stmt->get_result()->fetch_assoc();
        $user_stmt->close();
        
        // Update pending record
        $update_pending = "UPDATE pending_officers SET status = 'approved', reviewed_by = ?, review_date = NOW() WHERE pending_id = ?";
        $update_pending_stmt = $conn->prepare($update_pending);
        $update_pending_stmt->bind_param("ii", $user['user_id'], $pending_id);
        $update_pending_stmt->execute();
        
        // Update user role
        $update_user = "UPDATE users SET role = 'class_officer', status = 'active' WHERE user_id = ?";
        $update_user_stmt = $conn->prepare($update_user);
        $update_user_stmt->bind_param("i", $target_user_id);
        $update_user_stmt->execute();
        
        // Create class for officer
        $create_class = "INSERT INTO classes (course, year_level, block, class_officer_id, academic_year, semester) 
                         VALUES (?, ?, ?, ?, '2025-2026', '2nd')";
        $class_stmt = $conn->prepare($create_class);
        $class_stmt->bind_param("sisi", $user_data['course'], $user_data['year_level'], $user_data['block'], $target_user_id);
        $class_stmt->execute();
        $class_stmt->close();
        
        // Create notification
        $notif_sql = "INSERT INTO notifications (user_id, title, message, notification_type) 
                      VALUES (?, 'Class Officer Application Approved', 'Your application to become a class officer has been approved!', 'system')";
        $notif_stmt = $conn->prepare($notif_sql);
        $notif_stmt->bind_param("i", $target_user_id);
        $notif_stmt->execute();
        
        $success = "Application approved and class created successfully!";
    }
    
    if (isset($_POST['reject'])) {
        $pending_id = intval($_POST['pending_id']);
        $target_user_id = intval($_POST['user_id']);
        $reason = mysqli_real_escape_string($conn, $_POST['rejection_reason']);
        
        $update_pending = "UPDATE pending_officers SET status = 'rejected', reviewed_by = ?, review_date = NOW(), rejection_reason = ? WHERE pending_id = ?";
        $update_pending_stmt = $conn->prepare($update_pending);
        $update_pending_stmt->bind_param("isi", $user['user_id'], $reason, $pending_id);
        $update_pending_stmt->execute();
        
        // Update user status to active but keep as student_member
        $update_user = "UPDATE users SET status = 'active' WHERE user_id = ?";
        $update_user_stmt = $conn->prepare($update_user);
        $update_user_stmt->bind_param("i", $target_user_id);
        $update_user_stmt->execute();
        
        // Create notification
        $notif_sql = "INSERT INTO notifications (user_id, title, message, notification_type) 
                      VALUES (?, 'Class Officer Application Update', 'Your application was not approved. Reason: $reason', 'system')";
        $notif_stmt = $conn->prepare($notif_sql);
        $notif_stmt->bind_param("i", $target_user_id);
        $notif_stmt->execute();
        
        $success = "Application rejected.";
    }
}

// Get all pending applications
$pending_sql = "SELECT p.*, u.first_name, u.last_name, u.email, u.id_number
                FROM pending_officers p
                JOIN users u ON p.user_id = u.user_id
                WHERE p.status = 'pending'
                ORDER BY p.request_date ASC";
$pending_result = $conn->query($pending_sql);

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
    <title>Pending Approvals - Univents Admin</title>
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
                    <h2 class="mb-1">Pending Approvals</h2>
                    <p class="text-muted">Review and manage officer applications</p>
                </div>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if($pending_result && $pending_result->num_rows > 0): ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>ID Number</th>
                                    <th>Email</th>
                                    <th>Requested Role</th>
                                    <th>Course/Year/Block</th>
                                    <th>Request Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($app = $pending_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $app['first_name'] . ' ' . $app['last_name']; ?></td>
                                    <td><?php echo $app['id_number']; ?></td>
                                    <td><?php echo $app['email']; ?></td>
                                    <td>
                                        <span class="badge bg-warning">
                                            <?php echo str_replace('_', ' ', $app['requested_role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $app['course'] . ' ' . $app['year_level'] . $app['block']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($app['request_date'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-success approve-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#approveModal<?php echo $app['pending_id']; ?>">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger reject-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal<?php echo $app['pending_id']; ?>">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Approve Modal -->
                                <div class="modal fade" id="approveModal<?php echo $app['pending_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Approve Application</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Approve <strong><?php echo $app['first_name'] . ' ' . $app['last_name']; ?></strong> as <?php echo str_replace('_', ' ', $app['requested_role']); ?>?</p>
                                                    <input type="hidden" name="pending_id" value="<?php echo $app['pending_id']; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $app['user_id']; ?>">
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
                                <div class="modal fade" id="rejectModal<?php echo $app['pending_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Reject Application</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Reject <strong><?php echo $app['first_name'] . ' ' . $app['last_name']; ?></strong>'s application?</p>
                                                    <input type="hidden" name="pending_id" value="<?php echo $app['pending_id']; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $app['user_id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Reason for rejection</label>
                                                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
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
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h4>No Pending Applications</h4>
                            <p class="text-muted">All caught up! There are no applications waiting for review.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>