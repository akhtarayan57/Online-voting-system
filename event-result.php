<?php
// This file auto-declares winners when time expires
// Called automatically by event-vote.php
include("db.php");

// Get all active events where time has expired
$query = mysqli_query($conn,
    "SELECT * FROM events 
     WHERE status = 'active' 
     AND end_time IS NOT NULL 
     AND end_time < NOW()");

while ($event = mysqli_fetch_assoc($query)) {
    // Find winner (candidate with most votes)
    $wquery = mysqli_query($conn,
        "SELECT id FROM event_candidates 
         WHERE event_id = {$event['id']} 
         ORDER BY votes DESC LIMIT 1");
    $winner = mysqli_fetch_assoc($wquery);

    if ($winner) {
        // Update event with winner and mark as ended
        $stmt = mysqli_prepare($conn,
            "UPDATE events SET status='ended', winner_id=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ii", $winner['id'], $event['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>