<?php

require("config_vs.php");
$title = 'RETAIL-Аккаунты с нулевым балансом, без номеров';

$sql = mysqli_query($DB, "SELECT cs.id_client, cs.id_tariff, cs.type, cr.account_state, cr.login, t.description AS tariff_name
FROM clientsshared cs
   LEFT JOIN clientsretail cr ON cr.login = cs.login AND cr.id_client = cs.id_client
   LEFT JOIN tariffsnames  t  ON cs.id_tariff = t.id_tariff
WHERE cs.account_state = 0
  AND cs.id_client NOT IN (SELECT cd.client_id FROM portal_clientdids cd)
  -- AND cs.id_client NOT IN (SELECT p.id_client FROM payments p WHERE p.client_type = 32 AND p.type = 1)
  AND cs.id_tariff != 47 -- NOT ERCS
  -- AND cs.login NOT LIKE '000%'
  -- AND cs.login NOT LIKE '100%'
HAVING cr.login IS NOT NULL
ORDER BY cr.login");

print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>';
    include("style.css");
print '</head>
<body>'.
"\n<h1>$title (".mysqli_num_rows($sql).")</h1>\n<table>\n
<tr> <th>Логин</th> <th>Тариф</th> </tr>\n";

while ($r = mysqli_fetch_array($sql)) {
  print '<tr>
     <td>'.$r['login'].'</td>
     <td>'.$r['tariff_name'].'</td>
    '."</tr>\n";

}

print "\n</table>\n
</body></html>";

?>