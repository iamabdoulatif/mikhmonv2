<?php
session_start();
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  if (isset($_POST['name'])) {
    $API->comm("/ppp/profile/add", array(
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
<div class="card-header"><h3><i class="fa fa-plus"></i> <?= $_add; ?> <?= $_ppp_profiles; ?></h3></div>
<div class="card-body">
<form autocomplete="off" method="post" action="">
  <div>
    <a class="btn bg-warning" href="./?ppp=profiles&session=<?= $session; ?>"><i class="fa fa-close"></i> <?= $_close; ?></a>
    <button type="submit" class="btn bg-primary"><i class="fa fa-save"></i> <?= $_save; ?></button>
  </div>
  <table class="table">
    <tr><td><?= $_name; ?></td><td><input class="form-control" name="name" required="1" autofocus></td></tr>
    <tr><td>Local Address</td><td><input class="form-control" name="localaddress" placeholder="ex: 10.0.0.1"></td></tr>
    <tr><td>Remote Address / Pool</td><td><input class="form-control" name="remoteaddress" placeholder="ex: pppoe-pool"></td></tr>
    <tr><td>Rate Limit</td><td><input class="form-control" name="ratelimit" placeholder="ex: 2M/5M"></td></tr>
    <tr><td>DNS Server</td><td><input class="form-control" name="dnsserver" placeholder="ex: 1.1.1.1,8.8.8.8"></td></tr>
    <tr><td>Only One</td><td><select class="form-control" name="onlyone"><option>default</option><option>yes</option><option>no</option></select></td></tr>
    <tr><td><?= $_comment; ?></td><td><input class="form-control" name="comment"></td></tr>
  </table>
</form>
</div>
</div>
</div>
</div>
