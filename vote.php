<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include("db.php");

$user_id      = intval($_SESSION['user_id']);
$candidate_id = intval($_POST['candidate_id']);

if ($candidate_id <= 0) {
    header("Location: dashboard.php?error=invalid");
    exit();
}

// Check CR voting is active
$cr_st_q  = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='cr_voting_status'");
$cr_status = mysqli_fetch_assoc($cr_st_q)['setting_value'] ?? 'pending';

$cr_et_q  = mysqli_query($conn, "SELECT setting_value FROM voting_settings WHERE setting_name='cr_voting_end_time'");
$cr_end   = mysqli_fetch_assoc($cr_et_q)['setting_value'] ?? null;
$cr_ended = $cr_end && strtotime($cr_end) < time();

if ($cr_status != 'active' || $cr_ended) {
    header("Location: dashboard.php?error=voting_closed");
    exit();
}

mysqli_begin_transaction($conn);

try {
    // Lock user and check
    $check = mysqli_query($conn, "SELECT vote, verified FROM users WHERE id = $user_id FOR UPDATE");
    $user_data = mysqli_fetch_assoc($check);

    if (!$user_data) throw new Exception("User not found");
    if ($user_data['vote'] == 1) {
        mysqli_rollback($conn);
        header("Location: dashboard.php?error=already_voted");
        exit();
    }
    if ($user_data['verified'] != 1) {
        mysqli_rollback($conn);
        header("Location: dashboard.php?error=not_verified");
        exit();
    }

    // Check candidate exists
    $ccheck = mysqli_query($conn, "SELECT id FROM candidates WHERE id = $candidate_id");
    if (mysqli_num_rows($ccheck) == 0) throw new Exception("Candidate not found");

    // Count vote immediately (since verified)
    mysqli_query($conn, "UPDATE candidates SET votes = votes + 1 WHERE id = $candidate_id");
    mysqli_query($conn, "UPDATE users SET vote = 1, voted_for = $candidate_id WHERE id = $user_id");

    mysqli_commit($conn);
    header("Location: dashboard.php?success=voted");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    header("Location: dashboard.php?error=failed");
    exit();
}
?>