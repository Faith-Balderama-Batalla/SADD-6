<?php
// index.php - Landing page
require_once 'config.php';

// Get featured organizations
$orgs_sql = "SELECT * FROM organizations WHERE status = 'active' LIMIT 4";
$orgs_result = $conn->query($orgs_sql);

// Get upcoming events
$events_sql = "SELECT e.*, o.org_name 
               FROM events e 
               JOIN organizations o ON e.org_id = o.org_id 
               WHERE e.status = 'upcoming' 
               ORDER BY e.start_datetime ASC 
               LIMIT 3";
$events_result = $conn->query($events_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Univents | College Event Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <div class="brand-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <span class="brand-text">Uni<span class="brand-highlight">vents</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#organizations">Organizations</a></li>
                <?php if (isLoggedIn()): ?>
                    <?php $user = getCurrentUser($conn); ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo $user['first_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($user['role'] == 'admin'): ?>
                                <li><a class="dropdown-item" href="Admin/dashboard.php">Admin Dashboard</a></li>
                            <?php elseif ($user['role'] == 'org_officer'): ?>
                                <li><a class="dropdown-item" href="Officer/dashboard.php">Officer Dashboard</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="Student/dashboard.php">Student Dashboard</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-primary me-2" href="login-signup.php?mode=login">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary" href="login-signup.php?mode=signup">Sign Up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section id="home" class="hero-section">
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-7 hero-content">
                <div class="welcome-badge">
                    <i class="fas fa-university me-2"></i> College Student Organizations
                </div>
                <h1 class="hero-title">
                    <span class="title-line">Your Campus Events,</span>
                    <span class="title-line gradient-text">All in One Place</span>
                </h1>
                <p class="hero-description">
                    Univents connects you with all student organizations. Stay updated with events, 
                    announcements, and merchandise - all secured behind a simple login.
                </p>
                <div class="feature-tags">
                    <span class="tag"><i class="fas fa-bullhorn me-2"></i>Announcements</span>
                    <span class="tag"><i class="fas fa-calendar me-2"></i>Events</span>
                    <span class="tag"><i class="fas fa-qrcode me-2"></i>QR Attendance</span>
                    <span class="tag"><i class="fas fa-users me-2"></i>Organizations</span>
                </div>
                <?php if (!isLoggedIn()): ?>
                    <div class="hero-cta">
                        <a href="login-signup.php?mode=signup" class="btn btn-primary btn-lg me-3">
                            Get Started <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                    <div class="stats-container">
                        <div class="stat-item"><div class="stat-number">10+</div><div class="stat-label">Organizations</div></div>
                        <div class="stat-item"><div class="stat-number">500+</div><div class="stat-label">Students</div></div>
                        <div class="stat-item"><div class="stat-number">50+</div><div class="stat-label">Events/Year</div></div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-5 hero-image">
                <div class="login-preview-card">
                    <div class="preview-header"><i class="fas fa-lock"></i><h3>Login Required</h3></div>
                    <p>Access events, announcements, and organization details after logging in.</p>
                    <div class="preview-features">
                        <div class="preview-feature"><i class="fas fa-check-circle"></i><span>View event calendar</span></div>
                        <div class="preview-feature"><i class="fas fa-check-circle"></i><span>See announcements</span></div>
                        <div class="preview-feature"><i class="fas fa-check-circle"></i><span>Track attendance</span></div>
                    </div>
                    <div class="preview-cta">
                        <a href="login-signup.php?mode=login" class="btn btn-primary w-100">Login to Access</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="features-section">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-badge">FEATURES</span>
            <h2 class="section-title">Everything you need, <span class="gradient-text">secured</span></h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-bullhorn"></i></div>
                    <h3>Announcements</h3>
                    <p>Stay updated with the latest news from your organizations.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-calendar-alt"></i></div>
                    <h3>Event Calendar</h3>
                    <p>View all upcoming events across different organizations.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-qrcode"></i></div>
                    <h3>QR Attendance</h3>
                    <p>Fast and secure attendance tracking with QR codes.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Organizations Preview -->
<section id="organizations" class="organizations-section">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-badge">ORGANIZATIONS</span>
            <h2 class="section-title">Active <span class="gradient-text">Organizations</span></h2>
        </div>
        <div class="row g-4">
            <?php if ($orgs_result && $orgs_result->num_rows > 0): ?>
                <?php while($org = $orgs_result->fetch_assoc()): ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="org-card">
                            <div class="org-logo">
                                <div class="org-logo-placeholder"><i class="fas fa-users"></i></div>
                            </div>
                            <h4><?php echo $org['org_name']; ?></h4>
                            <p class="org-description"><?php echo substr($org['org_description'] ?? 'Student organization', 0, 60); ?>...</p>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p class="text-muted">Organizations will appear here once added.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="footer-brand"><i class="fas fa-calendar-check footer-icon"></i><span>Univents</span></div>
                <p class="footer-text">Centralized event management system for college organizations.</p>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h5>Platform</h5>
                <ul class="footer-links"><li><a href="#features">Features</a></li><li><a href="#organizations">Organizations</a></li></ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h5>Support</h5>
                <ul class="footer-links"><li><a href="#">Help Center</a></li><li><a href="#">Contact</a></li></ul>
            </div>
        </div>
        <div class="footer-bottom"><p>&copy; 2026 Univents. All rights reserved.</p></div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>