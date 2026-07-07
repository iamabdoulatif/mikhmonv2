<?php
/*
 * Mikhmon runtime config loader.
 *
 * The editable session store (router IPs, users, passwords) lives in
 * data/config.php — the persistent Docker volume — so credentials are never
 * baked into the public image or committed to git. include/config.php is only
 * a shipped default template; on first run it is migrated to data/config.php.
 *
 * MIKHMON_CONFIG_FILE is the single path every reader AND writer must use.
 * If the data directory is not writable, we fall back to the legacy
 * include/config.php location (same behaviour as historical Mikhmon).
 */
if (!defined('MIKHMON_CONFIG_FILE')) {
    $mikhmonCfgDir  = __DIR__ . '/../data';
    $mikhmonCfgFile = $mikhmonCfgDir . '/config.php';
    if (!file_exists($mikhmonCfgFile)) {
        if (!is_dir($mikhmonCfgDir)) {
            @mkdir($mikhmonCfgDir, 0755, true);
        }
        @copy(__DIR__ . '/config.php', $mikhmonCfgFile);
    }
    define('MIKHMON_CONFIG_FILE', file_exists($mikhmonCfgFile) ? $mikhmonCfgFile : (__DIR__ . '/config.php'));
}
include(MIKHMON_CONFIG_FILE);
