<?php

include("config.php");

$title = 'Free DIDs';

$sql = mysqli_query($db, "SELECT  d.did, d.fixrate, d.connection_charge, country  FROM `pkg_did` d
   WHERE d.id NOT IN (SELECT id_did FROM `pkg_did_destination` dd WHERE dd.activated = 1) AND d.activated = 1 AND d.reserved = 0
   ORDER BY d.did");

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
"\n<h1>$title (".mysqli_num_rows($sql).")</h1>\n<table>\n
<tr> <th>DID Number</th> <th>Country</th> <th>Connection price</th> <th>Price per month</th> </tr>\n";

while ($r = mysqli_fetch_array($sql)) {
  print '<tr> <td>'.$r['did'].'</td>
     <td>'.$r['country'].'</td>
     <td>'.round($r['connection_charge']).'</td>
     <td>'.round($r['fixrate'])."</td>
     </tr>\n";

}

print "\n</table>\n
</body></html>";

?>
