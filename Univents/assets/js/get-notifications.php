<?php
// assets/js/get-notifications.php - AJAX endpoint for notifications
require_once '../../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['unread_count' => 0, 'notifications' => []]);
    exit();
}

$user = getCurrentUser($conn);
$unread_count = getUnreadCount($conn, $user['user_id']);

$notifications = [];
$stmt = $conn->prepare("SELECT notification_id, title, message, notification_type, created_at, is_read 
                        FROM notifications 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 5");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['notification_id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'type' => $row['notification_type'],
        'time' => date('M d, g:i A', strtotime($row['created_at'])),
        'is_read' => (bool)$row['is_read']
    ];
}

echo json_encode([
    'unread_count' => $unread_count,
    'notifications' => $notifications
]);
?>