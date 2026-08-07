<?php
session_start();
include("db.php");

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Redirect to verify-form if not filled yet
$stmt = mysqli_prepare($conn, "SELECT id FROM user_details WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$has_profile = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if (!$has_profile) {
    header("Location: verify-form.php");
    exit();
}

// Get user info
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Get user details
$stmt = mysqli_prepare($conn, "SELECT * FROM user_details WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$details = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$my_course  = $details['course']  ?? '';
$my_section = $details['section'] ?? '';

$already_voted = ($user['vote'] == 1);
$is_verified   = ($user['verified'] == 1);
$is_candidate  = ($user['is_candidate'] == 1);
$is_cr         = ($user['is_cr'] == 1);
$needs_reverify= ($user['needs_reverify'] ?? 0) == 1;

// Get CR voting status
$cr_st_q   = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='cr_voting_status'");
$cr_status  = mysqli_fetch_assoc($cr_st_q)['setting_value'] ?? 'pending';

$cr_et_q   = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='cr_voting_end_time'");
$cr_end    = mysqli_fetch_assoc($cr_et_q)['setting_value'] ?? null;
$cr_ended  = $cr_end && strtotime($cr_end) < time();

// Get IR voting status
$ir_st_q   = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='ir_voting_status'");
$ir_status  = mysqli_fetch_assoc($ir_st_q)['setting_value'] ?? 'pending';

// Get section winner (if ended)
$section_winner = null;
if ($cr_ended || $cr_status == 'ended') {
    if ($my_course && $my_section) {
        $wstmt = mysqli_prepare($conn,
            "SELECT c.name, c.votes FROM candidates c
             WHERE c.course = ? AND c.section = ?
             ORDER BY c.votes DESC LIMIT 1");
        mysqli_stmt_bind_param($wstmt, "ss", $my_course, $my_section);
        mysqli_stmt_execute($wstmt);
        $section_winner = mysqli_fetch_assoc(mysqli_stmt_get_result($wstmt));
        mysqli_stmt_close($wstmt);
    }
}

$success = "";
$error   = "";

if (isset($_GET['success']) && $_GET['success'] == 'voted') $success = "✅ Your vote has been cast!";
if (isset($_GET['edit_success'])) $success = "✅ Profile updated! Awaiting admin re-verification before you can vote.";
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'already_voted')  $error = "⚠️ You have already voted!";
    elseif ($_GET['error'] == 'failed')     $error = "❌ Vote failed. Try again.";
    elseif ($_GET['error'] == 'not_verified') $error = "⚠️ You must be verified by admin before voting!";
    elseif ($_GET['error'] == 'voting_closed') $error = "⚠️ CR Voting is not open right now.";
}
if (isset($_GET['edit_error'])) {
    if ($_GET['edit_error'] == 'voted')     $error = "⚠️ You cannot edit your profile after voting.";
    elseif ($_GET['edit_error'] == 'candidate') $error = "⚠️ You cannot edit your profile while you are a CR candidate.";
    elseif ($_GET['edit_error'] == 'cr')    $error = "⚠️ You cannot edit your profile as an elected CR.";
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .dashboard-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      max-width: 1000px;
      margin: 30px auto;
      padding: 0 20px;
    }
    .dash-card {
      background: white;
      border-radius: 12px;
      padding: 28px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }
    .dash-card h3 {
      color: #1a1a2e;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #e94560;
    }
    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid #f0f0f0;
      font-size: 0.9rem;
    }
    .info-label { color: #777; }
    .info-value { font-weight: 600; color: #1a1a2e; }
    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 16px;
    }
    .badge-green  { background: #eafaf1; color: #1e8449; }
    .badge-orange { background: #fef9e7; color: #d4a017; }
    .badge-blue   { background: #eaf3fb; color: #1a5276; }
    .badge-red    { background: #fdecea; color: #c0392b; }

    /* ── Candidate card with photo ── */
    .candidates-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      margin-top: 16px;
    }
    .vote-card {
      background: #f9f9f9;
      border: 2px solid #ddd;
      border-radius: 12px;
      padding: 16px;
      text-align: center;
      transition: all 0.2s;
      min-width: 150px;
      max-width: 180px;
      flex: 1;
    }
    .vote-card:hover { border-color: #e94560; background: #fff5f5; }
    .vote-card img {
      width: 90px; height: 90px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #e94560;
      margin-bottom: 10px;
      display: block;
      margin-left: auto;
      margin-right: auto;
    }
    .vote-card .no-photo {
      width: 90px; height: 90px;
      border-radius: 50%;
      background: #ddd;
      display: flex; align-items: center; justify-content: center;
      font-size: 2rem;
      margin: 0 auto 10px;
      border: 3px solid #ccc;
    }
    .vote-card h4 { color: #1a1a2e; margin-bottom: 4px; font-size: 0.95rem; }
    .vote-card p  { color: #777; font-size: 0.8rem; margin-bottom: 12px; }

    @media (max-width: 700px) {
      .dashboard-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<?php include("loading.php"); ?>

<div class="top-bar">
  <img src="college-banner.png" alt="SRMU"
       style="height:45px; object-fit:contain; background:white; padding:4px 10px; border-radius:6px;">
  <h2>🗳️ Student Dashboard</h2>
  <div class="top-bar-links">
    <a href="result.php">📊 Results</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<?php if ($success): ?>
  <div style="max-width:1000px; margin:16px auto 0; padding:0 20px;">
    <div class="alert alert-success"><?php echo $success; ?></div>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div style="max-width:1000px; margin:16px auto 0; padding:0 20px;">
    <div class="alert alert-error"><?php echo $error; ?></div>
  </div>
<?php endif; ?>

<!-- Re-verify notice banner -->
<?php if ($needs_reverify && !$is_verified): ?>
<div style="max-width:1000px; margin:16px auto 0; padding:0 20px;">
  <div style="background:#fef9e7; border:1px solid #f9e79f; border-radius:10px;
              padding:14px 20px; color:#d4a017; font-weight:600;">
    ⏳ Your profile was edited and is waiting for admin re-verification.
    You will be able to vote once admin approves your updated details.
  </div>
</div>
<?php endif; ?>

<div class="dashboard-grid">

  <!-- Profile Card -->
  <div class="dash-card">
    <h3 style="display:flex; justify-content:space-between; align-items:center;
               border-bottom:none; margin-bottom:0; padding-bottom:0;">
      <span>👤 Your Profile</span>
      <?php if ($already_voted || $is_candidate || $is_cr): ?>
        <span title="Cannot edit now"
          style="background:#ccc; color:#fff; padding:6px 14px; border-radius:8px;
                 font-size:0.78rem; font-weight:600; cursor:not-allowed;">
          ✏️ Edit Details
        </span>
      <?php else: ?>
        <a href="edit-profile.php"
           style="background:#0f3460; color:#fff; padding:6px 14px; border-radius:8px;
                  font-size:0.78rem; font-weight:600; text-decoration:none;"
           onmouseover="this.style.background='#e94560'"
           onmouseout="this.style.background='#0f3460'">
          ✏️ Edit Details
        </a>
      <?php endif; ?>
    </h3>
    <div style="border-bottom:2px solid #e94560; margin:10px 0 20px;"></div>

    <!-- Selfie photo on dashboard -->
    <?php if (!empty($details['user_photo'])): ?>
    <div style="text-align:center; margin-bottom:16px;">
      <img src="uploads/<?php echo htmlspecialchars($details['user_photo']); ?>"
           style="width:80px; height:80px; border-radius:50%; object-fit:cover;
                  border:3px solid #e94560;">
    </div>
    <?php endif; ?>

    <?php if ($is_verified && !$needs_reverify): ?>
      <span class="status-badge badge-green">✅ Verified</span>
    <?php elseif ($needs_reverify): ?>
      <span class="status-badge badge-orange">⏳ Awaiting Re-Verification</span>
    <?php else: ?>
      <span class="status-badge badge-orange">⏳ Awaiting Verification</span>
    <?php endif; ?>

    <?php if ($is_candidate): ?>
      <span class="status-badge badge-blue" style="margin-left:6px;">🎯 CR Candidate</span>
    <?php endif; ?>
    <?php if ($is_cr): ?>
      <span class="status-badge badge-green" style="margin-left:6px;">🏆 Elected CR</span>
    <?php endif; ?>

    <div class="info-row">
      <span class="info-label">Name</span>
      <span class="info-value"><?php echo htmlspecialchars($user['name']); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Email</span>
      <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
    </div>
    <?php if ($details): ?>
    <div class="info-row">
      <span class="info-label">Course</span>
      <span class="info-value"><?php echo htmlspecialchars($my_course); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Section</span>
      <span class="info-value"><?php echo htmlspecialchars($my_section); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Roll No</span>
      <span class="info-value"><?php echo htmlspecialchars($details['roll_no']); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">ERP ID</span>
      <span class="info-value"><?php echo htmlspecialchars($details['erp_id']); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Vote Status</span>
      <span class="info-value">
        <?php echo $already_voted ? "✅ Voted" : "— Not Voted Yet"; ?>
      </span>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- === CR VOTING SECTION === -->
<div style="max-width:1000px; margin:0 auto 24px; padding:0 20px;">

  <?php if ($cr_status == 'ended' || $cr_ended): ?>
    <?php if ($section_winner && $section_winner['votes'] > 0): ?>
    <div style="background:linear-gradient(135deg,#f39c12,#e67e22); color:white;
                border-radius:12px; padding:24px; text-align:center;">
      <h3 style="margin-bottom:8px;">🏆 CR Election Result — Your Section</h3>
      <p style="font-size:1.3rem; font-weight:700;"><?php echo htmlspecialchars($section_winner['name']); ?></p>
      <p style="margin-top:6px; opacity:0.9;">
        is your Class Representative for
        <?php echo htmlspecialchars($my_course); ?> — Section <?php echo htmlspecialchars($my_section); ?>
      </p>
    </div>
    <?php else: ?>
    <div class="dash-card" style="text-align:center; color:#777;">
      <p>CR election has ended. No candidates were in your section.</p>
    </div>
    <?php endif; ?>

  <?php elseif ($cr_status == 'active' && !$cr_ended): ?>

    <div class="dash-card">
      <h3>🗳️ Vote for CR —
        <?php echo htmlspecialchars($my_course); ?> Section <?php echo htmlspecialchars($my_section); ?>
      </h3>

      <?php if ($cr_end): ?>
        <p style="color:#e94560; font-weight:600; margin-bottom:16px;">
          ⏱ Voting ends: <?php echo $cr_end; ?>
        </p>
      <?php endif; ?>

      <?php if (!$is_verified || $needs_reverify): ?>
        <div class="alert alert-error">
          ⚠️ Your account is not verified yet. 
          <?php echo $needs_reverify
            ? "You edited your profile — wait for admin to re-verify you."
            : "Admin will verify your account soon."; ?>
        </div>

      <?php elseif ($already_voted): ?>
        <div class="alert alert-success">✅ You have already cast your vote! Thank you.</div>

      <?php else: ?>

        <?php
        // Get candidates with their selfie photos
        $cand_stmt = mysqli_prepare($conn,
            "SELECT c.id, c.user_id, u.name as uname, ud.user_photo
             FROM candidates c
             JOIN users u ON c.user_id = u.id
             LEFT JOIN user_details ud ON c.user_id = ud.user_id
             WHERE c.course = ? AND c.section = ?");
        mysqli_stmt_bind_param($cand_stmt, "ss", $my_course, $my_section);
        mysqli_stmt_execute($cand_stmt);
        $candidates = mysqli_stmt_get_result($cand_stmt);
        mysqli_stmt_close($cand_stmt);
        $cand_count = mysqli_num_rows($candidates);
        ?>

        <?php if ($cand_count == 0): ?>
          <div class="alert alert-error">
            ℹ️ No candidates have been assigned for your section yet. Check back later.
          </div>
        <?php else: ?>
          <p style="color:#777; font-size:0.88rem; margin-bottom:8px;">
            Click on a candidate to vote. Photos are shown so you can identify them.
          </p>
          <div class="candidates-grid">
            <?php while ($c = mysqli_fetch_assoc($candidates)): ?>
            <div class="vote-card">
              <?php if (!empty($c['user_photo'])): ?>
                <img src="uploads/<?php echo htmlspecialchars($c['user_photo']); ?>"
                     alt="<?php echo htmlspecialchars($c['uname']); ?>">
              <?php else: ?>
                <div class="no-photo">👤</div>
              <?php endif; ?>
              <h4><?php echo htmlspecialchars($c['uname']); ?></h4>
              <p><?php echo htmlspecialchars($my_course); ?> — Sec <?php echo htmlspecialchars($my_section); ?></p>
              <form method="POST" action="vote.php">
                <input type="hidden" name="candidate_id" value="<?php echo $c['id']; ?>">
                <button type="submit" class="btn btn-primary" style="width:100%;">Vote</button>
              </form>
            </div>
            <?php endwhile; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>

  <?php elseif ($cr_status == 'pending'): ?>
    <div class="dash-card" style="text-align:center;">
      <h3>⏳ CR Election Not Started Yet</h3>
      <p style="color:#777; margin-top:8px;">
        Admin will open CR voting soon. Make sure your profile is complete.
      </p>
    </div>
  <?php endif; ?>
</div>

<!-- === IR VOTING BANNER === -->
<?php if ($ir_status == 'active'): ?>
<div style="max-width:1000px; margin:0 auto 24px; padding:0 20px;">
  <div style="background:linear-gradient(135deg,#8e44ad,#2c3e50); border-radius:12px; padding:20px 28px;
              display:flex; align-items:center; justify-content:space-between;">
    <div>
      <p style="color:white; font-weight:600; font-size:1rem; margin-bottom:4px;">🏛️ IR Election is LIVE!</p>
      <p style="color:#ddd; font-size:0.85rem;">Vote for the Institute Representative now!</p>
    </div>
    <a href="ir-vote.php"
       style="background:white; color:#8e44ad; padding:10px 20px; border-radius:8px;
              text-decoration:none; font-weight:600; white-space:nowrap; margin-left:20px;">
      Vote for IR →
    </a>
  </div>
</div>
<?php endif; ?>

<!-- === EVENT VOTING BANNER === -->
<div style="max-width:1000px; margin:0 auto 24px; padding:0 20px;">
  <div style="background:linear-gradient(135deg,#8e44ad,#3498db); border-radius:12px; padding:20px 28px;
              display:flex; align-items:center; justify-content:space-between;">
    <div>
      <p style="color:white; font-weight:600; font-size:1rem; margin-bottom:4px;">🎉 Ullaash Fest Voting</p>
      <p style="color:#ddd; font-size:0.85rem;">Vote for Fashion Walk, Dance, Singing &amp; more!</p>
    </div>
    <a href="event-vote.php"
       style="background:white; color:#8e44ad; padding:10px 20px; border-radius:8px;
              text-decoration:none; font-weight:600; white-space:nowrap; margin-left:20px;">
      Vote in Events →
    </a>
  </div>
</div>

<!-- Feedback Banner -->
<div style="max-width:1000px; margin:0 auto 40px; padding:0 20px;">
  <div style="background:linear-gradient(135deg,#1a1a2e,#0f3460); border-radius:12px; padding:20px 28px;
              display:flex; align-items:center; justify-content:space-between;">
    <div>
      <p style="color:white; font-weight:600; font-size:1rem; margin-bottom:4px;">📝 Share Your Feedback</p>
      <p style="color:#aaa; font-size:0.85rem;">Help us improve — fill out a quick feedback form</p>
    </div>
    <a href="https://docs.google.com/forms/d/e/1FAIpQLSfB2NIES2d2xMuaHO6Sfu9hHss64T5C7l8me2tzUxgKDlZbAw/viewform"
       target="_blank"
       style="background:#e94560; color:white; padding:10px 20px; border-radius:8px;
              text-decoration:none; font-weight:600; white-space:nowrap; margin-left:20px;">
      Fill Form →
    </a>
  </div>
</div>

</body>
</html>