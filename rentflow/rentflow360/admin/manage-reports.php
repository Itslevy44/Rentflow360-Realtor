<?php
// PHP Script for Admin Report and Review Management (admin/manage-reports.php)

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
 * Generates an HTML badge for report status.
 */
function get_report_status_badge($status) {
    $classes = [
        'resolved' => 'bg-green-100 text-green-800',
        'pending' => 'bg-red-100 text-red-800',
    ];
    $class = $classes[$status] ?? 'bg-blue-100 text-blue-800';
    return "<span class='inline-flex items-center px-3 py-1 text-xs font-medium rounded-full {$class} capitalize'>{$status}</span>";
}

$message = null;
$message_type = null;

// --- Handle Moderation Actions (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'resolve_report' && isset($_POST['report_id'])) {
            $report_id = (int)$_POST['report_id'];
            $stmt = $conn->prepare("UPDATE reports SET status = 'resolved' WHERE report_id = ?");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $message = "Report ID {$report_id} marked as resolved successfully.";
            $message_type = 'success';
            $stmt->close();
        
        } elseif ($action === 'delete_review' && isset($_POST['review_id'])) {
            $review_id = (int)$_POST['review_id'];
            $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
            $stmt->bind_param("i", $review_id);
            $stmt->execute();
            $message = "Review ID {$review_id} deleted successfully for inappropriate content.";
            $message_type = 'warning'; 
            $stmt->close();

        } elseif ($action === 'delete_listing_from_report' && isset($_POST['property_id'])) {
            $property_id = (int)$_POST['property_id'];
            $stmt = $conn->prepare("UPDATE properties SET status = 'rejected' WHERE property_id = ?");
            $stmt->bind_param("i", $property_id);
            $stmt->execute();

            // Also resolve all reports against this listing
            $stmt_r = $conn->prepare("UPDATE reports SET status = 'resolved' WHERE property_id = ?");
            $stmt_r->bind_param("i", $property_id);
            $stmt_r->execute();

            $message = "Listing ID {$property_id} rejected and all associated reports resolved.";
            $message_type = 'error'; 
            $stmt->close();
            $stmt_r->close();
        }
    } catch (Exception $e) {
        $message = "Database Error: " . $e->getMessage();
        $message_type = 'error';
    }

    // Redirect to clear POST data and prevent resubmission
    if (isset($message)) {
        // Pass current tab to maintain view context
        $current_tab = $_POST['current_tab'] ?? 'reports';
        header("Location: manage-reports.php?tab=" . urlencode($current_tab) . "&msg=" . urlencode($message) . "&type=" . urlencode($message_type));
        exit;
    }
}

// Check for and display redirected messages
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = urldecode($_GET['msg']);
    $message_type = urldecode($_GET['type']);
}

// Determine the active tab
$active_tab = $_GET['tab'] ?? 'reports';

// --- Fetch Reports Logic ---
$reports_sql = "
    SELECT r.*, p.title AS property_title, p.location, u.full_name AS reporter_name 
    FROM reports r
    JOIN properties p ON r.property_id = p.property_id
    JOIN users u ON r.reporter_id = u.user_id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC";

$reports_result = $conn->query($reports_sql);
$pending_reports = $reports_result ? $reports_result->fetch_all(MYSQLI_ASSOC) : [];


// --- Fetch Reviews Logic ---
$reviews_sql = "
    SELECT v.*, p.title AS property_title, u.full_name AS reviewer_name 
    FROM reviews v
    JOIN properties p ON v.property_id = p.property_id
    JOIN users u ON v.user_id = u.user_id
    ORDER BY v.created_at DESC
    LIMIT 100"; // Limit to 100 recent reviews for performance

