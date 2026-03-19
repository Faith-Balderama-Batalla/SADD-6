<?php
// login-signup.php
require_once 'config.php';

$error = '';
$success = '';

// Handle Login Form Submission
if (isset($_POST['login'])) {
    $id_number = mysqli_real_escape_string($conn, trim($_POST['id_number']));
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE (id_number = ? OR email = ?) AND status != 'inactive'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $id_number, $id_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            
            // Check if account is pending (for officers awaiting approval)
            if ($user['status'] === 'pending') {
                $error = 'Your account is pending approval. Please wait for admin confirmation.';
            } 
            else {
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Role-based redirect
                switch($user['role']) {
                    case 'admin':
                        header('Location: Admin/admindashboard.php');
                        break;
                    case 'org_officer':
                        header('Location: OrgMngrs/dashboard.php');
                        break;
                    case 'class_officer':
                        header('Location: StudentMembers/dashboard.php?class_officer=true');
                        break;
                    case 'student_member':
                    default:
                        header('Location: StudentMembers/dashboard.php');
                        break;
                }
                exit();
            }
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'User not found or account inactive';
    }
    $stmt->close();
}

// Handle Signup Form Submission
if (isset($_POST['signup'])) {
    $id_number = mysqli_real_escape_string($conn, trim($_POST['id_number']));
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $course = mysqli_real_escape_string($conn, $_POST['course']);
    $year_level = intval($_POST['year_level']);
    $block = mysqli_real_escape_string($conn, $_POST['block']);
    $requested_role = mysqli_real_escape_string($conn, $_POST['role']); // New role field
    
    $errors = [];
    
    if (empty($id_number)) $errors[] = "ID Number is required";
    if (empty($first_name)) $errors[] = "First Name is required";
    if (empty($last_name)) $errors[] = "Last Name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (empty($course)) $errors[] = "Course is required";
    if ($year_level < 1 || $year_level > 4) $errors[] = "Valid year level is required";
    if (empty($block)) $errors[] = "Block is required";
    
    // Validate role
    $valid_roles = ['student_member', 'org_officer', 'class_officer'];
    if (!in_array($requested_role, $valid_roles)) {
        $errors[] = "Invalid role selected";
    }
    
    if (empty($errors)) {
        $check_sql = "SELECT user_id FROM users WHERE id_number = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $id_number, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = 'ID number or email already exists';
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // For officer roles, set status to 'pending' until approved
        $status = ($requested_role === 'student_member') ? 'active' : 'pending';
        $role = $requested_role; // Use the requested role
        
        $insert_sql = "INSERT INTO users (id_number, first_name, last_name, email, password, course, year_level, block, role, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssssssisss", $id_number, $first_name, $last_name, $email, $hashed_password, $course, $year_level, $block, $role, $status);
        
        if ($insert_stmt->execute()) {
            $user_id = $insert_stmt->insert_id;
            
            // If class officer, create a pending class assignment record
            if ($role === 'class_officer') {
                $pending_sql = "INSERT INTO pending_officers (user_id, requested_role, course, year_level, block, request_date) 
                                VALUES (?, ?, ?, ?, ?, NOW())";
                $pending_stmt = $conn->prepare($pending_sql);
                $pending_stmt->bind_param("issis", $user_id, $role, $course, $year_level, $block);
                $pending_stmt->execute();
                $pending_stmt->close();
                
                $success = 'Account created! Your officer application has been submitted for approval.';
            } 
            // If org officer, create pending record (would need org selection - simplified for now)
            elseif ($role === 'org_officer') {
                $success = 'Account created! Your organization officer application will be reviewed by an admin.';
            } 
            else {
                $success = 'Account created successfully! You can now login.';
            }
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
        $insert_stmt->close();
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// Determine which mode to show
$show_login = true;
if (isset($_GET['mode']) && $_GET['mode'] == 'signup') {
    $show_login = false;
}
if (isset($_POST['signup']) && empty($success)) {
    $show_login = false;
}
if (isset($_POST['login']) && empty($success)) {
    $show_login = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Univents - <?php echo $show_login ? 'Login' : 'Sign Up'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <!-- Back to Home Link -->
    <a href="index.php" class="back-to-home">
        <i class="fas fa-arrow-left me-2"></i>Back to Home
    </a>

    <!-- Main Container -->
    <div class="split-container">
        
        <!-- LEFT SIDE - Branding (40%) -->
        <div class="split-left">
            <?php if ($show_login): ?>
                <div class="brand-content">
                    <div class="brand-icon-large">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h1 class="brand-title">Welcome Back!</h1>
                    <p class="brand-description">Sign in to access your organizations, events, and campus community at BU Polangui.</p>
                    
                    <div class="brand-features">
                        <div class="brand-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>View organization announcements</span>
                        </div>
                        <div class="brand-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Track event attendance</span>
                        </div>
                        <div class="brand-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Browse merchandise</span>
                        </div>
                    </div>
                    
                    <div class="brand-switch">
                        <p>New to Univents?</p>
                        <a href="?mode=signup" class="btn btn-outline-light btn-lg switch-btn">
                            Create Account <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="brand-content">
                    <div class="brand-icon-large">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h1 class="brand-title">Join Univents!</h1>
                    <p class="brand-description">Create your account and stay connected with all BU Polangui organizations.</p>
                    
                    <div class="brand-features">
                        <div class="brand-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Free for BU Polangui students</span>
                        </div>
                        <div class="brand-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Get real-time event reminders</span>
                        </div>
                        <div class="brand-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Follow your favorite organizations</span>
                        </div>
                    </div>
                    
                    <div class="brand-switch">
                        <p>Already have an account?</p>
                        <a href="?mode=login" class="btn btn-outline-light btn-lg switch-btn">
                            <i class="fas fa-arrow-left me-2"></i>Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT SIDE - Forms (60% - with proper spacing) -->
        <div class="split-right">
            <?php if ($show_login): ?>
                <!-- LOGIN FORM -->
                <div class="form-container">
                    <div class="form-header">
                        <h2 class="form-title">Welcome Back</h2>
                        <p class="form-subtitle">Please sign in to your account</p>
                    </div>

                    <?php if ($error && $show_login): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="?mode=login" class="auth-form">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-id-card"></i>
                                ID Number or Email
                            </label>
                            <input type="text" name="id_number" class="form-control" placeholder="Enter your ID or email" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i>
                                Password
                            </label>
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                        
                        <div class="form-options">
                            <label class="remember-checkbox">
                                <input type="checkbox"> Remember me
                            </label>
                            <a href="#" class="forgot-link">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" name="login" class="btn btn-primary w-100 py-3">
                            <i class="fas fa-sign-in-alt me-2"></i>LOGIN
                        </button>
                    </form>

                    <div class="form-footer">
                        <p>Don't have an account? <a href="?mode=signup" class="switch-link">Sign up here</a></p>
                    </div>
                </div>
            <?php else: ?>
                <!-- SIGNUP FORM - With proper spacing -->
                <div class="form-container signup-form-container">
                    <div class="form-header">
                        <h2 class="form-title">Join the BU Polangui community</h2>
                        <p class="form-subtitle">Create your account to get started</p>
                    </div>

                    <?php if ($error && !$show_login): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="?mode=signup" class="auth-form">
                        <!-- Name Row -->
                        <div class="form-row">
                            <div class="form-group half">
                                <label class="form-label">
                                    <i class="fas fa-user"></i>
                                    First Name
                                </label>
                                <input type="text" name="first_name" class="form-control" placeholder="First name" required>
                            </div>
                            <div class="form-group half">
                                <label class="form-label">
                                    <i class="fas fa-user"></i>
                                    Last Name
                                </label>
                                <input type="text" name="last_name" class="form-control" placeholder="Last name" required>
                            </div>
                        </div>

                        <!-- ID Number -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-id-card"></i>
                                ID Number
                            </label>
                            <input type="text" name="id_number" class="form-control" placeholder="e.g., 2026-01-0001" required>
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i>
                                Email
                            </label>
                            <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                        </div>

                        <!-- Password Row -->
                        <div class="form-row">
                            <div class="form-group half">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i>
                                    Password
                                </label>
                                <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
                            </div>
                            <div class="form-group half">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i>
                                    Confirm Password
                                </label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                            </div>
                        </div>

                        <!-- Course & Year & Block Row -->
                        <div class="form-row">
                            <div class="form-group half">
                                <label class="form-label">
                                    <i class="fas fa-graduation-cap"></i>
                                    Course
                                </label>
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
                            <div class="form-group half">
                                <label class="form-label">
                                    <i class="fas fa-layer-group"></i>
                                    Year Level
                                </label>
                                <select name="year_level" class="form-control" required>
                                    <option value="">Select Year</option>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                            <div class="form-group half">
                                <label class="form-label">
                                    <i class="fas fa-home"></i>
                                    Block
                                </label>
                                <select name="block" class="form-control" required>
                                    <option value="">Select Block</option>
                                    <option value="A">Block A</option>
                                    <option value="B">Block B</option>
                                    <option value="C">Block C</option>
                                    <option value="D">Block D</option>
                                </select>
                            </div>
                        </div>

                        <!-- Role Selection (Add this after the Year Level field) -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user-tag me-2"></i>Register as
                            </label>
                            <select name="role" class="form-control" required>
                                <option value="student_member">Student Member</option>
                                <option value="org_officer">Organization Officer</option>
                                <option value="class_officer">Class Officer</option>
                            </select>
                            <small class="text-muted">
                                <?php if(isset($_GET['mode']) && $_GET['mode'] == 'signup'): ?>
                                    Note: Organization and Class officer applications require admin approval.
                                <?php endif; ?>
                            </small>
                        </div>

                        <!-- Sign Up Button -->
                        <button type="submit" name="signup" class="btn btn-primary w-100 py-3 signup-btn">
                            <i class="fas fa-user-plus me-2"></i>SIGN UP
                        </button>

                        <!-- Form Footer -->
                        <div class="form-footer">
                            <p>Already have an account? <a href="?mode=login" class="switch-link">Sign in here</a></p>
                        </div>

                        <!-- Social Login -->
                        <div class="social-login">
                            <p class="or-divider"><span>Or sign up with</span></p>
                            <div class="social-icons">
                                <a href="#" class="social-icon"><i class="fab fa-google"></i></a>
                                <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                                <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>