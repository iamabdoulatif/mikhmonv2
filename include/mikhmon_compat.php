<?php
/**
 * Mikhmon v7 Compatibility & Persistence Layer
 * Fournit : parsing de dates RouterOS v6/v7, persistance des ventes locale,
 * helpers de repair pour les tickets expirés.
 */

if (!defined('MIKHMON_DATA_DIR')) {
    define('MIKHMON_DATA_DIR', __DIR__ . '/../data');
}

/**
 * Crée le répertoire de données persistant si nécessaire (Docker/volume ready).
 */
function mikhmon_ensure_data_dir() {
    $dir = MIKHMON_DATA_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Détecte le format de date retourné par RouterOS à partir d'un échantillon.
 * Retourne 'iso' (YYYY-MM-DD) ou 'legacy' (MMM/DD/YYYY ou MMM-DD-YYYY).
 */
function mikhmon_detect_routeros_date_format($sampleDate) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampleDate)) {
        return 'iso';
    }
    return 'legacy';
}

/**
 * Convertit une date RouterOS en timestamp PHP.
 * Accepte les deux formats : v7 ISO et v6 legacy (MMM/DD/YYYY ou MMM-DD-YYYY).
 */
function mikhmon_routeros_date_to_timestamp($dateStr, $timeStr = '00:00:00') {
    $dateStr = trim($dateStr);
    $timeStr = trim($timeStr);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr, $m)) {
        return strtotime($dateStr . ' ' . $timeStr);
    }
    if (preg_match('/^([a-zA-Z]{3})[\/-](\d{1,2})[\/-](\d{4})$/', $dateStr, $m)) {
        return strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $timeStr);
    }
    return false;
}

/**
 * Formate une date RouterOS v7 ISO en legacy MMM/DD/YYYY pour compatibilité PHP interne.
 * Utilisé pour normaliser les clés de rapports.
 */
function mikhmon_date_to_legacy($dateStr) {
    $ts = mikhmon_routeros_date_to_timestamp($dateStr);
    if ($ts === false) return $dateStr;
    return strtolower(date('M/d/Y', $ts));
}

/**
 * Retourne la clé mensuelle Mikhmon (ex: jul2026) depuis une source de vente.
 */
function mikhmon_sale_owner_from_source($source) {
    $source = mikhmon_date_to_legacy($source);
    $parts = explode('/', strtolower($source));
    if (count($parts) >= 3 && $parts[0] !== '' && $parts[2] !== '') {
        return $parts[0] . $parts[2];
    }
    return '';
}

/**
 * Normalise une ligne /system script Mikhmon en champs stables.
 */
function mikhmon_sale_script_to_record($scriptData) {
    $name = isset($scriptData['name']) ? $scriptData['name'] : '';
    $parts = explode('-|-', $name);
    $source = isset($parts[0]) ? trim($parts[0]) : '';
    if ($source === '' && isset($scriptData['source'])) {
        $source = $scriptData['source'];
    }
    $source = strtolower(mikhmon_date_to_legacy($source));

    $owner = isset($scriptData['owner']) ? strtolower(trim($scriptData['owner'])) : '';
    if ($owner === '') {
        $owner = mikhmon_sale_owner_from_source($source);
    }

    $price = isset($parts[3]) ? mikhmon_parse_money_amount($parts[3]) : 0.0;

    return array(
        'name' => $name,
        'owner' => $owner,
        'source' => $source,
        'time' => isset($parts[1]) ? $parts[1] : '',
        'username' => isset($parts[2]) ? $parts[2] : '',
        'price' => $price,
        'address' => isset($parts[4]) ? $parts[4] : '',
        'mac' => isset($parts[5]) ? $parts[5] : '',
        'validity' => isset($parts[6]) ? $parts[6] : '',
        'profile' => isset($parts[7]) ? $parts[7] : '',
        'user_comment' => isset($parts[8]) ? $parts[8] : '',
        'comment' => isset($scriptData['comment']) ? $scriptData['comment'] : '',
        'raw' => $scriptData,
    );
}

/**
 * Déduplique des scripts de vente par nom, qui est la clé naturelle Mikhmon.
 */
function mikhmon_unique_sale_scripts($rows) {
    $unique = array();
    foreach ((array) $rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = isset($row['name']) ? $row['name'] : md5(json_encode($row));
        $unique[$key] = $row;
    }
    return array_values($unique);
}

/**
 * Retourne les lignes locales sous forme plate.
 */