$reviews_result = $conn->query($reviews_sql);
$all_reviews = $reviews_result ? $reviews_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Manage Reports & Reviews | Rentflow360</title>
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
        let currentTab = '<?php echo $active_tab; ?>';

        /**
         * Opens a specific modal and sets up the form data for the action.
         */
        function openActionModal(action, id, title, extraId = null) {
            const modalId = `${action}Modal`;
            const form = document.getElementById(`${action}Form`);
            
            // Set the dynamic content
            document.getElementById(`${action}Title`).textContent = title;
            form.querySelector('[name="current_tab"]').value = currentTab;

            if (action === 'resolve_report' || action === 'delete_review') {
                 // For resolve_report: id is report_id. For delete_review: id is review_id.
                form.querySelector('[name="report_id"], [name="review_id"]').value = id;
            } else if (action === 'delete_listing_from_report' && extraId !== null) {
                // For deleting a listing based on a report: id is the report_id, extraId is the property_id
                form.querySelector('[name="report_id"]').value = id; // Optional, useful for tracking
                form.querySelector('[name="property_id"]').value = extraId;
            }

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

        // Function to switch tabs
        function switchTab(tabName) {
            currentTab = tabName;
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.add('hidden');
            });
            document.getElementById(tabName).classList.remove('hidden');

            document.querySelectorAll('.tab-button').forEach(el => {
                el.classList.remove('border-primary', 'text-primary', 'font-semibold');
                el.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700');
            });
            document.getElementById(`${tabName}-tab`).classList.add('border-primary', 'text-primary', 'font-semibold');
            document.getElementById(`${tabName}-tab`).classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700');
            
            // Update URL hash to maintain tab state on refresh
            history.pushState(null, '', `manage-reports.php?tab=${tabName}`);
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize tab based on URL or default
            switchTab(currentTab);
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
                    <a href="manage-reports.php" class="flex items-center p-3 rounded-lg bg-gray-700 text-white font-semibold">
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
            <h2 class="text-3xl font-bold text-gray-800">Reports & Review Moderation</h2>
            <p class="text-gray-500">Manage user reports on listings and moderate reviews for policy violations.</p>
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

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button id="reports-tab" onclick="switchTab('reports')" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 text-sm font-medium transition duration-150">
                    <i class="fas fa-flag mr-2"></i> Pending Reports (<?php echo count($pending_reports); ?>)
                </button>
                <button id="reviews-tab" onclick="switchTab('reviews')" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 text-sm font-medium transition duration-150">
                    <i class="fas fa-comments mr-2"></i> All Reviews
                </button>
            </nav>
        </div>

        <!-- --- TAB CONTENT: PENDING REPORTS --- -->
        <div id="reports" class="tab-content">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Pending Listing Reports</h3>
            <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report ID/Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reported Listing</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reporter / Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($pending_reports)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-check-double text-success mr-2"></i> No pending reports found. The queue is clear!
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($pending_reports as $report): ?>
                        <tr class="hover:bg-red-50/20 transition duration-100">
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="font-medium text-gray-900">#R<?php echo $report['report_id']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo date('Y-m-d H:i', strtotime($report['created_at'])); ?></div>
                            </td>
                            <td class="px-6 py-4 max-w-xs xl:max-w-md">
                                <a href="../listing-details.php?id=<?php echo $report['property_id']; ?>" target="_blank" class="text-base font-semibold text-primary hover:underline" title="View Listing #<?php echo $report['property_id']; ?>">
                                    <?php echo htmlspecialchars(substr($report['property_title'], 0, 40)) . (strlen($report['property_title']) > 40 ? '...' : ''); ?>
                                </a>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($report['location']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="font-medium text-gray-700"><?php echo htmlspecialchars($report['reporter_name']); ?></div>
                                <div class="text-xs font-bold text-red-600 capitalize mt-1"><?php echo htmlspecialchars($report['reason']); ?></div>
                            </td>
                            <td class="px-6 py-4 max-w-sm text-sm text-gray-600">
                                <?php echo htmlspecialchars(substr($report['description'], 0, 80)) . (strlen($report['description']) > 80 ? '...' : ' - No description'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <!-- Action: Resolve Report (No action needed on listing, just close the report) -->
                                <button 
                                    onclick="openActionModal('resolve_report', <?php echo $report['report_id']; ?>, 'Report #<?php echo $report['report_id']; ?>')" 
                                    class="text-success hover:text-green-700 transition duration-150 p-2 rounded-full hover:bg-green-50" title="Mark as Resolved">
                                    <i class="fas fa-check-circle"></i> Resolve
                                </button>

                                <!-- Action: Reject Listing (High Impact Action) -->
                                <button 
                                    onclick="openActionModal('delete_listing_from_report', <?php echo $report['report_id']; ?>, 'Listing: <?php echo addslashes(htmlspecialchars($report['property_title'])); ?>', <?php echo $report['property_id']; ?>)" 
                                    class="text-danger hover:text-red-700 transition duration-150 p-2 rounded-full hover:bg-red-50 border border-red-300" title="Reject Listing and Resolve Reports">
                                    <i class="fas fa-trash-alt"></i> Reject Listing
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- --- TAB CONTENT: ALL REVIEWS --- -->
        <div id="reviews" class="tab-content hidden">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">All User Reviews</h3>
            <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review ID/Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Listing</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reviewer / Rating</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comment</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($all_reviews)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No reviews have been submitted yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($all_reviews as $review): ?>
                        <tr class="hover:bg-gray-50 transition duration-100">
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="font-medium text-gray-900">#V<?php echo $review['review_id']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?></div>
                            </td>
                            <td class="px-6 py-4 max-w-xs xl:max-w-md">
                                <a href="../listing-details.php?id=<?php echo $review['property_id']; ?>" target="_blank" class="text-base font-semibold text-primary hover:underline">
                                    <?php echo htmlspecialchars(substr($review['property_title'], 0, 40)) . (strlen($review['property_title']) > 40 ? '...' : ''); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="font-medium text-gray-700"><?php echo htmlspecialchars($review['reviewer_name']); ?></div>
                                <div class="text-xs font-bold text-yellow-500 mt-1">
                                    <?php 
                                        $rating = (int)$review['rating'];
                                        echo str_repeat('<i class="fas fa-star"></i>', $rating) . str_repeat('<i class="far fa-star"></i>', 5 - $rating);
                                    ?>
                                    (<?php echo $rating; ?>/5)
                                </div>
                            </td>
                            <td class="px-6 py-4 max-w-sm text-sm text-gray-600">
                                <?php echo htmlspecialchars($review['comment']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <!-- Action: Delete Review -->
                                <button 
                                    onclick="openActionModal('delete_review', <?php echo $review['review_id']; ?>, 'Review by <?php echo addslashes(htmlspecialchars($review['reviewer_name'])); ?>')" 
                                    class="text-danger hover:text-red-700 transition duration-150 p-2 rounded-full hover:bg-red-50" title="Delete Review">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
    
    <!-- --- MODALS --- -->

    <!-- 1. Resolve Report Confirmation Modal -->
    <div id="resolve_reportModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4 modal-overlay">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md modal-content opacity-0 scale-90" onclick="event.stopPropagation()">
                <div class="p-6 text-center">
                    <i class="fas fa-check-circle text-success text-5xl mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Resolve Report</h3>
                    <p class="text-gray-600 mb-6">Mark report <strong id="resolve_reportTitle" class="text-success"></strong> as resolved. You should have already taken appropriate action (e.g., rejecting the listing, contacting the agent, or dismissing the report).</p>
                    
                    <form method="POST" id="resolve_reportForm" class="flex justify-center space-x-4">
                        <input type="hidden" name="action" value="resolve_report">
                        <input type="hidden" name="report_id" value="">
                        <input type="hidden" name="current_tab" value="reports">
                        
                        <button type="button" onclick="closeModal()" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-success text-white font-semibold rounded-lg hover:bg-green-600 transition duration-150">
                            <i class="fas fa-check-double mr-2"></i> Resolve
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Delete Review Confirmation Modal -->
    <div id="delete_reviewModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4 modal-overlay">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md modal-content opacity-0 scale-90" onclick="event.stopPropagation()">
                <div class="p-6 text-center">
                    <i class="fas fa-trash-alt text-danger text-5xl mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Review</h3>
                    <p class="text-gray-600 mb-6">Are you sure you want to **DELETE** this review from <strong id="delete_reviewTitle" class="text-danger"></strong>? This action is permanent and should only be used for policy violations.</p>
                    
                    <form method="POST" id="delete_reviewForm" class="flex justify-center space-x-4">
                        <input type="hidden" name="action" value="delete_review">
                        <input type="hidden" name="review_id" value="">
                        <input type="hidden" name="current_tab" value="reviews">
                        
                        <button type="button" onclick="closeModal()" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-danger text-white font-semibold rounded-lg hover:bg-red-600 transition duration-150">
                            <i class="fas fa-times mr-2"></i> Delete Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Reject Listing from Report Modal -->
    <div id="delete_listing_from_reportModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4 modal-overlay">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md modal-content opacity-0 scale-90" onclick="event.stopPropagation()">
                <div class="p-6 text-center">
                    <i class="fas fa-exclamation-triangle text-danger text-5xl mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Reject Reported Listing?</h3>
                    <p class="text-gray-600 mb-6">You are about to **REJECT** the listing: <strong id="delete_listing_from_reportTitle" class="text-danger"></strong>. This will also mark all associated reports as resolved. Are you sure you want to take this disciplinary action?</p>
                    
                    <form method="POST" id="delete_listing_from_reportForm" class="flex justify-center space-x-4">
                        <input type="hidden" name="action" value="delete_listing_from_report">
                        <input type="hidden" name="property_id" value="">
                        <input type="hidden" name="current_tab" value="reports">
                        
                        <button type="button" onclick="closeModal()" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-danger text-white font-semibold rounded-lg hover:bg-red-600 transition duration-150">
                            <i class="fas fa-ban mr-2"></i> Reject Listing
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>


</body>
</html>
