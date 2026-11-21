<?php
declare(strict_types=1);

/**
 * DEVVRAPPED — Etapa 1: estrutura e formulário.
 *
 * O que este arquivo faz:
 *  1. Detecta se a requisição veio por POST.
 *  2. Lê os 3 campos com segurança (aceita só string; ignora arrays).
 *  3. Valida nome, e-mail e URL do perfil do GitHub.
 *  4. Extrai o username da URL.
 *  5. Se tudo estiver válido, inclui o result.php (Etapa 4) e encerra.
 *  6. Se houver erro, reexibe o formulário com a mensagem em cada campo.
 */

/** Escapa texto para exibir com segurança no HTML. */
function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** Lê um campo do POST apenas se ele for string (protege contra ?nome[]=x). */
function postString(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

/**
 * Extrai o username de uma URL de perfil do GitHub.
 * Retorna null quando a URL não é um perfil válido.
 */
function extractGithubUsername(string $url): ?string
{
    if ($url === '') {
        return null;
    }

    // Aceita "github.com/fulano" digitado sem https://
    if (preg_match('#^https?://#i', $url) !== 1) {
        $url = 'https://' . $url;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return null;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host !== 'github.com' && $host !== 'www.github.com') {
        return null;
    }

    // Exige exatamente um segmento no caminho: /usuario (rejeita /usuario/repo)
    $path = trim((string) ($parts['path'] ?? ''), '/');
    if ($path === '' || str_contains($path, '/')) {
        return null;
    }

    // Regras do GitHub: 1–39 caracteres, letras/números/hífen,
    // não começa nem termina com hífen.
    $padrao = '/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?$/';
    if (preg_match($padrao, $path) !== 1) {
        return null;
    }

    return $path;
}

$errors = [];
$values = ['nome' => '', 'email' => '', 'github' => ''];
$githubUser = '';

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if ($isPost) {
    $values['nome'] = postString('nome');
    $values['email'] = postString('email');
    $values['github'] = postString('github');

    // --- Nome ---
    if ($values['nome'] === '') {
        $errors['nome'] = 'Preencha o seu nome.';
    } elseif (preg_match_all('/./u', $values['nome'], $caracteres) > 100) {
        $errors['nome'] = 'O nome pode ter no máximo 100 caracteres.';
    }

    // --- E-mail ---
    if ($values['email'] === '') {
        $errors['email'] = 'Preencha o seu e-mail.';
    } elseif (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'E-mail inválido. Exemplo: ana@exemplo.com';
    }

    // --- URL do GitHub ---
    if ($values['github'] === '') {
        $errors['github'] = 'Preencha a URL do seu perfil do GitHub.';
    } else {
        $githubUser = extractGithubUsername($values['github']) ?? '';
        if ($githubUser === '') {
            $errors['github'] = 'URL inválida. Use o formato https://github.com/seu-usuario';
        }
    }

    if ($errors === []) {
        require __DIR__ . '/result.php';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devvrapped — retrospectiva de commits</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="pagina">
        <h1 class="title">
            <img class="logo" src="DEV-logo.svg" alt="Devvrapped">
        </h1>
        <p class="subtitle">Sua retrospectiva anual de commits do GitHub</p>

        <form class="formulario-github" method="POST" action="/" novalidate>
            <div class="campo">
                <label class="label" for="nome">Seu nome</label>
                <input type="text" id="nome" name="nome" maxlength="100"
                       value="<?= esc($values['nome']) ?>"
                       placeholder="Seu nome"
                       <?= isset($errors['nome']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['nome'])): ?>
                    <p class="erro-campo"><?= esc($errors['nome']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label class="label" for="email">Seu e-mail</label>
                <input type="email" id="email" name="email"
                       value="<?= esc($values['email']) ?>"
                       placeholder="seunome@exemplo.com"
                       <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['email'])): ?>
                    <p class="erro-campo"><?= esc($errors['email']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label class="label" for="github">URL do seu perfil do GitHub</label>
                <input type="url" id="github" name="github"
                       value="<?= esc($values['github']) ?>"
                       placeholder="https://github.com/seu-usuario"
                       <?= isset($errors['github']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['github'])): ?>
                    <p class="erro-campo"><?= esc($errors['github']) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit" class="button">Gerar meu Wrapped</button>
        </form>
    </main>
</body>
</html>
