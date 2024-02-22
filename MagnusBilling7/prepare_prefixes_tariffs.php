<?php

include "config.php";

if ( $_SESSION['isAdmin'] != 1 ) { print '<a href="../" target="_blank">Only for admins</a>'; die; }
$only_sql = isset($_POST['only_sql']) ? $_POST['only_sql'] : '';
$hide_delete_queries = isset($_POST['hide_delete_queries']) ? $_POST['hide_delete_queries'] : '';

if ( $only_sql != 'on') {
print "<html><head>
    <title>Magnus Uplink tariffs pre-processor</title>
<style>
   td { background-color: green; }
   .tdwarn { background-color: orange; }
   textarea { background-color: silver; readonly; }
   .file { background-color: cyan; margin: 10px; padding: 12px; }
</style>

</head>
<body>
<h2>Prefixes and tariffs pre-processor</h2>
<h3>Analyze uploaded CSV file for selected provider and prepare SQL-scripts for upload Provider Rates (Uplink tariffs) in Magnus Billing 7</h3>";
};

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
     $csv_format = isset($_POST['delimiter']) ? $_POST['csv_format'] : ',';
     $provider  = isset($_POST['provider']) ? (int)$_POST['provider'] : 0;

     $mprefsql = mysqli_query($db, "SELECT id, prefix, destination FROM pkg_prefix");
     while ( $mprefsrow = mysqli_fetch_array($mprefsql)) {
           $cprefix = $mprefsrow['prefix'];
           $mprefs[$cprefix]['prefix_id']   = $mprefsrow['id'];
           $mprefs[$cprefix]['description'] = $mprefsrow['destination'];
     }
