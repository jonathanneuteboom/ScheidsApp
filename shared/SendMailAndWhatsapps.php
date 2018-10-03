<?php
$from_address = "scheids@skcvolleybal.nl";
$from_name = "Anne Vieveen";

$mail_address_scheidsco = "scheids@skcvolleybal.nl";
$name_scheidsco = "Anne Vieveen";

error_reporting(E_ALL);
ini_set("display_errors", 1);

include "func.inc.php";

$isServerRequest = $_SERVER['SERVER_ADDR'] === $_SERVER['REMOTE_ADDR'];

$user = GetUser();
if ($user->guest && !$isServerRequest) {
    exit("Je bent automatisch uitgelogd, doordat je te lang inactief bent geweest. Log opnieuw in");
}

if (!isScheidsco($user) && !$isServerRequest) {
    return json_encode(array("error" => "Jij bent geen ScheidsCo!"));
}

$dbc = GetDBConnection();

$matches = GetMatches();

$scheduled_matches = GetScheduledRefsAndTellersForNextWeek($dbc);
$scheduled_zaalwacht = GetScheduledZaalwachtForNextWeek($dbc);

$referees = GetReferees($dbc);

$referee_template = "
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<body>
Beste {{naam}},<br>
<br>
Aanstaande {{datum}} moet je een wedstrijd fluiten om {{tijd}}.<br>
Je zult de wedstrijd {{teams}} gaan fluiten.<br>
<br>
Voor meer informatie kun je naar de www.skcvolleybal.nl gaan. Hier kun je meer zien bij de ScheidsrechtersApp (eerst inloggen).<br>
<br>
Je kunt je schema importeren met de volgende google calendar link:<br>
https://www.google.com/calendar/render?cid=https://www.skcvolleybal.nl/scripts/ScheidsrechtersApp/shared/Calendars/user_{{user_id}}.ics<br>
Als je dit importeert worden alle wedstrijden automatisch in je google calendar gezet en ben je in 1 keer van al jouw wedstrijden op de hoogte.<br>
(het kan even duren voordat je agenda wordt gesynchroniseerd...)<br>
<br>
Met vriendelijke groet,<br>
<br>
$from_name<br>
<br>
(Dit is een automatisch gegenereerd bericht)
</body>
</html>";

$tellers_template = "
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<body>
Beste {{naam}},<br>
<br>
Aanstaande {{datum}} moet jouw team een wedstrijd tellen om {{tijd}}.<br>
Jullie zullen de wedstrijd {{teams}} gaan tellen.<br>
<br>
Met vriendelijke groet,<br>
<br>
$from_name<br>
<br>
(Dit is een automatisch gegenereerd bericht)
</body>
</html>";

$zaalwacht_template = "
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<body>
Beste {{naam}},<br>
<br>
Aanstaande {{datum}} heeft jouw team zaalwacht.<br>
<br>
Op de website kun je ook <a href='https://www.skcvolleybal.nl/index.php/informatie/praktische-info/documenten?download=47:zaalwachtinstructie2016-2017-dwf'>hier</a> nog wat meer informatie aantreffen over de zaalwacht.
<br>
Met vriendelijke groet,<br>
<br>
$from_name<br>
<br>
(Dit is een automatisch gegenereerd bericht)
</body>
</html>";

$samenvatting_template = "
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<body>
Beste {{naam}},<br>
<br>
De volgende mensen hebben zojuist een mail gekregen vanuit de webserver over het fluit/tel/zaalwacht schema:<br>
<br>
{{content}}
<br>
Met vriendelijke groet,<br>
<br>
De (binkies van de) Webcie,<br>
<br>
(Dit is een automatisch gegenereerd bericht)
</body>
</html>";

$fluiters_whatsapp_template = "Hi {{naam}},\n\nEven een reminder. Je moet de volgende wedstrijd fluiten:\n\n##414## {{datum}}\n##786## {{tijd}}\n##127## {{teams}}\n\nSucces met fluiten! ##2##";
$tellers_whatsapp_template = "Hi {{naam}},\n\nEven een reminder. Jouw team moet de volgende wedstrijd tellen:\n\n##414## {{datum}}\n##786## {{tijd}}\n##127## {{teams}}\n\nSucces met tellen! ##2##";
$zaalwacht_whatsapp_template = "Hi {{naam}},\n\nEven een reminder. Jouw team heeft de volgende datum zaalwacht:\n\n##414## {{datum}}\n\nSucces met de zaalwacht! ##2##";

$samenvatting = array();

