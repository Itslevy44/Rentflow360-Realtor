<?php
session_start();
// NOTE: Assuming db_connection.php and functions.php handle session initialization and database connection.
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Set headers for JSON response
header('Content-Type: application/json');

// --- Helper Functions ---
function response(string $status, string $message, array $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    response('error', 'Authentication failed. Please log in.');
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Check required inputs
if (empty($action) || $user_id != $input['user_id']) {
     response('error', 'Invalid request or user mismatch.', ['input' => $input]);
}

try {
    switch ($action) {
        
        case 'fetch_thread':
            $partner_id = intval($input['partner_id']);
            $last_id = intval($input['last_id']);

            if (!$partner_id) {
                response('error', 'Partner ID is required.');
            }

            // Fetch all messages between user and partner, optionally only newer than last_id
            $query = "
                SELECT 
                    message_id, sender_id, message_text, created_at 
                FROM messages 
                WHERE 
                    ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                    AND message_id > ?
                ORDER BY created_at ASC";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                response('error', 'Database preparation failed: ' . $conn->error);
            }
            
            // Bind parameters: (user, partner, partner, user, last_id)
            $stmt->bind_param("iiiii", $user_id, $partner_id, $partner_id, $user_id, $last_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $messages = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Mark fetched messages as read (messages received by $user_id from $partner_id)
            if ($last_id == 0) { // Only update read status on initial load or full sync
                 $mark_read_stmt = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE");
                 $mark_read_stmt->bind_param("ii", $partner_id, $user_id);
                 $mark_read_stmt->execute();
                 $mark_read_stmt->close();
            }

            response('success', 'Messages fetched.', ['messages' => $messages]);
            break;

        case 'send_message':
            $receiver_id = intval($input['receiver_id']);
            $message_text = trim($input['message_text']);

            if (!$receiver_id || empty($message_text)) {
                response('error', 'Receiver and message text are required.');
            }

            // Insert new message
            $insert_stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
            if (!$insert_stmt) {
                 response('error', 'Database preparation failed: ' . $conn->error);
            }

            $insert_stmt->bind_param("iis", $user_id, $receiver_id, $message_text);
            
            if ($insert_stmt->execute()) {
                response('success', 'Message sent.', ['message_id' => $insert_stmt->insert_id]);
            } else {
                response('error', 'Failed to send message: ' . $insert_stmt->error);
            }
            $insert_stmt->close();
            break;

        default:
            response('error', 'Unknown API action.');
            break;
    }
} catch (Exception $e) {
    error_log("Message API Error: " . $e->getMessage());
    response('error', 'An unexpected server error occurred.', ['details' => $e->getMessage()]);
}

?>
