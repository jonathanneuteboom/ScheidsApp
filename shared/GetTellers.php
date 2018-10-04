<?php
  require_once("func.inc.php");
  
  $user = GetUser();
  
  if ($user->guest){
    exit(json_encode(array("error" =>"Je bent automatisch uitgelogd, doordat je te lang inactief bent geweest. Log opnieuw in")));
  }
  
  if (!isReferee($user)){
    exit(json_encode(array("error" => "Jij bent geen Scheidsrechter!")));
  }
  
  if (!isScheidsco($user)){
    exit(json_encode(array("error" =>"Jij bent geen ScheidsCo!")));
  }
  
  $dbc = GetDBConnection();
  CheckMatches($dbc);
  $matches = GetMatches();
  
  // Get all the availabilities for all the matches
  $stmt = $dbc->prepare("SELECT M.id, M.date, M.time, M.code, M.user_id, G.title as tellers
                         FROM ScheidsApp_matches M
                         LEFT JOIN J3_usergroups G ON G.id = M.telteam_id
                         WHERE date >= CURDATE()");
  if (!$stmt->execute()){
		echo "Error:";
    print_r($stmt->errorInfo());
    return;
  }
  
  $teloverzicht = GetTelOverzichtPerTeam($dbc);
  
  // And set them in the matches-array
  while ($row = $stmt->fetch()) {
    if (isset($matches[$row['date']][$row['time']][$row['code']]) && $row['tellers'] != null){
      $matches[$row['date']][$row['time']][$row['code']]['tellers'] = array("team" => $row['tellers'], "geteld" => $teloverzicht[$row['tellers']]['geteld']);
    }
  }
  
  $teloverzicht = array_values($teloverzicht);
  
  if (!$stmt->execute()){
		echo "Error:";
    print_r($stmt->errorInfo());
    return;
  }
  
  echo json_encode(array("telschema" => $matches, "teloverzicht" => $teloverzicht));
  $dbc = null;
?>
