<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/helpers/auth.php'; auth_require();
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/app/helpers/common.php';
require_once dirname(__DIR__).'/vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

$pdo=db();

$pend=(int)$pdo->query("SELECT COUNT(*) FROM solicitacoes WHERE status='pendente'")->fetchColumn();
$aprov=(int)$pdo->query("SELECT COUNT(*) FROM solicitacoes WHERE status='aprovada'")->fetchColumn();
$users=(int)$pdo->query("SELECT COUNT(DISTINCT cpf) FROM solicitacoes WHERE status='aprovada'")->fetchColumn();
$sites=0;
try{$sites=(int)$pdo->query("SELECT COUNT(*) FROM sites_bloqueados WHERE ativo=1")->fetchColumn();}catch(Throwable $e){}

$online=0;$cpu='—';$mem='—';$uptime='—';$routerStatus=false;$active=[];
try{
  $mt=require dirname(__DIR__).'/config/mikrotik.php';
  $client=new Client(new Config([
    'host'=>$mt['host'],'user'=>$mt['user'],'pass'=>$mt['pass'],'port'=>$mt['port'],
    'timeout'=>4,'attempts'=>1
  ]));
  $routerStatus=true;

  $active=$client->query(new Query('/ip/hotspot/active/print'))->read();
  $online=count($active);

  $res=$client->query(new Query('/system/resource/print'))->read();
  if(!empty($res[0])){
    $cpu=($res[0]['cpu-load']??'—').'%';
    $free=(int)($res[0]['free-memory']??0);
    $total=(int)($res[0]['total-memory']??0);
    if($total>0) $mem=round((1-$free/$total)*100).'%';
    $uptime=$res[0]['uptime']??'—';
  }
}catch(Throwable $e){}

