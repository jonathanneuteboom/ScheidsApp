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
  
  $input = json_decode(file_get_contents("php://input"));
  
  if ($input == "" || !isset($input->availability) || 
                      !isset($input->date) || 
                      !isset($input->time)){
    exit("Incorrect input");
  }
  
  $dbc = GetDBConnection();
  
  // Check if the user is set to referee that timeslot
  if ($input->availability != 'Ja'){
    $stmt = $dbc->prepare("SELECT * FROM ScheidsApp_matches
                           WHERE user_id = :user_id and 
                                 date = :date and
                                 time = :time");
    
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':date', $input->date, PDO::PARAM_STR);
    $stmt->bindValue(':time', $input->time, PDO::PARAM_STR);
    
    if (!$stmt->execute()){
      echo "Error:";
      print_r($stmt->errorInfo());
    }
    else {
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!empty($result)){
        exit("Je bent al ingedeeld voor dit tijdslot en kan dit niet veranderen");
      }
    }
  }
  $stmt = $dbc->prepare("INSERT INTO ScheidsApp_availability (date, time, user_id, availability)
                         VALUES (:date, :time, :user_id, :availability)
                         ON DUPLICATE KEY
                         UPDATE availability=:availability");
  
  $input = json_decode(file_get_contents("php://input"));
  
  if ($input == "" || !isset($input->availability) || 
                      !isset($input->date) || 
                      !isset($input->time)){
    exit("Incorrect input");
  }
  
  $stmt->bindValue(':availability', $input->availability , PDO::PARAM_STR);
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
