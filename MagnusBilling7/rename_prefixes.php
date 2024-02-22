<?php

include "config.php";

if ( $_SESSION['isAdmin'] != 1 ) { print '<a href="../" target="_blank">Only for admins</a>'; die; }

print "<html><head>
    <title>Magnus Prefix Renamer</title>
<style>
   td { background-color: green; }
   .tdwarn { background-color: orange; }
   textarea { background-color: silver; readonly; }
   .file { background-color: cyan; margin: 10px; padding: 12px; }
</style>

</head>
<body>
<h2>Prefixes renamer</h2>
<h3>Analyze uploaded CSV file and prepare SQL-scripts for mass-rename prefixes from uploaded file in Magnus Billing 7. If file omitted, scripts for rename ALL prefixed will be prepared.</h3>";

$mprefs = array();   // Existing prefix-list array
$nprefs = '';   // New prefix-list SQL (candidates)

$exxprefs = '';   // Existing prefix-list (Already existing prefix names, only price need check)
$errprefs = '';   // Error prefix-list (cannot parsing)


if ( isset($_POST['hid1']) ) {

     $delimiter = isset($_POST['delimiter']) ? $_POST['delimiter'] : ',';

     $mprefsql = mysqli_query($db, "SELECT id, prefix, destination FROM pkg_prefix");
     while ( $mprefsrow = mysqli_fetch_array($mprefsql)) {
           $cprefix = $mprefsrow['prefix'];
           $mprefs[$cprefix]['prefix_id']   = $mprefsrow['id'];
           $mprefs[$cprefix]['description'] = $mprefsrow['destination'];
     }
# print_r($mprefs);

     if ( isset($_FILES['csv_file']['error']) and $_FILES['csv_file']['error'] != 0 ) { print "-- Error ".$_FILES['csv_file']['error']." - file not uploaded<Br/>\n"; };

     if ($tmpfile = fopen($_FILES['csv_file']['tmp_name'], "r")) {
        $src_lines = ''; $n = 1; $new_prefs_count = $exx_prefs_count = $err_prefs_count = $add_rate_count = $upd_rate_count = $err_rate_count = $exx_rate_count = 0;
        while(!feof($tmpfile)) {
           $line = fgetcsv($tmpfile, null, $delimiter);
           $npref = isset($line[0]) ? $line[0] : '';
           $ndesc = isset($line[1]) ? $line[1] : '';
           $ncost = isset($line[2]) ? $line[2] : '';
           $src_lines .= "$n $npref $ndesc $ncost\n";

           // Generate new prefixes INSERTs
           $mp_np_prefid = isset ($mprefs[$npref]['prefix_id']) ? $mprefs[$npref]['prefix_id'] : '';
           if ( $mp_np_prefid != '' ) {
                 if ( is_numeric($npref) and !is_numeric($ndesc) ) {
                    $new_prefs_count++;
                    $nprefs .= "UPDATE `pkg_prefix` SET `destination` = '$ndesc' WHERE `prefix` = '$npref' LIMIT 1;\n";
                 } else {
                    $err_prefs_count++;
                    $errprefs .= "-- skip N ".str_pad($n, 7)." PREF $npref DESC $ndesc\n";
                 };
           } else { $exx_prefs_count++; $exxprefs .= "CSV: $n ".str_pad("$npref, $ndesc", 30)."\n"; };

        $n++;
        }
        fclose($tmpfile);
     } else {
       print "<pre> -- Generate all-prefix list for mass rename (".count($mprefs).") \n\n";
       foreach ( $mprefs as $prefix => $data ) {
              print "UPDATE `pkg_prefix` SET `destination` = '".$data["description"]."' WHERE `id` = '".$data['prefix_id']."' AND `prefix` = '$prefix' LIMIT 1;\n";
       };
     };

     if ( isset($_FILES['csv_file']['name']) and $_FILES['csv_file']['name'] != '' ) {
        print '<table>';
        print "<tr><td> <div class=\"file\">-- Uploaded --<Br/>File:  <B>".$_FILES['csv_file']['name']."</B><Br/>Type: <B>".$_FILES['csv_file']['type']."</B><Br/>Size: <B>".$_FILES['csv_file']['size']."</B> bytes;</div>
                   </td>";
        print "<td> <pre> -- Non-Existing-prefixes [NOT in pkg_prefix] ($exx_prefs_count):<Br/><textarea cols=\"60\" rows=\"15\">$exxprefs</textarea></pre> </td>";
        print "<td class=\"tdwarn\"> <pre> -- Error-prefixes [Bad data for pkg_prefix] ($err_prefs_count):<Br/><textarea cols=\"60\" rows=\"15\">$errprefs</textarea></pre> </td></tr>";
        print "<tr><td> <pre> SRC Lines [Uploaded CSV-Data] (".($n-1)."):<Br/><textarea cols=\"60\" rows=\"15\">$src_lines</textarea></pre> </td>";
        print '</table>';
     }


     print "<pre> -- SQL rename-prefixes ($new_prefs_count):<Br/>$nprefs</pre>";
     print "<B>-- OK, FINISH.</B>";


} else {   // No POST, show CSV-upload form
   print '<form method="POST" enctype="multipart/form-data">
   Upload CSV:<Br/>
      Field order: '.create_select_csv_format('csv_format').'<Br/><Br/>
      <input type="hidden" name="MAX_FILE_SIZE" value="5128000" />
      delimiter: '.create_select_csv_delimiter('delimiter').' <Br/>
      CSV-File: <input type="file" name="csv_file" /><Br/><Br/>

      <input type="hidden" name="hid1" value="v1"> <Br/><Br/>
      <input type="submit" value="ANALyze">
   </form>';
};


?>
