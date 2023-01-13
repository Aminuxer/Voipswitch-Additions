<?php

require("config.php");
$title = 'Детальная информация по префиксам в клиентском тарифе';
$ref_tariff = isset($_GET['ref_tariff']) ? (int) $_GET['ref_tariff'] : 1;
$ref_prefix = isset($_GET['ref_prefix']) ? addslashes($_GET['ref_prefix']) : '';
$ref_country = isset($_GET['ref_country']) ? addslashes($_GET['ref_country']) : '';
$mode = isset($_GET['mode']) ? (int) $_GET['mode'] : 2;


$tariff_prefixes_sql = "SELECT
    p.prefix, p.destination AS prefix_name, t.rateinitial, LENGTH(p.prefix) AS prefix_length, t.status,
    tgp.id AS tgp_id, tgp.name AS trunk_group_name
  FROM pkg_rate t
   INNER JOIN pkg_prefix p ON p.id = t.id_prefix
   LEFT JOIN pkg_trunk_group tgp ON tgp.id = t.id_trunk_group
  WHERE t.id_plan = '$ref_tariff' AND (p.prefix LIKE '$ref_prefix%') AND (p.destination LIKE '$ref_country%')
  ORDER BY p.destination, p.prefix ASC";

$tariff_prefixes = mysqli_query($db, $tariff_prefixes_sql);
# print $tariff_prefixes_sql;

print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>
    <style>
   table { border-collapse: collapse; }
   thead, th { text-align: center; background-color: #bababa;  border: 1px solid #73766f; }
   td { border: 1px solid gray; }
   .bgred { background-color:red; font-weight:bold; }
   .dis { text-decoration: line-through; color: maroon; text-decoration-color: red; }
</style>
</head>
<body>'.
"\n<h1>$title</h1>\n";

$out = 'Найдено <B>'.mysqli_num_rows($tariff_prefixes).'</B> префиксов клиентского тарифа <B>'.get_name_client_tariff($ref_tariff)."</B> [iD: $ref_tariff] ";

print '<Br/><form method="get">Начало префикса: <input type="text" name="ref_prefix" value="'.$ref_prefix.'">
              Страна: <input type="text" name="ref_country" value="'.$ref_country.'">
              '.create_select_client_tariff('ref_tariff').'
       <input type="submit" value="Search">
  </form> <Br/><Br/>';

$tds = '<Br/>';
  

$tds .= "<table>\n<tr> <th>Prefix</th> <th>Направление</th> <th>Цена</th> <th>Trunks</th> </tr>";

$min_price = 9999999; $max_price = 0;
while ($r = mysqli_fetch_array($tariff_prefixes)) {
  if ( $r['tgp_id'] > 1 )  { $uplclass = 'bgred'; } else { $uplclass = ''; };
  if ( $r['status'] != 1 ) { $uplclass .= ' dis'; }
  $tds .= '<tr class="'.$uplclass.'"> <td>'.$r['prefix'].'</td> <td>'.$r['prefix_name'].'</td> <td>'.$r['rateinitial'].'</td>
          <td>'.$r['trunk_group_name']."</td></tr>\n";
  if ($min_price > $r['rateinitial']) { $min_price = $r['rateinitial']; }
  if ($max_price < $r['rateinitial']) { $max_price = $r['rateinitial']; }
}

print "$out<Br/>Prices from MIN <B>$min_price</B> to MAX <B>$max_price</B> $tds\n</table>\n
</body></html>";

?>
