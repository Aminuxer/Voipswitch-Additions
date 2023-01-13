<?php

include "config.php";

if ( $_SESSION['isAdmin'] != 1 ) { print '<a href="../" target="_blank">Only for admins</a>'; die; }

print "<html><head>
    <title>Magnus Tarifer (1.1)</title>
<style>
   table { border-collapse: collapse; }
   thead, th { text-align: center; background-color: #bababa;  border: 1px solid #73766f; }
   td { border: 1px solid gray; }
   .tdalarm { background-color: red; }
   .tdwarn { background-color: orange; }
   .bgr { color: green; font-weight: bold; }
   .bor { color: orange; font-weight: bold; }
   .dis { text-decoration: line-through; color: maroon; text-decoration-color: red; }
   textarea { background-color: silver; readonly; }
   .file { background-color: cyan; margin: 10px; padding: 12px; }
</style>

</head>
<body>";

$mode = isset($_GET['mode']) ? $_GET['mode'] : '';

if ( $mode == 'getsubs') {
     $provider = (int)$_GET['provider'];
     $cltariff = (int)$_GET['cltariff'];
     $prefix = (int)$_GET['prefix'];

     print 'Uplink Tariff   Subprefixes <B>'.$prefix.'*</B>, covered by Client Tariff prefix <B>'.$prefix.'</B>';

     $cltsqlq = "SELECT rc2.id AS client_rate_id, rc2.rateinitial AS price_client, rc2.status, rc2.id_prefix, rc2.id_trunk_group,
                      pf1.prefix, pf1.destination, rp1.buyrate AS price_uplink, ptg.name AS trunkgroupname, ptg.description AS trunkgroupdescription
            FROM `pkg_rate` rc2
                 INNER JOIN `pkg_prefix` pf1 ON rc2.id_prefix = pf1.id
                 LEFT JOIN pkg_rate_provider rp1 ON rp1.id_prefix = rc2.id_prefix AND rp1.id_provider = '$provider'
                 LEFT JOIN pkg_trunk_group ptg ON ptg.id = rc2.id_trunk_group
            WHERE pf1.prefix = '$prefix' AND rc2.id_plan = '$cltariff' LIMIT 1";

     $subsqlq = "SELECT rp2.id, rp2.id_prefix, rp2.buyrate, pf2.prefix,  pf2.destination
        FROM `pkg_rate_provider` rp2  INNER JOIN pkg_prefix pf2 ON pf2.id = rp2.id_prefix
     WHERE rp2.id_provider = '$provider' AND pf2.prefix LIKE CONCAT('$prefix', '%') AND pf2.prefix != '$prefix'
      AND not exists
(SELECT pf3.prefix FROM  pkg_rate pr3       LEFT JOIN `pkg_prefix` pf3 ON pr3.id_prefix = pf3.id
WHERE  pr3.id_plan = '$cltariff' AND pf3.prefix LIKE CONCAT ('$prefix', '%') AND pf3.prefix != '$prefix'
AND pf2.prefix LIKE CONCAT(pf3.prefix, '%') )
ORDER BY pf2.prefix, pf2.destination";


    $cltsql = mysqli_query ($db, $cltsqlq);
    if   ( mysqli_num_rows($cltsql) == 0 ) { print "No data for this client tariff"; }
    else {
           print '<table>
 <tr> <th colspan="6">CLIENT Tariff <B>'.get_name_client_tariff($cltariff).' [iD '.$cltariff.']</B><Br/>Prefix <B>'.$prefix.'</B></th>
      <th colspan="2">Uplink<Br/><B>'.get_name_provider($provider).' [iD '.$provider.']</B></th> </tr>
 <tr> <th>Prefix</th> <th>Destination</th> <th>ON</th> <th>Client<Br/>Price</th> <th>iD<Br/>pkg_prefix</th>
      <th>iD<Br/>pkg_rate</th> <th>up-price</td> <th>Trunk group</td> </tr>';
           $rc = mysqli_fetch_assoc($cltsql);
           if ( $rc['status'] ==  0 ) { $onclass = "dis";} else { $onclass = ""; };
           if ( $rc['id_trunk_group'] > 1 ) { $tgclass = 'tdalarm'; } else { $tgclass = ''; };
           if ( $rc['price_uplink'] > $rc['price_client'] ) { $cluprate = "tdalarm";} else { $cluprate = ""; };
                   print '<tr class="'.$onclass.'">
          <td><font class="bgr">'.$rc['prefix'].'</font></td>
          <td>'.$rc['destination'].'</td>   <td>'.$rc['status'].'</td>
          <td class="bgr">'.$rc['price_client'].'</td>   <td>'.$rc['id_prefix'].'</td> <td>'.$rc['client_rate_id'].'</td>
          <td class="'.$cluprate.'">'.$rc['price_uplink'].'</td> <td class="'.$tgclass.'">'.$rc['trunkgroupname'].' ('.$rc['trunkgroupdescription'].')</td><tr>';
           print '</table>';
    };
print '<Br/>';

#    print "<pre>$subsqlq</pre>";
    $subsql = mysqli_query ($db, $subsqlq);

    if   ( mysqli_num_rows($subsql) == 0 ) { print "No more longer subprefixes data for this uplink"; }
    else {
           print '<table>
 <tr> <th colspan="6">UPLINK Subprefixes <B>'.$prefix.'*</B> in <B>'.get_name_provider($provider).' [iD '.$provider.']</B></th> </tr>
 <tr> <th>&nbsp;#&nbsp;</th> <th>Prefix</th> <th>Destination</th> <th>Uplink<Br/>Price</th> <th>iD<Br/>pkg_prefix</th> <th>iD<Br/>pkg_rate_provider</th> </tr>';
           $n = 0;
           while ( $r = mysqli_fetch_array($subsql) ) {
                   $n++;
                   if ( $r['buyrate'] > $rc['price_client'] ) { $prclass = "tdalarm"; }
               elseif ( $r['buyrate'] == $rc['price_client']) { $prclass = "tdwarn"; }
                                                         else { $prclass = ""; };
                   print '<tr> <td>'.$n.'</td>
          <td><font class="bgr">'.$prefix.'</font><font class="bor">'.substr($r['prefix'], strlen($prefix), 50).'</font></td>
          <td>'.$r['destination'].'</td>
          <td class="'.$prclass.'">'.$r['buyrate'].'</td>   <td>'.$r['id_prefix'].'</td> <td>'.$r['id'].'</td><tr>';
           }
           print '</table>';
    };
    die;
}


# Main code
print "<h2>Tariffs Comparer</h2>
<h3>Compare Client Tariffs versus Uplink (provider) tariff in Magnus Billing 7</h3>";

$mprefs = array();   // Existing prefix-list array
$uprices = '';       // Uplink's prices

if ( isset($_POST['hid1']) ) {

     $mprefsql = mysqli_query($db, "SELECT id, prefix, destination FROM pkg_prefix");
     while ( $mprefsrow = mysqli_fetch_array($mprefsql)) {
           $cprefix = $mprefsrow['prefix'];
           $mprefs[$cprefix]['prefix_id']   = $mprefsrow['id'];
           $mprefs[$cprefix]['description'] = $mprefsrow['destination'];
     }
     // # print_r($mprefs);
     $cltariff = (int)$_POST['clienttariff'];
     $provider = (int)$_POST['provider'];

     $sql = "SELECT
     prr.id AS rate_id, prr.id_plan, prr.id_trunk_group, prr.id_prefix, prr.rateinitial AS price_client, prr.status,
     pf.prefix, pf.destination, tgp.name AS trunk_group_name, rpv.buyrate AS price_uplink, prr.id_trunk_group, tgp.name AS trunk_group_name,

     (  SELECT COUNT(rp2.id)    FROM `pkg_rate_provider` rp2  INNER JOIN pkg_prefix pf2 ON pf2.id = rp2.id_prefix
     WHERE rp2.id_provider = '$provider' AND pf2.prefix LIKE CONCAT(pf.prefix, '%') AND pf2.prefix != pf.prefix
      AND not exists
(SELECT pf3.prefix FROM  pkg_rate pr3       LEFT JOIN `pkg_prefix` pf3 ON pr3.id_prefix = pf3.id
WHERE  pr3.id_plan = '$cltariff' AND pf3.prefix LIKE CONCAT (pf.prefix, '%') AND pf3.prefix != pf.prefix
AND pf2.prefix LIKE CONCAT ( pf3.prefix, '%') )
     ) AS uplink_count_subs,

     (  SELECT MIN(rp2.buyrate)    FROM `pkg_rate_provider` rp2  INNER JOIN pkg_prefix pf2 ON pf2.id = rp2.id_prefix
     WHERE rp2.id_provider = '$provider' AND pf2.prefix LIKE CONCAT(pf.prefix, '%') AND pf2.prefix != pf.prefix
      AND not exists
(SELECT pf3.prefix FROM  pkg_rate pr3       LEFT JOIN `pkg_prefix` pf3 ON pr3.id_prefix = pf3.id
WHERE  pr3.id_plan = '$cltariff' AND pf3.prefix LIKE CONCAT (pf.prefix, '%') AND pf3.prefix != pf.prefix
AND pf2.prefix LIKE CONCAT ( pf3.prefix, '%') )

     ) AS uplink_price_min,

     (SELECT MAX(rp2.buyrate)     FROM `pkg_rate_provider` rp2   INNER JOIN pkg_prefix pf2 ON pf2.id = rp2.id_prefix
     WHERE rp2.id_provider = '$provider' AND pf2.prefix LIKE CONCAT(pf.prefix, '%') AND pf2.prefix != pf.prefix
AND not exists
(SELECT pf3.prefix FROM  pkg_rate pr3
       LEFT JOIN `pkg_prefix` pf3 ON pr3.id_prefix = pf3.id
WHERE  pr3.id_plan = '$cltariff' AND pf3.prefix LIKE CONCAT (pf.prefix, '%') AND pf3.prefix != pf.prefix
AND pf2.prefix LIKE CONCAT ( pf3.prefix, '%'))
     ) AS uplink_price_max,

     (  SELECT MIN(LENGTH(pf2.prefix))    FROM `pkg_rate_provider` rp2  INNER JOIN pkg_prefix pf2 ON pf2.id = rp2.id_prefix
     WHERE rp2.id_provider = '$provider' AND pf2.prefix LIKE CONCAT(pf.prefix, '%') AND pf2.prefix != pf.prefix
      AND not exists
(SELECT pf3.prefix FROM  pkg_rate pr3       LEFT JOIN `pkg_prefix` pf3 ON pr3.id_prefix = pf3.id
WHERE  pr3.id_plan = '$cltariff' AND pf3.prefix LIKE CONCAT (pf.prefix, '%') AND pf3.prefix != pf.prefix
AND pf2.prefix LIKE CONCAT ( pf3.prefix, '%') )
     ) AS uplink_len_min,

     (  SELECT MAX(LENGTH(pf2.prefix))    FROM `pkg_rate_provider` rp2  INNER JOIN pkg_prefix pf2 ON pf2.id = rp2.id_prefix
     WHERE rp2.id_provider = '$provider' AND pf2.prefix LIKE CONCAT(pf.prefix, '%') AND pf2.prefix != pf.prefix
      AND not exists
(SELECT pf3.prefix FROM  pkg_rate pr3       LEFT JOIN `pkg_prefix` pf3 ON pr3.id_prefix = pf3.id
WHERE  pr3.id_plan = '$cltariff' AND pf3.prefix LIKE CONCAT (pf.prefix, '%') AND pf3.prefix != pf.prefix
AND pf2.prefix LIKE CONCAT ( pf3.prefix, '%') )
     ) AS uplink_len_max

FROM `pkg_rate` prr
  LEFT JOIN pkg_prefix pf ON pf.id = prr.id_prefix
  LEFT JOIN pkg_trunk_group tgp ON tgp.id = prr.id_trunk_group
  LEFT JOIN pkg_rate_provider rpv ON rpv.id_prefix = prr.id_prefix AND rpv.id_provider = '$provider'
WHERE prr.id_plan = '$cltariff'
GROUP BY prr.id_prefix, prr.id_trunk_group
ORDER BY pf.prefix ASC, pf.destination ASC";

# print "<pre>$sql</pre>";
     $trfsql = mysqli_query($db, $sql);

     print 'Client Tariff <B>'.get_name_client_tariff($cltariff).' [iD '.$cltariff.']</B> vs Uplink Tariff <B>'.get_name_provider($provider).' [iD '.$provider.']</B>
<table>
<tr> <th rowspan="2">Prefix</th> <th rowspan="2">Dest</th> <th colspan="2">Direct price</th> <th rowspan="2">ON</th> <th colspan="6">Uplink sub-stats</th> </tr>
<tr>  <th>Client</th> <th>Uplink</th> <th>subs</th> <th>min. price</th> <th>max. price</th> <th>min. len</th> <th>max. len</th> <th>Trunk group</th> </tr>';
     while ( $trfr = mysqli_fetch_array($trfsql) ) {
        if ( $trfr['status'] == 0 ) { $trclass = "dis"; } else { $trclass = ""; }
        if ( $trfr['id_trunk_group'] > 1 ) { $trclass='tdalarm'; } else { $trclass = ''; };
        if ( ( $trfr['price_client'] < $trfr['price_uplink']
            or ($trfr['price_client'] < $trfr['uplink_price_min'] and $trfr['uplink_price_min'] > 0)
             ) and  $trfr['status'] != 0) { $pcclass = "tdalarm"; }
           elseif ( $trfr['price_client'] < $trfr['uplink_price_max'] ) { $pcclass = "tdwarn"; }
             else { $pcclass = ""; }

        print '<tr class="'.$trclass.'"> <td>'.$trfr['prefix'].'</td>
    <td>'.$trfr['destination'].'</td>
    <td class="'.$pcclass.'">'.$trfr['price_client'].'</td>
    <td>'.$trfr['price_uplink'].'</td>
    <td>'.$trfr['status'].'</td>
    <td> <a href="?mode=getsubs&provider='.$provider.'&cltariff='.$cltariff.'&prefix='.$trfr['prefix'].'" target="_blank">'.$trfr['uplink_count_subs'].'</a> </td>
    <td>'.$trfr['uplink_price_min'].'</td>
    <td>'.$trfr['uplink_price_max'].'</td>
    <td>'.$trfr['uplink_len_min'].'</td>
    <td>'.$trfr['uplink_len_max'].'</td>
    <td>'.$trfr['trunk_group_name'].'</td>
 </tr>'."\n";
     }
     print '</table>';
} else {   // No POST, show form
   print '<form method="POST" enctype="multipart/form-data">
      Client Tariff: '.create_select_client_tariff('clienttariff').'<Br/>
      Provider: '.create_select_provider('provider').'<Br/>
      <input type="hidden" name="hid1" value="v1"> <Br/><Br/>
      <input type="submit" value="Compare">
   </form>';
};


?>