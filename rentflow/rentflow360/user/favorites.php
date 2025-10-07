<?php
session_start();
// NOTE: Assuming db_connection.php and functions.php handle session initialization and database connection.
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$message = [];

// --- Handle Remove Favorite ---
if (isset($_GET['remove'])) {
    $property_id = intval($_GET['remove']);
    
    // Use prepared statement for secure deletion
    $delete_stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND property_id = ?");
    $delete_stmt->bind_param("ii", $user_id, $property_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success'] = "Listing removed from favorites.";
    } else {
        $_SESSION['error'] = "Failed to remove listing from favorites.";
    }
    
    // Redirect to clear the GET parameter and prevent re-submission
    header('Location: favorites.php');
    exit();
}

// Display messages from redirect (if any)
if (isset($_SESSION['success'])) {
	$message = ['type' => 'success', 'text' => $_SESSION['success']];
	unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
	$message = ['type' => 'error', 'text' => $_SESSION['error']];
	unset($_SESSION['error']);
}

// --- Fetch Favorites ---
// NOTE: Switched to prepared statement for better security
$query = "SELECT 
            p.*, 
            pp.photo_url, 
            f.saved_at, 
            u.full_name as agent_name 
          FROM favorites f
          JOIN properties p ON f.property_id = p.property_id
          LEFT JOIN property_photos pp ON p.property_id = pp.property_id AND pp.is_primary = 1
          LEFT JOIN users u ON p.agent_id = u.user_id
          WHERE f.user_id = ?
          ORDER BY f.saved_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$favorites = $result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - Rentflow360</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>My Saved Properties</h1>
                <p class="text-secondary">Keep track of your dream properties (<?php echo count($favorites); ?> listings)</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $message['type']; ?>"><?php echo $message['text']; ?></div>
            <?php endif; ?>

            <?php if (count($favorites) > 0): ?>
                <div class="favorite-listing-grid">
                    <?php foreach ($favorites as $favorite): 
                        // Assuming formatPrice and getStatusBadgeClass functions are in functions.php
                        $badge_class = getStatusBadgeClass($favorite['status']);
                        $photo_path = !empty($favorite['photo_url']) ? '../assets/images/uploads/' . $favorite['photo_url'] : 'https://placehold.co/400x300/A0B9E3/FFFFFF?text=No+Image';
                    ?>
                        <div class="property-card favorite-card">
                            <div class="card-image-wrapper">
                                <img src="<?php echo htmlspecialchars($photo_path); ?>" 
                                     alt="<?php echo htmlspecialchars($favorite['title']); ?>"
                                     onerror="this.onerror=null;this.src='https://placehold.co/400x300/A0B9E3/FFFFFF?text=No+Image';">
                                
                                <span class="status-badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst($favorite['status']); ?>
                                </span>
                            </div>

                            <div class="card-content">
                                <h3 class="card-title"><a href="../property-details.php?id=<?php echo $favorite['property_id']; ?>"><?php echo htmlspecialchars($favorite['title']); ?></a></h3>
                                
                                <p class="card-price"><?php echo formatPrice($favorite['price']); ?></p>

                                <p class="card-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($favorite['location']); ?>, <?php echo htmlspecialchars($favorite['city']); ?></p>

                                <div class="card-specs">
                                    <span><i class="fas fa-bed"></i> <?php echo $favorite['bedrooms']; ?></span>
                                    <span><i class="fas fa-bath"></i> <?php echo $favorite['bathrooms']; ?></span>
                                    <span><i class="fas fa-ruler-combined"></i> <?php echo $favorite['size_sqft']; ?> sqft</span>
                                </div>
                                
                                <div class="card-footer">
                                    <p class="saved-date">Saved: <?php echo date('M d, Y', strtotime($favorite['saved_at'])); ?></p>
                                    <a href="?remove=<?php echo $favorite['property_id']; ?>" class="btn btn-danger btn-sm remove-favorite-btn" onclick="return confirmDeletion(<?php echo $favorite['property_id']; ?>)">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="far fa-heart fa-4x"></i>
                    <h2>Your Favorites List is Empty</h2>
                    <p>It looks like you haven't saved any properties yet. Start browsing to find your favorites!</p>
                    <a href="../search.php" class="btn btn-primary btn-lg"><i class="fas fa-search"></i> Start Searching</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal for Delete Confirmation (replacing built-in confirm) -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Confirm Removal</h2>
            <p>Are you sure you want to remove this property from your favorites?</p>
            <div class="modal-actions">
                <button id="cancelDelete" class="btn btn-secondary">Cancel</button>
                <a id="confirmDeleteLink" href="#" class="btn btn-danger">Yes, Remove It</a>
            </div>
        </div>
    </div>

    <script>
        // Modal logic to handle delete confirmation (replacing built-in confirm)
        function confirmDeletion(propertyId) {
            const modal = document.getElementById('deleteModal');
            const closeBtn = document.querySelector('.close-btn');
            const cancelBtn = document.getElementById('cancelDelete');
            const confirmLink = document.getElementById('confirmDeleteLink');

            confirmLink.href = '?remove=' + propertyId;
            modal.style.display = 'flex';
            
            closeBtn.onclick = () => { modal.style.display = 'none'; };
            cancelBtn.onclick = () => { modal.style.display = 'none'; };
            window.onclick = (event) => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            };

            // Prevent default navigation so the modal can open
            return false; 
        }

        document.addEventListener('DOMContentLoaded', function() {
             // Ensure the modal is hidden by default if not triggered
             const modal = document.getElementById('deleteModal');
             if (modal) {
                modal.style.display = 'none';
             }
        });
    </script>
</body>
</html>
