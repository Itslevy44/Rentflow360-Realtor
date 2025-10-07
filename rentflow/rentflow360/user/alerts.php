<?php
session_start();
// NOTE: Assuming db_connection.php and functions.php handle session initialization and database connection.
require_once '../includes/db_connection.php';
require_once '../includes/functions.php'; // Assumes this contains requireLogin() and other helpers

// --- Core Helper Functions (Ideally in functions.php) ---

/**
 * Parses the JSON criteria field and formats it for display.
 * @param string $jsonCriteria The JSON string from the 'criteria' column.
 * @return array The parsed criteria array.
 */
function parseAlertCriteria(string $jsonCriteria): array {
    return json_decode($jsonCriteria, true) ?? [];
}

/**
 * Formats the criteria details (e.g., location, price) into human-readable strings.
 * This should ideally be in functions.php.
 * @param array $criteria The parsed criteria array.
 * @return string A formatted string describing the criteria.
 */
function formatAlertCriteria(array $criteria): string {
    $parts = [];
    if (!empty($criteria['location'])) {
        $parts[] = 'Location: ' . htmlspecialchars($criteria['location']);
    }
    if (!empty($criteria['property_type'])) {
        $parts[] = 'Type: ' . ucfirst(htmlspecialchars($criteria['property_type']));
    }

    $min = $criteria['min_price'] ?? null;
    $max = $criteria['max_price'] ?? null;

    if ($min && $max) {
        $parts[] = "Price: KES " . number_format($min) . " - " . number_format($max);
    } elseif ($min) {
        $parts[] = "Min Price: KES " . number_format($min);
    } elseif ($max) {
        $parts[] = "Max Price: KES " . number_format($max);
    }
    
    if (($criteria['bedrooms'] ?? 0) > 0) {
        $parts[] = "Bedrooms: " . $criteria['bedrooms'] . '+';
    }

    return implode(' | ', $parts);
}

/**
 * Returns the CSS class for the alert status badge.
 * This should ideally be in functions.php.
 * @param bool $isActive The value of the is_active column (1 or 0).
 * @return string The CSS class name.
 */
function getAlertStatusBadgeClass(bool $isActive): string {
    return $isActive ? 'status-active' : 'status-paused';
}
// --- End Helper Functions ---


// Ensure the user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$message = [];

// Display messages from redirect (if any, e.g., from handle_alert.php)
if (isset($_SESSION['success'])) {
    $message = ['type' => 'success', 'text' => $_SESSION['success']];
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $message = ['type' => 'error', 'text' => $_SESSION['error']];
    unset($_SESSION['error']);
}


