<?php
  require_once("func.inc.php");
  
  $user = GetUser();
  if ($user->guest){
    exit("Je bent automatisch uitgelogd, doordat je te lang inactief bent geweest. Log opnieuw in");
  }
  if (!isReferee($user)){
    exit(json_encode(array("error" => "Jij bent geen Scheidsrechter!")));
  }
  $user_id = $user->id;
  
  $dbc = GetDBConnection();
  
  $matches = GetOverzicht($dbc, $user_id);
  $referees = GetReferees($dbc);
  echo json_encode(array("overzicht" => $matches, "scheidsrechters" => $referees));
  $dbc = null;
?>
