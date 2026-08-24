<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'message'=>'Método não permitido.']);
    exit;
}

$dados = $_POST;
if (!$dados) {
    $dados = json_decode(file_get_contents('php://input') ?: '', true);
}
if (!is_array($dados)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'Dados inválidos.']);
    exit;
}

function mikrotik_client(): Client {
    $mt = require dirname(__DIR__,2).'/config/mikrotik.php';
    return new Client(new Config([
        'host'=>$mt['host'],
        'user'=>$mt['user'],
        'pass'=>$mt['pass'],
        'port'=>$mt['port'],
        'timeout'=>5,
        'attempts'=>2
    ]));
}

function normalizar_mac(string $mac): string {
    return strtoupper(trim($mac));
}

$acao = trim((string)($dados['acao'] ?? 'cadastro'));

/*
 * RESET do dispositivo no Hotspot após o cadastro.
 * Não remove Hotspot Users; apenas sessão, cookie e Host.
 */
if ($acao === 'reset_host') {
    try {
        $mac = normalizar_mac((string)($dados['mac'] ?? ''));
        $ip  = trim((string)($dados['ip'] ?? ''));

        if ($mac === '' && $ip === '') {
            throw new RuntimeException('MAC/IP do cliente não informado.');
        }

        $client = mikrotik_client();

        $ativos = $client->query(new Query('/ip/hotspot/active/print'))->read();
        foreach ($ativos as $ativo) {
            $id = $ativo['.id'] ?? null;
            $m  = normalizar_mac((string)($ativo['mac-address'] ?? ''));
            $a  = trim((string)($ativo['address'] ?? ''));
            if ($id && (($mac !== '' && $m === $mac) || ($ip !== '' && $a === $ip))) {
                $client->query((new Query('/ip/hotspot/active/remove'))->equal('.id',$id))->read();
            }
        }

        $cookies = $client->query(new Query('/ip/hotspot/cookie/print'))->read();
        foreach ($cookies as $cookie) {
            $id = $cookie['.id'] ?? null;
            $m  = normalizar_mac((string)($cookie['mac-address'] ?? ''));
            if ($id && $mac !== '' && $m === $mac) {
                $client->query((new Query('/ip/hotspot/cookie/remove'))->equal('.id',$id))->read();
            }
        }

        $hosts = $client->query(new Query('/ip/hotspot/host/print'))->read();
        foreach ($hosts as $host) {
            $id = $host['.id'] ?? null;
            $m  = normalizar_mac((string)($host['mac-address'] ?? ''));
            $a  = trim((string)($host['address'] ?? ''));
            if ($id && (($mac !== '' && $m === $mac) || ($ip !== '' && $a === $ip))) {
                $client->query((new Query('/ip/hotspot/host/remove'))->equal('.id',$id))->read();
            }
        }

        echo json_encode(['ok'=>true,'message'=>'Conexão Hotspot reiniciada.']);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'message'=>'Falha ao reiniciar Hotspot: '.$e->getMessage()]);
    }
    exit;
}

try {
    $nome = trim((string)($dados['nome'] ?? ''));
    $cpf = preg_replace('/\D+/','',(string)($dados['cpf'] ?? ''));
    $telefone = trim((string)($dados['telefone'] ?? ''));
    $email = trim((string)($dados['email'] ?? ''));
    $tipo = trim((string)($dados['tipo_solicitado'] ?? ''));
    $turma = trim((string)($dados['turma'] ?? ''));
    $senha = (string)($dados['senha'] ?? '');
    $observacoes = trim((string)($dados['observacoes'] ?? ''));

    if ($nome === '') throw new RuntimeException('Informe o nome.');
    if (strlen($cpf) !== 11) throw new RuntimeException('CPF inválido.');
    if (!in_array($tipo,['Aluno','Professor','Visitante'],true)) throw new RuntimeException('Tipo de acesso inválido.');

    $turmasPermitidas=['1º ANO','2º ANO','3º ANO','6º ANO','7º ANO','8º ANO','9º ANO'];
    if ($tipo === 'Aluno') {
        if (!in_array($turma,$turmasPermitidas,true)) throw new RuntimeException('Selecione a turma do aluno.');
    } else {
        $turma = null;
    }

    if (strlen($senha) < 8) throw new RuntimeException('A senha deve ter pelo menos 8 caracteres.');
    if ($email !== '' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('E-mail inválido.');

    $pdo = db();
    $st = $pdo->prepare("SELECT id FROM solicitacoes WHERE cpf=? AND status IN ('pendente','aprovada') LIMIT 1");
    $st->execute([$cpf]);
    if ($st->fetchColumn()) throw new RuntimeException('Já existe uma solicitação ou cadastro ativo para este CPF.');

    $client = mikrotik_client();
    $uq=(new Query('/ip/hotspot/user/print'))->where('name',$cpf);
    $usuarioExistente=$client->query($uq)->read();
    if (!empty($usuarioExistente)) throw new RuntimeException('Este CPF já existe no MikroTik.');

    $comentario='BST PENDENTE | '.$tipo;
    if ($tipo === 'Aluno' && $turma !== null) $comentario.=' | '.$turma;

    $add=(new Query('/ip/hotspot/user/add'))
        ->equal('name',$cpf)
        ->equal('password',$senha)
        ->equal('disabled','yes')
        ->equal('comment',$comentario);
    $client->query($add)->read();

    $senhaHash=password_hash($senha,PASSWORD_DEFAULT);

    try {
        $st=$pdo->prepare("INSERT INTO solicitacoes (nome,cpf,telefone,email,tipo_solicitado,turma,senha_hash,observacoes,status,criado_em) VALUES (?,?,?,?,?,?,?,?,'pendente',NOW())");
        $st->execute([$nome,$cpf,$telefone,$email,$tipo,$turma,$senhaHash,$observacoes]);
    } catch (Throwable $e) {
        $uq=(new Query('/ip/hotspot/user/print'))->where('name',$cpf);
        $u=$client->query($uq)->read();
        if (!empty($u[0]['.id'])) {
            $client->query((new Query('/ip/hotspot/user/remove'))->equal('.id',$u[0]['.id']))->read();
        }
        throw $e;
    }

    echo json_encode(['ok'=>true,'message'=>'Solicitação enviada com sucesso. Aguarde a aprovação.']);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