function mikhmon_sale_log_rows($session, $owner = '') {
    $loaded = mikhmon_load_sale_log($session, $owner);
    if ($owner !== '') {
        return is_array($loaded) ? array_values($loaded) : array();
    }

    $rows = array();
    foreach ((array) $loaded as $monthRows) {
        foreach ((array) $monthRows as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
    }
    return $rows;
}

/**
 * Filtre des scripts par mois, jour, préfixe utilisateur ou commentaire.
 */
function mikhmon_filter_sale_scripts($rows, $owner = '', $source = '', $prefix = '', $comment = '') {
    $filtered = array();
    $owner = strtolower($owner);
    $source = strtolower(mikhmon_date_to_legacy($source));

    foreach (mikhmon_unique_sale_scripts($rows) as $row) {
        $record = mikhmon_sale_script_to_record($row);
        if ($owner !== '' && $record['owner'] !== $owner) {
            continue;
        }
        if ($source !== '' && $record['source'] !== $source) {
            continue;
        }
        if ($prefix !== '' && substr($record['username'], 0, strlen($prefix)) !== $prefix) {
            continue;
        }
        if ($comment !== '' && strpos($record['user_comment'], $comment) === false && strpos($record['comment'], $comment) === false) {
            continue;
        }
        $filtered[] = $row;
    }
    return $filtered;
}

/**
 * Importe des scripts RouterOS dans le journal local, sans doublons.
 */
function mikhmon_import_sale_scripts($session, $scripts) {
    foreach ((array) $scripts as $row) {
        if (!is_array($row) || !isset($row['name'])) {
            continue;
        }
        $record = mikhmon_sale_script_to_record($row);
        if ($record['source'] === '' || $record['username'] === '') {
            continue;
        }
        mikhmon_save_sale_log($session, $row);
    }
}

/**
 * Charge les ventes fiables : RouterOS si disponible, puis journal local.
 */
function mikhmon_get_sale_scripts($API, $session, $query = array()) {
    $routerRows = array();
    if (is_object($API) && method_exists($API, 'comm')) {
        $routerRows = $API->comm('/system/script/print', $query);
        mikhmon_import_sale_scripts($session, $routerRows);
    }

    $owner = isset($query['?owner']) ? strtolower($query['?owner']) : '';
    $source = isset($query['?source']) ? strtolower(mikhmon_date_to_legacy($query['?source'])) : '';
    if ($owner === '' && $source !== '') {
        $owner = mikhmon_sale_owner_from_source($source);
    }

    $localRows = mikhmon_sale_log_rows($session, $owner);
    $rows = mikhmon_unique_sale_scripts(array_merge($localRows, (array) $routerRows));

    $comment = isset($query['?comment']) ? $query['?comment'] : '';
    return mikhmon_filter_sale_scripts($rows, $owner, $source, '', $comment);
}

/**
 * Résumé fiable des ventes locales déjà importées.
 */
function mikhmon_sales_summary($session, $owner, $source = '') {
    $rows = mikhmon_filter_sale_scripts(mikhmon_sale_log_rows($session, $owner), $owner, $source);
    $total = 0.0;
    foreach ($rows as $row) {
        $record = mikhmon_sale_script_to_record($row);
        $total += $record['price'];
    }
    return array(
        'count' => count($rows),
        'total' => $total,
    );
}

/**
 * Chemin du fichier JSON de persistance des ventes pour une session.
 */
function mikhmon_sale_file($session) {
    return mikhmon_ensure_data_dir() . '/sales_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $session) . '.json';
}

/**
 * Sauvegarde une vente localement (résistance aux redémarrages Docker / session PHP).
 * Les données sont indexées par mois (owner) pour un accès rapide.
 */
function mikhmon_save_sale_log($session, $scriptData) {
    $file = mikhmon_sale_file($session);
    $data = [];
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $data = json_decode($json, true) ?: [];
    }
    $record = mikhmon_sale_script_to_record($scriptData);
    $owner = $record['owner'];
    if ($owner === '') {
        $owner = isset($scriptData['owner']) ? $scriptData['owner'] : '';
    }
    if (!isset($data[$owner])) {
        $data[$owner] = [];
    }
    // Idempotence : la clé unique d'une vente est son "name" (date-|-heure-|-user-|-...).
    // Le rapport ré-appelle cette fonction à chaque affichage ; sans déduplication,
    // le fichier JSON gonflerait sans limite avec des doublons.
    $key = isset($scriptData['name']) ? $scriptData['name'] : null;
    if ($key !== null) {
        foreach ($data[$owner] as $existing) {
            if (isset($existing['name']) && $existing['name'] === $key) {
                return; // vente déjà persistée
            }
        }
    }
    $data[$owner][] = $scriptData;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Charge les ventes locales pour un owner (mois) donné.
 * Fusionne avec les données RouterOS si besoin.
 */
function mikhmon_load_sale_log($session, $owner = '') {
    $file = mikhmon_sale_file($session);
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true) ?: [];
    if ($owner === '') return $data;
    return isset($data[$owner]) ? $data[$owner] : [];
}

