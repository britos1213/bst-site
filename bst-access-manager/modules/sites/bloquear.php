<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/helpers/auth.php'; auth_require();
require_once dirname(__DIR__,2).'/app/helpers/common.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';
use RouterOS\Client; use RouterOS\Config; use RouterOS\Query;

$dominio=strtolower(trim((string)($_POST['dominio']??'')));
$descricao=trim((string)($_POST['descricao']??''));
$dominio=preg_replace('#^https?://#i','',$dominio);
$dominio=preg_replace('#/.*$#','',$dominio);

if(!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',$dominio)){
 flash_set('err','Domínio inválido.');header('Location: ../../public/sites.php');exit;
}
try{
 $mt=require dirname(__DIR__,2).'/config/mikrotik.php';
 $client=new Client(new Config(['host'=>$mt['host'],'user'=>$mt['user'],'pass'=>$mt['pass'],'port'=>$mt['port'],'timeout'=>5,'attempts'=>2]));
 $comment='BST-BLOCK:'.$dominio;
 $q=(new Query('/ip/dns/static/print'))->where('comment',$comment);
 if(empty($client->query($q)->read())){
   $regexp='(^|\\.)'.preg_quote($dominio,'/').'$';
   $client->query((new Query('/ip/dns/static/add'))->equal('regexp',$regexp)->equal('type','NXDOMAIN')->equal('comment',$comment))->read();
 }
 $pdo=db();
 $st=$pdo->prepare("INSERT INTO sites_bloqueados(dominio,descricao,ativo,criado_em) VALUES(?,?,1,NOW()) ON DUPLICATE KEY UPDATE descricao=VALUES(descricao),ativo=1,criado_em=NOW()");
 $st->execute([$dominio,$descricao]);
 log_action($pdo,(int)auth_user()['id'],'bloquear_site',$dominio);
 flash_set('ok','Site bloqueado.');
}catch(Throwable $e){flash_set('err','Erro: '.$e->getMessage());}
header('Location: ../../public/sites.php');
