<?php
session_start();
error_reporting(0);

if ($removesecr != "") {
  $API->comm("/ppp/secret/remove", array(".id" => "$removesecr"));
} elseif ($enablesecr != "") {
  $API->comm("/ppp/secret/set", array(".id" => "$enablesecr", "disabled" => "no"));
} elseif ($disablesecr != "") {
  $API->comm("/ppp/secret/set", array(".id" => "$disablesecr", "disabled" => "yes"));
}

echo "<script>window.location='./?ppp=secrets&session=" . $session . "'</script>";
