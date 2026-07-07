<?php
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
    exit;
}

include_once('../include/mikhmon_compat.php');

$profiles = mikhmon_get_hotspot_user_profiles($API, $iphost, $userhost, $passwdhost);
$count = count($profiles);
$repaired = 0;

foreach ($profiles as $prof) {
    $pid = $prof['.id'];
    $pname = $prof['name'];
    $ponlogin = isset($prof['on-login']) ? $prof['on-login'] : '';

    if (empty($ponlogin)) {
        continue;
    }

    // Parse the :put (",...") header to extract original settings
    $expmode = '0';
    $price = '0';
    $validity = '';
    $sprice = '0';
    $lock = 'Disable';
    $addrpool = isset($prof['address-pool']) ? $prof['address-pool'] : 'none';
    $ratelimit = isset($prof['rate-limit']) ? $prof['rate-limit'] : '';
    $sharedusers = isset($prof['shared-users']) ? $prof['shared-users'] : '1';
    $parent = isset($prof['parent-queue']) ? $prof['parent-queue'] : 'none';

    if (preg_match('/:put \(",((?:[^"]|"")*)"\);/', $ponlogin, $m)) {
        $csv = str_getcsv($m[1]);
        // csv[0] = expmode, [1] = price, [2] = validity, [3] = sprice, [4] = empty, [5] = lock/noexp, [6] = empty
        if (isset($csv[0]) && trim($csv[0]) !== '') {
            $expmode = trim($csv[0]);
        } elseif (isset($csv[5]) && trim($csv[5]) === 'noexp') {
            $expmode = '0';
        }
        if (isset($csv[1]) && trim($csv[1]) !== '') {
            $price = trim($csv[1]);
        }
        if (isset($csv[2]) && trim($csv[2]) !== '') {
            $validity = trim($csv[2]);
        }
        if (isset($csv[3]) && trim($csv[3]) !== '') {
            $sprice = trim($csv[3]);
        }
        if (isset($csv[5]) && trim($csv[5]) !== 'noexp' && trim($csv[5]) !== '') {
            $lock = trim($csv[5]);
        } elseif (isset($csv[6]) && trim($csv[6]) !== '') {
            $lock = trim($csv[6]);
        }
    }

    // Determine mode for bgservice
    if ($expmode == 'rem' || $expmode == 'remc') {
        $mode = 'remove';
    } elseif ($expmode == 'ntf' || $expmode == 'ntfc') {
        $mode = 'set limit-uptime=1s';
    } else {
        $mode = 'remove';
    }

    $lockStr = ($lock == 'Enable') ? '; [:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]]' : '';

    $randstarttime = "0" . rand(1, 5) . ":" . rand(10, 59) . ":" . rand(10, 59);
    $randinterval = "00:02:" . rand(10, 59);

    $newOnlogin = mikhmon_routeros_onlogin_script($validity, $price, $sprice, $pname, $lockStr, $expmode);
    $newBgservice = mikhmon_routeros_bgservice_script($pname, $mode);

    // Update profile on-login only (keep other settings intact)
    $API->comm("/ip/hotspot/user/profile/set", array(
        ".id" => "$pid",
        "on-login" => "$newOnlogin",
    ));

    // Update or create scheduler
    $getmon = $API->comm("/system/scheduler/print", array("?name" => "$pname"));
    if (!empty($getmon)) {
        $monid = $getmon[0]['.id'];
        $API->comm("/system/scheduler/set", array(
            ".id" => "$monid",
            "on-event" => "$newBgservice",
            "start-time" => "$randstarttime",
            "interval" => "$randinterval",
            "disabled" => "no",
        ));
    } elseif ($expmode != '0') {
        $API->comm("/system/scheduler/add", array(
            "name" => "$pname",
            "start-time" => "$randstarttime",
            "interval" => "$randinterval",
            "on-event" => "$newBgservice",
            "disabled" => "no",
            "comment" => "Monitor Profile $pname",
        ));
    }

    $repaired++;
}

echo "<script>alert('Repaired $repaired / $count profiles'); window.location='./?hotspot=user-profiles&session=" . $session . "'</script>";
