<?php
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
include 'includes/header.php';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get total count
$total_query = "SELECT COUNT(*) as total FROM properties WHERE status = 'approved'";
$total_result = $conn->query($total_query);
$total_properties = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_properties / $per_page);

// Get properties
$query = "SELECT p.*, pp.photo_url, u.full_name as agent_name 
         FROM properties p 
         LEFT JOIN property_photos pp ON p.property_id = pp.property_id AND pp.is_primary = 1
         LEFT JOIN users u ON p.agent_id = u.user_id
         WHERE p.status = 'approved'
         ORDER BY p.created_at DESC
         LIMIT $per_page OFFSET $offset";
$result = $conn->query($query);
?>

<main>
    <section class="properties-section">
        <div class="container">
            <h1>All Properties</h1>
            <p>Browse through our collection of <?php echo $total_properties; ?> properties</p>

            <div class="property-grid">
                <?php while ($property = $result->fetch_assoc()): ?>
                <div class="property-card">
                    <div class="property-image">
                        <img src="assets/images/uploads/<?php echo $property['photo_url'] ?? 'placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($property['title']); ?>">
                        <div class="property-badge"><?php echo ucfirst($property['listing_type']); ?></div>
                    </div>
                    <div class="property-info">
                        <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                        <p class="location">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php echo htmlspecialchars($property['location'] . ', ' . $property['city']); ?>
                        </p>
                        <div class="property-details">
                            <?php if ($property['bedrooms']): ?>
                            <span><i class="fas fa-bed"></i> <?php echo $property['bedrooms']; ?></span>
                            <?php endif; ?>
                            <?php if ($property['bathrooms']): ?>
                            <span><i class="fas fa-bath"></i> <?php echo $property['bathrooms']; ?></span>
                            <?php endif; ?>
                            <?php if ($property['size_sqft']): ?>
                            <span><i class="fas fa-ruler-combined"></i> <?php echo number_format($property['size_sqft']); ?> sqft</span>
                            <?php endif; ?>
                        </div>
                        <p class="price"><?php echo formatPrice($property['price']); ?></p>
                        <a href="property-details.php?id=<?php echo $property['property_id']; ?>" class="btn btn-primary">View Details</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="page-link">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="page-link active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>" class="page-link"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="page-link">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
