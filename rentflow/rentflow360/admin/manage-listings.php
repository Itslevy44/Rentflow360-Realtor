<?php
// PHP Script for Admin Listing Management (admin/manage-listings.php)

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


// --- Helper Functions for UI ---

/**
 * Generates an HTML badge for listing status.
 */
function get_listing_status_badge($status) {
    $classes = [
        'approved' => 'bg-green-100 text-green-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
        'rejected' => 'bg-red-100 text-red-800',
        'sold' => 'bg-gray-100 text-gray-800',
        'rented' => 'bg-gray-100 text-gray-800',
    ];
    $class = $classes[$status] ?? 'bg-blue-100 text-blue-800';
    return "<span class='inline-flex items-center px-3 py-1 text-xs font-medium rounded-full {$class} capitalize'>{$status}</span>";
}

/**
 * Formats the price for display (Ksh).
 */
function format_price($price) {
    return 'Ksh ' . number_format($price, 0);
}

$message = null;
$message_type = null;

// --- Handle Moderation Actions (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $property_id = (int)$_POST['property_id'];

    try {
        if ($action === 'approve_listing') {
            $stmt = $conn->prepare("UPDATE properties SET status = 'approved' WHERE property_id = ?");
            $stmt->bind_param("i", $property_id);
            $stmt->execute();
            $message = "Listing ID {$property_id} approved successfully.";
            $message_type = 'success';
            $stmt->close();

        } elseif ($action === 'reject_listing') {
            $stmt = $conn->prepare("UPDATE properties SET status = 'rejected' WHERE property_id = ?");
            $stmt->bind_param("i", $property_id);
            $stmt->execute();
            $message = "Listing ID {$property_id} rejected successfully.";
            $message_type = 'warning'; // Use warning color for rejection

        } elseif ($action === 'toggle_feature') {
            // Get current status
            $current_stmt = $conn->prepare("SELECT is_featured FROM properties WHERE property_id = ?");
            $current_stmt->bind_param("i", $property_id);
            $current_stmt->execute();
            $result = $current_stmt->get_result();
            $current_featured = $result->fetch_assoc()['is_featured'];
            $new_featured = $current_featured ? 0 : 1; // Toggle 0/1
            $current_stmt->close();

            // Update status
            $stmt = $conn->prepare("UPDATE properties SET is_featured = ? WHERE property_id = ?");
            $stmt->bind_param("ii", $new_featured, $property_id);
            $stmt->execute();
            $message = "Listing ID {$property_id} featured status " . ($new_featured ? 'enabled' : 'disabled') . ".";
            $message_type = 'info';
            $stmt->close();
        }
    } catch (Exception $e) {
        $message = "Database Error: " . $e->getMessage();
        $message_type = 'error';
    }

    // Redirect to clear POST data and prevent resubmission
    if (isset($message)) {
        header("Location: manage-listings.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
        exit;
    }
}

// Check for and display redirected messages
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = urldecode($_GET['msg']);
    $message_type = urldecode($_GET['type']);
}

// --- Fetch Listings Logic (GET Request for Search/Filter) ---

$search_term = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'pending'; // Default focus on pending for moderation
$filter_type = $_GET['type'] ?? 'all';

$sql = "SELECT p.*, u.full_name AS agent_name
        FROM properties p
        JOIN users u ON p.agent_id = u.user_id
        WHERE 1=1";
$params = [];
$types = '';

// Search filter
if ($search_term) {
    $sql .= " AND (p.title LIKE ? OR p.location LIKE ? OR u.full_name LIKE ?)";
    $search_like = "%{$search_term}%";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'sss';
}

