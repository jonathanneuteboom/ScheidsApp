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
    !isset($input->team)) {
    exit("Incorrect input: " . print_r($input, true));
}

if ($input->team == "") {
    $input->team = null;
}

$dbc = GetDBConnection();

$query = "SELECT id
          FROM J3_usergroups
          WHERE title = :team";
$stmt = $dbc->prepare($query);
$stmt->bindValue(':team', $input->team, PDO::PARAM_STR);
if (!$stmt->execute()) {
    exit("Error:\n" . print_r($stmt->errorInfo(), true));
}

$row = $stmt->fetch();
$teamId = $row['id'];

// Check if the match already has a referee
$query = "INSERT INTO ScheidsApp_zaalwacht
          (date, team_id)
          VALUES (:date, :teamId)
          ON DUPLICATE KEY UPDATE team_id = :teamId";
$stmt = $dbc->prepare($query);

$stmt->bindValue(':date', $input->date, PDO::PARAM_STR);
$stmt->bindValue(':teamId', $teamId, PDO::PARAM_INT);

if (!$stmt->execute()) {
    exit("Error:\n" . print_r($stmt->errorInfo(), true));
}

echo "Opgeslagen";
