<?php
session_start();
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  $profileid = $_GET['profile'];
  $getprofile = $API->comm("/ppp/profile/print", array("?.id" => "$profileid"));
  if (empty($getprofile)) {
    $getprofile = $API->comm("/ppp/profile/print", array("?name" => "$profileid"));
  }
  $profile = isset($getprofile[0]) ? $getprofile[0] : array();
  $pid = isset($profile['.id']) ? $profile['.id'] : $profileid;

  if (isset($_POST['name'])) {
    $API->comm("/ppp/profile/set", array(
      ".id" => "$pid",
      "name" => preg_replace('/\s+/', '-', $_POST['name']),
      "local-address" => $_POST['localaddress'],
      "remote-address" => $_POST['remoteaddress'],
      "rate-limit" => $_POST['ratelimit'],
      "dns-server" => $_POST['dnsserver'],
      "only-one" => $_POST['onlyone'],
      "comment" => $_POST['comment'],
    ));
    echo "<script>window.location='./?ppp=profiles&session=" . $session . "'</script>";
  }
}
?>
<div class="row">
<div class="col-8">
<div class="card box-bordered">
<div class="card-header"><h3><i class="fa fa-edit"></i> <?= $_edit; ?> <?= $_ppp_profiles; ?></h3></div>
<div class="card-body">
<form autocomplete="off" method="post" action="">
  <div>
    <a class="btn bg-warning" href="./?ppp=profiles&session=<?= $session; ?>"><i class="fa fa-close"></i> <?= $_close; ?></a>
    <button type="submit" class="btn bg-primary"><i class="fa fa-save"></i> <?= $_save; ?></button>
  </div>
  <table class="table">
    <tr><td><?= $_name; ?></td><td><input class="form-control" name="name" value="<?= htmlspecialchars(isset($profile['name']) ? $profile['name'] : '', ENT_QUOTES, 'UTF-8'); ?>" required="1" autofocus></td></tr>
    <tr><td>Local Address</td><td><input class="form-control" name="localaddress" value="<?= htmlspecialchars(isset($profile['local-address']) ? $profile['local-address'] : '', ENT_QUOTES, 'UTF-8'); ?>"></td></tr>
    <tr><td>Remote Address / Pool</td><td><input class="form-control" name="remoteaddress" value="<?= htmlspecialchars(isset($profile['remote-address']) ? $profile['remote-address'] : '', ENT_QUOTES, 'UTF-8'); ?>"></td></tr>
    <tr><td>Rate Limit</td><td><input class="form-control" name="ratelimit" value="<?= htmlspecialchars(isset($profile['rate-limit']) ? $profile['rate-limit'] : '', ENT_QUOTES, 'UTF-8'); ?>"></td></tr>
    <tr><td>DNS Server</td><td><input class="form-control" name="dnsserver" value="<?= htmlspecialchars(isset($profile['dns-server']) ? $profile['dns-server'] : '', ENT_QUOTES, 'UTF-8'); ?>"></td></tr>
    <tr><td>Only One</td><td><select class="form-control" name="onlyone"><option><?= htmlspecialchars(isset($profile['only-one']) ? $profile['only-one'] : 'default', ENT_QUOTES, 'UTF-8'); ?></option><option>default</option><option>yes</option><option>no</option></select></td></tr>
    <tr><td><?= $_comment; ?></td><td><input class="form-control" name="comment" value="<?= htmlspecialchars(isset($profile['comment']) ? $profile['comment'] : '', ENT_QUOTES, 'UTF-8'); ?>"></td></tr>
  </table>
</form>
</div>
</div>
</div>
</div>
