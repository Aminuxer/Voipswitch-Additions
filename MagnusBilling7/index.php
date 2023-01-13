<?php

   session_start();
   if ( isset ($_SESSION['isAdmin']) and $_SESSION['isAdmin'] == 1 ) { $adm = '<font color="red"> [ADMIN]</font>'; } else { $adm = ''; };
   if ( isset ($_SESSION['username']) ) { print '<h3>'.$_SESSION['username'].$adm.'</h3>'; } else { print '<a href="../" target="_blank">Login</a><Br/><Br/>'; };

if ( $_SESSION['isAdmin'] == 1 ) {
   print '
<a href="accounting_cdr.php">Accounting CDR</a> <Br><Br>

<a href="prepare_prefixes_tariffs.php">Tariffs/Prefixes UPLINK-DATA pre-processor</a> <Br>
<a href="prepare_client_tariffs.php">Tariffs/Prefixes CLIENT-DATA pre-processor</a> <Br>
<a href="rename_prefixes.php">Prefix mass-rename (sql-export for grep/edit)</a> <Br>
<a href="compare_tariffs.php">Compare Tariffs (Client tariff vs Uplink tariff)</a> <Br>
<a href="delete_unused_prefixes.php">Delete unused prefixes (sql-export)</a><Br>
<Br>
<a href="Frod_Percentage.php">Frod_Percentage by UPLINK tariffs</a><Br/>
<a href="Tariff_detail_prefixes.php">Tariff detail prefixes in UPLINKs</a><Br/>
<Br/>';

};

print '
<a href="free_DIDs.php">free_DIDs</a><Br>

<a href="Frod_Percentage_banned.php">Frod_Percentage_banned in CLIENT tariffs</a><Br/>
<a href="Tariff_detail_prefixes_client.php">Tariff detail CLIENT prefixes</a><Br/>

<pre>
Migration plan

1. Stop voipswitch application on windows
2. Export refills sql dump
3. export balances sql dump
4. Export CDR data for last month for buh and copy to any workstation/server
5. Disable port for old voipserver at switch, SAVE CONFIG. or old server poweroff

6. Import refills sql-dump
7. Import balances
8. Enable trunks in new server
9. Change IP on MagnusBilling to old one (substitute old IP), rename and restart VM
10. Make test calls:
   **DID -> mobile
   **DID -> YourCity, ExtCity
   mobile  -> **DID-retail
   mobile  -> **DID-pbx
   fixed   -> **DID
   **DID -> fixed
   **DID -> another department
11. Verify balances and CDR
';


?>
