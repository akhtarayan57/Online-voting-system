<?php
session_start();
include("db.php");

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Get user info
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Get IR voting status
$ir_st_q   = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='ir_voting_status'");
$ir_status  = mysqli_fetch_assoc($ir_st_q)['setting_value'] ?? 'pending';

$ir_et_q   = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='ir_voting_end_time'");
$ir_end    = mysqli_fetch_assoc($ir_et_q)['setting_value'] ?? null;
$ir_ended  = $ir_end && strtotime($ir_end) < time();

// Weightage: CR = 10 points, regular student = 5 points
$my_weightage = ($me['is_cr'] == 1) ? 10 : 5;

// Check if already voted in IR
$chk = mysqli_prepare($conn, "SELECT id FROM ir_votes WHERE voter_id = ?");
mysqli_stmt_bind_param($chk, "i", $user_id);
mysqli_stmt_execute($chk);
mysqli_stmt_store_result($chk);
$already_voted = mysqli_stmt_num_rows($chk) > 0;
mysqli_stmt_close($chk);

$success = "";
$error   = "";

if (isset($_POST['cast_ir_vote'])) {
    $candidate_id = intval($_POST['ir_candidate_id']);

    if ($already_voted) {
        $error = "You have already voted in the IR election!";
    } elseif ($ir_status != 'active') {
        $error = "IR voting is not active right now!";
    } elseif ($ir_ended) {
        $error = "IR voting time has ended!";
    } elseif ($candidate_id == $user_id) {
        $error = "You cannot vote for yourself!";
    } else {
        // Add vote with weightage
        $stmt = mysqli_prepare($conn,
            "UPDATE ir_candidates SET votes = votes + 1, weightage_votes = weightage_votes + ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "di", $my_weightage, $candidate_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Record vote
        $stmt2 = mysqli_prepare($conn,
            "INSERT INTO ir_votes (voter_id, candidate_id, weightage) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt2, "iid", $user_id, $candidate_id, $my_weightage);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        $already_voted = true;
        $success = "Your IR vote has been cast! Your voting power: {$my_weightage} points.";
    }
}

// Get IR candidates
$candidates = mysqli_query($conn,
    "SELECT ic.*, u.name as uname, ud.course, ud.section
     FROM ir_candidates ic
     JOIN users u ON ic.user_id = u.id
     LEFT JOIN user_details ud ON ic.user_id = ud.user_id
     ORDER BY ic.weightage_votes DESC");

// Get IR winner if ended
$ir_winner = null;
if ($ir_ended || $ir_status == 'ended') {
    $wq = mysqli_query($conn,
        "SELECT ic.*, u.name as uname FROM ir_candidates ic
         JOIN users u ON ic.user_id = u.id
         ORDER BY ic.weightage_votes DESC LIMIT 1");
    $ir_winner = mysqli_fetch_assoc($wq);
}

// Total weightage
$tv_q    = mysqli_query($conn, "SELECT SUM(weightage_votes) as total FROM ir_candidates");
$tv_r    = mysqli_fetch_assoc($tv_q);
$total_w = max($tv_r['total'], 1);
?>
<!DOCTYPE html>
<html>
<head>
  <title>IR Election Voting</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .ir-wrapper { max-width: 800px; margin: 30px auto; padding: 0 20px; }
    .ir-card { background: white; border-radius: 12px; padding: 28px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); margin-bottom: 20px; }
    .ir-candidate { display: flex; align-items: center; padding: 14px 0; border-bottom: 1px solid #eee; gap: 12px; }
    .ir-candidate:last-child { border-bottom: none; }
    .vote-bar-bg { flex: 1; background: #eee; border-radius: 10px; height: 10px; }
    .vote-bar-fill { height: 100%; background: linear-gradient(90deg,#8e44ad,#3498db); border-radius: 10px; }
    .winner-box {
      background: linear-gradient(135deg,#f39c12,#e67e22);
      color: white; padding: 24px; border-radius: 12px;
      text-align: center; margin-bottom: 20px;
    }
  </style>
</head>
<body>
<?php include("loading.php"); ?>

<div class="top-bar">
  <img src="college-banner.png" alt="SRMU"
       style="height:45px; object-fit:contain; background:white; padding:4px 10px; border-radius:6px;">
  <div class="top-bar-links">
    <a href="dashboard.php">← Dashboard</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<div class="ir-wrapper">
  <h2 style="text-align:center; color:#1a1a2e; margin-bottom:8px;">🏛️ IR Election</h2>
  <p style="text-align:center; color:#777; margin-bottom:24px;">
    Institute Representative Election — Weighted Voting System
  </p>

  <!-- Voting power card -->
  <div class="ir-card" style="background:linear-gradient(135deg,#1a1a2e,#0f3460); color:white; text-align:center;">
    <p style="margin-bottom:8px;">Your Voting Power</p>
    <span style="background:linear-gradient(135deg,#8e44ad,#3498db); color:white; padding:8px 20px;
                 border-radius:20px; font-size:1.1rem; font-weight:600;">
      <?php echo $my_weightage; ?> Points
      <?php echo $me['is_cr'] == 1 ? '(You are a CR — higher weight!)' : '(Student)'; ?>
    </span>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

  <!-- Winner box -->
  <?php if ($ir_winner && ($ir_ended || $ir_status == 'ended')): ?>
  <div class="winner-box">
    <h3>🏆 IR Election Result</h3>
    <p style="font-size:1.4rem; font-weight:700; margin:10px 0;">
      <?php echo htmlspecialchars($ir_winner['uname']); ?>
    </p>
    <p>Total Weighted Points: <?php echo $ir_winner['weightage_votes']; ?></p>
    <p style="margin-top:8px; opacity:0.9;">Congratulations! They are your new Institute Representative.</p>
  </div>
  <?php endif; ?>

  <!-- Status messages -->
  <?php if ($ir_status == 'pending'): ?>
    <div class="alert alert-error">⏳ IR voting has not started yet. Wait for admin to open it.</div>
  <?php elseif ($ir_status == 'active' && !$ir_ended && $ir_end): ?>
    <p style="color:#e94560; font-weight:600; margin-bottom:16px;">⏱ IR Voting ends: <?php echo $ir_end; ?></p>
  <?php endif; ?>

  <!-- Candidates list -->
  <div class="ir-card">
    <h3 style="border-bottom:2px solid #8e44ad; padding-bottom:10px; margin-bottom:20px; color:#1a1a2e;">
      🎯 IR Candidates (All Elected CRs)
    </h3>

    <?php
    $candidates = mysqli_query($conn,
        "SELECT ic.*, u.name as uname, ud.course, ud.section
         FROM ir_candidates ic
         JOIN users u ON ic.user_id = u.id
         LEFT JOIN user_details ud ON ic.user_id = ud.user_id
         ORDER BY ic.weightage_votes DESC");

    if (mysqli_num_rows($candidates) == 0): ?>
      <p style="color:#aaa; text-align:center;">No IR candidates yet. They appear after CR voting ends.</p>
    <?php else:
      while ($c = mysqli_fetch_assoc($candidates)):
        $pct = round(($c['weightage_votes'] / $total_w) * 100);
    ?>
    <div class="ir-candidate">
      <div style="min-width:160px;">
        <strong><?php echo htmlspecialchars($c['uname']); ?></strong><br>
        <span style="font-size:0.8rem; color:#777;">
          <?php echo htmlspecialchars($c['course'] ?? ''); ?>
          <?php echo !empty($c['section']) ? '— Sec ' . htmlspecialchars($c['section']) : ''; ?>
        </span>
      </div>
      <div class="vote-bar-bg">
        <div class="vote-bar-fill" style="width:<?php echo $pct; ?>%"></div>
      </div>
      <span style="min-width:70px; text-align:right; font-size:0.85rem; color:#555;">
        <?php echo $c['weightage_votes']; ?> pts
      </span>
      <?php if ($ir_status == 'active' && !$already_voted && !$ir_ended && $c['user_id'] != $user_id): ?>
        <form method="POST" style="margin-left:12px;">
          <input type="hidden" name="ir_candidate_id" value="<?php echo $c['user_id']; ?>">
          <button type="submit" name="cast_ir_vote"
                  style="background:#8e44ad; color:white; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600;">
            Vote
          </button>
        </form>
      <?php elseif ($c['user_id'] == $user_id): ?>
        <span style="margin-left:12px; color:#aaa; font-size:0.85rem;">(You)</span>
      <?php endif; ?>
    </div>
    <?php endwhile;
    endif; ?>

    <?php if ($already_voted): ?>
      <p style="color:#27ae60; font-weight:600; margin-top:16px;">✅ You have already cast your IR vote!</p>
    <?php endif; ?>
  </div>
</div>

<script>
setTimeout(function(){ location.reload(); }, 30000);
</script>
</body>
</html>