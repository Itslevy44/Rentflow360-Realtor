<?php
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

$property_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Update views count
$conn->query("UPDATE properties SET views_count = views_count + 1 WHERE property_id = $property_id");

// Track analytics
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $conn->query("INSERT INTO analytics (property_id, event_type, user_id) VALUES ($property_id, 'view', $user_id)");
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
    $conn->query("INSERT INTO analytics (property_id, event_type, ip_address) VALUES ($property_id, 'view', '$ip')");
}

// Get property details
$query = "SELECT p.*, u.full_name as agent_name, u.email as agent_email, u.phone as agent_phone 
         FROM properties p 
         LEFT JOIN users u ON p.agent_id = u.user_id
         WHERE p.property_id = $property_id AND p.status = 'approved'";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    header('Location: properties.php');
    exit();
}

$property = $result->fetch_assoc();

// Get photos
$photos_query = "SELECT * FROM property_photos WHERE property_id = $property_id ORDER BY display_order";
$photos = $conn->query($photos_query);

// Get amenities
$amenities_query = "SELECT a.* FROM property_amenities pa 
                   JOIN amenities a ON pa.amenity_id = a.amenity_id 
                   WHERE pa.property_id = $property_id";
$amenities = $conn->query($amenities_query);

// Check if favorited
$is_favorite = false;
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $fav_check = $conn->query("SELECT * FROM favorites WHERE user_id = $user_id AND property_id = $property_id");
    $is_favorite = $fav_check->num_rows > 0;
}

// Handle inquiry submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_inquiry'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $message = sanitize($_POST['message']);
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : 'NULL';
    
    $stmt = $conn->prepare("INSERT INTO inquiries (property_id, user_id, name, email, phone, message) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $property_id, $user_id, $name, $email, $phone, $message);
    
    if ($stmt->execute()) {
        $inquiry_success = "Your inquiry has been sent successfully!";
    }
}

// Handle favorite toggle
if (isset($_GET['action']) && $_GET['action'] === 'toggle_favorite' && isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    if ($is_favorite) {
        $conn->query("DELETE FROM favorites WHERE user_id = $user_id AND property_id = $property_id");
    } else {
        $conn->query("INSERT INTO favorites (user_id, property_id) VALUES ($user_id, $property_id)");
    }
    header("Location: property-details.php?id=$property_id");
    exit();
}

include 'includes/header.php';
?>

