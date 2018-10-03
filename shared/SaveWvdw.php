<?php 
  require_once("func.inc.php");
  
  $user = GetUser();
  if ($user->guest){
    exit("Je bent automatisch uitgelogd, doordat je te lang inactief bent geweest. Log opnieuw in");
  }
  
  if (!isReferee($user)){
    exit(json_encode(array("error" => "Jij bent geen Scheidsrechter!")));
  }
  
  $input = json_decode(file_get_contents("php://input"));
  
  if ($input == "" || !isset($input->date) || 
                      !isset($input->code)){
    exit("Incorrect input");
  }
  
  $date = $input->date;
  $code = $input->code;
  
  $dbc = GetDBConnection();
  
  $query = "INSERT INTO ScheidsApp_zaalwacht (date, wvdw) 
            VALUES (:date, :code)
            ON DUPLICATE KEY UPDATE wvdw = :code";
  $stmt = $dbc->prepare($query);
  $stmt->bindValue(":date", $date, PDO::PARAM_STR);
  $stmt->bindValue(":code", $code, PDO::PARAM_STR);
  if (!$stmt->execute()){
    exit(print_r($stmt->errorInfo(), 1));
  }
  exit("Gelukt");
  
?>
