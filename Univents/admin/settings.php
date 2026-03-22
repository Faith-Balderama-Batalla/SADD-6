<?php
// admin/settings.php
require_once '../config.php';
requireLogin();
$user = getCurrentUser($conn);
if ($user['role'] !== 'admin') { 
    header('Location: ../student/dashboard.php'); 
    exit(); 
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    if (isset($_POST['update_system_settings'])) {
        $site_name = mysqli_real_escape_string($conn, $_POST['site_name']);
        $site_url = mysqli_real_escape_string($conn, $_POST['site_url']);
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $allow_registration = isset($_POST['allow_registration']) ? 1 : 0;
        
        // Store settings in database (you may need to create a settings table)
        // For now, we'll use a JSON file
        $settings = [
            'site_name' => $site_name,
            'site_url' => $site_url,
            'maintenance_mode' => $maintenance_mode,
            'allow_registration' => $allow_registration,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if (file_put_contents('../config/settings.json', json_encode($settings, JSON_PRETTY_PRINT))) {
            $success = "System settings updated successfully!";
        } else {
            $error = "Failed to save settings.";
        }
    }
    
    if (isset($_POST['update_theme_settings'])) {
        $primary_color = mysqli_real_escape_string($conn, $_POST['primary_color']);
        $secondary_color = mysqli_real_escape_string($conn, $_POST['secondary_color']);
        $theme = mysqli_real_escape_string($conn, $_POST['theme']);
        
        $theme_settings = [
            'primary_color' => $primary_color,
            'secondary_color' => $secondary_color,
            'theme' => $theme,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if (file_put_contents('../config/theme.json', json_encode($theme_settings, JSON_PRETTY_PRINT))) {
            $success = "Theme settings updated successfully!";
        } else {
            $error = "Failed to save theme settings.";
        }
    }
    
    if (isset($_POST['clear_cache'])) {
        // Clear cache files
        $cache_dir = '../cache/';
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $success = "Cache cleared successfully!";
        } else {
            $error = "Cache directory not found.";
        }
    }
}

// Load current settings
$system_settings = [];
$theme_settings = [];

if (file_exists('../config/settings.json')) {
    $system_settings = json_decode(file_get_contents('../config/settings.json'), true);
}
if (file_exists('../config/theme.json')) {
    $theme_settings = json_decode(file_get_contents('../config/theme.json'), true);
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
    <title>System Settings - Univents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-body">
    <?php include 'nav_sidebar_admin.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="mb-4">
                <h2 class="mb-1">
                    <i class="fas fa-cog me-2" style="color: var(--primary-mint);"></i>
                    System Settings
                </h2>
                <p class="text-muted">Configure system-wide settings and preferences</p>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row g-4">
                <!-- System Settings -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-globe me-2" style="color: var(--primary-mint);"></i>
                                System Configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Site Name</label>
                                    <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($system_settings['site_name'] ?? 'Univents'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Site URL</label>
                                    <input type="url" name="site_url" class="form-control" value="<?php echo htmlspecialchars($system_settings['site_url'] ?? 'http://localhost/EMS'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="maintenance_mode" class="form-check-input" id="maintenance_mode" <?php echo ($system_settings['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">
                                            Maintenance Mode
                                        </label>
                                        <small class="d-block text-muted">When enabled, only administrators can access the system</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="allow_registration" class="form-check-input" id="allow_registration" <?php echo ($system_settings['allow_registration'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_registration">
                                            Allow New Registrations
                                        </label>
                                        <small class="d-block text-muted">Allow new users to sign up through the registration page</small>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_system_settings" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>Save System Settings
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Cache Management -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-database me-2" style="color: var(--primary-mint);"></i>
                                Cache Management
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="mb-3">
                                    <p class="text-muted">Clear cached data to apply recent changes and free up storage space.</p>
                                </div>
                                
                                <button type="submit" name="clear_cache" class="btn btn-warning w-100" onclick="return confirm('Clear all cached data? This action cannot be undone.')">
                                    <i class="fas fa-trash-alt me-2"></i>Clear All Cache
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Theme Settings -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-palette me-2" style="color: var(--primary-mint);"></i>
                                Theme Configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Default Theme</label>
                                    <select name="theme" class="form-control">
                                        <option value="light" <?php echo ($theme_settings['theme'] ?? 'light') == 'light' ? 'selected' : ''; ?>>Light</option>
                                        <option value="dark" <?php echo ($theme_settings['theme'] ?? 'light') == 'dark' ? 'selected' : ''; ?>>Dark</option>
                                        <option value="auto" <?php echo ($theme_settings['theme'] ?? 'light') == 'auto' ? 'selected' : ''; ?>>Auto (System Preference)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Primary Color</label>
                                    <input type="color" name="primary_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($theme_settings['primary_color'] ?? '#4FD1C5'); ?>">
                                    <small class="text-muted">This color will be used for primary buttons and accents</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Secondary Color</label>
                                    <input type="color" name="secondary_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($theme_settings['secondary_color'] ?? '#38B2AC'); ?>">
                                    <small class="text-muted">This color will be used for secondary elements</small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Theme changes will be applied after page refresh.
                                </div>
                                
                                <button type="submit" name="update_theme_settings" class="btn btn-primary w-100">
                                    <i class="fas fa-palette me-2"></i>Save Theme Settings
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- System Information -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2" style="color: var(--primary-mint);"></i>
                                System Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                            </div>
                            <div class="mb-2">
                                <strong>MySQL Version:</strong> <?php echo $conn->server_info; ?>
                            </div>
                            <div class="mb-2">
                                <strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?>
                            </div>
                            <div class="mb-2">
                                <strong>Upload Max Size:</strong> <?php echo ini_get('upload_max_filesize'); ?>
                            </div>
                            <div class="mb-2">
                                <strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?>
                            </div>
                            <div class="mb-2">
                                <strong>Session Save Path:</strong> <?php echo session_save_path() ?: 'Default'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Backup & Export -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0">
                        <i class="fas fa-database me-2" style="color: var(--primary-mint);"></i>
                        Backup & Export
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                <h6>Database Backup</h6>
                                <p class="small text-muted">Export your entire database for backup purposes</p>
                                <button class="btn btn-outline-primary" onclick="exportDatabase()">
                                    <i class="fas fa-download me-2"></i>Export Database
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <h6>Activity Logs</h6>
                                <p class="small text-muted">Export system activity logs for auditing</p>
                                <button class="btn btn-outline-primary" onclick="exportLogs()">
                                    <i class="fas fa-download me-2"></i>Export Logs
                                </button>
                            </div>
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
function exportDatabase() {
    showToast('Database export will be available in Phase 2', 'info');
}

function exportLogs() {
    showToast('Activity log export will be available in Phase 2', 'info');
}
</script>
</body>
</html>