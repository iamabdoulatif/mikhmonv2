<?php
/*
 * Info / support footer shared by admin.php and index.php.
 * This file was referenced by admin.php and include/menu.php but missing
 * from the repo, leaving silently failing include() calls.
 */
if (substr($_SERVER["REQUEST_URI"], -8) == "info.php") { header("Location:./"); }
?>
<div class="text-center" style="padding:8px 0; font-size:12px; opacity:.75;">
  SafelinkHub &middot; Support : <a href="tel:+2250709100552">+225 07 09 10 05 52</a>
</div>