$rows=$pdo->query("
SELECT
    id,
    nome,
    cpf,
    telefone,
    email,
    COALESCE(NULLIF(tipo_solicitado,''), setor) AS tipo_solicitado,
    status,
    criado_em
FROM solicitacoes
WHERE status = 'pendente'
ORDER BY criado_em DESC
LIMIT 30
")->fetchAll();




$flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
$app=require dirname(__DIR__).'/config/app.php';
$u=auth_user();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>BST Access Manager Enterprise</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="app">
<aside class="sidebar">
  <div class="brand">
    <img src="assets/img/bst-assinatura.png" alt="BST">
    <div><strong>BST Access Manager</strong><span>Enterprise • <?=h($app['institution'])?></span></div>
  </div>
  <nav>
    <a class="active" href="index.php">Dashboard</a>
    <a href="usuarios.php">Usuários</a>
    <a href="turmas.php">Turmas e Internet</a>
    <a href="solicitacoes.php">Solicitações</a>
    <a href="sites.php">Sites Bloqueados</a>
    <a href="logs.php">Logs do Sistema</a>
  </nav>
  <div class="sidebar-bottom">
    <div class="developer">
      <b>Desenvolvido por BST Soluções Tecnológicas</b><br>
      <span class="wa">WhatsApp: (62) 98493-9375</span><br>
      <span class="ig">Instagram: @britossolucoes</span>
    </div>
    <a style="display:block;margin-top:10px;color:#ff8e97;font-size:11px" href="logout.php">Sair</a>
  </div>
</aside>

<section class="main">
<header class="topbar">
  <div><h1>DASHBOARD</h1><p>Visão geral da rede em tempo real</p></div>
  <div class="status"><i></i><?=$routerStatus?'MikroTik Online':'Painel Online • MikroTik indisponível'?></div>
</header>

<main class="content">
<?php if($flash): ?><div class="flash <?=h($flash['type'])?>"><?=h($flash['message'])?></div><?php endif; ?>

<section class="cards">
<div class="card"><div class="label">Usuários Online</div><div class="value"><?=$online?></div><div class="meta">Hotspot ativo</div></div>
<div class="card"><div class="label">Usuários cadastrados</div><div class="value"><?=$users?></div><div class="meta">Aprovados</div></div>
<div class="card"><div class="label">Solicitações</div><div class="value"><?=$pend?></div><div class="meta">Pendentes</div></div>
<div class="card"><div class="label">Sites bloqueados</div><div class="value"><?=$sites?></div><div class="meta">DNS ativo</div></div>
<div class="card"><div class="label">CPU MikroTik</div><div class="value"><?=$cpu?></div><div class="meta">Carga atual</div></div>
<div class="card"><div class="label">Memória</div><div class="value"><?=$mem?></div><div class="meta">Uso atual • Uptime <?=h($uptime)?></div></div>
</section>

<div class="grid2">
<section class="panel">
<div class="panel-head"><h2>Solicitações recentes</h2><span style="font-size:10px;color:#94a1b3"><?=$pend?> pendente(s)</span></div>
<div class="tablewrap"><table><thead><tr><th>Nome</th><th>CPF</th><th>Tipo</th><th>Status</th><th>Ação</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><strong><?=h($r['nome'])?></strong><br><span style="color:#7f8da0"><?=h($r['email'])?></span></td>
<td><?=h($r['cpf'])?></td>
<td><?=h($r['tipo_solicitado'])?></td>
<td><?php $cl=$r['status']==='pendente'?'pending':($r['status']==='aprovada'?'approved':'rejected'); ?><span class="badge <?=$cl?>"><?=h(strtoupper($r['status']))?></span></td>
<td>
<?php if($r['status']==='pendente'): ?>
<button class="btn btn-blue" type="button" onclick='analisar(<?=json_encode(["id"=>(int)$r["id"],"nome"=>$r["nome"],"cpf"=>$r["cpf"],"tipo"=>$r["tipo_solicitado"]],JSON_HEX_APOS|JSON_HEX_QUOT)?>)'>ANALISAR</button>
<?php else: ?>—<?php endif;?>
</td>
</tr>
<?php endforeach;?>
</tbody></table></div>
</section>

<section>
<div class="panel">
<div class="panel-head"><h2>Usuários online</h2><span style="font-size:10px;color:#94a1b3"><?=$online?> ativo(s)</span></div>
<div class="tablewrap"><table style="min-width:520px"><thead><tr><th>Usuário</th><th>IP</th><th>Uptime</th></tr></thead><tbody>
<?php if(!$active):?><tr><td colspan="3">Nenhum usuário online.</td></tr><?php endif;?>
<?php foreach(array_slice($active,0,10) as $a):?><tr><td><?=h($a['user']??'')?></td><td><?=h($a['address']??'')?></td><td><?=h($a['uptime']??'')?></td></tr><?php endforeach;?>
</tbody></table></div>
</div>

<div class="panel">
<div class="panel-head"><h2>Ações rápidas</h2></div>
<div class="panel-body">
<a class="btn btn-red" href="sites.php">GERENCIAR SITES</a>
<a class="btn btn-dark" href="usuarios.php" style="margin-left:6px">GERENCIAR USUÁRIOS</a>
</div>
</div>
</section>
</div>
</main>
</section>
</div>

<div id="modal" class="modal-bg">
<div class="modal">
<div class="panel-head"><h2>Analisar solicitação</h2><button class="btn btn-dark" onclick="fechar()">FECHAR</button></div>
<div class="panel-body">
<p><strong id="nome"></strong><br><span id="cpf" style="color:#95a2b3"></span></p>
<p>Tipo solicitado: <strong id="tipo"></strong></p>

<form method="post" action="/acoes/aprovar.php">
<input type="hidden" name="id" id="idAprovar">
<label>Perfil Hotspot</label>
<select name="perfil" id="perfil" required>
<option value="PROFESSOR">PROFESSOR</option>
<option value="ALUNO">ALUNO</option>
<option value="VISITANTE">VISITANTE</option>
</select>
<label>VLAN registrada</label>
<select name="vlan" id="vlan" required>
<option value="20">20 - Professores</option>
<option value="30">30 - Alunos</option>
<option value="40">40 - Visitantes</option>
</select>
<div class="flash" style="margin-top:12px">Senha: definida pelo próprio usuário no cadastro. O administrador não precisa conhecê-la.</div>
<button class="btn btn-green" type="submit" style="width:100%;margin-top:10px">APROVAR E LIBERAR NO MIKROTIK</button>
</form>

<form method="post" action="/acoes/rejeitar.php">
<input type="hidden" name="id" id="idRejeitar">
<label>Motivo da rejeição</label><input name="motivo" maxlength="255" placeholder="Opcional">
<button class="btn btn-red" type="submit" style="width:100%;margin-top:8px">REJEITAR</button>
</form>
</div>
</div>
</div>

<script>
function analisar(d){
 document.getElementById('idAprovar').value=d.id;
 document.getElementById('idRejeitar').value=d.id;
 document.getElementById('nome').textContent=d.nome;
 document.getElementById('cpf').textContent='CPF: '+d.cpf;
 document.getElementById('tipo').textContent=d.tipo;
 const t=(d.tipo||'').toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
 if(t.includes('PROFESSOR')){perfil.value='PROFESSOR';vlan.value='20';}
 else if(t.includes('ALUNO')){perfil.value='ALUNO';vlan.value='30';}
 else{perfil.value='VISITANTE';vlan.value='40';}
 document.getElementById('modal').style.display='flex';
}
function fechar(){document.getElementById('modal').style.display='none';}
</script>
</body>
</html>