<main>
    <div class="property-details-container">
        <!-- Image Gallery -->
        <section class="property-gallery">
            <div class="main-image">
                <?php 
                $photos->data_seek(0);
                $first_photo = $photos->fetch_assoc();
                ?>
                <img id="mainImage" src="assets/images/uploads/<?php echo $first_photo['photo_url'] ?? 'placeholder.jpg'; ?>" alt="Property">
                <div class="watermark">Rentflow360</div>
            </div>
            <div class="thumbnail-gallery">
                <?php 
                $photos->data_seek(0);
                while ($photo = $photos->fetch_assoc()): 
                ?>
                <img src="assets/images/uploads/<?php echo $photo['photo_url']; ?>" 
                     alt="Property" 
                     onclick="changeMainImage(this.src)">
                <?php endwhile; ?>
            </div>
        </section>

        <div class="property-content">
            <!-- Property Info -->
            <section class="property-header">
                <div class="title-section">
                    <h1><?php echo htmlspecialchars($property['title']); ?></h1>
                    <p class="location">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($property['location'] . ', ' . $property['city']); ?>
                    </p>
                </div>
                <div class="price-section">
                    <h2 class="price"><?php echo formatPrice($property['price']); ?></h2>
                    <span class="listing-type"><?php echo ucfirst($property['listing_type']); ?></span>
                </div>
            </section>

            <!-- Key Details -->
            <section class="key-details">
                <div class="detail-item">
                    <i class="fas fa-home"></i>
                    <span><?php echo ucfirst($property['property_type']); ?></span>
                </div>
                <?php if ($property['bedrooms']): ?>
                <div class="detail-item">
                    <i class="fas fa-bed"></i>
                    <span><?php echo $property['bedrooms']; ?> Bedrooms</span>
                </div>
                <?php endif; ?>
                <?php if ($property['bathrooms']): ?>
                <div class="detail-item">
                    <i class="fas fa-bath"></i>
                    <span><?php echo $property['bathrooms']; ?> Bathrooms</span>
                </div>
                <?php endif; ?>
                <?php if ($property['size_sqft']): ?>
                <div class="detail-item">
                    <i class="fas fa-ruler-combined"></i>
                    <span><?php echo number_format($property['size_sqft']); ?> sqft</span>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <i class="fas fa-eye"></i>
                    <span><?php echo $property['views_count']; ?> Views</span>
                </div>
            </section>

            <!-- Action Buttons -->
            <section class="property-actions">
                <?php if (isLoggedIn()): ?>
                <a href="?id=<?php echo $property_id; ?>&action=toggle_favorite" class="btn <?php echo $is_favorite ? 'btn-danger' : 'btn-outline'; ?>">
                    <i class="fas fa-heart"></i> 
                    <?php echo $is_favorite ? 'Remove from Favorites' : 'Save to Favorites'; ?>
                </a>
                <?php endif; ?>
                <a href="#contact-agent" class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Contact Agent
                </a>
                <button onclick="shareProperty()" class="btn btn-outline">
                    <i class="fas fa-share"></i> Share
                </button>
            </section>

            <!-- Description -->
            <section class="property-description">
                <h2>Description</h2>
                <p><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
            </section>

            <!-- Amenities -->
            <?php if ($amenities->num_rows > 0): ?>
            <section class="property-amenities">
                <h2>Amenities</h2>
                <div class="amenities-grid">
                    <?php while ($amenity = $amenities->fetch_assoc()): ?>
                    <div class="amenity-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($amenity['amenity_name']); ?></span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Safety Tips -->
            <section class="safety-tips">
                <h2><i class="fas fa-shield-alt"></i> Safety Tips</h2>
                <ul>
                    <li>Always meet the agent/owner in person at the property</li>
                    <li>Never pay any money before viewing the property</li>
                    <li>Verify ownership documents before making payments</li>
                    <li>Use secure payment methods and get receipts</li>
                    <li>Report suspicious listings to admin</li>
                </ul>
            </section>

            <!-- Contact Agent -->
            <section class="contact-agent" id="contact-agent">
                <h2>Contact Agent</h2>
                <div class="agent-info">
                    <h3><?php echo htmlspecialchars($property['agent_name']); ?></h3>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($property['agent_email']); ?></p>
                    <?php if ($property['agent_phone']): ?>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($property['agent_phone']); ?></p>
                    <?php endif; ?>
                </div>

                <?php if (isset($inquiry_success)): ?>
                    <div class="alert success"><?php echo $inquiry_success; ?></div>
                <?php endif; ?>

                <form method="POST" class="inquiry-form">
                    <div class="form-group">
                        <label>Your Name *</label>
                        <input type="text" name="name" value="<?php echo isLoggedIn() ? $_SESSION['full_name'] : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?php echo isLoggedIn() ? $_SESSION['email'] : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Message *</label>
                        <textarea name="message" rows="5" required>I'm interested in this property. Please contact me.</textarea>
                    </div>
                    <button type="submit" name="send_inquiry" class="btn btn-primary">Send Inquiry</button>
                </form>
            </section>
        </div>
    </div>
</main>

<script>
function changeMainImage(src) {
    document.getElementById('mainImage').src = src;
}

function shareProperty() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo addslashes($property['title']); ?>',
            text: 'Check out this property on Rentflow360',
            url: window.location.href
        });
    } else {
        alert('Share this link: ' + window.location.href);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
