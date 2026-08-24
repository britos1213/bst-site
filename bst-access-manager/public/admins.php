<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/app/helpers/common.php';
require_once dirname(__DIR__).'/app/helpers/layout.php';
/*
 * Somente ADMIN pode acessar.
 */
if (!auth_has_role(['admin'])) {
    http_response_code(403);
    exit('Acesso negado.');
}

$pdo = db();

$st = $pdo->query("
    SELECT
        id,
        nome,
        email,
        perfil,
        ativo,
        criado_em,
        ultimo_login
    FROM administradores
    ORDER BY nome
");

$admins = $st->fetchAll();

page_start('Usuários do Portal', 'admins');
?>

<section class="panel">

    <div class="panel-head">
        <div>
            <h2>Usuários do Portal</h2>
            <p>Contas autorizadas a acessar o painel administrativo</p>
        </div>
    </div>

    <div class="tablewrap">

        <table>

            <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Perfil</th>
                <th>Status</th>
                <th>Último login</th>
            </tr>
            </thead>

            <tbody>

            <?php foreach ($admins as $a): ?>

                <tr>

                    <td><?=h($a['nome'])?></td>

                    <td><?=h($a['email'])?></td>

                    <td>
                        <span class="badge pending">
                            <?=h(strtoupper($a['perfil']))?>
                        </span>
                    </td>

                    <td>

                        <?php if ((int)$a['ativo'] === 1): ?>

                            <span class="badge approved">
                                ● ATIVO
                            </span>

                        <?php else: ?>

                            <span class="badge rejected">
                                ● INATIVO
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>
                        <?=h($a['ultimo_login'] ?: 'Nunca')?>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</section>


<section class="panel">

    <div class="panel-head">
        <div>
            <h2>Novo usuário</h2>
            <p>Cadastrar acesso ao portal administrativo</p>
        </div>
    </div>

    <div class="panel-body">

        <form method="post"
              action="/acoes/salvar-admin.php">

            <label>Nome</label>

            <input type="text"
                   name="nome"
                   maxlength="120"
                   required>


            <label style="margin-top:12px">
                E-mail
            </label>

            <input type="email"
                   name="email"
                   maxlength="190"
                   required>


            <label style="margin-top:12px">
                Senha
            </label>

            <input type="password"
                   name="senha"
                   minlength="8"
                   required>


            <label style="margin-top:12px">
                Perfil de acesso
            </label>

            <select name="perfil" required>

                <option value="consulta">
                    Consulta
                </option>

                <option value="professor">
                    Professor
                </option>

                <option value="coordenacao">
                    Coordenação
                </option>

                <option value="admin">
                    Administrador
                </option>

            </select>


            <button class="btn btn-green"
                    type="submit"
                    style="margin-top:15px">
                CADASTRAR USUÁRIO
            </button>

        </form>

    </div>

</section>

<?php page_end(); ?>