<?php
session_start();
error_reporting(0);

$pid = $removepprofile;
$API->comm("/ppp/profile/remove", array(
  ".id" => "$pid",
));

echo "<script>window.location='./?ppp=profiles&session=" . $session . "'</script>";
