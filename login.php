<?php
session_start();
require_once 'config/database.php';

// If already logged in, redirect based on role
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error_message = '';

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password harus diisi!';
    } else {
        // Hash password with MD5 (sesuai dengan data di database)
        $hashedPassword = md5($password);
        
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username, nama_lengkap, role FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $username, $hashedPassword);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $error_message = 'Username atau password salah!';
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Equipment Monitoring System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 50%, #80deea 100%);
    min-height: 100vh;
}

/* CONTAINER */
.login-container {
    display: flex;
    min-height: 100vh;
    position: relative;
}

.login-container::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(224,247,250,0.9) 0%, rgba(178,235,242,0.85) 50%, rgba(128,222,234,0.9) 100%);
    z-index: 1;
}

/* LEFT SECTION */
.left-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 40px 30px 40px 80px;
    position: relative;
    z-index: 2;
}

.logo-container {
    width: 100%;
    max-width: 260px;
    margin-top: 9px;
    margin-bottom: 16px;
}

.logo-container img {
    width: 100%;
    height: auto;
    filter: drop-shadow(0 6px 12px rgba(8, 127, 138, 0.2));
    transition: transform 0.3s ease;
}

.logo-container:hover img {
    transform: scale(1.02);
}

.brand-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(8, 127, 138, 0.1);
    border: 1px solid rgba(8, 127, 138, 0.2);
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 600;
    color: #065C63;
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.brand-badge i {
    font-size: 10px;
    color: #087F8A;
}

.brand-title {
    font-size: clamp(26px, 4vw, 34px);
    font-weight: 700;
    margin-bottom: 14px;
    color: #087F8A;
    text-align: center;
    line-height: 1.2;
}

.brand-subtitle {
    font-size: clamp(13px, 2vw, 15px);
    text-align: center;
    max-width: 360px;
    color: #5a6c70;
    line-height: 1.6;
}

/* FEATURES */
.features {
    margin-top: 32px;
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    justify-content: center;
}

.feature-item {
    text-align: center;
    padding: 18px 16px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(8, 127, 138, 0.15);
    font-size: 12px;
    color: #087F8A;
    min-width: 110px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(8, 127, 138, 0.08);
}

.feature-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(8, 127, 138, 0.15);
    border-color: #087F8A;
}

.feature-item i {
    font-size: 26px;
    margin-bottom: 10px;
    display: block;
    color: #087F8A;
}

.feature-item span {
    font-weight: 600;
    display: block;
    line-height: 1.4;
}

/* RIGHT SECTION */
.right-section {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 50px;
    position: relative;
    z-index: 2;
}

/* LOGIN BOX */
.login-box {
    background: linear-gradient(145deg, #087F8A 0%, #076d76 100%);
    border-radius: 20px;
    padding: 40px 36px;
    width: 100%;
    max-width: 420px;
    box-shadow: 
        0 20px 50px rgba(8, 127, 138, 0.35),
        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
    position: relative;
    overflow: hidden;
}

.login-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
}

/* LOGIN HEADER */
.login-header {
    text-align: center;
    margin-bottom: 24px;
}

.login-icon {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.login-icon i {
    font-size: 20px;
    color: #fff;
}

.login-header h2 {
    font-size: clamp(20px, 3vw, 22px);
    font-weight: 700;
    color: #fff;
    margin-bottom: 6px;
}

.login-header p {
    color: rgba(255, 255, 255, 0.85);
    font-size: 13px;
}

/* ALERT */
.alert {
    display: none;
    align-items: center;
    gap: 10px;
    background: #fff1f1;
    color: #c53030;
    padding: 12px 14px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
    border: 1px solid rgba(197, 48, 48, 0.2);
}

.alert.show {
    display: flex;
}

.alert i {
    font-size: 16px;
}

.alert .btn-close {
    margin-left: auto;
    background: none;
    border: none;
    color: #c53030;
    font-size: 18px;
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.3s;
    padding: 0;
    line-height: 1;
}

.alert .btn-close:hover {
    opacity: 1;
}

/* FORM */
.form-group {
    position: relative;
    margin-bottom: 16px;
}

.form-control {
    width: 100%;
    padding: 12px 14px 12px 42px;
    border: 2px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    font-size: 14px;
    font-family: inherit;
    background: rgba(255, 255, 255, 0.95);
    color: #333;
    outline: none;
    transition: all 0.3s ease;
}

.form-control::placeholder {
    color: #94a3b8;
}

.form-control:focus {
    border-color: #065C63;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(6, 92, 99, 0.2);
}

/* HIDE BROWSER DEFAULT PASSWORD TOGGLE */
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
    display: none;
}

input::-ms-reveal,
input::-ms-clear {
    display: none !important;
}

/* For Edge & Chrome */
input[type="password"]::-webkit-credentials-auto-fill-button,
input[type="password"]::-webkit-clear-button {
    display: none !important;
}

/* ICONS */
.form-group i.input-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 16px;
    pointer-events: none;
    transition: color 0.3s;
}

.form-group:focus-within i.input-icon {
    color: #065C63;
}

.password-toggle {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #64748b;
    font-size: 16px;
    transition: color 0.3s;
    padding: 4px;
}

.password-toggle:hover {
    color: #087F8A;
}

