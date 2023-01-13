<?php

require("config.php");
$title = 'Fraud prefix banned perсent in CLIENT Tariffs';

$ref_tariff = isset($_GET['ref_tariff']) ? (int) $_GET['ref_tariff'] : 2;


$monster_sql = "SELECT
  p1.destination, SUBSTR(p1.destination, 1, 5) AS country,
  MIN(p1.prefix) AS basic_prefix,
  MIN(LENGTH(p1.prefix)) AS basic_prefix_length,
  MAX(LENGTH(p1.prefix)) AS max_subprefix_length,
  MIN(t1.rateinitial) AS basic_voice_rate,
  MAX(t1.rateinitial) AS max_subprefix_price,
  COUNT(p1.prefix) as num_subprefixes,
  ROUND((MAX(t1.rateinitial) / t1.rateinitial ), 2) AS frod_local_ratio
FROM  `pkg_rate` t1
 INNER JOIN pkg_prefix p1 ON p1.id = t1.id_prefix
  WHERE t1.id_plan = '$ref_tariff'
GROUP BY country
-- HAVING frod_local_ratio > '$ref_excess' OR max_subprefix_price > '$ref_price'
ORDER BY country, p1.prefix, LENGTH(p1.prefix) ASC, LENGTH(p1.destination) ASC";

# print "<pre>$monster_sql</pre>";
$main_tariff_prefixes = mysqli_query($db, "$monster_sql") or print mysqli_error($DB);


