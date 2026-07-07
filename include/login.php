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


?>

<style>
  /* SafelinkHub palette: amber #eab308, warm black #1c1917, cream #fff3ed */
  body{background:#1c1917!important;color:#fff3ed}
  .slh-login-shell{min-height:calc(100vh - 20px);display:flex;align-items:center;justify-content:center;padding:10px;background:radial-gradient(circle at 20% 20%,rgba(234,179,8,.14),transparent 30%),radial-gradient(circle at 80% 10%,rgba(255,243,237,.06),transparent 26%)}
  .slh-login-card{width:100%;max-width:360px;margin:0 auto;padding-top:0;overflow:hidden;border-radius:12px}
  .slh-login-card .card{background:#1c1917!important;border:1px solid rgba(234,179,8,.35)}
  .slh-login-card .card-header{margin-bottom:0;padding:14px 10px;text-align:center;text-transform:uppercase;letter-spacing:.08em;background:#eab308!important}
  .slh-login-card .card-header h3{color:#1c1917!important;margin:0}
  .slh-login-body{padding:26px 24px 22px}
  .slh-brand-logo{width:64px;height:64px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center}
  .slh-brand-logo img{max-width:64px;max-height:64px}
  .slh-brand-title{font-size:24px;font-weight:800;line-height:1.1;color:#fff3ed}
  .slh-brand-subtitle{font-size:11px;font-weight:700;letter-spacing:.16em;margin-top:5px;color:#eab308!important}
  .slh-phone{display:inline-block;margin-top:12px;font-weight:700;color:#fff3ed!important}
  .slh-role-tabs{display:grid;grid-template-columns:repeat(3,1fr);gap:0;margin:22px 0;border-radius:5px;overflow:hidden;border:1px solid rgba(234,179,8,.25)}
  .slh-role-tabs span{padding:12px 4px;text-align:center;font-weight:700;background:rgba(255,243,237,.05);color:rgba(255,243,237,.65)!important}
  .slh-role-tabs .active{border-bottom:2px solid #eab308;border-radius:0;color:#eab308!important;background:rgba(234,179,8,.12)}
  .slh-input{height:44px;margin-bottom:12px;font-size:15px}
  .slh-login-body .form-control{background:rgba(255,243,237,.07)!important;color:#fff3ed!important;border:1px solid rgba(234,179,8,.35)!important}
  .slh-login-body .form-control::placeholder{color:rgba(255,243,237,.55)}
  .slh-login-body .form-control:focus{border-color:#eab308!important;outline:none}
  .slh-login-button{width:100%;height:46px;margin-top:8px;font-size:16px;font-weight:800;background:#eab308!important;color:#1c1917!important;border:none;border-radius:4px}
  .slh-login-button:hover{filter:brightness(1.08)}
  .slh-powered{padding:18px 10px;text-align:center;font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;border-top:1px solid rgba(234,179,8,.25);color:rgba(255,243,237,.6)!important}
  @media (max-width:576px){.slh-login-shell{align-items:flex-start;padding-top:20px}.slh-login-card{max-width:340px}.slh-login-body{padding:22px 18px}}
</style>

<div class="slh-login-shell">
  <div class="login-box slh-login-card">
    <div class="card box-bordered">
      <div class="card-header bg-primary">
        <h3><?= $_please_login ?></h3>
      </div>
      <div class="card-body slh-login-body text-center">
        <div class="slh-brand-logo">
          <img src="img/logo-safelinkhub.svg" alt="Safelinkhub">
        </div>
        <div class="slh-brand-title">MIKHMON</div>
        <div class="slh-brand-subtitle text-primary">BY SAFELINKHUB</div>
        <a class="slh-phone text-secondary" href="tel:+2250709100552"><i class="fa fa-phone"></i> +225 07 09 10 05 52</a>

        <div class="slh-role-tabs">
          <span class="active text-primary"><i class="fa fa-shield"></i><br>Admin</span>
          <span class="text-secondary"><i class="fa fa-briefcase"></i><br>Gérant</span>
          <span class="text-secondary"><i class="fa fa-ticket"></i><br>Vendeur</span>
        </div>

        <form autocomplete="off" action="" method="post">
          <input class="form-control slh-input" type="text" name="user" id="_username" placeholder="Identifiant" required="1" autofocus>
          <input class="form-control slh-input" type="password" name="pass" placeholder="Mot de passe" required="1">
          <input class="btn-login bg-primary pointer slh-login-button" type="submit" name="login" value="Login">
          <div class="mr-t-10"><?= $error; ?></div>
        </form>
      </div>
      <div class="slh-powered text-secondary">Powered by Safelinkhub</div>
    </div>
  </div>
</div>
<?php
// NOTE: no </body></html> here — admin.php appends its scripts and the
// closing tags after this include; closing twice produced invalid HTML.
?>
