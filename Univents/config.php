<?php
// config.php - Database configuration and authentication functions with enhanced security

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    
    session_start();
}

// ============ DATABASE CONFIGURATION ============
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'eventmanagementsystem';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// ============ SITE CONFIGURATION ============
define('SITE_NAME', 'Univents');
define('SITE_URL', 'http://localhost/EMS');
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCKOUT_TIME', 900); // 15 minutes

// ============ CSRF PROTECTION ============

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============ RATE LIMITING ============

function checkRateLimit($conn, $ip) {
    $stmt = $conn->prepare("SELECT login_attempts, locked_until FROM user_sessions WHERE ip_address = ? ORDER BY last_attempt DESC LIMIT 1");
    if (!$stmt) return true;
    
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
            return false; // Still locked
        }
        if ($row['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            return false; // Too many attempts
        }
    }
    return true;
}

function recordLoginAttempt($conn, $ip, $user_id = null) {
    // Check if session exists for this IP
    $check = $conn->prepare("SELECT session_id FROM user_sessions WHERE ip_address = ?");
    $check->bind_param("s", $ip);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing
        $stmt = $conn->prepare("UPDATE user_sessions SET 
            login_attempts = login_attempts + 1, 
            last_attempt = NOW(),
            locked_until = IF(login_attempts >= ?, DATE_ADD(NOW(), INTERVAL ? SECOND), locked_until)
            WHERE ip_address = ?");
        $lockout = LOCKOUT_TIME;
        $stmt->bind_param("iis", MAX_LOGIN_ATTEMPTS, $lockout, $ip);
        $stmt->execute();
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO user_sessions (user_id, ip_address, session_token, login_attempts, last_attempt) 
                               VALUES (?, ?, ?, 1, NOW())");
        $session_token = session_id();
        $stmt->bind_param("iss", $user_id, $ip, $session_token);
        $stmt->execute();
    }
}

function resetLoginAttempts($conn, $ip) {
    $stmt = $conn->prepare("UPDATE user_sessions SET login_attempts = 0, locked_until = NULL WHERE ip_address = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
}

// ============ SESSION SECURITY ============

function regenerateSession() {
    session_regenerate_id(true);
    $_SESSION['last_activity'] = time();
}

function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// ============ AUTHENTICATION FUNCTIONS ============

function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    return checkSessionTimeout();
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../login-signup.php');
        exit();
    }
}

function getCurrentUser($conn) {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT * FROM users WHERE user_id = ? AND status != 'inactive'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    return null;
}

function hasRole($conn, $roles) {
    $user = getCurrentUser($conn);
    if (!$user) return false;
    
    if (is_array($roles)) {
        return in_array($user['role'], $roles);
    }
    return $user['role'] === $roles;
}

function requireRole($conn, $roles) {
    if (!hasRole($conn, $roles)) {
        // Redirect based on role
        $user = getCurrentUser($conn);
        if ($user && $user['role'] == 'admin') {
            header('Location: ../admin/dashboard.php');
        } elseif ($user && $user['role'] == 'org_officer') {
            header('Location: ../officer/dashboard.php');
        } else {
            header('Location: ../student/dashboard.php');
        }
        exit();
    }
}

function getUserFullName($user) {
    return $user['first_name'] . ' ' . $user['last_name'];
}

function getUserInitials($user) {
    return strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
}

// ============ NOTIFICATION FUNCTIONS ============

function createNotification($conn, $user_id, $title, $message, $type = 'system', $reference_id = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, reference_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $user_id, $title, $message, $type, $reference_id);
    return $stmt->execute();
}

function getUnreadCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['unread'] ?? 0;
}

function getNotifications($conn, $user_id, $limit = 10) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