# print_r($mprefs);

     $provtsql = mysqli_query($db, "SELECT   rp.id, rp.id_prefix, rp.buyrate, p.prefix, p.destination
                 FROM `pkg_rate_provider` rp
                 LEFT JOIN `pkg_prefix` p ON p.id = rp.id_prefix
                 WHERE rp.id_provider = '$provider' ");
     if ( mysqli_num_rows($provtsql) > 0 ) {
        while ( $provtrow = mysqli_fetch_array($provtsql)) {
           $cprefix = $provtrow['prefix'];
           $mprovt[$cprefix]['prefix_id']   = $provtrow['id_prefix'];
           $mprovt[$cprefix]['cost']   = $provtrow['buyrate'];
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

           if ( $csv_format == 'pref_desc_price' ) {
                $npref = isset($line[0]) ? addslashes($line[0]) : '' ;
                $ndesc = isset($line[1]) ? addslashes($line[1]) : '' ;
                $ncost = isset($line[2]) ? floatval(str_replace(",", ".", $line[2])) : '' ;
                $src_lines .= "$n $npref $ndesc $ncost\n";
           } elseif ( $csv_format == 'desc_pref_price' ) {
                $npref = isset($line[1]) ? addslashes($line[1]) : '';
                $ndesc = isset($line[0]) ? addslashes($line[0]) : '';
                $ncost = isset($line[2]) ? floatval(str_replace(",", ".", $line[2])) : 0;
                $src_lines .= "$n $npref $ndesc $ncost\n";
           }

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
             if ( isset($mprovt[$npref]['prefix_id']) ) {
                 if ( is_numeric($npref) and is_numeric($ncost) and $ncost != $mprovt[$npref]['cost'] ) {
                    $upd_rate_count++;
                    $uprice .= "UPDATE `pkg_rate_provider` SET `buyrate` = '$ncost' WHERE `id` = '".$mprovt[$npref]['rate_prov_id']."' AND `id_provider` = '$provider' AND `id_prefix` = '".$mprovt[$npref]['prefix_id']."' AND `buyrate` = '".$mprovt[$npref]['cost']."' LIMIT 1;  -- prefix $npref\n";
                 } else {
                    $exx_rate_count++;
                    $exxrates .= "CSV: $n $npref    DB: rate_iD: ".$mprovt[$npref]['rate_prov_id']."  pref_iD: ".$mprovt[$npref]['prefix_id']."  cost: $ncost vs ".$mprovt[$npref]['cost']."\n";
                 };
             } else { $add_rate_count++; $aprice .= "INSERT INTO pkg_rate_provider (id_provider, id_prefix, buyrate, buyrateinitblock, buyrateincrement) VALUES ('$provider', '".$mprefs[$npref]['prefix_id']."', '$ncost', 60, 60);  -- prefix $npref\n"; };
           unset ($mprovt[$npref]);   // Parsed price-data removed from array
           } else {
                $err_rate_count++;
                $errrates .= "-- skip N ".str_pad($n, 7)." PREF $npref DESC $ndesc   PRICE $ncost\n";
           };
           $n++;
        };   // end while over lines in csv
        fclose($tmpfile);
     } else { print "Can't open tmp file"; };


     // Prepare outdated prices DELETEs
     // print_r($mprovt);
     if ( $hide_delete_queries != 'on' ) {
         foreach ( $mprovt as $cprefix => $delarr ) {
            $dprice .= "DELETE FROM `pkg_rate_provider` WHERE `id` = '".$delarr['rate_prov_id']."' AND `id_provider` = '$provider' AND `id_prefix` = '".$delarr['prefix_id']."' AND `buyrate` = '".$delarr['cost']."' LIMIT 1; -- prefix $cprefix\n";
            $del_rate_count++;
         }
     } else { $dprice .= "\n-- DELETE QUERIES HIDDEN --\n"; };

     if ( $only_sql != 'on') {
         print '<table>';
         print "<tr><td> <div class=\"file\">-- Uploaded --<Br/>File:  <B>".$_FILES['csv_file']['name']."</B><Br/>Type: <B>".$_FILES['csv_file']['type']."</B> [ $delimiter ]<Br/>Size: <B>".$_FILES['csv_file']['size']."</B> bytes;</div>
                           <div class=\"file\">-- Provider --<Br/>iD:  <B>$provider</B><Br/>Name: ".get_name_provider($provider)."<B></B></div>
                     </td>";
         print "<td> <pre> -- Existing-prefixes [already in pkg_prefix] ($exx_prefs_count):<Br/><textarea cols=\"60\" rows=\"15\">$exxprefs</textarea></pre> </td>";
         print "<td class=\"tdwarn\"> <pre> -- Error-prefixes [Bad data for pkg_prefix] ($err_prefs_count):<Br/><textarea cols=\"60\" rows=\"15\">$errprefs</textarea></pre> </td></tr>";
         print "<tr><td> <pre> SRC Lines [Uploaded CSV-Data] (".($n-1)."):<Br/><textarea cols=\"60\" rows=\"15\">$src_lines</textarea></pre> </td>";
         print "<td> <pre> -- Existing-rates [prefix/provider/cost equal]  ($exx_rate_count):<Br/><textarea cols=\"60\" rows=\"15\">$exxrates</textarea></pre> </td>";
         print "<td class=\"tdwarn\"> <pre> -- Skip rates [prefixes not found in pkg_prefix] ($err_rate_count):<Br/><textarea cols=\"60\" rows=\"15\">$errrates</textarea></pre> </td></tr>";
         print '</table>';
     } else { print "<pre>use mbilling;\nSET NAMES UTF8;\n\n</pre>"; };


     print "<pre> -- SQL new-prefixes ($new_prefs_count):<Br/>$nprefs</pre>";
     print "<pre> -- SQL new-prices ($add_rate_count):<Br/>$aprice</pre>";
     print "<pre> -- SQL upd-prices ($upd_rate_count):<Br/>$uprice</pre>";
     print "<pre> -- SQL del-prices ($del_rate_count):<Br/>$dprice</pre>";
     print "<pre>-- OK, FINISH.</pre>";


} else {   // No POST, show CSV-upload form
   print '<form method="POST" enctype="multipart/form-data">
   Upload CSV:<Br/>
      Field order: '.create_select_csv_format('csv_format').'<Br/><Br/>
      <input type="hidden" name="MAX_FILE_SIZE" value="5128000" />
      delimiter: '.create_select_csv_delimiter('delimiter').' <Br/>
      CSV-File: <input type="file" name="csv_file" /><Br/><Br/>

      Provider: '.create_select_provider('provider').'<Br/>
      <input type="hidden" name="hid1" value="v1"> <Br/><Br/>
      Hide analyze stat form (make only SQL): <input type="checkbox" name="only_sql" /><Br/><Br/>
      Hide Delete Queries: <input type="checkbox" name="hide_delete_queries" checked/><Br/><Br/>
      <input type="submit" value="ANALyze">
   </form>';
};


?>
