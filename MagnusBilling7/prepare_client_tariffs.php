<?php

include "config.php";

if ( $_SESSION['isAdmin'] != 1 ) { print '<a href="../" target="_blank">Only for admins</a>'; die; }

print "<html><head>
    <title>Magnus Client tariffs pre-processor</title>
<style>
   td { background-color: green; }
   .tdwarn { background-color: orange; }
   textarea { background-color: silver; readonly; }
   .file { background-color: cyan; margin: 10px; padding: 12px; }
</style>

</head>
<body>
<h2>Client tariffs pre-processor</h2>
<h3>Analyze uploaded CSV file for selected Client's Tariff Plan with uplink (trunk) group and prepare SQL-scripts for upload Client's Rates (end-user tariffs) in Magnus Billing 7</h3>";

$mprefs = array();   // Existing prefix-list array
$mprovt = array();   // Existing provider-prices array
$nprefs = '';   // New prefix-list SQL (candidates)

$aprice = '';   // New prefix-price SQL (add prices)
$uprice = '';   // New prefix-price SQL (refresh prices)
$dprice = '';   // Outdated prefix-price SQL (delete prices)

$exxprefs = '';   // Existing prefix-list (Already existing prefix names, only price need check)
$errprefs = '';   // Error prefix-list (cannot parsing)

$exxrates = '';   // Existing prefix-rate (Already existing prefix rate with same cost)
$errrates = '';   // Error prefix-rate (cannot parsing)

