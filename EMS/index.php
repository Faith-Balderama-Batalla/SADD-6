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
    <title>Univents | BU Polangui Event Management System</title>
    
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
                <li class="nav-item">
                    <a class="nav-link active" href="#home">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#organizations">Organizations</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#about">About</a>
                </li>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> 
                            <?php 
                                $user = getCurrentUser($conn);
                                echo $user['first_name'];
                            ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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
                    <i class="fas fa-university me-2"></i> BU Polangui Student Organizations
                </div>
                <h1 class="hero-title">
                    <span class="title-line">Your Campus Events,</span>
                    <span class="title-line gradient-text">All in One Place</span>
                </h1>
                <p class="hero-description">
                    Univents connects you with all student organizations at BU Polangui. 
                    Stay updated with events, announcements, and merchandise - all secured 
                    behind a simple login.
                </p>
                
                <div class="feature-tags">
                    <span class="tag"><i class="fas fa-bullhorn me-2"></i>Announcements</span>
                    <span class="tag"><i class="fas fa-calendar me-2"></i>Events</span>
                    <span class="tag"><i class="fas fa-tshirt me-2"></i>Merchandise</span>
                    <span class="tag"><i class="fas fa-users me-2"></i>Organizations</span>
                </div>

                <?php if (!isLoggedIn()): ?>
                    <div class="hero-cta">
                        <a href="login-signup.php?mode=signup" class="btn btn-primary btn-lg me-3">
                            Join Univents <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                        <a href="#features" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-play me-2"></i>Learn More
                        </a>
                    </div>

                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-number">10+</div>
                            <div class="stat-label">Organizations</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">500+</div>
                            <div class="stat-label">Students</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">50+</div>
                            <div class="stat-label">Events/Year</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="hero-cta">
                        <a href="dashboard.php" class="btn btn-primary btn-lg">
                            Go to Dashboard <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-5 hero-image">
                <!-- Right side now shows login prompt or preview -->
                <?php if (!isLoggedIn()): ?>
                    <div class="login-preview-card">
                        <div class="preview-header">
                            <i class="fas fa-lock"></i>
                            <h3>Login Required</h3>
                        </div>
                        <p>To view events, announcements, and organization details, please login or create an account.</p>
                        <div class="preview-features">
                            <div class="preview-feature">
                                <i class="fas fa-check-circle"></i>
                                <span>View event calendar</span>
                            </div>
                            <div class="preview-feature">
                                <i class="fas fa-check-circle"></i>
                                <span>See organization announcements</span>
                            </div>
                            <div class="preview-feature">
                                <i class="fas fa-check-circle"></i>
                                <span>Browse merchandise</span>
                            </div>
                            <div class="preview-feature">
                                <i class="fas fa-check-circle"></i>
                                <span>Track attendance</span>
                            </div>
                        </div>
                        <div class="preview-cta">
                            <a href="login-signup.php?mode=login" class="btn btn-primary w-100">Login to Access</a>
                            <p class="mt-2">Don't have an account? <a href="login-signup.php?mode=signup">Sign up</a></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="welcome-preview">
                        <img src="https://via.placeholder.com/500x400/E6FFFA/2C7A7B?text=Welcome+to+Univents" alt="Welcome" class="img-fluid">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Features Section - Preview Only -->
