<?php
declare(strict_types=1);

// Ensure session is started if not already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is currently logged in.
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the logged-in user has the specific required role.
 * @param string $role The role to check against ('user', 'agent', 'admin').
 * @return bool
 */
function hasRole(string $role): bool {
    return isset($_SESSION['role']) && strtolower($_SESSION['role']) === strtolower($role);
}

/**
 * Ensures the user is logged in before accessing the page.
 * Redirects to the login page if not authenticated.
 * NOTE: Adjust path based on where this function is called (e.g., in a dashboard file).
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

/**
 * Ensures the logged-in user has the specific required role.
 * Redirects to the homepage if not authorized.
 * @param string $role The required role ('user', 'agent', 'admin').
 * NOTE: Adjust path based on where this function is called (e.g., in a dashboard file).
 */
function requireRole(string $role): void {
    if (!hasRole($role)) {
        header('Location: ../index.php?error=unauthorized');
        exit();
    }
}

/**
 * Cleans and sanitizes input data (e.g., from $_POST or $_GET).
 * @param string $data The input string to sanitize.
 * @return string The sanitized string.
 */
function sanitize(string $data): string {
    // Remove whitespace, strip HTML tags, and convert special characters to HTML entities
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Handles image file uploads.
 * @param array $file The $_FILES array entry for the uploaded file.
 * @param string $targetDir The directory to save the file (default is the uploads folder).
 * @return array An array with upload status and message/filename.
 */
function uploadImage(array $file, string $targetDir = 'assets/images/uploads/'): array {
    // Ensure the target directory exists and is writable
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, or GIF allowed.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB.'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    // Create a unique, time-based filename to prevent clashes
    $filename = uniqid() . '_' . time() . '.' . $extension;
    // NOTE: The $targetDir is relative to the calling script, so ensure the path is correct
    $targetPath = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'message' => 'Image upload failed due to server error.'];
}

/**
 * Formats a numeric price into a Kenyan Shilling string.
 * @param float|int|string $price The price value.
 * @return string The formatted price.
 */
function formatPrice($price): string {
    if (!is_numeric($price)) {
        return 'Price On Request';
    }
    // Format to 0 decimal places for cleaner, large property price display
    return 'KSh ' . number_format((float)$price, 0, '.', ',');
}

/**
 * Converts a timestamp into a 'time ago' friendly string.
 * @param string $timestamp A database timestamp string (e.g., 'YYYY-MM-DD HH:MM:SS').
 * @return string Time elapsed string.
 */
function timeAgo(string $timestamp): string {
    $time = strtotime($timestamp);
    if ($time === false) {
        return date('M d, Y'); // Return current date if timestamp is invalid
    }
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';

    return date('M d, Y', $time);
}
?>
