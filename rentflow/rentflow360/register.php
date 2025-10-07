<?php
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $role = sanitize($_POST['role']);
    
    // Validate
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "All fields are required";
    } else {
        // Check if email exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = "Email already registered";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $email, $phone, $password_hash, $role);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Registration successful! Please login.";
                header('Location: login.php');
                exit();
            } else {
                $error = "Registration failed";
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <h2>Register</h2>
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Register as</label>
                <select name="role" required>
                    <option value="user">Buyer/Renter</option>
                    <option value="agent">Agent/Seller</option>
                </select>
            </div>
            <button type="submit" class="btn">Register</button>
        </form>
        
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
