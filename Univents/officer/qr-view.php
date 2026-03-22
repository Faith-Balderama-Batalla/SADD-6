<?php
// officer/qr-view.php - Display QR code for event
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'org_officer') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Get officer's organization
$org_stmt = $conn->prepare("
    SELECT o.org_id, o.org_name 
    FROM organizations o 
    JOIN organization_officers oo ON o.org_id = oo.org_id 
    WHERE oo.user_id = ? AND oo.status = 'active'
    LIMIT 1
");
$org_stmt->bind_param("i", $user['user_id']);
$org_stmt->execute();
$org = $org_stmt->get_result()->fetch_assoc();

if (!$org) {
    die("You are not assigned to any organization.");
}

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if (!$event_id) { 
    header('Location: events.php'); 
    exit(); 
}

// Get event details
$event_stmt = $conn->prepare("
    SELECT e.*, o.org_name 
    FROM events e 
    JOIN organizations o ON e.org_id = o.org_id 
    WHERE e.event_id = ? AND e.org_id = ?
");
$event_stmt->bind_param("ii", $event_id, $org['org_id']);
$event_stmt->execute();
$event = $event_stmt->get_result()->fetch_assoc();

if (!$event) {
    die("Event not found or you don't have permission.");
}

// Get or generate QR code
$qr_stmt = $conn->prepare("SELECT * FROM qr_codes WHERE event_id = ? AND is_active = TRUE");
$qr_stmt->bind_param("i", $event_id);
$qr_stmt->execute();
$qr = $qr_stmt->get_result()->fetch_assoc();

if (!$qr) {
    $qr_string = generateQRString($event_id);
    $valid_from = date('Y-m-d H:i:s', strtotime($event['start_datetime']) - 7200);
    $valid_until = date('Y-m-d H:i:s', strtotime($event['end_datetime']) + 7200);
    
    $insert_stmt = $conn->prepare("INSERT INTO qr_codes (event_id, qr_string, valid_from, valid_until) VALUES (?, ?, ?, ?)");
    $insert_stmt->bind_param("isss", $event_id, $qr_string, $valid_from, $valid_until);
    $insert_stmt->execute();
    $insert_stmt->close();
    
    $qr_string = $qr_string;
} else {
    $qr_string = $qr['qr_string'];
}

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Event QR Code - Univents Officer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs2-fix/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .qr-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .qr-code {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 20px 0;
        }
        .event-details {
            background: linear-gradient(135deg, rgba(79, 209, 197, 0.1), rgba(56, 178, 172, 0.05));
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
        }
        .validity-badge {
            display: inline-block;
            background: var(--light-mint);
            color: var(--dark-mint);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        @media print {
            .dashboard-navbar, .sidebar, .btn, .back-button, .theme-toggle {
                display: none !important;
            }
            .main-content {
                margin: 0;
                padding: 0;
            }
            .dashboard-content {
                margin: 0;
                padding: 20px;
            }
            .qr-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_officer.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-qrcode me-2" style="color: var(--primary-mint);"></i>
                        Event QR Code
                    </h2>
                    <p class="text-muted">Display this QR code for attendees to scan (Phase 2)</p>
                </div>
                <a href="events.php" class="btn btn-outline-primary back-button">
                    <i class="fas fa-arrow-left me-2"></i>Back to Events
                </a>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="qr-container">
                        <div class="validity-badge mb-3">
                            <i class="fas fa-clock me-1"></i>
                            Valid: 2 hours before to 2 hours after event
                        </div>
                        
                        <div class="qr-code" id="qrcode"></div>
                        
                        <h4><?php echo htmlspecialchars($event['event_title']); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($event['org_name']); ?></p>
                        
                        <div class="event-details">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><i class="fas fa-map-marker-alt me-2"></i>Venue</p>
                                    <strong><?php echo htmlspecialchars($event['venue']); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><i class="far fa-calendar me-2"></i>Date & Time</p>
                                    <strong><?php echo date('F j, Y g:i A', strtotime($event['start_datetime'])); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 d-flex gap-3 justify-content-center">
                            <button class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Print QR Code
                            </button>
                            <button class="btn btn-outline-secondary" onclick="downloadQR()">
                                <i class="fas fa-download me-2"></i>Download
                            </button>
                        </div>
                        
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Phase 2 Feature:</strong> Students will be able to scan this QR code to mark their attendance. 
                            For now, please use the manual attendance marking feature.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
// Generate QR Code
const qrcode = new QRCode(document.getElementById("qrcode"), {
    text: "<?php echo $qr_string; ?>",
    width: 250,
    height: 250,
    colorDark: "#2C7A7B",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});

// Download QR Code
function downloadQR() {
    const canvas = document.querySelector('#qrcode canvas');
    if (canvas) {
        const link = document.createElement('a');
        link.download = 'qrcode_<?php echo $event_id; ?>.png';
        link.href = canvas.toDataURL();
        link.click();
        showToast('QR Code downloaded successfully!', 'success');
    } else {
        showToast('Unable to download QR code', 'error');
    }
}
</script>
</body>
</html>