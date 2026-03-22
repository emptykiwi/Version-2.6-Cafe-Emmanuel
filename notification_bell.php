<!-- Notification Bell Component -->
<style>
.notification-bell {
    position: relative;
    display: inline-block;
}
.notification-bell .nav-cart-link {
    position: relative;
}
.notification-badge {
    position: absolute;
    top: -5px;
    right: -8px;
    background-color: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
    display: none;
}
.notification-badge.show {
    display: flex;
}
.notification-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    background: white;
    min-width: 360px;
    max-width: 400px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    z-index: 1000;
    max-height: 500px;
    overflow-y: auto;
}
.notification-dropdown.show {
    display: block;
}
.notification-header {
    padding: 16px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.notification-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}
.mark-all-read {
    background: none;
    border: none;
    color: #2196F3;
    font-size: 13px;
    cursor: pointer;
    font-weight: 600;
}
.mark-all-read:hover {
    text-decoration: underline;
}
.notification-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.notification-item {
    padding: 14px 20px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background 0.2s;
}
.notification-item:hover {
    background: #f8f9fa;
}
.notification-item.unread {
    background: #f0f7ff;
}
.notification-item.unread:hover {
    background: #e3f2fd;
}
.notification-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    vertical-align: middle;
}
.notification-icon.order_accepted {
    background: #e8f5e9;
    color: #4caf50;
}
.notification-icon.order_completed {
    background: #e3f2fd;
    color: #2196f3;
}
.notification-icon.order_cancelled {
    background: #ffebee;
    color: #f44336;
}
.notification-content {
    display: inline-block;
    vertical-align: middle;
    width: calc(100% - 48px);
}
.notification-title {
    font-weight: 600;
    font-size: 14px;
    color: #333;
    margin: 0 0 4px 0;
}
.notification-message {
    font-size: 13px;
    color: #666;
    margin: 0 0 4px 0;
}
.notification-time {
    font-size: 11px;
    color: #999;
}
.no-notifications {
    padding: 40px 20px;
    text-align: center;
    color: #999;
}
.no-notifications i {
    font-size: 48px;
    margin-bottom: 12px;
    display: block;
    opacity: 0.3;
}
</style>

<div class="notification-bell">
    <a href="#" class="nav-cart-link" id="notificationBell">
        <i class="fas fa-bell"></i>
        <span class="notification-badge" id="notificationBadge">0</span>
    </a>
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button class="mark-all-read" id="markAllRead" style="display: none;">Mark all as read</button>
        </div>
        <ul class="notification-list" id="notificationList">
            <li class="no-notifications">
                <i class="fas fa-bell"></i>
                <p>No notifications yet</p>
            </li>
        </ul>
    </div>
</div>

<script>
let notificationsOpen = false;

// Load notifications
function loadNotifications() {
    fetch('get_notifications.php?action=count')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.count > 0) {
                document.getElementById('notificationBadge').textContent = data.count;
                document.getElementById('notificationBadge').classList.add('show');
                document.getElementById('markAllRead').style.display = 'block';
            } else {
                document.getElementById('notificationBadge').classList.remove('show');
                document.getElementById('markAllRead').style.display = 'none';
            }
        });
}

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
    return date.toLocaleDateString();
}

function getNotificationIcon(type) {
    const icons = {
        'order_accepted': 'fa-check-circle',
        'order_completed': 'fa-check-double',
        'order_cancelled': 'fa-times-circle'
    };
    return icons[type] || 'fa-bell';
}

function loadNotificationList() {
    fetch('get_notifications.php?action=list&limit=20')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.notifications.length > 0) {
                const list = document.getElementById('notificationList');
                list.innerHTML = data.notifications.map(notif => `
                    <li class="notification-item ${!notif.is_read ? 'unread' : ''}" data-id="${notif.id}" onclick="markNotificationRead(${notif.id})">
                        <span class="notification-icon ${notif.type}">
                            <i class="fas ${getNotificationIcon(notif.type)}"></i>
                        </span>
                        <div class="notification-content">
                            <p class="notification-title">${notif.title}</p>
                            <p class="notification-message">${notif.message}</p>
                            <span class="notification-time">${formatTimeAgo(notif.created_at)}</span>
                        </div>
                    </li>
                `).join('');
            }
        });
}

function markNotificationRead(id) {
    fetch(`get_notifications.php?action=mark_read&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
                loadNotificationList();
            }
        });
}

// Toggle notification dropdown
document.getElementById('notificationBell').addEventListener('click', function(e) {
    e.preventDefault();
    notificationsOpen = !notificationsOpen;
    const dropdown = document.getElementById('notificationDropdown');
    
    if (notificationsOpen) {
        dropdown.classList.add('show');
        loadNotificationList();
    } else {
        dropdown.classList.remove('show');
    }
});

// Mark all as read
document.getElementById('markAllRead').addEventListener('click', function() {
    fetch('get_notifications.php?action=mark_all_read')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
                loadNotificationList();
            }
        });
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.notification-bell')) {
        document.getElementById('notificationDropdown').classList.remove('show');
        notificationsOpen = false;
    }
});

// Load notification count on page load and refresh every 5 seconds
loadNotifications();
setInterval(loadNotifications, 5000);
</script>
