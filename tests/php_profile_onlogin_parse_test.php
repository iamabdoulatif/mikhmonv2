<?php

require __DIR__ . '/../include/mikhmon_compat.php';

function assert_profile_equal($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$empty = mikhmon_parse_profile_onlogin('');
assert_profile_equal('0', $empty['expmode'], 'Empty on-login should be treated as no expiration mode.');
assert_profile_equal('None', $empty['expmode_label'], 'Empty on-login should show None.');
assert_profile_equal('', $empty['price'], 'Empty on-login should not invent a price.');
assert_profile_equal('Disable', $empty['lock'], 'Empty on-login should default lock to Disable.');

$script = ':put (",remc,500,1d,450,,Enable,,"); :local user "demo"';
$parsed = mikhmon_parse_profile_onlogin($script);
assert_profile_equal('remc', $parsed['expmode'], 'Mikhmon on-login should expose expiration mode.');
assert_profile_equal('Remove & Record', $parsed['expmode_label'], 'Mikhmon on-login should label remc.');
assert_profile_equal('500', $parsed['price'], 'Mikhmon on-login should expose price.');
assert_profile_equal('450', $parsed['selling_price'], 'Mikhmon on-login should expose selling price.');
assert_profile_equal('1d', $parsed['validity'], 'Mikhmon on-login should expose validity.');
assert_profile_equal('Enable', $parsed['lock'], 'Mikhmon on-login should expose lock mode.');

echo "php_profile_onlogin_parse_test passed\n";
