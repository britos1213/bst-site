<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/app/helpers/auth.php';
require_once dirname(__DIR__).'/app/helpers/common.php';

if(!empty($_SESSION['admin_id'])){header('Location:index.php');exit;}

$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $email=trim((string)($_POST['email']??''));
    $senha=(string)($_POST['senha']??'');
    $st=db()->prepare("SELECT * FROM administradores WHERE email=? AND ativo=1 LIMIT 1");
    $st->execute([$email]);
    $a=$st->fetch();
    if($a && password_verify($senha,$a['senha_hash'])){
        auth_login($a);
        db()->prepare("UPDATE administradores SET ultimo_login=NOW() WHERE id=?")->execute([$a['id']]);
        log_action(db(),(int)$a['id'],'login','Login administrativo');
        header('Location:index.php');exit;
    }
    $error='E-mail ou senha inválidos.';
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Entrar - BST Access Manager</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="login-page">
<div class="login-card">
  <div class="brandmark">BST</div>
  <h1>BST Access Manager</h1>
  <p>CEPMG ST. PALMITO • Administração do Hotspot</p>
  <?php if($error): ?><div class="flash err"><?=h($error)?></div><?php endif; ?>
  <form method="post">
    <label>E-mail</label><input type="email" name="email" required autofocus>
    <label style="margin-top:13px">Senha</label><input type="password" name="senha" required>
    <button class="btn btn-gold" type="submit">ENTRAR NO PAINEL</button>
  </form>
</div>
</body></html>
