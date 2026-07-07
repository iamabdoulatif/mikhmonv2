<?php
session_start();
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  $getsecrets = $API->comm("/ppp/secret/print");
  $TotalReg = count($getsecrets);
  $countsecret = $API->comm("/ppp/secret/print", array("count-only" => ""));
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
  <h3><i class="fa fa-key"></i> <?= $_ppp_secrets; ?>
    &nbsp; | &nbsp; <a href="./?ppp=addsecret&session=<?= $session; ?>"><i class="fa fa-user-plus"></i> <?= $_add; ?></a>
  </h3>
</div>
<div class="card-body">
<div class="overflow box-bordered" style="max-height:75vh">
<table id="tFilter" class="table table-bordered table-hover text-nowrap">
  <thead>
    <tr>
      <th class="text-center"><?php echo ($countsecret < 2) ? "$countsecret item" : "$countsecret items"; ?></th>
      <th><?= $_name; ?></th>
      <th>Service</th>
      <th><?= $_profile; ?></th>
      <th>Local Address</th>
      <th>Remote Address</th>
      <th>Last Logged Out</th>
      <th><?= $_comment; ?></th>
    </tr>
  </thead>
  <tbody>
<?php
for ($i = 0; $i < $TotalReg; $i++) {
  $secret = $getsecrets[$i];
  $id = isset($secret['.id']) ? $secret['.id'] : '';
  $name = isset($secret['name']) ? $secret['name'] : '';
  $disabled = isset($secret['disabled']) ? $secret['disabled'] : 'false';
  $service = isset($secret['service']) ? $secret['service'] : '';
  $profile = isset($secret['profile']) ? $secret['profile'] : '';
  $local = isset($secret['local-address']) ? $secret['local-address'] : '';
  $remote = isset($secret['remote-address']) ? $secret['remote-address'] : '';
  $last = isset($secret['last-logged-out']) ? $secret['last-logged-out'] : '';
  $comment = isset($secret['comment']) ? $secret['comment'] : '';
  $toggleUrl = ($disabled == 'true') ? "./?enable-pppsecret=" . urlencode($id) . "&session=" . $session : "./?disable-pppsecret=" . urlencode($id) . "&session=" . $session;
  $toggleIcon = ($disabled == 'true') ? 'fa-check-square text-green' : 'fa-minus-square text-warning';
  echo "<tr>";
  echo "<td class='text-center'><i class='fa fa-trash text-danger pointer' onclick=\"if(confirm('Delete PPP secret " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "?')){loadpage('./?remove-pppsecret=" . urlencode($id) . "&session=" . $session . "')}\" title='Remove'></i>&nbsp;&nbsp;<i class='fa " . $toggleIcon . " pointer' onclick=\"loadpage('" . $toggleUrl . "')\" title='Enable/Disable'></i></td>";
  echo "<td><a href='./?secret=" . urlencode($id) . "&session=" . $session . "'><i class='fa fa-edit'></i> " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</a></td>";
  echo "<td>" . htmlspecialchars($service, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($profile, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($local, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($remote, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($last, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') . "</td>";
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
