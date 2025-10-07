<?php
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ? AND status = 'active'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Update last login
            $conn->query("UPDATE users SET last_login = NOW() WHERE user_id = {$user['user_id']}");
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/index.php');
                    break;
                case 'agent':
                    header('Location: agent/dashboard.php');
                    break;
                default:
                    header('Location: user/dashboard.php');
            }
            exit();
        }
    }
    
    $error = "Invalid email or password";
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        
        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>