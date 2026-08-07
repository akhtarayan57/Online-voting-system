<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit();
}

$file = basename(urldecode($_GET['file']));
$path = "uploads/" . $file;
?>
<!DOCTYPE html>
<html>
<head>
  <title>View Photo</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { background: #111; display: flex;
           flex-direction: column; align-items: center;
           min-height: 100vh; }
    .top-bar {
      width: 100%;
      background: #1a1a2e;
      padding: 12px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .top-bar span { color: white; font-family: sans-serif; }
    .close-btn {
      background: #e94560;
      color: white;
      border: none;
      padding: 8px 20px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1rem;
      text-decoration: none;
      font-family: sans-serif;
    }
    .close-btn:hover { background: #c73652; }
    img {
      max-width: 90%;
      max-height: 85vh;
      margin-top: 20px;
      border-radius: 8px;
      box-shadow: 0 4px 30px rgba(0,0,0,0.5);
    }
    .not-found {
      color: white;
      margin-top: 40px;
      font-family: sans-serif;
    }
  </style>
</head>
<body>
  <div class="top-bar">
    <span>📷 <?php echo htmlspecialchars($file); ?></span>
    <a href="admin-dashboard.php" class="close-btn">✖ Close & Go Back</a>
  </div>

  <?php if (file_exists($path)): ?>
    <img src="<?php echo htmlspecialchars($path); ?>" alt="Photo">
  <?php else: ?>
    <p class="not-found">⚠️ Photo not found: <?php echo htmlspecialchars($file); ?></p>
  <?php endif; ?>
</body>
</html>