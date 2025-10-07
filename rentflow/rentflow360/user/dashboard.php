<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get statistics
$total_favorites = $conn->query("SELECT COUNT(*) as count FROM favorites WHERE user_id = $user_id")->fetch_assoc()['count'];
$total_alerts = $conn->query("SELECT COUNT(*) as count FROM alerts WHERE user_id = $user_id AND is_active = 1")->fetch_assoc()['count'];
$total_inquiries = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE user_id = $user_id")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Rentflow360</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>My Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-heart"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $total_favorites; ?></h3>
                        <p>Saved Properties</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-bell"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $total_alerts; ?></h3>
                        <p>Active Alerts</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $total_inquiries; ?></h3>
                        <p>My Inquiries</p>
                    </div>
                </div>
            </div>

            <!-- Recent Favorites -->
            <div class="dashboard-section">
                <h2>Recently Saved Properties</h2>
                <div class="property-grid">
                    <?php
                    $query = "SELECT p.*, pp.photo_url, f.saved_at 
                             FROM favorites f
                             JOIN properties p ON f.property_id = p.property_id
                             LEFT JOIN property_photos pp ON p.property_id = pp.property_id AND pp.is_primary = 1
                             WHERE f.user_id = $user_id
                             ORDER BY f.saved_at DESC LIMIT 6";
                    $result = $conn->query($query);
                    
                    if ($result->num_rows > 0):
                        while ($property = $result->fetch_assoc()):
                    ?>
                    <div class="property-card">
                        <img src="../assets/images/uploads/<?php echo $property['photo_url'] ?? 'placeholder.jpg'; ?>" alt="Property">
                        <div class="property-info">
                            <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                            <p class="location"><?php echo htmlspecialchars($property['location']); ?></p>
                            <p class="price"><?php echo formatPrice($property['price']); ?></p>
                            <a href="../property-details.php?id=<?php echo $property['property_id']; ?>" class="btn">View Details</a>
                        </div>
                    </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <p>You haven't saved any properties yet. <a href="../properties.php">Browse properties</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
