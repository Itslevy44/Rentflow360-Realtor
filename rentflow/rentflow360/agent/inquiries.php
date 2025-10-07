<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('agent');

$agent_id = $_SESSION['user_id'];

// --- Handle Inquiry Actions (Update Status/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_id'])) {
    $inquiry_id = intval($_POST['inquiry_id']);
    $action = $_POST['action'] ?? '';
    
    // Safety check: Ensure the inquiry belongs to a property owned by this agent
    $check_query = "SELECT i.inquiry_id FROM inquiries i JOIN properties p ON i.property_id = p.property_id WHERE i.inquiry_id = ? AND p.agent_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $inquiry_id, $agent_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        if ($action === 'mark_contacted') {
            $update_stmt = $conn->prepare("UPDATE inquiries SET status = 'Contacted', updated_at = NOW() WHERE inquiry_id = ?");
            $update_stmt->bind_param("i", $inquiry_id);
            if ($update_stmt->execute()) {
                $_SESSION['success'] = "Inquiry marked as contacted.";
            } else {
                $_SESSION['error'] = "Failed to update inquiry status.";
            }
        } elseif ($action === 'delete') {
            $delete_stmt = $conn->prepare("DELETE FROM inquiries WHERE inquiry_id = ?");
            $delete_stmt->bind_param("i", $inquiry_id);
            if ($delete_stmt->execute()) {
                $_SESSION['success'] = "Inquiry deleted.";
            } else {
                $_SESSION['error'] = "Failed to delete inquiry.";
            }
        }
    } else {
        $_SESSION['error'] = "Unauthorized action or inquiry not found.";
    }
    header("Location: inquiries.php"); 
    exit();
}


// --- Fetch All Inquiries for Agent's Properties ---
$query = "SELECT 
            i.inquiry_id, 
            i.name, 
            i.email, 
            i.phone, 
            i.message, 
            i.created_at, 
            i.status, 
            p.title AS property_title,
            p.property_id
          FROM inquiries i
          JOIN properties p ON i.property_id = p.property_id
          WHERE p.agent_id = ?
          ORDER BY i.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$result = $stmt->get_result();
$inquiries = $result->fetch_all(MYSQLI_ASSOC);


// Display messages from redirect (if any)
$message = [];
if (isset($_SESSION['success'])) {
    $message = ['type' => 'success', 'text' => $_SESSION['success']];
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $message = ['type' => 'error', 'text' => $_SESSION['error']];
    unset($_SESSION['error']);
}

// Function to determine CSS class for status badge
function getInquiryStatusBadgeClass($status) {
    switch ($status) {
        case 'New': return 'primary';
        case 'Contacted': return 'success';
        case 'Archived': return 'secondary';
        default: return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inquiries - Rentflow360</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>Property Inquiries (<?php echo count($inquiries); ?>)</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $message['type']; ?>"><?php echo $message['text']; ?></div>
            <?php endif; ?>

            <?php if (count($inquiries) > 0): ?>
                <div class="inquiry-container">
                    <?php foreach ($inquiries as $inquiry): ?>
                    <div class="inquiry-card <?php echo strtolower(str_replace(' ', '-', $inquiry['status'])); ?>">
                        <div class="inquiry-header">
                            <span class="inquiry-date"><?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?></span>
                            <span class="status-badge <?php echo getInquiryStatusBadgeClass($inquiry['status']); ?>">
                                <?php echo htmlspecialchars($inquiry['status']); ?>
                            </span>
                        </div>
                        
                        <h3 class="property-link">
                            <i class="fas fa-home"></i> 
                            <a href="../property-details.php?id=<?php echo $inquiry['property_id']; ?>">
                                <?php echo htmlspecialchars($inquiry['property_title']); ?>
                            </a>
                        </h3>
                        
                        <div class="inquiry-details">
                            <div class="detail-row">
                                <i class="fas fa-user-circle"></i>
                                <strong>Client:</strong> 
                                <span><?php echo htmlspecialchars($inquiry['name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-at"></i>
                                <strong>Email:</strong> 
                                <a href="mailto:<?php echo htmlspecialchars($inquiry['email']); ?>"><?php echo htmlspecialchars($inquiry['email']); ?></a>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-phone-alt"></i>
                                <strong>Phone:</strong> 
                                <a href="tel:<?php echo htmlspecialchars($inquiry['phone']); ?>"><?php echo htmlspecialchars($inquiry['phone']); ?></a>
                            </div>
                        </div>

                        <div class="inquiry-message">
                            <h4>Message:</h4>
                            <p><?php echo nl2br(htmlspecialchars(substr($inquiry['message'], 0, 150))) . (strlen($inquiry['message']) > 150 ? '...' : ''); ?></p>
                        </div>
                        
                        <div class="inquiry-actions">
                            <?php if ($inquiry['status'] !== 'Contacted'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="mark_contacted">
                                <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['inquiry_id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-check-circle"></i> Mark Contacted
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this inquiry?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['inquiry_id']; ?>">
                                <button type="submit" class="btn btn-danger-outline btn-sm">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox fa-4x"></i>
                    <h2>No New Inquiries</h2>
                    <p>You currently don't have any incoming property inquiries.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
