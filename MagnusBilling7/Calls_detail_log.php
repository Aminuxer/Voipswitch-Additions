<?php

require("config_vs.php");

$title = 'Детальная информация по звонкам';

$ref_tariff = isset($_GET['ref_tariff']) ? (int) $_GET['ref_tariff'] : '';
$ref_prefix = isset($_GET['ref_prefix']) ? addslashes($_GET['ref_prefix']) : '';
$ref_phone = isset($_GET['ref_phone']) ? addslashes($_GET['ref_phone']) : '';
$cut_date = isset($_GET['cut_date']) ? addslashes(substr($_GET['cut_date'], 0 ,10)) : date("Y-01-01");
$ref_login = isset($_GET['ref_login']) ? addslashes($_GET['ref_login']) : '';
$ref_route_type = isset($_GET['ref_route_type']) ? addslashes($_GET['ref_route_type']) : '';


$clients_tariffs_sql = mysqli_query($DB, "SELECT DISTINCT cs.id_tariff, tn.description AS tariff_name FROM clientsshared cs
   LEFT JOIN tariffsnames tn ON tn.id_tariff = cs.id_tariff
WHERE cs.id_tariff > 0") or print mysqli_error($DB);


$clients_routes_sql = mysqli_query($DB, "SELECT DISTINCT c.route_type, rt.route_type_name
FROM calls c
  LEFT JOIN routetypes rt ON rt.id_route_type = c.route_type
WHERE c.call_start > '$cut_date 00:00:00'
AND c.call_start < DATE_ADD('$cut_date', INTERVAL 1 MONTH)") or print mysqli_error($DB);



$sql_filter = '';

if ($ref_tariff != '') { $sql_filter .= " AND c.id_tariff = '$ref_tariff' "; };
if ($ref_prefix != '') { $sql_filter .= " AND tariff_prefix LIKE '$ref_prefix%' "; };
if ($ref_phone != '') { $sql_filter .= " AND called_number LIKE '%$ref_phone%' "; };
if ($ref_login != '')  { $sql_filter .= " AND cs.login LIKE '$ref_login%' "; };
if ($ref_route_type != '') { $sql_filter .= " AND c.route_type = '$ref_route_type' "; };

$sql = "SELECT
    dialing_plan_prefix, tariff_prefix, caller_id, called_number, duration,
    effective_duration, call_rate,
    (cost*ratio) AS cost_4_client_with_discount,
    costD AS uplink_discharge,
    c.ip_number AS client_ip,
    call_start, call_end, tariffdesc,
    c.id_client, id_call, route_type, c.id_tariff, c.client_type, c.id_route,
    id_cc, orig_call_id, term_call_id, id_cn,

    tn.description AS tariff_name,
    cs.login AS client_login, cs.account_state AS client_balance,
    gw.description AS route_description, gw.ip_number AS route_ip,
    ct.client_type_name

FROM calls c
   LEFT JOIN tariffsnames tn ON tn.id_tariff = c.id_tariff
   LEFT JOIN clientsshared cs ON cs.id_client = c.id_client
   LEFT JOIN gateways gw ON gw.id_route = c.id_route
   LEFT JOIN clienttypes ct ON ct.id_client_type = c.client_type
WHERE call_start > '$cut_date 00:00:00'
  AND call_start < DATE_ADD('$cut_date', INTERVAL 1 MONTH)
  $sql_filter

ORDER BY c.tariff_prefix, c.call_start
LIMIT 1000
";

$sql_obj = mysqli_query($DB, $sql);
 # print "<pre> $sql </pre> <Br/>";

function sec2time ($sec) {
  $sec = (int) $sec;
  $time = ''; $h = 0;
  if  ($sec >= 3600 ) {
    $h = (int) ($sec / 3600);
    if ($h > 0) { $time .= $h.':'; }
    $sec = $sec - $h*3600;
  }
  $m = (int) ($sec / 60);
    if ($h == 0) { $time .= $m.':'; }
            else { $time .=  sprintf("%02d", $m).':'; };
  if ($m > 0) { $sec = $sec - $m*60; }
  $time .= sprintf("%02d", $sec);
  return $time;
}

print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>';
    include("style.css");
print '</head>
<body>'.
"\n<h1>$title</h1>\n<table>\n";


for ($j = 0; $j<=1; $j++) {
    $y = date("Y") - $j;
    for ($i = 1; $i<=12; $i++) {
      $d = "$y-$i-01";
      if ($cut_date == $d) { print '[<B>'; }
      print '<a href="?cut_date='.$d.'&ref_tariff='.$ref_tariff.'&ref_prefix='.$ref_prefix.'&ref_login='.$ref_login.'&ref_phone='.$ref_phone.'&ref_route_type='.$ref_route_type.'">'.$d.'</a>';
      if ($cut_date == $d) { print '</B>]'; }
      print '&nbsp;&nbsp;';
    }
    print "<Br/> \n";
}

print "<Br/>Отчетный период: 1 месяц c $cut_date <Br/><Br/>\n";



print "Тариф:\n";
while ($r = mysqli_fetch_array($clients_tariffs_sql)) {
  $tariff_id = $r['id_tariff'];
    if  ($ref_tariff == $tariff_id) { print "<B>"; $tariff_name = $r['tariff_name']; }
  print ' [<a href="?ref_tariff='.$tariff_id.'&ref_prefix='.$ref_prefix.'&cut_date='.$cut_date.'&ref_login='.$ref_login.'&ref_phone='.$ref_phone.'&ref_route_type='.$ref_route_type.'">'.$r['tariff_name'].'</a>] ';
    if  ($ref_tariff == $tariff_id) { print "</B>"; }
  $tariffs[$tariff_id]['id'] = $tariff_id;
  $tariffs[$tariff_id]['name'] = $r['tariff_name'];
}
print ' [<a href="?ref_tariff=&ref_prefix='.$ref_prefix.'&cut_date='.$cut_date.'&ref_login='.$ref_login.'&ref_phone='.$ref_phone.'&ref_route_type='.$ref_route_type.'">ВСЕ</a>] ';


print "<Br/>Тип маршрута:\n";
while ($r = mysqli_fetch_array($clients_routes_sql)) {
  $route_type = $r['route_type'];
    if  ($ref_route_type == $route_type) { print "<B>"; $route_type_name = $r['route_type_name']; }
  print ' [<a href="?ref_route_type='.$route_type.'&ref_tariff='.$ref_tariff.'&ref_prefix='.$ref_prefix.'&cut_date='.$cut_date.'&ref_login='.$ref_login.'&ref_phone='.$ref_phone.'">'.$r['route_type_name'].'</a>] ';
    if  ($ref_route_type == $route_type) { print "</B>"; }
}
print ' [<a href="?ref_route_type=&ref_tariff='.$ref_tariff.'&ref_prefix='.$ref_prefix.'&cut_date='.$cut_date.'&ref_login='.$ref_login.'&ref_phone='.$ref_phone.'">ВСЕ</a>] ';


print '<Br/><form method="get">Prefix: <input type="text" name="ref_prefix" value="'.$ref_prefix.'">
                               Login: <input type="text" name="ref_login" value="'.$ref_login.'">
                               Dial Number: <input type="text" name="ref_phone" value="'.$ref_phone.'">
                   <input type="hidden" name="ref_route_type" value="'.$ref_route_type.'">
                   <input type="hidden" name="ref_tariff" value="'.$ref_tariff.'">
                   <input type="hidden" name="cut_date" value="'.$cut_date.'"> 
               <input type="submit" value="Search">
  </form> <Br/><Br/>';
  if ($repl_cnt > 0) { print "В префиксе сделана замена `810` : <B>$ref_prefix</B> -> <B>$cut_refprefix</B> <Br/>"; }
print ' Найдено <B>'.mysqli_num_rows($sql_obj).'</B> вызовов <Br/> <Br/>';
  

/*
print "<tr> <th colspan=\"2\">Клиент</th> <th colspan=\"2\">Префиксы</th>     <th colspan=\"2\">Тариф</th>             <th colspan=\"4\">Длительность</th>                           <th>Номер</th>  <th colspan=\"2\">Цена</th>  </tr>
<tr> <th>#</th> <th>Логин</th> <th>Диал-<Br/>план</th> <th>Тариф</th>     <th>Клиента</th> <th>Направление</th>    <th>Точная</th><th>Учёт<Br/>мин.</th><th>Начало</th><th>Конец</th>       
       <th>Набранный</th>  <th>Клиенту</th> <th>Аплинку</th> </tr> ";
*/
print "<tr> <th colspan=\"2\">Client</th> <th colspan=\"2\">Prefixes</th>     <th colspan=\"2\">Tariff</th>             <th colspan=\"4\">Duration</th>                           <th>Number</th>  <th colspan=\"2\">Cost</th>  </tr>
<tr> <th>#</th> <th>Login</th> <th>Dial-<Br/>Plan</th> <th>Tariff</th>     <th>Client</th> <th>direction</th>    <th>real</th><th>account</th><th>start</th><th>end</th>       
               <th>dialed</th>  <th>client</th> <th>uplink</th> </tr> ";

while ($r = mysqli_fetch_array($sql_obj)) {
    
  print '<tr> <td><a title="'.$r['client_type_name'].'">'.$r['client_type'].'</a></td>  <td><a title="Balance: '.$r['client_balance'].' Client IP: '.$r['client_ip'].'">'.$r['client_login'].'</a></td>
              <td>'.$r['dialing_plan_prefix'].'</td>  <td>'.$r['tariff_prefix'].'</td> <td><small>'.$r['tariff_name'].'</small></td> <td><small>'.$r['tariffdesc'].'</small></td> 
              <td><a title="'.$r['duration'].' сек.">'.sec2time($r['duration']).'</td><td>'.sec2time($r['effective_duration']).'</td>
                   <td><a title="Orig-Call-iD: '.$r['orig_call_id'].';"><small>'.$r['call_start'].'</small></a></td>
                   <td><a title="iD CN: '.$r['id_cn'].'; iD CC: '.$r['id_cc'].'; Term-Call-iD: '.$r['term_call_id'].';"><small>'.substr($r['call_end'], 11).'</small></a></td>
              <td><a title="Caller iD: '.$r['caller_id'].'; Route: '.$r['route_ip'].' ('.$r['route_description'].')">'.$r['called_number'].'</a></td>  
              <td class="price_td"><a title="'.round($r['call_rate'], 4).' руб,/мин.">'.round($r['cost_4_client_with_discount'], 4).'</td> <td class="price_td">'.round($r['uplink_discharge'], 4).'</td>
              </tr>'."\n";
}

print "\n</table>\n
</body></html>";

?>