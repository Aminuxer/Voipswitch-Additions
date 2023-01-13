<?php

include "config.php";

if ( isset($_POST['hid1']) ) {

  if ( $_SESSION['isAdmin'] == 1 ) {
      $client_id = isset($_POST['f_client']) ? (int)$_POST['f_client'] : '';
  } else { $client_id = (int)$_SESSION['id_user']; };
  $year = isset($_POST['f_year'])   ? (int)$_POST['f_year']  : '';
  $month = isset($_POST['f_month']) ? (int)$_POST['f_month'] : '';

  if ( $month == 12 ) { $year2 = $year + 1; $month2 = '01'; } else { $year2 = $year; $month2 = $month + 1; }

     $clntsql = mysqli_query($db, "SELECT
        u.id, u.username, u.credit, u.active, u.firstname, u.lastname, u.address, u.email, u.company_name, t.name AS plan_name, u.id_group
        FROM pkg_user u   LEFT JOIN pkg_plan t ON t.id = u.id_plan
     WHERE u.id_group NOT IN (1, 5) AND u.id = '$client_id' LIMIT 1");
     $clnt = mysqli_fetch_assoc($clntsql);

    $username = '';
    if ( $clnt['id_group'] == 4 ) {
         $username = 'PBX_';
         if ( $clnt['company_name'] != '' AND $clnt['company_name'] != $clnt['username'] ) { $username .= '_'.$clnt['company_name']; }
   } elseif ( $clnt['firstname'] != '' OR $clnt['lastname'] != '' ) { $username .= $clnt['firstname'].' '.$clnt['lastname']; };
   $username = preg_replace('/[\s\'\/]+/', '-', $username);
   $username = preg_replace('/[\'\/"\\\\]+/', '', $username);

    $cdrsql = mysqli_query($db, "SELECT
    c.starttime, c.src, c.callerid, c.calledstation, c.sessionbill, c.sessiontime, c.real_sessiontime,
    t.name AS plan_name, p.prefix, p.destination
FROM `pkg_cdr` c
   LEFT JOIN pkg_plan t ON t.id = c.id_plan
   LEFT JOIN pkg_prefix p ON p.id = c.id_prefix
WHERE c.id_user = '".$clnt['id']."' AND c.starttime >= '$year-$month-01 00:00:00' AND c.starttime < '$year2-$month2-01 00:00:00'
   ORDER BY c.starttime ASC, c.calledstation");

if ( $_POST['f_type'] == 'display') {
     header("Content-type: text/plain");
  } else {
     header("Content-type: text/csv");
     header("Content-Disposition: attachment; filename=".$clnt['username']."__$year-$month--$year2-$month2.csv");
  };

$count = $sum_money = $sum_seconds = $sum_seconds_real = 0;
     print "N, Starting-Date-Time, SIP-login, Caller-iD, Dialed-Number, Price, Accounted-Seconds, Real-Seconds, Prefix, Prefix-description, Tariff\n";
     while ( $r = mysqli_fetch_array($cdrsql)) {
             $count++;
             $sum_money += $r['sessionbill'];
             $sum_seconds += $r['sessiontime'];
             $sum_seconds_real += $r['real_sessiontime'];
             print "$count, ".$r['starttime'].", \"".$r['src']."\", \"".$r['callerid']."\", \"".$r['calledstation']."\", ".$r['sessionbill'].", ".$r['sessiontime'].", ".$r['real_sessiontime'].", \"".$r['prefix']."\", \"".$r['destination']."\", \"".$r['plan_name']."\"\n";
     }

     print '"SUM '.$count.'", ">= '.$year.'-'.$month.'-01", "U '.$clnt['username'].'",  "", "", "RUB '.$sum_money.'", "'. time_shorter($sum_seconds).'", "'. time_shorter($sum_seconds_real).'", "", "", ""'."\n"
     .'"", " < '.$year2.'-'.$month2.'-01", "'.$username.'", "", "", "", "", "", "", "", "CURRENT: '.$clnt['plan_name'].'"'."\n";


} else {   // No POST, show dialog form
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
<h2>Accounting CDR</h2>
<h3>Export accounting end-user CDR from Magnus Billing 7</h3>";


if ( $_SESSION['isAdmin'] == 1 ) {
         $clselect = create_select_client ("f_client");
} else { $clselect = '<INPUT type="text" readonly value="'.$_SESSION['username'].'" size="6">
                      <INPUT type="hidden" name="f_client" value="'.$_SESSION['id_user'].'">';
};

   print '<form method="POST" enctype="multipart/form-data">
       <input type="text" name="f_year" value="'.date("Y").'" maxlength="6" size="4" pattern="[1-9]{1}[0-9]{3,}">-
      <input type="text" name="f_month" value="'.date("m").'" maxlength="2" size="3" pattern="[0]?[1-9]{1}|[1]?[0-2]{1}"> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; '.$clselect.'<Br/>
         <label> <input type="radio" name="f_type" value="display" checked> Display</label><Br/>
         <label> <input type="radio" name="f_type" value="save"> Save CSV</label><Br/>
      <input type="hidden" name="hid1" value="v1"> <Br/>
      <input type="submit" value="Export CDR">
   </form>';
};


?>
