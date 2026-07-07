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

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
	echo '
<html>
<head><title>403 Forbidden</title></head>
<body bgcolor="white">
<center><h1>403 Forbidden</h1></center>
<hr><center>nginx/1.14.0</center>
</body>
</html>
';
} else {

	include_once(__DIR__ . '/../include/mikhmon_compat.php');

// get user profile
	$getprofile = mikhmon_get_hotspot_user_profiles($API, $iphost, $userhost, $passwdhost);
	$TotalReg = count($getprofile);
// count user profile
	$countprofile = $TotalReg;
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
    <h3><i class=" fa fa-pie-chart"></i> User Profile 
    &nbsp; | &nbsp; <a href="./?user-profile=add&session=<?= $session; ?>" title="Add User"><i class="fa fa-user-plus"></i> Add</a>
    &nbsp; | &nbsp; <a href="./?repair-profiles=1&session=<?= $session; ?>" onclick="return confirm('Repair all profiles with v7-safe scripts?')" title="Repair All Profiles"><i class="fa fa-wrench"></i> Repair All</a>
	</h3>
</div>
<!-- /.card-header -->
<div class="card-body">
<div class="overflow box-bordered" style="max-height: 75vh"> 			   
<table id="tFilter" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr> 
		<th style="min-width:50px;" class="text-center" >
		<?php
	if ($countprofile < 2) {
		echo "$countprofile item  ";
	} elseif ($countprofile > 1) {
		echo "$countprofile items   ";
	}
	?></th>
		<th class="align-middle"><?= $_name ?></th>
		<th class="align-middle">Shared<br>Users</th>
		<th class="align-middle">Rate<br>Limit</th>
		<th class="align-middle"><?= $_expired_mode ?></th>
		<th class="align-middle"><?= $_validity ?></th>
		<th class="text-right align-middle" > <?= $_price." ".$currency; ?></th>
		<th class="text-right align-middle" > <?= $_selling_price." ".$currency; ?></th>
		<th class="align-middle"><?= $_lock_user ?></th>
    </tr>
  </thead>
  <tbody>
<?php

for ($i = 0; $i < $TotalReg; $i++) {

	$profiledetalis = $getprofile[$i];
	$pid = isset($profiledetalis['.id']) ? $profiledetalis['.id'] : '';
	$pname = isset($profiledetalis['name']) ? $profiledetalis['name'] : '';
	$psharedu = isset($profiledetalis['shared-users']) ? $profiledetalis['shared-users'] : '';
	$pratelimit = isset($profiledetalis['rate-limit']) ? $profiledetalis['rate-limit'] : '';
	$ponlogin = isset($profiledetalis['on-login']) ? $profiledetalis['on-login'] : '';
	$profileOnlogin = mikhmon_parse_profile_onlogin($ponlogin);
	$getmonexpired = $API->comm("/system/scheduler/print", array(
    "?name" => "$pname",
  ));
  $monexpired = isset($getmonexpired[0]) ? $getmonexpired[0] : array();
  $monid = isset($monexpired['.id']) ? $monexpired['.id'] : '';
	$pmon = isset($monexpired['name']) ? $monexpired['name'] : '';
	$chkpmon = isset($monexpired['disabled']) ? $monexpired['disabled'] : 'true';
	if(empty($pmon) || $chkpmon == "true"){$moncolor = "text-orange";}else{$moncolor = "text-green";}
	echo "<tr>";
	?>
  <td style='text-align:center;'><i class='fa fa-minus-square text-danger pointer' onclick="if(confirm('Are you sure to delete profile (<?= $pname; ?>)?')){loadpage('./?remove-user-profile=<?= $pid; ?>&pname=<?= $pname ?>&session=<?= $session; ?>')}else{}" title='Remove <?= $pname; ?>'></i>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
  <?php
	echo "<a title='Open User by profile " . $pname . "'  href='./?hotspot=users&profile=" . $pname . "&session=" . $session . "'><i class='fa fa-users'></i></a></td>";
	echo "<td><a title='Open User Profile " . $pname . "' href='./?user-profile=" . $pid . "&session=" . $session . "'><i class='fa fa-edit'></i> <i class='fa fa-ci fa-circle ".$moncolor."'></i> $pname</a></td>";
//$profiledetalis = $ARRAY[$i];echo "<td>" . $profiledetalis['name'];echo "</td>";
	echo "<td>" . $psharedu;
	echo "</td>";
	echo "<td>" . $pratelimit;
	echo "</td>";

	echo "<td>";
	echo $profileOnlogin['expmode_label'];
	echo "</td>";
	echo "<td>";
	echo $profileOnlogin['validity'];

	echo "</td>";

	echo "<td style='text-align:right;'>";
	$price = $profileOnlogin['price'];
	if ($price == "" || $price == "0") {
		echo "";
	} else {
		echo mikhmon_format_money_amount($price, $currency, $cekindo);
	}

	echo "</td>";
	echo "<td style='text-align:right;'>";
	$price = $profileOnlogin['selling_price'];
	if ($price == "" || $price == "0") {
		echo "";
	} else {
		echo mikhmon_format_money_amount($price, $currency, $cekindo);
	}

	echo "</td>";
	echo "<td>";

	echo $profileOnlogin['lock'];
	echo "</td>";
	echo "</tr>";
}
?>
  </tbody>
</table>
</div>
</div>
</div>
</div>
</div>