// --- Fetch Alerts ---
$query = "SELECT 
            alert_id, 
            alert_type, 
            criteria, 
            is_active, 
            created_at
          FROM alerts
          WHERE user_id = ?
          ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    // Log error, and set a user-friendly message
    error_log("Failed to fetch alerts: " . $stmt->error);
    $alerts = [];
    $message = ['type' => 'error', 'text' => 'We could not load your alerts right now.'];
} else {
    $result = $stmt->get_result();
    $alerts = $result->fetch_all(MYSQLI_ASSOC);
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Property Alerts - RentFlow360 Dashboard</title>
    <!-- Assuming external styles are linked -->
    <link rel="stylesheet" href="../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Embedded styles for this page (similar to your reference) -->
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --text-color: #343a40;
            --bg-light: #f8f9fa;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
            --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.05);
            --font-family: 'Inter', sans-serif;
        }

        /* Basic Structural Styles */
        body { font-family: var(--font-family); color: var(--text-color); background-color: var(--bg-light); line-height: 1.6; }
        .dashboard-wrapper { display: flex; min-height: 100vh; }
        .dashboard-content { width: 100%; padding: 2rem; flex-grow: 1; }
        .dashboard-header { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .dashboard-header h1 { font-size: 2rem; color: var(--text-color); }
        .btn { padding: 0.75rem 1.5rem; font-size: 1rem; font-weight: 600; border-radius: 0.5rem; cursor: pointer; transition: all 0.3s ease; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }
        .btn-primary { background-color: var(--primary-color); color: white; border: 1px solid var(--primary-color); }
        .btn-primary:hover { background-color: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-danger { background-color: var(--danger-color); color: white; border: 1px solid var(--danger-color); }
        .btn-danger:hover { background-color: #bd2130; border-color: #bd2130; }
        .btn-secondary { background-color: var(--secondary-color); color: white; border: 1px solid var(--secondary-color); }
        .btn-secondary:hover { background-color: #5a6268; border-color: #5a6268; }
        
        /* Alert and Form Specific Styles */
        .alert-form-card { background-color: var(--card-bg); padding: 1.5rem; border-radius: 0.75rem; box-shadow: var(--shadow-light); margin-bottom: 3rem; }
        .form-row { display: flex; flex-wrap: wrap; gap: 1rem; }
        .form-group { flex: 1 1 200px; display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; }
        .form-group input, .form-group select { padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; font-size: 1rem; width: 100%; }
        .form-actions { flex: 1 1 100%; display: flex; justify-content: flex-end; align-items: flex-end; margin-top: 1rem; }

        /* Alerts Display Grid */
        .alerts-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .alert-card { background-color: var(--card-bg); border-radius: 0.75rem; box-shadow: var(--shadow-light); padding: 1.5rem; transition: transform 0.2s; }
        .alert-card:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08); }

        .alert-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px dashed var(--border-color); }
        .alert-card h4 { font-size: 1.1rem; margin: 0; }
        
        .alert-details-text { font-size: 0.9rem; color: var(--secondary-color); margin-bottom: 1rem; }

        .alert-status { font-size: 0.85rem; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 0.4rem; }
        .status-active { background-color: #e6ffed; color: var(--success-color); }
        .status-paused { background-color: #fff3cd; color: #b08d00; }
        
        .alert-card-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--border-color); }
        .alert-card-footer span { font-size: 0.8rem; color: var(--secondary-color); font-style: italic; }

        /* Empty State */
        .empty-state { text-align: center; padding: 4rem 2rem; background-color: var(--card-bg); border-radius: 0.75rem; box-shadow: var(--shadow-light); margin-top: 2rem; }
        .empty-state i { color: var(--primary-color); margin-bottom: 1.5rem; opacity: 0.8; font-size: 3rem; }
        
        /* Modal Styles (from favorites.php reference) */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); justify-content: center; align-items: center; }
        .modal-content { background-color: var(--card-bg); padding: 2rem; border-radius: 0.75rem; width: 90%; max-width: 400px; box-shadow: 0 5px 25px rgba(0, 0, 0, 0.4); text-align: center; }
        .modal-content h2 { color: var(--danger-color); margin-bottom: 1rem; }
        .modal-actions { display: flex; justify-content: center; gap: 1rem; margin-top: 1.5rem; }
        .close-btn { position: absolute; top: 10px; right: 20px; font-size: 24px; font-weight: bold; cursor: pointer; color: var(--secondary-color); }

        /* Alert/Error Messages */
        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 0.5rem; font-weight: 600; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-content { padding: 1rem; }
            .form-row { flex-direction: column; gap: 0.5rem; }
            .form-actions { justify-content: center; margin-top: 0; }
            .form-actions .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; // Included sidebar ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>Property Alerts</h1>
                <p class="text-secondary">Get notified instantly when new properties matching your criteria hit the market.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $message['type']; ?>"><?php echo $message['text']; ?></div>
            <?php endif; ?>

            <!-- Alert Creation Form -->
            <div class="alert-form-card">
                <h3><i class="fas fa-plus-circle" style="margin-right: 8px;"></i> Create a New Alert</h3>
                <!-- ACTION points to the handler script -->
                <form id="createAlertForm" method="POST" action="handle_alert.php">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-row">
                        <!-- Location -->
                        <div class="form-group">
                            <label for="location">Location (City or Estate)</label>
                            <input type="text" id="location" name="location" placeholder="e.g., Kilimani, Lavington" required>
                        </div>

                        <!-- Property Type -->
                        <div class="form-group">
                            <label for="property_type">Property Type</label>
                            <!-- NOTE: Values should match ENUMs in properties/alerts table -->
                            <select id="property_type" name="property_type" required>
                                <option value="">Select Type</option>
                                <option value="apartment">Apartment</option>
                                <option value="house">House</option>
                                <option value="commercial">Commercial</option>
                                <option value="land">Land</option>
                                <option value="office">Office</option>
                            </select>
                        </div>

                        <!-- Min Price -->
                        <div class="form-group">
                            <label for="min_price">Min Price (KSH)</label>
                            <input type="number" id="min_price" name="min_price" placeholder="e.g., 50,000" min="0">
                        </div>

                        <!-- Max Price -->
                        <div class="form-group">
                            <label for="max_price">Max Price (KSH)</label>
                            <input type="number" id="max_price" name="max_price" placeholder="e.g., 150,000" min="0">
                        </div>

                        <!-- Bedrooms -->
                        <div class="form-group">
                            <label for="bedrooms">Min Bedrooms</label>
                            <select id="bedrooms" name="bedrooms">
                                <option value="0">Any</option>
                                <option value="1">1+</option>
                                <option value="2">2+</option>
                                <option value="3">3+</option>
                                <option value="4">4+</option>
                                <option value="5">5+</option>
                            </select>
                        </div>
                        
                        <!-- Form Submit Button -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus" style="margin-right: 5px;"></i> Save Alert</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Alert List -->
            <h2><i class="fas fa-list-alt" style="margin-right: 5px; color: var(--primary-color);"></i> My Saved Search Alerts</h2>
            <p class="text-secondary" style="margin-bottom: 1.5rem;">
                <?php echo count($alerts); ?> alerts found. Manage them below.
            </p>

            <div class="alerts-grid">
                
                <?php if (count($alerts) > 0): ?>
                <?php foreach ($alerts as $alert): 
                    // Decode the JSON criteria field fetched from the database
                    $criteria = parseAlertCriteria($alert['criteria']);
                    $criteria_description = formatAlertCriteria($criteria);

                    // Determine status for display and actions
                    $is_active = (bool)$alert['is_active'];
                    $status_text = $is_active ? 'ACTIVE' : 'PAUSED';
                    $status_class = getAlertStatusBadgeClass($is_active);
                    $action_text = $is_active ? 'Pause' : 'Resume';
                    $action_icon = $is_active ? 'fa-pause' : 'fa-play';
                    $action_class = $is_active ? 'btn-secondary' : 'btn-primary';
                    
                    // Format creation date
                    $created_date = date('M d, Y', strtotime($alert['created_at']));
                ?>
                
                <!-- Dynamic Alert Card populated by database data -->
                <div class="alert-card" data-alert-id="<?php echo $alert['alert_id']; ?>">
                    <div class="alert-card-header">
                        <h4><?php echo htmlspecialchars($criteria['location'] ?? 'Global'); ?> - <?php echo ucfirst(htmlspecialchars($criteria['property_type'] ?? 'Property')); ?></h4>
                        <span class="alert-status <?php echo $status_class; ?>">
                            <?php echo $status_text; ?> <i class="fas fa-bell<?php echo $is_active ? '' : '-slash'; ?>"></i>
                        </span>
                    </div>
                    <div class="alert-details-text">
                        <?php echo $criteria_description; ?>
                    </div>

                    <div class="alert-card-footer">
                        <span>Created: <?php echo $created_date; ?></span>
                        <div class="card-actions">
                            <!-- Status Change button: uses JS to open a confirmation modal for the status change -->
                            <button class="btn btn-sm <?php echo $action_class; ?>" 
                                onclick="openStatusModal(<?php echo $alert['alert_id']; ?>, '<?php echo $action_text; ?>', <?php echo $is_active ? 'false' : 'true'; ?>)">
                                <i class="fas <?php echo $action_icon; ?>"></i> <?php echo $action_text; ?>
                            </button>
                            <!-- Delete button action opens modal -->
                            <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?php echo $alert['alert_id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php endforeach; ?>
                <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-bell-slash"></i>
                    <h2>No Active Alerts</h2>
                    <p>You haven't set up any property alerts yet. Use the form above to tell us what you're looking for!</p>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>


    <!-- Status Change Confirmation Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeStatusModal()">&times;</span>
            <h2 id="statusModalTitle">Confirm Action</h2>
            <p id="statusModalText">Are you sure you want to change the status of this alert?</p>
            <form id="statusChangeForm" method="POST" action="handle_alert.php">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" id="status_alert_id" name="alert_id">
                <input type="hidden" id="status_new_value" name="is_active">
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="confirmStatusBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Delete Confirmation Modal (Mirrored from favorites.php style) -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeDeleteModal()">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to **delete** this property alert? This action cannot be undone.</p>
            <form id="deleteAlertForm" method="POST" action="handle_alert.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="alertIdToDelete" name="alert_id">
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt" style="margin-right: 5px;"></i> Yes, Delete It</button>
                </div>
            </form>
        </div>
    </div>


    <script>
        // --- MODAL CONTROL LOGIC ---

        const deleteModal = document.getElementById('deleteModal');
        const alertIdInput = document.getElementById('alertIdToDelete');
        const statusModal = document.getElementById('statusModal');

        // DELETE MODAL FUNCTIONS
        function openDeleteModal(alertId) {
            alertIdInput.value = alertId;
            deleteModal.style.display = 'flex';
        }

        function closeDeleteModal() {
            deleteModal.style.display = 'none';
            alertIdInput.value = '';
        }

        // STATUS MODAL FUNCTIONS
        function openStatusModal(alertId, actionText, newStatus) {
            document.getElementById('statusModalTitle').textContent = `${actionText} Alert`;
            document.getElementById('statusModalText').innerHTML = `Are you sure you want to **${actionText.toLowerCase()}** this property alert?`;
            
            // Set form values
            document.getElementById('status_alert_id').value = alertId;
            document.getElementById('status_new_value').value = newStatus ? 1 : 0; // 1 for active/resume, 0 for paused

            // Update button appearance
            const confirmBtn = document.getElementById('confirmStatusBtn');
            confirmBtn.textContent = actionText;
            confirmBtn.className = newStatus ? 'btn btn-primary' : 'btn btn-secondary';

            statusModal.style.display = 'flex';
        }

        function closeStatusModal() {
            statusModal.style.display = 'none';
            document.getElementById('status_alert_id').value = '';
            document.getElementById('status_new_value').value = '';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
            if (event.target === statusModal) {
                closeStatusModal();
            }
        }

        // Ensure modals are hidden on load
        document.addEventListener('DOMContentLoaded', function() {
            deleteModal.style.display = 'none';
            statusModal.style.display = 'none';
        });
    </script>
</body>
</html>
