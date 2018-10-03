<?php
  include('func.inc.php');
  
  error_reporting(0);
  
  $user = GetUser();
  $dbc = GetDBConnection();
  
  // Create excel with all detailed information
  $xlsx = WriteScheduleToExcel($dbc, $user->id, $user->name);
  
  // Create ical which can be imported in google
  $ical = "https://www.google.com/calendar/render?cid=https://www.skcvolleybal.nl/scripts/ScheidsrechtersApp/shared/Calendars/" . SetGoogleCalendars($dbc, $user->id);
  
  echo json_encode(array("ical_file" => $ical, "xlsx" => $xlsx));
?>
