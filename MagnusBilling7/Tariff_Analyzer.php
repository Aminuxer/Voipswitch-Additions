<?php

require("config_vs.php");
$title = 'Анализатор тарифов (beta)';
$ref_tariff = isset($_GET['ref_tariff']) ? (int) $_GET['ref_tariff'] : 18;

$clients_tariffs_sql = mysqli_query($DB, "SELECT DISTINCT cs.id_tariff, tn.description AS tariff_name FROM clientsshared cs
   LEFT JOIN tariffsnames tn ON tn.id_tariff = cs.id_tariff
WHERE cs.id_tariff > 0") or print mysqli_error($DB);

$uplink_tariffs_sql = mysqli_query($DB, "SELECT g.id_route, g.description AS gate_name, g.ip_number, tn.id_tariff, tn.description AS tariff_name,
  (SELECT COUNT(*) FROM tariffs t WHERE t.id_tariff = g.id_tariff) AS cnt_prefixes
FROM gateways g
    LEFT JOIN tariffsnames tn ON tn.id_tariff = g.id_tariff") or print mysqli_error($DB);


$uplinks_tariff_prefixes = mysqli_query($DB, "SELECT t.id_tariff, t.prefix, t.description AS prefix_name, t.voice_rate
  FROM tariffs t
  -- WHERE prefix LIKE '54%' or prefix LIKE '81054%'  -- prefix filter
  ORDER BY t.id_tariff, t.description, t.prefix");


print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>';
    include("style.css");
print '</head>
<body>'.
"\n<h1>$title</h1>\n

Этот отчет позволяет сравнить цены по направлениям.<Br/><Br/>

Сперва выбирается список тарифов, назначенных клиентам, и выводится блоком ссылок в заголовке.
За основу берется один из клиентских тарифов, по умолчанию выбран базовый тариф (iD = $ref_tariff),
этот тариф называется референсным.
Каждый префикс из референсного тарифа - одна строчка отчета. Цена, снимаемая с клиента считается
референсной, выводится в третьем столбце.
В правой части таблицы в заголовок вынесены все тарифы, прицепленные
к аплинкам (Destination-Gateways), рядом у знака суммы написано число префиксов, найденных в тарифе данного аплинка.<Br/>
Например, такой заголовок: OBIT ∑<sup>2018</sup> сообщает, что в тарифе Обита 2018 префиксов совокупно.<Br/><Br/>

На пересечении строки префикса и столбца аплинка находятся цены из тарифов аплинка.<Br/>
Цена, выводимая до знака суммы, относится только к случаю точного совпадения префиксов 1-к-1.
Если она черная - значит она меньше референсной, звонок не дожен быть убыточен.
Если она красная - значит она больше референсной, звонок может быть убыточен.
Если цифр до знака суммы нет, то значит точных совпадений префиксов не найдено.

Знак суммы с цифрой в верхнем регистре обозначает, что для данного референсного префикса (из выбранного клиентского тарифа) есть
префиксы большей длины. Например, запись ∑<sup>8</sup> обозначает, что для данного префикса в клиентском тарифе есть 8 суб-префиксов
в тарифе данного аплинка.
Цифры после знака суммы показывают диапазон цен по набору суб-префиксов. Если все цены на суб-префиксы одинаковы, выводится только одно число.
Если цена после знака суммы синяя - значит все цены из набора меньше референсной, звонок не в убыток.
Если цена после знака суммы оранжевая - значит хотя бы одна цена из набора выше референсной, и есть риск звонков в убыток.<Br/><Br/>

Важные заметки:<Br/>
- Отчет не анализирует диал-план, LCR, Do-Not-Jump и прочие правила маршрутизации звонков. Производится
просто прямое сопоставление префиксов клиентов с префиксами аплинков в разрезе цен.<Br/>
- Ряд тарифов не содержат кода выхода не межгород 810, поэтому цены после знака суммы могут захватывать избыточные данные,
поскольку поиск суб-префиксов идет как по значению с кодом выхода, так и без этого кода.<Br/>
- Короткие префиксы клиентских тарифов (1-2 цифры) включают в себя огромное число суб-префиксов.<Br/>
- Если в клиентском тарифе есть группа префиксов с общим началом (например, 810355. 8103556, 8103557), то суб-префиксы аплинков
будут корректно распределены по ячейкам.<Br/>
- Из-за мешанины в тарифах с кодом выхода 810 некоторые префиксы, содержащие 810 в начале (например, 6 и 8106) могут попасть в обе строки отчета.<Br/>
- Если референсная цена - красная -1, значит на данном клиентском тарифе направление выключено.

<Br/><Br/>

";

$n = 0;
while ($r = mysqli_fetch_array($uplink_tariffs_sql)) {
  $gate_id = $r['id_route'];
  $uplinks_tds .= '<th><a title="iD='.$r['id_tariff'].'; '.$r['tariff_name'].' (IP: '.$r['ip_number'].'); Префиксов: '.$r['cnt_prefixes'].'">'.$r['gate_name']." ∑<sup>".$r['cnt_prefixes']."</sup></a></th>\n";
  $uplinks[$n]['name'] = $r['gate_name'];
  $uplinks[$n]['ip'] = $r['ip_number'];
  $uplinks[$n]['id'] = $gate_id;
  $uplinks[$n]['tariff_id'] = $r['id_tariff'];
  $uplinks[$n]['tariff_name'] = $r['tariff_name'];
  $uplinks[$n]['cnt_prefixes'] = $r['cnt_prefixes'];
  $n++;
}

print "Основной референсный тариф:\n";
while ($r = mysqli_fetch_array($clients_tariffs_sql)) {
  $tariff_id = $r['id_tariff'];
  print ' [<a href="?ref_tariff='.$tariff_id.'">'.$r['tariff_name'].'</a>] ';
  $tariffs[$tariff_id]['id'] = $tariff_id;
  $tariffs[$tariff_id]['name'] = $r['tariff_name'];
}

# print '<pre>';
while ($r = mysqli_fetch_array($uplinks_tariff_prefixes)) {
  $tariff_id = $r['id_tariff'];
  $prefix = $r['prefix'];
#  print "$tariff_id $prefix \n";
  $tariffs_prefs[$tariff_id][$prefix]['name'] = $r['prefix_name'];
  $tariffs_prefs[$tariff_id][$prefix]['price'] = $r['voice_rate'];
}
# print_r($tariffs_prefs['9']); print '</pre>';


$num_uplinks = mysqli_num_rows($uplink_tariffs_sql);
$ref_pref_array = $tariffs_prefs[$ref_tariff];

print "<table>\n
<tr> <th rowspan=\"2\">Префикс</th> <th rowspan=\"2\">Направление <Br/> (".$tariffs[$ref_tariff]['name'].")<Br/>∑<sup>".count($ref_pref_array)."</sup></th> <th rowspan=\"2\">Реф. Цена</th>
     <th colspan=\"$num_uplinks\">Цены аплинков</th>
     </tr>
<tr>$uplinks_tds</tr>\n"; 



while (list($ref_prefix, $ref_pref_arr) = each($ref_pref_array)) {
  $ref_price = $ref_pref_arr['price'];
  $ref_name = $ref_pref_arr['name'];
    if ($ref_price == 0) { $voicerate_class = ' class="green"'; }
    elseif ($ref_price < 0) { $voicerate_class = ' class="red"'; }
    else { $voicerate_class = ''; };
  print '<tr> <td>'.$ref_prefix.'</td>
  <td>'.$ref_name.'</td>
  <td'.$voicerate_class.'>'.$ref_price.'</td>';

  for ($n = 0; $n < $num_uplinks; $n++) {
     $uplink_tariff = $uplinks[$n]['tariff_id'];
     $price = ''; $price2 = '';
     $price = $tariffs_prefs[$uplink_tariff][$ref_prefix]['price'];
       if ($ref_price < 0) { $voicerate_class = ' class="gray"'; }
       elseif ($price >= $ref_price) { $voicerate_class = ' class="red"'; }
       else { $voicerate_class = ''; }

       /* START DEEP ANALyze */
           $ref_prefix_len = strlen($ref_prefix);
           $ref_prefix_short = str_replace('810', '', $ref_prefix);
           $ref_prefix_short_len = strlen($ref_prefix_short);

           /* Start search more selective prefixes for sql-exceptions */
           $sql_deep_exceptions = '';
           $deep_subprefixes_array = $tariffs_prefs[$ref_tariff];
           # print "<pre>\n==============\n"; print_r($deep_subprefixes_array); print "\n-----------\n</pre>";
           while (list($subpref, $subpref_arr) = each($deep_subprefixes_array)) {
              # print "--$uplink_tariff-> $subpref, $ref_prefix, $ref_prefix_short<Br/>";
              if (substr($subpref, 0, $ref_prefix_len) == $ref_prefix and strlen($subpref) > $ref_prefix_len) {
                # print "--$uplink_tariff-> $subpref, $ref_prefix, $ref_prefix_short : ".$subpref_arr['price']."<Br/>";
                $sql_deep_exceptions .= "AND `prefix` NOT LIKE '$subpref%' AND `prefix` NOT LIKE '".str_replace('810', '', $subpref)."%' ";
              }
           }
           $sql_deep_exceptions .= 'AND `prefix` != 0';
           # print "##$uplink_tariff# $sql_deep_exceptions <Br/>";
           
           /* End search more selective prefixes for sql-exceptions */

           $deep_anal_sqlq = "SELECT SUBSTR(prefix, 1, $ref_prefix_len) AS pref_part,
                       MIN(voice_rate) AS min_price, MAX(voice_rate) AS max_price, COUNT(prefix) AS prefixes_cnt
                       FROM tariffs WHERE id_tariff = $uplink_tariff AND prefix LIKE '$ref_prefix%' -- AND prefix != '$ref_prefix'
                       $sql_deep_exceptions
                       GROUP BY pref_part
                       UNION
                       SELECT SUBSTR(prefix, 1, $ref_prefix_short_len) AS pref_part,
                       MIN(voice_rate) AS min_price, MAX(voice_rate) AS max_price, COUNT(prefix) AS prefixes_cnt
                       FROM tariffs WHERE id_tariff = $uplink_tariff AND prefix LIKE '$ref_prefix_short%' -- AND prefix != '$ref_prefix_short'
                       $sql_deep_exceptions
                       GROUP BY pref_part
                       ";
           $deep_anal_sql = mysqli_query($DB, $deep_anal_sqlq);
           # $price2 .= '<pre><small>'.$deep_anal_sqlq;
           while ($rd1 = mysqli_fetch_array($deep_anal_sql)) {
                 $voicerate_class2 = ' class="blue"';
                 if ($rd1['min_price'] == $rd1['max_price']) { $price_out = $rd1['min_price']; } else
                    {  $price_out = $rd1['min_price'].'..'.$rd1['max_price']; };
                 if ($rd1['min_price'] > $ref_price or $rd1['max_price'] > $ref_price) { $voicerate_class2 = ' class="orange"'; };
                 if ($ref_price < 0) { $voicerate_class2 = ' class="gray"'; }
                 $price2 .= '<a'.$voicerate_class2.' title="Prefixes: '.$rd1['prefixes_cnt'].'">
                     <font class="green"> ∑<sup>'.$rd1['prefixes_cnt'].'</sup> </font>'.$price_out."</a> 
                     <a href=\"Tariff_detail_prefixes.php?ref_tariff=$uplink_tariff&ref_prefix=$ref_prefix\" target=\"_blank\">?</a>
                     <Br/>\n";
           }
       
      /* END DEEP ANAlyze */

      print " <td><font$voicerate_class>$price</font> $price2</td> ";
  }
  print "</tr>\n";
}

print "\n</table>\n
</body></html>";

?>