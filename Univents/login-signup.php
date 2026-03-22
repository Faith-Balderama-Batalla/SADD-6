<?php
// login-signup.php - Secure Role-Based Login & Signup with Card-Based Design
require_once 'config.php';

$error = '';
$success = '';
$selected_role = isset($_GET['role']) ? $_GET['role'] : '';
$step = isset($_GET['step']) ? $_GET['step'] : 'select'; // select, register, login

// ==================== LOGIN HANDLING ====================
if (isset($_POST['login']) && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (!checkRateLimit($conn, $ip)) {
        $error = 'Too many failed attempts. Please try again later.';
    } else {
        $id_number = mysqli_real_escape_string($conn, trim($_POST['id_number']));
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? true : false;
        
        // Use prepared statement for secure login
        $stmt = $conn->prepare("SELECT * FROM users WHERE (id_number = ? OR email = ?) AND status != 'inactive'");
        $stmt->bind_param("ss", $id_number, $id_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                resetLoginAttempts($conn, $ip);
                
                if ($user['status'] === 'pending') {
                    $error = 'Your account is pending approval. Please wait for admin confirmation.';
                } else {
                    regenerateSession();
                    
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    
                    // Set session timeout based on remember me
                    if ($remember) {
                        $_SESSION['last_activity'] = time();
                        ini_set('session.cookie_lifetime', 2592000); // 30 days
                    }
                    
                    // Role-based redirect
                    switch($user['role']) {
                        case 'admin':
                            header('Location: admin/dashboard.php');
                            break;
                        case 'org_officer':
                            header('Location: officer/dashboard.php');
                            break;
                        default:
                            header('Location: student/dashboard.php');
                            break;
                    }
                    exit();
                }
            } else {
                recordLoginAttempt($conn, $ip, $user['user_id']);
                $error = 'Invalid password';
            }
        } else {
            recordLoginAttempt($conn, $ip);
            $error = 'User not found or account inactive';
        }
        $stmt->close();
    }
}

// ==================== SIGNUP HANDLING ====================
if (isset($_POST['signup']) && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $id_number = mysqli_real_escape_string($conn, trim($_POST['id_number']));
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // ==================== VALIDATION ====================
    if (empty($id_number)) $errors[] = "ID Number is required";
    if (empty($first_name)) $errors[] = "First Name is required";
    if (empty($last_name)) $errors[] = "Last Name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    
    // Password validation
    $password_errors = validatePassword($password);
    if (!empty($password_errors)) {
        $errors = array_merge($errors, $password_errors);
    }
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    
    // Role-specific validation
    $valid_roles = ['student_member', 'org_officer'];
    if (!in_array($role, $valid_roles)) {
        $errors[] = "Invalid role selected";
    }
    
    if ($role === 'org_officer') {
        $organization_id = intval($_POST['organization_id']);
        $position = mysqli_real_escape_string($conn, $_POST['position']);
        if (empty($organization_id)) $errors[] = "Please select an organization";
        if (empty($position)) $errors[] = "Please enter your position";
    } else {
        $course = mysqli_real_escape_string($conn, $_POST['course']);
        $year_level = intval($_POST['year_level']);
        $block = mysqli_real_escape_string($conn, $_POST['block']);
        
        if (empty($course)) $errors[] = "Course is required";
        if ($year_level < 1 || $year_level > 4) $errors[] = "Valid year level is required";
        if (empty($block)) $errors[] = "Block is required";
    }
    
    // ==================== DUPLICATE CHECK ====================
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE id_number = ? OR email = ?");
        $check_stmt->bind_param("ss", $id_number, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = 'ID number or email already exists';
        }
        $check_stmt->close();
    }
    
    // ==================== INSERT USER ====================
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $status = ($role === 'student_member') ? 'active' : 'pending';
        
        if ($role === 'student_member') {
            $stmt = $conn->prepare("INSERT INTO users (id_number, first_name, last_name, email, password, role, status, course, year_level, block) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssis", $id_number, $first_name, $last_name, $email, $hashed_password, $role, $status, $course, $year_level, $block);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (id_number, first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $id_number, $first_name, $last_name, $email, $hashed_password, $role, $status);
        }
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // ==================== OFFICER APPLICATION ====================
            if ($role === 'org_officer') {
                $pending_stmt = $conn->prepare("INSERT INTO pending_officers (user_id, requested_role, organization_id, request_date) VALUES (?, 'org_officer', ?, NOW())");
                $pending_stmt->bind_param("ii", $user_id, $organization_id);
                $pending_stmt->execute();
                $pending_stmt->close();
                
                // Notify all admins
                $admin_stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'admin'");
                $admin_stmt->execute();
                $admin_result = $admin_stmt->get_result();
                while ($admin = $admin_result->fetch_assoc()) {
                    createNotification($conn, $admin['user_id'], 'New Officer Application', "$first_name $last_name has applied to become an organization officer.", 'system', $user_id);
                }
                $admin_stmt->close();
                
                $success = 'Application submitted! Your account will be activated after admin approval.';
            } else {
                // Create welcome notification for student
                createNotification($conn, $user_id, 'Welcome to Univents!', 'Your account has been created successfully. Start exploring organizations and events!', 'system');
                $success = 'Account created successfully! You can now login.';
            }
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
        $stmt->close();
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// ==================== DETERMINE WHICH VIEW TO SHOW ====================
$show_login = false;
if (isset($_GET['mode']) && $_GET['mode'] == 'login') {
    $show_login = true;
    $step = 'login';
} elseif (isset($_GET['role']) && in_array($_GET['role'], ['student_member', 'org_officer'])) {
    $step = 'register';
    $selected_role = $_GET['role'];
} elseif (isset($_POST['signup']) && empty($success)) {
    $step = 'register';
    $selected_role = $_POST['role'] ?? '';
} elseif (isset($_POST['login'])) {
    $show_login = true;
    $step = 'login';
}

