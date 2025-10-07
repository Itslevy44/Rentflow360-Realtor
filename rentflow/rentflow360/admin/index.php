<?php
// PHP Script for Admin Main Dashboard (admin/index.php)

session_start();

// NOTE: The path is '../' because this file is inside the 'admin/' subdirectory.
require_once '../includes/db_connection.php';

// --- Placeholder Auth and Session Data (Replace with real logic) ---
// Since we can't include functions.php, we simulate the authentication logic.
function requireAdminAuth() {
    // Implement real authentication check here.
    // if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    //     header('Location: ../login.php');
    //     exit;
    // }
    // Placeholder data for rendering the page
    if (!isset($_SESSION['full_name'])) {
        $_SESSION['full_name'] = 'System Admin'; 
    }
}
requireAdminAuth();


// --- Helper Functions ---

/**
 * Formats the price for display (Ksh).
 */
function formatPrice($price) {
    return 'Ksh ' . number_format($price, 0);
}

/**
 * Generates an HTML badge for listing status using Tailwind classes.
 */
function getStatusBadge($status) {
    $classes = [
        'approved' => 'bg-green-100 text-green-800 border-green-300',
        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'rejected' => 'bg-red-100 text-red-800 border-red-300',
    ];
    $class = $classes[$status] ?? 'bg-gray-100 text-gray-800 border-gray-300';
    return "<span class='inline-flex items-center px-3 py-1 text-xs font-medium rounded-full border {$class} capitalize'>{$status}</span>";
}


// --- Get Statistics ---
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")->fetch_assoc()['count'] ?? 0;
$total_properties = $conn->query("SELECT COUNT(*) as count FROM properties")->fetch_assoc()['count'] ?? 0;
$pending_properties = $conn->query("SELECT COUNT(*) as count FROM properties WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
$total_inquiries = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'")->fetch_assoc()['count'] ?? 0;
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;

// --- Get Recent Properties ---
$query = "SELECT p.*, u.full_name as agent_name 
         FROM properties p 
         JOIN users u ON p.agent_id = u.user_id 
         ORDER BY p.created_at DESC LIMIT 10";
$result = $conn->query($query);
$recent_properties = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Rentflow360</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome CDN for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#007bff', // Blue for primary actions
                        'success': '#28a745', // Green for positive status
                        'danger': '#dc3545', // Red for warnings/rejections
                        'warning': '#ffc107', // Yellow for pending/alerts
                        'info': '#17a2b8', // Cyan for informational cards
                    },
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 flex h-screen overflow-hidden">

    <!-- Admin Sidebar -->
    <aside class="w-64 bg-gray-800 text-white flex-shrink-0 flex flex-col hidden md:flex">
        <div class="p-6 text-2xl font-bold text-white border-b border-gray-700">
            Rentflow360 <span class="text-xs text-primary block mt-1 font-normal">Admin Panel</span>
        </div>
        <nav class="flex-grow p-4">
            <ul class="space-y-2">
                <li>
                    <a href="index.php" class="flex items-center p-3 rounded-lg bg-gray-700 text-white font-semibold">
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
                    <a href="analytics.php" class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-gray-700">
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
            <h1 class="text-3xl font-extrabold text-gray-900">Admin Dashboard</h1>
            <p class="text-gray-500">Welcome back, <span class="font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>. Here's a quick overview of the platform.</p>
        </header>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            
            <!-- Total Users -->
            <div class="bg-white p-5 rounded-xl shadow-xl hover:shadow-2xl transition duration-300 border-l-4 border-success">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500">Total Users</p>
                    <i class="fas fa-users text-2xl text-success/70"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($total_users); ?></h3>
            </div>

            <!-- Total Properties -->
            <div class="bg-white p-5 rounded-xl shadow-xl hover:shadow-2xl transition duration-300 border-l-4 border-primary">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500">Total Listings</p>
                    <i class="fas fa-building text-2xl text-primary/70"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($total_properties); ?></h3>
            </div>

            <!-- Pending Approval -->
            <a href="manage-listings.php?status=pending" class="block bg-white p-5 rounded-xl shadow-xl hover:shadow-2xl transition duration-300 border-l-4 border-warning cursor-pointer">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500">Pending Approval</p>
                    <i class="fas fa-clock text-2xl text-warning/70"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($pending_properties); ?></h3>
            </a>

            <!-- New Inquiries -->
            <div class="bg-white p-5 rounded-xl shadow-xl hover:shadow-2xl transition duration-300 border-l-4 border-info">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500">New Inquiries</p>
                    <i class="fas fa-envelope text-2xl text-info/70"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($total_inquiries); ?></h3>
            </div>
            
            <!-- Pending Reports -->
            <a href="manage-reports.php?tab=reports" class="block bg-white p-5 rounded-xl shadow-xl hover:shadow-2xl transition duration-300 border-l-4 border-danger cursor-pointer">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500">Pending Reports</p>
                    <i class="fas fa-flag text-2xl text-danger/70"></i>
                </div>
                <h3 class="text-3xl font-bold text-danger mt-1"><?php echo number_format($pending_reports); ?></h3>
            </a>

        </div>

        <!-- Recent Properties -->
        <div class="dashboard-section bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center justify-between">
                <span>Recent Properties</span>
                <a href="manage-listings.php" class="text-sm font-medium text-primary hover:text-blue-700">View All <i class="fas fa-arrow-right ml-1 text-xs"></i></a>
            </h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recent_properties)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">No recent properties found.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($recent_properties as $row): ?>
                        <tr class="hover:bg-gray-50 transition duration-100">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $row['property_id']; ?></td>
                            <td class="px-6 py-4 max-w-xs xl:max-w-md">
                                <span class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($row['title']); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?php echo htmlspecialchars($row['agent_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800">
                                <?php echo formatPrice($row['price']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php echo getStatusBadge($row['status']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="manage-listings.php?view=<?php echo $row['property_id']; ?>" class="text-primary hover:text-blue-700 transition duration-150 p-2 rounded-full hover:bg-primary/10" title="View Details">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>
</body>
</html>
