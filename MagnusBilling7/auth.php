<?php

session_start();

# Rely to MagnusBilling7 php-sessions data
if ( !isset ($_SESSION['logged']) or $_SESSION['username'] == '' )
{
   print '<a href="../" target="_blank">Login first</a>
      and <a href="?">refresh page</a>';
   die;
};

?>
