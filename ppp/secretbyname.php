<?php
session_start();
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  $sid = $secretbyname;
  $getsecret = $API->comm("/ppp/secret/print", array("?.id" => "$sid"));
  if (empty($getsecret)) {
    $getsecret = $API->comm("/ppp/secret/print", array("?name" => "$sid"));
  }
  $secret = isset($getsecret[0]) ? $getsecret[0] : array();
  $secretid = isset($secret['.id']) ? $secret['.id'] : $sid;
  $profiles = $API->comm("/ppp/profile/print");

  if (isset($_POST['name'])) {
    $payload = array(
      ".id" => "$secretid",
      "name" => preg_replace('/\s+/', '-', $_POST['name']),
      "service" => $_POST['service'],
      "profile" => $_POST['profile'],
      "local-address" => $_POST['localaddress'],
      "remote-address" => $_POST['remoteaddress'],
      "comment" => $_POST['comment'],
    );
    if ($_POST['password'] !== '') {
      $payload['password'] = $_POST['password'];
    }
    $API->comm("/ppp/secret/set", $payload);
    echo "<script>window.location='./?ppp=secrets&session=" . $session . "'</script>";
  }
}
?>
<div class="row">
<div class="col-8">
<div class="card box-bordered">
<div class="card-header"><h3><i class="fa fa-edit"></i> <?= $_edit; ?> <?= $_ppp_secrets; ?></h3></div>
<div class="card-body">
<form autocomplete="off" method="post" action="">
  <div>
    <a class="btn bg-warning" href="./?ppp=secrets&session=<?= $session; ?>"><i class="fa fa-close"></i> <?= $_close; ?></a>
    <button type="submit" class="btn bg-primary"><i class="fa fa-save"></i> <?= $_save; ?></button>
  </div>
  <table class="table">
    <tr><td><?= $_name; ?></td><td><input class="form-control" name="name" value="<?= htmlspecialchars(isset($secret['name']) ? $secret['name'] : '', ENT_QUOTES, 'UTF-8'); ?>" required="1" autofocus></td></tr>
    <tr><td>Password</td><td><input class="form-control" name="password" placeholder="Leave blank to keep current password"></td></tr>
    <tr><td>Service</td><td><select class="form-control" name="service"><option><?= htmlspecialchars(isset($secret['service']) ? $secret['service'] : 'pppoe', ENT_QUOTES, 'UTF-8'); ?></option><option>pppoe</option><option>any</option><option>pptp</option><option>l2tp</option><option>sstp</option><option>ovpn</option></select></td></tr>
    <tr><td><?= $_profile; ?></td><td><select class="form-control" name="profile"><option><?= htmlspecialchars(isset($secret['profile']) ? $secret['profile'] : '', ENT_QUOTES, 'UTF-8'); ?></option><?php foreach ($profiles as $profile) { echo "<option>" . htmlspecialchars($profile['name'], ENT_QUOTES, 'UTF-8') . "</option>"; } ?></select></td></tr>
    <tr><td>Local Address</td><td><input class="form-control" name="localaddress" value="<?= htmlspecialchars(isset($secret['local-address']) ? $secret['local-address'] : '', ENT_QUOTES, 'UTF-8'); ?>"></td></tr>
    <tr><td>Remote Address</td><td><input class="form-control" name="remoteaddress" value="<?= htmlspecialchars(isset($secret['remote-address']) ? $secret['remote-address'] : '', ENT_QUOTES, 'UTF-8'); ?>"></td></tr>
    <tr><td><?= $_comment; ?></td><td><input class="form-control" name="comment" value="<?= htmlspecialchars(isset($secret['comment']) ? $secret['comment'] : '', ENT_QUOTES, 'UTF-8'); ?>"></td></tr>
  </table>
</form>
</div>
</div>
</div>
</div>
