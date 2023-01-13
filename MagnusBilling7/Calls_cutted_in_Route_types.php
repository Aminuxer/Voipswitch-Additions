<?php

require("config_vs.php");
$title = 'Звонки в разрезе типов направлений';
$cut_date = isset($_GET['cut_date']) ? addslashes(substr($_GET['cut_date'], 0 ,10)) : date("Y-01-01");

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
      print '<a href="?cut_date='.$d.'">'.$d.'</a> &nbsp;&nbsp;';
    }
    print "<Br/> \n";
}
print "<Br/>Отчетный период: 1 месяц c $cut_date <Br/><Br/>\n";


$sql_text = "SELECT c.route_type, rt.route_type_name, COUNT(id_call) AS cnt_calls,
         MIN(call_start) AS first_call, MAX(call_start) AS last_call,
         ROUND(SUM(duration)/60) AS sum_minutes,
         COUNT(DISTINCT caller_id) AS uniq_cids,
         COUNT(DISTINCT id_client, client_type) AS uniq_id_clients,
         COUNT(DISTINCT called_number) AS uniq_cnums,
         COUNT(DISTINCT ip_number) AS uniq_ip
                  -- c.id_client, c.ip_number, c.caller_id, c.called_number, c.route_type,
FROM calls c
  LEFT JOIN routetypes rt ON rt.id_route_type = c.route_type

WHERE call_start > '$cut_date 00:00:00'  and DATE(call_start) < DATE_ADD('$cut_date', INTERVAL 1 MONTH)
GROUP BY c.route_type";
$sql = mysqli_query($DB, $sql_text) or print mysqli_error($DB);
# print "<pre> $sql_text </pre>";

print "<tr> <th rowspan=\"2\">iD</th> <th rowspan=\"2\">Тип<Br/>маршрута</th> <th rowspan=\"2\">Всего<Br/>вызовов</th> <th rowspan=\"2\">Всего<Br/>минут</th> <th colspan=\"4\">Уникальных</th> <th rowspan=\"2\">Первый вызов</th> <th rowspan=\"2\">Последний вызов</th> </tr>
<tr> <th>Client iD</th> <th>Caller iD</th> <th>Dial Number</th> <th>IP</th> </tr>";

while ($r = mysqli_fetch_array($sql)) {
  print '<tr> <td>'.$r['route_type'].'</td> <td>'.$r['route_type_name'].'</td> <td>'.$r['cnt_calls'].'</td> <td>'.$r['sum_minutes'].'</td>
              <td>'.$r['uniq_id_clients'].'</td> <td>'.$r['uniq_cids'].'</td> <td>'.$r['uniq_cnums'].'</td> <td>'.$r['uniq_ip'].'</td>
              <td>'.$r['first_call'].'</td> <td>'.$r['last_call']."</td> </tr>\n";

}

print "\n</table>\n
</body></html>";

?>