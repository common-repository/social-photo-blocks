<?php

if(strlen($_GET['code'])>512) exit();

$arr = explode("/wp-content/", $_SERVER['PHP_SELF']);
header("Location: {$arr[0]}/wp-admin/options-general.php?page=Social_Photo_Blocks&code={$_GET['code']}");

?>