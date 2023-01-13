<?php

//   Script for prepare migrate main data (users, accounts, DIDs, CallerIDs, Payments Logs)
//   For migrate tariffs/prefixes use scripts prepare_prefixes_tariffs.php / prepare_client_tariffs.php and export function in voipswitch
//   ** from voipswitch (proprietary badass windows software from voipswitch.com )
//   ** to Magnus Billing 7 (opensource class 5 softswitch https://www.magnusbilling.org/ )
//   Script extract data from voipswitch mysql database and generate new SQL-dumps for MagnusBilling. Use grep for improve powerful.
//   Your can upload dumps step-by-step by shell or web-db-shell like phpMyAdmin or more lightweight Adminer
//   Some issues like id_user < 3 must be solved manually or with usage custom fixes at string 82-88, 133 in this script
# die();

$_vs_addr = 'old-sip.voipswitch.com';   // VoipSwitch database server
$_vs_db = 'voipswitch_database_name';
$_vs_username = 'voipswitch_database_username';
$_vs_password = 'voipswitch_database_password';

$ip_access_subnet = '127.0.0.1 192.168.0.0/22';   // Add your IP/subnets - access only whitelist (EXPORT PASSWORDS !!)

$new_id_group_retails = 3;   // id_group in Magnus for voipsw retail-clients; Create before import;
$new_id_group_virtpbx = 4;   // id_group in Magnus for voipsw pbx-clients; Create before import;
$new_id_user_owner = 1;      // id_user in users (all new accounts owner/creator). id = 1 for root in magnusbilling.
$new_did_prefix = '7812';    // If voipswitch has local-numbers without country-city code, add this for convert DIDs to E.164

// ----- END CONFIG PART ----- //



function net_match ($ip, $network) {   // # net_match ($_SERVER['REMOTE_ADDR'], '192.168.0.0/16') // return true if IP in subnet
   $ip_arr = explode ('/', $network);
   if ( !isset($ip_arr[1]) or $ip_arr[1] == '') { $ip_arr[1] = 32; };
   $network_long = ip2long($ip_arr[0]);
   $x = ip2long($ip_arr[1]);
   $mask = long2ip($x) == $ip_arr[1] ? $x : 0xffffffff << (32 - $ip_arr[1]);
   $ip_long = ip2long($ip);
   # echo ">".$ip_arr[1]."> ".decbin($mask)."\n";
   return ($ip_long & $mask) == ($network_long & $mask); }


function check_ip_acl($ip, $ip_acl_str) { // # check_ip_acl ($_SERVER['REMOTE_ADDR'], '192.168.0.0/16, 127.0.0.0/8') - return number of first matched subnet. If no matches return 0.
   $ip_acl_str = trim($ip_acl_str);
   $ip_acl_arr = preg_split("/[\s;,|]+/", $ip_acl_str);
   foreach ( $ip_acl_arr as $key => $acl ) { if (net_match($ip, trim($acl)) == 1) { return $key+1; }   }
   return 0; }


if ( check_ip_acl($_SERVER['REMOTE_ADDR'], $ip_access_subnet) == 0 ) {
   print 'Error: IP '.$_SERVER['REMOTE_ADDR'].' denied in config.';
   die();
}


$DB = mysqli_connect($_vs_addr, $_vs_username, $_vs_password) or die(-1*mysqli_connect_errno($DB).'; MySQL Connect Error;');

mysqli_select_db($DB, $_vs_db) or die(-1*mysqli_errno($DB).'; MySQL Select-DB Error;');

header("Content-type: text/plain");
header("Charset: utf-8");

$title = '-- Data for MagnusBilling';
$cu_date = date("Y-m-d H:i:s");

$sql = mysqli_query($DB, "SET NAMES UTF8");

print "USE mbilling";

function pwd($p) {
   global $cu_date;
   return sha1($p.$cu_date.'sadfi7348934903ejsifUm0i');
}

