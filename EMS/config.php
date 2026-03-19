<?php
// config.php - Database configuration and authentication functions

// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
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

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Site configuration
define('SITE_NAME', 'Univents');
define('SITE_URL', 'http://localhost/eventmanagementsystem');

// ============ AUTHENTICATION FUNCTIONS ============

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login-signup.php');
        exit();
    }
}

/**
 * Get current user data from database
 * @param mysqli $conn Database connection
 * @return array|null User data array or null if not logged in
 */
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

/**
 * Check if user has a specific role
 * @param mysqli $conn Database connection
 * @param string|array $roles Role(s) to check
 * @return bool True if user has the role
 */
function hasRole($conn, $roles) {
    $user = getCurrentUser($conn);
    if (!$user) return false;
    
    if (is_array($roles)) {
        return in_array($user['role'], $roles);
    }
    return $user['role'] === $roles;
}

/**
 * Redirect if user doesn't have required role
 * @param mysqli $conn Database connection
 * @param string|array $roles Required role(s)
 */
function requireRole($conn, $roles) {
    if (!hasRole($conn, $roles)) {
        header('Location: dashboard.php');
        exit();
    }
}

/**
 * Get user's full name
 * @param array $user User data array
 * @return string Full name
 */
function getUserFullName($user) {
    return $user['first_name'] . ' ' . $user['last_name'];
}

/**
 * Get user's initials for avatar
 * @param array $user User data array
 * @return string Initials (e.g., "JD" for John Doe)
 */
function getUserInitials($user) {
    return strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
}
?>