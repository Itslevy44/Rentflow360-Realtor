-- Rentflow360 Database Schema
-- Create Database
CREATE DATABASE IF NOT EXISTS rentflow360;
USE rentflow360;

-- Users Table (supports multiple roles)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('guest', 'user', 'agent', 'admin') DEFAULT 'user',
    profile_photo VARCHAR(255),
    status ENUM('active', 'suspended', 'pending') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- Properties/Listings Table
CREATE TABLE properties (
    property_id INT PRIMARY KEY AUTO_INCREMENT,
    agent_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    property_type ENUM('apartment', 'house', 'commercial', 'land', 'office') NOT NULL,
    listing_type ENUM('rent', 'sale') NOT NULL,
    price DECIMAL(15, 2) NOT NULL,
    location VARCHAR(100) NOT NULL,
    city VARCHAR(50) NOT NULL,
    county VARCHAR(50),
    address TEXT,
    bedrooms INT,
    bathrooms INT,
    size_sqft DECIMAL(10, 2),
    status ENUM('pending', 'approved', 'rejected', 'sold', 'rented') DEFAULT 'pending',
    is_featured BOOLEAN DEFAULT FALSE,
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_location (location),
    INDEX idx_city (city),
    INDEX idx_price (price),
    INDEX idx_status (status),
    INDEX idx_property_type (property_type),
    FULLTEXT idx_search (title, description, location)
);

-- Property Photos Table
CREATE TABLE property_photos (
    photo_id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    photo_url VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    INDEX idx_property (property_id)
);

-- Property Amenities Table
CREATE TABLE amenities (
    amenity_id INT PRIMARY KEY AUTO_INCREMENT,
    amenity_name VARCHAR(50) UNIQUE NOT NULL
);

-- Property-Amenities Junction Table
CREATE TABLE property_amenities (
    property_id INT,
    amenity_id INT,
    PRIMARY KEY (property_id, amenity_id),
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    FOREIGN KEY (amenity_id) REFERENCES amenities(amenity_id) ON DELETE CASCADE
);

-- Favorites/Saved Listings Table
CREATE TABLE favorites (
    favorite_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    property_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, property_id)
);

-- Alerts/Notifications Table
CREATE TABLE alerts (
    alert_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    alert_type ENUM('price_drop', 'new_listing', 'saved_search') NOT NULL,
    criteria JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Inquiries/Contact Messages Table
CREATE TABLE inquiries (
    inquiry_id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    message TEXT NOT NULL,
    status ENUM('new', 'replied', 'closed') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_property (property_id),
    INDEX idx_status (status)
);

-- Reviews/Ratings Table
CREATE TABLE reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (property_id, user_id)
);

-- Reports Table
CREATE TABLE reports (
    report_id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason ENUM('fake', 'inappropriate', 'scam', 'duplicate', 'other') NOT NULL,
    description TEXT,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_status (status)
);

-- Messages Table (for real-time chat)
CREATE TABLE messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    property_id INT,
    message_text TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE SET NULL,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id)
);

-- Blog Posts Table
CREATE TABLE blog_posts (
    post_id INT PRIMARY KEY AUTO_INCREMENT,
    author_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    featured_image VARCHAR(255),
    status ENUM('draft', 'published') DEFAULT 'draft',
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_slug (slug),
    INDEX idx_status (status)
);

-- Analytics Table
CREATE TABLE analytics (
    analytics_id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    event_type ENUM('view', 'inquiry', 'favorite', 'click') NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_property (property_id),
    INDEX idx_event (event_type),
    INDEX idx_date (created_at)
);

-- Insert Default Amenities
INSERT INTO amenities (amenity_name) VALUES
('WiFi'),
('Parking'),
('Swimming Pool'),
('Gym'),
('Security'),
('Garden'),
('Balcony'),
('Air Conditioning'),
('Furnished'),
('Pet Friendly'),
('Elevator'),
('Backup Generator'),
('Water Supply'),
('CCTV'),
('Playground');

-- Create Default Admin User (password: admin123 - should be changed)
INSERT INTO users (full_name, email, password_hash, role, status, email_verified) VALUES
('Admin User', 'admin@rentflow360.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', TRUE);