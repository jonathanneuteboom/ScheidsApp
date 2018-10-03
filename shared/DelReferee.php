<?php 
  require_once("func.inc.php");
  
  $user = GetUser();
  if ($user->guest){
    exit("Je bent automatisch uitgelogd, doordat je te lang inactief bent geweest. Log opnieuw in");
  }
  
  if (!isScheidsco($user)){
    exit(json_encode(array("error" =>"Jij bent geen ScheidsCo!")));
  }
  
  $input = json_decode(file_get_contents("php://input"));
  
  if ($input == "" || !isset($input->user_id) || !isset($input->code)){
    exit("Incorrect input: " . print_r($input, true));
  }
  
  $dbc = GetDBConnection();
  
  // First set the code to the referee
  $stmt = $dbc->prepare("DELETE FROM ScheidsApp_matches
                         WHERE code=:code and user_id=:user_id");
  
  $stmt->bindValue(':user_id', $input->user_id, PDO::PARAM_STR);
  $stmt->bindValue(':code', $input->code, PDO::PARAM_STR);

  if (!$stmt->execute()){
		exit("Error:\n" . print_r($stmt->errorInfo(), true));
  }
  
  // first rewrite all the google calendar files
  SetGoogleCalendars($dbc, $input->user_id);
  
  echo "Opgeslagen";  
?>
