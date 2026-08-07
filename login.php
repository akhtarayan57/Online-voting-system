<?php
session_start();
include("db.php");
$error = "";
if (isset($_POST['email'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user   = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user']    = $user['email'];
        $_SESSION['user_id'] = $user['id'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid Email or Password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include("loading.php"); ?>
  <div class="form-wrapper">
    <div style="text-align:center; margin-bottom:20px;">
      <img src="college-logo.jpg" alt="SRMU Logo"
           style="width:90px; height:90px; object-fit:contain;">
    </div>
    <h2>👤 Student Login</h2>
    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo $error; ?></div>
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
      <button type="submit" class="form-submit">Login</button>
    </form>
    <p class="form-footer">No account? <a href="register.php">Register here</a></p>
<p class="form-footer" style="margin-top:8px;">
  <a href="forgot-password.php" style="color:#e94560; font-weight:600;">🔑 Forgot Password?</a>
</p>
    <p class="form-footer" style="margin-top:8px;">
      <a href="admin-login.php" style="color:#1a1a2e; font-weight:600;">🔐 Admin Login</a>
    </p>
  </div>
</body>
</html>