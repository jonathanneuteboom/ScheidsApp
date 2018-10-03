<?php
  ini_set('include_path', ini_get('include_path').':../../libs/PHPExcel/Classes/');
  include 'PHPExcel.php';
  include 'PHPExcel/Writer/Excel2007.php';
  include(realpath(dirname(__FILE__)."/../../libs/PHPMailer/PHPMailerAutoload.php" ));
  
  setlocale(LC_ALL, 'nl_NL.UTF-8');  
    
  $SKC_teams = array(
             "Dames 1" => "SKC DS 1",
             "Dames 2" => "SKC DS 2",
             "Dames 3" => "SKC DS 3",
             "Dames 4" => "SKC DS 4",
             "Dames 5" => "SKC DS 5",
             "Dames 6" => "SKC DS 6",
             "Dames 7" => "SKC DS 7",
             "Dames 8" => "SKC DS 8",
             "Dames 9" => "SKC DS 9",
             "Dames 10" => "SKC DS 10",
             "Dames 11" => "SKC DS 11",
             "Dames 12" => "SKC DS 12",
             "Dames 13" => "SKC DS 13",
             "Dames 14" => "SKC DS 14",
             "Dames 15" => "SKC DS 15",
             
             "Heren 1" => "SKC HS 1",
             "Heren 2" => "SKC HS 2",
             "Heren 3" => "SKC HS 3",
             "Heren 4" => "SKC HS 4",
             "Heren 5" => "SKC HS 5",
             "Heren 6" => "SKC HS 6",
             "Heren 7" => "SKC HS 7",
             "Heren 8" => "SKC HS 8"
           );  
  function LoadJoomla(){
    define( '_JEXEC', 1 );
    define( 'JPATH_BASE', realpath(dirname(__FILE__).'/../../..' ));
    
    require_once ( JPATH_BASE. '/includes/defines.php' );
    require_once ( JPATH_BASE. '/includes/framework.php' );
    $mainframe = JFactory::getApplication('site');
    $mainframe->initialise();
    
    $session = JFactory::getSession();
  }
  
  function GetUser(){
    LoadJoomla();
    return JFactory::getUser();
  }
  
  function GetDBConnection(){
    require_once(JPATH_BASE . "/configuration.php");
    $config = new JConfig();
    $host = $config->host;
    $db = $config->db;
    $user = $config->user;
    $password = $config->password;
    $dbc = new PDO("mysql:host=$host;dbname=$db", $user, $password);
    return $dbc;
  }
  
  function GetTeams(){
    global $SKC_teams;
    $t = array();
    foreach ($SKC_teams as $team){
      $t[] = $team;
    }
    return $t;
  }
  
  function GetTelOverzichtPerTeam($dbc){
    $stmt = $dbc->prepare("SELECT tellers as teamnaam, count(tellers) as count
                           FROM ScheidsApp_matches 
                           WHERE tellers IS NOT NULL 
                           GROUP BY tellers");
    if (!$stmt->execute()){
      echo "Error:";
      exit(print_r($stmt->errorInfo()));
    }
    $result = $stmt->fetchAll();
    $teloverzicht = array();
    
    global $SKC_teams;
    foreach ($SKC_teams as $team => $nevobo_format){
      $teloverzicht[$team] = array("team" => $team, "geteld" => 0);
    }
    
    foreach((array)$result as $row){
      $teloverzicht[$row['teamnaam']] = array("team" => $row['teamnaam'], "geteld" => $row['count']);
    }
    
    return $teloverzicht;
  }
  
  function GetZaalwachtOverzichtPerTeam($dbc){
    $stmt = $dbc->prepare("SELECT team, count(team) as count
                           FROM ScheidsApp_zaalwacht
                           WHERE team IS NOT NULL 
                           GROUP BY team");
    if (!$stmt->execute()){
      echo "Error:";
      exit(print_r($stmt->errorInfo()));
    }
    $result = $stmt->fetchAll();
    $zaalwachtoverzicht = array();
    
    global $SKC_teams;
    foreach ($SKC_teams as $team => $nevobo_format){
      $zaalwachtoverzicht[$team] = array("team" => $team, "zaalwacht" => 0);
    }
    
    foreach((array)$result as $row){
      $zaalwachtoverzicht[$row['team']] = array("team" => $row['team'], "zaalwacht" => $row['count']);
    }
    
    return $zaalwachtoverzicht;
  }
  
  
  function GetApiKey($dbc){
    $stmt = $dbc->prepare("SELECT * FROM WAPI_users WHERE username = 'SKC'");
    if (!$stmt->execute()){
      echo "Error:";
      exit(print_r($stmt->errorInfo()));
    }
    $result = $stmt->fetch();
    return $result['api_key'];
  }
  
  function GetTeamPerReferee($dbc){
    $stmt = $dbc->prepare("SELECT T3.user_id, T4.title 
                           FROM (
                             SELECT T1.user_id, T2.group_id FROM (
                               SELECT G.title, M.user_id, M.group_id 
                               FROM J3_usergroups G
                               INNER JOIN J3_user_usergroup_map M
                               ON G.id = M.group_id
                               WHERE G.title = 'Scheidsrechters'
                             ) as T1
                             INNER JOIN J3_user_usergroup_map T2
                             ON T1.user_id = T2.user_id
                           ) T3
                           LEFT JOIN J3_usergroups T4
                           ON T3.group_id = T4.id
                           WHERE T4.title LIKE 'Heren %' or T4.title LIKE 'Dames %'");
    if (!$stmt->execute()){
      echo "Error:";
      exit(print_r($stmt->errorInfo()));
    }
    $rows = $stmt->fetchAll();
    
    foreach ($rows as $row){
      $result[$row['user_id']] = $row['title'];
    }
    return $result;
  }
  
  function GetScheduledRefereesPerTeam($dbc){
    $stmt = $dbc->prepare("SELECT title as teamnaam, count(title) as count FROM J3_usergroups as G
                           INNER JOIN (
                             select id, date, time, code, S.user_id, group_id from ScheidsApp_matches S
                             INNER JOIN 
                             J3_user_usergroup_map M
                             ON S.user_id = M.user_id
                           ) as T1
                           ON G.id = T1.group_id
                           WHERE G.title LIKE 'Heren %' or G.title LIKE 'Dames %'
                           GROUP BY title
                           ORDER BY (CASE title
                             WHEN 'Dames 1'  THEN 1
                             WHEN 'Dames 2'  THEN 2
                             WHEN 'Dames 3'  THEN 3
                             WHEN 'Dames 4'  THEN 4
                             WHEN 'Dames 5'  THEN 5
                             WHEN 'Dames 6'  THEN 6
                             WHEN 'Dames 7'  THEN 7
                             WHEN 'Dames 8'  THEN 8
                             WHEN 'Dames 9'  THEN 9
                             WHEN 'Dames 10' THEN 10
                             WHEN 'Dames 11' THEN 11
                             WHEN 'Dames 12' THEN 12
                             WHEN 'Dames 13' THEN 13
                             WHEN 'Dames 14' THEN 14
                             WHEN 'Dames 15' THEN 15
                             WHEN 'Dames 16' THEN 16
                             WHEN 'Heren 1'  THEN 17
                             WHEN 'Heren 2'  THEN 18
                             WHEN 'Heren 3'  THEN 19                    
                             WHEN 'Heren 4'  THEN 20          
                             WHEN 'Heren 5'  THEN 21         
                             WHEN 'Heren 6'  THEN 22
                             WHEN 'Heren 7'  THEN 23
                             WHEN 'Heren 8'  THEN 24
                           END) ASC");
    if (!$stmt->execute()){
      echo "Error:";
      exit(print_r($stmt->errorInfo()));
    }
    $rows = $stmt->fetchAll();
    
    $result = array();
    foreach ($rows as $row){
      $result[$row['teamnaam']] = $row['count'];
    }
    return $result;
  }
  
  function isReferee($user){
    // Scheidsrechter == 53
    return array_key_exists(53, $user->{'groups'});
  }
  
  function isScheidsco($user){
    // ScheidsCo == 56
    return array_key_exists(56, $user->{'groups'});
  }
  
  function utf8_converter($array){
    array_walk_recursive($array, function(&$item, $key){
      if(!mb_detect_encoding($item, 'utf-8', true)){
        $item = utf8_encode($item);
      }
    });

    return $array;
  }
  
  function GetReferees($dbc){
    $stmt = $dbc->prepare("SELECT T3.user_id, T3.name, T3.scheidsrechterscode, T3.email, IF(ISNULL(W.number), '', W.number) as number, T3.`count`
                           FROM (
                             SELECT R.id as user_id, R.name as name, R.email, IF(ISNULL(cb_scheidsrechterscode), '', cb_scheidsrechterscode) as scheidsrechterscode, IF(ISNULL(C.count), 0, C.count) as count
                             FROM (
                               SELECT Users.id, Users.name, C.cb_scheidsrechterscode, Users.email
                               FROM J3_comprofiler as C
                               INNER JOIN 
                               (
                                 SELECT U.id, U.name, U.email
                                 FROM J3_users U
                                 INNER JOIN 
                                 (
                                   SELECT G.title, M.user_id, M.group_id 
                                   FROM J3_usergroups G
                                   INNER JOIN J3_user_usergroup_map M
                                   ON G.id = M.group_id
                                   WHERE G.title = 'Scheidsrechters'
                                 ) as RID
                                 ON U.id = RID.user_id
                               ) as Users
                               ON C.user_id = Users.id
                             ) as R
                             LEFT JOIN (
                                 SELECT user_id, count(user_id) as count 
                                 FROM ScheidsApp_matches
                                 WHERE code != '' 
                                 GROUP BY user_id
                             ) as C
                             ON C.user_id = R.id
                             ) as T3
                             LEFT JOIN SKC_whatsapp_users W
                             ON T3.user_id = W.user_id");
    
    if (!$stmt->execute()){
      echo "Error:";
      exit(print_r($stmt->errorInfo()));
    }
    
    $referees = array();
    while ($row = $stmt->fetch()) {
      $referees[$row['user_id']] = array("name" => $row['name'],
                                         "email" => $row['email'],
                                         "number" => $row['number'],
                                         "count" => $row['count'],
                                         "scheidsrechterscode" => $row['scheidsrechterscode']);
    }
    return utf8_converter($referees);
  }
  
  function GetScheduledRefsAndTellersForNextWeek($dbc){
    $stmt = $dbc->prepare("SELECT * FROM ScheidsApp_matches
                           WHERE date >= CURDATE() and 
                                 date < DATE_ADD(NOW(), INTERVAL 7 DAY)");
    
    if (!$stmt->execute()){
      echo "Error:";
      exit(print_r($stmt->errorInfo()));
    }
    return $stmt->fetchAll();
  }
  
  function CheckMatches($dbc){
    $overzicht = GetMatches();
    $scheduled_matches = GetScheduledMatches($dbc);
    foreach ($scheduled_matches as $match){
      if (!isset($overzicht[$match['date']][$match['time']][$match['code']])){
        $stmt = $dbc->prepare("DELETE FROM ScheidsApp_matches where id=:id");
        $stmt->bindValue(':id', $match['id'], PDO::PARAM_INT);
        if (!$stmt->execute()){
          echo "Error:";
          exit(print_r($stmt->errorInfo()));
        }
      }
    }
  }
  
  function GetScheduledMatches($dbc){
    $stmt = $dbc->prepare("SELECT * FROM ScheidsApp_matches
                           WHERE date >= CURDATE()");
    
    if (!$stmt->execute()){
      echo "Error:";
      exit(print_r($stmt->errorInfo()));
    }
    return $stmt->fetchAll();
  }
  
  function GetScheduledZaalwachtForNextWeek($dbc){
    $stmt = $dbc->prepare("SELECT * FROM ScheidsApp_zaalwacht
                           WHERE date >= CURDATE() and 
                                 date < DATE_ADD(NOW(), INTERVAL 7 DAY)");
    
    if (!$stmt->execute()){
      echo "Error:";
      exit(print_r($stmt->errorInfo()));
    }
    return $stmt->fetchAll();
  }

   function GetYearOfMatch($month){
      $month = strtolower($month);
      $yearOfBeginOfSeason = date('n') <= 6 ? date('Y') - 1 : date('Y');
      $lastMonths = array("januari", "februari", "maart", "april", "mei", "juni");
      if (in_array($month, $lastMonths)){
         return $yearOfBeginOfSeason + 1;
      }
      else {
         return $yearOfBeginOfSeason;
      }
  }

  function GetMatches($url = "https://api.nevobo.nl/export/sporthal/LDNUN/programma.rss"){
      if ($url == ""){
         return array();
      }

      $xml = new SimpleXMLElement($url, NULL, TRUE);
      foreach ($xml->channel->item as $i){
         /* regular expression to get the details of the title */
         $title_eval = preg_match_all("/\s*([0-9]+)\s*([a-z.]+)\s*([0-9]+:[0-9]+):\s*([0-9a-zA-Z '\/]+)-\s*([0-9a-zA-Z ']+)\s*/", (string)$i->title, $raw_title, PREG_SET_ORDER);
         $title = array_map('trim', $raw_title[0]);
   
         if ($title_eval == false) continue;

         /* regular expression to get the details of the description */
         $description_eval = preg_match_all("/Wedstrijd: \s*([a-zA-Z0-9 ]+) \s*([a-zA-Z0-9- ]+), Datum: \s*([a-zA-Z]+)\s*([0-9]+)\s*([a-zA-Z]+),\s*([0-9]+:[0-9]+),\s*Speellocatie:\s*([a-zA-Z0-9'\- ]+),\s*([a-zA-Z0-9' ]+),\s*([a-zA-Z0-9' ]+)\s*/", 
         trim($i->description), $raw_description, PREG_SET_ORDER);
         $description = array_map('trim', $raw_description[0]);
      
         if ($description_eval == false || $description_eval == 0){
            echo "Er ging iets fout met de volgende wedstrijd: " . $i->description;
         }
      
         $match['match_id'] = $description[1] . " " . $description[2];
         $match['date_string'] = $description[3] . " " . $description[4] . " ". $description[5];
			
         $year = GetYearOfMatch($description[5]);
         $date_stamp = strptime($match['date_string'] . " " . $year, '%A %e %B %Y');
			
         $match['date'] = (1900 + $date_stamp['tm_year']) . "-" . sprintf("%02d", 1 + $date_stamp['tm_mon']) . "-" . sprintf("%02d", $date_stamp['tm_mday']);
         $match['time'] = str_replace(":", "", $description[6]);
         $match['gym_name'] = $description[7];
         $match['street'] = $description[8];
         $match['city'] = $description[9];
         $match['teams'] = $title[4] . " - " . $title[5];
         $matches[$match['date']][$match['time']][$match['match_id']] = $match;
      }
    
      return $matches;
      
	}
  
  function GetResults($dbc, $url = "https://api.nevobo.nl/export/sporthal/LDNUN/resultaten.rss"){
    if ($url == ""){
      return array();
    }
		$xml = new SimpleXMLElement($url, NULL, TRUE);
		foreach ($xml->channel->item as $i){
      /* regular expression to get the details of the title */
      $title_eval = preg_match_all("/\s*([0-9a-zA-Z\- ']+),\s*/", (string)$i->title, $raw_title, PREG_SET_ORDER);
      $title = array_map('trim', $raw_title[0]);
      if ($title_eval == false){
        echo "<br><b>Error! Breng de webcie op de hoogte: webmasters@skcvolleybal.nl</b><br><br>".$i->title."<br>";
      }
      
      /* regular expression to get the guid */
      $description_eval = preg_match_all("/https:\/\/api.nevobo.nl\/permalink\/wedstrijd\/\s*([0-9a-zA-Z]+)\+\+\+\s*([a-zA-Z0-9]+)/", 
                     trim($i->guid), $raw_description, PREG_SET_ORDER);
      $guid = array_map('trim', $raw_description[0]);
      
      if ($description_eval == false || $description_eval == 0){
        echo "Er ging iets fout met de volgende wedstrijd: " . $i->guid;
      }
      
      $match = $title[1];
      $code = $guid[1] . " " . $guid[2];
      $results[$code]['teams'] = $match;
		}
    
    $stmt = $dbc->prepare("SELECT * 
                           FROM ScheidsApp_matches
                           WHERE date < CURDATE()");
  
    if (!$stmt->execute()){
      echo "Error:";
      print_r($stmt->errorInfo());
      return;
    }
    
    $results = array();
    while ($row = $stmt->fetch()) {
      $results[$row['code']]['tellers'] = $row['tellers'];
      $results[$row['code']]['scheids'] = $row['user_id'];
      $results[$row['code']]['tijd'] = $row['time'];
      $results[$row['code']]['datum'] = $row['date'];
      
    }
    
		return $results;
	}
  
  function GetUserURL($user_id, $dbc){
    $stmt = $dbc->prepare("SELECT G.title FROM 
                          (
                            SELECT * 
                            FROM J3_user_usergroup_map 
                            WHERE user_id = :user_id
                          ) as M
                          INNER JOIN J3_usergroups as G
                          ON M.group_id = G.id
                          WHERE G.title LIKE 'Heren %' or G.title LIKE 'Dames %'");
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    
    if (!$stmt->execute()){
      exit(print_r($stmt->errorInfo()));
    }
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $number_of_results = count($result);
    if ($number_of_results == 0){
      return "";
    } 
    else {
      $team_name = $result[0]['title'];
    }
    
    $gender = strtolower(substr($team_name, 0, 5));
    $team = substr($team_name, 6);
    
    $url = "https://api.nevobo.nl/export/team/CKL9R53/$gender/$team/programma.rss";
    return $url;
	}
  
  function GetOverzicht($dbc, $user_id){
    $matches = GetMatches();
    
    $scheduled_matches = GetScheduledMatches($dbc);
    
    if (!empty($scheduled_matches) && count($scheduled_matches) > 0){
      foreach ($scheduled_matches as $row){
        $matches[$row['date']][$row['time']][$row['code']]['scheids'] = $row['user_id'];
        if ($row['user_id'] == $user_id){
          $matches[$row['date']][$row['time']][$row['code']]['jij'] = "waar";
        }
      }
    }
    
    return $matches;
  }
  
  function SetGoogleCalendars($dbc, $user_id){
    include_once("../assets/libs/iCalcreator/iCalcreator.class.php");
    
    $matches = GetMatches();
    
    $stmt = $dbc->prepare("SELECT * 
                           FROM ScheidsApp_matches
                           WHERE user_id = :user_id and 
                           date >= CURDATE()");
    
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    
    if (!$stmt->execute()){
      echo "Error:";
      exit(print_r($stmt->errorInfo()));
    }
    
    $referees = GetReferees($dbc);
    
    $filename = "user_" . $user_id . ".ics";
    $config    = array( "unique_id" => "www.skcvolleybal.nl", 
                        "TZID" => "Europe/Amsterdam", 
                        "directory" => "Calendars",
                        "filename" => "$filename" );
    $vcalendar = new vcalendar( $config );
    $vcalendar->setProperty( "method",        "PUBLISH" );
    $vcalendar->setProperty( "x-wr-calname",  "Scheidsrooster " . $referees[$user_id]['name'] );
    $vcalendar->setProperty( "X-WR-CALDESC",  "Alle wedstrijden die jij moet fluiten in een google calendar account, aangeboden door de (Binkies van de) Webcie." );
    $uuid      = "Scheidsrooster " . $referees[$user_id]['name'];
    $vcalendar->setProperty( "X-WR-RELCALID", $uuid );
    $vcalendar->setProperty( "X-WR-TIMEZONE", "Europe/Amsterdam" );
    
    //$vcalendar = new vcalendar( $config );
    $vcalendar->setConfig( "nl", "\r\n" );
    while ($row = $stmt->fetch()){
      $match = $matches[$row['date']][$row['time']][$row['code']];
      $date = date_parse($row['date'] . " " . $row['time']);
      $vevent = & $vcalendar->newComponent( "vevent" );
      $vevent->setProperty( "dtstart", array( "year"  => $date['year']
                                            , "month" => $date['month']
                                            , "day"   => $date['day']
                                            , "hour"  => $date['hour']
                                            , "min"   => $date['minute']
                                            , "sec"   => $date['second'] ));
      $vevent->setProperty( "dtend",   array( "year"  => $date['year']
                                            , "month" => $date['month']
                                            , "day"   => $date['day']
                                            , "hour"  => $date['hour'] + 2
                                            , "min"   => $date['minute']
                                            , "sec"   => $date['second'] ));
                                            
      $vevent->setProperty( "summary", "Wedstrijden Fluiten" );
      $vevent->setProperty( "description", $match['teams'] . "<br>" . $row['code']);
    }
    // Store the calendar for subscription to the calendar
    if( FALSE === $vcalendar->saveCalendar()) {
      echo "error when saving.. .";
    }
    return $filename;
  }

   function coor2xls($column, $row){
      for($coordinate = ""; $column >= 0; $column = intval($column / 26) - 1){
         $coordinate = chr($column % 26 + 0x41) . $coordinate;
      }
      return $coordinate . $row;
   }
  
  function GetTeamMembers($dbc, $team){
    $stmt = $dbc->prepare("SELECT U.id, U.name, U.email, W.number FROM J3_users U
                           INNER JOIN (
                             select user_id from J3_usergroups G
                             INNER JOIN J3_user_usergroup_map M 
                             on G.id = M.group_id 
                             where title=:team
                           ) as T
                           ON T.user_id = U.id
                           LEFT JOIN SKC_whatsapp_users W
                           ON T.user_id = W.user_id");
    $stmt->bindValue(':team', $team, PDO::PARAM_STR);
    
    if (!$stmt->execute()){
      exit(print_r($stmt->errorInfo()));
    }
    
    return $stmt->fetchAll();
  }
  
  function GetTelSchema($dbc){
    $stmt = $dbc->prepare("SELECT * FROM ScheidsApp_matches WHERE date >= CURDATE() and tellers IS NOT NULL");
    if (!$stmt->execute()){
      echo "Error:";
      print_r($stmt->errorInfo());
      return;
    }
    return $stmt->fetchAll();
  }
  
  function GetZaalwachtSchema($dbc){
    $stmt = $dbc->prepare("SELECT * FROM ScheidsApp_zaalwacht WHERE date >= CURDATE() and team IS NOT NULL");
    if (!$stmt->execute()){
      echo "Error:";
      print_r($stmt->errorInfo());
      return;
    }
    return $stmt->fetchAll();
  }

  function GetAllHomeMatches(){
      $matches = GetMatches();
      $result = [];
      $counter = 0;
      foreach($matches as $date => $timeslots){
         $result[] = ["date" => $date];
         foreach ($timeslots as $time => $timeslot){
            $result[$counter]["timeslots"][] = $time;
         }
         $counter++;
      }
      return $result;
  }
  
  function WriteScheduleToExcel($dbc, $user_id, $user_name){
    // Create new PHPExcel object
    $objPHPExcel = new PHPExcel();
    
    $referees = GetReferees($dbc);
    
    // Set properties
    $period = date('Y') . " - " . date('Y', strtotime('+1 year'));
    $objPHPExcel->getProperties()->setCreator("Webcie");
    $objPHPExcel->getProperties()->setLastModifiedBy("Webcie");
    $objPHPExcel->getProperties()->setTitle("Scheidsschema SKC Seizoen $period");
    $objPHPExcel->getProperties()->setSubject("Scheidsschema SKC Seizoen $period");
    $objPHPExcel->getProperties()->setDescription("Het scheidsrechtersschema voor het SKC voor het seizoen $period");
    
    $matches = GetOverzicht($dbc, 0);
    
    // De Komende wedstrijden
    $objPHPExcel->setActiveSheetIndex(0);
    $objPHPExcel->getActiveSheet()->setTitle('Komende wedstrijden');
    $objPHPExcel->getActiveSheet()->SetCellValue('A1', 'Datum');
    $objPHPExcel->getActiveSheet()->SetCellValue('B1', 'Tijd');
    $objPHPExcel->getActiveSheet()->SetCellValue('C1', 'Wedstrijdcode');
    $objPHPExcel->getActiveSheet()->SetCellValue('D1', 'Teams');
    $objPHPExcel->getActiveSheet()->SetCellValue('E1', 'Scheidsrechter');
    $match_count = 2;
    foreach ($matches as $date => $times){
      foreach ($times as $time => $codes){
        foreach ($codes as $code => $match_info){if (!isset($match_info['date'])){echo "date: " . $date . ", time: " . $time . ", code: " . $code . ", " . print_r($match_info, true);}
          $objPHPExcel->getActiveSheet()->SetCellValue('A'.$match_count, $match_info['date']);
          $objPHPExcel->getActiveSheet()->SetCellValue('B'.$match_count, $match_info['time']);
          $objPHPExcel->getActiveSheet()->SetCellValue('C'.$match_count, $code);
          $objPHPExcel->getActiveSheet()->SetCellValue('D'.$match_count, $match_info['teams']);
          if (isset($match_info['scheids'])){
            $scheids_id = $match_info['scheids'];
            $objPHPExcel->getActiveSheet()->SetCellValue('E'.$match_count, $referees[$scheids_id]['name']);
          }
          $match_count++;
        }
      }
    }
    
    // De afgelopen wedstrijden
    $objPHPExcel->createSheet(1);
    $objPHPExcel->setActiveSheetIndex(1);
    $objPHPExcel->getActiveSheet()->setTitle('Ingedeelde wedstrijden');
    $objPHPExcel->getActiveSheet()->SetCellValue('A1', 'Datum');
    $objPHPExcel->getActiveSheet()->SetCellValue('B1', 'Tijd');
    $objPHPExcel->getActiveSheet()->SetCellValue('C1', 'Wedstrijdcode');
    $objPHPExcel->getActiveSheet()->SetCellValue('D1', 'Scheidsrechter');
    $stmt = $dbc->prepare("SELECT * 
                           FROM ScheidsApp_matches
                           WHERE user_id IS NOT NULL and date < CURDATE()
                           ORDER BY date ASC, time ASC, code ASC");
    
    if (!$stmt->execute()){
      exit(print_r($stmt->errorInfo()));
    }

    $match_count = 2;
    while ($row = $stmt->fetch()) {
      $objPHPExcel->getActiveSheet()->SetCellValue('A'.$match_count, $row['date']);
      $objPHPExcel->getActiveSheet()->SetCellValue('B'.$match_count, $row['time']);
      $objPHPExcel->getActiveSheet()->SetCellValue('C'.$match_count, $row['code']);
      $scheids_id = $row['user_id'];
      if (isset($referees[$scheids_id])){
        $name = $referees[$scheids_id]['name'];
      }
      else {
        $name = "Oud-scheidsrechter";
      }
      $objPHPExcel->getActiveSheet()->SetCellValue('D'.$match_count, $name);      
      $match_count++;
    }
    
    // De aantal wedstrijden per scheidsrechter
    $objPHPExcel->createSheet(2);
    $objPHPExcel->setActiveSheetIndex(2);
    $objPHPExcel->getActiveSheet()->setTitle('Gefloten wedstrijd per persoon');
    $objPHPExcel->getActiveSheet()->SetCellValue('A2', 'Scheidsrechter');
    $objPHPExcel->getActiveSheet()->SetCellValue('B2', 'Aantal keer gefloten');
    
    $query = "SELECT * FROM ScheidsApp_availability
              ORDER BY user_id, date, time";
    $stmt = $dbc->prepare($query);
    
    if (!$stmt->execute()){
      exit(print_r($stmt->errorInfo()));
    }

    $availabilityForAllUsers = [];
    while ($row = $stmt->fetch()) {
      $userId = $row['user_id'];
      $date = $row['date'];
      $time = $row['time'];
      $availabilityAnswer = $row['availability'];
      if(!isset($availabilityForAllUsers[$userId])){
         $availabilityForAllUsers[$userId] = [];
      }

      $availabilityForAllUsers[$userId][] = [
         "date" => $date,
         "time" => $time,
         "availability" => $availabilityAnswer
      ];
    }
    
    // Get Dates of all the home matches and put them in the header columns/rows
    $homeMatches = GetAllHomeMatches();
    $dateBase = 2;
    foreach ($homeMatches as $homeMatch){
      $objPHPExcel->getActiveSheet()->SetCellValue(coor2xls($dateBase, 1), $homeMatch['date']);
      $timeBase = 0;
      foreach($homeMatch['timeslots'] as $timeslot){
         $objPHPExcel->getActiveSheet()->SetCellValue(coor2xls($dateBase + $timeBase, 2), substr_replace($timeslot, ":", 2, 0));
         $timeBase++;
      }
      $dateBase += count($homeMatch['timeslots']);
    }
    
    $query = "SELECT 
                U.ID AS user_id, 
                U.name, 
                COUNT(MS.USER_ID) AS count 
              FROM J3_users U
              INNER JOIN J3_user_usergroup_map M ON U.ID = M.USER_ID
              INNER JOIN (
                SELECT * 
                FROM J3_usergroups 
                WHERE TITLE = 'SCHEIDSRECHTERS'
              ) G ON M.GROUP_ID = G.ID
              LEFT JOIN ScheidsApp_matches MS ON U.ID = MS.USER_ID
              GROUP BY MS.USER_ID
              ORDER BY COUNT DESC";
    $stmt = $dbc->prepare($query);
    
    if (!$stmt->execute()){
      exit(print_r($stmt->errorInfo()));
    }

    $dates = [];
    $matrix = [];
    $match_count = 3;

    while ($row = $stmt->fetch()) {
      $userId = $row['user_id'];
      $name = $row['name'];
      $count = $row['count'];

      if (!isset($referees[$userId])){
        $name = "Oud-scheidsrechter";
      }

      $objPHPExcel->getActiveSheet()->SetCellValue('A'.$match_count, $name);
      $objPHPExcel->getActiveSheet()->SetCellValue('B'.$match_count, $row['count']);
      
      // Add the dates
      if (isset($availabilityForAllUsers[$userId])){
         $userAvailability = $availabilityForAllUsers[$userId];
         $columnCounter = 2;
         foreach ($homeMatches as $homeMatch){
            foreach($homeMatch['timeslots'] as $timeslot){
               foreach ($userAvailability as $option){
                  if ($option['date'] == $homeMatch['date'] && $option['time'] == $timeslot){
                     //echo "(" . $columnCounter . ", " . $match_count . ") = " . $option['availability'] . "<br />";
                     $objPHPExcel->getActiveSheet()->SetCellValue(coor2xls($columnCounter, $match_count), $option['availability']);
                     switch ($option['availability']){
                        case 'Ja': $color = "C6EFCE"; break;
                        case 'Nee': $color = "FFC7CE"; break;
                        case 'Misschien': $color = "FFEB9C"; break;
                        default: $color = "FFFFFF"; break;
                     }

                     $objPHPExcel->getActiveSheet()->getStyle(coor2xls($columnCounter, $match_count))->applyFromArray(
                        array(
                           'fill' => array(
                              'type' => PHPExcel_Style_Fill::FILL_SOLID,
                              'color' => array('rgb' => $color)
                           )
                        )
                     );
                  }
               }

               $columnCounter++;
            }
         }
      }

      $match_count++;
    }

    // Schema van de gebruiker
    $objPHPExcel->createSheet(3);
    $objPHPExcel->setActiveSheetIndex(3);
    $objPHPExcel->getActiveSheet()->setTitle($user_name);
    $objPHPExcel->getActiveSheet()->SetCellValue('A1', 'Datum');
    $objPHPExcel->getActiveSheet()->SetCellValue('B1', 'Tijd');
    $objPHPExcel->getActiveSheet()->SetCellValue('C1', 'Wedstrijdcode');
    $objPHPExcel->getActiveSheet()->SetCellValue('D1', 'Teams');
    $objPHPExcel->getActiveSheet()->SetCellValue('E1', 'Scheidsrechter');
    $match_count = 2;
    foreach ($matches as $date => $times){
      foreach ($times as $time => $codes){
        foreach ($codes as $code => $match_info){
          if (isset($match_info['scheids'])){
            $scheids_id = $match_info['scheids'];
            if ($scheids_id == $user_id){
              $objPHPExcel->getActiveSheet()->SetCellValue('A'.$match_count, $match_info['date']);
              $objPHPExcel->getActiveSheet()->SetCellValue('B'.$match_count, $match_info['time']);
              $objPHPExcel->getActiveSheet()->SetCellValue('C'.$match_count, $code);
              $objPHPExcel->getActiveSheet()->SetCellValue('D'.$match_count, $match_info['teams']);
              if (isset($match_info['scheids'])){
                $scheids_id = $match_info['scheids'];
                $objPHPExcel->getActiveSheet()->SetCellValue('E'.$match_count, $referees[$scheids_id]['name']);
              }
              $match_count++;
            }
          }
        }
      }
    }
    
    
    // Scherm Fluiters per team
    $objPHPExcel->createSheet(4);
    $objPHPExcel->setActiveSheetIndex(4);
    $objPHPExcel->getActiveSheet()->setTitle("Gefloten per team");
    $objPHPExcel->getActiveSheet()->SetCellValue('A1', 'Team');
    $objPHPExcel->getActiveSheet()->SetCellValue('B1', 'Aantal keer gefloten');
    $team_count = 2;
    $gefloten_per_team = GetScheduledRefereesPerTeam($dbc);
    
    foreach ($gefloten_per_team as $team =>$gefloten){
      $objPHPExcel->getActiveSheet()->SetCellValue('A'.$team_count, $team);
      $objPHPExcel->getActiveSheet()->SetCellValue('B'.$team_count, $gefloten);
      $team_count++;
    }
    
    // Scherm Tellers 
    $objPHPExcel->createSheet(5);
    $objPHPExcel->setActiveSheetIndex(5);
    $objPHPExcel->getActiveSheet()->setTitle("Telschema");
    $objPHPExcel->getActiveSheet()->SetCellValue('A1', 'Datum');
    $objPHPExcel->getActiveSheet()->SetCellValue('B1', 'Tijd');
    $objPHPExcel->getActiveSheet()->SetCellValue('C1', 'Wedstrijd');
    $objPHPExcel->getActiveSheet()->SetCellValue('D1', 'Team');
    $team_count = 2;
    $telschema = GetTelSchema($dbc);
    
    foreach ($telschema as $t){
      $objPHPExcel->getActiveSheet()->SetCellValue('A'.$team_count, $t['date']);
      $objPHPExcel->getActiveSheet()->SetCellValue('B'.$team_count, $t['time']);
      $objPHPExcel->getActiveSheet()->SetCellValue('C'.$team_count, $matches[$t['date']][$t['time']][$t['code']]['teams']);
      $objPHPExcel->getActiveSheet()->SetCellValue('D'.$team_count, $t['tellers']);
      $team_count++;
    }
    
    // Geteld per team
    $objPHPExcel->createSheet(6);
    $objPHPExcel->setActiveSheetIndex(6);
    $objPHPExcel->getActiveSheet()->setTitle("Geteld per team");
    $objPHPExcel->getActiveSheet()->SetCellValue('A1', 'Team');
    $objPHPExcel->getActiveSheet()->SetCellValue('B1', 'Aantal keer geteld');
    $team_count = 2;
    $geteld_per_team = GetTelOverzichtPerTeam($dbc);
    
    foreach ($geteld_per_team as $row){
      $objPHPExcel->getActiveSheet()->SetCellValue('A'.$team_count, $row['team']);
      $objPHPExcel->getActiveSheet()->SetCellValue('B'.$team_count, $row['geteld']);
      $team_count++;
    }
    
    // Scherm Zaalwacht 
    $objPHPExcel->createSheet(7);
    $objPHPExcel->setActiveSheetIndex(7);
    $objPHPExcel->getActiveSheet()->setTitle("Zaalwachtschema");
    $objPHPExcel->getActiveSheet()->SetCellValue('A1', 'Datum');
    $objPHPExcel->getActiveSheet()->SetCellValue('B1', 'Team');
    $team_count = 2;
    $telschema = GetZaalwachtSchema($dbc);
    
    foreach ($telschema as $t){
      $objPHPExcel->getActiveSheet()->SetCellValue('A'.$team_count, $t['date']);
      $objPHPExcel->getActiveSheet()->SetCellValue('D'.$team_count, $t['team']);
      $team_count++;
    }
    
    // Zaalwacht per team
    $objPHPExcel->createSheet(8);
    $objPHPExcel->setActiveSheetIndex(8);
    $objPHPExcel->getActiveSheet()->setTitle("Zaalwacht per team");
    $objPHPExcel->getActiveSheet()->SetCellValue('A1', 'Team');
    $objPHPExcel->getActiveSheet()->SetCellValue('B1', 'Aantal keer zaalwacht');
    $team_count = 2;
    $gefloten_per_team = GetZaalwachtOverzichtPerTeam($dbc);
    
    
    foreach ($gefloten_per_team as $row){
      $objPHPExcel->getActiveSheet()->SetCellValue('A'.$team_count, $row['team']);
      $objPHPExcel->getActiveSheet()->SetCellValue('B'.$team_count, $row['zaalwacht']);
      $team_count++;
    }

    // Auto size columns for each worksheet
    //PHPExcel_Shared_Font::setAutoSizeMethod(PHPExcel_Shared_Font::AUTOSIZE_METHOD_EXACT);
    foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
      $objPHPExcel->setActiveSheetIndex($objPHPExcel->getIndex($worksheet));
      $cols = explode(":", trim(preg_replace('/\d+/u', '', $objPHPExcel->getActiveSheet()->calculateWorksheetDimension())));
      $col = $cols[0]; //first util column with data
      $end = ++$cols[1]; //last util column with data +1, to use it inside the WHILE loop. Else, is not going to use last util range column.
      while($col != $end){
         $objPHPExcel->getActiveSheet()->getColumnDimension($col)->setAutoSize(true);
         $col++;
      }
    }
    $objPHPExcel->setActiveSheetIndex(0);
   
   



    // Save Excel 2007 file
    $objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
    $objWriter->save("./Scheidsrechtersschemas/scheidsrechtersschema_" . $user_id . ".xlsx");
    return "https://www.skcvolleybal.nl/scripts/ScheidsrechtersApp/shared/Scheidsrechtersschemas/scheidsrechtersschema_" . $user_id . ".xlsx";
  }
  
  function SendMail($from_address, $from_name, $to_address, $to_name, $subject, $body, $attachment = ""){
    if (!filter_var($to_address, FILTER_VALIDATE_EMAIL)) return;
    $PHPMailer = new PHPMailer();
    $PHPMailer->CharSet = "UTF-8";
    $PHPMailer->setFrom($from_address, $from_name);
    $PHPMailer->addAddress($to_address, $to_name);
    $PHPMailer->Subject = $subject;
    $PHPMailer->msgHTML($body);
    
    if ($attachment != ""){
      $PHPMailer->addAttachment($attachment);
    }
    if (!$PHPMailer->send()) {
      echo "Mailer Error: " . $PHPMailer->ErrorInfo;
    }
  }
  
  function SendWhatsapp($WAPI, $to, $body){
    $m = new Message($to, "Text", array("message" => $body));
    $WAPI->SetOutboxMessage($m);
  }
  
?>