print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>
    <style>
   table { border-collapse: collapse; }
   thead, th { text-align: center; background-color: #bababa;  border: 1px solid #73766f; }
   td { border: 1px solid gray; }

.orange {
    color:orange;
    font-weight:bold;
}

.bgred {
   background-color:red;
   font-weight:bold;
}

.bgorange {
   background-color:orange;
   font-weight:bold;
}
.bgyellow {
    background-color:yellow;
     font-weight:bold;
}

</style>
</head>
<body>'.
"\n<h1>$title</h1>\n

Этот отчет позволяет определить процент заблокированного адресного пространства в клиентсикх тарифах.<Br/><Br/>

<form method=\"GET\">
<table>
  <tr> <td>Исследуемый<Br/>тариф:</td> <td>".create_select_client_tariff("ref_tariff", $ref_tariff)."
       <INPUT type=\"submit\" value=\"Сформировать\"></td> </tr>
</table>
<form>

Каждый тариф анализируется независимо.

Для тарифа выгружается список стран и базовых префиксов, относящихся к стране в целом.
Считается разброс цен между базовой ценой префикса страны и самым дорогим субпрефиксом.
Если он меньше или равен <B>$ref_excess</B> - страна считается чистой, и в отчет не попадает.
Также не попадают в отчет страны, префиксы которых не дороже порога цены (<B>$ref_price р.</B>).
<Br/><Br/>

Затем ищутся базовые префиксы стран, и его цена берется за базовую для страны. Цена умножается на $ref_excess,
и эта цена является пороговой для страны. Если стоимость суб-префикса оказывается выше порога - то данный
под-диапазон считает мошенническим. Вычисляется отношение длин префиксов, и какой процент адресного пространства занимает данный фродовый префикс.
полученный процент добавляется в сумму.<Br/><Br/>



<Br/><Br/>";


if ( isset($_GET['ref_tariff']) ) {

print "Клиентский тариф: <B>".get_name_client_tariff($ref_tariff)."</B> [iD: $ref_tariff]<table>\n
<tr> <th>#</th> <th>Префикс</th> <th>Направление<Br/></th> <th>Базовая<Br/>цена</th> <th>Длина<Br/>базы</th> <th>Пик<Br/>длины</th>
     <th>Пик<Br/>цены</th> <th>суб-<Br/>префиксов</th>
     <th>Разброс<Br/>цен, n-раз</th> <th>%<Br/>бан</th>
     </tr>\n";



while ($r = mysqli_fetch_array($main_tariff_prefixes)) {
  $cut_refsubprefix = $r['basic_prefix'];
  $cut_refsubprefix_lenght = $r['basic_prefix_length'];

  $sub_prefixes_sql = mysqli_query($db, "SELECT
     p.prefix, p.destination AS prefix_name, t.rateinitial, LENGTH(p.prefix) AS prefix_length, t.id_trunk_group
  FROM pkg_rate t
   INNER JOIN pkg_prefix p ON p.id = t.id_prefix
  WHERE t.id_plan = '$ref_tariff' AND (p.prefix LIKE '$cut_refsubprefix%') AND p.destination LIKE '".$r['country']."%'
  ORDER BY p.destination, p.prefix") or  print mysqli_error($DB) ;
  
  $cancer_percent = 0; $dd = '';
  while ($r2 = mysqli_fetch_array($sub_prefixes_sql)) {
     // $dd .= "<Br/>-- $cut_refsubprefix -..- ".$r2['prefix']." 1. ".$r2['buyrate']." 2. ".$r['basic_voice_rate']." == CP $cancer_percent --;";
     if ( $r2['id_trunk_group'] > 1 ) {
        $cancer_percent += 10 ** ( $cut_refsubprefix_lenght - $r2['prefix_length'] );
        // $dd .= "<Br/>-- $cut_refsubprefix -..- ".$r2['prefix']." 1. $cut_refsubprefix_lenght 2. ".$r2['prefix_length']." 3. ".$r2['buyrate']." == CP $cancer_percent --;";
     }
  }

  if ( $r['basic_voice_rate'] > 30 ) { $bvr_cl = 'bgred'; } elseif ( $r['basic_voice_rate'] > 20 ) { $bvr_cl = 'bgorange'; } elseif ( $r['basic_voice_rate'] > 10 ) { $bvr_cl = 'bgyellow'; } else { $bvr_cl = ''; };

  $prlen_diff = $r['max_subprefix_length'] - $r['basic_prefix_length'];
  if ( $prlen_diff > 4 ) { $prld_cl = 'bgred'; } elseif ( $prlen_diff > 3 ) { $prld_cl = 'bgorange'; } elseif ( $prlen_diff > 2 ) { $prld_cl = 'bgyellow'; } else { $prld_cl = ''; };

  if ( $r['max_subprefix_price'] > 50 ) { $mspp_cl = 'bgred'; } elseif ( $r['max_subprefix_price'] > 30 ) { $mspp_cl = 'bgorange'; } elseif ( $r['max_subprefix_price'] > 15 ) { $mspp_cl = 'bgyellow'; } else { $mspp_cl = ''; };

  if ( $r['num_subprefixes'] > 50 ) { $nsp_cl = 'bgred'; } elseif ( $r['num_subprefixes'] > 30 ) { $nsp_cl = 'bgorange'; } elseif ( $r['num_subprefixes'] > 10 ) { $nsp_cl = 'bgyellow'; } else { $nsp_cl = ''; };

  if ( $r['frod_local_ratio'] > 20 ) { $flr_cl = 'bgred'; } elseif ( $r['frod_local_ratio'] > 10 ) { $flr_cl = 'bgorange'; } elseif ( $r['frod_local_ratio'] > 5 ) { $flr_cl = 'bgyellow'; } else { $flr_cl = ''; };

  $cancer_percent = round(100 * $cancer_percent, 4);
  if ( $cancer_percent > 30 ) { $cp_cl = 'bgred'; } elseif ( $cancer_percent > 20 ) { $cp_cl = 'bgorange'; } elseif ( $cancer_percent > 10 ) { $cp_cl = 'bgyellow'; } else { $cp_cl = ''; };
  $n++;
  print '<tr> <td>'.$n.'</td>
     <td><a href="Tariff_detail_prefixes_client.php?ref_tariff='.$ref_tariff.'&ref_prefix='.$cut_refsubprefix.'" target="_blank">'.$cut_refsubprefix.'</a></td>
     <td><a href="Tariff_detail_prefixes_client.php?ref_tariff='.$ref_tariff.'&ref_country='.$r['country'].'" target="_blank">'.$r['destination'].'</a></td>
     <td class="'.$bvr_cl.'">'.$r['basic_voice_rate'].'</td>
     <td>'.$r['basic_prefix_length'].'</td>
     <td class="'.$prld_cl.'">'.$r['max_subprefix_length'].'</td>
     <td class="'.$mspp_cl.'">'.$r['max_subprefix_price'].'</td>
     <td class="'.$nsp_cl.'">'.$r['num_subprefixes'].'</td>
     <td class="'.$flr_cl.'">'.$r['frod_local_ratio'].'</td>
     <td class="'.$cp_cl.'">'.$dd.$cancer_percent.' %</td>
     '."</tr>\n";

}


print "\n</table>\n";
}

print '</body></html>';

?>
