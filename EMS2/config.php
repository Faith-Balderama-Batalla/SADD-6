<?php
// config.php - Database configuration and authentication functions

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ems_system';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Site configuration
define('SITE_NAME', 'Univents');
define('SITE_URL', 'http://localhost/EMS');

// ============ AUTHENTICATION FUNCTIONS ============

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login-signup.php');
        exit();
    }
}

function getCurrentUser($conn) {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT * FROM users WHERE user_id = ?";
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
        header('Location: dashboard.php');
        exit();
    }
}

function getUserFullName($user) {
    return $user['first_name'] . ' ' . $user['last_name'];
}

function getUserInitials($user) {
    return strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
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

function getEventQRCode($conn, $event_id) {
    $sql = "SELECT * FROM qr_codes WHERE event_id = ? AND is_active = TRUE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function generateQRString($event_id) {
    return hash('sha256', $event_id . time() . uniqid());
}
?>