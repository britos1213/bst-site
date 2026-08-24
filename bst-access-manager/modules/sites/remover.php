<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/helpers/auth.php'; auth_require();
require_once dirname(__DIR__,2).'/app/helpers/common.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';
use RouterOS\Client; use RouterOS\Config; use RouterOS\Query;
$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
if(!$id){flash_set('err','Registro inválido.');header('Location: ../../public/sites.php');exit;}
try{
 $pdo=db();$st=$pdo->prepare("SELECT * FROM sites_bloqueados WHERE id=?");$st->execute([$id]);$s=$st->fetch();
 if(!$s) throw new RuntimeException('Site não encontrado.');
 $mt=require dirname(__DIR__,2).'/config/mikrotik.php';
 $client=new Client(new Config(['host'=>$mt['host'],'user'=>$mt['user'],'pass'=>$mt['pass'],'port'=>$mt['port'],'timeout'=>5,'attempts'=>2]));
 $q=(new Query('/ip/dns/static/print'))->where('comment','BST-BLOCK:'.$s['dominio']);
 foreach($client->query($q)->read() as $item){
   if(!empty($item['.id'])) $client->query((new Query('/ip/dns/static/remove'))->equal('.id',$item['.id']))->read();
 }
 $pdo->prepare("UPDATE sites_bloqueados SET ativo=0 WHERE id=?")->execute([$id]);
 log_action($pdo,(int)auth_user()['id'],'remover_bloqueio_site',$s['dominio']);
 flash_set('ok','Bloqueio removido.');
}catch(Throwable $e){flash_set('err','Erro: '.$e->getMessage());}
header('Location: ../../public/sites.php');