// Status filter
if ($filter_status !== 'all') {
    $sql .= " AND p.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

// Property Type filter
if ($filter_type !== 'all') {
    $sql .= " AND p.property_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Manage Listings | Rentflow360</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome CDN for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease-in-out;
        }
        .modal-content {
            transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#007bff',
                        'secondary': '#6c757d',
                        'success': '#28a745',
                        'danger': '#dc3545',
                        'warning': '#ffc107',
                        'info': '#17a2b8',
                    },
                }
            }
        }
        
        let activeModalId = null;

        /**
         * Opens a specific modal and sets up the form data for the action.
         */
        function openActionModal(action, propertyId, title) {
            const modalId = `${action}Modal`;
            const form = document.getElementById(`${action}Form`);
            
            // Set the dynamic content
            document.getElementById(`${action}Title`).textContent = title;
            form.querySelector('[name="property_id"]').value = propertyId;
            
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.querySelector('.modal-content').classList.add('scale-100', 'opacity-100');
                    modal.querySelector('.modal-content').classList.remove('scale-90', 'opacity-0');
                }, 10);
                activeModalId = modalId;
            }
        }

        /**
         * Closes the currently active modal.
         */
        function closeModal() {
            if (activeModalId) {
                const modal = document.getElementById(activeModalId);
                if (modal) {
                    modal.querySelector('.modal-content').classList.add('scale-90', 'opacity-0');
                    modal.querySelector('.modal-content').classList.remove('scale-100', 'opacity-100');
                    setTimeout(() => {
                        modal.classList.add('hidden');
                        activeModalId = null;
                    }, 300);
                }
            }
        }

        // Setup event listener to close modal on Escape key press
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && activeModalId) {
                closeModal();
            }
        });
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
                    <a href="manage-listings.php" class="flex items-center p-3 rounded-lg bg-gray-700 text-white font-semibold">
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
            <h2 class="text-3xl font-bold text-gray-800">Listing Moderation</h2>
            <p class="text-gray-500">Review and moderate all property listings submitted by agents.</p>
        </header>

        <!-- Notification Message -->
        <?php if ($message): ?>
        <div class="mb-4 p-4 rounded-lg shadow-md <?php 
            echo $message_type === 'success' ? 'bg-success/10 text-success border border-success' : 
                 ($message_type === 'warning' ? 'bg-warning/10 text-warning border border-warning' : 
                 ($message_type === 'info' ? 'bg-info/10 text-info border border-info' : 'bg-danger/10 text-danger border border-danger')); 
        ?>">
            <i class="fas <?php 
                echo $message_type === 'success' ? 'fa-check-circle' : 
                     ($message_type === 'error' ? 'fa-exclamation-triangle' : 
                     ($message_type === 'warning' ? 'fa-ban' : 'fa-info-circle')); 
            ?> mr-2"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Control Bar (Search & Filter) -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
            <form method="GET" class="flex space-x-2 w-full md:w-auto">
                <input type="text" name="search" placeholder="Search by title, location, or agent..." value="<?php echo htmlspecialchars($search_term); ?>" class="p-2 border border-gray-300 rounded-lg w-full md:w-80 shadow-sm focus:ring-primary focus:border-primary">
                
                <select name="status" onchange="this.form.submit()" class="p-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary focus:border-primary">
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending (Review)</option>
                    <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                </select>
                
                <select name="type" onchange="this.form.submit()" class="p-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary focus:border-primary hidden sm:block">
                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="apartment" <?php echo $filter_type === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                    <option value="house" <?php echo $filter_type === 'house' ? 'selected' : ''; ?>>House</option>
                    <option value="commercial" <?php echo $filter_type === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                    <option value="land" <?php echo $filter_type === 'land' ? 'selected' : ''; ?>>Land</option>
                    <option value="office" <?php echo $filter_type === 'office' ? 'selected' : ''; ?>>Office</option>
                </select>

                <button type="submit" class="p-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition duration-150 flex items-center"><i class="fas fa-filter"></i></button>
            </form>
        </div>

        <!-- Listings Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID / Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Listing Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price / Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status / Feature</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($listings)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No listings found matching your criteria.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($listings as $listing): ?>
                    <tr class="hover:bg-gray-50 transition duration-100">
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="font-medium text-gray-900">#<?php echo $listing['property_id']; ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('Y-m-d', strtotime($listing['created_at'])); ?></div>
                        </td>
                        <td class="px-6 py-4 max-w-xs xl:max-w-md">
                            <div class="text-base font-semibold text-primary hover:underline cursor-pointer" title="<?php echo htmlspecialchars($listing['title']); ?>">
                                <?php echo htmlspecialchars(substr($listing['title'], 0, 40)) . (strlen($listing['title']) > 40 ? '...' : ''); ?>
                            </div>
                            <div class="text-sm text-gray-500 flex items-center mt-1">
                                <i class="fas fa-map-marker-alt text-xs mr-1"></i> <?php echo htmlspecialchars($listing['location']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="font-bold text-gray-900"><?php echo format_price($listing['price']); ?></div>
                            <div class="text-xs text-secondary capitalize"><?php echo $listing['listing_type'] . ' | ' . $listing['property_type']; ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            <div class="font-medium"><?php echo htmlspecialchars($listing['agent_name']); ?></div>
                            <div class="text-xs text-gray-500">ID: <?php echo $listing['agent_id']; ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo get_listing_status_badge($listing['status']); ?>
                            <?php if ($listing['is_featured']): ?>
                                <span class='inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 mt-1'><i class="fas fa-star text-yellow-500 mr-1"></i> Featured</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <!-- View Button (Placeholder link to the full listing details page) -->
                            <a href="../listing-details.php?id=<?php echo $listing['property_id']; ?>" target="_blank" class="text-gray-500 hover:text-primary transition duration-150 p-2 rounded-full hover:bg-gray-100" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>

                            <!-- Approve Button -->
                            <?php if ($listing['status'] !== 'approved'): ?>
                            <button 
                                onclick="openActionModal('approve', <?php echo $listing['property_id']; ?>, '<?php echo addslashes(htmlspecialchars($listing['title'])); ?>')" 
                                class="text-success hover:text-green-700 transition duration-150 p-2 rounded-full hover:bg-green-50" title="Approve Listing">
                                <i class="fas fa-check-circle"></i>
                            </button>
                            <?php endif; ?>

                            <!-- Reject Button -->
                            <?php if ($listing['status'] !== 'rejected'): ?>
                            <button 
                                onclick="openActionModal('reject', <?php echo $listing['property_id']; ?>, '<?php echo addslashes(htmlspecialchars($listing['title'])); ?>')" 
                                class="text-danger hover:text-red-700 transition duration-150 p-2 rounded-full hover:bg-red-50" title="Reject Listing">
                                <i class="fas fa-times-circle"></i>
                            </button>
                            <?php endif; ?>

                            <!-- Toggle Feature Button -->
                            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to toggle the featured status for this listing?')">
                                <input type="hidden" name="action" value="toggle_feature">
                                <input type="hidden" name="property_id" value="<?php echo $listing['property_id']; ?>">
                                <button type="submit" 
                                    class="p-2 rounded-full transition duration-150 
                                           <?php echo $listing['is_featured'] ? 'text-yellow-500 hover:bg-yellow-100' : 'text-gray-400 hover:bg-gray-100'; ?>" 
                                    title="<?php echo $listing['is_featured'] ? 'Remove from Featured' : 'Make Featured'; ?>">
                                    <i class="fas fa-bolt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <!-- --- MODALS --- -->

    <!-- 1. Approve Confirmation Modal -->
    <div id="approveModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4 modal-overlay">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md modal-content opacity-0 scale-90" onclick="event.stopPropagation()">
                <div class="p-6 text-center">
                    <i class="fas fa-check-circle text-success text-5xl mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Approval</h3>
                    <p class="text-gray-600 mb-6">Are you sure you want to **APPROVE** the listing: <strong id="approveTitle" class="text-success"></strong>? It will become public.</p>
                    
                    <form method="POST" id="approveForm" class="flex justify-center space-x-4">
                        <input type="hidden" name="action" value="approve_listing">
                        <input type="hidden" name="property_id" value="">
                        
                        <button type="button" onclick="closeModal()" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-success text-white font-semibold rounded-lg hover:bg-green-600 transition duration-150">
                            <i class="fas fa-thumbs-up mr-2"></i> Approve
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Reject Confirmation Modal -->
    <div id="rejectModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4 modal-overlay">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md modal-content opacity-0 scale-90" onclick="event.stopPropagation()">
                <div class="p-6 text-center">
                    <i class="fas fa-times-circle text-danger text-5xl mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Rejection</h3>
                    <p class="text-gray-600 mb-6">Are you sure you want to **REJECT** the listing: <strong id="rejectTitle" class="text-danger"></strong>? The agent may be notified.</p>
                    
                    <form method="POST" id="rejectForm" class="flex justify-center space-x-4">
                        <input type="hidden" name="action" value="reject_listing">
                        <input type="hidden" name="property_id" value="">
                        
                        <button type="button" onclick="closeModal()" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-danger text-white font-semibold rounded-lg hover:bg-red-600 transition duration-150">
                            <i class="fas fa-ban mr-2"></i> Reject
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
