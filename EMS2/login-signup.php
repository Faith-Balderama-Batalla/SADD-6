<?php
// login-signup.php
require_once 'config.php';

$error = '';
$success = '';

// Handle Login
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
            if ($user['status'] === 'pending') {
                $error = 'Your account is pending approval.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_role'] = $user['role'];
                
                // Role-based redirect
                switch($user['role']) {
                    case 'admin': header('Location: Admin/dashboard.php'); break;
                    case 'org_officer': header('Location: Officer/dashboard.php'); break;
                    default: header('Location: Student/dashboard.php'); break;
                }
                exit();
            }
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'User not found';
    }
    $stmt->close();
}

// Handle Signup
if (isset($_POST['signup'])) {
    $id_number = mysqli_real_escape_string($conn, trim($_POST['id_number']));
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $course = mysqli_real_escape_string($conn, $_POST['course']);
    $year_level = intval($_POST['year_level']);
    $block = mysqli_real_escape_string($conn, $_POST['block']);
    $requested_role = mysqli_real_escape_string($conn, $_POST['role']);
    
    $errors = [];
    if (empty($id_number)) $errors[] = "ID Number required";
    if (empty($first_name)) $errors[] = "First Name required";
    if (empty($last_name)) $errors[] = "Last Name required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required";
    if (strlen($password) < 8) $errors[] = "Password must be 8+ characters";
    if ($password !== $confirm) $errors[] = "Passwords do not match";
    if (empty($course)) $errors[] = "Course required";
    if ($year_level < 1 || $year_level > 4) $errors[] = "Valid year level required";
    if (empty($block)) $errors[] = "Block required";
    
    if (empty($errors)) {
        $check = $conn->prepare("SELECT user_id FROM users WHERE id_number = ? OR email = ?");
        $check->bind_param("ss", $id_number, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = 'ID or email already exists';
        }
        $check->close();
    }
    
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $status = ($requested_role === 'student_member') ? 'active' : 'pending';
        
        $insert = $conn->prepare("INSERT INTO users (id_number, first_name, last_name, email, password, course, year_level, block, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param("ssssssisss", $id_number, $first_name, $last_name, $email, $hashed, $course, $year_level, $block, $requested_role, $status);
        
        if ($insert->execute()) {
            $user_id = $insert->insert_id;
            
            if ($requested_role === 'class_officer') {
                $pending = $conn->prepare("INSERT INTO pending_officers (user_id, requested_role, course, year_level, block) VALUES (?, ?, ?, ?, ?)");
                $pending->bind_param("isiss", $user_id, $requested_role, $course, $year_level, $block);
                $pending->execute();
                $success = "Account created! Your officer application is pending approval.";
            } elseif ($requested_role === 'org_officer') {
                $pending = $conn->prepare("INSERT INTO pending_officers (user_id, requested_role, course, year_level, block) VALUES (?, ?, ?, ?, ?)");
                $pending->bind_param("isiss", $user_id, $requested_role, $course, $year_level, $block);
                $pending->execute();
                $success = "Account created! Your officer application is pending approval.";
            } else {
                $success = "Account created successfully! You can now login.";
            }
        } else {
            $errors[] = "Registration failed.";
        }
        $insert->close();
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

$show_login = !(isset($_GET['mode']) && $_GET['mode'] == 'signup');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Univents - <?php echo $show_login ? 'Login' : 'Sign Up'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #F0FFF4 0%, #E6FFFA 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .split-container {
            display: flex;
            width: 1200px;
            max-width: 100%;
            min-height: 600px;
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
        }
        .split-left {
            flex: 1;
            background: var(--gradient);
            padding: 3rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .split-right {
            flex: 1;
            padding: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .brand-icon-large {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
        }
        .brand-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .form-container {
            width: 100%;
            max-width: 450px;
        }
        .form-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }
        .back-to-home {
            position: fixed;
            top: 20px;
            left: 20px;
            background: white;
            padding: 10px 20px;
            border-radius: 50px;
            text-decoration: none;
            color: var(--dark-mint);
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 100;
        }
        @media (max-width: 768px) {
            .split-container {
                flex-direction: column;
            }
            .split-left, .split-right {
                padding: 2rem;
            }
        }
    </style>
</head>
<body class="auth-page">
    <a href="index.php" class="back-to-home"><i class="fas fa-arrow-left me-2"></i>Back to Home</a>
    
    <div class="split-container">
        <div class="split-left">
            <?php if ($show_login): ?>
                <div class="text-center">
                    <div class="brand-icon-large"><i class="fas fa-calendar-check"></i></div>
                    <h1 class="brand-title">Welcome Back!</h1>
                    <p>Sign in to access your organizations and events.</p>
                    <div class="mt-4">
                        <p class="mb-2">New to Univents?</p>
                        <a href="?mode=signup" class="btn btn-outline-light">Create Account</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <div class="brand-icon-large"><i class="fas fa-user-plus"></i></div>
                    <h1 class="brand-title">Join Univents!</h1>
                    <p>Create your account and stay connected.</p>
                    <div class="mt-4">
                        <p class="mb-2">Already have an account?</p>
                        <a href="?mode=login" class="btn btn-outline-light">Sign In</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="split-right">
            <div class="form-container">
                <?php if ($show_login): ?>
                    <h2 class="form-title">Sign In</h2>
                    <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                    <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">ID Number or Email</label>
                            <input type="text" name="id_number" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100 py-2">Login</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="?mode=signup" class="text-primary">Don't have an account? Sign up</a>
                    </div>
                <?php else: ?>
                    <h2 class="form-title">Create Account</h2>
                    <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <input type="text" name="id_number" class="form-control" placeholder="ID Number" required>
                        </div>
                        <div class="mb-3">
                            <input type="email" name="email" class="form-control" placeholder="Email" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <input type="password" name="password" class="form-control" placeholder="Password (8+ chars)" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <select name="course" class="form-control" required>
                                    <option value="">Course</option>
                                    <option value="BSIT">BS Information Technology</option>
                                    <option value="BSCS">BS Computer Science</option>
                                    <option value="BSIS">BS Information Systems</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <select name="year_level" class="form-control" required>
                                    <option value="">Year</option>
                                    <option value="1">1st</option><option value="2">2nd</option>
                                    <option value="3">3rd</option><option value="4">4th</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <select name="block" class="form-control" required>
                                    <option value="">Block</option>
                                    <option value="A">A</option><option value="B">B</option>
                                    <option value="C">C</option><option value="D">D</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <select name="role" class="form-control" required>
                                <option value="student_member">Student Member</option>
                                <option value="org_officer">Organization Officer (Requires Approval)</option>
                                <option value="class_officer">Class Officer (Requires Approval)</option>
                            </select>
                            <small class="text-muted">Officer applications require admin approval.</small>
                        </div>
                        <button type="submit" name="signup" class="btn btn-primary w-100 py-2">Sign Up</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="?mode=login" class="text-primary">Already have an account? Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>