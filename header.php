<?php
if(session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";

// Ensure user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Fetch all user data by using '*'
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION["id"]);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get the current page filename
$currentPage = basename($_SERVER['PHP_SELF']);

// Define base navigation items
$nav_items = [
    ['href' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard'],
    ['href' => 'fund-wallet.php', 'icon' => 'fas fa-wallet', 'title' => 'Fund Wallet'],
    ['href' => 'profile.php', 'icon' => 'fas fa-user-circle', 'title' => 'My Profile'],
];

// Add the VTU links for all users
$nav_items[] = ['href' => 'airtime.php', 'icon' => 'fas fa-mobile-alt', 'title' => 'Buy Airtime'];
$nav_items[] = ['href' => 'data.php', 'icon' => 'fas fa-wifi', 'title' => 'Buy Data'];


// Add the rest of the service links
$other_services = [
    ['href' => 'bvn-mod.php', 'icon' => 'fas fa-user-edit', 'title' => 'BVN Modification'],
    ['href' => 'bvn-retrieval.php', 'icon' => 'fas fa-search', 'title' => 'BVN Retrieval'],
    ['href' => 'nin-tracking.php', 'icon' => 'fas fa-fingerprint', 'title' => 'NIN Retrieval'],
    ['href' => 'cac-reg.php', 'icon' => 'fas fa-landmark', 'title' => 'CAC Registration'],
    ['href' => 'jtb-tin.php', 'icon' => 'fas fa-file-invoice-dollar', 'title' => 'JTB-TIN'],
    ['href' => 'result-check.php', 'icon' => 'fas fa-graduation-cap', 'title' => 'Result Checker'],
    ['href' => 'jamb-services.php', 'icon' => 'fas fa-university', 'title' => 'JAMB Services'],
];

$nav_items = array_merge($nav_items, $other_services);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Trust Identity'; ?></title>
    <link rel="stylesheet" href="css/main.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3 class="sidebar-logo">Mkolo Digital</h3>
            <button class="sidebar-toggle-btn" id="sidebar-toggle-btn"><i class="fas fa-angle-left"></i></button>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <?php foreach ($nav_items as $item): ?>
                <li>
                    <a href="<?php echo $item['href']; ?>" class="<?php echo ($currentPage == $item['href']) ? 'active' : ''; ?>">
                        <i class="<?php echo $item['icon']; ?> fa-fw"></i> 
                        <span class="nav-item-text"><?php echo $item['title']; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="sidebar-link">
                <i class="fas fa-sign-out-alt fa-fw"></i>
                <span class="nav-item-text">Logout</span>
            </a>
        </div>
    </aside>
    <div class="main-wrapper" id="main-wrapper">
        <header class="top-header">
            <button class="mobile-menu-btn" id="mobile-menu-btn"><i class="fas fa-bars"></i></button>
            <div class="user-menu" id="user-menu">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=eef2ff&color=4f46e5&bold=true" alt="User Avatar" class="user-avatar">
                <div class="user-menu-dropdown" id="user-menu-dropdown">
                    <div class="dropdown-header">
                        <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <a href="profile.php" class="dropdown-link"><i class="fas fa-user-circle fa-fw"></i> My Profile</a>
                    <a href="logout.php" class="dropdown-link"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a>
                </div>
            </div>
        </header>
        <main class="main-content">
<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const sidebar = document.getElementById('sidebar');
    const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const userMenu = document.getElementById('user-menu');
    const userMenuDropdown = document.getElementById('user-menu-dropdown');
    if (userMenu) {
        userMenu.addEventListener('click', (event) => {
            event.stopPropagation();
            userMenuDropdown.classList.toggle('active');
        });
    }
    window.addEventListener('click', () => {
        if (userMenuDropdown && userMenuDropdown.classList.contains('active')) {
            userMenuDropdown.classList.remove('active');
        }
    });
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', () => { body.classList.toggle('sidebar-collapsed'); });
    }
    if (mobileMenuBtn && sidebar && sidebarOverlay) {
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.add('sidebar--open');
            sidebarOverlay.classList.add('sidebar-overlay--visible');
        });
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('sidebar--open');
            sidebarOverlay.classList.remove('sidebar-overlay--visible');
        });
    }
});
</script>
