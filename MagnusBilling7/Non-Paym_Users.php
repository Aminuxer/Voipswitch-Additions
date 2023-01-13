<?php

require("config_vs.php");
$title = 'Клиенты, по которым давно не было _списаний_ за номер';
$cut_date = isset($_GET['cut_date']) ? addslashes(substr($_GET['cut_date'], 0 ,10)) : date("Y-01-01");



$sql = mysqli_query($DB, "SELECT ic.Name, ic.LastName, ic.Address, ic.EMail, ic.Phone, ic.MobilePhone, DATE(ic.Creation_Date) AS Creation_Date,
    ld.did,  cs.account_state, lac.name, cd.client_id, cd.client_type, cs.login, cs.id_tariff, tn.description AS tariff_name,
    cs.type%2 AS enabled,   -- 1st BIT in type int-value is Enable status !!
    (SELECT DATE(MAX(data)) FROM payments p WHERE p.id_client = cd.client_id AND p.client_type = cd.client_type AND p.type = 3) as last_discharge,
    (SELECT DATE(MAX(data)) FROM payments p WHERE p.id_client = cd.client_id AND p.client_type = cd.client_type AND p.type = 1) as last_payment,
    (SELECT DATE(MAX(call_start)) FROM calls c WHERE c.id_client = cd.client_id AND c.client_type = cd.client_type) as last_call,
    pc.country_code, pc.country_name, pc.country_phonecode, pc.setup_fee, pc.monthly_fee
FROM portal_localdids  ld
    LEFT JOIN portal_clientdids cd ON cd.phone_number = ld.did
    LEFT JOIN portal_localareacodes lac ON lac.id = ld.areacode_id
    LEFT JOIN portal_countries pc ON lac.country_id = pc.id
    LEFT JOIN clientsshared cs ON cs.id_client = cd.client_id
    LEFT JOIN invoiceclients ic ON ic.IDClient = cd.client_id and ic.Type = cd.client_type
    LEFT JOIN tariffsnames tn ON tn.id_tariff = cs.id_tariff
WHERE ld.assigned != 0
  -- AND country_name = 'Russia'
HAVING last_discharge < '$cut_date'
ORDER BY last_discharge DESC");

print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>';
    include("style.css");
print '</head>
<body>'.
"\n<h1>$title (".mysqli_num_rows($sql).")</h1>\n
Дата среза: $cut_date <Br/>\n";

for ($j = 0; $j<=1; $j++) {
    $y = date("Y") - $j;
    for ($i = 1; $i<=12; $i++) {
      $d = "$y-$i-01";
      print '<a href="?cut_date='.$d.'">'.$d.'</a> ';
    }
    print "<Br/> \n";
}

print "
Этот отчет позволяет найти номера, по которым давно нет списаний за использование внешнего номера.<Br/><Br/>

Сперва выбирается граничная дата - дата среза. Даты на начало месяца можно вызвать по ссылкам в заголовке,
при необходимости детализации до дня - поправить в адресной строке. В таблице будет список номеров и логинов, для которых 
не было зафиксировано списаний за номер после даты среза.
В последних трёх столбцах указаны даты последних события для данного номера.<Br/>
Платёж - внесение среств через платёжные системы<Br/>
Звонок - совершение исходящего вызова<Br/>
Списание - Списание ежемесячной суммы за внешний номер<Br/><Br/>
в данном отчете фильтрация идёт только по датам ежемесячных списаний за внешний номер.
Даты звонков и платежей не учитываются, для этого есть другой отчет.<Br/>


<table>\n
<tr> <th>Номер</th> <th>ФИО</th> <th>Адрес</th> <th>EMail</th> <th>Телефон</th> <th>Создан</th> <th>Баланс</th> <th>Тариф</th>
     <th>Логин</th><th>ON</th> <th>Списание</th> <th>Платёж</th> <th>Звонок</th>
     </tr>\n";

while ($r = mysqli_fetch_array($sql)) {
  print '<tr> <td>'.$r['did'].'</td>
     <td>'.$r['Name'].' '.$r['LastName'].'</td>
     <td>'.$r['Address'].'</td>
     <td>'.$r['EMail'].'</td>
     <td>'.$r['Phone'].'; '.$r['MobilePhone'].'</td>
     <td>'.$r['Creation_Date'].'</td>
     <td>'.$r['account_state'].'</td>
     <td>'.$r['tariff_name'].'</td>
     <td>'.$r['login'].'</td>
     <td>'.$r['enabled'].'</td>
     <td>'.$r['last_discharge'].'</td>
     <td>'.$r['last_payment'].'</td>
     <td>'.$r['last_call'].'</td>
     '."</tr>\n";

}

print "\n</table>\n
</body></html>";

?>