if ( isset($_POST['hid1']) ) {

     $delimiter = isset($_POST['delimiter']) ? $_POST['delimiter'] : ',';
     $clienttariff  = isset($_POST['clienttariff']) ? (int)$_POST['clienttariff'] : 0;
     $trunkgroup    = isset($_POST['trunkgroup']) ? (int)$_POST['trunkgroup'] : 0;

     $mprefsql = mysqli_query($db, "SELECT id, prefix, destination FROM pkg_prefix");
     while ( $mprefsrow = mysqli_fetch_array($mprefsql)) {
           $cprefix = $mprefsrow['prefix'];
           $mprefs[$cprefix]['prefix_id']   = $mprefsrow['id'];
           $mprefs[$cprefix]['description'] = $mprefsrow['destination'];
     }
# print_r($mprefs);

     $provtsql = mysqli_query($db, "SELECT   rp.id, rp.id_prefix, rp.rateinitial, p.prefix, p.destination
                 FROM `pkg_rate` rp
                 LEFT JOIN `pkg_prefix` p ON p.id = rp.id_prefix
                 WHERE rp.id_plan = '$clienttariff' ");
     if ( mysqli_num_rows($provtsql) > 0 ) {
        while ( $provtrow = mysqli_fetch_array($provtsql)) {
           $cprefix = $provtrow['prefix'];
           $mprovt[$cprefix]['prefix_id']   = $provtrow['id_prefix'];
           $mprovt[$cprefix]['cost']   = $provtrow['rateinitial'];
           $mprovt[$cprefix]['description'] = $provtrow['destination'];
           $mprovt[$cprefix]['rate_prov_id']   = $provtrow['id'];
        }
      // print_r($mprovt);
     }

     if ( isset($_FILES['csv_file']['error']) and $_FILES['csv_file']['error'] != 0 ) { print "Error ".$_FILES['csv_file']['error']."<Br/>\n"; die; };

     if ($tmpfile = fopen($_FILES['csv_file']['tmp_name'], "r")) {
        $src_lines = ''; $n = 1; $new_prefs_count = $exx_prefs_count = $err_prefs_count = $add_rate_count = $upd_rate_count = $del_rate_count = $err_rate_count = $exx_rate_count = 0;
        while(!feof($tmpfile)) {
           $line = fgetcsv($tmpfile, null, $delimiter);
           $npref = isset($line[0]) ? $line[0] : '';
           $ndesc = isset($line[1]) ? addslashes($line[1]) : '';
           $ncost = isset($line[2]) ? floatval(str_replace(",", ".", $line[2])) : '';
           $src_lines .= "$n $npref $ndesc $ncost\n";

           // Generate new prefixes INSERTs
           if ( !isset($mprefs[$npref]['prefix_id']) or $mprefs[$npref]['prefix_id'] == '' ) {
                 if ( is_numeric($npref) and !is_numeric($ndesc) ) {
                    $new_prefs_count++;
                    if ( strlen($npref) <= 5 and $npref > 10) {
                         $nprefs .= "INSERT INTO `pkg_prefix` (`id`, `prefix`, `destination`) VALUES ('$npref', '$npref', '$ndesc');\n";
                       } else {
                         $nprefs .= "INSERT INTO `pkg_prefix` (`prefix`, `destination`) VALUES ('$npref', '$ndesc');\n";
                       };
                 } else {
                    $err_prefs_count++;
                    $errprefs .= "-- skip N ".str_pad($n, 7)." PREF $npref DESC $ndesc\n";
                 };
           } else { $exx_prefs_count++; $exxprefs .= "CSV: $n ".str_pad("$npref, $ndesc", 30)."     DB: ".$mprefs[$npref]['description'].", iD: ".$mprefs[$npref]['prefix_id']."\n"; };


           // Generate Provider prices INSERT/UPDATE
           if ( isset($mprefs[$npref]['prefix_id']) and $mprefs[$npref]['prefix_id'] != 0 ) {
             if ( $ncost == -1 ) { $active = 0; } else { $active = 1; };
             if ( isset($mprovt[$npref]['prefix_id']) ) {
                 if ( is_numeric($npref) and is_numeric($ncost) and $ncost != $mprovt[$npref]['cost'] ) {
                    $upd_rate_count++;
                    $uprice .= "UPDATE `pkg_rate` SET `rateinitial` = '$ncost', `status` = '$active' WHERE `id` = '".$mprovt[$npref]['rate_prov_id']."' AND `id_plan` = '$clienttariff' AND `id_prefix` = '".$mprovt[$npref]['prefix_id']."' AND `rateinitial` = '".$mprovt[$npref]['cost']."' LIMIT 1;  -- prefix $npref\n";
                 } else {
                    $exx_rate_count++;
                    $exxrates .= "CSV: $n $npref    DB: rate_iD: ".$mprovt[$npref]['rate_prov_id']."  pref_iD: ".$mprovt[$npref]['prefix_id']."  cost: $ncost vs ".$mprovt[$npref]['cost']."\n";
                 };
             } else { $add_rate_count++; $aprice .= "INSERT INTO pkg_rate (id_plan, id_trunk_group, id_prefix, rateinitial, initblock, billingblock, status) VALUES ('$clienttariff', '$trunkgroup', '".$mprefs[$npref]['prefix_id']."', '$ncost', 60, 60, $active);  -- prefix $npref\n"; };
           unset($mprovt[$npref]);
           } else {
                $err_rate_count++;
                $errrates .= "-- skip N ".str_pad($n, 7)." PREF $npref DESC $ndesc   PRICE $ncost\n";
           };


           $n++;
        }
        fclose($tmpfile);
     } else { print "Can't open tmp file"; };

     // Prepare outdated prices DELETEs
     // print_r($mprovt);
     foreach ( $mprovt as $cprefix => $delarr ) {
        $dprice .= "DELETE FROM `pkg_rate` WHERE `id` = '".$delarr['rate_prov_id']."' AND `id_plan` = '$clienttariff' AND `id_prefix` = '".$delarr['prefix_id']."' AND `rateinitial` = '".$delarr['cost']."' LIMIT 1; -- prefix $cprefix\n";
        $del_rate_count++;
     }


$trunksql = mysqli_query($db, "SELECT tgt.*, tg.name, tg.description, t.trunkcode, t.host, t.status
   FROM `pkg_trunk_group_trunk` tgt
   LEFT JOIN `pkg_trunk_group` tg ON tg.id = tgt.id_trunk_group
   LEFT JOIN `pkg_trunk` t ON t.id = tgt.id_trunk
WHERE tgt.id_trunk_group = '$trunkgroup'");
$trunks = '';
while ( $trrow = mysqli_fetch_array($trunksql) ) { $trunks .= $trrow['trunkcode']." "; }

     print '<table>';
     print "<tr><td> <div class=\"file\">-- Uploaded --<Br/>File:  <B>".$_FILES['csv_file']['name']."</B><Br/>Type: <B>".$_FILES['csv_file']['type']."</B> [ $delimiter ]<Br/>Size: <B>".$_FILES['csv_file']['size']."</B> bytes;</div>
                     <div class=\"file\">-- Client Tariff --<Br/>iD:  <B>$clienttariff</B><Br/>Name: <B>".get_name_client_tariff($clienttariff)."</B><Br/>Trunks: <small>$trunks</small></div>
                </td>";
     print "<td> <pre> -- Existing-prefixes [already in pkg_prefix] ($exx_prefs_count):<Br/><textarea cols=\"60\" rows=\"15\">$exxprefs</textarea></pre> </td>";
     print "<td class=\"tdwarn\"> <pre> -- Error-prefixes [Bad data for pkg_prefix] ($err_prefs_count):<Br/><textarea cols=\"60\" rows=\"15\">$errprefs</textarea></pre> </td></tr>";
     print "<tr><td> <pre> SRC Lines [Uploaded CSV-Data] (".($n-1)."):<Br/><textarea cols=\"60\" rows=\"15\">$src_lines</textarea></pre> </td>";
     print "<td> <pre> -- Existing-rates [prefix/provider/cost equal]  ($exx_rate_count):<Br/><textarea cols=\"60\" rows=\"15\">$exxrates</textarea></pre> </td>";
     print "<td class=\"tdwarn\"> <pre> -- Skip rates [prefixes not found in pkg_prefix] ($err_rate_count):<Br/><textarea cols=\"60\" rows=\"15\">$errrates</textarea></pre> </td></tr>";
     print '</table>';


     print "<pre> -- SQL new-prefixes ($new_prefs_count):<Br/>$nprefs</pre>";
     print "<pre> -- SQL new-prices ($add_rate_count):<Br/>$aprice</pre>";
     print "<pre> -- SQL upd-prices ($upd_rate_count):<Br/>$uprice</pre>";
     print "<pre> -- SQL del-prices ($del_rate_count):<Br/>$dprice</pre>";
     print "<B>-- OK, FINISH.</B>";


} else {   // No POST, show CSV-upload form
   print '<form method="POST" enctype="multipart/form-data">
   Upload CSV:<Br/>
      Field order: '.create_select_csv_format('csv_format').'<Br/><Br/>
      <input type="hidden" name="MAX_FILE_SIZE" value="5128000" />
      delimiter: '.create_select_csv_delimiter('delimiter').' <Br/>
      CSV-File: <input type="file" name="csv_file" /><Br/><Br/>

      Client Tariff: '.create_select_client_tariff('clienttariff').'<Br/>
      Trunk group: '.create_select_trunk_group('trunkgroup').'<Br/>

      <input type="hidden" name="hid1" value="v1"> <Br/><Br/>
      <input type="submit" value="ANALyze">
   </form>';
};


?>
