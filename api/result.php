<?php
declare(strict_types=1);

/**
 * Etapa 4 — mostra as estatísticas e desenha o card (1080x1920).
 *
 * Este arquivo é incluído pelo index.php DEPOIS da validação, então
 * ele espera encontrar estas variáveis no escopo:
 *   $githubUser (string)  → username extraído da URL do perfil
 *   $values['nome']       → nome digitado pela pessoa
 *
 * Se alguém abrir result.php direto no navegador (sem essas variáveis),
 * ele redireciona para o index.php.
 */

// A consulta à API pode demorar para perfis com muitos repositórios.
set_time_limit(120);

if (
    !isset($githubUser, $values)
    || !is_string($githubUser)
    || $githubUser === ''
    || !isset($values['nome'])
    || !is_string($values['nome'])
) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/GitHubApiException.php';
require_once __DIR__ . '/src/GitHubClient.php';
require_once __DIR__ . '/src/Statistics.php';

$userName = (string) $values['nome'];
$userEmail = isset($values['email']) && is_string($values['email']) ? $values['email'] : '';
$year = (int) date('Y');
$erro = null;
$payload = null;

try {
    $client = new GitHubClient(GITHUB_TOKEN);
    $data = $client->collect($githubUser, $year);

    $statistics = new Statistics(
        $data['commits'],
        $data['languages'],
        new DateTimeZone(TIMEZONE),
    );

    $payload = array_merge(
        [
            'name' => $userName,
            'username' => $githubUser,
            'year' => $year,
        ],
        $statistics->toArray(),
    );
} catch (GitHubApiException $exception) {
    // A mensagem já vem amigável em PT-BR (inclusive a do limite de requisições).
    $erro = $exception->getMessage();
}

// JSON injetado na página para o card.js desenhar no canvas.
// JSON_HEX_TAG transforma < e > em \u003C/\u003E: impede que um
// valor vindo da API feche a tag <script> e injete código.
$json = '';
if ($payload !== null) {
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR,
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devvrapped de <?= esc($userName) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="pagina pagina-resultado">
        <h1 class="title">
            <img class="logo" src="DEV-logo.svg" alt="Devvrapped">
            <span class="ano"><?= $year ?></span>
        </h1>
        <p class="subtitle"><?= esc($userName) ?> (@<?= esc($githubUser) ?>)</p>

        <?php if ($erro !== null): ?>
            <div class="alerta-ruim" role="alert"><?= esc($erro) ?></div>
            <a class="button botao-secundario" href="index.php">Voltar e tentar de novo</a>
        <?php else: ?>
            <section class="bloco">
                <p class="numero-grande"><?= (int) $payload['total_commits'] ?></p>
                <p class="rotulo">commits em <?= $year ?></p>
            </section>

            <?php if ($payload['languages'] !== []): ?>
            <section class="bloco">
                <h2 class="titulo-bloco">Linguagens do ano</h2>
                <ul class="lista">
                    <?php foreach ($payload['languages'] as $lang): ?>
                    <li class="item-linguagem">
                        <span class="nome"><?= esc($lang['name']) ?></span>
                        <span class="barra"><span class="barra-preenchida"
                              style="width: <?= esc((string) $lang['percent']) ?>%"></span></span>
                        <span class="percentual"><?= $lang['percent'] ?>%</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <section class="bloco">
                <h2 class="titulo-bloco">Seu ano em detalhes</h2>
                <ul class="lista-detalhes">
                    <?php if ($payload['weekday'] !== null): ?>
                    <li>
                        <strong>Dia com mais commits</strong>
                        <span><?= esc($payload['weekday']['name']) ?>
                            (<?= (int) $payload['weekday']['count'] ?>)</span>
                    </li>
                    <?php endif; ?>
                    <?php if ($payload['hour'] !== null): ?>
                    <li>
                        <strong>Horário de pico</strong>
                        <span><?= (int) $payload['hour']['hour'] ?>h
                            (<?= (int) $payload['hour']['count'] ?>)</span>
                    </li>
                    <?php endif; ?>
                    <?php if ($payload['top_repository'] !== null): ?>
                    <li>
                        <strong>Repositório mais ativo</strong>
                        <span><?= esc($payload['top_repository']['name']) ?>
                            (<?= (int) $payload['top_repository']['count'] ?>)</span>
                    </li>
                    <?php endif; ?>
                    <li>
                        <strong>Linguagem principal</strong>
                        <span><?= $payload['top_language'] !== null ? esc($payload['top_language']) : '—' ?></span>
                    </li>
                </ul>
            </section>

            <?php if ($payload['emails'] !== []): ?>
            <section class="bloco">
                <h2 class="titulo-bloco">E-mails com mais commits</h2>
                <ol class="lista">
                    <?php foreach ($payload['emails'] as $rank => $email): ?>
                    <li class="item-email">
                        <span class="posicao"><?= $rank + 1 ?>º</span>
                        <span class="nome"><?= esc($email['email']) ?></span>
                        <span class="percentual"><?= (int) $email['count'] ?>
                            (<?= $email['percent'] ?>%)</span>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <?php endif; ?>

            <h2 class="titulo-bloco">Seu card</h2>
            <canvas id="card" width="1080" height="1920" role="img"
                    aria-label="Card do Wrapped de <?= esc($userName) ?> em <?= $year ?>"></canvas>

            <div class="acoes">
                <button type="button" class="button" id="baixar">Baixar imagem (PNG)</button>
                <button type="button" class="button" id="enviar"
                        data-email="<?= esc($userEmail) ?>">Enviar por e-mail</button>
                <a class="button botao-secundario" href="index.php">Novo Wrapped</a>
            </div>

            <p id="status-email" class="dica" role="status"></p>

            <p class="dica">Nada é salvo no servidor: o card existe só nesta aba.
                Baixe o PNG para guardar.</p>
        <?php endif; ?>
    </main>

    <?php if ($erro === null): ?>
    <script>
        window.DEVVRAPPED = <?= $json ?>;
    </script>
    <script src="assets/js/card.js" defer></script>
    <?php endif; ?>
</body>
</html>
