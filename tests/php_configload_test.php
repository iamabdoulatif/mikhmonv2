<?php
// configload must expose MIKHMON_CONFIG_FILE and load the $data config array.
// The runtime store lives in data/config.php (volume) and must at least
// contain the admin entry; include/config.php stays a credential-free template.
error_reporting(E_ALL & ~E_DEPRECATED);
$_SERVER['REQUEST_URI'] = '/';

include __DIR__ . '/../include/configload.php';

if (!defined('MIKHMON_CONFIG_FILE')) {
    fwrite(STDERR, "FAIL: MIKHMON_CONFIG_FILE not defined\n");
    exit(1);
}
if (!is_array($data) || !isset($data['mikhmon'])) {
    fwrite(STDERR, "FAIL: \$data['mikhmon'] not loaded from " . MIKHMON_CONFIG_FILE . "\n");
    exit(1);
}

// The shipped template must not embed a router session (credentials-free).
$template = file_get_contents(__DIR__ . '/../include/config.php');
if (preg_match("/\\\$data\\['(?!mikhmon')/", $template)) {
    fwrite(STDERR, "FAIL: include/config.php template contains a non-default session\n");
    exit(1);
}

echo "php_configload_test passed\n";
