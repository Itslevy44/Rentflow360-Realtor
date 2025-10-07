<?php
session_start();
// The handler is expected to be located in the 'user' directory,
// so we go up one level to reach the 'includes' directory.
require_once '../includes/db_connection.php';
require_once '../includes/functions.php'; // Contains requireLogin() and other security helpers

// Ensure the user is logged in before proceeding
requireLogin();

// The user ID is mandatory for all operations to ensure security (Authorization)
$user_id = $_SESSION['user_id'];
$redirect_url = 'alerts.php'; // Redirect back to the main alerts listing page

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header("Location: $redirect_url");
    exit();
}

// Ensure the action parameter is set
if (!isset($_POST['action'])) {
    $_SESSION['error'] = 'Action not specified.';
    header("Location: $redirect_url");
    exit();
}

$action = $_POST['action'];

try {
    switch ($action) {
        // --- 1. Create New Alert ---
        case 'create':
            // Basic validation
            if (empty($_POST['location']) || empty($_POST['property_type'])) {
                throw new Exception("Location and property type are required.");
            }

            // Sanitize and collect criteria
            $criteria = [
                'location'      => sanitize_input($_POST['location']),
                'property_type' => sanitize_input($_POST['property_type']),
                // Convert to int and allow null/0 if not provided
                'min_price'     => (int)($_POST['min_price'] ?? 0),
                'max_price'     => (int)($_POST['max_price'] ?? 0),
                'bedrooms'      => (int)($_POST['bedrooms'] ?? 0),
            ];

            // Filter out empty/zero values for cleaner storage
            $criteria = array_filter($criteria, function($value) {
                return $value !== null && $value !== 0 && $value !== '';
            });

            // Encode criteria into JSON for storage in the database
            $criteria_json = json_encode($criteria);
            $alert_type = 'search'; // Default type for property search alerts

            // SQL to insert the new alert. is_active defaults to 1 (active).
            $query = "INSERT INTO alerts (user_id, alert_type, criteria, is_active) VALUES (?, ?, ?, 1)";
            $stmt = $conn->prepare($query);

            if (!$stmt) {
                 throw new Exception("Database prepare failed: " . $conn->error);
            }

            $stmt->bind_param("iss", $user_id, $alert_type, $criteria_json);

            if (!$stmt->execute()) {
                throw new Exception("Failed to create alert: " . $stmt->error);
            }

            $_SESSION['success'] = 'New property alert successfully created and activated!';
            break;

        // --- 2. Toggle Alert Status (Activate/Pause) ---
        case 'toggle_status':
            $alert_id = filter_var($_POST['alert_id'], FILTER_VALIDATE_INT);
            // new_status comes in as "1" or "0" string, convert to int for comparison
            $new_status = (int)$_POST['is_active']; 
            
            if (!$alert_id) {
                throw new Exception("Invalid alert ID.");
            }
            if ($new_status !== 0 && $new_status !== 1) {
                throw new Exception("Invalid status value.");
            }

            // Ensure the user owns the alert before updating
            $query = "UPDATE alerts SET is_active = ? WHERE alert_id = ? AND user_id = ?";
            $stmt = $conn->prepare($query);

            if (!$stmt) {
                 throw new Exception("Database prepare failed: " . $conn->error);
            }

            $stmt->bind_param("iii", $new_status, $alert_id, $user_id);

            if (!$stmt->execute()) {
                throw new Exception("Failed to toggle alert status: " . $stmt->error);
            }

            if ($stmt->affected_rows === 0) {
                 throw new Exception("Alert not found or status already set.");
            }
            
            $status_text = ($new_status === 1) ? 'activated' : 'paused';
            $_SESSION['success'] = "Alert status successfully changed to **{$status_text}**.";
            break;

        // --- 3. Delete Alert ---
        case 'delete':
            $alert_id = filter_var($_POST['alert_id'], FILTER_VALIDATE_INT);

            if (!$alert_id) {
                throw new Exception("Invalid alert ID for deletion.");
            }

            // Ensure the user owns the alert before deleting
            $query = "DELETE FROM alerts WHERE alert_id = ? AND user_id = ?";
            $stmt = $conn->prepare($query);

            if (!$stmt) {
                 throw new Exception("Database prepare failed: " . $conn->error);
            }

            $stmt->bind_param("ii", $alert_id, $user_id);

            if (!$stmt->execute()) {
                throw new Exception("Failed to delete alert: " . $stmt->error);
            }
            
            if ($stmt->affected_rows === 0) {
                 throw new Exception("Alert not found or could not be deleted.");
            }

            $_SESSION['success'] = 'Property alert successfully deleted.';
            break;

        default:
            $_SESSION['error'] = 'Unknown action requested.';
            break;
    }

} catch (Exception $e) {
    // Catch any exceptions (like missing inputs or database errors)
    error_log("Alert Handler Error: " . $e->getMessage());
    $_SESSION['error'] = "Operation failed: " . $e->getMessage();
} finally {
    // Close the statement if it exists and redirect
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    header("Location: $redirect_url");
    exit();
}
