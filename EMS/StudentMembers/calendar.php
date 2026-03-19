<?php
// calendar.php
require_once '../config.php';

if (!isLoggedIn()) {
    header('Location: ../login-signup.php');
    exit();
}

$user = getCurrentUser($conn);
$user_id = $user['user_id'];

// Get organizations for sidebar dropdown
$orgs_sql = "SELECT o.* FROM organizations o
             JOIN organization_memberships om ON o.org_id = om.org_id
             WHERE om.user_id = ? AND om.status = 'active'
             ORDER BY o.org_name ASC";
$orgs_stmt = $conn->prepare($orgs_sql);
$orgs_stmt->bind_param("i", $user_id);
$orgs_stmt->execute();
$my_organizations = $orgs_stmt->get_result();

// Get unread notifications count
$notif_sql = "SELECT COUNT(*) as unread FROM notifications 
              WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$unread_count = $notif_result->fetch_assoc()['unread'] ?? 0;

// Get current month/year from URL or default to current
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Calculate previous and next month
$prev_month = $selected_month == 1 ? 12 : $selected_month - 1;
$prev_year = $selected_month == 1 ? $selected_year - 1 : $selected_year;
$next_month = $selected_month == 12 ? 1 : $selected_month + 1;
$next_year = $selected_month == 12 ? $selected_year + 1 : $selected_year;

// Get month name
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$month_name = $month_names[$selected_month];

// Get first day of month and number of days
$first_day = mktime(0, 0, 0, $selected_month, 1, $selected_year);
$days_in_month = date('t', $first_day);
$starting_weekday = date('w', $first_day); // 0 = Sunday, 1 = Monday, etc.

// Get events for this month
$events_sql = "SELECT e.*, o.org_name, o.org_color 
               FROM events e
               JOIN organizations o ON e.org_id = o.org_id
               JOIN organization_memberships om ON o.org_id = om.org_id
               WHERE om.user_id = ? 
               AND MONTH(e.start_datetime) = ? 
               AND YEAR(e.start_datetime) = ?
               ORDER BY e.start_datetime ASC";
$events_stmt = $conn->prepare($events_sql);
$events_stmt->bind_param("iii", $user_id, $selected_month, $selected_year);
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

// Get upcoming events (next 30 days) for the list view
$upcoming_sql = "SELECT e.*, o.org_name, o.org_color 
                 FROM events e
                 JOIN organizations o ON e.org_id = o.org_id
                 JOIN organization_memberships om ON o.org_id = om.org_id
                 WHERE om.user_id = ? 
                 AND e.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
                 ORDER BY e.start_datetime ASC
                 LIMIT 10";
