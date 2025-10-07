<?php
// PHP Script for Admin Analytics Dashboard (admin/analytics.php)

session_start();

// NOTE: The path is '../' because this file is inside the 'admin/' subdirectory.
require_once '../includes/db_connection.php'; 

// --- Authentication and Authorization Check ---
// IMPORTANT: Replace this placeholder with actual session/auth logic!
$admin_id = 1; 
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     header('Location: ../login.php');
//     exit;
// }
// $admin_id = $_SESSION['user_id']; 


// --- 1. Fetch Key Metrics (Counts) ---
$metrics = [];
try {
    // Total Users (assuming roles are stored in the 'users' table)
    $metrics_result = $conn->query("SELECT COUNT(*) as total FROM users");
    $metrics['total_users'] = $metrics_result->fetch_assoc()['total'] ?? 0;

    $metrics_result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'agent'");
    $metrics['total_agents'] = $metrics_result->fetch_assoc()['total'] ?? 0;

    // Listing Statuses
    $metrics_result = $conn->query("SELECT status, COUNT(*) as count FROM properties GROUP BY status");
    $listing_counts = [];
    while ($row = $metrics_result->fetch_assoc()) {
        $listing_counts[$row['status']] = $row['count'];
    }
    $metrics['approved_listings'] = $listing_counts['approved'] ?? 0;
    $metrics['pending_listings'] = $listing_counts['pending'] ?? 0;
    $metrics['rejected_listings'] = $listing_counts['rejected'] ?? 0;
    $metrics['total_listings'] = array_sum($listing_counts);

    // Pending Reports
    $metrics_result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE status = 'pending'");
    $metrics['pending_reports'] = $metrics_result->fetch_assoc()['total'] ?? 0;

    // --- 2. Mock Listing Performance (Views & Inquiries) ---
    // In a real application, these would come from dedicated tables (e.g., listing_views, inquiries)
    // Here we simulate fetching the top 5 viewed listings for demonstration
    $top_listings_sql = "
        SELECT 
            p.property_id, 
            p.title, 
            p.location, 
            p.price,
            p.status,
            u.full_name AS agent_name,
            FLOOR(RAND() * 5000 + 1000) AS total_views, 
            FLOOR(RAND() * 50 + 5) AS total_inquiries 
        FROM properties p
        JOIN users u ON p.agent_id = u.user_id
        WHERE p.status = 'approved'
        ORDER BY total_views DESC
        LIMIT 5";

    $top_listings_result = $conn->query($top_listings_sql);
    $top_listings = $top_listings_result ? $top_listings_result->fetch_all(MYSQLI_ASSOC) : [];
    
} catch (Exception $e) {
    // Handle database errors gracefully
    error_log("Analytics DB Error: " . $e->getMessage());
    $metrics = ['total_users' => 0, 'total_agents' => 0, 'total_listings' => 0, 'approved_listings' => 0, 'pending_reports' => 0];
    $top_listings = [];
}
$conn->close();

/**
 * Formats the price for display (Ksh).
 */
function format_price($price) {
    return 'Ksh ' . number_format($price, 0);
}

