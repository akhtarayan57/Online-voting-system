<?php
include("db.php");

$error   = "";
$success = "";

// ── User submits registration form ──
if (isset($_POST['register'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match. Please re-enter.";
    } else {
        // Check email not already registered
        $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($chk, "s", $email);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        $exists = mysqli_stmt_num_rows($chk) > 0;
        mysqli_stmt_close($chk);

        if ($exists) {
            $error = "This email is already registered. Please login.";
        } else {
            // Insert user directly — verified = 0.
            // Email will be verified later, when the student logs in
            // and fills out their profile in verify-form.php.
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn,
                "INSERT INTO users (name, email, phone, password, verified) VALUES (?, ?, ?, ?, 0)");
            mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $phone, $hashed);

            if (mysqli_stmt_execute($stmt)) {
                $success = "✅ Registration successful! You can now login. "
                         . "You'll verify your email when you complete your profile.";
            } else {
                $error = "Registration failed: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Register - SRMU Voting</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .password-wrap { position: relative; }
    .password-wrap input { padding-right: 44px; }
    .eye-btn {
      position: absolute;
      right: 12px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      cursor: pointer; font-size: 1.1rem; color: #aaa;
    }
  </style>
</head>
<body>
  <?php include("loading.php"); ?>
  <div class="form-wrapper">
    <div style="text-align:center; margin-bottom:20px;">
      <img src="college-logo.jpg" alt="SRMU Logo"
           style="width:90px; height:90px; object-fit:contain;">
    </div>

    <h2>📝 Student Registration</h2>

    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo $success; ?></div>
      <p class="form-footer"><a href="login.php">← Go to Login</a></p>
    <?php else: ?>

    <form method="POST">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required>
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" required maxlength="10" pattern="\d{10}"
               placeholder="10-digit mobile number">
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="password-wrap">
          <input type="password" name="password" id="pass1" required minlength="6"
                 placeholder="At least 6 characters">
          <button type="button" class="eye-btn" onclick="togglePass('pass1',this)">👁</button>
        </div>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <div class="password-wrap">
          <input type="password" name="confirm_password" id="pass2" required minlength="6"
                 placeholder="Re-enter your password">
          <button type="button" class="eye-btn" onclick="togglePass('pass2',this)">👁</button>
        </div>
      </div>
      <button type="submit" name="register" class="form-submit">
        Register →
      </button>
    </form>

    <?php endif; ?>
    <p class="form-footer">Already have an account? <a href="login.php">Login here</a></p>
  </div>

<script>
function togglePass(id, btn) {
  var inp = document.getElementById(id);
  if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
  else { inp.type = 'password'; btn.textContent = '👁'; }
}
</script>
</body>
</html>