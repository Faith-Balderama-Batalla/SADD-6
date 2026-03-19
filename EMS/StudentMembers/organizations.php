<?php
// StudentMembers/organizations.php
require_once '../config.php';

if (!isLoggedIn()) {
    header('Location: ../login-signup.php');
    exit();
}

$user = getCurrentUser($conn);
$user_id = $user['user_id'];

$success = '';
$error = '';

// Handle join organization request
if (isset($_GET['join'])) {
    $org_id = intval($_GET['join']);
    
    // Check if already a member
    $check_sql = "SELECT * FROM organization_memberships WHERE user_id = ? AND org_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $org_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result();
    
    if ($existing->num_rows > 0) {
        $membership = $existing->fetch_assoc();
        if ($membership['status'] == 'active') {
            $error = "You are already a member of this organization.";
        } elseif ($membership['status'] == 'pending') {
            $error = "Your membership request is already pending.";
        } else {
            // Reactivate if inactive
            $update_sql = "UPDATE organization_memberships SET status = 'pending' WHERE membership_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $membership['membership_id']);
            $update_stmt->execute();
            $update_stmt->close();
            $success = "Your membership request has been submitted!";
        }
    } else {
        // New membership request
        $insert_sql = "INSERT INTO organization_memberships (user_id, org_id, status) VALUES (?, ?, 'pending')";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $user_id, $org_id);
        
        if ($insert_stmt->execute()) {
            $success = "Membership request submitted successfully!";
        } else {
            $error = "Failed to submit request.";
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}

// Handle leave organization
if (isset($_GET['leave'])) {
    $org_id = intval($_GET['leave']);
    
    $update_sql = "UPDATE organization_memberships SET status = 'inactive' WHERE user_id = ? AND org_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $user_id, $org_id);
    
    if ($update_stmt->execute()) {
        $success = "You have left the organization.";
    } else {
        $error = "Failed to leave organization.";
    }
    $update_stmt->close();
}

// Get organizations the user is a member of
$my_orgs_sql = "SELECT o.*, om.status as membership_status, om.membership_date
                FROM organizations o
                JOIN organization_memberships om ON o.org_id = om.org_id
                WHERE om.user_id = ?
                ORDER BY 
                    CASE om.status 
                        WHEN 'active' THEN 1
                        WHEN 'pending' THEN 2
                        ELSE 3
                    END, o.org_name ASC";
$my_orgs_stmt = $conn->prepare($my_orgs_sql);
$my_orgs_stmt->bind_param("i", $user_id);
$my_orgs_stmt->execute();
$my_organizations = $my_orgs_stmt->get_result();

// Get all other organizations
$other_orgs_sql = "SELECT o.*, 
                    (SELECT COUNT(*) FROM organization_memberships WHERE org_id = o.org_id AND status = 'active') as member_count
                   FROM organizations o
                   WHERE o.status = 'active' 
                   AND o.org_id NOT IN (
                       SELECT org_id FROM organization_memberships WHERE user_id = ?
                   )
                   ORDER BY o.org_name ASC";
$other_orgs_stmt = $conn->prepare($other_orgs_sql);
$other_orgs_stmt->bind_param("i", $user_id);
$other_orgs_stmt->execute();
$other_organizations = $other_orgs_stmt->get_result();

// Get unread notifications count
$notif_sql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$unread_count = $notif_result->fetch_assoc()['unread'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizations - Univents</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../style.css">
    <style>
        .org-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
            border: 1px solid rgba(79, 209, 197, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .org-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(79, 209, 197, 0.1);
            border-color: var(--primary-mint);
        }
        
        .org-header {
            height: 100px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .org-avatar {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            background: white;
            position: absolute;
            bottom: -35px;
            left: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: 3px solid white;
        }
        
        .org-avatar i {
            font-size: 2rem;
            color: var(--primary-mint);
        }
        
        .org-body {
            padding: 45px 20px 20px;
        }
        
        .org-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .org-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 0.85rem;
            color: var(--text-light);
        }
        
        .org-description {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: rgba(72, 187, 120, 0.1);
            color: #48BB78;
        }
        
        .status-badge.pending {
            background: rgba(246, 173, 85, 0.1);
            color: #F6AD55;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid rgba(79, 209, 197, 0.1);
            padding-bottom: 10px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .filter-tab:hover {
            background: rgba(79, 209, 197, 0.1);
        }
        
        .filter-tab.active {
            background: var(--primary-mint);
            color: white;
        }
    </style>
</head>
<body class="dashboard-body">
    
    <?php include 'nav_sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Organizations</h2>
                    <p class="text-muted">Discover and join student organizations</p>
                </div>
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
            
            <!-- My Organizations Section -->
            <?php if($my_organizations && $my_organizations->num_rows > 0): ?>
                <h4 class="mb-3">My Organizations</h4>
                <div class="row g-4 mb-5">
                    <?php while($org = $my_organizations->fetch_assoc()): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="org-card position-relative">
                                <div class="org-header" style="background: linear-gradient(135deg, <?php echo $org['org_color'] ?? '#4FD1C5'; ?> 0%, <?php echo $org['org_color'] ?? '#38B2AC'; ?> 100%);">
                                    <div class="status-badge <?php echo $org['membership_status']; ?>">
                                        <?php echo ucfirst($org['membership_status']); ?>
                                    </div>
                                </div>
                                <div class="org-avatar" style="color: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="org-body">
                                    <h3 class="org-title"><?php echo htmlspecialchars($org['org_name']); ?></h3>
                                    <div class="org-meta">
                                        <span><i class="far fa-calendar me-1"></i>Joined <?php echo date('M Y', strtotime($org['membership_date'])); ?></span>
                                    </div>
                                    <p class="org-description">
                                        <?php echo substr(htmlspecialchars($org['org_description'] ?? 'No description available.'), 0, 100); ?>...
                                    </p>
                                    <div class="d-flex gap-2">
                                        <a href="organization.php?id=<?php echo $org['org_id']; ?>" class="btn btn-primary flex-grow-1">
                                            <i class="fas fa-eye me-2"></i>View
                                        </a>
                                        <?php if($org['membership_status'] == 'active'): ?>
                                            <a href="?leave=<?php echo $org['org_id']; ?>" class="btn btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to leave this organization?')">
                                                <i class="fas fa-sign-out-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
            
            <!-- Discover Organizations Section -->
            <h4 class="mb-3">Discover Organizations</h4>
            
            <?php if($other_organizations && $other_organizations->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while($org = $other_organizations->fetch_assoc()): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="org-card">
                                <div class="org-header" style="background: linear-gradient(135deg, <?php echo $org['org_color'] ?? '#4FD1C5'; ?> 0%, <?php echo $org['org_color'] ?? '#38B2AC'; ?> 100%);"></div>
                                <div class="org-avatar" style="color: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="org-body">
                                    <h3 class="org-title"><?php echo htmlspecialchars($org['org_name']); ?></h3>
                                    <div class="org-meta">
                                        <span><i class="fas fa-users me-1"></i><?php echo $org['member_count']; ?> members</span>
                                    </div>
                                    <p class="org-description">
                                        <?php echo substr(htmlspecialchars($org['org_description'] ?? 'No description available.'), 0, 100); ?>...
                                    </p>
                                    <a href="?join=<?php echo $org['org_id']; ?>" class="btn btn-outline-primary w-100" 
                                       onclick="return confirm('Send membership request to this organization?')">
                                        <i class="fas fa-plus-circle me-2"></i>Join Organization
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State - Show sample organizations -->
                <div class="text-center py-5">
                    <i class="fas fa-building fa-4x text-muted mb-3"></i>
                    <h4>No Organizations Available</h4>
                    <p class="text-muted mb-4">Check back later for organizations to join</p>
                    
                    <!-- Sample Preview -->
                    <div class="row justify-content-center mt-4">
                        <div class="col-md-8">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Sample Organizations:</h5>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <div style="width: 40px; height: 40px; background: #4FD1C5; border-radius: 10px;" class="me-2"></div>
                                                <div>
                                                    <strong>Computer Science Society</strong><br>
                                                    <small class="text-muted">120+ members</small>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center mb-3">
                                                <div style="width: 40px; height: 40px; background: #F56565; border-radius: 10px;" class="me-2"></div>
                                                <div>
                                                    <strong>Student Government</strong><br>
                                                    <small class="text-muted">50+ members</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <div style="width: 40px; height: 40px; background: #9F7AEA; border-radius: 10px;" class="me-2"></div>
                                                <div>
                                                    <strong>Junior Entrepreneurs</strong><br>
                                                    <small class="text-muted">85+ members</small>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center mb-3">
                                                <div style="width: 40px; height: 40px; background: #48BB78; border-radius: 10px;" class="me-2"></div>
                                                <div>
                                                    <strong>Young Photographers</strong><br>
                                                    <small class="text-muted">60+ members</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-muted mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Organizations will appear here once added by administrators.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</div> <!-- Close dashboard-container -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Sidebar JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const collapseBtn = document.getElementById('collapseBtn');
        const menuToggle = document.getElementById('menuToggle');
        const orgDropdown = document.getElementById('orgDropdown');
        const orgMenu = document.getElementById('orgMenu');
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
        
        if (orgDropdown) {
            orgDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                orgMenu.classList.toggle('show');
                const arrow = this.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('fa-chevron-down');
                    arrow.classList.toggle('fa-chevron-up');
                }
            });
        }
        
        let notificationTimeout;
        if (notificationBtn && notificationMenu) {
            notificationBtn.addEventListener('mouseenter', function() {
                clearTimeout(notificationTimeout);
                notificationMenu.classList.add('show');
            });
            
            notificationBtn.addEventListener('mouseleave', function() {
                notificationTimeout = setTimeout(() => {
                    if (!notificationMenu.matches(':hover')) {
                        notificationMenu.classList.remove('show');
                    }
                }, 300);
            });
            
            notificationMenu.addEventListener('mouseenter', function() {
                clearTimeout(notificationTimeout);
            });
            
            notificationMenu.addEventListener('mouseleave', function() {
                notificationTimeout = setTimeout(() => {
                    if (!notificationMenu.classList.contains('stay')) {
                        notificationMenu.classList.remove('show');
                    }
                }, 300);
            });
            
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationMenu.classList.toggle('stay');
                notificationMenu.classList.add('show');
            });
            
            document.addEventListener('click', function(e) {
                if (!notificationDropdown.contains(e.target)) {
                    notificationMenu.classList.remove('show', 'stay');
                }
            });
        }
    });
</script>
</body>
</html>