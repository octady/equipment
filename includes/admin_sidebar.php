<?php
// Admin Sidebar Component - Push Layout with Toggle
?>
<style>
/* SIDEBAR TRIGGER BUTTON */
.sidebar-trigger {
    position: fixed;
    top: 20px;
    left: 20px;
    width: 48px;
    height: 48px;
    background: var(--white, #fff);
    border: none;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    z-index: 998;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.sidebar-trigger:hover {
    background: #087F8A;
    box-shadow: 0 6px 25px rgba(8, 127, 138, 0.3);
}

.sidebar-trigger i {
    font-size: 20px;
    color: #334155;
    transition: color 0.3s;
}

.sidebar-trigger:hover i {
    color: #fff;
}

.sidebar-trigger.hidden {
    opacity: 0;
    visibility: hidden;
    transform: translateX(-20px);
}

/* ADMIN SIDEBAR STYLES */
.admin-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    height: 100vh;
    background: linear-gradient(180deg, #0F4C5C 0%, #0a3640 100%);
    z-index: 1000;
    transform: translateX(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
}

.admin-sidebar.active {
    transform: translateX(0);
}

/* Push content when sidebar is active */
.admin-sidebar.active ~ .admin-main {
    margin-left: 280px;
}

/* Reset page-info padding when sidebar is active */
.admin-sidebar.active ~ .admin-main .page-info {
    padding-left: 0;
}

/* Sidebar Header */
.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    gap: 14px;
}

.sidebar-logo {
    height: 100px;
    width: auto;
}

.sidebar-logo img {
    height: 100%;
    width: auto;
    object-fit: contain;
}

.sidebar-close {
    margin-left: auto;
    width: 32px;
    height: 32px;
    border: none;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.8);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.sidebar-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

/* Sidebar Navigation */
.sidebar-nav {
    flex: 1;
    padding: 20px 12px;
    overflow-y: auto;
}

.nav-section {
    margin-bottom: 24px;
}

.nav-section-title {
    font-size: 11px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 0 12px;
    margin-bottom: 10px;
}

.nav-item {
    margin-bottom: 4px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    color: rgba(255, 255, 255, 0.75);
    text-decoration: none;
    border-radius: 10px;
    transition: all 0.2s;
    font-size: 14px;
    font-weight: 500;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
}

.nav-link i {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

/* Submenu */
.nav-submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    padding-left: 20px;
}

.nav-item.open .nav-submenu {
    max-height: 200px;
}

.nav-item.has-submenu > .nav-link::after {
    content: '\f078';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    font-size: 10px;
    margin-left: auto;
    transition: transform 0.3s;
}

.nav-item.has-submenu.open > .nav-link::after {
    transform: rotate(180deg);
}

.nav-submenu .nav-link {
    padding: 10px 14px;
    font-size: 13px;
}

.nav-submenu .nav-link i {
    font-size: 6px;
    color: rgba(255, 255, 255, 0.4);
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.admin-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
}

.admin-avatar {
    width: 40px;
    height: 40px;
    background: rgba(8, 127, 138, 0.5);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 14px;
}

.admin-info h4 {
    font-size: 13px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 2px;
}

.admin-info span {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.7);
}

/* Logout Button */
.logout-btn-sidebar {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 16px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff;
    text-decoration: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
}

.logout-btn-sidebar:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}

.logout-btn-sidebar i {
    font-size: 14px;
}

/* Main Content Transition */
.admin-main {
    margin-left: 0;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 100vh;
}

/* Responsive */
@media (max-width: 992px) {
    .admin-sidebar.active ~ .admin-main {
        margin-left: 0;
    }
}

@media (max-width: 768px) {
    .admin-sidebar {
        width: 260px;
    }
    
    .sidebar-trigger {
        top: 16px;
        left: 16px;
        width: 44px;
        height: 44px;
    }
}
</style>

<!-- Sidebar Trigger Button (Hamburger) -->
<button class="sidebar-trigger" id="sidebarTrigger">
    <i class="fas fa-bars"></i>
</button>

<!-- Admin Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Header -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="img/logo_bandara_putih.png" alt="InJourney Logo">
        </div>
        <button class="sidebar-close" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Menu Utama</div>
            
            <div class="nav-item">
                <a href="admin_dashboard.php" class="nav-link active">
                    <i class="fas fa-th-large"></i>
                    Dashboard
                </a>
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Manajemen Data</div>
            
            <div class="nav-item has-submenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu(this); return false;">
                    <i class="fas fa-database"></i>
                    Kelola Master
                </a>
                <div class="nav-submenu">
                    <a href="admin_lokasi.php" class="nav-link">
                        <i class="fas fa-circle"></i>
                        Kelola Lokasi
                    </a>
                    <a href="admin_jenis_peralatan.php" class="nav-link">
                        <i class="fas fa-circle"></i>
                        Kelola Jenis Peralatan
                    </a>
                    <a href="admin_peralatan.php" class="nav-link">
                        <i class="fas fa-circle"></i>
                        Kelola Peralatan
                    </a>
                </div>
            </div>

            <div class="nav-item">
                <a href="admin_users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Kelola User
                </a>
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Pelaporan</div>
            
            <div class="nav-item has-submenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu(this); return false;">
                    <i class="fas fa-file-alt"></i>
                    Laporan
                </a>
                <div class="nav-submenu">
                    <a href="admin_laporan_harian.php" class="nav-link">
                        <i class="fas fa-circle"></i>
                        Laporan Harian
                    </a>
                    <a href="admin_laporan_bulanan.php" class="nav-link">
                        <i class="fas fa-circle"></i>
                        Laporan Bulanan
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="admin-profile">
            <div class="admin-avatar">AD</div>
            <div class="admin-info">
                <h4>Administrator</h4>
                <span>Supervisor</span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn-sidebar">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<script>
// Sidebar Elements
const sidebar = document.getElementById('adminSidebar');
const sidebarTrigger = document.getElementById('sidebarTrigger');
const sidebarClose = document.getElementById('sidebarClose');

// Open sidebar on hover trigger
sidebarTrigger.addEventListener('mouseenter', function() {
    sidebar.classList.add('active');
    sidebarTrigger.classList.add('hidden');
});

// Also support click
sidebarTrigger.addEventListener('click', function() {
    sidebar.classList.add('active');
    sidebarTrigger.classList.add('hidden');
});

// Close sidebar only with close button
sidebarClose.addEventListener('click', function() {
    sidebar.classList.remove('active');
    sidebarTrigger.classList.remove('hidden');
});

// Toggle submenu
function toggleSubmenu(element) {
    const navItem = element.parentElement;
    navItem.classList.toggle('open');
}

// Set active link based on current page
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
            // Open parent submenu if exists
            const parentSubmenu = link.closest('.nav-submenu');
            if (parentSubmenu) {
                parentSubmenu.parentElement.classList.add('open');
            }
        }
    });
    
    // If no link is active, set dashboard as active
    const activeLinks = document.querySelectorAll('.nav-link.active');
    if (activeLinks.length === 0) {
        const dashboardLink = document.querySelector('a[href="admin_dashboard.php"]');
        if (dashboardLink) dashboardLink.classList.add('active');
    }
});
</script>
