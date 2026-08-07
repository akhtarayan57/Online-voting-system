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

// Check if profile already submitted
$stmt = mysqli_prepare($conn, "SELECT id FROM user_details WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$already_submitted = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($already_submitted) {
    header("Location: dashboard.php");
    exit();
}

if (isset($_POST['submit'])) {
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
        $user_photo = "";

        // Upload selfie (required)
        if (isset($_FILES['user_photo']) && $_FILES['user_photo']['error'] == 0) {
            $ext = pathinfo($_FILES['user_photo']['name'], PATHINFO_EXTENSION);
            if (!in_array(strtolower($ext), ['jpg','jpeg','png'])) {
                $error = "Only JPG/PNG allowed for selfie.";
            } else {
                $filename = "user_" . $user_id . "_" . time() . "." . $ext;
                move_uploaded_file($_FILES['user_photo']['tmp_name'], $upload_dir . $filename);
                $user_photo = $filename;
            }
        } else {
            $error = "Please upload your selfie photo.";
        }

        if (empty($error)) {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO user_details
                 (user_id, full_name, age, gender, roll_no, erp_id,
                  user_photo, college_id_photo, college_id_back, course, section)
                 VALUES (?, ?, ?, ?, ?, ?, ?, '', '', ?, ?)");
            mysqli_stmt_bind_param($stmt, "isissssss",
                $user_id, $full_name, $age, $gender,
                $roll_no, $erp_id, $user_photo,
                $course, $section);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // NOT auto-verified. An admin reviews the submitted photo/details
            // and manually flips `verified` to 1 from the admin dashboard.
            $success = "✅ Profile submitted! An admin will review your details and photo. "
                     . "You'll be able to vote once your account is manually verified.";
            $profile_done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Complete Your Profile</title>
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
    .section-title {
      font-weight: 700;
      color: #1a1a2e;
      margin: 20px 0 12px;
    }
    .info-box {
      background: #eaf2fa;
      border: 1px solid #a9c9df;
      border-radius: 8px;
      padding: 12px 16px;
      color: #21618c;
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
  <h2 style="text-align:center; margin-bottom:8px; color:#1a1a2e;">🪪 Complete Your Profile</h2>
  <p style="text-align:center; color:#777; margin-bottom:16px; font-size:0.9rem;">
    Fill in your college details below.
  </p>

  <div class="info-box">
    ℹ️ After you submit, an admin will manually review your profile and photo.
    You'll be marked as verified — and able to vote — once that review is complete.
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
  <?php endif; ?>

  <?php if (!empty($success) && !empty($profile_done)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <p style="text-align:center; margin-top:16px;">
      <a href="dashboard.php" class="btn btn-primary">Go to Dashboard →</a>
    </p>
  <?php else: ?>

  <form method="POST" enctype="multipart/form-data">

    <p class="section-title"><span class="step-badge">1</span>Personal Info</p>

    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="full_name" required>
    </div>
    <div class="form-group">
      <label>Age</label>
      <input type="number" name="age" min="17" max="40" required>
    </div>
    <div class="form-group">
      <label>Gender</label>
      <select name="gender" required>
        <option value="">Select Gender</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        <option value="Other">Other</option>
      </select>
    </div>

    <p class="section-title"><span class="step-badge">2</span>College Info</p>

    <div class="form-group">
      <label>Roll Number</label>
      <input type="text" name="roll_no" required placeholder="Your college roll number">
    </div>
    <div class="form-group">
      <label>ERP ID (exactly 5 digits)</label>
      <input type="text" name="erp_id" maxlength="5" pattern="\d{5}"
             placeholder="e.g. 12345" required>
    </div>
    <div class="form-group">
      <label>Course</label>
      <select name="course" required>
        <option value="">Select Course</option>
        <option value="BCA(DS+AI)">BCA (DS+AI)</option>
        <option value="BCA(CS)">BCA (CS)</option>
        <option value="B.Tech(DS+AI)">B.Tech (DS+AI)</option>
        <option value="B.Tech(CS)">B.Tech (CS)</option>
        <option value="BBA">BBA</option>
        <option value="MBA">MBA</option>
        <option value="MCA">MCA</option>
        <option value="B.Sc">B.Sc</option>
        <option value="B.Com">B.Com</option>
        <option value="Other">Other</option>
      </select>
    </div>
    <div class="form-group">
      <label>Section</label>
      <select name="section" required>
        <option value="">Select Section</option>
        <option value="A">A</option>
        <option value="B">B</option>
        <option value="C">C</option>
        <option value="D">D</option>
      </select>
    </div>

    <p class="section-title"><span class="step-badge">3</span>Your Selfie Photo</p>
    <p style="color:#777; font-size:0.85rem; margin-bottom:10px;">
      This photo will appear on your candidate card if you become a CR candidate,
      so voters can identify you. Upload a clear face photo.
    </p>

    <div class="form-group">
      <div class="upload-box" style="padding:20px;">
        <video id="selfie_video" autoplay playsinline
               style="width:100%; max-height:220px; border-radius:8px; display:none; background:#000;"></video>
        <canvas id="selfie_canvas" style="display:none;"></canvas>
        <img id="prev1" style="display:none; width:100%; max-height:220px; object-fit:cover; border-radius:8px; margin-bottom:8px;">

        <div id="selfie_controls">
          <button type="button" onclick="startCamera()"
                  style="background:#1a1a2e; color:white; border:none; padding:10px 20px;
                         border-radius:8px; cursor:pointer; margin:6px;">
            📷 Open Camera
          </button>
          <button type="button" id="snap_btn" onclick="snapPhoto()"
                  style="background:#e94560; color:white; border:none; padding:10px 20px;
                         border-radius:8px; cursor:pointer; margin:6px; display:none;">
            📸 Take Photo
          </button>
          <button type="button" id="retake_btn" onclick="retakePhoto()"
                  style="background:#777; color:white; border:none; padding:10px 20px;
                         border-radius:8px; cursor:pointer; margin:6px; display:none;">
            🔄 Retake
          </button>
        </div>

        <p style="color:#aaa; font-size:0.8rem; margin-top:10px;">Or upload from gallery:</p>
        <input type="file" name="user_photo" id="user_photo_file" accept="image/*"
               onchange="previewUpload(this)" style="margin-top:4px;">
        <!-- hidden file input fed by camera snap -->
        <input type="file" name="user_photo_cam" id="user_photo_cam" accept="image/*" style="display:none;">
      </div>
    </div>

    <button type="submit" name="submit" class="form-submit" style="margin-top:20px;">
      ✅ Save Profile
    </button>
  </form>
  <?php endif; ?>
</div>

<script>
var stream = null;

function startCamera() {
  var video   = document.getElementById('selfie_video');
  var snapBtn = document.getElementById('snap_btn');
  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
    .then(function(s) {
      stream = s;
      video.srcObject = s;
      video.style.display = 'block';
      snapBtn.style.display = 'inline-block';
    })
    .catch(function(err) {
      alert('Camera error: ' + err.message + '\nPlease allow camera access or use gallery upload below.');
    });
}

function snapPhoto() {
  var video     = document.getElementById('selfie_video');
  var canvas    = document.getElementById('selfie_canvas');
  var preview   = document.getElementById('prev1');
  var snapBtn   = document.getElementById('snap_btn');
  var retakeBtn = document.getElementById('retake_btn');

  canvas.width  = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext('2d').drawImage(video, 0, 0);

  canvas.toBlob(function(blob) {
    var file = new File([blob], 'selfie.jpg', { type: 'image/jpeg' });
    var dt   = new DataTransfer();
    dt.items.add(file);
    document.getElementById('user_photo_cam').files = dt.files;
    document.getElementById('user_photo_file').files = dt.files;

    preview.src = canvas.toDataURL('image/jpeg');
    preview.style.display = 'block';
    if (stream) stream.getTracks().forEach(function(t) { t.stop(); });
    video.style.display   = 'none';
    snapBtn.style.display = 'none';
    retakeBtn.style.display = 'inline-block';
  }, 'image/jpeg', 0.9);
}

function retakePhoto() {
  document.getElementById('prev1').style.display = 'none';
  document.getElementById('retake_btn').style.display = 'none';
  startCamera();
}

function previewUpload(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = document.getElementById('prev1');
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>