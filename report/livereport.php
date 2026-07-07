<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
// load session MikroTik
  $session = $_GET['session'];
// set  timezone
date_default_timezone_set($_SESSION['timezone']);

// lang
include('../include/lang.php');
include('../lang/'.$langid.'.php');


// load config
  include('../include/configload.php');
  include('../include/readcfg.php');

// routeros api
  include_once('../lib/routeros_api.class.php');
  include_once('../lib/formatbytesbites.php');
  include_once('../include/mikhmon_compat.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $API->timeout = 15;
  $API->connect($iphost, $userhost, decrypt($passwdhost));

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
    $getclock = $API->comm("/system/clock/print");
    $clock = isset($getclock[0]) ? $getclock[0] : array("date" => date("M/d/Y"));
    $idhr = mikhmon_date_to_legacy($clock['date']);
    $idbl = mikhmon_sale_owner_from_source($idhr);
    $_SESSION[$session.'idhr'] = $idhr;
    mikhmon_get_sale_scripts($API, $session, array(
      "?owner" => "$idbl",
    ));
    $todaySummary = mikhmon_sales_summary($session, $idbl, $idhr);
    $monthSummary = mikhmon_sales_summary($session, $idbl);
    $TotalRHr = $todaySummary['count'];
    $TotalRBl = $monthSummary['count'];
    $_SESSION[$session.'totalBl'] = $TotalRBl;
    $_SESSION[$session.'totalHr'] = $TotalRHr;
    $tHr = $todaySummary['total'];
    $tBl = $monthSummary['total'];
  }
}
?>

            <div id="r_4" class="row">
              <div <?= $lreport; ?> class="box bmh-75 box-bordered">
                <div class="box-group">
                  <div class="box-group-icon"><i class="fa fa-money"></i></div>
                    <div class="box-group-area">
                      <span >
                        <div id="reloadLreport">
                        <?php 
                          $dincome = mikhmon_format_money_amount($tHr, $currency, $cekindo);
                          $mincome = mikhmon_format_money_amount($tBl, $currency, $cekindo);
                          $_SESSION[$session.'dincome'] = $dincome;
                          $_SESSION[$session.'mincome'] = $mincome;
                            echo $_income."<br/>" . "
                          ".$_today." " . $TotalRHr . "vcr : " . $currency . " " . $dincome . "<br/>
                          ".$_this_month." " . $TotalRBl . "vcr : " . $currency . " " . $mincome;
                          ?>
                        </div>
                    </span>
                </div>
              </div>
            </div>
            </div>
