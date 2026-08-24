<?php
declare(strict_types=1);

require_once __DIR__.'/common.php';
require_once __DIR__.'/auth.php';

function page_start(string $title, string $active='dashboard'): void {
    $u = auth_user();
    $app = require dirname(__DIR__,2).'/config/app.php';
    $base = $app['base_url'];
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#071426">
<title><?=h($title)?> - <?=h($app['name'])?></title>
<link rel="stylesheet" href="<?=$base?>/assets/css/app.css">
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="brandmark">BST</div>
    <div><strong>BST Access Manager</strong><span><?=h($app['institution'])?></span></div>
  </div>
  <nav>
    <a class="<?=$active==='dashboard'?'active':''?>" href="<?=$base?>/index.php">Dashboard</a>
    <a class="<?=$active==='solicitacoes'?'active':''?>" href="<?=$base?>/solicitacoes.php">Solicitações</a>
    <a class="<?=$active==='usuarios'?'active':''?>" href="<?=$base?>/usuarios.php">Usuários</a>
    <a class="<?=$active==='turmas'?'active':''?>" href="<?=$base?>/turmas.php">Turmas e Internet</a>
    <a class="<?=$active==='sites'?'active':''?>" href="<?=$base?>/sites.php">Bloqueio de Sites</a>
    <a class="disabled" href="#" title="Disponível na V1.1">Hotspot <small>V1.1</small></a>
    <a class="<?=$active==='online'?'active':''?>"
   href="<?=$base?>/online.php">
    Usuários Online
</a>
    <a class="disabled" href="#" title="Disponível na V1.1">VLANs <small>V1.1</small></a>
    <a class="disabled" href="#" title="Disponível na V1.2">Relatórios <small>V1.2</small></a>
    <a class="<?=$active==='logs'?'active':''?>" href="<?=$base?>/logs.php">Logs</a>
<a class="<?=$active==='agenda'?'active':''?>"
   href="<?=$base?>/agenda.php">
    Agenda das Turmas
</a>

<?php if (auth_has_role(['admin'])): ?>

<a class="<?=$active==='admins'?'active':''?>"
   href="<?=$base?>/admins.php">
    Usuários do Portal
</a>

<?php endif; ?>
  </nav>
  <div class="sidebar-bottom">
    <div class="userbox"><b><?=h($u['nome'])?></b><span><?=h($u['email'])?></span></div>
    <a class="logout" href="<?=$base?>/logout.php">Sair</a>
  </div>
</aside>
<section class="main">
<header class="topbar">
  <button class="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
  <div><h1><?=h($title)?></h1><p><?=h($app['institution'])?></p></div>
  <div class="status-dot"><i></i> Sistema ativo</div>
</header>
<main class="content">
<?php
$f = flash_get();
if ($f): ?>
<div class="flash <?=h($f['type'])?>"><?=h($f['message'])?></div>
<?php endif;
}

function page_end(): void { ?>
</main></section></div>
<script src="assets/js/app.js"></script>
</body></html>
<?php }
