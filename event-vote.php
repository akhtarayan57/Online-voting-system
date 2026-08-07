<?php
session_start();
include("db.php");

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Get all events
$events_query = mysqli_query($conn, "SELECT * FROM events ORDER BY id ASC");

// Handle vote submission
$success = "";
$error   = "";

if (isset($_POST['vote_event'])) {
    $event_id     = intval($_POST['event_id']);
    $candidate_id = intval($_POST['candidate_id']);

    // Check if already voted in this event
    $stmt = mysqli_prepare($conn,
        "SELECT id FROM event_votes WHERE user_id = ? AND event_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $event_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        $error = "You have already voted in this event!";
    } else {
        // Check event is active
        $estmt = mysqli_prepare($conn,
            "SELECT * FROM events WHERE id = ? AND status = 'active'");
        mysqli_stmt_bind_param($estmt, "i", $event_id);
        mysqli_stmt_execute($estmt);
        $eres  = mysqli_stmt_get_result($estmt);
        $event = mysqli_fetch_assoc($eres);
        mysqli_stmt_close($estmt);

        if (!$event) {
            $error = "This event is not active or has ended!";
        } else {
            // Check time not expired
            if ($event['end_time'] && strtotime($event['end_time']) < time()) {
                $error = "Voting time for this event has ended!";
            } else {
                // Add vote
                $stmt2 = mysqli_prepare($conn,
                    "UPDATE event_candidates SET votes = votes + 1 WHERE id = ?");
                mysqli_stmt_bind_param($stmt2, "i", $candidate_id);
                mysqli_stmt_execute($stmt2);
                mysqli_stmt_close($stmt2);

                // Record vote
                $stmt3 = mysqli_prepare($conn,
                    "INSERT INTO event_votes (user_id, event_id, candidate_id) 
                     VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt3, "iii",
                    $user_id, $event_id, $candidate_id);
                mysqli_stmt_execute($stmt3);
                mysqli_stmt_close($stmt3);

                $success = "Vote cast successfully!";
            }
        }
    }
    mysqli_stmt_close($stmt);
}

