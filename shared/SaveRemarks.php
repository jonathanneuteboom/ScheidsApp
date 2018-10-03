<?php 
  require_once("func.inc.php");
  
  $user = GetUser();
  if ($user->guest){
    return json_encode(array("error" =>"Je bent automatisch uitgelogd, doordat je te lang inactief bent geweest. Log opnieuw in"));
  }
  if (!isReferee($user)){
    exit(json_encode(array("error" => "Jij bent geen Scheidsrechter!")));
  }
  $user_id = $user->id;
  
  $dbc = GetDBConnection();
  $stmt = $dbc->prepare("INSERT INTO ScheidsApp_availability (date, time, user_id, remarks)
                         VALUES (:date, :time, :user_id, :remarks)
                         ON DUPLICATE KEY
                         UPDATE remarks=:remarks");
  
  $input = json_decode(file_get_contents("php://input"));
  
  if ($input == "" || !isset($input->remarks) || 
                      !isset($input->date) || 
                      !isset($input->time)){
    exit("Incorrect input");
  }
  
  $stmt->bindValue(':remarks', $input->remarks , PDO::PARAM_STR);
  $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
  $stmt->bindValue(':date', $input->date, PDO::PARAM_STR);
  $stmt->bindValue(':time', $input->time, PDO::PARAM_STR);
  
  if (!$stmt->execute()){
		echo "Error:";
    print_r($stmt->errorInfo());
  }
  else {
    echo "Opgeslagen";
  }
?>