<section id="features" class="features-section">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-badge">FEATURES</span>
            <h2 class="section-title">Everything you need, <span class="gradient-text">secured</span></h2>
            <p class="section-subtitle">All features require login to ensure privacy and security for BU Polangui students</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <h3>Organization Announcements</h3>
                    <p>Stay updated with the latest news from all your followed organizations.</p>
                    <?php if (!isLoggedIn()): ?>
                        <div class="feature-lock">
                            <i class="fas fa-lock"></i> Login required
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Event Calendar</h3>
                    <p>View all upcoming events across different organizations.</p>
                    <?php if (!isLoggedIn()): ?>
                        <div class="feature-lock">
                            <i class="fas fa-lock"></i> Login required
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tshirt"></i>
                    </div>
                    <h3>Merchandise Showcase</h3>
                    <p>Browse and discover organization merchandise and booth locations.</p>
                    <?php if (!isLoggedIn()): ?>
                        <div class="feature-lock">
                            <i class="fas fa-lock"></i> Login required
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Organizations Preview (Limited) -->
<section id="organizations" class="organizations-section">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-badge">ORGANIZATIONS</span>
            <h2 class="section-title">Active <span class="gradient-text">Student Organizations</span></h2>
            <p class="section-subtitle">Login to follow organizations and get updates</p>
        </div>

        <div class="row g-4">
            <?php if ($orgs_result && $orgs_result->num_rows > 0): ?>
                <?php while($org = $orgs_result->fetch_assoc()): ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="org-card">
                            <div class="org-logo">
                                <?php if($org['org_logo']): ?>
                                    <img src="<?php echo $org['org_logo']; ?>" alt="<?php echo $org['org_name']; ?>">
                                <?php else: ?>
                                    <div class="org-logo-placeholder">
                                        <i class="fas fa-users"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h4><?php echo $org['org_name']; ?></h4>
                            <p class="org-description"><?php echo substr($org['org_description'] ?? 'Student organization at BU Polangui', 0, 60); ?>...</p>
                            <?php if (!isLoggedIn()): ?>
                                <button class="btn btn-outline-primary w-100 mt-3" onclick="location.href='login-signup.php?mode=login'">
                                    <i class="fas fa-lock me-2"></i>Login to Follow
                                </button>
                            <?php else: ?>
                                <button class="btn btn-outline-primary w-100 mt-3">Follow</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <!-- Sample orgs for preview when DB is empty -->
                <div class="col-lg-3 col-md-6">
                    <div class="org-card">
                        <div class="org-logo">
                            <div class="org-logo-placeholder">
                                <i class="fas fa-laptop-code"></i>
                            </div>
                        </div>
                        <h4>Computer Science Society</h4>
                        <p class="org-description">Organization for CS students at BU Polangui...</p>
                        <button class="btn btn-outline-primary w-100 mt-3" onclick="location.href='login-signup.php?mode=login'">
                            <i class="fas fa-lock me-2"></i>Login to Follow
                        </button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="org-card">
                        <div class="org-logo">
                            <div class="org-logo-placeholder">
                                <i class="fas fa-landmark"></i>
                            </div>
                        </div>
                        <h4>Student Government</h4>
                        <p class="org-description">Official student government of BU Polangui...</p>
                        <button class="btn btn-outline-primary w-100 mt-3" onclick="location.href='login-signup.php?mode=login'">
                            <i class="fas fa-lock me-2"></i>Login to Follow
                        </button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="org-card">
                        <div class="org-logo">
                            <div class="org-logo-placeholder">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        <h4>Junior Entrepreneurs</h4>
                        <p class="org-description">Future business leaders of BU Polangui...</p>
                        <button class="btn btn-outline-primary w-100 mt-3" onclick="location.href='login-signup.php?mode=login'">
                            <i class="fas fa-lock me-2"></i>Login to Follow
                        </button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="org-card">
                        <div class="org-logo">
                            <div class="org-logo-placeholder">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <h4>Young Photographers</h4>
                        <p class="org-description">Photography enthusiasts at BU Polangui...</p>
                        <button class="btn btn-outline-primary w-100 mt-3" onclick="location.href='login-signup.php?mode=login'">
                            <i class="fas fa-lock me-2"></i>Login to Follow
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Events Preview - Hidden from non-logged in users -->
<?php if (isLoggedIn()): ?>
<section class="events-preview-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <span class="section-badge">CALENDAR</span>
                <h2 class="section-title">Upcoming <span class="gradient-text">Events</span></h2>
                <p class="section-subtitle">Events you can attend as a member</p>
                
                <div class="event-list">
                    <?php if ($events_result && $events_result->num_rows > 0): ?>
                        <?php while($event = $events_result->fetch_assoc()): ?>
                            <div class="event-item">
                                <div class="event-date">
                                    <span class="day"><?php echo date('d', strtotime($event['start_datetime'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($event['start_datetime'])); ?></span>
                                </div>
                                <div class="event-details">
                                    <h4><?php echo $event['event_title']; ?></h4>
                                    <p><i class="fas fa-map-marker-alt me-2"></i><?php echo $event['venue']; ?></p>
                                    <span class="org-tag"><?php echo $event['org_name']; ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted">No upcoming events at the moment.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="calendar-preview">
                    <img src="https://via.placeholder.com/500x400/E6FFFA/2C7A7B?text=Event+Calendar" alt="Calendar Preview" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="cta-card">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="cta-title">Join Univents today</h2>
                    <p class="cta-text">Connect with your organizations, never miss an event, and be part of the BU Polangui community.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <?php if (!isLoggedIn()): ?>
                        <a href="login-signup.php?mode=signup" class="btn btn-light btn-lg">Get Started</a>
                        <p class="cta-small mt-2">Free for BU Polangui students</p>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-light btn-lg">Go to Dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="footer-brand">
                    <i class="fas fa-calendar-check footer-icon"></i>
                    <span>Univents</span>
                </div>
                <p class="footer-text">BU Polangui's centralized event management system for student organizations.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h5>Platform</h5>
                <ul class="footer-links">
                    <li><a href="#features">Features</a></li>
                    <li><a href="#organizations">Organizations</a></li>
                    <li><a href="#">Events</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h5>Support</h5>
                <ul class="footer-links">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">FAQs</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h5>Legal</h5>
                <ul class="footer-links">
                    <li><a href="#">Privacy</a></li>
                    <li><a href="#">Terms</a></li>
                </ul>
            </div>
            <div class="col-lg-2 mb-4">
                <h5>BU Polangui</h5>
                <ul class="footer-links">
                    <li><a href="#">About BU</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Univents - BU Polangui. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>