<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('agent');

$agent_id = $_SESSION['user_id'];

// --- 1. Fetch Overall KPIs (Total Listings, Views, Inquiries) ---
$kpi_query = "
    SELECT 
        COUNT(p.property_id) AS total_listings,
        COALESCE(SUM(p.views_count), 0) AS total_views,
        (SELECT COALESCE(COUNT(i.inquiry_id), 0) FROM inquiries i JOIN properties p2 ON i.property_id = p2.property_id WHERE p2.agent_id = ?) AS total_inquiries
    FROM properties p
    WHERE p.agent_id = ?
";
$kpi_stmt = $conn->prepare($kpi_query);
$kpi_stmt->bind_param("ii", $agent_id, $agent_id);
$kpi_stmt->execute();
$kpi_result = $kpi_stmt->get_result();
$kpis = $kpi_result->fetch_assoc();

// --- 2. Fetch Listing Status Breakdown ---
$status_query = "
    SELECT status, COUNT(*) as count 
    FROM properties 
    WHERE agent_id = ? 
    GROUP BY status
";
$status_stmt = $conn->prepare($status_query);
$status_stmt->bind_param("i", $agent_id);
$status_stmt->execute();
$status_breakdown = $status_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- 3. Top 3 Most Viewed Properties ---
$top_views_query = "
    SELECT property_id, title, views_count 
    FROM properties 
    WHERE agent_id = ? 
    ORDER BY views_count DESC 
    LIMIT 3
";
$top_views_stmt = $conn->prepare($top_views_query);
$top_views_stmt->bind_param("i", $agent_id);
$top_views_stmt->execute();
$top_views = $top_views_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- 4. Top 3 Properties by Inquiries ---
$top_inquiries_query = "
    SELECT 
        p.property_id, 
        p.title, 
        COUNT(i.inquiry_id) as inquiry_count
    FROM properties p
    LEFT JOIN inquiries i ON p.property_id = i.property_id
    WHERE p.agent_id = ?
    GROUP BY p.property_id, p.title
    HAVING COUNT(i.inquiry_id) > 0 /* Only show properties with inquiries */
    ORDER BY inquiry_count DESC
    LIMIT 3
";
$top_inquiries_stmt = $conn->prepare($top_inquiries_query);
$top_inquiries_stmt->bind_param("i", $agent_id);
$top_inquiries_stmt->execute();
$top_inquiries = $top_inquiries_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Function to get icon based on status (requires global color utility classes from style.css)
function getStatusIcon($status) {
    // Note: The text classes need to be defined in style.css (e.g., .text-success)
    switch ($status) {
        case 'approved': return '<i class="fas fa-toggle-on text-success"></i>'; // 'approved' is the 'active' status equivalent for live properties
        case 'pending': return '<i class="fas fa-clock text-warning"></i>';
        case 'sold':
        case 'rented': return '<i class="fas fa-check-double text-info"></i>';
        case 'rejected': return '<i class="fas fa-ban text-danger"></i>';
        default: return '<i class="fas fa-question-circle text-secondary"></i>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Rentflow360</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>Performance Analytics</h1>
                <p class="text-secondary">Data reflects the performance of your listings across Rentflow360.</p>
            </div>

            <!-- KPI Grid -->
            <div class="analytics-grid">
                <div class="stat-card primary">
                    <i class="fas fa-chart-line"></i>
                    <div class="stat-info">
                        <span class="stat-label">Total Views</span>
                        <span class="stat-value"><?php echo number_format($kpis['total_views']); ?></span>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <i class="fas fa-envelope-open-text"></i>
                    <div class="stat-info">
                        <span class="stat-label">Total Inquiries</span>
                        <span class="stat-value"><?php echo number_format($kpis['total_inquiries']); ?></span>
                    </div>
                </div>

                <div class="stat-card info">
                    <i class="fas fa-list-alt"></i>
                    <div class="stat-info">
                        <span class="stat-label">Approved Listings</span>
                        <?php 
                        $approved_count = 0;
                        foreach($status_breakdown as $item) {
                            if ($item['status'] === 'approved') {
                                $approved_count = $item['count'];
                                break;
                            }
                        }
                        ?>
                        <span class="stat-value"><?php echo number_format($approved_count); ?></span>
                    </div>
                </div>

                <div class="stat-card warning">
                    <i class="fas fa-building"></i>
                    <div class="stat-info">
                        <span class="stat-label">Total Listings</span>
                        <span class="stat-value"><?php echo number_format($kpis['total_listings']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Performance Tables -->
            <div class="performance-section-grid">
                <!-- Status Breakdown -->
                <div class="analytics-panel">
                    <h3>Listing Status Breakdown</h3>
                    <?php if (count($status_breakdown) > 0): ?>
                    <table class="performance-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($status_breakdown as $item): ?>
                            <tr>
                                <td><?php echo getStatusIcon($item['status']); ?> <?php echo ucfirst($item['status']); ?></td>
                                <td><span class="data-badge"><?php echo number_format($item['count']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div class="empty-analytics">No data available. Add properties to see analytics.</div>
                    <?php endif; ?>
                </div>

                <!-- Top Viewed Properties -->
                <div class="analytics-panel">
                    <h3>Top 3 Most Viewed Listings</h3>
                    <?php if (count($top_views) > 0): ?>
                    <table class="performance-table">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_views as $property): ?>
                            <tr>
                                <td><a href="../property-details.php?id=<?php echo $property['property_id']; ?>"><?php echo htmlspecialchars($property['title']); ?></a></td>
                                <td><span class="data-badge views"><?php echo number_format($property['views_count']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div class="empty-analytics">Views data is not yet available.</div>
                    <?php endif; ?>
                </div>

                <!-- Top Properties by Inquiries -->
                <div class="analytics-panel">
                    <h3>Top 3 Listings by Inquiries</h3>
                    <?php if (count($top_inquiries) > 0): ?>
                    <table class="performance-table">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Inquiries</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_inquiries as $property): ?>
                            <tr>
                                <td><a href="../property-details.php?id=<?php echo $property['property_id']; ?>"><?php echo htmlspecialchars($property['title']); ?></a></td>
                                <td><span class="data-badge inquiries"><?php echo number_format($property['inquiry_count']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div class="empty-analytics">No inquiries received yet.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
