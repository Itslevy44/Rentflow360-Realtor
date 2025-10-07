<?php
// Function check is temporary, will be replaced when includes/functions.php is properly included.
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() { return isset($_SESSION['user_id']); }
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Assume $page_title is set in the calling script (e.g., index.php)
$page_title = $page_title ?? 'Rentflow360 - Find Your Perfect Property in Kenya';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Font Awesome for icons, including the menu icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <a href="index.php">Rentflow360 Realty</a>
                </div>
                <!-- Menu Toggle Button for Mobile -->
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <ul class="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="properties.php">Properties</a></li>
                    <li><a href="search.php">Search</a></li>
                    <?php if (isLoggedIn()): ?>
                        <?php
                            $role = $_SESSION['role'] ?? 'user';
                            $dashboard_link = strtolower($role) . '/dashboard.php';
                        ?>
                        <li><a href="<?php echo $dashboard_link; ?>">Dashboard</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container">
    <!-- Content from the calling page (e.g., index.php) starts here -->
