<?php
// PHP Script for Admin User Management (admin/manage-users.php)

session_start();

// NOTE: The path is '../' because this file is inside the 'admin/' subdirectory.
// You must ensure this path correctly points to your database connection file.
require_once '../includes/db_connection.php'; 

// --- Authentication and Authorization Check ---
// IMPORTANT: In a production environment, you must check the user session here.
// Placeholder: Assuming the admin user ID is 1 for testing purposes.
$admin_id = 1; 

// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     header('Location: ../login.php');
//     exit;
// }
// $admin_id = $_SESSION['user_id']; 


// Helper Functions for UI Badges
function get_role_badge($role) {
    $role_classes = [
        'admin' => 'bg-red-500 text-white',
        'agent' => 'bg-blue-500 text-white',
        'user' => 'bg-green-500 text-white',
        'guest' => 'bg-gray-400 text-white',
    ];
    $class = $role_classes[$role] ?? 'bg-gray-200 text-gray-800';
    return "<span class='inline-flex items-center px-3 py-1 text-sm font-medium rounded-full {$class} capitalize'>{$role}</span>";
}

function get_status_badge($status) {
    $status_classes = [
        'active' => 'bg-green-100 text-green-800',
        'suspended' => 'bg-red-100 text-red-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
    ];
    $class = $status_classes[$status] ?? 'bg-gray-100 text-gray-800';
    return "<span class='inline-flex items-center px-3 py-1 text-xs font-medium rounded-full {$class} capitalize'>{$status}</span>";
}

$message = null;
$message_type = null;

// --- Handle Form Submissions (Create, Update, Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'update_user' && isset($_POST['user_id'], $_POST['role'], $_POST['status'])) {
            // Update User Role and Status
            $user_id = (int)$_POST['user_id'];
            $role = $_POST['role'];
            $status = $_POST['status'];

            $stmt = $conn->prepare("UPDATE users SET role = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $stmt->bind_param("ssi", $role, $status, $user_id);
            $stmt->execute();
            $message = "User ID {$user_id} updated successfully.";
            $message_type = 'success';
            $stmt->close();
            
        } elseif ($action === 'delete_user' && isset($_POST['user_id'])) {
            // Delete User
            $user_id = (int)$_POST['user_id'];

            if ($user_id === $admin_id) {
                $message = "Error: You cannot delete your own admin account.";
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $message = "User ID {$user_id} deleted successfully.";
                $message_type = 'success';
                $stmt->close();
            }

        } elseif ($action === 'create_user' && isset($_POST['full_name'], $_POST['email'], $_POST['password'], $_POST['new_role'], $_POST['new_status'])) {
            // Create New User
            $full_name = $_POST['full_name'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            $role = $_POST['new_role'];
            $status = $_POST['new_status'];
            
            // Hash the password securely
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, status, email_verified) VALUES (?, ?, ?, ?, ?, TRUE)");
            $stmt->bind_param("sssss", $full_name, $email, $password_hash, $role, $status);
            
            if ($stmt->execute()) {
                $message = "New user '{$full_name}' created successfully.";
                $message_type = 'success';
            } else {
                if ($conn->errno == 1062) {
                    $message = "Error: A user with this email already exists.";
                } else {
                    $message = "Error creating user: " . $stmt->error;
                }
                $message_type = 'error';
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $message = "Database Error: " . $e->getMessage();
        $message_type = 'error';
    }

    // Redirect to clear POST data and prevent resubmission
    if (isset($message)) {
        header("Location: manage-users.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
        exit;
    }
}

// Check for and display redirected messages
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = urldecode($_GET['msg']);
    $message_type = urldecode($_GET['type']);
}

// --- Fetch Users Logic (GET Request) ---

$search_term = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? 'all';

$sql = "SELECT user_id, full_name, email, role, status, created_at, last_login, 
               (SELECT COUNT(property_id) FROM properties WHERE agent_id = u.user_id) AS property_count
        FROM users u 
        WHERE 1=1";
$params = [];
$types = '';

if ($search_term) {
    $sql .= " AND (full_name LIKE ? OR email LIKE ?)";
    $search_like = "%{$search_term}%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'ss';
}

