<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit();
}
include("db.php");

$success = "";
$error   = "";

// ── VERIFY USER ──
if (isset($_POST['verify_user'])) {
    $uid = intval($_POST['user_id']);
    mysqli_query($conn, "UPDATE users SET verified = 1, needs_reverify = 0 WHERE id = $uid");
    $success = "User verified!";
}

// ── UNVERIFY USER ──
if (isset($_POST['unverify_user'])) {
    $uid = intval($_POST['user_id']);
    mysqli_query($conn, "UPDATE users SET verified = 0 WHERE id = $uid");
    $success = "User unverified.";
}

// ── MAKE CR CANDIDATE ──
if (isset($_POST['make_candidate'])) {
    $uid = intval($_POST['user_id']);
    $stmt = mysqli_prepare($conn,
        "SELECT u.name, ud.course, ud.section
         FROM users u JOIN user_details ud ON u.id = ud.user_id
         WHERE u.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row && $row['course'] && $row['section']) {
        $chk = mysqli_prepare($conn, "SELECT id FROM candidates WHERE user_id = ?");
        mysqli_stmt_bind_param($chk, "i", $uid);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        $already = mysqli_stmt_num_rows($chk) > 0;
        mysqli_stmt_close($chk);

        if (!$already) {
            $ins = mysqli_prepare($conn,
                "INSERT INTO candidates (user_id, name, course, section, votes, pending_votes)
                 VALUES (?, ?, ?, ?, 0, 0)");
            mysqli_stmt_bind_param($ins, "isss", $uid, $row['name'], $row['course'], $row['section']);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
            mysqli_query($conn, "UPDATE users SET is_candidate = 1 WHERE id = $uid");
            $success = "'{$row['name']}' added as CR candidate for {$row['course']} Sec {$row['section']}!";
        } else {
            $error = "This user is already a candidate.";
        }
    } else {
        $error = "User has not filled their profile yet.";
    }
}

// ── REMOVE CR CANDIDATE ──
if (isset($_POST['remove_candidate'])) {
    $cid = intval($_POST['candidate_id']);
    $stmt = mysqli_prepare($conn, "SELECT user_id FROM candidates WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $cid);
    mysqli_stmt_execute($stmt);
    $crow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($crow) {
        mysqli_query($conn, "UPDATE users SET is_candidate = 0 WHERE id = {$crow['user_id']}");
        $stmt2 = mysqli_prepare($conn, "DELETE FROM candidates WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, "i", $cid);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
        $success = "Candidate removed.";
    }
}

// ── REMOVE USER ──
if (isset($_POST['remove_user'])) {
    $uid = intval($_POST['user_id']);
    mysqli_query($conn, "DELETE FROM user_details WHERE user_id = $uid");
    mysqli_query($conn, "DELETE FROM candidates WHERE user_id = $uid");
    mysqli_query($conn, "DELETE FROM ir_candidates WHERE user_id = $uid");
    mysqli_query($conn, "DELETE FROM users WHERE id = $uid");
    $success = "User removed.";
}

// ── CR VOTING CONTROLS ──
if (isset($_POST['start_cr_voting'])) {
    $cr_time = intval($_POST['cr_time_limit']);
    $cr_end  = date('Y-m-d H:i:s', strtotime("+{$cr_time} minutes"));
    mysqli_query($conn, "UPDATE voting_settings SET setting_value='active' WHERE setting_name='cr_voting_status'");
    $stmt = mysqli_prepare($conn, "UPDATE voting_settings SET setting_value=? WHERE setting_name='cr_voting_end_time'");
    mysqli_stmt_bind_param($stmt, "s", $cr_end);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    $success = "CR Voting started! Ends at $cr_end";
}
if (isset($_POST['force_end_cr'])) {
    mysqli_query($conn, "UPDATE users SET is_cr = 0, cr_section = NULL, cr_course = NULL");
    $groups = mysqli_query($conn, "SELECT DISTINCT course, section FROM candidates WHERE course IS NOT NULL AND section != ''");
    while ($g = mysqli_fetch_assoc($groups)) {
        $c = $g['course']; $s = $g['section'];
        $wq = mysqli_prepare($conn, "SELECT user_id, name FROM candidates WHERE course = ? AND section = ? ORDER BY votes DESC LIMIT 1");
        mysqli_stmt_bind_param($wq, "ss", $c, $s);
        mysqli_stmt_execute($wq);
        $winner = mysqli_fetch_assoc(mysqli_stmt_get_result($wq));
        mysqli_stmt_close($wq);
        if ($winner) {
            $uid = $winner['user_id'];
            $ustmt = mysqli_prepare($conn, "UPDATE users SET is_cr = 1, cr_section = ?, cr_course = ?, is_candidate = 0 WHERE id = ?");
            mysqli_stmt_bind_param($ustmt, "ssi", $s, $c, $uid);
            mysqli_stmt_execute($ustmt); mysqli_stmt_close($ustmt);
        }
    }
    mysqli_query($conn, "UPDATE voting_settings SET setting_value='ended' WHERE setting_name='cr_voting_status'");
    mysqli_query($conn, "DELETE FROM ir_candidates");
    $crs = mysqli_query($conn, "SELECT id, name FROM users WHERE is_cr = 1");
    while ($cr = mysqli_fetch_assoc($crs)) {
        $ins = mysqli_prepare($conn, "INSERT INTO ir_candidates (user_id, name, votes, weightage_votes) VALUES (?, ?, 0, 0)");
        mysqli_stmt_bind_param($ins, "is", $cr['id'], $cr['name']);
        mysqli_stmt_execute($ins); mysqli_stmt_close($ins);
    }
    $success = "CR Voting ended! Winners declared. IR candidates ready.";
}
if (isset($_POST['reset_cr_votes'])) {
    mysqli_query($conn, "UPDATE candidates SET votes = 0, pending_votes = 0");
    mysqli_query($conn, "UPDATE users SET vote = 0, voted_for = 0, pending_vote = 0");
    mysqli_query($conn, "UPDATE voting_settings SET setting_value='pending' WHERE setting_name='cr_voting_status'");
    mysqli_query($conn, "UPDATE voting_settings SET setting_value=NULL WHERE setting_name='cr_voting_end_time'");
    $success = "All CR votes reset!";
}

// ── IR VOTING CONTROLS ──
if (isset($_POST['start_ir_voting'])) {
    $ir_time = intval($_POST['ir_time_limit']);
    $ir_end  = date('Y-m-d H:i:s', strtotime("+{$ir_time} minutes"));
    mysqli_query($conn, "UPDATE voting_settings SET setting_value='active' WHERE setting_name='ir_voting_status'");
    $stmt = mysqli_prepare($conn, "UPDATE voting_settings SET setting_value=? WHERE setting_name='ir_voting_end_time'");
    mysqli_stmt_bind_param($stmt, "s", $ir_end);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    $success = "IR Voting started! Ends at $ir_end";
}
if (isset($_POST['force_end_ir'])) {
    mysqli_query($conn, "UPDATE voting_settings SET setting_value='ended' WHERE setting_name='ir_voting_status'");
    $success = "IR Voting ended!";
}
if (isset($_POST['reset_ir_votes'])) {
    mysqli_query($conn, "UPDATE ir_candidates SET votes = 0, weightage_votes = 0");
    mysqli_query($conn, "DELETE FROM ir_votes");
    mysqli_query($conn, "UPDATE voting_settings SET setting_value='pending' WHERE setting_name='ir_voting_status'");
    $success = "IR votes reset!";
}

// ── EVENT CONTROLS ──
if (isset($_POST['add_event'])) {
    $ename = trim($_POST['event_name']); $tlimit = intval($_POST['time_limit']);
    $stmt  = mysqli_prepare($conn, "INSERT INTO events (name, fest_name, time_limit, status) VALUES (?, 'Ullaash', ?, 'pending')");
    mysqli_stmt_bind_param($stmt, "si", $ename, $tlimit);
    if (mysqli_stmt_execute($stmt)) $success = "Event '$ename' added!";
    mysqli_stmt_close($stmt);
}
if (isset($_POST['add_event_candidate'])) {
    $event_id = intval($_POST['event_id']); $cname = trim($_POST['candidate_name']);
    $stmt = mysqli_prepare($conn, "INSERT INTO event_candidates (event_id, name, votes) VALUES (?, ?, 0)");
    mysqli_stmt_bind_param($stmt, "is", $event_id, $cname);
    if (mysqli_stmt_execute($stmt)) $success = "Candidate added!";
    mysqli_stmt_close($stmt);
}
if (isset($_POST['start_event'])) {
    $event_id = intval($_POST['event_id']); $tlimit = intval($_POST['event_time']);
    $end_time = date('Y-m-d H:i:s', strtotime("+{$tlimit} minutes"));
    $stmt = mysqli_prepare($conn, "UPDATE events SET status='active', start_time=NOW(), end_time=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "si", $end_time, $event_id);
    if (mysqli_stmt_execute($stmt)) $success = "Event started!";
    mysqli_stmt_close($stmt);
}
if (isset($_POST['end_event'])) {
    $event_id = intval($_POST['event_id']);
    $wq = mysqli_query($conn, "SELECT id FROM event_candidates WHERE event_id=$event_id ORDER BY votes DESC LIMIT 1");
    $winner = mysqli_fetch_assoc($wq);
    $stmt = mysqli_prepare($conn, "UPDATE events SET status='ended', winner_id=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "ii", $winner['id'], $event_id);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    $success = "Event ended!";
}
if (isset($_POST['reset_event_votes'])) {
    $event_id = intval($_POST['event_id']);
    mysqli_query($conn, "UPDATE event_candidates SET votes=0 WHERE event_id=$event_id");
    mysqli_query($conn, "DELETE FROM event_votes WHERE event_id=$event_id");
    mysqli_query($conn, "UPDATE events SET status='pending', winner_id=NULL, start_time=NULL, end_time=NULL WHERE id=$event_id");
    $success = "Event reset!";
}
if (isset($_POST['remove_event'])) {
    $event_id = intval($_POST['event_id']);
    mysqli_query($conn, "DELETE FROM event_votes WHERE event_id=$event_id");
    mysqli_query($conn, "DELETE FROM event_candidates WHERE event_id=$event_id");
    mysqli_query($conn, "DELETE FROM events WHERE id=$event_id");
    $success = "Event removed!";
}

// ── Fetch all users with full details ──
$all_users = mysqli_query($conn,
    "SELECT u.id, u.name, u.email, u.phone, u.verified, u.vote,
            u.is_candidate, u.is_cr,
            COALESCE(u.needs_reverify, 0) as needs_reverify,
            ud.full_name, ud.age, ud.gender, ud.roll_no, ud.erp_id,
            ud.course, ud.section, ud.user_photo
     FROM users u
     LEFT JOIN user_details ud ON u.id = ud.user_id
     ORDER BY u.needs_reverify DESC, u.verified ASC, u.id DESC");

// ── Get distinct courses and sections for filter dropdowns ──
$course_list  = mysqli_query($conn, "SELECT DISTINCT course FROM user_details WHERE course IS NOT NULL AND course != '' ORDER BY course");
$section_list = mysqli_query($conn, "SELECT DISTINCT section FROM user_details WHERE section IS NOT NULL AND section != '' ORDER BY section");

$cr_st_q   = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='cr_voting_status'");
$cr_status  = mysqli_fetch_assoc($cr_st_q)['setting_value'] ?? 'pending';
$ir_st_q   = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='ir_voting_status'");
$ir_status  = mysqli_fetch_assoc($ir_st_q)['setting_value'] ?? 'pending';

// Count users needing reverify
$rv_q     = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE needs_reverify = 1");
$rv_count = mysqli_fetch_assoc($rv_q)['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .admin-wrapper { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
    .section { background: white; border-radius: 12px; padding: 28px; margin-bottom: 28px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    .section h3 { margin-bottom: 20px; color: #1a1a2e; border-bottom: 2px solid #e94560; padding-bottom: 10px; }
    .btn-add      { background: #1a1a2e; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .btn-remove   { background: #e94560; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
    .btn-verify   { background: #27ae60; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
    .btn-unverify { background: #e67e22; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
    .btn-candidate{ background: #8e44ad; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
    .btn-details  { background: #2980b9; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1a1a2e; color: white; padding: 10px 14px; text-align: left; font-size: 0.9rem; }
    td { padding: 10px 14px; border-bottom: 1px solid #eee; font-size: 0.9rem; vertical-align: middle; }
    .badge-verified  { background: #eafaf1; color: #1e8449; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; display:inline-block; }
    .badge-pending   { background: #fef9e7; color: #d4a017; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; display:inline-block; }
    .badge-reverify  { background: #fdecea; color: #c0392b; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; display:inline-block; }
    .badge-candidate { background: #f4ecfb; color: #8e44ad; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; display:inline-block; }
    .badge-cr        { background: #fff5e6; color: #e67e22; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; display:inline-block; }
    .thumbnail { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #eee; }
    .action-cell { display: flex; flex-direction: column; gap: 6px; align-items: flex-start; }
    .status-panel { background: #f0f4f8; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; gap: 24px; flex-wrap: wrap; }
    .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; align-items: center; }
    .filter-bar select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem; background: white; }
    .filter-bar button { padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
    .reverify-alert {
      background: #fdecea; border: 1px solid #f5c6cb;
      border-radius: 10px; padding: 14px 20px; margin-bottom: 20px;
      color: #c0392b; font-weight: 600;
      display: flex; align-items: center; gap: 12px;
    }
    .reverify-badge {
      background: #c0392b; color: white;
      border-radius: 50%; width: 28px; height: 28px;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 0.85rem; font-weight: 700; flex-shrink: 0;
    }

    /* ── Detail Panel ── */
    .detail-row { display: none; background: #f7f9fc; }
    .detail-row.open { display: table-row; }
    .detail-panel { padding: 20px 24px; border-left: 4px solid #2980b9; }
    .detail-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 14px; margin-bottom: 20px;
    }
    .detail-item label {
      display: block; font-size: 0.73rem; font-weight: 700;
      text-transform: uppercase; color: #999; margin-bottom: 3px; letter-spacing: 0.5px;
    }
    .detail-item span { font-size: 0.93rem; color: #1a1a2e; font-weight: 600; }
    .detail-section-title {
      font-size: 0.8rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.6px; color: #2980b9; margin-bottom: 10px; margin-top: 4px;
    }
    .selfie-large {
      width: 120px; height: 120px; border-radius: 10px;
      object-fit: cover; border: 3px solid #e94560;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .no-photo-box {
      width: 120px; height: 120px; border-radius: 10px;
      background: #f0f4f8; display: flex; align-items: center;
      justify-content: center; font-size: 2.5rem; border: 2px dashed #ddd;
    }
  </style>
</head>
<body>
<?php include("loading.php"); ?>

<div class="top-bar">
  <img src="college-banner.png" alt="SRMU"
       style="height:45px; object-fit:contain; background:white; padding:4px 10px; border-radius:6px;">
  <h2>⚙️ Admin Dashboard</h2>
  <div class="top-bar-links">
    <a href="result.php">📊 Results</a>
    <a href="admin-logout.php">Logout</a>
  </div>
</div>

<div class="admin-wrapper">

<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

<!-- ══════════════════════════════════
     SECTION 1: VOTING CONTROL PANEL
══════════════════════════════════ -->
<div class="section">
  <h3>🎛️ Election Control Panel</h3>
  <div class="status-panel">
    <div><strong>CR Voting:</strong>
      <span class="badge-<?php echo $cr_status=='active'?'verified':($cr_status=='ended'?'candidate':'pending'); ?>">
        <?php echo strtoupper($cr_status); ?>
      </span>
    </div>
    <div><strong>IR Voting:</strong>
      <span class="badge-<?php echo $ir_status=='active'?'verified':($ir_status=='ended'?'candidate':'pending'); ?>">
        <?php echo strtoupper($ir_status); ?>
      </span>
    </div>
  </div>
  <div style="display:flex; gap:12px; flex-wrap:wrap;">
    <?php if ($cr_status == 'pending'): ?>
    <form method="POST" style="display:flex; gap:8px; align-items:center;">
      <label style="font-weight:600; font-size:0.9rem;">CR Duration (mins):</label>
      <input type="number" name="cr_time_limit" value="60" min="1" style="width:70px; padding:8px; border:1px solid #ddd; border-radius:6px;">
      <button type="submit" name="start_cr_voting" style="background:#27ae60; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600;">▶ Start CR Voting</button>
    </form>
    <?php endif; ?>
    <?php if ($cr_status == 'active'): ?>
    <form method="POST" onsubmit="return confirm('End CR voting and declare winners?')">
      <button type="submit" name="force_end_cr" style="background:#c0392b; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600;">⏹ End CR Voting & Declare Winners</button>
    </form>
    <?php endif; ?>
    <form method="POST" onsubmit="return confirm('Reset ALL CR votes?')">
      <button type="submit" name="reset_cr_votes" style="background:#e94560; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600;">🔄 Reset CR Votes</button>
    </form>
    <?php if ($ir_status == 'pending' && $cr_status == 'ended'): ?>
    <form method="POST" style="display:flex; gap:8px; align-items:center;">
      <label style="font-weight:600; font-size:0.9rem;">IR Duration (mins):</label>
      <input type="number" name="ir_time_limit" value="30" min="1" style="width:70px; padding:8px; border:1px solid #ddd; border-radius:6px;">
      <button type="submit" name="start_ir_voting" style="background:#27ae60; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600;">▶ Start IR Voting</button>
    </form>
    <?php endif; ?>
    <?php if ($ir_status == 'active'): ?>
    <form method="POST" onsubmit="return confirm('End IR voting?')">
      <button type="submit" name="force_end_ir" style="background:#c0392b; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600;">⏹ End IR Voting</button>
    </form>
    <?php endif; ?>
    <form method="POST" onsubmit="return confirm('Reset IR votes?')">
      <button type="submit" name="reset_ir_votes" style="background:#8e44ad; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600;">🔄 Reset IR Votes</button>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════
     SECTION 2: REGISTERED STUDENTS
══════════════════════════════════ -->
<div class="section">
  <h3>👥 Registered Students</h3>

  <!-- Re-verify alert -->
  <?php if ($rv_count > 0): ?>
  <div class="reverify-alert">
    <span class="reverify-badge"><?php echo $rv_count; ?></span>
    <span>
      <?php echo $rv_count; ?> student(s) edited their profile and need your re-verification before they can vote.
      Use the <strong>"Show Needs Re-verify"</strong> filter below to see them.
    </span>
  </div>
  <?php endif; ?>

  <p style="color:#777; margin-bottom:14px; font-size:0.88rem;">
    ✅ New students are <strong>auto-verified</strong> via email OTP — no action needed for them.<br>
    ⚠️ Only students who <strong>edited their profile</strong> appear in "Needs Re-verify" and require your review.
  </p>

  <!-- ── Filter Bar ── -->
  <div class="filter-bar">
    <strong style="font-size:0.88rem; color:#555;">Filter:</strong>

    <!-- Status filter -->
    <button onclick="applyFilter()" id="btn-all"
            style="background:#1a1a2e; color:white;">All</button>
    <button onclick="applyFilter('unverified')" id="btn-unverified"
            style="background:#e67e22; color:white;">Unverified</button>
    <button onclick="applyFilter('verified')" id="btn-verified"
            style="background:#27ae60; color:white;">Verified</button>
    <button onclick="applyFilter('reverify')" id="btn-reverify"
            style="background:#c0392b; color:white;">
      🔴 Needs Re-verify <?php if($rv_count > 0) echo "($rv_count)"; ?>
    </button>

    <!-- Course filter -->
    <select id="filter-course" onchange="applyFilter()">
      <option value="">All Courses</option>
      <?php
      // Re-query course list since we used it once
      $cl = mysqli_query($conn, "SELECT DISTINCT course FROM user_details WHERE course IS NOT NULL AND course != '' ORDER BY course");
      while ($row = mysqli_fetch_assoc($cl)):
      ?>
      <option value="<?php echo htmlspecialchars($row['course']); ?>">
        <?php echo htmlspecialchars($row['course']); ?>
      </option>
      <?php endwhile; ?>
    </select>

    <!-- Section filter -->
    <select id="filter-section" onchange="applyFilter()">
      <option value="">All Sections</option>
      <?php
      $sl = mysqli_query($conn, "SELECT DISTINCT section FROM user_details WHERE section IS NOT NULL AND section != '' ORDER BY section");
      while ($row = mysqli_fetch_assoc($sl)):
      ?>
      <option value="<?php echo htmlspecialchars($row['section']); ?>">
        Section <?php echo htmlspecialchars($row['section']); ?>
      </option>
      <?php endwhile; ?>
    </select>

    <button onclick="resetFilters()" style="background:#777; color:white;">Reset</button>
  </div>

  <p id="visible-count" style="font-size:0.85rem; color:#777; margin-bottom:12px;"></p>

  <table id="usersTable">
    <thead>
      <tr>
        <th>Photo</th>
        <th>Name</th>
        <th>Email / Phone</th>
        <th>Course / Section</th>
        <th>Roll No / ERP</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($u = mysqli_fetch_assoc($all_users)):
      $uid        = $u['id'];
      $hasProf    = !empty($u['course']);
      $needsRev   = $u['needs_reverify'] == 1;
    ?>

    <!-- Main row -->
    <tr class="main-user-row"
        data-verified="<?php echo $u['verified']; ?>"
        data-reverify="<?php echo $needsRev ? '1' : '0'; ?>"
        data-course="<?php echo htmlspecialchars($u['course'] ?? ''); ?>"
        data-section="<?php echo htmlspecialchars($u['section'] ?? ''); ?>">

      <td>
        <?php if (!empty($u['user_photo'])): ?>
          <img src="uploads/<?php echo htmlspecialchars($u['user_photo']); ?>"
               class="thumbnail" title="<?php echo htmlspecialchars($u['name']); ?>">
        <?php else: ?>
          <div style="width:48px;height:48px;border-radius:50%;background:#eee;
                      display:flex;align-items:center;justify-content:center;font-size:1.3rem;">👤</div>
        <?php endif; ?>
      </td>

      <td>
        <strong><?php echo htmlspecialchars($u['name']); ?></strong>
        <?php if (!empty($u['full_name']) && $u['full_name'] !== $u['name']): ?>
          <br><span style="font-size:0.8rem;color:#777;"><?php echo htmlspecialchars($u['full_name']); ?></span>
        <?php endif; ?>
        <?php if ($u['is_candidate']==1): ?><br><span class="badge-candidate">🎯 Candidate</span><?php endif; ?>
        <?php if ($u['is_cr']==1):        ?><br><span class="badge-cr">🏆 CR Winner</span><?php endif; ?>
      </td>

      <td>
        <?php echo htmlspecialchars($u['email']); ?>
        <?php if (!empty($u['phone'])): ?>
          <br><span style="font-size:0.8rem;color:#777;">📞 <?php echo htmlspecialchars($u['phone']); ?></span>
        <?php endif; ?>
      </td>

      <td>
        <?php if ($hasProf): ?>
          <strong><?php echo htmlspecialchars($u['course']); ?></strong>
          <br>Section <?php echo htmlspecialchars($u['section']); ?>
        <?php else: ?><span style="color:#ccc;">No profile</span><?php endif; ?>
      </td>

      <td>
        <?php if ($hasProf): ?>
          <?php echo htmlspecialchars($u['roll_no'] ?? '—'); ?>
          <br><span style="font-size:0.8rem;color:#777;">ERP: <?php echo htmlspecialchars($u['erp_id'] ?? '—'); ?></span>
        <?php else: ?>—<?php endif; ?>
      </td>

      <td>
        <?php if ($needsRev): ?>
          <span class="badge-reverify">🔴 Re-verify</span>
        <?php elseif ($u['verified']==1): ?>
          <span class="badge-verified">✅ Verified</span>
        <?php else: ?>
          <span class="badge-pending">⏳ Pending</span>
        <?php endif; ?>
        <?php if ($u['vote']==1): ?><br><span style="color:#27ae60;font-size:0.8rem;">🗳️ Voted</span><?php endif; ?>
      </td>

      <td>
        <div class="action-cell">
          <button type="button" class="btn-details" onclick="toggleDetails(<?php echo $uid; ?>)">
            👁 Details
          </button>

          <form method="POST">
            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
            <?php if ($u['verified']==0 || $needsRev): ?>
              <button type="submit" name="verify_user" class="btn-verify">✅ Verify</button>
            <?php else: ?>
              <button type="submit" name="unverify_user" class="btn-unverify">✖ Unverify</button>
            <?php endif; ?>
          </form>

          <?php if ($u['verified']==1 && $u['is_candidate']==0 && $u['is_cr']==0 && $hasProf && !$needsRev): ?>
          <form method="POST">
            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
            <button type="submit" name="make_candidate" class="btn-candidate">🎯 Make CR Candidate</button>
          </form>
          <?php endif; ?>

          <form method="POST" onsubmit="return confirm('Remove this user permanently?')">
            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
            <button type="submit" name="remove_user" class="btn-remove">🗑 Remove</button>
          </form>
        </div>
      </td>
    </tr>

    <!-- Expandable Detail Row -->
    <tr class="detail-row" id="detail-<?php echo $uid; ?>">
      <td colspan="7">
        <div class="detail-panel">
          <?php if (!$hasProf): ?>
            <p style="color:#e94560;font-weight:600;">⚠️ This student has not filled their profile yet.</p>
          <?php else: ?>

            <?php if ($needsRev): ?>
            <div style="background:#fdecea;border-radius:8px;padding:10px 14px;margin-bottom:16px;color:#c0392b;font-weight:600;font-size:0.88rem;">
              🔴 This student edited their profile and needs re-verification. Review their details below, then click Verify.
            </div>
            <?php endif; ?>

            <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">

              <!-- Selfie -->
              <div>
                <p class="detail-section-title">🤳 Selfie</p>
                <?php if (!empty($u['user_photo'])): ?>
                  <img src="uploads/<?php echo htmlspecialchars($u['user_photo']); ?>"
                       class="selfie-large" alt="Selfie">
                <?php else: ?>
                  <div class="no-photo-box">👤</div>
                  <p style="color:#e94560;font-size:0.78rem;margin-top:4px;">Not uploaded</p>
                <?php endif; ?>
              </div>

              <!-- Info -->
              <div style="flex:1; min-width:280px;">
                <p class="detail-section-title">📋 Personal Info</p>
                <div class="detail-grid">
                  <div class="detail-item"><label>Full Name</label><span><?php echo htmlspecialchars($u['full_name']?:'—'); ?></span></div>
                  <div class="detail-item"><label>Age</label><span><?php echo htmlspecialchars($u['age']??'—'); ?> yrs</span></div>
                  <div class="detail-item"><label>Gender</label><span><?php echo htmlspecialchars($u['gender']??'—'); ?></span></div>
                  <div class="detail-item"><label>Email</label><span><?php echo htmlspecialchars($u['email']); ?></span></div>
                  <div class="detail-item"><label>Phone</label><span><?php echo htmlspecialchars($u['phone']??'—'); ?></span></div>
                </div>

                <p class="detail-section-title" style="margin-top:14px;">🎓 Academic Info</p>
                <div class="detail-grid">
                  <div class="detail-item"><label>Course</label><span><?php echo htmlspecialchars($u['course']?:'—'); ?></span></div>
                  <div class="detail-item"><label>Section</label><span><?php echo htmlspecialchars($u['section']?:'—'); ?></span></div>
                  <div class="detail-item"><label>Roll Number</label><span><?php echo htmlspecialchars($u['roll_no']?:'—'); ?></span></div>
                  <div class="detail-item"><label>ERP ID</label><span><?php echo htmlspecialchars($u['erp_id']?:'—'); ?></span></div>
                  <div class="detail-item"><label>Voted?</label><span><?php echo $u['vote']==1?'✅ Yes':'❌ No'; ?></span></div>
                  <div class="detail-item"><label>CR Candidate?</label><span><?php echo $u['is_candidate']==1?'✅ Yes':'❌ No'; ?></span></div>
                </div>
              </div>
            </div>

            <!-- Action buttons inside panel -->
            <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap; padding-top:14px; border-top:1px solid #e0e8f0;">
              <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                <?php if ($u['verified']==0 || $needsRev): ?>
                  <button type="submit" name="verify_user" class="btn-verify">✅ Verify This Student</button>
                <?php else: ?>
                  <button type="submit" name="unverify_user" class="btn-unverify">✖ Unverify</button>
                <?php endif; ?>
              </form>
              <?php if ($u['verified']==1 && $u['is_candidate']==0 && $u['is_cr']==0 && $hasProf && !$needsRev): ?>
              <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                <button type="submit" name="make_candidate" class="btn-candidate">🎯 Make CR Candidate</button>
              </form>
              <?php endif; ?>
              <button type="button" onclick="toggleDetails(<?php echo $uid; ?>)"
                      style="background:#777; color:white; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-size:0.85rem;">
                ✖ Collapse
              </button>
            </div>

          <?php endif; ?>
        </div>
      </td>
    </tr>

    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<!-- ══════════════════════════════════
     SECTION 3: CR CANDIDATES
══════════════════════════════════ -->
<div class="section">
  <h3>🎯 CR Candidates</h3>
  <?php
  $all_candidates = mysqli_query($conn,
      "SELECT c.*, u.name as uname FROM candidates c
       JOIN users u ON c.user_id = u.id
       ORDER BY c.course, c.section, c.votes DESC");
  if (mysqli_num_rows($all_candidates) == 0): ?>
    <p style="color:#aaa;">No candidates yet. Click "Make CR Candidate" above.</p>
  <?php else: ?>
  <table>
    <tr><th>Name</th><th>Course</th><th>Section</th><th>Votes</th><th>Action</th></tr>
    <?php while ($c = mysqli_fetch_assoc($all_candidates)): ?>
    <tr>
      <td><strong><?php echo htmlspecialchars($c['uname']); ?></strong></td>
      <td><?php echo htmlspecialchars($c['course']); ?></td>
      <td>Section <?php echo htmlspecialchars($c['section']); ?></td>
      <td><?php echo $c['votes']; ?></td>
      <td>
        <form method="POST" onsubmit="return confirm('Remove this candidate?')">
          <input type="hidden" name="candidate_id" value="<?php echo $c['id']; ?>">
          <button type="submit" name="remove_candidate" class="btn-remove">Remove</button>
        </form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════
     SECTION 4: IR CANDIDATES
══════════════════════════════════ -->
<div class="section">
  <h3>🏛️ IR Candidates (Elected CRs)</h3>
  <?php
  $ir_candidates = mysqli_query($conn,
      "SELECT ic.*, u.name as uname, ud.course, ud.section
       FROM ir_candidates ic
       JOIN users u ON ic.user_id = u.id
       LEFT JOIN user_details ud ON ic.user_id = ud.user_id
       ORDER BY ic.weightage_votes DESC");
  if (mysqli_num_rows($ir_candidates) == 0): ?>
    <p style="color:#aaa;">No IR candidates yet. Added automatically after CR voting ends.</p>
  <?php else: ?>
  <table>
    <tr><th>Name</th><th>Course</th><th>Section</th><th>Votes</th><th>Weighted Points</th></tr>
    <?php while ($ic = mysqli_fetch_assoc($ir_candidates)): ?>
    <tr>
      <td><strong><?php echo htmlspecialchars($ic['uname']); ?></strong></td>
      <td><?php echo htmlspecialchars($ic['course'] ?? '—'); ?></td>
      <td><?php echo htmlspecialchars($ic['section'] ?? '—'); ?></td>
      <td><?php echo $ic['votes']; ?></td>
      <td><?php echo $ic['weightage_votes']; ?> pts</td>
    </tr>
    <?php endwhile; ?>
  </table>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════
     SECTION 5: ULLAASH EVENTS
══════════════════════════════════ -->
<div class="section">
  <h3>🎉 Ullaash Fest Events</h3>
  <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
    <input type="text" name="event_name" placeholder="Event Name" required
           style="flex:1; padding:10px 14px; border:1px solid #ddd; border-radius:8px;">
    <input type="number" name="time_limit" placeholder="Time (minutes)" value="10" min="1"
           style="width:130px; padding:10px 14px; border:1px solid #ddd; border-radius:8px;">
    <button type="submit" name="add_event" class="btn-add">➕ Add Event</button>
  </form>

  <?php
  $all_events = mysqli_query($conn, "SELECT * FROM events ORDER BY id ASC");
  while ($ev = mysqli_fetch_assoc($all_events)):
    $ev_cands    = mysqli_query($conn, "SELECT * FROM event_candidates WHERE event_id={$ev['id']} ORDER BY votes DESC");
    $winner_name = "";
    if ($ev['winner_id']) {
        $wq = mysqli_query($conn, "SELECT name FROM event_candidates WHERE id={$ev['winner_id']}");
        $wr = mysqli_fetch_assoc($wq);
        $winner_name = $wr['name'] ?? "";
    }
  ?>
  <div style="background:#f9f9f9; border-radius:8px; padding:16px; margin-bottom:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <strong><?php echo htmlspecialchars($ev['name']); ?> — <span style="color:#555;"><?php echo ucfirst($ev['status']); ?></span></strong>
      <div style="display:flex; gap:6px;">
        <?php if ($ev['status']=='pending'): ?>
          <form method="POST" style="display:flex; gap:6px;">
            <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
            <input type="number" name="event_time" value="<?php echo $ev['time_limit']; ?>" style="width:70px; padding:6px;">
            <button type="submit" name="start_event" class="btn-verify">▶ Start</button>
          </form>
        <?php elseif ($ev['status']=='active'): ?>
          <form method="POST">
            <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
            <button type="submit" name="end_event" class="btn-unverify">⏹ End</button>
          </form>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('Reset event?')">
          <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
          <button type="submit" name="reset_event_votes" class="btn-unverify">🔄 Reset</button>
        </form>
        <form method="POST" onsubmit="return confirm('Remove event?')">
          <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
          <button type="submit" name="remove_event" class="btn-remove">🗑</button>
        </form>
      </div>
    </div>
    <?php if ($winner_name): ?>
      <div style="background:linear-gradient(135deg,#f39c12,#e67e22); color:white; padding:8px; border-radius:6px; margin-bottom:10px;">
        🏆 Winner: <?php echo htmlspecialchars($winner_name); ?>
      </div>
    <?php endif; ?>
    <table style="width:100%;">
      <tr><th>Candidate</th><th>Votes</th></tr>
      <?php while ($ec = mysqli_fetch_assoc($ev_cands)): ?>
      <tr><td><?php echo htmlspecialchars($ec['name']); ?></td><td><?php echo $ec['votes']; ?></td></tr>
      <?php endwhile; ?>
    </table>
    <?php if ($ev['status']=='pending'): ?>
    <form method="POST" style="display:flex; gap:8px; margin-top:12px;">
      <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
      <input type="text" name="candidate_name" placeholder="Add candidate name"
             style="flex:1; padding:8px; border:1px solid #ddd; border-radius:6px;">
      <button type="submit" name="add_event_candidate" class="btn-add">Add</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endwhile; ?>
</div>

</div><!-- end admin-wrapper -->

<script>
var activeStatus = '';

function applyFilter(status) {
  if (status !== undefined) activeStatus = status;

  var course  = document.getElementById('filter-course').value;
  var section = document.getElementById('filter-section').value;
  var rows    = document.querySelectorAll('#usersTable tbody tr.main-user-row');
  var visible = 0;

  rows.forEach(function(row) {
    var v       = row.getAttribute('data-verified');
    var rv      = row.getAttribute('data-reverify');
    var rc      = row.getAttribute('data-course');
    var rs      = row.getAttribute('data-section');
    var detRow  = document.getElementById('detail-' + getDuid(row));

    var statusOk = true;
    if (activeStatus === 'unverified') statusOk = (v == '0' && rv == '0');
    else if (activeStatus === 'verified')   statusOk = (v == '1' && rv == '0');
    else if (activeStatus === 'reverify')   statusOk = (rv == '1');

    var courseOk  = !course  || rc === course;
    var sectionOk = !section || rs === section;
    var show      = statusOk && courseOk && sectionOk;

    row.style.display = show ? '' : 'none';
    if (detRow) {
      if (!show) detRow.classList.remove('open');
      detRow.style.display = show ? '' : 'none';
    }
    if (show) visible++;
  });

  document.getElementById('visible-count').textContent =
    'Showing ' + visible + ' student(s)';
}

function getDuid(row) {
  var next = row.nextElementSibling;
  return next ? next.id.replace('detail-', '') : null;
}

function resetFilters() {
  activeStatus = '';
  document.getElementById('filter-course').value  = '';
  document.getElementById('filter-section').value = '';
  applyFilter();
}

function toggleDetails(uid) {
  var detailRow = document.getElementById('detail-' + uid);
  if (!detailRow) return;
  detailRow.classList.toggle('open');
  if (detailRow.classList.contains('open')) {
    detailRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

// Run on load to show total count
window.addEventListener('DOMContentLoaded', function() { applyFilter(); });
</script>
</body>
</html>