<?php
// admin/add_user.php - Process adding new user
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $id_number = mysqli_real_escape_string($conn, trim($_POST['id_number']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $course = mysqli_real_escape_string($conn, $_POST['course']);
    $year_level = intval($_POST['year_level']);
    $block = mysqli_real_escape_string($conn, $_POST['block']);
    
    $errors = [];
    
    // Validation
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($id_number)) $errors[] = "ID number is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if (!preg_match('/[A-Z]/', $password)) $errors[] = "Password must contain at least one uppercase letter";
    if (!preg_match('/[0-9]/', $password)) $errors[] = "Password must contain at least one number";
    
    // Check for duplicates
    $check = $conn->prepare("SELECT user_id FROM users WHERE id_number = ? OR email = ?");
    $check->bind_param("ss", $id_number, $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $errors[] = "ID number or email already exists";
    }
    $check->close();
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $status = 'active';
        
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, id_number, email, password, role, course, year_level, block, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssiss", $first_name, $last_name, $id_number, $email, $hashed_password, $role, $course, $year_level, $block, $status);
        
        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;
            createNotification($conn, $new_user_id, 'Welcome to Univents!', 'Your account has been created by an administrator. You can now log in.', 'system');
            $_SESSION['success'] = "User added successfully!";
        } else {
            $_SESSION['error'] = "Failed to add user.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

header('Location: users.php');
exit();
?>