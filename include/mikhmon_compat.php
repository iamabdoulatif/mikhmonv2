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
    $owner = isset($scriptData['owner']) ? $scriptData['owner'] : '';
    if (!isset($data[$owner])) {
        $data[$owner] = [];
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
        $entries = array_filter($entries, function($e) use ($username) {
            $parts = explode('-|-', isset($e['name']) ? $e['name'] : '');
            return (isset($parts[2]) ? $parts[2] : '') !== $username;
        });
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

    $onlogin = ':put (",'.$expmode.',' . $price . ',' . $validity . ','.$sprice.',,' . $lock . ',,"); {:local comment [ /ip hotspot user get [/ip hotspot user find where name="$user"] comment]; :local ucode [:pic $comment 0 2]; :if ($ucode = "vc" or $ucode = "up" or $comment = "") do={ :local date [/system clock get date]; :local time [/system clock get time]; :local month ""; :local day ""; :local year ""; :local montharray ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"); :if ([:pick $date 4] = "-") do={ :set year [:pick $date 0 4]; :set month [:pick $date 5 7]; :set day [:pick $date 8 10]; :local mnum [:tonum $month]; :set month [:pick $montharray ($mnum - 1)]; } else={ :set month [:pick $date 0 3]; :set day [:pick $date 4 6]; :set year [:pick $date 7 11]; }; :local datestr ("$month/$day/$year"); :local monthstr $month; :local owner ("$monthstr$year"); :local v "' . $validity . '"; :if ([:len $v] > 0) do={ :if ([/system scheduler find where name="$user"] = "") do={ /sys sch add name="$user" disable=no start-date=$date start-time=$time interval=$v; :delay 5s; :local exp [/sys sch get [/sys sch find where name="$user"] next-run]; :local explen [:len $exp]; :if ($explen = 8) do={ /ip hotspot user set comment="$datestr $exp" [find where name="$user"]; }; :if ($explen >= 15) do={ /ip hotspot user set comment="$exp" [find where name="$user"]; }; :delay 2s; /sys sch remove [find where name="$user"]; }; };';

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
