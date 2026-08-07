<?php
session_start();

// Hardcoded admin credentials
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin@123');

$error = "";

if (isset($_POST['username'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        header("Location: admin-dashboard.php");
        exit();
    } else {
        $error = "Invalid admin credentials";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include("loading.php"); ?>
  <div class="form-wrapper">
  <div style="text-align:center; margin-bottom:20px;">
    <img src="college-logo.jpg" alt="SRMU Logo"
         style="width:100px; height:100px; object-fit:contain;">
  </div>
  <h2>🔐 Admin Login</h2>

    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="form-submit">Login as Admin</button>
    </form>

    <p class="form-footer"><a href="login.php">← Back to User Login</a></p>
  </div>
</body>
</html>