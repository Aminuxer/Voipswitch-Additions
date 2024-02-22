<?php

require("config.php");

$title = 'Frod check';

$uplink_cost_alarm = 30;   # лимит суммарного расхода в аплинке
$uplink_discharge_ratio_alarm = 4; # лимит цены за минуту
$dialing_plan_prefix_except = '^7813|^791'; # эти префиксы диалплана не смотрим

if ( $_SESSION['isAdmin'] != 1 and $ip != '127.0.0.1' and $ip != '37.153.0.13' ) { print '<a href="../" target="_blank">Only for admins</a>'; die; }

$mode = isset($_GET['mode']) ? addslashes($_GET['mode']) : 0;
$cut_date = isset($_GET['cut_date']) ? addslashes(substr($_GET['cut_date'], 0 ,10)) : date("Y-m-d");


$sql = "SELECT
    c.callerid, c.starttime, DATE_ADD(c.starttime, INTERVAL real_sessiontime SECOND) AS call_end,
    c.sessiontime, c.calledstation, c.sessionbill, c.src, c.buycost, c.real_sessiontime, c.uniqueid,
    u.username, u.credit AS client_balance, g.name AS client_type,
    p.name as tariff_name, pf.prefix, pf.destination, p.name AS tariff_desc, tr.trunkcode
FROM `pkg_cdr` c
   LEFT JOIN `pkg_user` u ON u.id = c.id_user
   LEFT JOIN `pkg_group_user` g ON g.id = u.id_group
   LEFT JOIN `pkg_plan` p ON p.id = c.id_plan
   LEFT JOIN `pkg_rate` r ON r.id_plan = c.id_plan AND r.id_prefix = c.id_prefix
   LEFT JOIN `pkg_prefix` pf ON pf.id = c.id_prefix
   LEFT JOIN `pkg_trunk`  tr ON tr.id = c.id_trunk
 WHERE  1 = 1
--  AND  c.starttime < DATE_ADD('$cut_date', INTERVAL 1 DAY)
--  AND  c.starttime > DATE_SUB('$cut_date', INTERVAL 3 DAY)
  AND  c.buycost > $uplink_cost_alarm
  AND pf.prefix NOT REGEXP '$dialing_plan_prefix_except'
  AND  (c.buycost*60/c.sessiontime) > $uplink_discharge_ratio_alarm
 ORDER BY pf.prefix, c.starttime
LIMIT 500
";

$sql_obj = mysqli_query($db, $sql);
# print "<pre> $sql </pre> <Br/>";



if ($mode == 1) {
  $num_rows = mysqli_num_rows($sql_obj);
  if ( $num_rows > 0 ) {
     $text = "<pre>Найдено $num_rows подозрительных вызовов на сервере [ ".$_SERVER['SERVER_NAME']." ],
при проверке $cut_date и трёх дней до этой даты.
Префиксы [$dialing_plan_prefix_except] исключены из проверки.
В следующих вызовах цена минуты превысила $uplink_discharge_ratio_alarm руб/минуту И суммарная цена вызова превысила $uplink_cost_alarm рублей.

|      Login /    |   Prefix-  |            Direction / Country           | Date of start call  |   Time  |        Dialed        | Cost   | Cost   |
| Dogovor Number  |   Client   |                                          |                     |         |        Number        | Client | Uplink |
|-----------------|------------|------------------------------------------|---------------------|---------|----------------------|-----------------|
";

while ($r = mysqli_fetch_array($sql_obj)) {
    
  $text .= '| '.str_pad($r['username'], 15).' | '.str_pad($r['prefix'], 10).' | '.str_pad($r['destination'], 55).
           ' | '.$r['starttime'].' | '.str_pad($r['real_sessiontime'], 7).' | '.str_pad($r['calledstation'],20).
           ' | '.str_pad(round($r['sessionbill'], 2), 6).' | '.str_pad(round($r['buycost'], 2), 6)." |\n";
}
$url = 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'].'?cut_date='.$cut_date;
$text .= '</pre>
<a href="'.$url.'">'.$url.'</a>';

print $text;
#file_get_contents("http://jabber.weba.ru/sendxmpp/?mode=silent&user=Amin|sergey&msg=".urlencode($url)."&sender=mon_paymsys&theme=VoIP_Switch_Frod_Attention");

$mail_headers = "Content-Type: text/html; charset=UTF-8\r\nFrom: VoIP-Anti-FROD <no-reply@weba.ru>\r\n";
#mail ("noc@weba.ru,sergey@weba.ru", "VoIP Switch FROD Alarm !!!", $text, $mail_headers);

  } else { print 'OK; No Frod calls detected.'; };
} else {

print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>
    <style>
   table { border-collapse: collapse; }
   thead, th { text-align: center; background-color: #bababa;  border: 1px solid #73766f; }
   td { border: 1px solid gray; }
</style>';
print '</head>
<body>'.
"\n<h1>$title</h1>\n<table>\n";

print "<Br/>cut_date: $cut_date (+ три дня назад)<Br/>
Игнорируем префиксы: $dialing_plan_prefix_except<Br/><Br/>
Расход в аплинке суммарно больше $uplink_cost_alarm   <Br/>
 <B>И</B> <Br/>
Скорость расхода в аплинке больше $uplink_discharge_ratio_alarm   <Br/>
 <Br/><Br/>\n";


print "<tr> <th colspan=\"2\">Client</th> <th colspan=\"2\">Prefixes</th>     <th colspan=\"2\">Tariff</th>             <th colspan=\"4\">Duration</th>                           <th>Number</th>  <th colspan=\"2\">Cost</th>  </tr>
<tr> <th>#</th> <th>Login</th> <th>Client</th> <th>Uplink</th>     <th>Client</th> <th>direction</th>    <th>real</th><th>account</th><th>start</th><th>end</th>       
               <th>dialed</th>  <th>client</th> <th>uplink</th> </tr> ";

while ($r = mysqli_fetch_array($sql_obj)) {
    
  print '<tr> <td>'.$r['client_type'].'</td>  <td><a title="Balance: '.$r['client_balance'].'">'.$r['username'].'</a></td>
              <td>'.$r['prefix'].'</td>  <td>-</td> <td><small>'.$r['tariff_desc'].'</small></td> <td><small>'.$r['destination'].'</small></td> 
              <td>'.$r['real_sessiontime'].'</td><td>'.$r['sessiontime'].'</td>
                   <td><a title="Orig-Call-iD: '.$r['callerid'].';"><small>'.$r['starttime'].'</small></a></td>
                   <td><a title="Term-Call-iD: '.$r['uniqueid'].';"><small>'.substr($r['call_end'], 11).'</small></a></td>
              <td><a title="Caller iD: '.$r['callerid'].'; Route: '.$r['trunkcode'].' ('.$r['destination'].')">'.$r['calledstation'].'</a></td>  
              <td class="price_td"><a title="'.round($r['sessionbill'], 4).' руб,/мин.">'.round($r['sessionbill'], 4).'</td> <td class="price_td">'.round($r['buycost'], 4).'</td>
              </tr>'."\n";
}

print "\n</table>\n
</body></html>";
};

?>
