<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include("db.php");

// Get CR voting status
$cr_st_q   = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='cr_voting_status'");
$cr_status = mysqli_fetch_assoc($cr_st_q)['setting_value'] ?? 'pending';

$cr_et_q   = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='cr_voting_end_time'");
$cr_end    = mysqli_fetch_assoc($cr_et_q)['setting_value'] ?? null;
$cr_ended  = $cr_end && strtotime($cr_end) < time();

$election_concluded = ($cr_status == 'ended' || $cr_ended);

$sections_query = mysqli_query($conn,
    "SELECT DISTINCT course, section FROM candidates
     WHERE course IS NOT NULL AND section IS NOT NULL
     ORDER BY course, section");
?>
<!DOCTYPE html>
<html>
<head>
  <title>CR Voting Results</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .results-wrapper { max-width: 800px; margin: 30px auto; padding: 0 20px; }
    .section-card {
      background: white; border-radius: 12px; padding: 24px;
      margin-bottom: 24px; box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }
    .section-card h3 {
      color: #1a1a2e; border-bottom: 2px solid #e94560;
      padding-bottom: 10px; margin-bottom: 20px;
    }
    .winner-badge {
      background: linear-gradient(135deg,#f39c12,#e67e22);
      color: white; padding: 6px 16px; border-radius: 20px;
      font-size: 0.85rem; font-weight: 600; display: inline-block; margin-bottom: 16px;
    }
    .declared-winner-box {
      background: linear-gradient(135deg,#1e8449,#27ae60);
      color: white; border-radius: 10px; padding: 16px 20px;
      margin-bottom: 18px; display: flex; align-items: center; gap: 16px;
    }
    .declared-winner-box .crown { font-size: 2rem; }
    .declared-winner-box .winner-name { font-size: 1.15rem; font-weight: 700; }
    .declared-winner-box .winner-label { font-size: 0.82rem; opacity: 0.88; margin-top: 3px; }
    .declared-winner-box .winner-votes {
      margin-left: auto; background: rgba(255,255,255,0.2);
      border-radius: 8px; padding: 6px 14px; text-align: center; white-space: nowrap;
    }
    .declared-winner-box .winner-votes span { display: block; font-size: 1.1rem; font-weight: 700; }
    .declared-winner-box .winner-votes small { font-size: 0.78rem; opacity: 0.85; }
    .status-pill {
      display: inline-block; padding: 3px 12px; border-radius: 20px;
      font-size: 0.78rem; font-weight: 700; vertical-align: middle; margin-left: 10px;
    }
    .pill-live    { background: #fdecea; color: #c0392b; }
    .pill-pending { background: #fef9e7; color: #d4a017; }
    .pill-done    { background: #eafaf1; color: #1e8449; }
    .candidate-row {
      display: flex; align-items: center; margin-bottom: 12px;
      padding: 8px; background: #f9f9f9; border-radius: 8px;
    }
    .candidate-name { width: 160px; font-weight: 600; }
    .progress-bar { flex: 1; background: #eee; border-radius: 10px; height: 22px; margin: 0 12px; overflow: hidden; }
    .progress-fill {
      height: 100%; background: linear-gradient(90deg,#e94560,#c73652);
      border-radius: 10px; display: flex; align-items: center;
      justify-content: flex-end; padding-right: 8px;
      color: white; font-size: 0.75rem; font-weight: bold;
    }
    .vote-count { min-width: 90px; text-align: right; font-size: 0.9rem; color: #555; }
  </style>
</head>
<body>
<?php include("loading.php"); ?>
<div class="top-bar">
  <img src="college-banner.png" alt="SRMU"
       style="height:45px; object-fit:contain; background:white; padding:4px 10px; border-radius:6px;">
  <h2>📊 CR Election Results
    <?php
    if ($election_concluded)
      echo '<span class="status-pill pill-done">✅ Concluded</span>';
    elseif ($cr_status == 'active')
      echo '<span class="status-pill pill-live">🔴 Live</span>';
    else
      echo '<span class="status-pill pill-pending">⏳ Pending</span>';
    ?>
  </h2>
  <div class="top-bar-links">
    <a href="dashboard.php">← Dashboard</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<div class="results-wrapper">

  <?php if ($election_concluded): ?>
  <div style="background:linear-gradient(135deg,#1a1a2e,#0f3460); border-radius:12px;
              padding:22px 28px; margin-bottom:28px; text-align:center; color:white;">
    <div style="font-size:2rem; margin-bottom:8px;">🏆</div>
    <h3 style="font-size:1.3rem; margin-bottom:6px;">CR Election — Official Declaration</h3>
    <p style="color:#aaa; font-size:0.9rem;">
      The CR election has concluded. Winners declared below are the official
      Class Representatives for their respective sections.
    </p>
  </div>
  <?php elseif ($cr_status == 'active'): ?>
  <div style="background:#eafaf1; border:1px solid #a9dfbf; border-radius:10px;
              padding:14px 20px; margin-bottom:24px; color:#1e8449; font-weight:600; text-align:center;">
    🔴 CR Election is currently LIVE — Results update in real time
  </div>
  <?php else: ?>
  <div style="background:#fef9e7; border:1px solid #f9e79f; border-radius:10px;
              padding:14px 20px; margin-bottom:24px; color:#d4a017; font-weight:600; text-align:center;">
    ⏳ CR Election has not started yet — results will appear once voting opens
  </div>
  <?php endif; ?>

  <?php
  $has_results = false;
  while ($section = mysqli_fetch_assoc($sections_query)):
    $course = $section['course'];
    $sec    = $section['section'];

    $cstmt = mysqli_prepare($conn,
        "SELECT c.*, u.name as uname FROM candidates c
         JOIN users u ON c.user_id = u.id
         WHERE c.course = ? AND c.section = ? ORDER BY c.votes DESC");
    mysqli_stmt_bind_param($cstmt, "ss", $course, $sec);
    mysqli_stmt_execute($cstmt);
    $cand_result = mysqli_stmt_get_result($cstmt);
    mysqli_stmt_close($cstmt);

    $tstmt = mysqli_prepare($conn,
        "SELECT SUM(votes) as total FROM candidates WHERE course = ? AND section = ?");
    mysqli_stmt_bind_param($tstmt, "ss", $course, $sec);
    mysqli_stmt_execute($tstmt);
    $total_row   = mysqli_fetch_assoc(mysqli_stmt_get_result($tstmt));
    $total_votes = ($total_row['total'] > 0) ? $total_row['total'] : 1;
    mysqli_stmt_close($tstmt);

    $wstmt = mysqli_prepare($conn,
        "SELECT u.name, c.votes FROM candidates c
         JOIN users u ON c.user_id = u.id
         WHERE c.course = ? AND c.section = ? ORDER BY c.votes DESC LIMIT 1");
    mysqli_stmt_bind_param($wstmt, "ss", $course, $sec);
    mysqli_stmt_execute($wstmt);
    $winner = mysqli_fetch_assoc(mysqli_stmt_get_result($wstmt));
    mysqli_stmt_close($wstmt);

    $has_results = true;
  ?>
  <div class="section-card">
    <h3>📚 <?php echo htmlspecialchars($course); ?> — Section <?php echo htmlspecialchars($sec); ?></h3>
    <?php if ($winner && $winner['votes'] > 0): ?>
      <?php if ($election_concluded): ?>
        <div class="declared-winner-box">
          <div class="crown">👑</div>
          <div>
            <div class="winner-name"><?php echo htmlspecialchars($winner['name']); ?></div>
            <div class="winner-label">Elected as Class Representative</div>
          </div>
          <div class="winner-votes">
            <span><?php echo $winner['votes']; ?></span>
            <small>votes won</small>
          </div>
        </div>
      <?php else: ?>
        <div class="winner-badge">🏆 Leading: <?php echo htmlspecialchars($winner['name']); ?> (<?php echo $winner['votes']; ?> votes)</div>
      <?php endif; ?>
    <?php endif; ?>
    <?php
    $rank = 1;
    while ($cand = mysqli_fetch_assoc($cand_result)):
      $pct = $total_votes > 0 ? round(($cand['votes'] / $total_votes) * 100) : 0;
    ?>
    <div class="candidate-row">
      <div class="candidate-name"><?php echo $rank == 1 ? '👑 ' : ''; echo htmlspecialchars($cand['uname']); ?></div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?php echo $pct; ?>%;">
          <?php if ($pct > 15) echo $pct . '%'; ?>
        </div>
      </div>
      <div class="vote-count"><?php echo $cand['votes']; ?> votes (<?php echo $pct; ?>%)</div>
    </div>
    <?php $rank++; endwhile; ?>
  </div>
  <?php endwhile; ?>

  <?php if (!$has_results): ?>
    <div class="section-card">
      <p style="text-align:center; color:#999;">No candidates have been assigned yet. Check back after admin sets up the election.</p>
    </div>
  <?php endif; ?>
</div>
</body>
</html>