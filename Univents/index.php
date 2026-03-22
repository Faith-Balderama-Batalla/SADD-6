<?php
// index.php - Landing page (Simplified with Visual-Only Dark Mode Button)
require_once 'config.php';

// Get dynamic stats
$orgs_count = $conn->query("SELECT COUNT(*) as count FROM organizations WHERE status = 'active'")->fetch_assoc()['count'];
$users_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role != 'admin'")->fetch_assoc()['count'];
$events_count = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'upcoming'")->fetch_assoc()['count'];

// Get featured organizations
$orgs_sql = "SELECT * FROM organizations WHERE status = 'active' LIMIT 4";
$orgs_result = $conn->query($orgs_sql);

// Get upcoming events (only for logged-in users)
if (isLoggedIn()) {
    $user = getCurrentUser($conn);
    $user_id = $user['user_id'];
    
    $events_sql = "SELECT e.*, o.org_name, o.org_color 
                   FROM events e 
                   JOIN organizations o ON e.org_id = o.org_id 
                   JOIN organization_memberships om ON o.org_id = om.org_id 
                   WHERE om.user_id = ? AND om.status = 'active' 
                     AND e.status = 'upcoming' 
                   ORDER BY e.start_datetime ASC 
                   LIMIT 3";
    $events_stmt = $conn->prepare($events_sql);
    $events_stmt->bind_param("i", $user_id);
    $events_stmt->execute();
    $events_result = $events_stmt->get_result();
}
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
    <style>
        /* ==================== ENHANCED LANDING PAGE STYLES ==================== */
        
        /* Body Background - Light Mint */
        body {
            background: #F0FFF4;
        }
        
        /* ==================== NAVBAR - Sticky with Blur ==================== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background: rgba(240, 255, 244, 0.95);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            padding: 1rem 0;
        }
        
        .navbar.scrolled {
            background: rgba(240, 255, 244, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
            padding: 0.75rem 0;
        }
        
        /* ==================== NAVBAR BRAND - Logo and Text Side by Side ==================== */
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }
        
        .brand-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }
        
        .brand-icon:hover {
            transform: rotate(5deg);
        }
        
        .brand-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2D3748;
            white-space: nowrap;
        }
        
        .brand-highlight {
            color: #4FD1C5;
        }
        
        /* ==================== DARK MODE TOGGLE BUTTON (Visual Only - Phase 2) ==================== */
        .theme-toggle-btn {
            background: rgba(79, 209, 197, 0.1);
            border: 1px solid rgba(79, 209, 197, 0.3);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #4FD1C5;
            font-size: 1.2rem;
            margin-left: 0.5rem;
        }
        
        .theme-toggle-btn:hover {
            background: #4FD1C5;
            color: white;
            transform: rotate(15deg);
            border-color: transparent;
        }
        
        /* Fix for fixed navbar - Add padding to hero section */
        .hero-section {
            padding-top: 100px;
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding-top: 80px;
            }
            
            .brand-text {
                font-size: 1.2rem;
            }
            
            .brand-icon {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }
            
            .theme-toggle-btn {
                width: 36px;
                height: 36px;
                font-size: 1rem;
                margin: 0.5rem 0;
            }
        }
        
        /* ==================== BUTTONS - Mint Outline ==================== */
        .btn-outline-primary {
            border: 2px solid #4FD1C5;
            color: #4FD1C5;
            background: transparent;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #4FD1C5 0%, #38B2AC 100%);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 209, 197, 0.3);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4FD1C5 0%, #38B2AC 100%);
            color: white;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 209, 197, 0.3);
        }
        
        /* ==================== CARDS - Enhanced Shadows & Borders ==================== */
        .feature-card, .org-card, .event-card, .stat-card {
            background: white;
            border: 1px solid rgba(79, 209, 197, 0.15);
            border-radius: 24px;
            padding: 2rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            height: 100%;
        }
        
        .feature-card:hover, .org-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(79, 209, 197, 0.15);
            border-color: #4FD1C5;
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            background: rgba(79, 209, 197, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            background: linear-gradient(135deg, #4FD1C5 0%, #38B2AC 100%);
            transform: scale(1.1);
        }
        
        .feature-icon i {
            font-size: 2rem;
            color: #4FD1C5;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon i {
            color: white;
        }
        
        /* ==================== SECTION BACKGROUNDS - Alternating ==================== */
        .hero-section {
            background: linear-gradient(135deg, #FFFFFF 0%, #E6FFFA 100%);
            position: relative;
            overflow: hidden;
        }
        
        .features-section {
            background: white;
            border-radius: 40px;
            margin: 2rem 0;
            padding: 4rem 0;
        }
        
        .organizations-section {
            background: transparent;
            padding: 4rem 0;
        }
        
        .events-preview-section {
            background: white;
            border-radius: 40px;
            margin: 2rem 0;
            padding: 4rem 0;
        }
        
        .cta-section {
            padding: 4rem 0;
        }
        
        /* ==================== SECTION HEADERS ==================== */
        .section-header {
            margin-bottom: 3rem;
        }
        
        .section-badge {
            background: rgba(79, 209, 197, 0.1);
            color: #4FD1C5;
            padding: 5px 15px;
            border-radius: 50px;
            display: inline-block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
            letter-spacing: 0.5px;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: #2D3748;
        }
        
        .section-subtitle {
            color: #718096;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #4FD1C5 0%, #38B2AC 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* ==================== HERO SECTION ==================== */
        .welcome-badge {
            background: rgba(79, 209, 197, 0.1);
            padding: 8px 20px;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 1.5rem;
            font-weight: 500;
            color: #4FD1C5;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            color: #2D3748;
        }
        
        .hero-description {
            font-size: 1.1rem;
            color: #718096;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .feature-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .tag {
            background: white;
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(79, 209, 197, 0.2);
            color: #4A5568;
        }
        
        .tag:hover {
            transform: translateY(-2px);
            border-color: #4FD1C5;
            color: #4FD1C5;
        }
        
        /* ==================== STATS CONTAINER ==================== */
        .stats-container {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(79, 209, 197, 0.2);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #4FD1C5;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #718096;
        }
        
        /* ==================== LOGIN PREVIEW CARD ==================== */
        .login-preview-card {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            text-align: center;
            border: 1px solid rgba(79, 209, 197, 0.15);
            transition: all 0.3s ease;
        }
        
        .login-preview-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(79, 209, 197, 0.15);
        }
        
        .preview-header i {
            font-size: 3rem;
            color: #4FD1C5;
            margin-bottom: 1rem;
        }
        
        .preview-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0;
            color: #2D3748;
        }
        
        .preview-features {
            text-align: left;
            margin: 1.5rem 0;
        }
        
        .preview-feature {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            background: rgba(79, 209, 197, 0.05);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .preview-feature:hover {
            background: rgba(79, 209, 197, 0.1);
            transform: translateX(5px);
        }
        
        .preview-feature i {
            color: #4FD1C5;
            font-size: 1rem;
        }
        
        .preview-feature span {
            color: #4A5568;
        }
        
        .preview-cta {
            margin-top: 1.5rem;
        }
        
        /* ==================== ORGANIZATION CARDS ==================== */
        .org-logo-placeholder {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4FD1C5 0%, #38B2AC 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
            transition: all 0.3s ease;
        }
        
        .org-card:hover .org-logo-placeholder {
            transform: scale(1.1);
        }
        
        .org-card h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2D3748;
        }
        
        .org-description {
            font-size: 0.85rem;
            color: #718096;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        
        .feature-lock {
            margin-top: 1rem;
            font-size: 0.8rem;
            color: #718096;
            background: rgba(0, 0, 0, 0.05);
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }
        
        .coming-soon-badge {
            display: inline-block;
            background: linear-gradient(135deg, #F6AD55, #ED8936);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
            vertical-align: middle;
        }
        
        /* ==================== EVENT LIST ==================== */
        .event-list {
            margin-top: 1.5rem;
        }
        
        .event-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: rgba(79, 209, 197, 0.05);
            border-radius: 16px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }
        
        .event-item:hover {
            background: rgba(79, 209, 197, 0.1);
            transform: translateX(5px);
            border-color: rgba(79, 209, 197, 0.2);
        }
        
        .event-date {
            min-width: 60px;
            text-align: center;
            background: white;
            border-radius: 12px;
            padding: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .event-date .day {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4FD1C5;
            line-height: 1;
        }
        
        .event-date .month {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #718096;
        }
        
        .event-details h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #2D3748;
        }
        
        .event-details p {
            font-size: 0.8rem;
            color: #718096;
            margin-bottom: 0.25rem;
        }
        
        .org-tag {
            font-size: 0.7rem;
            color: #4FD1C5;
            background: rgba(79, 209, 197, 0.1);
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .calendar-preview {
            background: linear-gradient(135deg, rgba(79, 209, 197, 0.1), rgba(56, 178, 172, 0.05));
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .calendar-preview:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(79, 209, 197, 0.1);
        }
        
        .calendar-preview i {
            color: #4FD1C5;
        }
        
        .calendar-preview h4 {
            color: #2D3748;
        }
        
        /* ==================== CTA SECTION ==================== */
        .cta-card {
            background: linear-gradient(135deg, #4FD1C5, #38B2AC);
            border-radius: 30px;
            padding: 3rem;
            color: white;
            transition: all 0.3s ease;
        }
        
        .cta-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(79, 209, 197, 0.3);
        }
        
        .cta-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .cta-text {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .btn-light {
            background: white;
            color: #4FD1C5;
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .btn-light:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            color: #38B2AC;
        }
        
        .cta-small {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        /* ==================== FOOTER - Dark Background ==================== */
        .footer {
            background: #0a0a0a;
            color: #a0a0a0;
            padding: 4rem 0 1.5rem;
            margin-top: 2rem;
        }
        
        .footer-brand {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
        }
        
        .footer-brand i {
            color: #4FD1C5;
            margin-right: 0.5rem;
        }
        
        .footer-text {
            color: #a0a0a0;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
        }
        
        .social-links a {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a0a0a0;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: #4FD1C5;
            color: white;
            transform: translateY(-3px);
        }
        
        .footer h5 {
            color: white;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
        }
        
        .footer-links li {
            margin-bottom: 0.5rem;
        }
        
        .footer-links a {
            color: #a0a0a0;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-links a:hover {
            color: #4FD1C5;
            padding-left: 5px;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 992px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .cta-card {
                padding: 2rem;
                text-align: center;
            }
            
            .stats-container {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .feature-card, .org-card {
                padding: 1.5rem;
            }
            
            .features-section, .events-preview-section {
                padding: 2rem 0;
                margin: 1rem 0;
            }
        }
    </style>
</head>
<body>

<!-- Navigation with Visual-Only Dark Mode Toggle -->
<nav class="navbar navbar-expand-lg fixed-top" id="mainNavbar">
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
                
                <!-- Dark Mode Toggle Button (Visual Only - Phase 2) -->
                <li class="nav-item">
                    <button class="theme-toggle-btn" id="themeToggle" aria-label="Dark mode coming in Phase 2" onclick="alert('Dark mode feature will be available in Phase 2!')">
                        <i class="fas fa-moon"></i>
                    </button>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <?php $user = getCurrentUser($conn); ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> 
                            <?php echo $user['first_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php if($user['role'] == 'admin'): ?>
                                <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</a></li>
                            <?php elseif($user['role'] == 'org_officer'): ?>
                                <li><a class="dropdown-item" href="officer/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Officer Dashboard</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="student/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="student/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-outline-primary me-2" href="login-signup.php?mode=login">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary" href="login-signup.php?step=select">Sign Up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section id="home" class="hero-section">
    <div class="container">
        <div class="row align-items-center">
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
                        <a href="login-signup.php?step=select" class="btn btn-primary btn-lg me-3">
                            Join Univents <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                        <a href="#features" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-play me-2"></i>Learn More
                        </a>
                    </div>

                    <!-- Dynamic Stats -->
                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo max($orgs_count, 10); ?>+</div>
                            <div class="stat-label">Organizations</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo max($users_count, 500); ?>+</div>
                            <div class="stat-label">Students</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo max($events_count, 50); ?>+</div>
                            <div class="stat-label">Events/Year</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="hero-cta">
                        <a href="<?php 
                            $user = getCurrentUser($conn);
                            if($user['role'] == 'admin') echo 'admin/dashboard.php';
                            elseif($user['role'] == 'org_officer') echo 'officer/dashboard.php';
                            else echo 'student/dashboard.php';
                        ?>" class="btn btn-primary btn-lg">
                            Go to Dashboard <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-5 hero-image">
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
                            <p class="mt-2">Don't have an account? <a href="login-signup.php?step=select">Sign up</a></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="welcome-preview text-center">
                        <div class="login-preview-card" style="background: rgba(79, 209, 197, 0.1);">
                            <i class="fas fa-smile-wink fa-4x mb-3" style="color: #4FD1C5;"></i>
                            <h3>Welcome back!</h3>
                            <p>You're logged in. Visit your dashboard to see upcoming events.</p>
                            <a href="<?php 
                                $user = getCurrentUser($conn);
                                if($user['role'] == 'admin') echo 'admin/dashboard.php';
                                elseif($user['role'] == 'org_officer') echo 'officer/dashboard.php';
                                else echo 'student/dashboard.php';
                            ?>" class="btn btn-primary mt-2">Go to Dashboard</a>
                        </div>
                    </div>
                <?php endif; ?>
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
                    <span class="coming-soon-badge">Coming Soon</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Organizations Section -->
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
                            <div class="org-logo-placeholder" style="background: <?php echo $org['org_color'] ?? '#4FD1C5'; ?>;">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4><?php echo htmlspecialchars($org['org_name']); ?></h4>
                            <p class="org-description"><?php echo substr(htmlspecialchars($org['org_description'] ?? 'Student organization at BU Polangui'), 0, 60); ?>...</p>
                            <?php if (!isLoggedIn()): ?>
                                <button class="btn btn-outline-primary w-100 mt-3" onclick="location.href='login-signup.php?mode=login'">
                                    <i class="fas fa-lock me-2"></i>Login to Follow
                                </button>
                            <?php else: ?>
                                <button class="btn btn-outline-primary w-100 mt-3" onclick="location.href='student/organizations.php'">
                                    View Organization
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <!-- Sample orgs for preview when DB is empty -->
                <div class="col-lg-3 col-md-6">
                    <div class="org-card">
                        <div class="org-logo-placeholder" style="background: #4FD1C5;">
                            <i class="fas fa-laptop-code"></i>
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
                        <div class="org-logo-placeholder" style="background: #F6AD55;">
                            <i class="fas fa-landmark"></i>
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
                        <div class="org-logo-placeholder" style="background: #9F7AEA;">
                            <i class="fas fa-chart-line"></i>
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
                        <div class="org-logo-placeholder" style="background: #48BB78;">
                            <i class="fas fa-camera"></i>
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

<!-- Events Preview - Only visible to logged-in users -->
<?php if (isLoggedIn() && isset($events_result) && $events_result->num_rows > 0): ?>
<section class="events-preview-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <span class="section-badge">CALENDAR</span>
                <h2 class="section-title">Upcoming <span class="gradient-text">Events</span></h2>
                <p class="section-subtitle">Events you can attend as a member</p>
                
                <div class="event-list">
                    <?php while($event = $events_result->fetch_assoc()): ?>
                        <div class="event-item" onclick="window.location.href='student/events.php?register=<?php echo $event['event_id']; ?>'">
                            <div class="event-date">
                                <span class="day"><?php echo date('d', strtotime($event['start_datetime'])); ?></span>
                                <span class="month"><?php echo date('M', strtotime($event['start_datetime'])); ?></span>
                            </div>
                            <div class="event-details">
                                <h4><?php echo htmlspecialchars($event['event_title']); ?></h4>
                                <p><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($event['venue']); ?></p>
                                <span class="org-tag"><?php echo htmlspecialchars($event['org_name']); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="calendar-preview">
                    <i class="fas fa-calendar-alt fa-4x mb-3"></i>
                    <h4>View Full Calendar</h4>
                    <p class="text-muted">See all your organization's events in one place</p>
                    <a href="student/calendar.php" class="btn btn-outline-primary mt-2">
                        View Calendar <i class="fas fa-arrow-right ms-2"></i>
                    </a>
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
                        <a href="login-signup.php?step=select" class="btn btn-light btn-lg">Get Started</a>
                        <p class="cta-small mt-2">Free for BU Polangui students</p>
                    <?php else: ?>
                        <?php $user = getCurrentUser($conn); ?>
                        <a href="<?php 
                            if($user['role'] == 'admin') echo 'admin/dashboard.php';
                            elseif($user['role'] == 'org_officer') echo 'officer/dashboard.php';
                            else echo 'student/dashboard.php';
                        ?>" class="btn btn-light btn-lg">Go to Dashboard</a>
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
                    <i class="fas fa-calendar-check"></i>
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

<!-- Navbar Scroll Effect -->
<script>
    // Navbar color change on scroll
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('mainNavbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const offset = 80;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - offset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
</script>
</body>
</html>