// Get organizations for dropdown
$organizations = [];
if ($step == 'register' && $selected_role == 'org_officer') {
    $org_stmt = $conn->prepare("SELECT org_id, org_name FROM organizations WHERE status = 'active' ORDER BY org_name");
    $org_stmt->execute();
    $org_result = $org_stmt->get_result();
    while($org = $org_result->fetch_assoc()) {
        $organizations[] = $org;
    }
    $org_stmt->close();
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Univents - <?php echo $show_login ? 'Login' : ($step == 'select' ? 'Choose Role' : 'Sign Up'); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* ==================== AUTH PAGE STYLES ==================== */
        .auth-page {
            min-height: 100vh;
            background: #F0FFF4;
            font-family: 'Inter', sans-serif;
        }
        
        /* Container */
        .auth-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        /* Main Card */
        .auth-card {
            background: white;
            border-radius: 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            border: 1px solid rgba(79, 209, 197, 0.15);
        }
        
        /* Left Side - Branding */
        .auth-branding {
            flex: 0 0 40%;
            background: linear-gradient(135deg, rgba(79, 209, 197, 0.1), rgba(56, 178, 172, 0.05));
            padding: 3rem 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .auth-branding::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(79, 209, 197, 0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: moveBackground 30s linear infinite;
        }
        
        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .brand-logo {
            position: relative;
            z-index: 1;
        }
        
        .brand-icon-large {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4FD1C5, #38B2AC);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        
        .brand-icon-large i {
            font-size: 1.8rem;
            color: white;
        }
        
        .brand-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2D3748;
        }
        
        .brand-tagline {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        
        .brand-features {
            list-style: none;
            padding: 0;
            margin-top: 2rem;
        }
        
        .brand-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: #4A5568;
            font-size: 0.9rem;
        }
        
        .brand-features li i {
            color: #4FD1C5;
            font-size: 1rem;
        }
        
        /* Right Side - Forms */
        .auth-form-side {
            flex: 0 0 60%;
            padding: 3rem;
        }
        
        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #4FD1C5;
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            transform: translateX(-5px);
            color: #38B2AC;
        }
        
        /* Form Header */
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2D3748;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: #718096;
            font-size: 0.9rem;
        }
        
        /* Role Cards */
        .role-cards {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .role-card {
            flex: 1;
            background: white;
            border: 1px solid rgba(79, 209, 197, 0.15);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(79, 209, 197, 0.1);
            border-color: #4FD1C5;
        }
        
        .role-icon {
            width: 70px;
            height: 70px;
            background: rgba(79, 209, 197, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .role-icon i {
            font-size: 2rem;
            color: #4FD1C5;
        }
        
        .role-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2D3748;
        }
        
        .role-card p {
            font-size: 0.85rem;
            color: #718096;
            margin-bottom: 1rem;
        }
        
        .role-features {
            list-style: none;
            padding: 0;
            text-align: left;
            margin-bottom: 1rem;
        }
        
        .role-features li {
            font-size: 0.8rem;
            color: #4A5568;
            padding: 0.25rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .role-features li i {
            color: #4FD1C5;
            font-size: 0.7rem;
        }
        
        .role-card .btn {
            width: 100%;
            margin-top: 0.5rem;
        }
        
        .role-note {
            font-size: 0.7rem;
            color: #F6AD55;
            margin-top: 0.5rem;
            display: block;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.85rem;
            color: #4A5568;
        }
        
        .form-label i {
            margin-right: 0.5rem;
            color: #4FD1C5;
            width: 16px;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4FD1C5;
            box-shadow: 0 0 0 3px rgba(79, 209, 197, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        /* Password Strength */
        .password-strength {
            margin-top: 0.5rem;
        }
        
        .strength-bar {
            height: 4px;
            background: #E2E8F0;
            border-radius: 2px;
            width: 0;
            transition: width 0.3s ease;
        }
        
        .strength-text {
            font-size: 0.7rem;
            margin-top: 0.25rem;
            display: block;
        }
        
        /* Show Password Toggle */
        .password-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #A0AEC0;
            cursor: pointer;
        }
        
        .toggle-password:hover {
            color: #4FD1C5;
        }
        
        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .checkbox-group input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #4FD1C5;
        }
        
        .checkbox-group label {
            font-size: 0.85rem;
            color: #4A5568;
            cursor: pointer;
            margin: 0;
        }
        
        /* Form Footer */
        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #E2E8F0;
        }
        
        .form-footer a {
            color: #4FD1C5;
            text-decoration: none;
            font-weight: 500;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .alert-success {
            background: #E6FFFA;
            color: #2C7A7B;
            border-left: 4px solid #4FD1C5;
        }
        
        .alert-danger {
            background: #FED7D7;
            color: #C53030;
            border-left: 4px solid #F56565;
        }
        
        /* Theme Toggle (Visual Only - Phase 2) */
        .theme-toggle-dummy {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            border: 1px solid rgba(79, 209, 197, 0.3);
            color: #4FD1C5;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .theme-toggle-dummy:hover {
            background: #4FD1C5;
            color: white;
            transform: rotate(15deg);
            border-color: transparent;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .auth-branding {
                flex: 0 0 100%;
                text-align: center;
            }
            
            .auth-form-side {
                flex: 0 0 100%;
                padding: 2rem;
            }
            
            .brand-features {
                display: inline-block;
                text-align: left;
            }
            
            .brand-icon-large {
                margin-left: auto;
                margin-right: auto;
            }
            
            .role-cards {
                flex-direction: column;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        
        @media (max-width: 576px) {
            .auth-container {
                padding: 1rem;
            }
            
            .auth-form-side {
                padding: 1.5rem;
            }
            
            .auth-branding {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body class="auth-page">
    
    <div class="auth-container">
        <div class="auth-card">
            <!-- Left Side - Branding -->
            <div class="auth-branding">
                <div class="brand-logo">
                    <div class="brand-icon-large">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h1 class="brand-title">Univents</h1>
                    <p class="brand-tagline">BU Polangui's centralized event management system for student organizations.</p>
                    
                    <ul class="brand-features">
                        <li><i class="fas fa-check-circle"></i> Connect with student organizations</li>
                        <li><i class="fas fa-check-circle"></i> Track event attendance</li>
                        <li><i class="fas fa-check-circle"></i> Get real-time announcements</li>
                        <li><i class="fas fa-check-circle"></i> Browse organization merchandise</li>
                    </ul>
                </div>
            </div>
            
            <!-- Right Side - Forms -->
            <div class="auth-form-side">
                
                <?php if ($show_login): ?>
                    <!-- LOGIN FORM -->
                    <a href="index.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                    
                    <div class="form-header">
                        <h2>Welcome Back</h2>
                        <p>Please sign in to your account</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="?mode=login" class="auth-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-id-card"></i> ID Number or Email
                            </label>
                            <input type="text" name="id_number" class="form-control" placeholder="Enter your ID or email" required autofocus>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i> Password
                            </label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="login-password" class="form-control" placeholder="Enter your password" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('login-password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <label class="checkbox-group">
                                <input type="checkbox" name="remember"> 
                                <label>Remember me</label>
                            </label>
                            <a href="#" class="forgot-link" style="color: #4FD1C5; font-size: 0.85rem; text-decoration: none;" onclick="alert('Password reset will be available in Phase 2'); return false;">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" name="login" class="btn btn-primary w-100 py-3" style="font-weight: 600;">
                            <i class="fas fa-sign-in-alt me-2"></i> LOGIN
                        </button>
                    </form>
                    
                    <div class="form-footer">
                        <p>Don't have an account? <a href="?step=select">Sign up here</a></p>
                    </div>
                    
                <?php elseif ($step == 'select'): ?>
                    <!-- ROLE SELECTION CARDS -->
                    <a href="index.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                    
                    <div class="form-header">
                        <h2>Choose Your Role</h2>
                        <p>Select how you want to use Univents</p>
                    </div>
                    
                    <div class="role-cards">
                        <!-- Student Card -->
                        <div class="role-card" onclick="selectRole('student_member')">
                            <div class="role-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h3>Student</h3>
                            <p>Join organizations, attend events, and stay updated</p>
                            <ul class="role-features">
                                <li><i class="fas fa-check-circle"></i> View announcements</li>
                                <li><i class="fas fa-check-circle"></i> Register for events</li>
                                <li><i class="fas fa-check-circle"></i> Track attendance</li>
                                <li><i class="fas fa-check-circle"></i> Join organizations</li>
                            </ul>
                            <button class="btn btn-outline-primary">Select Student</button>
                        </div>
                        
                        <!-- Organization Officer Card -->
                        <div class="role-card" onclick="selectRole('org_officer')">
                            <div class="role-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <h3>Organization Officer</h3>
                            <p>Manage your organization's events and announcements</p>
                            <ul class="role-features">
                                <li><i class="fas fa-check-circle"></i> Create events</li>
                                <li><i class="fas fa-check-circle"></i> Post announcements</li>
                                <li><i class="fas fa-check-circle"></i> Track attendance</li>
                                <li><i class="fas fa-check-circle"></i> Manage members</li>
                            </ul>
                            <span class="role-note">* Requires admin approval</span>
                            <button class="btn btn-outline-primary">Select Officer</button>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <p>Already have an account? <a href="?mode=login">Sign in here</a></p>
                    </div>
                    
                    <script>
                    function selectRole(role) {
                        window.location.href = `?step=register&role=${role}`;
                    }
                    </script>
                    
                <?php else: ?>
                    <!-- REGISTRATION FORM (Role-specific) -->
                    <a href="?step=select" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to role selection
                    </a>
                    
                    <div class="form-header">
                        <h2>
                            <?php if ($selected_role == 'org_officer'): ?>
                                Become an Organization Officer
                            <?php else: ?>
                                Join as a Student
                            <?php endif; ?>
                        </h2>
                        <p>
                            <?php if ($selected_role == 'org_officer'): ?>
                                Fill out the form to apply for officer status
                            <?php else: ?>
                                Create your student account
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="?step=register&role=<?php echo $selected_role; ?>" class="auth-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="role" value="<?php echo $selected_role; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ID Number</label>
                            <input type="text" name="id_number" class="form-control" placeholder="e.g., 2026-01-0001" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password" id="signup-password" class="form-control" required>
                                    <button type="button" class="toggle-password" onclick="togglePassword('signup-password', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength">
                                    <div class="strength-bar"></div>
                                    <span class="strength-text"></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="confirm_password" id="confirm-password" class="form-control" required>
                                    <button type="button" class="toggle-password" onclick="togglePassword('confirm-password', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($selected_role == 'org_officer'): ?>
                            <div class="form-group">
                                <label class="form-label">Organization *</label>
                                <select name="organization_id" class="form-control" required>
                                    <option value="">Select Organization</option>
                                    <?php foreach($organizations as $org): ?>
                                        <option value="<?php echo $org['org_id']; ?>"><?php echo htmlspecialchars($org['org_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Position *</label>
                                <input type="text" name="position" class="form-control" placeholder="e.g., President, Secretary, Event Coordinator" required>
                            </div>
                        <?php else: ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Course</label>
                                    <select name="course" class="form-control" required>
                                        <option value="">Select Course</option>
                                        <option value="BSIT">BS Information Technology</option>
                                        <option value="BSCS">BS Computer Science</option>
                                        <option value="BSIS">BS Information Systems</option>
                                        <option value="BSED">BS Education</option>
                                        <option value="BEED">BEED</option>
                                        <option value="BSN">BS Nursing</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Year Level</label>
                                    <select name="year_level" class="form-control" required>
                                        <option value="">Select Year</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Block</label>
                                    <select name="block" class="form-control" required>
                                        <option value="">Select Block</option>
                                        <option value="A">Block A</option>
                                        <option value="B">Block B</option>
                                        <option value="C">Block C</option>
                                        <option value="D">Block D</option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" name="signup" class="btn btn-primary w-100 py-3" style="font-weight: 600;">
                            <i class="fas fa-user-plus me-2"></i>
                            <?php echo $selected_role == 'org_officer' ? 'SUBMIT APPLICATION' : 'SIGN UP'; ?>
                        </button>
                    </form>
                    
                    <div class="form-footer">
                        <p>Already have an account? <a href="?mode=login">Sign in here</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Visual-Only Dark Mode Button (Phase 2) -->
    <button class="theme-toggle-dummy" onclick="alert('Dark mode will be available in Phase 2!')">
        <i class="fas fa-moon"></i>
    </button>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ==================== PASSWORD STRENGTH METER ====================
        const passwordInput = document.getElementById('signup-password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strengthBar = document.querySelector('.strength-bar');
                const strengthText = document.querySelector('.strength-text');
                
                let strength = 0;
                if (password.length >= 8) strength++;
                if (password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                const percentage = (strength / 4) * 100;
                strengthBar.style.width = percentage + '%';
                
                if (strength <= 1) {
                    strengthBar.style.background = '#F56565';
                    strengthText.textContent = 'Weak';
                    strengthText.style.color = '#F56565';
                } else if (strength <= 2) {
                    strengthBar.style.background = '#F6AD55';
                    strengthText.textContent = 'Fair';
                    strengthText.style.color = '#F6AD55';
                } else if (strength <= 3) {
                    strengthBar.style.background = '#4FD1C5';
                    strengthText.textContent = 'Good';
                    strengthText.style.color = '#4FD1C5';
                } else {
                    strengthBar.style.background = '#48BB78';
                    strengthText.textContent = 'Strong';
                    strengthText.style.color = '#48BB78';
                }
            });
        }
        
        // ==================== SHOW/HIDE PASSWORD TOGGLE ====================
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // ==================== AUTO-HIDE ALERTS ====================
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    </script>
</body>
</html>