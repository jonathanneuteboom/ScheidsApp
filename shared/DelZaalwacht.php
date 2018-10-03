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
  
  if ($input == "" || !isset($input->id)){
    exit("Incorrect input: " . print_r($input, true));
  }
  
  $dbc = GetDBConnection();
  
  // Check if the match already has a referee
  $stmt = $dbc->prepare("DELETE FROM ScheidsApp_zaalwacht where id = :id");
  
  $stmt->bindValue(':id', $input->id, PDO::PARAM_INT);
  
  if (!$stmt->execute()){
		exit("Error:\n" . print_r($stmt->errorInfo(), true));
  }
  
  echo "Verwijderd!";  
?>

