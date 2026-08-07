<?php
include("db.php");

$step        = 1;
$error       = "";
$success     = "";
$token_email = "";

// ── STEP 1: User submits email ──
if (isset($_POST['send_token'])) {
    $email = trim($_POST['email']);
    $stmt  = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) == 0) {
        $error = "No account found with this email.";
        $step  = 1;
    } else {
        mysqli_stmt_close($stmt);

        // Generate a 6-digit token
        $token   = strval(rand(100000, 999999));
        // Store expiry as a Unix timestamp (no timezone issues)
        $expires = time() + 600; // 10 minutes from now

        // Save token in DB
        $upd = mysqli_prepare($conn,
            "UPDATE users SET reset_token=?, reset_expires=? WHERE email=?");
        // Store expires as plain integer string
        $expires_str = strval($expires);
        mysqli_stmt_bind_param($upd, "sss", $token, $expires_str, $email);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        $success     = "Your reset token is: <strong style='font-size:1.3rem;letter-spacing:3px;'>{$token}</strong><br><small>(valid for 10 minutes — copy it now!)</small>";
        $step        = 2;
        $token_email = $email;
    }
}

// ── STEP 2: User submits token + new password ──
if (isset($_POST['reset_password'])) {
    $email       = trim($_POST['email']);
    $token       = trim($_POST['token']);
    $new         = $_POST['new_password'];
    $confirm     = $_POST['confirm_password'];
    $step        = 2;
    $token_email = $email;

    if (strlen($new) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($new !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Fetch token and expiry from DB
        $stmt = mysqli_prepare($conn,
            "SELECT reset_token, reset_expires FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row    = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$row) {
            $error = "Email not found.";
        } elseif ($row['reset_token'] !== $token) {
            $error = "Invalid token. Please check and try again.";
        } elseif (time() > intval($row['reset_expires'])) {
            $error = "Token has expired. Please request a new one.";
            $step  = 1;
        } else {
            // All good — update password
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $upd    = mysqli_prepare($conn,
                "UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE email=?");
            mysqli_stmt_bind_param($upd, "ss", $hashed, $email);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $success = "✅ Password changed successfully!";
            $step    = 1;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Forgot Password</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include("loading.php"); ?>
<div class="form-wrapper">
  <div style="text-align:center; margin-bottom:20px;">
    <img src="college-logo.jpg" alt="SRMU Logo"
         style="width:90px; height:90px; object-fit:contain;">
  </div>
  <h2>🔑 Forgot Password</h2>

  <?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
  <?php endif; ?>

  <?php if ($step == 1 && !$success): ?>
  <!-- Step 1: Enter email -->
  <form method="POST">
    <div class="form-group">
      <label>Enter your registered Email</label>
      <input type="email" name="email" required placeholder="you@example.com">
    </div>
    <button type="submit" name="send_token" class="form-submit">Get Reset Token</button>
  </form>
  <?php endif; ?>

  <?php if ($step == 1 && $success): ?>
    <p class="form-footer" style="margin-top:16px;">
      <a href="login.php">← Go to Login</a>
    </p>
  <?php endif; ?>

  <?php if ($step == 2): ?>
  <!-- Step 2: Enter token + new password -->
  <form method="POST">
    <input type="hidden" name="email" 
           value="<?php echo htmlspecialchars($token_email); ?>">
    <div class="form-group">
      <label>Reset Token (shown above in green box)</label>
      <input type="text" name="token" required 
             placeholder="Enter 6-digit token" maxlength="6">
    </div>
    <div class="form-group">
      <label>New Password</label>
      <input type="password" name="new_password" required minlength="6">
    </div>
    <div class="form-group">
      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" required>
    </div>
    <button type="submit" name="reset_password" class="form-submit">
      Reset Password
    </button>
  </form>
  <?php endif; ?>

  <p class="form-footer"><a href="login.php">← Back to Login</a></p>
</div>
</body>
</html>