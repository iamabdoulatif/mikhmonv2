<?php
session_start();
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  $profiles = $API->comm("/ppp/profile/print");
  if (isset($_POST['name'])) {
    $API->comm("/ppp/secret/add", array(
      "name" => preg_replace('/\s+/', '-', $_POST['name']),
      "password" => $_POST['password'],
      "service" => $_POST['service'],
      "profile" => $_POST['profile'],
      "local-address" => $_POST['localaddress'],
      "remote-address" => $_POST['remoteaddress'],
      "comment" => $_POST['comment'],
      "disabled" => "no",
    ));
    echo "<script>window.location='./?ppp=secrets&session=" . $session . "'</script>";
  }
}
?>
<div class="row">
<div class="col-8">
<div class="card box-bordered">
<div class="card-header"><h3><i class="fa fa-user-plus"></i> <?= $_add; ?> <?= $_ppp_secrets; ?></h3></div>
<div class="card-body">
<form autocomplete="off" method="post" action="">
  <div>
    <a class="btn bg-warning" href="./?ppp=secrets&session=<?= $session; ?>"><i class="fa fa-close"></i> <?= $_close; ?></a>
    <button type="submit" class="btn bg-primary"><i class="fa fa-save"></i> <?= $_save; ?></button>
  </div>
  <table class="table">
    <tr><td><?= $_name; ?></td><td><input class="form-control" name="name" required="1" autofocus></td></tr>
    <tr><td>Password</td><td><input class="form-control" name="password" required="1"></td></tr>
    <tr><td>Service</td><td><select class="form-control" name="service"><option>pppoe</option><option>any</option><option>pptp</option><option>l2tp</option><option>sstp</option><option>ovpn</option></select></td></tr>
    <tr><td><?= $_profile; ?></td><td><select class="form-control" name="profile"><?php foreach ($profiles as $profile) { echo "<option>" . htmlspecialchars($profile['name'], ENT_QUOTES, 'UTF-8') . "</option>"; } ?></select></td></tr>
    <tr><td>Local Address</td><td><input class="form-control" name="localaddress"></td></tr>
    <tr><td>Remote Address</td><td><input class="form-control" name="remoteaddress"></td></tr>
    <tr><td><?= $_comment; ?></td><td><input class="form-control" name="comment"></td></tr>
  </table>
</form>
</div>
</div>
</div>
</div>
