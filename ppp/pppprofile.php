<?php
session_start();
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  $getprofiles = $API->comm("/ppp/profile/print");
  $TotalReg = count($getprofiles);
  $countprofile = $API->comm("/ppp/profile/print", array("count-only" => ""));
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
  <h3><i class="fa fa-id-card"></i> <?= $_ppp_profiles; ?>
    &nbsp; | &nbsp; <a href="./?ppp=add-profile&session=<?= $session; ?>"><i class="fa fa-plus-square"></i> <?= $_add; ?></a>
  </h3>
</div>
<div class="card-body">
<div class="overflow box-bordered" style="max-height:75vh">
<table id="tFilter" class="table table-bordered table-hover text-nowrap">
  <thead>
    <tr>
      <th class="text-center"><?php echo ($countprofile < 2) ? "$countprofile item" : "$countprofile items"; ?></th>
      <th><?= $_name; ?></th>
      <th>Local Address</th>
      <th>Remote Address</th>
      <th>Rate Limit</th>
      <th>DNS Server</th>
      <th>Only One</th>
      <th><?= $_comment; ?></th>
    </tr>
  </thead>
  <tbody>
<?php
for ($i = 0; $i < $TotalReg; $i++) {
  $profile = $getprofiles[$i];
  $id = isset($profile['.id']) ? $profile['.id'] : '';
  $name = isset($profile['name']) ? $profile['name'] : '';
  $local = isset($profile['local-address']) ? $profile['local-address'] : '';
  $remote = isset($profile['remote-address']) ? $profile['remote-address'] : '';
  $rate = isset($profile['rate-limit']) ? $profile['rate-limit'] : '';
  $dns = isset($profile['dns-server']) ? $profile['dns-server'] : '';
  $onlyone = isset($profile['only-one']) ? $profile['only-one'] : '';
  $comment = isset($profile['comment']) ? $profile['comment'] : '';
  echo "<tr>";
  echo "<td class='text-center'><i class='fa fa-minus-square text-danger pointer' onclick=\"if(confirm('Delete PPP profile " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "?')){loadpage('./?remove-pprofile=" . urlencode($id) . "&session=" . $session . "')}\" title='Remove'></i></td>";
  echo "<td><a href='./?ppp=edit-profile&profile=" . urlencode($id) . "&session=" . $session . "'><i class='fa fa-edit'></i> " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</a></td>";
  echo "<td>" . htmlspecialchars($local, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($remote, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($rate, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($dns, ENT_QUOTES, 'UTF-8') . "</td>";
  echo "<td>" . htmlspecialchars($onlyone, ENT_QUOTES, 'UTF-8') . "</td>";
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