/**
 * Supprime les ventes locales d'un user donné (quand le user est supprimé).
 */
function mikhmon_remove_sale_by_user($session, $username) {
    $file = mikhmon_sale_file($session);
    if (!file_exists($file)) return;
    $json = file_get_contents($file);
    $data = json_decode($json, true) ?: [];
    foreach ($data as $owner => &$entries) {
        $entries = array_values(array_filter($entries, function($e) use ($username) {
            $parts = explode('-|-', isset($e['name']) ? $e['name'] : '');
            return (isset($parts[2]) ? $parts[2] : '') !== $username;
        }));
    }
    unset($entries); // rompt la référence du foreach (évite tout effet de bord ultérieur)
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Supprime des ventes locales par mois et/ou jour de rapport.
 */
function mikhmon_remove_sale_log($session, $owner = '', $source = '') {
    $file = mikhmon_sale_file($session);
    if (!file_exists($file)) return;

    $json = file_get_contents($file);
    $data = json_decode($json, true) ?: array();
    $owner = strtolower($owner);
    $source = strtolower(mikhmon_date_to_legacy($source));

    if ($owner === '' && $source !== '') {
        $owner = mikhmon_sale_owner_from_source($source);
    }

    if ($owner === '' && $source === '') {
        file_put_contents($file, json_encode(array(), JSON_PRETTY_PRINT));
        return;
    }

    if (!isset($data[$owner])) {
        return;
    }

    if ($source === '') {
        unset($data[$owner]);
    } else {
        $data[$owner] = array_values(array_filter($data[$owner], function($row) use ($source) {
            $record = mikhmon_sale_script_to_record($row);
            return $record['source'] !== $source;
        }));
    }

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Parse le prix d'une chaîne, gère les formats indonésiens et standards.
 */
function mikhmon_parse_money_amount($value) {
    $v = str_replace([' ', ','], ['', '.'], trim($value));
    return floatval($v);
}

/**
 * Formate un montant avec la logique historique de devise Mikhmon.
 */
function mikhmon_format_money_amount($amount, $currency, $cekindo) {
    $indoCurrencies = array();
    if (isset($cekindo['indo']) && is_array($cekindo['indo'])) {
        $indoCurrencies = $cekindo['indo'];
    }
    if (in_array($currency, $indoCurrencies)) {
        return number_format((float) $amount, 0, ',', '.');
    }
    return number_format((float) $amount, 2);
}

/**
 * Parse le header :put du on-login Mikhmon sans casser les profils externes.
 */
function mikhmon_parse_profile_onlogin($onlogin) {
    $parts = explode(',', (string) $onlogin);
    $expmode = isset($parts[1]) ? trim($parts[1]) : '0';
    if ($expmode === '') {
        $expmode = '0';
    }

    $labels = array(
        'rem' => 'Remove',
        'ntf' => 'Notice',
        'remc' => 'Remove & Record',
        'ntfc' => 'Notice & Record',
        '0' => 'None',
    );
    if (!isset($labels[$expmode])) {
        $expmode = '0';
    }

    $price = isset($parts[2]) ? trim($parts[2]) : '';
    $validity = isset($parts[3]) ? trim($parts[3]) : '';
    $sellingPrice = isset($parts[4]) ? trim($parts[4]) : '';
    $lock = isset($parts[6]) ? trim($parts[6]) : '';

    if ($price === '0') {
        $price = '';
    }
    if ($sellingPrice === '0') {
        $sellingPrice = '';
    }
    if ($lock === '') {
        $lock = 'Disable';
    }

    return array(
        'expmode' => $expmode,
        'expmode_label' => $labels[$expmode],
        'price' => $price,
        'selling_price' => $sellingPrice,
        'validity' => $validity,
        'lock' => $lock,
    );
}

/**
 * Force un timeout de lecture suffisant sur les connexions RouterOS.
 */
function mikhmon_api_prepare($API, $timeout = 15) {
    if (is_object($API)) {
        $API->timeout = $timeout;
        if (isset($API->socket) && is_resource($API->socket)) {
            @socket_set_timeout($API->socket, $timeout);
        }
    }
}

/**
 * Lit une liste RouterOS et réessaie si count-only indique une réponse tronquée.
 */
function mikhmon_routeros_print_all($API, $command, $query = array(), $iphost = '', $userhost = '', $passwdhost = '') {
    mikhmon_api_prepare($API);
    $expected = '';
    if (empty($query) && is_object($API) && method_exists($API, 'comm')) {
        $expected = $API->comm($command, array('count-only' => ''));
    }

    $rows = array();
    if (is_object($API) && method_exists($API, 'comm')) {
        $rows = $API->comm($command, $query);
    }

    if (empty($query) && is_numeric($expected) && count($rows) < (int) $expected && class_exists('RouterosAPI') && $iphost !== '' && $userhost !== '') {
        $retry = new RouterosAPI();
        $retry->debug = false;
        $retry->timeout = 30;
        $password = function_exists('decrypt') ? decrypt($passwdhost) : $passwdhost;
        if ($retry->connect($iphost, $userhost, $password)) {
            $retryRows = $retry->comm($command, $query);
            if (count($retryRows) >= count($rows)) {
                $rows = $retryRows;
            }
            $retry->disconnect();
        }
    }

    return is_array($rows) ? $rows : array();
}

/**
 * Charge tous les profils Hotspot, même sur RouterOS v7/API lente.
 */
function mikhmon_get_hotspot_user_profiles($API, $iphost = '', $userhost = '', $passwdhost = '') {
    return mikhmon_routeros_print_all($API, '/ip/hotspot/user/profile/print', array(), $iphost, $userhost, $passwdhost);
}

/**
 * Extrait les dates start/end du commentaire d'un user hotspot.
 * Formats supportés :
 *   YYYY-MM-DD HH:MM:SS (v7)
 *   MMM/DD/YYYY HH:MM:SS (v6 legacy)
 */
function mikhmon_parse_user_comment_dates($comment) {
    $result = ['start' => null, 'end' => null, 'raw' => $comment];
    if (empty($comment)) return $result;

    // Format ISO dans le commentaire : YYYY-MM-DD HH:MM:SS
    if (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $comment, $m)) {
        $result['end'] = $m[1];
    }
    // Format legacy dans le commentaire : MMM/DD/YYYY HH:MM:SS
    elseif (preg_match('/([a-zA-Z]{3}\/\d{1,2}\/\d{4}\s+\d{2}:\d{2}:\d{2})/', $comment, $m)) {
        $result['end'] = $m[1];
    }
    return $result;
}

/**
 * Récupère les dates start/end via l'API RouterOS pour un user donné.
 * Cherche dans le commentaire (stocké par on-login) et retourne un tableau [startStr, endStr].
 */
function mikhmon_get_user_lifetime_dates($API, $username) {
    $result = ['start' => '-', 'end' => '-'];
    $users = $API->comm('/ip/hotspot/user/print', ['?name' => $username]);
    if (empty($users)) return $result;
    $comment = isset($users[0]['comment']) ? $users[0]['comment'] : '';
    $dates = mikhmon_parse_user_comment_dates($comment);
    if ($dates['end']) {
        $result['end'] = $dates['end'];
    }
    return $result;
}

/**
 * Script RouterOS on-login v7-safe (string PHP à injecter dans le profil).
 * Génère un bloc normalisé MMM/DD/YYYY pour la compatibilité des rapports.
 */
function mikhmon_routeros_onlogin_script($validity, $price, $sprice, $profileName, $lock, $expmode) {
    $recordLine = '';
    if ($expmode == 'remc' || $expmode == 'ntfc') {
        $recordLine = '; :local mac $"mac-address"; :local time [/system clock get time]; :local date [/system clock get date]; :local month ""; :local day ""; :local year ""; :local montharray ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"); :if ([:pick $date 4] = "-") do={ :set year [:pick $date 0 4]; :set month [:pick $date 5 7]; :set day [:pick $date 8 10]; :local mnum [:tonum $month]; :set month [:pick $montharray ($mnum - 1)]; } else={ :set month [:pick $date 0 3]; :set day [:pick $date 4 6]; :set year [:pick $date 7 11]; }; :local datestr ("$month/$day/$year"); :local monthstr $month; :local owner ("$monthstr$year"); /system script add name="$datestr-|-$time-|-$user-|-'.$price.'-|-$address-|-$mac-|-' . $validity . '-|-'.$profileName.'-|-$comment" owner="$owner" source="$datestr" comment="mikhmon"';
    }

    $onlogin = ':put (",'.$expmode.',' . $price . ',' . $validity . ','.$sprice.',,' . $lock . ',,"); {:local comment [ /ip hotspot user get [/ip hotspot user find where name="$user"] comment]; :local ucode [:pick $comment 0 2]; :if ($ucode = "vc" or $ucode = "up" or $comment = "") do={ :local date [/system clock get date]; :local time [/system clock get time]; :local month ""; :local day ""; :local year ""; :local montharray ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"); :if ([:pick $date 4] = "-") do={ :set year [:pick $date 0 4]; :set month [:pick $date 5 7]; :set day [:pick $date 8 10]; :local mnum [:tonum $month]; :set month [:pick $montharray ($mnum - 1)]; } else={ :set month [:pick $date 0 3]; :set day [:pick $date 4 6]; :set year [:pick $date 7 11]; }; :local datestr ("$month/$day/$year"); :local monthstr $month; :local owner ("$monthstr$year"); :local v "' . $validity . '"; :if ([:len $v] > 0) do={ :if ([/system scheduler find where name="$user"] = "") do={ /sys sch add name="$user" disable=no start-date=$date start-time=$time interval=$v; :delay 5s; :local exp [/sys sch get [/sys sch find where name="$user"] next-run]; :local explen [:len $exp]; :if ($explen = 8) do={ /ip hotspot user set comment="$datestr $exp" [find where name="$user"]; }; :if ($explen >= 15) do={ /ip hotspot user set comment="$exp" [find where name="$user"]; }; :delay 2s; /sys sch remove [find where name="$user"]; }; };';

    $onlogin .= $recordLine;

    if ($expmode == 'rem' || $expmode == 'remc') {
        $onlogin .= '}}';
    } elseif ($expmode == 'ntf' || $expmode == 'ntfc') {
        $onlogin .= '}}';
    } elseif ($expmode == '0' && $price != '') {
        $onlogin = ':put (",,' . $price . ',,,noexp,' . $lock . ',,")';
    } else {
        $onlogin = '';
    }
    return $onlogin;
}

/**
 * Script RouterOS bgservice (scheduler de monitoring) v7-safe.
 */
function mikhmon_routeros_bgservice_script($profileName, $mode) {
    return ':local dateint do={:local d $1; :if ([:len $d] >= 10) do={ :if ([:pick $d 4] = "-") do={ :return [:tonum ([:pick $d 0 4] . [:pick $d 5 7] . [:pick $d 8 10])]; } else={ :if ([:pick $d 3] = "/") do={ :local montharray ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"); :local month [:pick $d 0 3]; :local days [:pick $d 4 6]; :local year [:pick $d 7 11]; :local monthint ([ :find $montharray $month]); :local m ($monthint + 1); :if ([:len $m] = 1) do={ :set m ("0" . $m); }; :return [:tonum ("$year$m$days")]; }; }; }; :return 0; }; :local timeint do={ :local t $1; :if ([:len $t] >= 5) do={ :return (([:pick $t 0 2] * 60) + [:pick $t 3 5]); }; :return 0; }; :local date [/system clock get date]; :local time [/system clock get time]; :local month ""; :local day ""; :local year ""; :local montharray ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"); :if ([:pick $date 4] = "-") do={ :set year [:pick $date 0 4]; :set month [:pick $date 5 7]; :set day [:pick $date 8 10]; :local mnum [:tonum $month]; :set month [:pick $montharray ($mnum - 1)]; } else={ :set month [:pick $date 0 3]; :set day [:pick $date 4 6]; :set year [:pick $date 7 11]; }; :local datestr ("$month/$day/$year"); :local today [$dateint d=$datestr]; :local curtime [$timeint t=$time]; :foreach i in [ /ip hotspot user find where profile="'.$profileName.'" ] do={ :local comment [ /ip hotspot user get $i comment]; :local name [ /ip hotspot user get $i name]; :local gettime ""; :local expd 0; :local expt 0; :if ([:len $comment] >= 19) do={ :if (([:pick $comment 4] = "-") and ([:pick $comment 7] = "-") and ([:pick $comment 10] = " ")) do={ :set expd [$dateint d=$comment]; :set gettime [:pick $comment 11 19]; :set expt [$timeint t=$gettime]; } else={ :if (([:pick $comment 3] = "/") and ([:pick $comment 6] = "/")) do={ :set expd [$dateint d=$comment]; :set gettime [:pick $comment 12 20]; :set expt [$timeint t=$gettime]; }; }; }; :if ($expd > 0) do={ :if (($expd < $today) or ($expd = $today and $expt < $curtime)) do={ [ /ip hotspot user '.$mode.' $i ]; [ /ip hotspot active remove [find where user=$name] ]; }; }; };';
}
