<?php
// OrgMngrs/dashboard.php
require_once '../config.php';

if (!isLoggedIn()) {
    header('Location: ../login-signup.php');
    exit();
}

$user = getCurrentUser($conn);

if ($user['role'] !== 'org_officer') {
    header('Location: ../StudentMembers/dashboard.php');
    exit();
}

// Get organizations this officer manages
$orgs_sql = "SELECT o.*, oo.position 
             FROM organizations o
             JOIN organization_officers oo ON o.org_id = oo.org_id
             WHERE oo.user_id = ? AND oo.status = 'active'
             ORDER BY o.org_name ASC";
$orgs_stmt = $conn->prepare($orgs_sql);
$orgs_stmt->bind_param("i", $user['user_id']);
$orgs_stmt->execute();
$managed_orgs = $orgs_stmt->get_result();
$primary_org = $managed_orgs->fetch_assoc(); // First organization

// Get stats
$members_count = 0;
$events_count = 0;
$pending_count = 0;

if ($primary_org) {
    $members = $conn->query("SELECT COUNT(*) as count FROM organization_memberships WHERE org_id = {$primary_org['org_id']} AND status = 'active'");
    $members_count = $members->fetch_assoc()['count'];
    
    $events = $conn->query("SELECT COUNT(*) as count FROM events WHERE org_id = {$primary_org['org_id']} AND status = 'upcoming'");
    $events_count = $events->fetch_assoc()['count'];
}

// Get unread notifications
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
    <title>Organization Dashboard - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../style2.css">
</head>
<body class="dashboard-body">
    
    <?php include 'nav_sidebar_org.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Organization Dashboard</h2>
                    <p class="text-muted">Manage your organizations and engage with members</p>
                </div>
                <div class="org-officer-badge">
                    <i class="fas fa-star me-2"></i>Organization Officer
                </div>
            </div>
            
            <?php if(!$primary_org): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    You are not assigned to any organization yet. Please contact an administrator.
                </div>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="analytics-card">
                        <div class="d-flex align-items-center">
                            <div class="org-stat-icon me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $members_count; ?></h3>
                                <p class="text-muted mb-0">Active Members</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="analytics-card">
                        <div class="d-flex align-items-center">
                            <div class="org-stat-icon me-3">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $events_count; ?></h3>
                                <p class="text-muted mb-0">Upcoming Events</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="analytics-card">
                        <div class="d-flex align-items-center">
                            <div class="org-stat-icon me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $pending_count; ?></h3>
                                <p class="text-muted mb-0">Pending Requests</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="analytics-card">
                        <div class="d-flex align-items-center">
                            <div class="org-stat-icon me-3">
                                <i class="fas fa-tshirt"></i>
                            </div>
                            <div>
                                <h3 class="mb-0">5</h3>
                                <p class="text-muted mb-0">Merchandise</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Quick Actions</h5>
                            <a href="create_announcement.php" class="btn btn-primary me-2">
                                <i class="fas fa-bullhorn me-2"></i>Post Announcement
                            </a>
                            <a href="create_event.php" class="btn btn-outline-primary me-2">
                                <i class="fas fa-plus-circle me-2"></i>Create Event
                            </a>
                            <a href="add_merchandise.php" class="btn btn-outline-primary me-2">
                                <i class="fas fa-tshirt me-2"></i>Add Merchandise
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0">Recent Announcements</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border-0 py-3">
                                    <p class="mb-1 text-muted">No recent announcements</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0 text-end">
                            <a href="announcements.php" class="btn btn-sm btn-link">View All</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0">Upcoming Events</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border-0 py-3">
                                    <p class="mb-1 text-muted">No upcoming events</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0 text-end">
                            <a href="events.php" class="btn btn-sm btn-link">View All</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>