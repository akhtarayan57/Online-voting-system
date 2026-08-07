<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include("db.php");

$user_id = intval($_SESSION['user_id']);
$error   = "";
$success = "";

// Safety: get user flags first
$uflag = mysqli_prepare($conn, "SELECT vote, is_candidate, is_cr FROM users WHERE id = ?");
mysqli_stmt_bind_param($uflag, "i", $user_id);
mysqli_stmt_execute($uflag);
$urow = mysqli_fetch_assoc(mysqli_stmt_get_result($uflag));
mysqli_stmt_close($uflag);

// Block edit if voted or is a candidate or is a CR
if ($urow['vote'] == 1) {
    header("Location: dashboard.php?edit_error=voted"); exit();
}
if ($urow['is_candidate'] == 1) {
    header("Location: dashboard.php?edit_error=candidate"); exit();
}
if ($urow['is_cr'] == 1) {
    header("Location: dashboard.php?edit_error=cr"); exit();
}

// Fetch existing details
$stmt = mysqli_prepare($conn, "SELECT * FROM user_details WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$det = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$det) {
    header("Location: verify-form.php"); exit();
}

// Handle form submission
if (isset($_POST['update'])) {
    $full_name = trim($_POST['full_name']);
    $age       = intval($_POST['age']);
    $gender    = $_POST['gender'];
    $roll_no   = trim($_POST['roll_no']);
    $erp_id    = trim($_POST['erp_id']);
    $course    = trim($_POST['course']);
    $section   = trim($_POST['section']);

    if (!preg_match('/^\d{5}$/', $erp_id)) {
        $error = "ERP ID must be exactly 5 digits.";
    } elseif ($age < 17) {
        $error = "Age must be at least 17.";
    } else {
        $upload_dir = "uploads/";
        $user_photo = $det['user_photo']; // keep old by default

        // Optional: new selfie
        if (isset($_FILES['user_photo']) && $_FILES['user_photo']['error'] == 0) {
            $ext = pathinfo($_FILES['user_photo']['name'], PATHINFO_EXTENSION);
            if (!in_array(strtolower($ext), ['jpg','jpeg','png'])) {
                $error = "Only JPG/PNG allowed for selfie.";
            } else {
                $filename   = "user_" . $user_id . "_" . time() . "." . $ext;
                move_uploaded_file($_FILES['user_photo']['tmp_name'], $upload_dir . $filename);
                $user_photo = $filename;
            }
        }

        if (empty($error)) {
            // Update user_details (no college ID columns)
            $upd = mysqli_prepare($conn,
                "UPDATE user_details
                 SET full_name=?, age=?, gender=?, roll_no=?, erp_id=?,
                     course=?, section=?, user_photo=?
                 WHERE user_id=?");
            mysqli_stmt_bind_param($upd, "sissssssi",
                $full_name, $age, $gender, $roll_no, $erp_id,
                $course, $section, $user_photo, $user_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            // KEY: mark needs_reverify = 1 so admin knows to check this user
            // But keep verified = 1 so they can still access the site
            // They will NOT be able to vote until admin clears needs_reverify
            mysqli_query($conn,
                "UPDATE users SET needs_reverify = 1, verified = 0 WHERE id = $user_id");

            header("Location: dashboard.php?edit_success=1");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit Profile</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .verify-wrapper {
      max-width: 520px;
      margin: 40px auto;
      background: white;
      padding: 36px;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    .upload-box {
      border: 2px dashed #ddd;
      border-radius: 8px;
      padding: 16px;
      text-align: center;
      margin-top: 6px;
    }
    .upload-box:hover { border-color: #e94560; }
    select {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 1rem;
    }
    .section-title {
      font-weight: 700;
      color: #1a1a2e;
      margin: 20px 0 12px;
    }
    .step-badge {
      background: #1a1a2e;
      color: white;
      border-radius: 50%;
      width: 24px; height: 24px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      margin-right: 8px;
    }
    .current-photo {
      width: 80px; height: 80px;
      object-fit: cover;
      border-radius: 8px;
      border: 2px solid #ddd;
      margin-bottom: 8px;
    }
    .warning-box {
      background: #fef9e7;
      border: 1px solid #f9e79f;
      border-radius: 8px;
      padding: 12px 16px;
      color: #d4a017;
      font-size: 0.88rem;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
<?php include("loading.php"); ?>

<div class="verify-wrapper">
  <div style="text-align:center; margin-bottom:16px;">
    <img src="college-logo.jpg" alt="SRMU" style="width:70px; height:70px; object-fit:contain;">
  </div>
  <h2 style="text-align:center; margin-bottom:8px; color:#1a1a2e;">✏️ Edit Your Profile</h2>
  <p style="text-align:center; color:#777; margin-bottom:16px; font-size:0.9rem;">
    Update your details. After saving, admin will re-verify you before you can vote.
  </p>

  <div class="warning-box">
    ⚠️ <strong>Important:</strong> After editing, your account will be marked
    <strong>unverified</strong> until admin re-approves your updated details.
    You cannot vote until re-verified. Only edit if something is genuinely wrong.
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">

    <p class="section-title"><span class="step-badge">1</span>Personal Info</p>

    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="full_name" required
             value="<?php echo htmlspecialchars($det['full_name']); ?>">
    </div>
    <div class="form-group">
      <label>Age</label>
      <input type="number" name="age" min="17" max="40" required
             value="<?php echo intval($det['age']); ?>">
    </div>
    <div class="form-group">
      <label>Gender</label>
      <select name="gender" required>
        <option value="">Select Gender</option>
        <option value="Male"   <?php if($det['gender']=='Male')   echo 'selected'; ?>>Male</option>
        <option value="Female" <?php if($det['gender']=='Female') echo 'selected'; ?>>Female</option>
        <option value="Other"  <?php if($det['gender']=='Other')  echo 'selected'; ?>>Other</option>
      </select>
    </div>

    <p class="section-title"><span class="step-badge">2</span>Academic Info</p>

    <div class="form-group">
      <label>Roll Number</label>
      <input type="text" name="roll_no" required
             value="<?php echo htmlspecialchars($det['roll_no']); ?>">
    </div>
    <div class="form-group">
      <label>ERP ID (5 digits)</label>
      <input type="text" name="erp_id" required maxlength="5" pattern="\d{5}"
             value="<?php echo htmlspecialchars($det['erp_id']); ?>">
    </div>
    <div class="form-group">
      <label>Course</label>
      <select name="course" required>
        <option value="">Select Course</option>
        <?php
        $courses = ['BCA(DS+AI)','BCA(CS)','B.Tech(DS+AI)','B.Tech(CS)',
                    'BBA','MBA','MCA','B.Sc','B.Com','Other'];
        foreach ($courses as $c): ?>
        <option value="<?php echo $c; ?>"
          <?php if ($det['course'] == $c) echo 'selected'; ?>>
          <?php echo $c; ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Section</label>
      <select name="section" required>
        <option value="">Select Section</option>
        <?php foreach (['A','B','C','D'] as $s): ?>
        <option value="<?php echo $s; ?>"
          <?php if ($det['section'] == $s) echo 'selected'; ?>>
          <?php echo $s; ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <p class="section-title"><span class="step-badge">3</span>Selfie Photo
      <span style="font-weight:400; color:#aaa; font-size:0.85rem;">(leave blank to keep current)</span>
    </p>

    <div class="form-group">
      <?php if (!empty($det['user_photo'])): ?>
        <div style="margin-bottom:8px;">
          <p style="font-size:0.8rem; color:#777; margin-bottom:4px;">Current photo:</p>
          <img src="uploads/<?php echo htmlspecialchars($det['user_photo']); ?>"
               class="current-photo">
        </div>
      <?php endif; ?>
      <div class="upload-box">
        <img id="prev1" style="display:none; width:100%; max-height:180px; object-fit:cover; border-radius:8px; margin-bottom:8px;">
        <input type="file" name="user_photo" accept="image/*"
               onchange="previewFile(this,'prev1')" style="margin-top:4px;">
      </div>
    </div>

    <button type="submit" name="update" class="form-submit" style="margin-top:20px;">
      💾 Save Changes & Submit for Re-Verification
    </button>
  </form>

  <p class="form-footer" style="margin-top:16px;">
    <a href="dashboard.php">← Cancel & Go Back</a>
  </p>
</div>

<script>
function previewFile(input, previewId) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = document.getElementById(previewId);
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>