function generate_random_password($length=15, $arr='abcdefghijkmnoprstuvxyzABCDEFGHJKLMNPQRSTUVXYZ23456789_~!@#$%^&*')
{ $length = (int)$length; $pass = ""; srand( ((int)((double)microtime()*1000003)) );
if ($length == 0) { $length = 15; }
for($i = 0; $i < $length; $i++) { $index = mt_rand(0, strlen($arr) - 1); $pass .= $arr[$index]; }
return $pass; }

$accounts_sql = $accounts_pbx_sql = $sipusers_sql = $balances_sql = $detailed_data_sql = $pkg_callerid_sql = '';

$sql = mysqli_query($DB, "SELECT * FROM `clientsretail` ORDER BY login");
$balances_sql .= "\n-- -- retail balances\n\n";
while ($r = mysqli_fetch_assoc($sql)) {

   # main accounts
   if ( substr($r['password'], -4) == '_OFF' or $r['account_state'] < 0) { $act = 0; } else { $act = 1; };
   if ( $r['id_tariff'] == 18 ) { $plan = 1; }
   if ( $r['id_tariff'] == 44 ) { $plan = 2; }
   if ( $r['id_client'] == 1 ) { $r['id_client'] = 69; }

   $accounts_sql .= 'INSERT INTO `pkg_user` (`id`, `id_user`, `id_group`, `username`, `password`, `active`, `credit`, `city`, `state`, `country`, `disk_space`, `sipaccountlimit`, `calllimit`, `cpslimit`, `callingcard_pin`, `id_plan`)
   VALUES (\''.$r['id_client'].'\', \''.$new_id_user_owner.'\', \''.$new_id_group_retails.'\', \''.$r['login'].'\', \''.$r['password'].'\', \''.$act.'\', \''.$r['account_state'].'\', \'SPb_\', \'Russia\', \'7\', \'1\', \'1\', \'2\', \'3\', \''.generate_random_password(6, '123456789').'\', \''.$plan.'\');'."\n";

   $balances_sql .= 'UPDATE `pkg_user` SET credit = \''.$r['account_state'].'\' WHERE username = \''.$r['login'].'\' LIMIT 1;'."\n";
}
print "\n\n-- create RETAIL accounts --\n\n$accounts_sql\n\n";


$sql = mysqli_query($DB, "SELECT cp.* FROM `clientspbx` cp ORDER BY login");
$balances_sql .= "\n-- -- pbx balances\n\n";
$detailed_data_sql .= "\n-- -- pbx detailed data \n\n";
$pbx_res_to_accid = array ();
$pbx_res_to_login = array ();
while ($r = mysqli_fetch_assoc($sql)) {
   if ($r['reseller'] > 0) {
              $pbx_res_to_accid[$r['reseller']] = $r['id_client'];
              $pbx_res_to_login[$r['reseller']] = $r['login'];
   }
   # main accounts
   if ( substr($r['password'], -4) == '_OFF' or $r['account_state'] < 0) { $act = 0; } else { $act = 1; };
   if ( $r['id_tariff'] == 18 ) { $plan = 1; }
   if ( $r['id_tariff'] == 44 ) { $plan = 2; }
   $accounts_pbx_sql .= 'INSERT INTO `pkg_user` (`id`, `id_user`, `id_group`, `username`, `password`, `active`, `credit`, `city`, `state`, `country`, `callingcard_pin`, `id_plan`)
                VALUES (\''.$r['id_client'].'\', \''.$new_id_user_owner.'\', \''.$new_id_group_virtpbx.'\', \''.$r['login'].'\', \''.$r['password'].'\', \''.$act.'\', \''.$r['account_state'].'\', \'SPb_\', \'Russia\', \''.generate_random_password(6, '0123456789').'\', \''.$plan.'\');'."\n";
   $balances_sql .= 'UPDATE `pkg_user` SET credit = \''.$r['account_state'].'\' WHERE username = \''.$r['login'].'\' LIMIT 1;'."\n";
   $detailed_data_sql .= "UPDATE `pkg_user` SET `lastname` = '".$r["company_name"]."', `firstname` = '".$r["company_name"]."', `language` = 'ru', `sipaccountlimit` = '10', `calllimit` = '20', `cpslimit` = '5', `disk_space` = '7', city = 'SPb_', company_name = '".$r["company_name"]."', `commercial_name` = 'PBX_".$r['login']."', `id_group` = '".$new_id_group_virtpbx."' WHERE `username` = '".$r['login']."' LIMIT 1;\n";
}
print "\n\n-- create PBX accounts --\n\n$accounts_pbx_sql\n\n";

# print_r($pbx_res_to_accid);

print "\n\n-- Balances --\n\n$balances_sql\n\n";

$sql = mysqli_query($DB, "SELECT * FROM `clientsshared` ORDER BY login");
$sipusers_sql = '';
while ($r = mysqli_fetch_assoc($sql)) {
   # sip users
   $tp = explode(';', $r['tech_prefix']);
   if ( substr($tp[0], 0, 2) == 'CP' ) { $cid = substr($tp[0], 4); }
   if ( $r['id_reseller'] > 0 ) {
            $id_client = $pbx_res_to_accid[$r['id_reseller']];
            $pbx_login = $pbx_res_to_login[$r['id_reseller']]; }
   else {
            $id_client = $r['id_client']; $pbx_login = $r['login'];
            if ($id_client == 1) { $id_client = 69; $r['id_client'] = 69; }
   };

   $sipusers_sql .= "INSERT INTO `pkg_sip` (`id`, `id_user`, `name`, `accountcode`, `callerid`, `context`, `secret`, `allow`, `defaultuser`, `cid_number`, `calllimit`, `status`, `voicemail_password`, `host`)
               VALUES ('".$r['id_client']."', '".$id_client."', '".$r['login']."', '$pbx_login', '$cid', 'billing', '".$r['password']."', 'alaw,ulaw,g729,gsm,opus', '".$r['login']."', '$cid', '2', '1', '".generate_random_password(6, '0123456789')."', 'dynamic');\n";
}
print "\n-- create SIP-users --\n\n$sipusers_sql\n\n";


$sql = mysqli_query($DB, "
SELECT
cs.login, cs.password, cs.id_client, ic.Name, ic.LastName, ic.Address, ic.EMail, ic.Phone, ic.MobilePhone, DATE(ic.Creation_Date) AS Creation_Date,
    cs.account_state, cs.id_tariff, cs.id_reseller,
    cs.type%2 AS enabled   -- 1st BIT in type int-value is Enable status !!
 FROM clientsretail cs
    LEFT JOIN invoiceclients ic ON ic.IDClient = cs.id_client   -- and ic.Type = cd.client_type
");
$detailed_data_sql .= "\n-- detailed retail data\n\n";
while ($r = mysqli_fetch_assoc($sql)) {
   if ($r['id_reseller'] < 0) { $cust_id = $r['id_client']; } else { $cust_id = $r['id_reseller']; }
   if ( substr($r["Creation_Date"], 0, 4) == 1900 ) { $r["Creation_Date"] = '1980-01-01 00:00:01'; }
   if ( strlen($r["Phone"]) == 7 ) { $r["Phone"] = $new_did_prefix.$r["Phone"]; }
   $detailed_data_sql .= "UPDATE `pkg_user` SET `lastname` = '".substr($r["LastName"], 0, 50)."', `firstname` = '".substr($r["Name"], 0, 50)."', `address` = '".$r['Address']."', `phone` = '".$r["Phone"]."', `mobile` = '".substr($r["MobilePhone"], 0, 20)."', `email` = '".$r["EMail"]."', `creationdate` = '".$r["Creation_Date"]."', `language` = 'ru' WHERE `username` = '".$r['login']."' LIMIT 1;\n";
}
print "-- UPDATE Retail User detailed data --\n\n$detailed_data_sql\n\n";





$sql = mysqli_query($DB, "
SELECT pld.id AS pld_id, pld.areacode_id, pld.availability, pld.assigned, pld.did
, pc.country_name AS strana, FLOOR(pc.setup_fee*30) AS setup_price, FLOOR(pc.monthly_fee*30) AS monthly_price
, cds.client_id, cds.client_type, cds.client_type_name, cds.client_login
FROM `portal_localdids` pld
LEFT JOIN portal_localareacodes plac ON plac.id = pld.areacode_id
LEFT JOIN portal_countries pc ON pc.id = plac.country_id  
LEFT JOIN client_dids cds ON cds.phone_number = pld.did
");
$did_fulllist_sql = $did_use_sql = $did_dest_sql = '';
while ($r = mysqli_fetch_assoc($sql)) {
   if ( $r['client_id'] > 0 ) {
        $client_id = $r['client_id'];
        $assigned = 1;
        $start_date = $cu_date;
        if ( $client_id == 1 ) { $client_id = 69; };
   }
   else
   {
        $client_id = 'NULL';
        $assigned = 0;
        $start_date = '';
   };
   $did_fulllist_sql .= "INSERT INTO pkg_did (`id`, `id_user`, `activated`, `reserved`, `did`, `creationdate`, `startingdate`, `fixrate`, `connection_charge`, `country`)
     VALUES ('".$r['pld_id']."', $client_id, $assigned, '$assigned', '".$new_did_prefix.$r['did']."', '$cu_date', '$start_date', '".$r['monthly_price']."', '".$r['setup_price']."', '".$r['strana']."');\n";
   if ( $client_id > 0 ) {
       $did_use_sql .= "INSERT INTO pkg_did_use (`id_user`, `id_did`, `reservationdate`, `status`, `month_payed`) VALUES ('".$client_id."', '".$r['pld_id']."', '$start_date', 1, 1);\n";
       $did_dest_sql .= "INSERT INTO pkg_did_destination (`id_user`, `id_sip`, `id_did`, `activated`, `voip_call`, `destination`, `priority`) VALUES ('".$client_id."', '".$client_id."', '".$r['pld_id']."', 1, 1, '', 3);\n";
       $pkg_callerid_sql .= "INSERT INTO pkg_callerid (`id`, `cid`, `name`, `description`, `id_user`, `activated`) VALUES ('".$r['pld_id']."', '".$new_did_prefix.$r['did']."',  '".$new_did_prefix.$r['did']."', 'for user ".$r['client_login'].", id: ".$client_id."',  '".$client_id."', 1);\n";
   }
}
print "-- DIDs FULL LIST --\n\n$did_fulllist_sql\n\n";
print "-- DIDs USAGE LIST --\n\n$did_use_sql\n\n";
print "-- DIDs DESTINATION LIST --\n\n$did_dest_sql\n\n";


print "-- CallerID LIST --\n\n$pkg_callerid_sql\n\n";


echo "\n\n-- # Payments -- \n\n";
$sql = mysqli_query($DB, "SELECT `id_client`, `client_type`, `money`, `data`, `type`, `description`, `actual_value`  FROM `payments` ORDER BY id_client");

while ($r = mysqli_fetch_assoc($sql)) {
   $pd = explode(" ", $r['description']);
   if ( $r['type'] == 1 ) {
      $balance = $r['money'];
      $in = 'Pay';
      for ($i = 0; $i < 12; $i++) {   if ( preg_match('/^[0-9]/', $pd[$i]) ) { $in .= '-'.$pd[$i]; }   };
   }
   elseif ( $r['type'] == 3 ) {
      $balance = -1 * $r['money'];
      $in = 'DID';
      for ($i = 0; $i < 12; $i++) {   if ( preg_match('/^[0-9]/', $pd[$i]) ) { $in .= '-'.$pd[$i]; }   };      
   }
   elseif ( $r['type'] == 6 ) { $balance = -1 * $r['money']; }
   elseif ( $r['type'] == 5 ) { $balance = -1 * $r['money']; }
   if ( $balance != 0 ) {
      print 'INSERT INTO pkg_refill (id_user, `date`, credit, description, refill_type, payment, invoice_number) '.
       'VALUES (\''.$r['id_client'].'\', \''.$r['data'].'\', \''.$balance.'\', \''.$r['description'].'\', \'0\', \'1\', \''.$in.'\');'."\n";
   }
}

?>

