<?php
session_start();
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  $getactive = $API->comm("/ppp/active/print");
  $TotalReg = count($getactive);
  $countactive = $API->comm("/ppp/active/print", array("count-only" => ""));
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
  <h3><i class="fa fa-plug"></i> <?= $_ppp_active; ?> <?php echo ($countactive < 2) ? "$countactive item" : "$countactive items"; ?></h3>
</div>
<div class="card-body">
<div class="overflow box-bordered" style="max-height:75vh">
<table id="tFilter" class="table table-bordered table-hover text-nowrap">
  <thead>
    <tr>
      <th></th>
      <th><?= $_name; ?></th>
      <th>Service</th>
      <th>Caller ID</th>
      <th>Address</th>
      <th>Uptime</th>
      <th>Encoding</th>
      <th>Session ID</th>
    </tr>
  </thead>
  <tbody>
<?php
for ($i = 0; $i < $TotalReg; $i++) {
  $active = $getactive[$i];
  $id = isset($active['.id']) ? $active['.id'] : '';
  $name = isset($active['name']) ? $active['name'] : '';
  $service = isset($active['service']) ? $active['service'] : '';
  $caller = isset($active['caller-id']) ? $active['caller-id'] : '';
  $address = isset($active['address']) ? $active['address'] : '';
  $uptime = isset($active['uptime']) ? formatDTM($active['uptime']) : '';
  $encoding = isset($active['encoding']) ? $active['encoding'] : '';
  $sessionid = isset($active['session-id']) ? $active['session-id'] : '';
  echo "<tr>";
  echo "<td class='text-center'><i class='fa fa-minus-square text-danger pointer' onclick=\"if(confirm('Disconnect PPP active " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "?')){loadpage('./?remove-pactive=" . urlencode($id) . "&session=" . $session . "')}\" title='Disconnect'></i></td>";
  echo "<td>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($service, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($caller, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($uptime, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($encoding, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($sessionid, ENT_QUOTES, 'UTF-8') . "</td>";
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
