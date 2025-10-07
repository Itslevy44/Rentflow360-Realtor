<?php
// index.php

require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Set a page title for the header
$page_title = "Find Your Dream Property in Kenya";

// --- Simulate Database Connection for the Trending Section ---
// NOTE: Assuming $pdo (PDO object) is available from db_connection.php
// If using MySQLi procedural, replace $pdo->prepare/$stmt->execute/$stmt->fetchAll with mysqli_query/mysqli_fetch_assoc
if (isset($pdo)) {
    try {
        $query = "SELECT p.property_id, p.title, p.price, p.location, p.bedrooms, p.bathrooms, p.size_sqft, 
                         pp.photo_url
                  FROM properties p 
                  LEFT JOIN property_photos pp ON p.property_id = pp.property_id AND pp.is_primary = 1
                  WHERE p.status = 'approved' AND p.is_featured = 1
                  ORDER BY p.views_count DESC 
                  LIMIT 6";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $trending_properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log error, but show default empty array to user
        error_log("Trending Properties Query Error: " . $e->getMessage());
        $trending_properties = []; 
    }
} else {
    // Fallback if DB connection fails
    $trending_properties = [];
}
// --- End DB Simulation ---

include 'includes/header.php'; // Includes the styled header
?>

<main>
    <!-- Hero Section with Background Image and Search -->
    <section class="hero-section">
        <div class="hero-overlay">
            <div class="hero-content">
                <h1 class="animate-in">Find Your Dream Property in Kenya</h1>
                <p class="animate-in delay-1">Discover the perfect home, apartment, or commercial space across Nairobi, Mombasa, and beyond.</p>
                
                <!-- Quick Search Bar (Prominently styled) -->
                <div class="quick-search animate-in delay-2">
                    <form action="properties.php" method="GET" class="search-form">
                        <div class="input-group">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" name="location" placeholder="Enter city, neighborhood, or keyword..." required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-building"></i>
                            <select name="property_type">
                                <option value="">Property Type</option>
                                <option value="apartment">Apartment</option>
                                <option value="house">House</option>
                                <option value="commercial">Commercial</option>
                                <option value="land">Land</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary-search">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </form>
                    <!-- Link to Advanced Search -->
                    <a href="search.php" class="advanced-search-link">
                        <i class="fas fa-sliders-h"></i> Advanced Filters
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Trending Properties Section -->
    <section class="section trending-properties">
        <div class="section-header">
            <h2>Featured & Trending Properties</h2>
            <p>Hand-picked listings generating the most interest this week.</p>
        </div>

        <div class="property-grid">
            <?php if (count($trending_properties) > 0): ?>
                <?php foreach ($trending_properties as $property): ?>
                    <div class="property-card">
                        <a href="property-details.php?id=<?php echo $property['property_id']; ?>">
                            <div class="card-image-wrapper">
                                <img src="assets/images/uploads/<?php echo $property['photo_url'] ?? 'placeholder.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($property['title']); ?>" 
                                     onerror="this.onerror=null; this.src='https://placehold.co/600x400/007bff/ffffff?text=No+Image';">
                                <span class="card-tag tag-featured"><i class="fas fa-star"></i> Featured</span>
                                <span class="card-price"><?php echo formatPrice($property['price']); ?></span>
                            </div>
                            <div class="card-body">
                                <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                                <p class="card-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($property['location']); ?></p>
                                <div class="card-specs">
                                    <span><i class="fas fa-bed"></i> <?php echo $property['bedrooms']; ?> Bed</span>
                                    <span><i class="fas fa-bath"></i> <?php echo $property['bathrooms']; ?> Bath</span>
                                    <span><i class="fas fa-ruler-combined"></i> <?php echo number_format($property['size_sqft']); ?> Sqft</span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p><i class="fas fa-building"></i> No trending properties available right now.</p>
                   
                </div>
            <?php endif; ?>
        </div>
    
    </section>
</main>

<?php include 'includes/footer.php'; ?>
