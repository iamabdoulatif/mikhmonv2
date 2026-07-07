<?php

require __DIR__ . '/../lib/routeros_api.class.php';

$api = new RouterosAPI();
if ($api->timeout < 15) {
    fwrite(STDERR, 'RouterOS API timeout should be at least 15 seconds for multi-row replies. Actual: ' . $api->timeout . "\n");
    exit(1);
}

echo "php_routeros_api_timeout_test passed\n";
