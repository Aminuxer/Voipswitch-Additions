<?php

require("config.php");

if ( $_SESSION['isAdmin'] != 1 ) { print '<a href="../" target="_blank">Only for admins</a>'; die; }

$title = 'Детальная информация по префиксам в провайдерском тарифе';
$ref_tariff = isset($_GET['ref_tariff']) ? (int) $_GET['ref_tariff'] : 1;
$ref_prefix = isset($_GET['ref_prefix']) ? addslashes($_GET['ref_prefix']) : '';
$ref_country = isset($_GET['ref_country']) ? addslashes($_GET['ref_country']) : '';
$mode = isset($_GET['mode']) ? (int) $_GET['mode'] : 2;


$tariff_prefixes_sql = "SELECT p.prefix, p.destination AS prefix_name, t.buyrate, LENGTH(p.prefix) AS prefix_length
  FROM pkg_rate_provider t
   INNER JOIN pkg_prefix p ON p.id = t.id_prefix
  WHERE t.id_provider = '$ref_tariff' AND (p.prefix LIKE '$ref_prefix%') AND (p.destination LIKE '$ref_country%')
  ORDER BY p.destination, p.prefix";

$tariff_prefixes = mysqli_query($db, $tariff_prefixes_sql);
# print $tariff_prefixes_sql;

print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>
    <style>
   table { border-collapse: collapse; }
   thead, th { text-align: center; background-color: #bababa;  border: 1px solid #73766f; }
   td { border: 1px solid gray; }
</style>
</head>
<body>'.
"\n<h1>$title</h1>\n";

$out = 'Найдено <B>'.mysqli_num_rows($tariff_prefixes).'</B> префиксов провайдера <B>'.get_name_provider($ref_tariff)."</B> [iD: $ref_tariff] ";

print '<Br/><form method="get">Начало префикса: <input type="text" name="ref_prefix" value="'.$ref_prefix.'">
              Страна: <input type="text" name="ref_country" value="'.$ref_country.'">
              '.create_select_provider('ref_tariff').'
       <input type="submit" value="Search">
  </form> <Br/><Br/>';

$tds = '<Br/>';
  

$tds .= "<table>\n<tr> <th>Prefix</th> <th>Направление</th> <th>Цена</th> </tr>";

$min_price = 9999999; $max_price = 0;
while ($r = mysqli_fetch_array($tariff_prefixes)) {
  $tds .= '<tr> <td>'.$r['prefix'].'</td> <td>'.$r['prefix_name'].'</td><td>'.$r['buyrate']."</td>   </tr>\n";
  if ($min_price > $r['buyrate']) { $min_price = $r['buyrate']; }
  if ($max_price < $r['buyrate']) { $max_price = $r['buyrate']; }
}

print "$out<Br/>Prices from MIN <B>$min_price</B> to MAX <B>$max_price</B> $tds\n</table>\n
</body></html>";

?>
