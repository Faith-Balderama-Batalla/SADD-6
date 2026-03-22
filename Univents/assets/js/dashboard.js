// assets/js/dashboard.js
// Consolidated JavaScript for all dashboard pages

document.addEventListener('DOMContentLoaded', function() {
    
    // ==================== SIDEBAR FUNCTIONALITY ====================
    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('collapseBtn');
    const menuToggle = document.getElementById('menuToggle');
    
    // Load saved sidebar state
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true' && sidebar) {
        sidebar.classList.add('collapsed');
    }
    
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-left');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        });
    }
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-show');
        });
        
        // Close mobile sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('mobile-show');
                }
            }
        });
    }
    
    // ==================== DROPDOWN MENUS ====================
    const profileBtn = document.getElementById('profileBtn');
    const profileMenu = document.getElementById('profileMenu');
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationMenu = document.getElementById('notificationMenu');
    
    if (profileBtn) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileMenu.classList.toggle('show');
            if (notificationMenu) notificationMenu.classList.remove('show');
        });
    }
    
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu.classList.toggle('show');
            if (profileMenu) profileMenu.classList.remove('show');
        });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        if (profileMenu) profileMenu.classList.remove('show');
        if (notificationMenu) notificationMenu.classList.remove('show');
    });
    
    // ==================== NOTIFICATION FUNCTIONS ====================
    function loadNotifications() {
        fetch('../assets/js/get-notifications.php')
            .then(response => response.json())
            .then(data => {
                const badge = notificationBtn ? notificationBtn.querySelector('.notification-badge') : null;
                const notificationList = document.getElementById('notificationList');
                
                if (badge) {
                    if (data.unread_count > 0) {
                        if (!badge) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'notification-badge';
                            newBadge.textContent = data.unread_count;
                            notificationBtn.appendChild(newBadge);
                        } else {
                            badge.textContent = data.unread_count;
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                }
                
                if (notificationList && data.notifications.length > 0) {
                    let html = '';
                    data.notifications.forEach(notif => {
                        html += `
                            <div class="notification-item ${!notif.is_read ? 'unread' : ''}">
                                <div class="notification-icon">
                                    <i class="fas fa-${getNotificationIcon(notif.type)}"></i>
                                </div>
                                <div class="notification-content">
                                    <strong>${escapeHtml(notif.title)}</strong>
                                    <p class="mb-0 small">${escapeHtml(notif.message)}</p>
                                    <small class="text-muted">${notif.time}</small>
                                </div>
                            </div>
                        `;
                    });
                    notificationList.innerHTML = html;
                } else if (notificationList) {
                    notificationList.innerHTML = '<div class="notification-item"><div class="notification-content"><p class="mb-0 text-muted">No notifications</p></div></div>';
                }
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }
    
    function getNotificationIcon(type) {
        switch(type) {
            case 'event': return 'calendar-alt';
            case 'announcement': return 'bullhorn';
            case 'attendance': return 'check-circle';
            case 'system': return 'cog';
            default: return 'bell';
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Load notifications on page load
    if (notificationBtn) {
        loadNotifications();
        
        // Auto-refresh every 30 seconds
        setInterval(loadNotifications, 30000);
    }
    
    // ==================== FORM SUBMISSION WITH LOADING ====================
    const forms = document.querySelectorAll('form[data-loading="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
                
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 10000);
            }
        });
    });
    
    // ==================== DELETE CONFIRMATION ====================
    window.confirmDelete = function(id, name, type = 'item') {
        if (confirm(`Delete "${name}"? This action cannot be undone.`)) {
            window.location.href = `${type}.php?delete=${id}`;
        }
    };
    
    // ==================== TOAST NOTIFICATIONS ====================
    window.showToast = function(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };
    
    // ==================== TABLE SEARCH ====================
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.searchable-table tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
    
    // ==================== EXPORT TABLE TO CSV ====================
    window.exportToCSV = function(tableId, filename = 'export.csv') {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tr');
        const csvData = [];
        
        rows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('th, td');
            cells.forEach(cell => {
                rowData.push('"' + cell.textContent.replace(/"/g, '""') + '"');
            });
            csvData.push(rowData.join(','));
        });
        
        const blob = new Blob([csvData.join('\n')], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        window.URL.revokeObjectURL(url);
        
        showToast('Export completed successfully!', 'success');
    };
    
    // ==================== AUTO-HIDE ALERTS ====================
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // ==================== THEME TOGGLE ====================
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        const currentTheme = localStorage.getItem('theme') || 'light';
        
        if (currentTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        } else {
            themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        }
        
        themeToggle.addEventListener('click', () => {
            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }
        });
    }
});

// ==================== CSRF TOKEN HELPER ====================
function getCSRFToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : '';
}