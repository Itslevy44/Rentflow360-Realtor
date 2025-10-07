<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('agent');

$agent_id = $_SESSION['user_id'];

// Get statistics
$total_properties = $conn->query("SELECT COUNT(*) as count FROM properties WHERE agent_id = $agent_id")->fetch_assoc()['count'];
$active_properties = $conn->query("SELECT COUNT(*) as count FROM properties WHERE agent_id = $agent_id AND status = 'approved'")->fetch_assoc()['count'];
$pending_properties = $conn->query("SELECT COUNT(*) as count FROM properties WHERE agent_id = $agent_id AND status = 'pending'")->fetch_assoc()['count'];
$total_views = $conn->query("SELECT SUM(views_count) as total FROM properties WHERE agent_id = $agent_id")->fetch_assoc()['total'] ?? 0;
$new_inquiries = $conn->query("SELECT COUNT(*) as count FROM inquiries i JOIN properties p ON i.property_id = p.property_id WHERE p.agent_id = $agent_id AND i.status = 'new'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - Rentflow360</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>Agent Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $total_properties; ?></h3>
                        <p>Total Properties</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $active_properties; ?></h3>
                        <p>Active Listings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $pending_properties; ?></h3>
                        <p>Pending Approval</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-eye"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $total_views; ?></h3>
                        <p>Total Views</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $new_inquiries; ?></h3>
                        <p>New Inquiries</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="add-property.php" class="action-btn">
                    <i class="fas fa-plus"></i> Add New Property
                </a>
                <a href="manage-properties.php" class="action-btn">
                    <i class="fas fa-list"></i> Manage Properties
                </a>
                <a href="inquiries.php" class="action-btn">
                    <i class="fas fa-inbox"></i> View Inquiries
                </a>
            </div>

            <!-- Recent Properties -->
            <div class="dashboard-section">
                <h2>Your Recent Properties</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Location</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM properties WHERE agent_id = $agent_id ORDER BY created_at DESC LIMIT 10";
                        $result = $conn->query($query);
                        
                        while ($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['location']); ?></td>
                            <td><?php echo formatPrice($row['price']); ?></td>
                            <td><span class="badge <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            <td><?php echo $row['views_count']; ?></td>
                            <td>
                                <a href="manage-properties.php?edit=<?php echo $row['property_id']; ?>" class="btn-small">Edit</a>
                                <a href="../property-details.php?id=<?php echo $row['property_id']; ?>" class="btn-small" target="_blank">View</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