// Refetch events after vote
$events_query = mysqli_query($conn, "SELECT * FROM events ORDER BY id ASC");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Ullaash - Event Voting</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .event-wrapper {
      max-width: 900px;
      margin: 30px auto;
      padding: 0 20px;
    }
    .event-card {
      background: white;
      border-radius: 12px;
      padding: 28px;
      margin-bottom: 24px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }
    .event-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 12px;
      border-bottom: 2px solid #e94560;
    }
    .event-title { color: #1a1a2e; font-size: 1.2rem; }
    .event-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    .badge-active { background: #eafaf1; color: #1e8449; }
    .badge-pending { background: #fef9e7; color: #d4a017; }
    .badge-ended { background: #fdecea; color: #c0392b; }
    .candidates-row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }
    .candidate-btn {
      background: #f0f4f8;
      border: 2px solid #ddd;
      border-radius: 8px;
      padding: 12px 20px;
      cursor: pointer;
      transition: all 0.2s;
      font-size: 0.95rem;
      font-weight: 600;
      color: #1a1a2e;
    }
    .candidate-btn:hover {
      border-color: #e94560;
      background: #fdecea;
    }
    .timer {
      font-size: 0.9rem;
      color: #e94560;
      font-weight: 600;
      margin-bottom: 12px;
    }
    .winner-box {
      background: linear-gradient(135deg, #f39c12, #e67e22);
      color: white;
      padding: 16px;
      border-radius: 8px;
      text-align: center;
      font-size: 1.1rem;
      font-weight: 600;
    }
    .fest-header {
      text-align: center;
      margin-bottom: 30px;
    }
    .fest-header h1 {
      font-size: 2rem;
      color: #1a1a2e;
    }
    .fest-header p {
      color: #777;
    }
    .votes-bar {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 6px;
      font-size: 0.85rem;
      color: #555;
    }
  </style>
</head>
<body>
  <?php include("loading.php"); ?>

<script>
// Loading screen functionality
document.addEventListener('DOMContentLoaded', function() {
  const loadingScreen = document.getElementById('loadingScreen');
  
  function showLoading() {
    if (loadingScreen) {
      loadingScreen.classList.add('show');
    }
  }
  
  // Hide loading when page is fully loaded
  window.addEventListener('load', function() {
    if (loadingScreen) {
      setTimeout(function() {
        loadingScreen.classList.remove('show');
      }, 100);
    }
  });
  
  // Show loading on all internal link clicks
  document.querySelectorAll('a[href]').forEach(function(link) {
    link.addEventListener('click', function(e) {
      var href = this.getAttribute('href');
      if (href && !href.startsWith('#') && 
          !href.startsWith('http://') && 
          !href.startsWith('https://') &&
          !href.startsWith('javascript:')) {
        showLoading();
      }
    });
  });
  
  // Show loading on form submissions
  document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function() {
      showLoading();
    });
  });
});
</script>

<div class="top-bar">
  <img src="college-banner.png" alt="SRMU"
       style="height:45px; object-fit:contain;
              background:white; padding:4px 10px;
              border-radius:6px;">
  <div class="top-bar-links">
    <a href="dashboard.php">← Dashboard</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<div class="event-wrapper">

  <div class="fest-header">
    <h1>🎉 Ullaash Fest Voting</h1>
    <p>Vote for your favourite participants in each event</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
  <?php endif; ?>

  <?php while ($event = mysqli_fetch_assoc($events_query)): 
    $event_id = $event['id'];

    // Check if user already voted in this event
    $vstmt = mysqli_prepare($conn,
        "SELECT id FROM event_votes WHERE user_id = ? AND event_id = ?");
    mysqli_stmt_bind_param($vstmt, "ii", $user_id, $event_id);
    mysqli_stmt_execute($vstmt);
    mysqli_stmt_store_result($vstmt);
    $user_voted = mysqli_stmt_num_rows($vstmt) > 0;
    mysqli_stmt_close($vstmt);

    // Get candidates
    $cstmt = mysqli_prepare($conn,
        "SELECT * FROM event_candidates WHERE event_id = ? ORDER BY votes DESC");
    mysqli_stmt_bind_param($cstmt, "i", $event_id);
    mysqli_stmt_execute($cstmt);
    $candidates = mysqli_stmt_get_result($cstmt);
    mysqli_stmt_close($cstmt);

    // Get total votes for this event
    $tvstmt = mysqli_prepare($conn,
        "SELECT SUM(votes) as total FROM event_candidates WHERE event_id = ?");
    mysqli_stmt_bind_param($tvstmt, "i", $event_id);
    mysqli_stmt_execute($tvstmt);
    $tvres   = mysqli_stmt_get_result($tvstmt);
    $tvrow   = mysqli_fetch_assoc($tvres);
    $total_v = $tvrow['total'] > 0 ? $tvrow['total'] : 1;
    mysqli_stmt_close($tvstmt);

    // Calculate time remaining
    $time_remaining = "";
    $is_expired     = false;
    if ($event['end_time']) {
        $remaining = strtotime($event['end_time']) - time();
        if ($remaining > 0) {
            $mins = floor($remaining / 60);
            $secs = $remaining % 60;
            $time_remaining = "⏱ {$mins}m {$secs}s remaining";
        } else {
            $is_expired     = true;
            $time_remaining = "⏱ Voting ended";
        }
    }

    // Get winner if ended
    $winner = null;
    if ($event['winner_id']) {
        $wstmt = mysqli_prepare($conn,
            "SELECT name FROM event_candidates WHERE id = ?");
        mysqli_stmt_bind_param($wstmt, "i", $event['winner_id']);
        mysqli_stmt_execute($wstmt);
        $wres   = mysqli_stmt_get_result($wstmt);
        $winner = mysqli_fetch_assoc($wres);
        mysqli_stmt_close($wstmt);
    }
  ?>

  <div class="event-card">
    <div class="event-header">
      <h3 class="event-title">
        <?php
        $icons = ['Fashion Walk'=>'👗','Dance'=>'💃',
                  'Singing'=>'🎤','Tug of War'=>'💪'];
        $icon  = isset($icons[$event['name']]) ? $icons[$event['name']] : '🎯';
        echo $icon . ' ' . htmlspecialchars($event['name']);
        ?>
      </h3>
      <span class="event-badge 
        <?php 
        if ($event['status'] == 'active') echo 'badge-active';
        elseif ($event['status'] == 'ended') echo 'badge-ended';
        else echo 'badge-pending';
        ?>">
        <?php echo ucfirst($event['status']); ?>
      </span>
    </div>

    <?php if ($time_remaining): ?>
      <div class="timer"><?php echo $time_remaining; ?></div>
    <?php endif; ?>

    <?php if ($winner): ?>
      <div class="winner-box">
        🏆 Winner: <?php echo htmlspecialchars($winner['name']); ?>
      </div>

    <?php elseif ($event['status'] == 'active' && !$user_voted && !$is_expired): ?>
      <form method="POST">
        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
        <div class="candidates-row">
          <?php
          // Reset candidates result
          $cstmt2 = mysqli_prepare($conn,
              "SELECT * FROM event_candidates WHERE event_id = ?");
          mysqli_stmt_bind_param($cstmt2, "i", $event_id);
          mysqli_stmt_execute($cstmt2);
          $candidates2 = mysqli_stmt_get_result($cstmt2);
          mysqli_stmt_close($cstmt2);

          while ($c = mysqli_fetch_assoc($candidates2)):
          ?>
          <button type="submit" name="vote_event" 
                  class="candidate-btn"
                  value="vote"
                  onclick="this.form.candidate_id.value=<?php echo $c['id']; ?>">
            <?php echo htmlspecialchars($c['name']); ?>
          </button>
          <?php endwhile; ?>
          <input type="hidden" name="candidate_id" value="">
        </div>
      </form>

    <?php elseif ($user_voted): ?>
      <p style="color:green; font-weight:600;">✅ You have voted in this event!</p>
      <!-- Show results -->
      <?php
      $cstmt3 = mysqli_prepare($conn,
          "SELECT * FROM event_candidates WHERE event_id = ? ORDER BY votes DESC");
      mysqli_stmt_bind_param($cstmt3, "i", $event_id);
      mysqli_stmt_execute($cstmt3);
      $candidates3 = mysqli_stmt_get_result($cstmt3);
      mysqli_stmt_close($cstmt3);

      while ($c = mysqli_fetch_assoc($candidates3)):
        $pct = round(($c['votes'] / $total_v) * 100);
      ?>
      <div class="votes-bar">
        <span style="min-width:120px"><?php echo htmlspecialchars($c['name']); ?></span>
        <div style="flex:1;background:#eee;border-radius:10px;height:12px;">
          <div style="width:<?php echo $pct; ?>%;background:#e94560;
                      height:100%;border-radius:10px;"></div>
        </div>
        <span><?php echo $c['votes']; ?> (<?php echo $pct; ?>%)</span>
      </div>
      <?php endwhile; ?>

    <?php elseif ($event['status'] == 'pending'): ?>
      <p style="color:#d4a017;">⏳ This event voting has not started yet.</p>

    <?php elseif ($event['status'] == 'ended' || $is_expired): ?>
      <p style="color:#c0392b;">🔒 Voting for this event has ended.</p>
      <?php
      $cstmt4 = mysqli_prepare($conn,
          "SELECT * FROM event_candidates WHERE event_id = ? ORDER BY votes DESC");
      mysqli_stmt_bind_param($cstmt4, "i", $event_id);
      mysqli_stmt_execute($cstmt4);
      $candidates4 = mysqli_stmt_get_result($cstmt4);
      mysqli_stmt_close($cstmt4);

      while ($c = mysqli_fetch_assoc($candidates4)):
        $pct = round(($c['votes'] / $total_v) * 100);
      ?>
      <div class="votes-bar">
        <span style="min-width:120px"><?php echo htmlspecialchars($c['name']); ?></span>
        <div style="flex:1;background:#eee;border-radius:10px;height:12px;">
          <div style="width:<?php echo $pct; ?>%;background:#e94560;
                      height:100%;border-radius:10px;"></div>
        </div>
        <span><?php echo $c['votes']; ?> (<?php echo $pct; ?>%)</span>
      </div>
      <?php endwhile; ?>
    <?php endif; ?>

  </div>

  <?php endwhile; ?>

</div>

<!-- Auto refresh every 30 seconds to update timers -->
<script>
setTimeout(function(){ location.reload(); }, 30000);
</script>

</body>
</html>