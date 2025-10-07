<?php
session_start();
// NOTE: Assuming db_connection.php handles the MySQL connection setup.
require_once '../includes/db_connection.php'; 
require_once '../includes/functions.php'; // Assuming this contains helper functions like requireLogin()

// Define the path for profile photos (relative to the current file)
$upload_dir = '../assets/images/profiles/';

// Ensure the user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$message = [];
$error = [];

// --- 1. Handle Form Submissions (POST Requests) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- A. Handle Profile Photo Upload ---
    if (isset($_POST['upload_photo'])) {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file_info = pathinfo($_FILES['profile_photo']['name']);
            $extension = strtolower($file_info['extension']);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($extension, $allowed_extensions)) {
                $error['photo'] = 'Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.';
            } else {
                // Generate a unique file name
                $new_file_name = $user_id . '_' . time() . '.' . $extension;
                $target_file = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                    
                    // 1. Get the old photo name to potentially delete it
                    $old_photo = null;
                    $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->bind_result($old_photo);
                    $stmt->fetch();
                    $stmt->close();
                    
                    // 2. Update the database
                    $update_stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE user_id = ?");
                    $update_stmt->bind_param("si", $new_file_name, $user_id);
                    
                    if ($update_stmt->execute()) {
                        $message['photo'] = 'Profile photo updated successfully!';
                        
                        // 3. Delete old file if it exists and is not the default
                        if ($old_photo && $old_photo !== $new_file_name && file_exists($upload_dir . $old_photo)) {
                            unlink($upload_dir . $old_photo);
                        }
                    } else {
                        $error['photo'] = 'Database update failed: ' . $update_stmt->error;
                        // Clean up the uploaded file if DB update fails
                        unlink($target_file); 
                    }
                    $update_stmt->close();

                } else {
                    $error['photo'] = 'Failed to upload file.';
                }
            }
        } else if (isset($_POST['upload_photo'])) {
            $error['photo'] = 'No file uploaded or an upload error occurred.';
        }
    }

    // --- B. Handle General Profile Update (Name and Phone) ---
    if (isset($_POST['update_profile'])) {
        $full_name = filter_var(trim($_POST['full_name'] ?? ''), FILTER_SANITIZE_STRING);
        $phone = filter_var(trim($_POST['phone'] ?? ''), FILTER_SANITIZE_STRING);

        if (empty($full_name)) {
            $error['profile'] = 'Full name cannot be empty.';
        } else {
            $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
            $update_stmt->bind_param("ssi", $full_name, $phone, $user_id);

            if ($update_stmt->execute()) {
                $message['profile'] = 'Profile details updated successfully!';
            } else {
                $error['profile'] = 'Failed to update profile details: ' . $update_stmt->error;
            }
            $update_stmt->close();
        }
    }

    // --- C. Handle Password Update ---
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // 1. Fetch current password hash
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error['password'] = 'All password fields are required.';
        } elseif (!password_verify($current_password, $user_data['password_hash'])) {
            $error['password'] = 'Current password is incorrect.';
        } elseif ($new_password !== $confirm_password) {
            $error['password'] = 'New password and confirmation do not match.';
        } elseif (strlen($new_password) < 8) {
            $error['password'] = 'New password must be at least 8 characters long.';
        } else {
            // Hash the new password and update
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $update_stmt->bind_param("si", $new_password_hash, $user_id);

            if ($update_stmt->execute()) {
                $message['password'] = 'Password updated successfully!';
            } else {
                $error['password'] = 'Failed to update password: ' . $update_stmt->error;
            }
            $update_stmt->close();
        }
    }
}

// --- 2. Fetch User Data (required after POST to show latest info) ---

$stmt = $conn->prepare("SELECT user_id, full_name, email, phone, role, profile_photo, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    // Should not happen if requireLogin is working, but safety first
    header('Location: ../login.php'); 
    exit();
}

