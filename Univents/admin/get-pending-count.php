<?php
// admin/get-pending-count.php - AJAX endpoint for pending count
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') {
    echo json_encode(['count' => 0]);
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM pending_officers WHERE status = 'pending'");
$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_assoc()['count'];

echo json_encode(['count' => $count]);
?>