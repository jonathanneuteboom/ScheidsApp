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
  
  $referees = GetReferees($dbc);
  foreach ($referees as $ref_id =>$ref){
    $ref_ids[$ref_id] = $ref_id;
  }

  foreach ($matches as $date => $times){
    foreach ($times as $time => $codes){
      foreach ($codes as $code => $match_info){
        if (isset($matches[$date][$time][$code])){
          $matches[$date][$time][$code]['Nog niet ingevuld'] = $ref_ids;
        }
      }
    }
  }
  
  // Get all the availabilities for all the matches
  $stmt = $dbc->prepare("SELECT T3.user_id, T3.date, T3.time, T3.availability, T3.remarks, IF(ISNULL(T4.count),0,T4.count) as count
                         FROM (
                           SELECT * 
                           FROM ScheidsApp_availability
                           WHERE availability != ''
                         ) as T3
                         LEFT JOIN (
                           SELECT user_id, count(user_id) as count 
                           FROM ScheidsApp_matches
                           WHERE code != '' 
                           GROUP BY user_id
                         ) as T4
                         ON T3.user_id = T4.user_id
                         ORDER BY date ASC, time ASC, count ASC");
  if (!$stmt->execute()){
		echo "Error:";
    print_r($stmt->errorInfo());
    return;
  }
  
  // And set them in the matches-array
  while ($row = $stmt->fetch()) {
    if (isset($matches[$row['date']][$row['time']])){
      foreach ($matches[$row['date']][$row['time']] as $code => $match){
        $matches[$row['date']][$row['time']][$code][$row['availability']][] = array("user_id" => $row['user_id'], 
                                                                                    "remarks" => $row['remarks']);
        unset($matches[$row['date']][$row['time']][$code]['Nog niet ingevuld'][$row['user_id']]);        
      }
    }
  }
  
  // Get all the already appointer referees and put them in the matches-array
  $stmt = $dbc->prepare("SELECT * 
                         FROM ScheidsApp_matches
                         WHERE code != ''");
  
  if (!$stmt->execute()){
		echo "Error:";
    print_r($stmt->errorInfo());
    return;
  }
  
  while ($row = $stmt->fetch()) {
    if (strtotime($row['date']) >= time()){ 
      $matches[$row['date']][$row['time']][$row['code']]['scheids'] = $row['user_id'];
    }
  }
    
  echo json_encode(array("overzicht" => $matches, 
                         "scheidsrechters" => $referees, 
                         "geflotenPerTeam" => GetScheduledRefereesPerTeam($dbc), 
                         "teamPerScheids" => GetTeamPerReferee($dbc)));/*, 
                         "results" => GetResults($dbc)));*/
  $dbc = null;
?>
