<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/helpers/auth.php'; auth_require();
require_once dirname(__DIR__,2).'/app/helpers/common.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
$motivo=trim((string)($_POST['motivo']??''));

if(!$id){
 flash_set('err','Solicitação inválida.');
 header('Location: ../../public/index.php'); exit;
}

$pdo=db();

try{
 $st=$pdo->prepare("SELECT * FROM solicitacoes WHERE id=? AND status='pendente' LIMIT 1");
 $st->execute([$id]); $sol=$st->fetch();
 if(!$sol) throw new RuntimeException('Solicitação não encontrada ou já analisada.');

 $cpf=preg_replace('/\D+/','',(string)$sol['cpf']);
 $mt=require dirname(__DIR__,2).'/config/mikrotik.php';

 $client=new Client(new Config([
   'host'=>$mt['host'],'user'=>$mt['user'],'pass'=>$mt['pass'],'port'=>$mt['port'],
   'timeout'=>5,'attempts'=>2
 ]));

 $uq=(new Query('/ip/hotspot/user/print'))->where('name',$cpf);
 $u=$client->query($uq)->read();
 if(!empty($u) && !empty($u[0]['.id'])){
   $client->query((new Query('/ip/hotspot/user/remove'))->equal('.id',$u[0]['.id']))->read();
 }

 $up=$pdo->prepare("UPDATE solicitacoes SET status='rejeitada',motivo_rejeicao=?,analisado_em=NOW() WHERE id=?");
 $up->execute([$motivo?:null,$id]);

 log_action($pdo,(int)auth_user()['id'],'rejeitar_solicitacao','CPF '.$cpf.($motivo?' | '.$motivo:''));
 flash_set('ok','Solicitação rejeitada.');

}catch(Throwable $e){
 flash_set('err','Falha ao rejeitar: '.$e->getMessage());
}
header('Location: ../../public/index.php');
