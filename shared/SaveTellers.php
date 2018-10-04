<?php
require_once "func.inc.php";

$user = GetUser();
if ($user->guest) {
    exit("Je bent automatisch uitgelogd, doordat je te lang inactief bent geweest. Log opnieuw in");
}

if (!isScheidsco($user)) {
    return json_encode(array("error" => "Jij bent geen ScheidsCo!"));
}

$input = json_decode(file_get_contents("php://input"));

if ($input == "" || !isset($input->date) ||
    !isset($input->time) ||
    !isset($input->code) ||
    !isset($input->tellers)) {
    exit("Incorrect input: " . print_r($input, true));
}

$dbc = GetDBConnection();

if ($input->tellers == "") {
    $input->tellers = null;
}

// Check if the match already has a referee
$stmt = $dbc->prepare("SELECT id FROM J3_usergroups WHERE title = :tellers");
$stmt->bindValue(':tellers', $input->tellers, PDO::PARAM_STR);

if (!$stmt->execute()) {
    exit("Error:\n" . print_r($stmt->errorInfo(), true));
}
$row = $stmt->fetch();
$teamId = $row['id'];

$stmt = $dbc->prepare("INSERT INTO ScheidsApp_matches
                       (date, time, code, telteam_id, user_id)
                       VALUES (:date, :time, :code, :telteamId, NULL)
                       ON DUPLICATE KEY UPDATE telteam_id = :telteamId");

$stmt->bindValue(':date', $input->date, PDO::PARAM_STR);
$stmt->bindValue(':time', $input->time, PDO::PARAM_STR);
$stmt->bindValue(':code', $input->code, PDO::PARAM_STR);
$stmt->bindValue(':telteamId', $teamId, PDO::PARAM_INT);

if (!$stmt->execute()) {
    exit("Error:\n" . print_r($stmt->errorInfo(), true));
}

echo "Opgeslagen";
