<?php

require("config_vs.php");
$title = 'Исходящие звонки в разрезе префиксов, анализ звонков (beta)';
$cut_date = isset($_GET['cut_date']) ? addslashes(substr($_GET['cut_date'], 0 ,10)) : date("Y-01-01");

print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>';
    include("style.css");
print '</head>
<body>'.
"\n<h1>$title</h1>\n";

for ($j = 0; $j<=1; $j++) {
    $y = date("Y") - $j;
    for ($i = 1; $i<=12; $i++) {
      $d = "$y-$i-01";
      print '<a href="?cut_date='.$d.'">'.$d.'</a> &nbsp;&nbsp;';
    }
    print "<Br/> \n";
}
print "<Br/>Отчетный период: 1 месяц c $cut_date <Br/><Br/>\n";


$sql_text = " SELECT c.tariff_prefix, t.description AS prefix_name, count(id_call) AS cnt_calls,
  ROUND(SUM(duration)/60) AS sum_minutes,                                    -- duration in DB - seconds !!!
  MIN(call_start) AS first_call, MAX(call_start) AS last_call,
         COUNT(DISTINCT caller_id) AS uniq_cids,
         COUNT(DISTINCT id_client, client_type) AS uniq_id_clients,
         COUNT(DISTINCT called_number) AS uniq_cnums,
         COUNT(DISTINCT ip_number) AS uniq_ip

FROM calls c
  LEFT JOIN tariffs t ON t.id_tariff = 18 AND c.tariff_prefix = t.prefix

WHERE call_start > '$cut_date 00:00:00' AND call_start < DATE_ADD('$cut_date', INTERVAL 1 MONTH)
 AND c.route_type = 0
GROUP BY c.tariff_prefix
ORDER BY  sum_minutes DESC";

$sql = mysqli_query($DB, $sql_text) or print mysqli_error($DB);
# print "<pre> $sql_text </pre>";

 
print "<table>\n
<tr> <th rowspan=\"2\">iD</th> <th rowspan=\"2\">Тип<Br/>маршрута</th> <th rowspan=\"2\">Всего<Br/>вызовов</th> <th rowspan=\"2\">Всего<Br/>минут</th> <th colspan=\"4\">Уникальных</th> <th rowspan=\"2\">Первый вызов</th> <th rowspan=\"2\">Последний вызов</th> </tr>
<tr> <th>Client iD</th> <th>Caller iD</th> <th>Dial Number</th> <th>IP</th> </tr>";

while ($r = mysqli_fetch_array($sql)) {
  print '<tr> <td><a href="Tariff_detail_prefixes.php?ref_prefix='.$r['tariff_prefix'].'&ref_tariff=18" target="_blank">'.$r['tariff_prefix'].'</a></td>
              <td>'.$r['prefix_name'].'</td> <td>'.$r['cnt_calls'].'</td> <td>'.$r['sum_minutes'].'</td>
              <td>'.$r['uniq_id_clients'].'</td> <td>'.$r['uniq_cids'].'</td> <td>'.$r['uniq_cnums'].'</td> <td>'.$r['uniq_ip'].'</td>
              <td>'.$r['first_call'].'</td> <td>'.$r['last_call']."</td> </tr>\n";
}
              
print "\n</table>\n
</body></html>";

?>