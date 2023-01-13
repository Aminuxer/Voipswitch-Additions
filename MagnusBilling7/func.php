<?php

$ip = $_SERVER['REMOTE_ADDR'];


function create_select_csv_format ($name) {
   $out = '<SELECT name="'.$name.'">
      <OPTION value="pref_desc_price">Prefix, description, price
      <OPTION value="desc_pref_price">Description, prefix, price
   </SELECT>';
   return $out;
}


function create_select_csv_delimiter ($name) {
   $out = '<SELECT name="'.$name.'">
      <OPTION value=",">, [comma]
      <OPTION value=";">; [dot-comma]
      <OPTION value="\t">\t [TAB]
   </SELECT>';
   return $out;
}

function create_select_provider ($name) {
   global $db;
   $q = mysqli_query($db, "SELECT id, provider_name FROM `pkg_provider` ORDER BY provider_name");
   $out = '<SELECT name="'.$name.'">';
   while ( $r = mysqli_fetch_array($q) ) { $out .= '<OPTION value="'.$r['id'].'">'.$r['provider_name']; };
   $out .= '</SELECT>';
   return $out;
}

function get_name_provider ($id) {
   global $db;
   $id = (int)$id;
   $q = mysqli_query($db, "SELECT id, provider_name FROM `pkg_provider` WHERE `id` = '$id' LIMIT 1");
   $r = mysqli_fetch_assoc($q);
   $out = $r['provider_name'];
   return $out;
}


function create_select_client_tariff ($name) {
   global $db;
   $q = mysqli_query($db, "SELECT id, name FROM `pkg_plan` ORDER BY name");
   $out = '<SELECT name="'.$name.'">';
   while ( $r = mysqli_fetch_array($q) ) { $out .= '<OPTION value="'.$r['id'].'">'.$r['name']; };
   $out .= '</SELECT>';
   return $out;
}


function get_name_client_tariff ($id) {
   global $db;
   $q = mysqli_query($db, "SELECT id, name FROM `pkg_plan` WHERE `id` = '$id' LIMIT 1");
   $r = mysqli_fetch_assoc($q);
   $out = $r['name'];
   return $out;
}


function create_select_trunk_group ($name) {
   global $db;
   $q = mysqli_query($db, "SELECT id, name, description FROM `pkg_trunk_group` ORDER BY name");
   $out = '<SELECT name="'.$name.'">';
   while ( $r = mysqli_fetch_array($q) ) { $out .= '<OPTION value="'.$r['id'].'">'.$r['name'].' - '.$r['description']; };
   $out .= '</SELECT>';
   return $out;
}


function create_select_client ($name) {
   global $db;
   $q = mysqli_query($db, "SELECT id, username FROM `pkg_user` WHERE id_group NOT IN (1, 5) ORDER BY id_group DESC, username");
   $out = '<SELECT name="'.$name.'">';
   while ( $r = mysqli_fetch_array($q) ) { $out .= '<OPTION value="'.$r['id'].'">'.$r['username']; };
   $out .= '</SELECT>';
   return $out;
}


function time_shorter($seconds) {
   $D = floor($seconds / (3600*24));
   $H = ($seconds / 3600) % 3600;
   $i = ($seconds / 60) % 60;
   $s = $seconds % 60;
   return sprintf("%d %02d:%02d:%02d", $D, $H, $i, $s);
}

function net_match ($ip, $network) {   # net_match ($_SERVER['REMOTE_ADDR'], '192.168.0.0/16') - check that source IP match subnet
   $ip_arr = explode ('/', $network);
   if ( ! isset ($ip_arr[1]) ) { $ip_arr[1] = ''; };
   if ($ip_arr[1] == '') { $ip_arr[1] = 32; };
   $network_long = ip2long($ip_arr[0]);
   $x = ip2long($ip_arr[1]);
   $mask = long2ip($x) == $ip_arr[1] ? $x : 0xffffffff << (32 - $ip_arr[1]);
   $ip_long = ip2long($ip);
   # echo ">".$ip_arr[1]."> ".decbin($mask)."\n";
   return ($ip_long & $mask) == ($network_long & $mask); }


function check_ip_acl($ip, $ip_acl_str) { # check_ip_acl ($_SERVER['REMOTE_ADDR'], '192.168.0.0/16, 127.0.0.0/8') - check that source IP match at least one subnets in IP ACL
   $ip_acl_str = trim($ip_acl_str);
   $ip_acl_arr = preg_split("/[\s;,|]+/", $ip_acl_str);
   foreach ( $ip_acl_arr as $key => $acl ) { if (net_match($ip, trim($acl)) == 1) { return $key+1; }   }
   return 0; }


$db = mysqli_connect($array['dbhost'], $array['dbuser'], $array['dbpass']) or die(-1*mysqli_connect_errno($db).'; MySQL Connect Error;');
mysqli_select_db($db, $array['dbname']) or die(-1*mysqli_errno($db).'; MySQL Select-DB Error;');
mysqli_query($db, "SET NAMES UTF8");


?>
