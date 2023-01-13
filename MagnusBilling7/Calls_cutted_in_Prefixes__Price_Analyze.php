<?php

require("config_vs.php");
$title = 'Исходящие звонки в разрезе префиксов, анализ цен (beta)';
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
  ROUND(SUM(c.cost*c.ratio), 2) AS sum_cost_4_client_with_discount,          -- cost - in dollars, ratio - ruble/dollar rate at call-time
  ROUND(SUM(c.call_rate*c.effective_duration)/60, 2) AS sum_cost_4_client,  -- cost - call_rate - rubles/minute, eff.duaration - in SECONDS !!!
  ROUND(SUM(c.costD), 2) AS sum_cost_4_uplink                                -- costD - intagrated
FROM calls c
  LEFT JOIN tariffs t ON t.id_tariff = 18 AND c.tariff_prefix = t.prefix

WHERE call_start > '$cut_date 00:00:00' AND call_start < DATE_ADD('$cut_date', INTERVAL 1 MONTH)
 AND c.route_type = 0
GROUP BY c.tariff_prefix
ORDER BY  sum_minutes DESC";

$sql = mysqli_query($DB, $sql_text) or print mysqli_error($DB);
# print "<pre> $sql_text </pre>";

$sum_discount = $sum_dohod = $sum_rashod = $sum_profit = $sum_calls = $sum_minutes = 0;
 
print "!!! С ценами требуется ручной анализ в VoipSwitch !!!<Br/>
<table>\n
<tr> <th>Prefix</th> <th>Название</th> <th>Вызовов</th> <th>Минут</th>
     <th>Чистый доход<Br/>от клиентов</th> <th>Скидок<Br/>по пакетам</th>
     <th>Расходы<Br/>в аплинках</th>
     <th>Прибыль</th> </tr>";

while ($r = mysqli_fetch_array($sql)) {
  $sum_calls += $r['cnt_calls'];
  $sum_minutes += $r['sum_minutes'];
  $discount = round( $r['sum_cost_4_client'] - $r['sum_cost_4_client_with_discount'] , 2);
  $sum_discount += $discount;
  $sum_dohod += $r['sum_cost_4_client'];
  $sum_rashod += $r['sum_cost_4_uplink'];

  if ($r['sum_cost_4_client'] < $r['sum_cost_4_uplink']) { $remark = '<font class="red"><B>⚠</B></font>&nbsp;&nbsp;'; } else { $remark = ''; };
  if ($discount < 0) { $remark_disc = '<font class="red"><B>⚠</B></font>&nbsp;&nbsp;'; } else { $remark_disc = ''; };

  print '<tr> <td><a href="Tariff_detail_prefixes.php?ref_prefix='.$r['tariff_prefix'].'&ref_tariff=18" target="_blank">'.$r['tariff_prefix'].'</a></td>
              <td>'.$r['prefix_name'].'</td> <td>'.$r['cnt_calls'].'</td> <td>'.$r['sum_minutes'].'</td>
              <td class="price_td">'.$r['sum_cost_4_client'].'</td> <td class="price_td">'.$remark_disc.$discount.'</td>
              <td class="price_td">'.$r['sum_cost_4_uplink'].'</td>
              <td class="price_td">'.$remark.( $r['sum_cost_4_client'] - $r['sum_cost_4_uplink'])."</td>
              </tr>\n";
}

  print '<tr> <td colspan="2"></td> <td>'.$sum_calls.'</td> <td>'.$sum_minutes.'</td> <td class="price_td">'.$sum_dohod.'</td> <td class="price_td">'.$sum_discount.'</td> <td class="price_td">'.$sum_rashod.'</td>
              <td class="price_td">'.round(($sum_dohod - $sum_rashod), 2)."</td> </tr>\n";
              
print "\n</table>\n
</body></html>";

?>