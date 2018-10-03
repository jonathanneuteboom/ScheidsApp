<?php 
  require_once("func.inc.php");
  
  $user = GetUser();
  if ($user->guest){
    exit("Je bent automatisch uitgelogd, doordat je te lang inactief bent geweest. Log opnieuw in");
  }
  
  if (!isScheidsco($user)){
    return json_encode(array("error" =>"Jij bent geen ScheidsCo!"));
  }
  
  $input = json_decode(file_get_contents("php://input"));
  
  if ($input == "" || !isset($input->date) || 
                      !isset($input->time) || 
                      !isset($input->user_id) || 
                      !isset($input->code)){
    exit("Incorrect input: " . print_r($input, true));
  }
  
  $dbc = GetDBConnection();
  
  // Check if the match already has a referee
  $previous_refid = "";
  $stmt = $dbc->prepare("SELECT * 
                         FROM ScheidsApp_matches 
                         WHERE code=:code");
  
  $stmt->bindValue(':code', $input->code, PDO::PARAM_STR);
  
  if (!$stmt->execute()){
		exit("Error:\n" . print_r($stmt->errorInfo(), true));
  }
  else {
    $result = $stmt->fetchAll();
    if (count($result) > 0){
      $previous_refid = $result[0]['user_id'];
    }
  }
  
  $stmt = $dbc->prepare("INSERT INTO ScheidsApp_matches (date, time, code, user_id)
                         VALUES (:date, :time, :code, :user_id)
                         ON DUPLICATE KEY
                         UPDATE code=:code,
                         user_id=:user_id");
  
  $stmt->bindValue(':date', $input->date, PDO::PARAM_STR);
  $stmt->bindValue(':time', $input->time, PDO::PARAM_STR);
  $stmt->bindValue(':code', $input->code, PDO::PARAM_STR);
  $stmt->bindValue(':user_id', $input->user_id, PDO::PARAM_STR);
  
  if (!$stmt->execute()){
		exit("Error:\n" . print_r($stmt->errorInfo(), true));
  }
    
  // first rewrite all the google calendar files
  SetGoogleCalendars($dbc, $input->user_id);
  
  // Recalculate the sche
  if ($previous_refid != ""){
    SetGoogleCalendars($dbc, $previous_refid);
  }
  echo "Opgeslagen";  
?>
