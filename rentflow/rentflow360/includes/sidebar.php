<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'user';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h3><?php echo ucfirst($role); ?> Panel</h3>
    </div>
    <ul class="sidebar-menu">
        <?php if ($role === 'admin'): ?>
            <li class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
            </li>
            <li class="<?php echo $currentPage === 'manage-users.php' ? 'active' : ''; ?>">
                <a href="manage-users.php"><i class="fas fa-users"></i> Manage Users</a>
            </li>
            <li class="<?php echo $currentPage === 'manage-listings.php' ? 'active' : ''; ?>">
                <a href="manage-listings.php"><i class="fas fa-building"></i> Manage Properties</a>
            </li>
            <li class="<?php echo $currentPage === 'manage-reports.php' ? 'active' : ''; ?>">
                <a href="manage-reports.php"><i class="fas fa-flag"></i> Reports</a>
            </li>
            <li class="<?php echo $currentPage === 'analytics.php' ? 'active' : ''; ?>">
                <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a>
            </li>
        <?php elseif ($role === 'agent'): ?>
            <li class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            </li>
            <li class="<?php echo $currentPage === 'add-property.php' ? 'active' : ''; ?>">
                <a href="add-property.php"><i class="fas fa-plus"></i> Add Property</a>
            </li>
            <li class="<?php echo $currentPage === 'manage-properties.php' ? 'active' : ''; ?>">
                <a href="manage-properties.php"><i class="fas fa-building"></i> My Properties</a>
            </li>
            <li class="<?php echo $currentPage === 'inquiries.php' ? 'active' : ''; ?>">
                <a href="inquiries.php"><i class="fas fa-envelope"></i> Inquiries</a>
            </li>
            <li class="<?php echo $currentPage === 'analytics.php' ? 'active' : ''; ?>">
                <a href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a>
            </li>
        <?php else: ?>
            <li class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            </li>
            <li class="<?php echo $currentPage === 'favorites.php' ? 'active' : ''; ?>">
                <a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a>
            </li>
            <li class="<?php echo $currentPage === 'alerts.php' ? 'active' : ''; ?>">
                <a href="alerts.php"><i class="fas fa-bell"></i> Alerts</a>
            </li>
            <li class="<?php echo $currentPage === 'messages.php' ? 'active' : ''; ?>">
                <a href="messages.php"><i class="fas fa-comments"></i> Messages</a>
            </li>
            <li class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            </li>
        <?php endif; ?>
        <li>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </li>
    </ul>
</aside>
