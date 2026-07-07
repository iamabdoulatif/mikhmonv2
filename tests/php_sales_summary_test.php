<?php

$tmp = sys_get_temp_dir() . '/mikhmon-sales-test-' . getmypid();
if (!is_dir($tmp)) {
    mkdir($tmp, 0755, true);
}
define('MIKHMON_DATA_DIR', $tmp);

require __DIR__ . '/../include/mikhmon_compat.php';

function assert_equal($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$session = 'UnitTest';
@unlink(mikhmon_sale_file($session));

$saleOne = array(
    'name' => 'jul/07/2026-|-08:58:35-|-abc123-|-500-|-10.0.0.2-|-AA:BB:CC:DD:EE:FF-|-1d-|-01-JOUR-|-vc-001-',
    'owner' => 'jul2026',
    'source' => 'jul/07/2026',
    'comment' => 'mikhmon',
);
$saleTwo = array(
    'name' => 'jul/08/2026-|-09:00:00-|-def456-|-1000-|-10.0.0.3-|-11:22:33:44:55:66-|-1d-|-01-JOUR-|-vc-002-',
    'owner' => 'jul2026',
    'source' => 'jul/08/2026',
    'comment' => 'mikhmon',
);

$record = mikhmon_sale_script_to_record($saleOne);
assert_equal('abc123', $record['username'], 'Sale parser should extract username.');
assert_equal(500.0, $record['price'], 'Sale parser should extract numeric price.');
assert_equal('jul2026', $record['owner'], 'Sale parser should keep month owner.');

mikhmon_save_sale_log($session, $saleOne);
mikhmon_save_sale_log($session, $saleOne);
mikhmon_save_sale_log($session, $saleTwo);

$daySummary = mikhmon_sales_summary($session, 'jul2026', 'jul/07/2026');
assert_equal(1, $daySummary['count'], 'Daily summary should ignore duplicate scripts.');
assert_equal(500.0, $daySummary['total'], 'Daily summary should total the exact sale amount.');

$monthSummary = mikhmon_sales_summary($session, 'jul2026');
assert_equal(2, $monthSummary['count'], 'Monthly summary should include all unique month sales.');
assert_equal(1500.0, $monthSummary['total'], 'Monthly summary should total exact month sales.');

$formatted = mikhmon_format_money_amount(1500, 'fcfa', array('indo' => array()));
assert_equal('1,500.00', $formatted, 'Money formatter should keep existing non-Indonesian decimal format.');

mikhmon_remove_sale_log($session, 'jul2026', 'jul/07/2026');
$removedDaySummary = mikhmon_sales_summary($session, 'jul2026', 'jul/07/2026');
assert_equal(0, $removedDaySummary['count'], 'Removing a day should remove local fallback rows for that day.');
$remainingMonthSummary = mikhmon_sales_summary($session, 'jul2026');
assert_equal(1, $remainingMonthSummary['count'], 'Removing a day should keep other month rows.');
assert_equal(1000.0, $remainingMonthSummary['total'], 'Removing a day should keep other month amounts.');

@unlink(mikhmon_sale_file($session));
@rmdir($tmp);

echo "php_sales_summary_test passed\n";
