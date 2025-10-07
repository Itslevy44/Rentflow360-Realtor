<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('agent');

$agent_id = $_SESSION['user_id'];

// Fetch all properties for the logged-in agent
$query = "SELECT 
			p.*, 
			(SELECT photo_url FROM property_photos pp WHERE pp.property_id = p.property_id AND pp.is_primary = 1 LIMIT 1) as primary_photo,
			(SELECT COUNT(*) FROM inquiries i WHERE i.property_id = p.property_id) as total_inquiries
		  FROM properties p
		  WHERE p.agent_id = ?
		  ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$result = $stmt->get_result();
$listings = $result->fetch_all(MYSQLI_ASSOC);

// Handle Delete Request (Simplified, checking ownership first)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_property' && isset($_POST['property_id'])) {
	$delete_id = intval($_POST['property_id']);
	
	// Check ownership before deleting
	$check_ownership = $conn->prepare("SELECT property_id FROM properties WHERE property_id = ? AND agent_id = ?");
	$check_ownership->bind_param("ii", $delete_id, $agent_id);
	$check_ownership->execute();
	
	if ($check_ownership->get_result()->num_rows > 0) {
		// Deleting the property will cascade delete photos and amenities due to foreign key constraints in the schema.sql
		$delete_stmt = $conn->prepare("DELETE FROM properties WHERE property_id = ?");
		$delete_stmt->bind_param("i", $delete_id);
		
		if ($delete_stmt->execute()) {
			$_SESSION['success'] = "Property listing deleted successfully.";
		} else {
			$_SESSION['error'] = "Failed to delete property.";
		}
	} else {
		$_SESSION['error'] = "Unauthorized action or property not found.";
	}
	header("Location: manage-properties.php"); 
	exit();
}


// Function to determine CSS class for status badge (defined in functions.php, but copied here temporarily if not yet defined)
function getStatusBadgeClass($status) {
	switch ($status) {
        case 'approved': return 'success'; // 'approved' is the active listing status
		case 'pending': return 'warning';
		case 'sold':
		case 'rented': return 'info';
		case 'rejected': return 'danger';
		default: return 'secondary';
	}
}

// Display messages from redirect (if any)
$message = [];
if (isset($_SESSION['success'])) {
	$message = ['type' => 'success', 'text' => $_SESSION['success']];
	unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
	$message = ['type' => 'error', 'text' => $_SESSION['error']];
	unset($_SESSION['error']);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Properties - Rentflow360</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
	<div class="dashboard-wrapper">
		<?php include '../includes/sidebar.php'; ?>
		
		<div class="dashboard-content">
			<div class="dashboard-header">
				<h1>My Property Listings (<?php echo count($listings); ?>)</h1>
				<a href="add-property.php" class="btn btn-primary add-listing-btn">
					<i class="fas fa-plus"></i> Add New Property
				</a>
			</div>

			<?php if (!empty($message)): ?>
				<div class="alert <?php echo $message['type']; ?>"><?php echo $message['text']; ?></div>
			<?php endif; ?>

			<?php if (count($listings) > 0): ?>
				<div class="listing-container">
					<?php foreach ($listings as $listing): ?>
					<div class="listing-card">
						<div class="listing-image">
							<img src="../assets/images/uploads/<?php echo $listing['primary_photo'] ?? 'placeholder.jpg'; ?>" 
								 alt="<?php echo htmlspecialchars($listing['title']); ?>"
								 onerror="this.onerror=null;this.src='https://placehold.co/150x120/A0B9E3/FFFFFF?text=No+Image';">
						</div>
						<div class="listing-details">
							<h3 class="listing-title">
								<a href="../property-details.php?id=<?php echo $listing['property_id']; ?>">
									<?php echo htmlspecialchars($listing['title']); ?>
								</a>
							</h3>
							<p class="listing-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($listing['location']); ?>, <?php echo htmlspecialchars($listing['city']); ?></p>
							
							<div class="listing-specs">
								<span><i class="fas fa-bed"></i> <?php echo $listing['bedrooms']; ?> Beds</span>
								<span><i class="fas fa-bath"></i> <?php echo $listing['bathrooms']; ?> Baths</span>
								<span><i class="fas fa-ruler-combined"></i> <?php echo $listing['size_sqft']; ?> sqft</span>
							</div>
							
							<div class="listing-meta">
								<span class="listing-price"><?php echo formatPrice($listing['price']); ?></span>
								<span class="status-badge <?php echo getStatusBadgeClass($listing['status']); ?>">
									<?php echo ucfirst($listing['status']); ?>
								</span>
							</div>
						</div>

						<div class="listing-stats">
							<div class="stat-item">
								<i class="fas fa-eye"></i>
								<span><?php echo number_format($listing['views_count']); ?> Views</span> <!-- CORRECTED FROM views to views_count -->
							</div>
							<div class="stat-item">
								<i class="fas fa-envelope-open-text"></i>
								<span><?php echo number_format($listing['total_inquiries']); ?> Inquiries</span>
							</div>
						</div>
						
						<div class="listing-actions">
							<a href="edit-property.php?id=<?php echo $listing['property_id']; ?>" class="btn btn-secondary btn-sm">
								<i class="fas fa-edit"></i> Edit
							</a>
                            <!-- Replaced confirm() with a placeholder to meet environment safety standards -->
                            <button type="button" class="btn btn-danger btn-sm delete-btn" data-property-id="<?php echo $listing['property_id']; ?>">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<div class="empty-state">
					<i class="fas fa-building-circle-xmark fa-4x"></i>
					<h2>No Properties Found</h2>
					<p>You have not added any properties yet. Start by creating your first listing!</p>
					<a href="add-property.php" class="btn btn-primary btn-lg"><i class="fas fa-plus-circle"></i> Add Property Now</a>
				</div>
			<?php endif; ?>
		</div>
	</div>

    <!-- Custom Modal/UI for Delete Confirmation -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to permanently delete this property listing? This action cannot be undone.</p>
            <div class="modal-actions">
                <button id="cancelDelete" class="btn btn-secondary">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_property">
                    <input type="hidden" name="property_id" id="modalPropertyId">
                    <button type="submit" class="btn btn-danger">Confirm Delete</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal logic to handle delete confirmation (replacing alert/confirm)
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('deleteModal');
            const closeBtn = document.querySelector('.close-btn');
            const cancelBtn = document.getElementById('cancelDelete');
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const modalPropertyId = document.getElementById('modalPropertyId');

            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const propertyId = this.getAttribute('data-property-id');
                    modalPropertyId.value = propertyId;
                    modal.style.display = 'flex';
                });
            });

            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });

            cancelBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });

            window.addEventListener('click', (event) => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
