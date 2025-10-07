<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('agent');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agent_id = $_SESSION['user_id'];
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $property_type = sanitize($_POST['property_type']);
    $listing_type = sanitize($_POST['listing_type']);
    $price = floatval($_POST['price']);
    $location = sanitize($_POST['location']);
    $city = sanitize($_POST['city']);
    $county = sanitize($_POST['county']);
    $bedrooms = intval($_POST['bedrooms']);
    $bathrooms = intval($_POST['bathrooms']);
    $size_sqft = floatval($_POST['size_sqft']);
    
    $stmt = $conn->prepare("INSERT INTO properties (agent_id, title, description, property_type, listing_type, price, location, city, county, bedrooms, bathrooms, size_sqft) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssdsssiid", $agent_id, $title, $description, $property_type, $listing_type, $price, $location, $city, $county, $bedrooms, $bathrooms, $size_sqft);
    
    if ($stmt->execute()) {
        $property_id = $conn->insert_id;
        
        // Handle photo uploads
        if (!empty($_FILES['photos']['name'][0])) {
            $upload_dir = '../assets/images/uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['photos']['error'][$key] === 0) {
                    $file = [
                        'name' => $_FILES['photos']['name'][$key],
                        'type' => $_FILES['photos']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'size' => $_FILES['photos']['size'][$key]
                    ];
                    
                    $upload = uploadImage($file);
                    if ($upload['success']) {
                        $is_primary = $key === 0 ? 1 : 0;
                        $conn->query("INSERT INTO property_photos (property_id, photo_url, is_primary, display_order) VALUES ($property_id, '{$upload['filename']}', $is_primary, $key)");
                    }
                }
            }
        }
        
        // Handle amenities
        if (!empty($_POST['amenities'])) {
            foreach ($_POST['amenities'] as $amenity_id) {
                $conn->query("INSERT INTO property_amenities (property_id, amenity_id) VALUES ($property_id, $amenity_id)");
            }
        }
        
        $success = "Property added successfully! Awaiting admin approval.";
    } else {
        $error = "Failed to add property";
    }
}

// Get amenities
$amenities = $conn->query("SELECT * FROM amenities ORDER BY amenity_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Property - Agent</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>Add New Property</h1>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="property-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Property Title *</label>
                        <input type="text" name="title" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="5" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Property Type *</label>
                        <select name="property_type" required>
                            <option value="">Select Type</option>
                            <option value="apartment">Apartment</option>
                            <option value="house">House</option>
                            <option value="commercial">Commercial</option>
                            <option value="land">Land</option>
                            <option value="office">Office</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Listing Type *</label>
                        <select name="listing_type" required>
                            <option value="">Select Type</option>
                            <option value="rent">For Rent</option>
                            <option value="sale">For Sale</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price (KSh) *</label>
                        <input type="number" name="price" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Size (sq ft)</label>
                        <input type="number" name="size_sqft" step="0.01">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Bedrooms</label>
                        <input type="number" name="bedrooms" min="0">
                    </div>
                    <div class="form-group">
                        <label>Bathrooms</label>
                        <input type="number" name="bathrooms" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Location *</label>
                        <input type="text" name="location" placeholder="e.g., Westlands, Kilimani" required>
                    </div>
                    <div class="form-group">
                        <label>City *</label>
                        <input type="text" name="city" placeholder="e.g., Nairobi" required>
                    </div>
                    <div class="form-group">
                        <label>County</label>
                        <input type="text" name="county" placeholder="e.g., Nairobi County">
                    </div>
                </div>

                <div class="form-group">
                    <label>Amenities</label>
                    <div class="checkbox-grid">
                        <?php while ($amenity = $amenities->fetch_assoc()): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="amenities[]" value="<?php echo $amenity['amenity_id']; ?>">
                            <?php echo htmlspecialchars($amenity['amenity_name']); ?>
                        </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Property Photos (Max 10)</label>
                    <input type="file" name="photos[]" multiple accept="image/*">
                    <small>First image will be the primary photo</small>
                </div>

                <button type="submit" class="btn btn-primary">Add Property</button>
            </form>
        </div>
    </div>
</body>
</html>