function markNotificationRead($conn, $user_id, $notification_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

function markAllNotificationsRead($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// ============ HELPER FUNCTIONS ============

function getOrganizationByOfficer($conn, $user_id) {
    $sql = "SELECT o.* FROM organizations o
            JOIN organization_officers oo ON o.org_id = oo.org_id
            WHERE oo.user_id = ? AND oo.status = 'active'
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getUserOrganizations($conn, $user_id) {
    $sql = "SELECT o.*, om.status as membership_status, om.membership_date
            FROM organizations o 
            JOIN organization_memberships om ON o.org_id = om.org_id 
            WHERE om.user_id = ? AND om.status = 'active'
            ORDER BY o.org_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}

function isUserInOrganization($conn, $user_id, $org_id) {
    $stmt = $conn->prepare("SELECT * FROM organization_memberships WHERE user_id = ? AND org_id = ? AND status = 'active'");
    $stmt->bind_param("ii", $user_id, $org_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function isUserOfficerOfOrganization($conn, $user_id, $org_id) {
    $stmt = $conn->prepare("SELECT * FROM organization_officers WHERE user_id = ? AND org_id = ? AND status = 'active'");
    $stmt->bind_param("ii", $user_id, $org_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function getEventQRCode($conn, $event_id) {
    $sql = "SELECT * FROM qr_codes WHERE event_id = ? AND is_active = TRUE AND NOW() BETWEEN valid_from AND valid_until";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function generateQRString($event_id) {
    return hash('sha256', $event_id . time() . uniqid() . random_bytes(16));
}

// ============ EVENT REGISTRATION FUNCTIONS ============

function registerForEvent($conn, $event_id, $user_id, $type = 'active') {
    // Check if already registered
    $check = $conn->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $check->bind_param("ii", $event_id, $user_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Already registered'];
    }
    
    // Check active participant limit
    $event = $conn->prepare("SELECT max_active_participants, current_active_participants FROM events WHERE event_id = ?");
    $event->bind_param("i", $event_id);
    $event->execute();
    $event_data = $event->get_result()->fetch_assoc();
    
    if ($type == 'active' && $event_data['max_active_participants'] > 0) {
        if ($event_data['current_active_participants'] >= $event_data['max_active_participants']) {
            return ['success' => false, 'message' => 'Active participant slots are full'];
        }
        
        // Update current active participants
        $update = $conn->prepare("UPDATE events SET current_active_participants = current_active_participants + 1 WHERE event_id = ?");
        $update->bind_param("i", $event_id);
        $update->execute();
    }
    
    // Insert registration
    $insert = $conn->prepare("INSERT INTO event_registrations (event_id, user_id, registration_type) VALUES (?, ?, ?)");
    $insert->bind_param("iis", $event_id, $user_id, $type);
    
    if ($insert->execute()) {
        return ['success' => true, 'message' => 'Registered successfully'];
    }
    
    return ['success' => false, 'message' => 'Registration failed'];
}

// ============ THEME SETTINGS ============

function getThemePreference() {
    if (isset($_COOKIE['theme'])) {
        return $_COOKIE['theme'];
    }
    return 'light';
}

function setThemePreference($theme) {
    setcookie('theme', $theme, time() + (86400 * 30), "/");
}

// ============ HELPER FOR PREPARED SEARCH ============

function buildSearchQuery($table, $fields, $search_term) {
    $conditions = [];
    $params = [];
    $types = "";
    
    foreach ($fields as $field) {
        $conditions[] = "$field LIKE ?";
        $params[] = "%$search_term%";
        $types .= "s";
    }
    
    return [
        'sql' => "SELECT * FROM $table WHERE " . implode(" OR ", $conditions),
        'types' => $types,
        'params' => $params
    ];
}

// ============ EMAIL VALIDATION ============

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateIdNumber($id_number) {
    // Basic validation - can be customized
    return preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{4}$/', $id_number);
}

// ============ PASSWORD VALIDATION ============

function validatePassword($password) {
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    return $errors;
}

// ============ DASHBOARD REDIRECTION ============

function redirectToDashboard($user) {
    switch($user['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'org_officer':
            header('Location: officer/dashboard.php');
            break;
        case 'student_member':
        default:
            header('Location: student/dashboard.php');
            break;
    }
    exit();
}

// ============ SANITIZATION ============

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>