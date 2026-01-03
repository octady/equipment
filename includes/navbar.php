<?php
// Determine active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header class="top-header">
    <div class="header-content">
        <div class="logo-left">
            <img src="assets/img/logo_injourney.png" alt="InJourney Airports" class="logo-injourney">
        </div>
        
        <!-- Hamburger Menu Button (Mobile Only) -->
        <button class="hamburger-menu" id="hamburgerBtn" onclick="toggleMobileMenu()">
            <i class="fa-solid fa-bars" id="hamburgerIcon"></i>
        </button>
        
        <nav class="navbar-center" id="navbarMenu">
            <ul class="nav-menu">
                <li class="nav-item <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                    <a href="dashboard.php">Beranda</a>
                </li>
                <li class="nav-item <?= ($current_page == 'monitoring.php') ? 'active' : '' ?>">
                    <a href="monitoring.php">Monitoring</a>
                </li>
                <li class="nav-item <?= ($current_page == 'history.php') ? 'active' : '' ?>">
                    <a href="history.php">Riwayat Monitoring</a>
                </li>
            </ul>
            <button class="logout-btn" onclick="location.href='logout.php'">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Keluar</span>
            </button>
        </nav>
        <div class="logo-right">
            <img src="assets/img/logo_bandara.png" alt="Bandara" class="logo-bandara">
        </div>
    </div>
</header>

<style>
/* ========================================
   NAVBAR STYLES
   ======================================== */
.top-header {
    background: #fff;
    box-shadow: 0 2px 15px rgba(8, 127, 138, 0.1);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
}

.header-content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 30px;
}

.logo-left, .logo-right {
    display: flex;
    align-items: center;
}

.logo-injourney {
    height: 60px;
    width: auto;
    transform: scale(1.25);
    transform-origin: left center;
}

.logo-bandara {
    height: 70px;
    width: auto;
    transform: scale(1.2);
    transform-origin: right center;
}

.navbar-center {
    display: flex;
    align-items: center;
    gap: 30px;
}

.nav-menu {
    display: flex;
    align-items: center;
    gap: 8px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-item {
    position: relative;
}

.nav-item > a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    color: #475569;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    border-radius: 0;
    transition: color 0.3s ease;
    position: relative;
    background: transparent;
}

/* Smooth underline animation */
.nav-item > a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 2px;
    background: #087F8A;
    border-radius: 2px;
    transition: width 0.3s ease;
}

.nav-item > a:hover {
    color: #087F8A;
    background: transparent;
}

.nav-item > a:hover::after {
    width: 100%;
}

.nav-item.active > a {
    color: #087F8A;
    font-weight: 600;
    background: transparent;
}

.nav-item.active > a::after {
    width: 100%;
}

/* Dropdown */
.dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    background: #fff;
    min-width: 180px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.3s ease;
    padding: 8px 0;
    margin-top: 8px;
    list-style: none;
    z-index: 100;
}

.dropdown:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-menu li a {
    display: block;
    padding: 10px 18px;
    color: #475569;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
}

.dropdown-menu li a:hover {
    background: #e6f7f8;
    color: #087F8A;
}

.dropdown-menu li a.active-dropdown {
    color: #087F8A;
    font-weight: 600;
}

/* Logout Button */
.logout-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #087F8A;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: inherit;
}

.logout-btn:hover {
    background: #065C63;
    box-shadow: 0 4px 12px rgba(8, 127, 138, 0.25);
}

.logout-btn i {
    font-size: 12px;
}

/* ========================================
   HAMBURGER MENU STYLES (Mobile Navigation)
   ======================================== */
.hamburger-menu {
    display: none;
    background: none;
    border: none;
    font-size: 24px;
    color: #087F8A;
    cursor: pointer;
    padding: 10px;
    border-radius: 8px;
    transition: all 0.3s;
    z-index: 101;
}

.hamburger-menu:hover {
    background: #e8f5f7;
}

/* Mobile Responsive Navbar */
@media (max-width: 768px) {
    .hamburger-menu {
        display: flex;
        align-items: center;
        justify-content: center;
        order: 2;
    }
    
    .header-content {
        flex-wrap: wrap;
        padding: 12px 15px;
    }
    
    .logo-left {
        order: 1;
    }
    
    .logo-right {
        order: 3;
    }
    
    .navbar-center {
        display: none;
        order: 4;
        width: 100%;
        flex-direction: column;
        gap: 0;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e5e7eb;
        animation: slideDown 0.3s ease;
    }
    
    .navbar-center.active {
        display: flex;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .nav-menu {
        flex-direction: column;
        gap: 0;
        width: 100%;
    }
    
    .nav-item {
        width: 100%;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .nav-item > a {
        padding: 15px 20px;
        display: block;
        text-align: center;
    }
    
    .nav-item:not(.active) > a::after {
        display: none;
    }
    
    /* Dropdown for mobile */
    .dropdown .dropdown-menu {
        position: static;
        opacity: 1;
        visibility: visible;
        transform: none;
        box-shadow: none;
        border-radius: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        background: #f8f9fa;
    }
    
    .dropdown.open .dropdown-menu {
        max-height: 200px;
    }
    
    .dropdown-menu li a {
        text-align: center;
        padding: 12px 20px;
    }
    
    .logout-btn {
        width: 100%;
        justify-content: center;
        margin-top: 15px;
        padding: 14px;
        border-radius: 10px;
    }
    
    .logo-injourney {
        height: 45px;
    }
    
    .logo-bandara {
        height: 50px;
    }
}

@media (max-width: 480px) {
    .header-content {
        padding: 10px 12px;
    }
    
    .hamburger-menu {
        font-size: 22px;
        padding: 8px;
    }
    
    .logo-injourney {
        height: 38px;
    }
    
    .logo-bandara {
        height: 42px;
    }
    
    .nav-item > a {
        padding: 14px 15px;
        font-size: 14px;
    }
    
    .logout-btn {
        padding: 12px;
        font-size: 14px;
    }
}
</style>

<script>
// Toggle Mobile Menu
function toggleMobileMenu() {
    const navbar = document.getElementById('navbarMenu');
    const icon = document.getElementById('hamburgerIcon');
    
    navbar.classList.toggle('active');
    
    // Change icon between bars and X
    if (navbar.classList.contains('active')) {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
    } else {
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
    }
}

// Toggle Dropdown on Mobile
function toggleDropdown(element) {
    if (window.innerWidth <= 768) {
        element.classList.toggle('open');
    }
}

// Close menu when clicking outside
document.addEventListener('click', function(event) {
    const navbar = document.getElementById('navbarMenu');
    const hamburger = document.getElementById('hamburgerBtn');
    
    if (navbar && !navbar.contains(event.target) && !hamburger.contains(event.target)) {
        navbar.classList.remove('active');
        const icon = document.getElementById('hamburgerIcon');
        if (icon) {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }
});

// Close menu on window resize if going to desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        const navbar = document.getElementById('navbarMenu');
        const icon = document.getElementById('hamburgerIcon');
        if (navbar) {
            navbar.classList.remove('active');
        }
        if (icon) {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }
});
</script>
