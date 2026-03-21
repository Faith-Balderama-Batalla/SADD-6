<?php
// includes/mark-attendance-handler.php
// This file handles attendance marking via AJAX

require_once '../config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is officer
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user = getCurrentUser($conn);
if ($user['role'] !== 'org_officer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$qr_data = $data['qr_data'] ?? '';
$event_id = $data['event_id'] ?? 0;

if (!$qr_data || !$event_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Verify QR code is valid for this event
$qr_sql = "SELECT * FROM qr_codes WHERE qr_string = ? AND event_id = ? AND is_active = TRUE AND NOW() BETWEEN valid_from AND valid_until";
$qr_stmt = $conn->prepare($qr_sql);
$qr_stmt->bind_param("si", $qr_data, $event_id);
$qr_stmt->execute();
$qr_result = $qr_stmt->get_result();

if ($qr_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired QR code']);
    exit();
}

// Find student by QR data (student ID)
$student_sql = "SELECT * FROM users WHERE id_number = ? AND status = 'active'";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("s", $qr_data);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit();
}

// Check if already marked
$check_sql = "SELECT * FROM attendance_logs WHERE event_id = ? AND student_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $event_id, $student['user_id']);
$check_stmt->execute();

if ($check_stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Student already marked present']);
    exit();
}

// Mark attendance
$insert_sql = "INSERT INTO attendance_logs (event_id, student_id, officer_id, status, marked_via) VALUES (?, ?, ?, 'present', 'qr')";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("iii", $event_id, $student['user_id'], $user['user_id']);

if ($insert_stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Attendance marked for ' . $student['first_name'] . ' ' . $student['last_name'],
        'student' => [
            'name' => $student['first_name'] . ' ' . $student['last_name'],
            'id' => $student['id_number']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark attendance']);
}

$qr_stmt->close();
$student_stmt->close();
$check_stmt->close();
$insert_stmt->close();
?>