// --- Mock Data for Charts (Time Series Data Simulation) ---
$weekly_data = [
    'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    'views' => [120, 150, 90, 210, 180, 300, 250],
    'new_users' => [15, 22, 18, 30, 25, 40, 35]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Analytics | Rentflow360</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome CDN for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        /* Placeholder styling for charts */
        .chart-placeholder {
            height: 300px;
            background: repeating-linear-gradient(
                -45deg,
                #f3f4f6,
                #f3f4f6 10px,
                #ffffff 10px,
                #ffffff 20px
            );
            border: 1px dashed #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-weight: 600;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#007bff',
                        'success': '#28a745',
                        'danger': '#dc3545',
                        'warning': '#ffc107',
                        'info': '#17a2b8',
                    },
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 flex h-screen overflow-hidden">

    <!-- Admin Sidebar -->
    <aside class="w-64 bg-gray-800 text-white flex-shrink-0 flex flex-col hidden md:flex">
        <div class="p-6 text-2xl font-bold text-primary border-b border-gray-700">
            Rentflow360
        </div>
        <nav class="flex-grow p-4">
            <ul class="space-y-2">
                <li>
                    <a href="index.php" class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-gray-700">
                        <i class="fas fa-tachometer-alt w-5 h-5 mr-3"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="manage-listings.php" class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-gray-700">
                        <i class="fas fa-list-alt w-5 h-5 mr-3"></i> Manage Listings
                    </a>
                </li>
                <li>
                    <a href="manage-users.php" class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-gray-700">
                        <i class="fas fa-users w-5 h-5 mr-3"></i> Manage Users
                    </a>
                </li>
                <li>
                    <a href="manage-reports.php" class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-gray-700">
                        <i class="fas fa-flag w-5 h-5 mr-3"></i> Reports & Reviews
                    </a>
                </li>
                <li>
                    <a href="analytics.php" class="flex items-center p-3 rounded-lg bg-gray-700 text-white font-semibold">
                        <i class="fas fa-chart-line w-5 h-5 mr-3"></i> Analytics
                    </a>
                </li>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-700">
            <a href="../logout.php" class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-gray-700">
                <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-grow overflow-auto p-4 md:p-8">
        <header class="mb-6">
            <h2 class="text-3xl font-bold text-gray-800">Platform Analytics Overview</h2>
            <p class="text-gray-500">Key metrics, performance indicators, and trends for Rentflow360.</p>
        </header>

        <!-- --- 1. Key Metrics Cards --- -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Total Listings -->
            <div class="bg-white p-6 rounded-xl shadow-lg border-b-4 border-primary">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500">Total Listings</p>
                    <i class="fas fa-home text-2xl text-primary/70"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($metrics['total_listings']); ?></p>
                <p class="text-xs text-gray-500 mt-2">
                    <?php echo number_format($metrics['approved_listings']); ?> approved, <?php echo number_format($metrics['pending_listings']); ?> pending
                </p>
            </div>

            <!-- Total Users -->
            <div class="bg-white p-6 rounded-xl shadow-lg border-b-4 border-success">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500">Total Users</p>
                    <i class="fas fa-users text-2xl text-success/70"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($metrics['total_users']); ?></p>
                <p class="text-xs text-gray-500 mt-2">
                    Including <?php echo number_format($metrics['total_agents']); ?> registered agents
                </p>
            </div>

            <!-- Total Monthly Views (Mocked) -->
            <div class="bg-white p-6 rounded-xl shadow-lg border-b-4 border-warning">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500">Monthly Page Views</p>
                    <i class="fas fa-eye text-2xl text-warning/70"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format(array_sum($weekly_data['views']) * 4); ?></p>
                <p class="text-xs text-gray-500 mt-2">
                    Based on weekly average data
                </p>
            </div>

            <!-- Pending Reports -->
            <div class="bg-white p-6 rounded-xl shadow-lg border-b-4 border-danger">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500">Urgent: Pending Reports</p>
                    <i class="fas fa-flag text-2xl text-danger/70"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900 mt-1 text-danger"><?php echo number_format($metrics['pending_reports']); ?></p>
                <p class="text-xs text-gray-500 mt-2">
                    Needs immediate attention in Reports section
                </p>
            </div>

        </section>

        <!-- --- 2. Charts and Trends --- -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Platform Traffic Trend -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-chart-area mr-2 text-primary"></i> Weekly Platform Traffic
                </h3>
                <div class="chart-placeholder rounded-lg">
                    [Placeholder for Chart.js - Total Views Trend]
                </div>
                <div class="mt-4 text-sm text-gray-600">
                    <p>Sample Data (Views): <?php echo implode(', ', $weekly_data['views']); ?></p>
                    <p>Sample Data (New Users): <?php echo implode(', ', $weekly_data['new_users']); ?></p>
                </div>
            </div>

            <!-- Listing Status Distribution -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-pie-chart mr-2 text-warning"></i> Listing Status Distribution
                </h3>
                <div class="chart-placeholder rounded-lg">
                    [Placeholder for Chart.js - Listing Status Pie Chart]
                </div>
                <div class="mt-4 text-sm text-gray-600 space-y-1">
                    <p class="font-medium text-primary">Approved: <?php echo number_format($metrics['approved_listings']); ?></p>
                    <p class="font-medium text-warning">Pending: <?php echo number_format($metrics['pending_listings']); ?></p>
                    <p class="font-medium text-danger">Rejected: <?php echo number_format($metrics['rejected_listings']); ?></p>
                </div>
            </div>
            
        </section>


        <!-- --- 3. Top Performing Listings --- -->
        <section class="mb-8">
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Top 5 Performing Listings (Approved)</h3>
            <p class="text-gray-500 mb-4">Highest viewed listings, indicating market interest.</p>
            
            <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Listing</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total Views</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Inquiries</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($top_listings)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No approved listings found to calculate performance metrics.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($top_listings as $listing): ?>
                        <tr class="hover:bg-gray-50 transition duration-100">
                            <td class="px-6 py-4 max-w-xs xl:max-w-md">
                                <a href="../listing-details.php?id=<?php echo $listing['property_id']; ?>" target="_blank" class="text-base font-semibold text-primary hover:underline" title="<?php echo htmlspecialchars($listing['title']); ?>">
                                    <?php echo htmlspecialchars(substr($listing['title'], 0, 40)) . (strlen($listing['title']) > 40 ? '...' : ''); ?>
                                </a>
                                <div class="text-xs text-gray-500 flex items-center mt-1">
                                    <i class="fas fa-map-marker-alt text-xs mr-1"></i> <?php echo htmlspecialchars($listing['location']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?php echo htmlspecialchars($listing['agent_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                <?php echo format_price($listing['price']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold text-success">
                                <?php echo number_format($listing['total_views']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold text-primary">
                                <?php echo number_format($listing['total_inquiries']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</body>
</html>
