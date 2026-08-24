<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/app/helpers/common.php';

$msg='';$err='';
$count=(int)db()->query("SELECT COUNT(*) FROM administradores")->fetchColumn();
if($_SERVER['REQUEST_METHOD']==='POST' && $count===0){
    $nome=trim((string)($_POST['nome']??''));
    $email=trim((string)($_POST['email']??''));
    $senha=(string)($_POST['senha']??'');
    if($nome==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($senha)<8){
        $err='Preencha corretamente. A senha precisa ter pelo menos 8 caracteres.';
    }else{
        $st=db()->prepare("INSERT INTO administradores(nome,email,senha_hash,ativo,criado_em) VALUES(?,?,?,1,NOW())");
        $st->execute([$nome,$email,password_hash($senha,PASSWORD_DEFAULT)]);
        $msg='Administrador criado. Agora acesse login.php.';
        $count=1;
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><link rel="stylesheet" href="assets/css/app.css"><title>Instalação</title></head>
<body class="login-page"><div class="login-card"><div class="brandmark">BST</div><h1>Primeiro Administrador</h1>
<?php if($msg):?><div class="flash ok"><?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="flash err"><?=h($err)?></div><?php endif;?>
<?php if($count>0):?><p>Já existe administrador cadastrado.</p><a class="btn btn-blue" href="login.php">IR PARA LOGIN</a>
<?php else:?><form method="post"><label>Nome</label><input name="nome" required><label style="margin-top:12px">E-mail</label><input type="email" name="email" required><label style="margin-top:12px">Senha</label><input type="password" name="senha" minlength="8" required><button class="btn btn-gold" type="submit">CRIAR ADMINISTRADOR</button></form><?php endif;?>
</div></body></html>