// Send mails for each referee and team to count
foreach ($scheduled_matches as $match) {
    $mail_body = $referee_template;
    $whatsapp_body = $fluiters_whatsapp_template;

    $user_id = $match['user_id'];
    $date = $match['date'];
    $time = $match['time'];
    $code = $match['code'];
    $tellers = $match['tellers'];
    $teams = $matches[$date][$time][$code]['teams'];
    $date_NL = $matches[$date][$time][$code]['date_string'];

    if ($user_id != null) {
        $referee = $referees[$user_id];
        $name = $referee['name'];

        $mail_address = $referee['email'];
        $mail_body = str_replace("{{naam}}", $name, $mail_body);
        $mail_body = str_replace("{{datum}}", $date_NL, $mail_body);
        $mail_body = str_replace("{{tijd}}", $time, $mail_body);
        $mail_body = str_replace("{{teams}}", $teams, $mail_body);
        $mail_body = str_replace("{{user_id}}", $user_id, $mail_body);

        // Recreate the xlsx-schedule for this user
        //$attachment = str_replace("http://www.skcvolleybal.nl/", "/var/www/", WriteScheduleToExcel($dbc, $user_id, $name));

        $phone_number = $referee['number'];
        $whatsapp_body = str_replace("{{naam}}", $name, $whatsapp_body);
        $whatsapp_body = str_replace("{{datum}}", $date_NL, $whatsapp_body);
        $whatsapp_body = str_replace("{{tijd}}", $time, $whatsapp_body);
        $whatsapp_body = str_replace("{{teams}}", $teams, $whatsapp_body);
        // Send to referees
        $samenvatting['scheidsrechters'][] = array("name" => $name, "mail" => $mail_address, "number" => $phone_number);
        SendMail($from_address, $from_name, $mail_address, $name, "Fluiten " . $teams, $mail_body, $phone_number, $whatsapp_body, $attachment);
    }

    if ($tellers != null) {
        // Send to tellers
        $team_members = GetTeamMembers($dbc, $tellers);
        foreach ((array) $team_members as $m) {
            $tellers_mail_body = $tellers_template;
            $tellers_mail_body = str_replace("{{naam}}", $m['name'], $tellers_mail_body);
            $tellers_mail_body = str_replace("{{datum}}", $date_NL, $tellers_mail_body);
            $tellers_mail_body = str_replace("{{tijd}}", $time, $tellers_mail_body);
            $tellers_mail_body = str_replace("{{teams}}", $teams, $tellers_mail_body);

            $tellers_whatsapp_body = $tellers_whatsapp_template;
            $tellers_whatsapp_body = str_replace("{{naam}}", $m['name'], $tellers_whatsapp_body);
            $tellers_whatsapp_body = str_replace("{{datum}}", $date_NL, $tellers_whatsapp_body);
            $tellers_whatsapp_body = str_replace("{{tijd}}", $time, $tellers_whatsapp_body);
            $tellers_whatsapp_body = str_replace("{{teams}}", $teams, $tellers_whatsapp_body);

            $samenvatting['tellers'][$tellers][] = array("name" => $m['name'], "mail" => $m['email'], "number" => $m['number']);
            SendMail($from_address, $from_name, $m['email'], $m['name'], "Tellen " . $teams, $tellers_mail_body, $m['number'], $tellers_whatsapp_body);
        }
    }
}

foreach ($scheduled_zaalwacht as $zaalwacht) {
    // Send to Zaalwacht
    $team_members = GetTeamMembers($dbc, $zaalwacht['team']);
    foreach ((array) $team_members as $m) {
        $zaalwacht_mail_body = $zaalwacht_template;
        $zaalwacht_mail_body = str_replace("{{naam}}", $m['name'], $zaalwacht_mail_body);
        $zaalwacht_mail_body = str_replace("{{datum}}", $zaalwacht['date'], $zaalwacht_mail_body);

        $zaalwacht_whatsapp_body = $zaalwacht_whatsapp_template;
        $zaalwacht_whatsapp_body = str_replace("{{naam}}", $m['name'], $zaalwacht_whatsapp_body);
        $zaalwacht_whatsapp_body = str_replace("{{datum}}", $zaalwacht['date'], $zaalwacht_whatsapp_body);

        $samenvatting['zaalwacht'][$zaalwacht['team']][] = array("name" => $m['name'], "mail" => $m['email'], "number" => $m['number']);
        SendMail($from_address, $from_name, $m['email'], $m['name'], "Zaalwacht " . $zaalwacht['date'], $zaalwacht_mail_body, $m['number'], $zaalwacht_whatsapp_body);
    }
}

// Create the summary mail
$mail_content = "<b>Scheidsrechters</b><br>";
if (!empty($samenvatting['scheidsrechters'])) {
    foreach ($samenvatting['scheidsrechters'] as $s) {
        $mail_content .= "   " . $s['name'] . " (" . $s['mail'];
        if ($s['number'] != "") {
            $mail_content .= ", " . $s['number'];
        }
        $mail_content .= ")<br>";
    }
}

$mail_content .= "<br><b>Tellers</b>";
if (!empty($samenvatting['tellers'])) {
    foreach ($samenvatting['tellers'] as $teamname => $team) {
        $mail_content .= "<br>$teamname<br>";
        foreach ($team as $members) {
            $mail_content .= "      " . $members['name'] . " (" . $members['mail'];
            if ($members['number'] != "") {
                $mail_content .= ", " . $members['number'];
            }
            $mail_content .= ")<br>";
        }
    }
}

$mail_content .= "<br><b>Zaalwacht</b>";
if (!empty($samenvatting['zaalwacht'])) {
    foreach ($samenvatting['zaalwacht'] as $teamname => $team) {
        $mail_content .= "<br>$teamname<br>";
        foreach ($team as $members) {
            $mail_content .= "      " . $members['name'] . " (" . $members['mail'];
            if ($members['number'] != "") {
                $mail_content .= ", " . $members['number'];
            }
            $mail_content .= ")<br>";
        }
    }
}

SendMail("scheids@skcvolleybal.nl",
    "SKC scheidsrechtercoördinator",
    $mail_address_scheidsco,
    $name_scheidsco,
    "Samenvatting fluit/tel/zaalwacht mails " . date("j-M-Y"),
    str_replace("{{naam}}", $name_scheidsco, str_replace("{{content}}", $mail_content, $samenvatting_template)));

SendMail("scheids@skcvolleybal.nl",
    "SKC scheidsrechtercoördinator",
    "jonathan.neuteboom@gmail.com",
    "Jonathan Neuteboom",
    "Samenvatting fluit/tel/zaalwacht mails " . date("j-M-Y"),
    str_replace("{{naam}}", $name_scheidsco, str_replace("{{content}}", $mail_content, $samenvatting_template)));

echo "Verzonden";
