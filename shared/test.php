<?php
  include("func.inc.php");
  
  // Create new PHPExcel object
  $objPHPExcel = new PHPExcel();
  
  
            
  $user = GetUser();
  $dbc = GetDBConnection();
  
  /* Fetch taken_overzicht */
  $query = "SELECT S.*, U.name AS scheids, M.team as scheids_team, Z.team as zaalwacht FROM ScheidsApp_matches S
            LEFT JOIN J3_users U ON S.user_id = U.id
            LEFT JOIN (
                SELECT user_id, G.title AS team FROM J3_user_usergroup_map M 
                INNER JOIN J3_usergroups G ON M.group_id = G.id
                WHERE G.parent_id = 12
            ) M ON S.user_id = M.user_id
            LEFT JOIN ScheidsApp_zaalwacht Z ON S.date = Z.date
            ORDER BY date, time";
  $stmt = $dbc->prepare($query);
  if (!$stmt->execute()) exit(print_r($stmt->errorInfo()));
  
  $schema = $stmt->fetchAll();
  
  $taken_overzicht = [];
  foreach ($schema as $row){
    $date = $row["date"];
    $scheids = $row["scheids"];
    $tellers = $row["tellers"];
    $zaalwacht = $row["zaalwacht"];
    $scheids_team = $row["scheids_team"];
    
    if (!empty($row["tellers"])){
      if (isset($taken_overzicht[$row["tellers"]])){
        $taken_overzicht[$row["tellers"]]["tellen"]++;
      }
      else {
        $taken_overzicht[$row["tellers"]] = [
          "tellen" => 1,
          "fluiten" => ["count" => 0, "leden" => []],
          "zaalwacht" => ["count" => 0, "dagen" => []]
        ];
      }
    }
    
    if (!empty($row["zaalwacht"])){
      if (isset($taken_overzicht[$zaalwacht])){
        if (!in_array($date, $taken_overzicht[$zaalwacht]["zaalwacht"]["dagen"])){
          $taken_overzicht[$zaalwacht]["zaalwacht"]["dagen"][] = $date;
          $taken_overzicht[$zaalwacht]["zaalwacht"]["count"]++;
        }
      }
      else {
        $taken_overzicht[$zaalwacht] = [
          "tellen" => 1,
          "fluiten" => ["count" => 0, "leden" => []],
          "zaalwacht" => ["count" => 1, "dagen" => [$date]],
          "leden" => []
        ];
      }
    }
    
    if (!empty($scheids)){
      if (!empty($scheids_team)){
        if (isset($taken_overzicht[$scheids_team])){
          $taken_overzicht[$scheids_team]["fluiten"]["count"]++;
          if (isset($taken_overzicht[$scheids_team]["fluiten"]["leden"][$row["scheids"]])){
            $taken_overzicht[$scheids_team]["fluiten"]["leden"][$row["scheids"]]++;
          }
          else {
            $taken_overzicht[$scheids_team]["fluiten"]["leden"][] = [$row["scheids"] => 1];
          }
        }
        else {
          $taken_overzicht[$scheids_team] = [
            "tellen" => 0,
            "fluiten" => ["count" => 1, "leden" => [$row["scheids"] => 1]],
            "zaalwacht" => ["count" => 0, "dagen" => []]
          ];
        }
      }
      else {
        if (isset($teamlozen[$row["scheids"]])){
          $teamlozen[$row["scheids"]]++;
        }
        else {
          $teamlozen[$row["scheids"]] = 1;
        }
      }
    }
  }
  
  ksort($taken_overzicht, SORT_NATURAL);
  
  $teamRegex = "([0-9a-zA-Z '\/-]+)";
  $programUrl = "https://api.nevobo.nl/export/vereniging/CKL9R53/programma.rss";
  $titleRegex = "/([0-9]+) ([a-z.]+) ([0-9]+:[0-9]+): $teamRegex - $teamRegex/";
  $descriptionRegex = "/Wedstrijd: ([a-zA-Z0-9]+)\s*([a-zA-Z0-9]+), Datum: ([a-zA-Z]+) ([0-9]+) ([a-zA-Z]+), ([0-9]+:[0-9]+), Speellocatie: ([a-zA-Z0-9'\- ]+), ([a-zA-Z0-9' ]+), ([a-zA-Z0-9]+)\s*([a-zA-Z0-9' ]+)/";
  $feed = new SimplePie();
  $feed->set_feed_url($programUrl);
  $feed->set_timeout(30);
  $feed->set_cache_duration(60*60*24);
  $feed->init();
  $matches = [];  
  $items = $feed->get_items(0);
  foreach ($items as $item){
    if (preg_match_all($titleRegex, $item->get_title(), $title, PREG_SET_ORDER) !== false){
      if (preg_match_all($descriptionRegex, $item->get_description(), $description, PREG_SET_ORDER) !== false){
        if (substr($title[0][4], 0, 4) === "SKC "){
          $code = $description[0][1] . " " . $description[0][2];
          $matches[$code] = [
          "tijd" => $title[0][3],
          "teams" => $title[0][4] . " - " . $title[0][5],
          "dag" => ucwords($description[0][3] . " " . $description[0][4] . " " . $description[0][5])
        ];
        }
      }
      else {
        echo "Nextmactches: Kon description niet parsen met regex: " . $team . "<br />";
      }
    }
    else {
      echo "Nextmactches: Kon title niet parsen met regex: " . $team . "<br />";
    }
  }
  
  $borderOutlineStyle = array(
    'borders' => array(
      'outline' => array(
        'style' => PHPExcel_Style_Border::BORDER_THIN
      )
    )
  );
  
  $backgroundColor = array(
    'fill' => array(
      'type' => PHPExcel_Style_Fill::FILL_SOLID,
      'color' => array('rgb' => 'cccccc')
      )
    );
  
  // Set properties
  $objPHPExcel->getProperties()->setCreator("Webcie");
  $objPHPExcel->getProperties()->setLastModifiedBy("Webcie");
  $objPHPExcel->getProperties()->setTitle("Scheidstaken SKC Seizoen 2016-2017");
  $objPHPExcel->getProperties()->setSubject("Scheidstaken SKC Seizoen 2016-2017");
  $objPHPExcel->getProperties()->setDescription("Het scheidsrechterstaken voor het SKC voor het seizoen 2016 - 2017");
  
  // Add some data
  $objPHPExcel->setActiveSheetIndex(0);
  $objPHPExcel->getActiveSheet()->setTitle('Komende wedstrijden');
  $objPHPExcel->getActiveSheet()->SetCellValue('A1', 'Datum');
  $objPHPExcel->getActiveSheet()->SetCellValue('B1', 'Tijd');
  $objPHPExcel->getActiveSheet()->SetCellValue('C1', 'Teams');
  $objPHPExcel->getActiveSheet()->SetCellValue('D1', 'Scheidsrechter');
  $objPHPExcel->getActiveSheet()->SetCellValue('E1', 'Tellers');
  $objPHPExcel->getActiveSheet()->SetCellValue('F1', 'Zaalwacht');
  $objPHPExcel->getActiveSheet()->getStyle('A1:F1')->getFont()->setUnderline(true)->SetBold(true)->SetItalic(true);
  $counter = 2;
  $current_zaalwacht = "";
  reset($schema);
  $current_date = $schema[key($schema)]["date"];
  $first_match_row = 2;
  foreach ($schema as $match){
    if ($match["date"] < date("Y-m-d")) continue;
    $objPHPExcel->getActiveSheet()->SetCellValue('A'.$counter, $match["date"]);
    $objPHPExcel->getActiveSheet()->SetCellValue('B'.$counter, $match["time"]);
    $teams = isset($matches[$match["code"]])?$matches[$match["code"]]["teams"]:"Nog onbekend";
    $objPHPExcel->getActiveSheet()->SetCellValue('C'.$counter, $teams);
    $objPHPExcel->getActiveSheet()->SetCellValue('D'.$counter, $match["scheids"]);
    $objPHPExcel->getActiveSheet()->SetCellValue('E'.$counter, $match["tellers"]);
    if ($current_zaalwacht != $match["zaalwacht"]){
      $objPHPExcel->getActiveSheet()->SetCellValue('F'.$counter, $match["tellers"]);
      $current_zaalwacht = $match["zaalwacht"];
    }
    if ($current_date != $match["date"]){
      $objPHPExcel->getActiveSheet()->getStyle("A" . $first_match_row .":F" . ($counter-1))->applyFromArray($borderOutlineStyle);
      $current_date = $match["date"];
      $first_match_row = $counter;
    }
    $counter++;
  }
  $objPHPExcel->getActiveSheet()->getStyle("A" . $first_match_row .":F" . ($counter-1))->applyFromArray($borderOutlineStyle);
  
  $objPHPExcel->createSheet(1);
  $objPHPExcel->setActiveSheetIndex(1);
  $objPHPExcel->getActiveSheet()->setTitle('Taken Overzicht');
  $objPHPExcel->getActiveSheet()->SetCellValue('C1', 'Scheids');
  $objPHPExcel->getActiveSheet()->SetCellValue('D1', 'Tellen');
  $objPHPExcel->getActiveSheet()->SetCellValue('E1', 'Zaalwacht');
  
  $base_row = 2;
  foreach ($taken_overzicht as $team => $taken){
    $counter = 1;
    $objPHPExcel->getActiveSheet()->SetCellValue("A" . $base_row, $team);
    $objPHPExcel->getActiveSheet()->getStyle("A" . $base_row . ":E" . $base_row)->applyFromArray($backgroundColor);
    foreach ($taken as $taak => $value){
      switch($taak){
        case "fluiten":
          foreach ($value["leden"] as $name => $count){
            $objPHPExcel->getActiveSheet()->SetCellValue("B" . ($base_row + $counter), $name);
            $objPHPExcel->getActiveSheet()->SetCellValue("C" . ($base_row + $counter), $count);
            $counter++;
          }
          break;
        case "tellen":
          $objPHPExcel->getActiveSheet()->SetCellValue("C" . $base_row, $value);
          break;
        case "zaalwacht":
          $objPHPExcel->getActiveSheet()->SetCellValue("D" . $base_row, $value["count"]);
          break;
      }
    }
    $base_row += $counter;
  }
  
  // Auto size columns for each worksheet
  foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
    $objPHPExcel->setActiveSheetIndex($objPHPExcel->getIndex($worksheet));
    $sheet = $objPHPExcel->getActiveSheet();
    $cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    foreach ($cellIterator as $cell) {
      $sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
    }
  }
  
  $objPHPExcel->setActiveSheetIndex(0);
  
  // redirect output to client browser
  header("Content-Type: application/vnd.openxmlformatsofficedocument.spreadsheetml.sheet");
  header("Content-Disposition: attachment;filename='scheidsschema.xlsx'");
  header("Cache-Control: max-age=0");
  $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
  $objWriter->save('php://output');

