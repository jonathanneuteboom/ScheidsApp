<?php
  require_once("func.inc.php");
  
	function CheckOverlap($SKC_matches, $user_matches){
    foreach ($SKC_matches as $date => $timeslots){
      if (isset($user_matches[$date])){
        foreach ($timeslots as $time => $matches){
          foreach ($user_matches[$date] as $user_time => $user_matches_array){
            reset($user_matches[$date][$user_time]);
            $first_key = key($user_matches[$date][$user_time]);
            $difference = abs($time - $user_time);
            if ($difference < 200){
              $class = "impossible";
            } else if ($difference < 400){
              $user_location = $user_matches[$date][$user_time][$first_key]['gym_name'];
              
              if ($user_location == "Universitair SC"){
                $class = "possible";
              } 
              else {
                $class = "maybe";
              }
            }
            else {
              $class = "possible";
            }
            $item = $user_matches_array[$first_key];
            $item['class'] = $class;
            
            $SKC_matches[$date][$time] = array('overlap' => $item) + $SKC_matches[$date][$time];
          }
        }
      }
    }
    return $SKC_matches;
  }

  $user = GetUser();
  if ($user->guest){
    exit("Je bent automatisch uitgelogd, doordat je te lang inactief bent geweest. Log opnieuw in");
  }
  if (!isReferee($user)){
    exit(json_encode(array("error" => "Jij bent geen Scheidsrechter!")));
  }
  $user_id = $user->id;
  
  $dbc = GetDBConnection();
  
  
  $SKC_matches = GetMatches();
  
  $url = GetUserURL($user_id, $dbc);
  $user_matches = GetMatches($url);
  
  $matches_with_overlap = CheckOverlap($SKC_matches, $user_matches);
  
  $dbc = GetDBConnection();
  $stmt = $dbc->prepare("SELECT date, time, availability, remarks
                         FROM ScheidsApp_availability
                         WHERE user_id = :user_id and date >= CURDATE()");
  
  $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
  
  if (!$stmt->execute()){
    exit(print_r($stmt->errorInfo()));
  }
  else {
		$rows = $stmt->fetchAll();
    foreach ($rows as $row){
      $matches_with_overlap[$row['date']][$row['time']]['availability'] = $row['availability'];
      $matches_with_overlap[$row['date']][$row['time']]['remarks'] = $row['remarks'];
    }
	}
	
  echo json_encode($matches_with_overlap);
  $dbc = null;
?>