$upcoming_stmt = $conn->prepare($upcoming_sql);
$upcoming_stmt->bind_param("i", $user_id);
$upcoming_stmt->execute();
$upcoming_events = $upcoming_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Univents</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../style.css">
    <style>
        /* Calendar-specific styles */
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
            border: 1px solid rgba(79, 209, 197, 0.1);
        }
        
        .calendar-weekday {
            text-align: center;
            font-weight: 600;
            color: var(--text-dark);
            padding: 10px;
            background: rgba(79, 209, 197, 0.05);
            border-radius: 8px;
            margin-bottom: 5px;
        }
        
        .calendar-day {
            min-height: 100px;
            background: #F8FAFC;
            border-radius: 12px;
            padding: 8px;
            position: relative;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .calendar-day:hover {
            background: white;
            border-color: var(--primary-mint);
            box-shadow: 0 5px 15px rgba(79, 209, 197, 0.1);
        }
        
        .calendar-day.empty {
            background: transparent;
            min-height: auto;
        }
        
        .calendar-day.empty:hover {
            border-color: transparent;
            box-shadow: none;
        }
        
        .day-number {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
            display: block;
        }
        
        .calendar-event {
            font-size: 0.7rem;
            padding: 2px 4px;
            margin-bottom: 2px;
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
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 4px;
        }
        
        .upcoming-event-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid rgba(79, 209, 197, 0.1);
            transition: all 0.3s ease;
        }
        
        .upcoming-event-item:hover {
            background: rgba(79, 209, 197, 0.05);
        }
        
        .upcoming-event-date {
            min-width: 50px;
            text-align: center;
            margin-right: 15px;
        }
        
        .upcoming-event-day {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-mint);
            line-height: 1;
        }
        
        .upcoming-event-month {
            font-size: 0.7rem;
            color: var(--text-light);
            text-transform: uppercase;
        }
        
        .upcoming-event-details {
            flex: 1;
        }
        
        .upcoming-event-title {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .upcoming-event-org {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .upcoming-event-time {
            font-size: 0.75rem;
            color: var(--text-light);
        }
        
        .month-nav-btn {
            padding: 8px 16px;
            border: 1px solid rgba(79, 209, 197, 0.2);
            background: white;
            border-radius: 8px;
            color: var(--text-dark);
            transition: all 0.3s ease;
        }
        
        .month-nav-btn:hover {
            background: var(--primary-mint);
            color: white;
            border-color: var(--primary-mint);
        }
        
        .today-btn {
            padding: 8px 16px;
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .today-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 209, 197, 0.3);
        }
    </style>
</head>
<body class="dashboard-body">
    
    <?php include 'nav_sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Event Calendar</h2>
                    <p class="text-muted">View all upcoming events from your organizations</p>
                </div>
            </div>

            <!-- Calendar Navigation -->
            <div class="calendar-header">
                <div>
                    <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn month-nav-btn me-2">
                        <i class="fas fa-chevron-left me-1"></i> <?php echo $month_names[$prev_month];
                        ?>
                    </a>
                    <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn month-nav-btn">
                        <?php echo $month_names[$next_month]; ?> <i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </div>
                <h3 class="mb-0"><?php echo $month_name . ' ' . $selected_year; ?></h3>
                <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" class="today-btn">
                    <i class="fas fa-calendar-day me-2"></i>Today
                </a>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-grid mb-4">
                <!-- Weekday headers -->
                <div class="calendar-weekday">Sun</div>
                <div class="calendar-weekday">Mon</div>
                <div class="calendar-weekday">Tue</div>
                <div class="calendar-weekday">Wed</div>
                <div class="calendar-weekday">Thu</div>
                <div class="calendar-weekday">Fri</div>
                <div class="calendar-weekday">Sat</div>

                <!-- Empty cells for days before month starts -->
                <?php for ($i = 0; $i < $starting_weekday; $i++): ?>
                    <div class="calendar-day empty"></div>
                <?php endfor; ?>

                <!-- Days of the month -->
                <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                    <?php 
                    $is_today = ($day == date('j') && $selected_month == date('m') && $selected_year == date('Y'));
                    $day_class = $is_today ? 'calendar-day today' : 'calendar-day';
                    ?>
                    <div class="<?php echo $day_class; ?>" style="<?php echo $is_today ? 'background: rgba(79, 209, 197, 0.1); border: 2px solid var(--primary-mint);' : ''; ?>">
                        <span class="day-number"><?php echo $day; ?></span>
                        
                        <!-- Events for this day -->
                        <?php if (isset($events_by_day[$day])): ?>
                            <?php foreach ($events_by_day[$day] as $event): ?>
                                <div class="calendar-event" 
                                     data-bs-toggle="tooltip" 
                                     title="<?php echo htmlspecialchars($event['event_title'] . ' at ' . date('g:i A', strtotime($event['start_datetime']))); ?>"
                                     onclick="location.href='event.php?id=<?php echo $event['event_id']; ?>'">
                                    <span class="event-dot" style="background-color: <?php echo $event['org_color'] ?? '#4FD1C5'; ?>;"></span>
                                    <?php echo date('g:i A', strtotime($event['start_datetime'])); ?> - 
                                    <?php echo substr($event['event_title'], 0, 15) . (strlen($event['event_title']) > 15 ? '...' : ''); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>

                <!-- Empty cells for remaining days (if needed) -->
                <?php 
                $total_cells = $starting_weekday + $days_in_month;
                $remaining_cells = ceil($total_cells / 7) * 7 - $total_cells;
                for ($i = 0; $i < $remaining_cells; $i++): 
                ?>
                    <div class="calendar-day empty"></div>
                <?php endfor; ?>
            </div>

            <!-- Upcoming Events List -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i>Upcoming Events (Next 30 Days)</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($upcoming_events && $upcoming_events->num_rows > 0): ?>
                                <div class="upcoming-events-list">
                                    <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                                        <div class="upcoming-event-item" onclick="location.href='event.php?id=<?php echo $event['event_id']; ?>'" style="cursor: pointer;">
                                            <div class="upcoming-event-date">
                                                <div class="upcoming-event-day"><?php echo date('d', strtotime($event['start_datetime'])); ?></div>
                                                <div class="upcoming-event-month"><?php echo date('M', strtotime($event['start_datetime'])); ?></div>
                                            </div>
                                            <div class="upcoming-event-details">
                                                <div class="upcoming-event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                                                <div class="upcoming-event-org">
                                                    <i class="fas fa-building me-1"></i><?php echo $event['org_name']; ?>
                                                </div>
                                                <div class="upcoming-event-time">
                                                    <i class="far fa-clock me-1"></i><?php echo date('l, g:i A', strtotime($event['start_datetime'])); ?>
                                                </div>
                                            </div>
                                            <div class="upcoming-event-venue text-end">
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo $event['venue']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5>No upcoming events</h5>
                                    <p class="text-muted">There are no events scheduled in the next 30 days.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div> <!-- Close dashboard-container -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Initialize tooltips -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<!-- Same sidebar JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const collapseBtn = document.getElementById('collapseBtn');
        const menuToggle = document.getElementById('menuToggle');
        const orgDropdown = document.getElementById('orgDropdown');
        const orgMenu = document.getElementById('orgMenu');
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationMenu = document.getElementById('notificationMenu');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });
        }
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-show');
            });
        }
        
        if (orgDropdown) {
            orgDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                orgMenu.classList.toggle('show');
                const arrow = this.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('fa-chevron-down');
                    arrow.classList.toggle('fa-chevron-up');
                }
            });
        }
        
        let notificationTimeout;
        if (notificationBtn && notificationMenu) {
            notificationBtn.addEventListener('mouseenter', function() {
                clearTimeout(notificationTimeout);
                notificationMenu.classList.add('show');
            });
            
            notificationBtn.addEventListener('mouseleave', function() {
                notificationTimeout = setTimeout(() => {
                    if (!notificationMenu.matches(':hover')) {
                        notificationMenu.classList.remove('show');
                    }
                }, 300);
            });
            
            notificationMenu.addEventListener('mouseenter', function() {
                clearTimeout(notificationTimeout);
            });
            
            notificationMenu.addEventListener('mouseleave', function() {
                notificationTimeout = setTimeout(() => {
                    if (!notificationMenu.classList.contains('stay')) {
                        notificationMenu.classList.remove('show');
                    }
                }, 300);
            });
            
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationMenu.classList.toggle('stay');
                notificationMenu.classList.add('show');
            });
            
            document.addEventListener('click', function(e) {
                if (!notificationDropdown.contains(e.target)) {
                    notificationMenu.classList.remove('show', 'stay');
                }
            });
        }
    });
</script>
</body>
</html>