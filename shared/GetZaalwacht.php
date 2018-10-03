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
  
  $stmt = $dbc->prepare("SELECT * FROM ScheidsApp_zaalwacht");
  if (!$stmt->execute()){
		exit(print_r($stmt->errorInfo()));
  }
  
  $wvdws = [];
  while ($row = $stmt->fetch()){
    $wvdws[$row["date"]] = $row["wvdw"];
  }
  
  foreach ($matches as $date => $timeslot){
    foreach ($timeslot as $time => $match){
      foreach ($match as $code => $match_info){
        $zaalwacht[$date]['teams'][] = [
          "teams" => $match_info['teams'], 
          "wvdw" => isset($wvdws[$date]) && $wvdws[$date] == $match_info["match_id"],
          "code" => $match_info["match_id"]
        ];
      }
    }
  }
  
  
  // Get all the availabilities for all the matches
  $stmt = $dbc->prepare("SELECT * FROM ScheidsApp_zaalwacht WHERE date >= CURDATE()");
  if (!$stmt->execute()){
		echo "Error:";
    print_r($stmt->errorInfo());
    return;
  }
  
  $zaalwachtoverzicht = GetZaalwachtOverzichtPerTeam($dbc);
  
  // And set them in the matches-array
  while ($row = $stmt->fetch()) {
    if (isset($matches[$row['date']]) && $row['team'] != null){
      $zaalwacht[$row['date']]['zaalwacht'] = [
         "id" => $row["id"],
         "team" => $row['team'], 
         "gefloten" => $zaalwachtoverzicht[$row['team']]['zaalwacht']
      ];
    }
  }
  
  $zaalwachtoverzicht = array_values($zaalwachtoverzicht);
  
  if (!$stmt->execute()){
		echo "Error:";
    print_r($stmt->errorInfo());
    return;
  }
  
  echo json_encode(array("zaalwachtschema" => $zaalwacht, "zaalwachtoverzicht" => $zaalwachtoverzicht));
  $dbc = null;
?>
