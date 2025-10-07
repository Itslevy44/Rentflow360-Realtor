<?php
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
include 'includes/header.php';

// Get search parameters
$location = isset($_GET['location']) ? sanitize($_GET['location']) : '';
$property_type = isset($_GET['property_type']) ? sanitize($_GET['property_type']) : '';
$listing_type = isset($_GET['listing_type']) ? sanitize($_GET['listing_type']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$bedrooms = isset($_GET['bedrooms']) ? intval($_GET['bedrooms']) : 0;

// Build query
$where_clauses = ["p.status = 'approved'"];

if (!empty($location)) {
    // Handle messy input - search in location, city, and county
    $location_safe = $conn->real_escape_string($location);
    $where_clauses[] = "(p.location LIKE '%$location_safe%' OR p.city LIKE '%$location_safe%' OR p.county LIKE '%$location_safe%')";
}

if (!empty($property_type)) {
    $where_clauses[] = "p.property_type = '$property_type'";
}

if (!empty($listing_type)) {
    $where_clauses[] = "p.listing_type = '$listing_type'";
}

if ($min_price > 0) {
    $where_clauses[] = "p.price >= $min_price";
}

if ($max_price > 0) {
    $where_clauses[] = "p.price <= $max_price";
}

if ($bedrooms > 0) {
    $where_clauses[] = "p.bedrooms >= $bedrooms";
}

$where_sql = implode(' AND ', $where_clauses);

// Get results
$query = "SELECT p.*, pp.photo_url, u.full_name as agent_name 
         FROM properties p 
         LEFT JOIN property_photos pp ON p.property_id = pp.property_id AND pp.is_primary = 1
         LEFT JOIN users u ON p.agent_id = u.user_id
         WHERE $where_sql
         ORDER BY p.created_at DESC";
$result = $conn->query($query);
$total_results = $result->num_rows;
?>

<main>
    <section class="search-section">
        <div class="container">
            <h1>Search Properties</h1>

            <!-- Advanced Search Form -->
            <form method="GET" class="search-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" placeholder="Enter location, city, or county" value="<?php echo htmlspecialchars($location); ?>">
                    </div>
                    <div class="form-group">
                        <label>Property Type</label>
                        <select name="property_type">
                            <option value="">All Types</option>
                            <option value="apartment" <?php echo $property_type === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                            <option value="house" <?php echo $property_type === 'house' ? 'selected' : ''; ?>>House</option>
                            <option value="commercial" <?php echo $property_type === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                            <option value="land" <?php echo $property_type === 'land' ? 'selected' : ''; ?>>Land</option>
                            <option value="office" <?php echo $property_type === 'office' ? 'selected' : ''; ?>>Office</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Listing Type</label>
                        <select name="listing_type">
                            <option value="">All</option>
                            <option value="rent" <?php echo $listing_type === 'rent' ? 'selected' : ''; ?>>For Rent</option>
                            <option value="sale" <?php echo $listing_type === 'sale' ? 'selected' : ''; ?>>For Sale</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Min Price (KSh)</label>
                        <input type="number" name="min_price" placeholder="Min" value="<?php echo $min_price > 0 ? $min_price : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Max Price (KSh)</label>
                        <input type="number" name="max_price" placeholder="Max" value="<?php echo $max_price > 0 ? $max_price : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Bedrooms</label>
                        <select name="bedrooms">
                            <option value="0">Any</option>
                            <option value="1" <?php echo $bedrooms === 1 ? 'selected' : ''; ?>>1+</option>
                            <option value="2" <?php echo $bedrooms === 2 ? 'selected' : ''; ?>>2+</option>
                            <option value="3" <?php echo $bedrooms === 3 ? 'selected' : ''; ?>>3+</option>
                            <option value="4" <?php echo $bedrooms === 4 ? 'selected' : ''; ?>>4+</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="search.php" class="btn btn-outline">Clear</a>
                    </div>
                </div>
            </form>

            <!-- Results -->
            <div class="search-results">
                <h2>Search Results (<?php echo $total_results; ?> properties found)</h2>

                <?php if ($total_results > 0): ?>
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
                <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No properties found</h3>
                    <p>Try adjusting your search filters</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>