// Determine profile photo URL
$profile_photo_path = !empty($user['profile_photo']) 
    ? $upload_dir . htmlspecialchars($user['profile_photo']) 
    : 'https://placehold.co/150x150/198754/FFFFFF?text=' . urlencode(substr($user['full_name'], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Rentflow360</title>
    <!-- Assuming external styles are linked -->
    <link rel="stylesheet" href="../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Embedded styles for Profile UI -->
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --text-color: #343a40;
            --bg-light: #f8f9fa;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
            --shadow-light: 0 8px 25px rgba(0, 0, 0, 0.08);
            --font-family: 'Inter', sans-serif;
        }
        body { font-family: var(--font-family); color: var(--text-color); background-color: var(--bg-light); }
        .dashboard-wrapper { display: flex; min-height: 100vh; }
        .dashboard-content { width: 100%; padding: 20px; flex-grow: 1; }
        .profile-container { max-width: 900px; margin: 0 auto; background-color: var(--card-bg); border-radius: 1rem; box-shadow: var(--shadow-light); padding: 2rem; }
        
        .profile-header { text-align: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; }
        .profile-photo { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 5px solid var(--primary-color); margin-bottom: 1rem; }
        .profile-header h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        .profile-header .role-tag { background-color: var(--success-color); color: white; padding: 0.25rem 0.75rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; }

        .settings-tabs { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 2rem; }
        .settings-tab { padding: 1rem 1.5rem; cursor: pointer; font-weight: 600; color: var(--secondary-color); border-bottom: 3px solid transparent; transition: all 0.2s; }
        .settings-tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        
        .settings-content { padding-top: 1rem; }
        .setting-section { margin-bottom: 2rem; padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 0.75rem; background-color: var(--bg-light); }

        /* Form Styling */
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: var(--text-color); }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; box-sizing: border-box; transition: border-color 0.2s; }
        .form-control:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1); }
        .btn-primary { background-color: var(--primary-color); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; transition: background-color 0.2s; font-weight: 600; }
        .btn-primary:hover { background-color: #0056b3; }
        
        /* Alert Messages */
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-weight: 500; }
        .alert-success { background-color: #d4edda; color: var(--success-color); border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: var(--danger-color); border: 1px solid #f5c6cb; }

        /* Photo Upload Styling */
        .photo-upload-area { display: flex; align-items: center; gap: 2rem; }
        .photo-preview-box { border: 2px dashed var(--border-color); padding: 1rem; border-radius: 1rem; }
        .photo-input-group { flex-grow: 1; }
        .file-input { display: block; width: 100%; padding: 0.5rem 0; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; // Assuming sidebar includes links for user dashboard sections ?>
        
        <div class="dashboard-content">
            <div class="profile-container">

                <!-- Profile Header -->
                <div class="profile-header">
                    <img src="<?php echo $profile_photo_path; ?>" 
                         alt="Profile Photo" 
                         class="profile-photo"
                         onerror="this.onerror=null;this.src='https://placehold.co/150x150/198754/FFFFFF?text=<?php echo urlencode(substr($user['full_name'], 0, 1)); ?>';">
                    <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <span class="role-tag"><?php echo strtoupper(htmlspecialchars($user['role'])); ?></span>
                    <p class="text-secondary mt-1">Member Since: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>

                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <div class="settings-tab active" data-target="general-info">General Info</div>
                    <div class="settings-tab" data-target="security">Security</div>
                    <div class="settings-tab" data-target="photo-settings">Profile Photo</div>
                </div>

                <!-- Settings Content -->
                <div class="settings-content">
                    
                    <!-- 1. General Info Tab -->
                    <div class="setting-section" id="general-info">
                        <h2>Personal Information</h2>

                        <?php if (isset($message['profile'])): ?>
                            <div class="alert alert-success"><?php echo $message['profile']; ?></div>
                        <?php endif; ?>
                        <?php if (isset($error['profile'])): ?>
                            <div class="alert alert-danger"><?php echo $error['profile']; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address (Cannot be changed)</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-control" disabled>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="form-control" placeholder="e.g., +2547XXXXXXXX">
                            </div>

                            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        </form>
                    </div>

                    <!-- 2. Security Tab (Password Change) -->
                    <div class="setting-section" id="security" style="display: none;">
                        <h2>Change Password</h2>

                        <?php if (isset($message['password'])): ?>
                            <div class="alert alert-success"><?php echo $message['password']; ?></div>
                        <?php endif; ?>
                        <?php if (isset($error['password'])): ?>
                            <div class="alert alert-danger"><?php echo $error['password']; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="update_password" value="1">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="new_password">New Password (Min 8 characters)</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            </div>

                            <button type="submit" class="btn-primary"><i class="fas fa-lock"></i> Change Password</button>
                        </form>
                    </div>

                    <!-- 3. Profile Photo Tab -->
                    <div class="setting-section" id="photo-settings" style="display: none;">
                        <h2>Manage Profile Photo</h2>
                        
                        <?php if (isset($message['photo'])): ?>
                            <div class="alert alert-success"><?php echo $message['photo']; ?></div>
                        <?php endif; ?>
                        <?php if (isset($error['photo'])): ?>
                            <div class="alert alert-danger"><?php echo $error['photo']; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="upload_photo" value="1">
                            
                            <div class="photo-upload-area">
                                <div class="photo-preview-box">
                                    <img src="<?php echo $profile_photo_path; ?>" 
                                         alt="Current Photo" 
                                         class="profile-photo"
                                         id="photoPreview"
                                         style="width: 100px; height: 100px; border-width: 2px;"
                                         onerror="this.onerror=null;this.src='https://placehold.co/100x100/198754/FFFFFF?text=<?php echo urlencode(substr($user['full_name'], 0, 1)); ?>';">
                                </div>
                                <div class="photo-input-group">
                                    <p>Upload a new image. Max file size: 2MB. (JPG, PNG, WEBP)</p>
                                    <input type="file" id="profile_photo_upload" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="file-input" required>
                                </div>
                            </div>
                            
                            <div style="margin-top: 1.5rem;">
                                <button type="submit" class="btn-primary"><i class="fas fa-upload"></i> Upload Photo</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.settings-tab');
            const sections = document.querySelectorAll('.setting-section');
            const photoInput = document.getElementById('profile_photo_upload');
            const photoPreview = document.getElementById('photoPreview');

            // --- Tab Switching Logic ---
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Deactivate all tabs and hide all sections
                    tabs.forEach(t => t.classList.remove('active'));
                    sections.forEach(s => s.style.display = 'none');

                    // Activate the clicked tab and show the target section
                    this.classList.add('active');
                    const target = this.getAttribute('data-target');
                    document.getElementById(target).style.display = 'block';
                });
            });

            // --- Photo Preview Logic ---
            if (photoInput) {
                photoInput.addEventListener('change', function(event) {
                    if (event.target.files.length > 0) {
                        const file = event.target.files[0];
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            photoPreview.src = e.target.result;
                            photoPreview.onerror = null; // Clear potential error handler
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>
</body>
</html>