if ($filter_role !== 'all') {
    $sql .= " AND role = ?";
    $params[] = $filter_role;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Manage Users | Rentflow360</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome CDN for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        /* Style for the action modals/dialogs */
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

        // Global state for modals
        let activeModalId = null;

        /**
         * Opens a specific modal.
         * @param {string} modalId - The ID of the modal to open (e.g., 'createUserModal', 'editUserModal-123').
         */
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.querySelector('.modal-content').classList.add('scale-100', 'opacity-100');
                    modal.querySelector('.modal-content').classList.remove('scale-90', 'opacity-0');
                }, 10); // Small delay for transition
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
                    }, 300); // Wait for transition to finish
                }
            }
        }

        // Setup event listener to close modal on Escape key press
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && activeModalId) {
                closeModal();
            }
        });

        // Function to populate the edit form before opening the modal
        function openEditModal(userId, fullName, email, role, status) {
            const modalId = `editUserModal-${userId}`;
            const form = document.getElementById(`editForm-${userId}`);
            
            // Set form values
            form.querySelector('[name="full_name"]').value = fullName;
            form.querySelector('[name="email"]').value = email;
            form.querySelector('[name="role"]').value = role;
            form.querySelector('[name="status"]').value = status;
            
            // Open the modal
            openModal(modalId);
        }

        // Function to handle the delete confirmation
        function openDeleteModal(userId, fullName) {
            const modalId = 'deleteConfirmModal';
            const form = document.getElementById('deleteForm');

            document.getElementById('deleteUserName').textContent = fullName;
            form.querySelector('[name="user_id"]').value = userId;

            openModal(modalId);
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
                    <a href="manage-users.php" class="flex items-center p-3 rounded-lg bg-gray-700 text-white font-semibold">
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
            <h2 class="text-3xl font-bold text-gray-800">User Management</h2>
            <p class="text-gray-500">View, create, and manage all user accounts in the Rentflow360 platform.</p>
        </header>

        <!-- Notification Message -->
        <?php if ($message): ?>
        <div class="mb-4 p-4 rounded-lg shadow-md <?php echo $message_type === 'success' ? 'bg-success/10 text-success border border-success' : 'bg-danger/10 text-danger border border-danger'; ?>">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Control Bar (Search, Filter, Create) -->
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
            <form method="GET" class="flex space-x-2 w-full md:w-auto">
                <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search_term); ?>" class="p-2 border border-gray-300 rounded-lg w-full md:w-80 shadow-sm focus:ring-primary focus:border-primary">
                <select name="role" onchange="this.form.submit()" class="p-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary focus:border-primary">
                    <option value="all">All Roles</option>
                    <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="agent" <?php echo $filter_role === 'agent' ? 'selected' : ''; ?>>Agent</option>
                    <option value="user" <?php echo $filter_role === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="guest" <?php echo $filter_role === 'guest' ? 'selected' : ''; ?>>Guest</option>
                </select>
                <button type="submit" class="p-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition duration-150"><i class="fas fa-search"></i></button>
            </form>
            <button onclick="openModal('createUserModal')" class="w-full md:w-auto px-4 py-2 bg-success text-white font-semibold rounded-lg shadow-md hover:bg-green-600 transition duration-150 flex items-center justify-center">
                <i class="fas fa-user-plus mr-2"></i> Create New User
            </button>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Listings (Agents)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined / Last Login</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No users found matching your criteria.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50 transition duration-100">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $user['user_id']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo get_role_badge($user['role']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo get_status_badge($user['status']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-medium">
                            <?php echo $user['role'] === 'agent' ? $user['property_count'] : 'N/A'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="block">Joined: <?php echo date('Y-m-d', strtotime($user['created_at'])); ?></span>
                            <span class="block text-xs">Last: <?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <button 
                                onclick="openEditModal(
                                    <?php echo $user['user_id']; ?>, 
                                    '<?php echo addslashes(htmlspecialchars($user['full_name'])); ?>', 
                                    '<?php echo addslashes(htmlspecialchars($user['email'])); ?>', 
                                    '<?php echo $user['role']; ?>', 
                                    '<?php echo $user['status']; ?>'
                                )" 
                                class="text-primary hover:text-blue-700 transition duration-150 p-2 rounded-full hover:bg-blue-50" title="Edit User">
                                <i class="fas fa-edit"></i>
                            </button>

                            <?php if ($user['user_id'] !== $admin_id): // Prevent deleting the current admin ?>
                            <button 
                                onclick="openDeleteModal(<?php echo $user['user_id']; ?>, '<?php echo addslashes(htmlspecialchars($user['full_name'])); ?>')" 
                                class="text-danger hover:text-red-700 transition duration-150 p-2 rounded-full hover:bg-red-50" title="Delete User">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <!-- --- MODALS --- -->

    <!-- 1. Create New User Modal -->
    <div id="createUserModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4 modal-overlay">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg modal-content opacity-0 scale-90" onclick="event.stopPropagation()">
                <div class="p-6">
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 flex items-center border-b pb-3">
                        <i class="fas fa-user-plus mr-3 text-success"></i> Create New User
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="mb-4">
                            <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" id="full_name" name="full_name" required class="mt-1 p-3 w-full border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        </div>
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" id="email" name="email" required class="mt-1 p-3 w-full border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        </div>
                        <div class="mb-4">
                            <label for="password" class="block text-sm font-medium text-gray-700">Initial Password</label>
                            <input type="password" id="password" name="password" required class="mt-1 p-3 w-full border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <p class="text-xs text-warning mt-1">User must change this temporary password upon first login.</p>
                        </div>
                        <div class="flex space-x-4 mb-6">
                            <div class="flex-1">
                                <label for="new_role" class="block text-sm font-medium text-gray-700">Role</label>
                                <select id="new_role" name="new_role" required class="mt-1 p-3 w-full border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                                    <option value="user">User</option>
                                    <option value="agent">Agent</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label for="new_status" class="block text-sm font-medium text-gray-700">Status</label>
                                <select id="new_status" name="new_status" required class="mt-1 p-3 w-full border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-success text-white font-semibold rounded-lg hover:bg-green-600 transition duration-150">
                                <i class="fas fa-save mr-2"></i> Save User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Edit User Modals (Dynamically created for each user) -->
    <?php foreach ($users as $user): ?>
    <div id="editUserModal-<?php echo $user['user_id']; ?>" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4 modal-overlay">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg modal-content opacity-0 scale-90" onclick="event.stopPropagation()">
                <div class="p-6">
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 flex items-center border-b pb-3">
                        <i class="fas fa-user-edit mr-3 text-primary"></i> Edit User #<?php echo $user['user_id']; ?>
                    </h3>
                    <form method="POST" id="editForm-<?php echo $user['user_id']; ?>">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                        
                        <div class="mb-4">
                            <label for="edit_full_name_<?php echo $user['user_id']; ?>" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" id="edit_full_name_<?php echo $user['user_id']; ?>" name="full_name" required value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly disabled class="mt-1 p-3 w-full border border-gray-200 bg-gray-50 rounded-lg">
                        </div>
                        <div class="mb-4">
                            <label for="edit_email_<?php echo $user['user_id']; ?>" class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" id="edit_email_<?php echo $user['user_id']; ?>" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>" readonly disabled class="mt-1 p-3 w-full border border-gray-200 bg-gray-50 rounded-lg">
                        </div>

                        <div class="flex space-x-4 mb-6">
                            <div class="flex-1">
                                <label for="edit_role_<?php echo $user['user_id']; ?>" class="block text-sm font-medium text-gray-700">Role</label>
                                <select id="edit_role_<?php echo $user['user_id']; ?>" name="role" required class="mt-1 p-3 w-full border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                                    <option value="guest" <?php echo $user['role'] === 'guest' ? 'selected' : ''; ?>>Guest</option>
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="agent" <?php echo $user['role'] === 'agent' ? 'selected' : ''; ?>>Agent</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label for="edit_status_<?php echo $user['user_id']; ?>" class="block text-sm font-medium text-gray-700">Status</label>
                                <select id="edit_status_<?php echo $user['user_id']; ?>" name="status" required class="mt-1 p-3 w-full border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white font-semibold rounded-lg hover:bg-blue-600 transition duration-150">
                                <i class="fas fa-save mr-2"></i> Apply Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- 3. Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4 modal-overlay">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md modal-content opacity-0 scale-90" onclick="event.stopPropagation()">
                <div class="p-6 text-center">
                    <i class="fas fa-exclamation-triangle text-danger text-5xl mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Deletion</h3>
                    <p class="text-gray-600 mb-6">Are you sure you want to delete the user: <strong id="deleteUserName" class="text-danger"></strong>? This action cannot be undone.</p>
                    
                    <form method="POST" id="deleteForm" class="flex justify-center space-x-4">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="">
                        
                        <button type="button" onclick="closeModal()" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-danger text-white font-semibold rounded-lg hover:bg-red-600 transition duration-150">
                            <i class="fas fa-trash-alt mr-2"></i> Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
