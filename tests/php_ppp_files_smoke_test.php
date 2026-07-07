<?php

$root = dirname(__DIR__);
$required = array(
    'ppp/pppsecrets.php',
    'ppp/addsecret.php',
    'ppp/secretbyname.php',
    'ppp/pppprofile.php',
    'ppp/addpppprofile.php',
    'ppp/profilebyname.php',
    'ppp/pppactive.php',
    'process/psecret.php',
    'process/removepprofile.php',
);

foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) {
        fwrite(STDERR, "Missing PPPoE file: $file\n");
        exit(1);
    }
}

echo "php_ppp_files_smoke_test passed\n";
