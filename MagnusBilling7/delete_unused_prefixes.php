<?php

include "config.php";

if ( $_SESSION['isAdmin'] != 1 ) { print '<a href="../" target="_blank">Only for admins</a>'; die; }

print "<html><head>
    <title>Magnus Tarifer (1.1)</title>
<style>
   td { background-color: green; }
   .tdwarn { background-color: orange; }
   textarea { background-color: silver; readonly; }
   .file { background-color: cyan; margin: 10px; padding: 12px; }
</style>

</head>
<body>
<h2>DELETE UNUSED PREFIXES</h2>
<h3>Analyze database for search unused prefixes and prepare SQL-scripts for delete this from Magnus Billing 7</h3>";


if ( isset($_POST['hid1']) ) {

   $count = 0;
   $oprefs = $comments = '';

     $oprefsql = mysqli_query($db, "SELECT pr.id, pr.prefix, pr.destination FROM pkg_prefix pr
   WHERE
       pr.id NOT IN (SELECT id_prefix FROM `pkg_rate`)
   AND pr.id NOT IN (SELECT id_prefix FROM `pkg_rate_agent`)
   AND pr.id NOT IN (SELECT id_prefix FROM `pkg_rate_provider`)
   AND pr.id NOT IN (SELECT id_prefix FROM `pkg_user_rate`)
   AND pr.id NOT IN (SELECT id_prefix FROM `pkg_balance`)
   AND pr.id NOT IN (SELECT id_prefix FROM `pkg_cdr`)
   AND pr.id NOT IN (SELECT id_prefix FROM `pkg_cdr_archive`)
   AND pr.id NOT IN (SELECT id_prefix FROM `pkg_cdr_failed`)
   ORDER BY pr.prefix");
     while ( $oprefsrow = mysqli_fetch_array($oprefsql)) {
             $count++;
             $comments .= str_pad($oprefsrow['prefix'].' '.$oprefsrow['destination'], 50).'   iD: '.$oprefsrow['id']."\n";
             $oprefs .= "DELETE FROM pkg_prefixes WHERE `id`='".$oprefsrow['id']."' LIMIT 1;\n";
     }
# print_r($mprefs);

     print "<textarea class=\"tdwarn\" cols=\"80\" rows=\"15\">$comments</textarea>";
     print "<pre> -- SQL UNUSED prefixes ($count):<Br/>$oprefs</pre>";
     print "<B>-- OK, FINISH.</B>";


} else {   // No POST, show CSV-upload form
   print '<form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="hid1" value="v1"> <Br/><Br/>
      <input type="submit" value="Find">
   </form>';
};


?>