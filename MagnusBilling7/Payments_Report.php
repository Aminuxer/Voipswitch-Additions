<?php

require("config_vs.php");
$title = 'Отчёт по платежам за телефонию';
$cut_date = isset($_GET['cut_date']) ? addslashes(substr($_GET['cut_date'], 0 ,10)) : date("Y-01-01");
$search = isset($_GET['f_search']) ? $_GET['f_search'] : '';

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
print "<Br/>Отчетный период: 1 месяц c $cut_date 
<form method=\"GET\">
Поиск в комментах: <INPUT type=\"text\" name=\"f_search\" value=\"".htmlspecialchars($search)."\">
<INPUT type=\"hidden\" name=\"cut_date\" value=\"$cut_date\">
<INPUT type=\"submit\">
</form>
<Br/><Br/>\n";

if ($search != '') { $subq = " AND p.description LIKE '%".addslashes($search)."%' "; } else { $subq = ''; };

$sql_text = "SELECT p.*, cs.login, ic.Name, ic.Address FROM payments p
   LEFT JOIN clientsshared cs ON cs.id_client = p.id_client
   LEFT JOIN invoiceclients ic ON ic.IDClient = cs.id_client and ic.Type = p.client_type
WHERE p.type = 1 AND p.data > '$cut_date 00:00:00' AND p.data < DATE_ADD('$cut_date', INTERVAL 1 MONTH)
$subq
ORDER BY p.client_type, p.id_client;";

$sql = mysqli_query($DB, $sql_text) or print mysqli_error($DB);
# print "<pre> $sql_text </pre>";

$sum_money = $sum_commented = 0;
 
print "<table width=\"80%\">
<tr> <th>Логин</th> <th>Имя</th> <th>Адрес</th> <th>Дата</th> <th>Сумма</th> <th>Комментарий</th>
</tr>";

while ($r = mysqli_fetch_array($sql)) {
  $sum_money += $r['money'];
  if ($r['description'] != '') { $sum_commented += $r['money']; }

  print '<tr> <td>'.$r['login'].'</td> <td>'.$r['Name'].'</td> <td>'.$r['Address'].'</td>
              <td><nobr>'.$r['data'].'</nobr></td> <td>'.round($r['money'], 2).'</td> <td>'.$r['description'].'</td>
              '."</tr>\n";
}

   print '<tr> <td colspan="4">∑</td> <td>'.$sum_money.'</td> <td>Commented payments summ: '.$sum_commented.'</td> </tr>'."\n";
              
print "\n</table>\n
</body></html>";

?>
