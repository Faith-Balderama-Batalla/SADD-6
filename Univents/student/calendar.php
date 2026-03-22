<?php
// student/calendar.php - Event Calendar View
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);

// Get current month/year
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Month names
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Calculate first day and days in month
$first_day = mktime(0, 0, 0, $selected_month, 1, $selected_year);
$days_in_month = date('t', $first_day);
$starting_weekday = date('w', $first_day);

// Get events for this month from user's organizations
$events_stmt = $conn->prepare("
    SELECT e.*, o.org_name, o.org_color 
    FROM events e
    JOIN organizations o ON e.org_id = o.org_id
    JOIN organization_memberships om ON o.org_id = om.org_id
    WHERE om.user_id = ? AND om.status = 'active'
      AND MONTH(e.start_datetime) = ? AND YEAR(e.start_datetime) = ?
    ORDER BY e.start_datetime ASC
");
$events_stmt->bind_param("iii", $user['user_id'], $selected_month, $selected_year);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

// Organize events by day
$events_by_day = [];
while ($event = $events_result->fetch_assoc()) {
    $day = date('j', strtotime($event['start_datetime']));
    if (!isset($events_by_day[$day])) {
        $events_by_day[$day] = [];
    }
    $events_by_day[$day][] = $event;
}

// Get upcoming events
$upcoming_stmt = $conn->prepare("
    SELECT e.*, o.org_name, o.org_color 
    FROM events e
    JOIN organizations o ON e.org_id = o.org_id
    JOIN organization_memberships om ON o.org_id = om.org_id
    WHERE om.user_id = ? AND om.status = 'active'
      AND e.start_datetime > NOW()
    ORDER BY e.start_datetime ASC
    LIMIT 10
");
$upcoming_stmt->bind_param("i", $user['user_id']);
$upcoming_stmt->execute();
$upcoming_events = $upcoming_stmt->get_result();

$unread_count = getUnreadCount($conn, $user['user_id']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Event Calendar - Univents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .calendar-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        .calendar-weekday {
            text-align: center;
            font-weight: 600;
            padding: 12px;
            background: rgba(79, 209, 197, 0.1);
            border-radius: 12px;
        }
        .calendar-day {
            min-height: 100px;
            background: #F8FAFC;
            border-radius: 12px;
            padding: 8px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            cursor: pointer;
        }
        .calendar-day:hover {
            background: white;
            border-color: var(--primary-mint);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .calendar-day.empty {
            background: transparent;
            min-height: auto;
        }
        .day-number {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }
        .calendar-day.today {
            background: rgba(79, 209, 197, 0.1);
            border: 2px solid var(--primary-mint);
        }
        .calendar-event {
            font-size: 0.7rem;
            padding: 2px 6px;
            margin-bottom: 4px;
            border-radius: 4px;
            background: rgba(79, 209, 197, 0.1);
            color: var(--dark-mint);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .calendar-event:hover {
            background: var(--primary-mint);
            color: white;
        }
        .event-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 4px;
        }
        .nav-btn {
            padding: 8px 16px;
            border: 1px solid rgba(79, 209, 197, 0.2);
            background: white;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .nav-btn:hover {
            background: var(--primary-mint);
            color: white;
            border-color: var(--primary-mint);
        }
        .today-btn {
            padding: 8px 16px;
            background: var(--gradient);
            color: white;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .today-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 209, 197, 0.3);
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_student.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="mb-4">
                <h2 class="mb-1">
                    <i class="fas fa-calendar-alt me-2" style="color: var(--primary-mint);"></i>
                    Event Calendar
                </h2>
                <p class="text-muted">View all events from your organizations</p>
            </div>
            
            <!-- Calendar -->
            <div class="calendar-container">
                <!-- Navigation -->
                <div class="calendar-header">
                    <div>
                        <a href="?month=<?php echo $selected_month == 1 ? 12 : $selected_month - 1; ?>&year=<?php echo $selected_month == 1 ? $selected_year - 1 : $selected_year; ?>" class="nav-btn me-2">
                            <i class="fas fa-chevron-left me-1"></i> <?php echo $month_names[$selected_month == 1 ? 12 : $selected_month - 1]; ?>
                        </a>
                        <a href="?month=<?php echo $selected_month == 12 ? 1 : $selected_month + 1; ?>&year=<?php echo $selected_month == 12 ? $selected_year + 1 : $selected_year; ?>" class="nav-btn">
                            <?php echo $month_names[$selected_month == 12 ? 1 : $selected_month + 1]; ?> <i class="fas fa-chevron-right ms-1"></i>
                        </a>
                    </div>
                    <h4 class="mb-0"><?php echo $month_names[$selected_month] . ' ' . $selected_year; ?></h4>
                    <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" class="today-btn">
                        <i class="fas fa-calendar-day me-2"></i>Today
                    </a>
                </div>
                
                <!-- Weekday Headers -->
                <div class="calendar-grid">
                    <div class="calendar-weekday">Sun</div>
                    <div class="calendar-weekday">Mon</div>
                    <div class="calendar-weekday">Tue</div>
                    <div class="calendar-weekday">Wed</div>
                    <div class="calendar-weekday">Thu</div>
                    <div class="calendar-weekday">Fri</div>
                    <div class="calendar-weekday">Sat</div>
                    
                    <!-- Empty cells before month starts -->
                    <?php for ($i = 0; $i < $starting_weekday; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>
                    
                    <!-- Days of the month -->
                    <?php for ($day = 1; $day <= $days_in_month; $day++): 
                        $is_today = ($day == date('j') && $selected_month == date('m') && $selected_year == date('Y'));
                    ?>
                        <div class="calendar-day <?php echo $is_today ? 'today' : ''; ?>">
                            <span class="day-number"><?php echo $day; ?></span>
                            <?php if (isset($events_by_day[$day])): ?>
                                <?php foreach ($events_by_day[$day] as $event): ?>
                                    <div class="calendar-event" 
                                         onclick="window.location.href='events.php?register=<?php echo $event['event_id']; ?>'"
                                         title="<?php echo htmlspecialchars($event['event_title']); ?>">
                                        <span class="event-dot" style="background: <?php echo $event['org_color'] ?? '#4FD1C5'; ?>;"></span>
                                        <?php echo substr(htmlspecialchars($event['event_title']), 0, 15); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Upcoming Events List -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-week me-2" style="color: var(--primary-mint);"></i>
                        Upcoming Events
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if($upcoming_events->num_rows > 0): ?>
                        <?php while($event = $upcoming_events->fetch_assoc()): ?>
                            <div class="d-flex align-items-center p-3 border-bottom hover-lift" 
                                 onclick="window.location.href='events.php?register=<?php echo $event['event_id']; ?>'"
                                 style="cursor: pointer;">
                                <div class="event-date-badge me-3">
                                    <div class="day"><?php echo date('d', strtotime($event['start_datetime'])); ?></div>
                                    <div class="month"><?php echo date('M', strtotime($event['start_datetime'])); ?></div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($event['event_title']); ?></h6>
                                    <p class="text-muted small mb-0">
                                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($event['org_name']); ?>
                                        <i class="fas fa-map-marker-alt ms-3 me-1"></i><?php echo htmlspecialchars($event['venue']); ?>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($event['start_datetime'])); ?></small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <p class="text-muted">No upcoming events scheduled.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>