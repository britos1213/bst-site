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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'message' => 'Método não permitido.'
    ]);

    exit;
}

try {

    $dados = $_POST;

    if (!$dados) {
        $raw = file_get_contents('php://input');
        $dados = json_decode($raw ?: '', true);
    }

    if (!is_array($dados)) {
        throw new RuntimeException('Dados inválidos.');
    }

    $nome = trim((string)($dados['nome'] ?? ''));
    $cpf = preg_replace('/\D+/', '', (string)($dados['cpf'] ?? ''));
    $telefone = trim((string)($dados['telefone'] ?? ''));
    $email = trim((string)($dados['email'] ?? ''));
    $tipo = trim((string)($dados['tipo_solicitado'] ?? ''));
    $turma = trim((string)($dados['turma'] ?? ''));
    $senha = (string)($dados['senha'] ?? '');
    $observacoes = trim((string)($dados['observacoes'] ?? ''));

    if ($nome === '') {
        throw new RuntimeException('Informe o nome.');
    }

    if (strlen($cpf) !== 11) {
        throw new RuntimeException('CPF inválido.');
    }

    if (!in_array($tipo, ['Aluno', 'Professor', 'Visitante'], true)) {
        throw new RuntimeException('Tipo de acesso inválido.');
    }

    $turmasPermitidas = [
        '1º ANO',
        '2º ANO',
        '3º ANO',
        '6º ANO',
        '7º ANO',
        '8º ANO',
        '9º ANO'
    ];

    if ($tipo === 'Aluno') {

        if (!in_array($turma, $turmasPermitidas, true)) {
            throw new RuntimeException('Selecione a turma do aluno.');
        }

    } else {

        $turma = null;
    }

    if (strlen($senha) < 8) {
        throw new RuntimeException(
            'A senha deve ter pelo menos 8 caracteres.'
        );
    }

    if (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        throw new RuntimeException('E-mail inválido.');
    }

    $pdo = db();

    /*
     * Verifica duplicidade no banco.
     */
    $st = $pdo->prepare("
        SELECT id
        FROM solicitacoes
        WHERE cpf = ?
        AND status IN ('pendente','aprovada')
        LIMIT 1
    ");

    $st->execute([$cpf]);

    if ($st->fetchColumn()) {
        throw new RuntimeException(
            'Já existe uma solicitação ou cadastro para este CPF.'
        );
    }

    /*
     * Conecta ao MikroTik.
     */
    $mt = require dirname(__DIR__,2).'/config/mikrotik.php';

    $client = new Client(new Config([
        'host'     => $mt['host'],
        'user'     => $mt['user'],
        'pass'     => $mt['pass'],
        'port'     => $mt['port'],
        'timeout'  => 5,
        'attempts' => 2
    ]));

    /*
     * Verifica se o CPF já existe no Hotspot.
     */
    $uq = (new Query('/ip/hotspot/user/print'))
        ->where('name', $cpf);

    $usuarioExistente = $client
        ->query($uq)
        ->read();

    if (!empty($usuarioExistente)) {
        throw new RuntimeException(
            'Este CPF já existe no MikroTik.'
        );
    }

    /*
     * Cria usuário PENDENTE no MikroTik.
     *
     * Disabled = yes:
     * ainda não consegue autenticar.
     */
    $comentario = 'BST PENDENTE | '.$tipo;

    if ($tipo === 'Aluno' && $turma !== null) {
        $comentario .= ' | '.$turma;
    }

    $add = (new Query('/ip/hotspot/user/add'))
        ->equal('name', $cpf)
        ->equal('password', $senha)
        ->equal('disabled', 'yes')
        ->equal('comment', $comentario);

    $client->query($add)->read();

    /*
     * Banco armazena somente o hash da senha.
     */
    $senhaHash = password_hash(
        $senha,
        PASSWORD_DEFAULT
    );

    try {

        $st = $pdo->prepare("
            INSERT INTO solicitacoes
            (
                nome,
                cpf,
                telefone,
                email,
                tipo_solicitado,
                turma,
                senha_hash,
                observacoes,
                status,
                criado_em
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW())
        ");

        $st->execute([
            $nome,
            $cpf,
            $telefone,
            $email,
            $tipo,
            $turma,
            $senhaHash,
            $observacoes
        ]);

    } catch (Throwable $e) {

        /*
         * Se o banco falhar depois de criar no MikroTik,
         * remove o usuário para não deixar cadastro órfão.
         */
        $uq = (new Query('/ip/hotspot/user/print'))
            ->where('name', $cpf);

        $u = $client
            ->query($uq)
            ->read();

        if (!empty($u[0]['.id'])) {

            $remove = (new Query('/ip/hotspot/user/remove'))
                ->equal('.id', $u[0]['.id']);

            $client->query($remove)->read();
        }

        throw $e;
    }

    echo json_encode([
        'ok' => true,
        'message' =>
            'Solicitação enviada com sucesso. Aguarde a aprovação.'
    ]);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}