/* BUTTON */
.btn-login {
    width: 100%;
    padding: 14px;
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    background: linear-gradient(135deg, #065C63 0%, #054851 100%);
    border: none;
    border-radius: 12px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 8px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(6, 92, 99, 0.4);
}

.btn-login::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.btn-login:hover::before {
    left: 100%;
}

.btn-login:hover {
    background: linear-gradient(135deg, #054851 0%, #043d44 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(6, 92, 99, 0.5);
}

.btn-login:active {
    transform: translateY(0);
}

.btn-login:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.btn-login i {
    margin-right: 8px;
}

/* FORGOT PASSWORD */
.forgot-link {
    text-align: center;
    margin-top: 18px;
}

.forgot-link a {
    color: rgba(255, 255, 255, 0.9);
    font-size: 12px;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.forgot-link a:hover {
    color: #fff;
}

.forgot-link a i {
    font-size: 12px;
}

/* FOOTER */
.login-footer {
    text-align: center;
    margin-top: 22px;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
}

.login-footer p {
    font-size: 10px;
    color: rgba(255, 255, 255, 0.6);
}

/* RESPONSIVE */
@media (max-width: 968px) {
    .login-container {
        flex-direction: column;
    }
    
    .left-section {
        padding: 36px 24px;
    }
    
    .logo-container {
        max-width: 280px;
        margin-top: 10px;
        margin-bottom: 24px;
    }
    
    .features {
        margin-top: 24px;
        gap: 10px;
    }
    
    .feature-item {
        padding: 14px 12px;
        min-width: 95px;
    }
    
    .right-section {
        padding: 24px;
        justify-content: center;
    }
    
    .login-box {
        padding: 32px 28px;
        max-width: 100%;
    }
}

@media (max-width: 640px) {
    .left-section {
        padding: 28px 20px;
    }
    
    .logo-container {
        max-width: 240px;
    }
    
    .brand-badge {
        padding: 6px 12px;
        font-size: 10px;
    }
    
    .features {
        gap: 8px;
    }
    
    .feature-item {
        font-size: 11px;
        padding: 12px 10px;
        min-width: 85px;
    }
    
    .feature-item i {
        font-size: 22px;
        margin-bottom: 8px;
    }
    
    .login-box {
        padding: 28px 22px;
        border-radius: 20px;
    }
    
    .login-icon {
        width: 50px;
        height: 50px;
    }
    
    .login-icon i {
        font-size: 20px;
    }
}

@media (max-width: 400px) {
    .brand-subtitle {
        font-size: 12px;
    }
    
    .features {
        flex-direction: column;
        width: 100%;
        max-width: 240px;
    }
    
    .feature-item {
        width: 100%;
        min-width: unset;
        flex-direction: row;
        display: flex;
        align-items: center;
        gap: 12px;
        text-align: left;
    }
    
    .feature-item i {
        margin-bottom: 0;
        font-size: 20px;
    }
}
</style>
</head>
<body>

<div class="login-container">
    <!-- LEFT SECTION -->
    <div class="left-section">
        <div class="logo-container">
            <img src="assets/img/logo_bandara.png" alt="Logo">
        </div>
        
        <div class="brand-badge">
            <i class="fas fa-circle"></i>
            Equipment & Technology Division
        </div>
        
        <h1 class="brand-title">Equipment Monitoring System</h1>
        

        <div class="features">
            <div class="feature-item">
                <i class="fas fa-clipboard-check"></i>
                <span>Digital<br>Checklist</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-chart-line"></i>
                <span>Real-time<br>Monitoring</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-file-alt"></i>
                <span>Auto<br>Report</span>
            </div>
        </div>
    </div>

    <!-- RIGHT SECTION -->
    <div class="right-section">
        <div class="login-box">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h2>Selamat Datang!</h2>
                <p>Silakan login untuk mengakses sistem</p>
            </div>

            <!-- ALERT ERROR -->
            <div class="alert <?= !empty($error_message) ? 'show' : '' ?>" id="alertError">
                <i class="fas fa-exclamation-circle"></i>
                <span id="alertMessage"><?= !empty($error_message) ? htmlspecialchars($error_message) : 'Username atau password salah!' ?></span>
                <button type="button" class="btn-close" onclick="hideAlert()">&times;</button>
            </div>

            <form id="loginForm" method="POST" action="">
                <div class="form-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>

                <div class="form-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <i class="fas fa-eye-slash password-toggle" id="togglePassword"></i>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </button>
            </form>

            <div class="forgot-link">
                <a href="forgotPassword.php">
                    <i class="fas fa-key"></i> Lupa Password?
                </a>
            </div>

            <div class="login-footer">
                <p>&copy; 2024 Equipment & Technology Division</p>
            </div>
        </div>
    </div>
</div>

<script>
// PASSWORD TOGGLE
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', function() {
    if (passwordInput.getAttribute('type') === 'password') {
        passwordInput.setAttribute('type', 'text');
        this.classList.remove('fa-eye-slash');
        this.classList.add('fa-eye');
    } else {
        passwordInput.setAttribute('type', 'password');
        this.classList.remove('fa-eye');
        this.classList.add('fa-eye-slash');
    }
});

// ALERT FUNCTIONS
function hideAlert() {
    const alert = document.getElementById('alertError');
    alert.classList.remove('show');
}
</script>

</body>
</html>
