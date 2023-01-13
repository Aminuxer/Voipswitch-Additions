<?php

require("config_vs.php");
$title = 'Префиксы, которые есть в Тариф-1, но нет в Тариф-2';
$ref_tariff1 = isset($_GET['ref_tariff1']) ? (int) $_GET['ref_tariff1'] : 39;
$ref_tariff2 = isset($_GET['ref_tariff2']) ? (int) $_GET['ref_tariff2'] : 18;

$ref_add810 = isset($_GET['ref_add810']) ? 1 : 0;

$clients_tariffs_sql = mysqli_query($DB, "SELECT DISTINCT t.id_tariff, tn.description AS tariff_name
FROM tariffs t
   LEFT JOIN tariffsnames tn ON tn.id_tariff = t.id_tariff
WHERE t.id_tariff > 0") or print mysqli_error($DB);

$tariff_prefixes_sql = "SELECT t1.prefix, t1.description AS prefix_name, t1.voice_rate
 FROM tariffs t1
   WHERE t1.id_tariff = $ref_tariff1
 AND t1.prefix NOT IN (SELECT t2.prefix FROM tariffs t2 WHERE t2.id_tariff = $ref_tariff2);";

$tariff_prefixes = mysqli_query($DB, $tariff_prefixes_sql);
# print $tariff_prefixes_sql;

print '<!DOCTYPE html>
<html><head>     <meta charset="utf-8">
    <title>'.$title.'</title>';
    include("style.css");
print '</head>
<body>'.
"\n<h1>$title</h1>\n


<Br/><table>
<tr> <th>Тариф1</th> <th>Тариф2</th> </tr>\n";
while ($r = mysqli_fetch_array($clients_tariffs_sql)) {
  $tariff_id = $r['id_tariff'];
    print '<tr> <td>';
    if  ($ref_tariff1 == $tariff_id) { print "<B>"; $tariff1_name = $r['tariff_name']; }
       print ' [<a href="?ref_tariff1='.$tariff_id.'&ref_tariff2='.$ref_tariff2.'&ref_add810='.$ref_add810.'">'.$r['tariff_name'].'</a>] ';
       if  ($ref_tariff1 == $tariff_id) { print "</B>"; }
    print '</td> <td>';
    if  ($ref_tariff2 == $tariff_id) { print "<B>"; $tariff2_name = $r['tariff_name']; }
       print ' [<a href="?ref_tariff1='.$ref_tariff1.'&ref_tariff2='.$tariff_id.'&ref_add810='.$ref_add810.'">'.$r['tariff_name'].'</a>] ';
       if  ($ref_tariff1 == $tariff_id) { print "</B>"; }
   print "</td></tr>\n";
}
print "</table>\n";

print '<a href="?ref_tariff1='.$ref_tariff1.'&ref_tariff2='.$ref_tariff2.'&ref_add810=1">Добавить 810</a>
&nbsp; &nbsp; &nbsp; &nbsp; &nbsp;';
print '<a href="?ref_tariff1='.$ref_tariff1.'&ref_tariff2='.$ref_tariff2.'">Снять 810</a>';




  if ($repl_cnt > 0) { print "В префиксе сделана замена `810` : <B>$ref_prefix</B> -> <B>$cut_refprefix</B> <Br/>"; }
print '<Br/> Найдено <B>'.mysqli_num_rows($tariff_prefixes).'</B> префиксов в тарифе <B>'.$tariff1_name.'</B> (iD = <B>'.$ref_tariff1.'</B>)
  <Br/> <Br/>';
  

print "<pre>\n\n";

if ($ref_add810 == 1) { $pre_prefix = '810'; } else { $pre_prefix = ''; };

while ($r = mysqli_fetch_array($tariff_prefixes)) {
  print $pre_prefix.$r['prefix'].',"'.$r['prefix_name'].'",'.$r['voice_rate']."\n";

}

print "\n</table>\n
</body></html>";

?>