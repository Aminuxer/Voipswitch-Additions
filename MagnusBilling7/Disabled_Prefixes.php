<?php

require("config_vs.php");
$title = 'Запрещённые префиксы';

$sql = mysqli_query($DB, "SELECT t.id_tariff, tn.description AS tariff_name, t.prefix, t.description AS prefix_name
FROM tariffs t
  LEFT JOIN tariffsnames tn ON tn.id_tariff = t.id_tariff
WHERE t.voice_rate < 0
 -- AND t.id_tariff IN (SELECT DISTINCT cs.id_tariff FROM clientsshared cs WHERE cs.id_tariff > 0)
GROUP BY prefix, prefix_name, tariff_name
ORDER BY prefix") or print mysqli_error($DB);

print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>';
    include("style.css");
print '</head>
<body>'.
"\n<h1>$title (".mysqli_num_rows($sql).")</h1>\n
Это список префиксов, куда вызовы запрещены по причине крайне частого
использования в мошеннических целях.
<table>\n

 <tr> <th>Тариф iD</th> <th>Тариф</th>  <th>Prefix</th> <th>Направление</th> </tr>";

while ($r = mysqli_fetch_array($sql)) {
  print '<tr> <td>'.$r['id_tariff'].'</td> <td>'.$r['tariff_name'].'</td> <td>'.$r['prefix'].'</td> <td>'.$r['prefix_name']."</td></tr>\n";

}

print "\n</table>\n
</body></html>";

?>