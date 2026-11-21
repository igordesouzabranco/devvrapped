<?php
declare(strict_types=1);

/**
 * Etapa 5 — recebe o PNG em base64 (fetch POST) e envia por e-mail.
 *
 * Segurança:
 *  - só aceita POST;
 *  - limita o tamanho do corpo ANTES de decodificar;
 *  - valida o e-mail com filter_var;
 *  - confirma que o anexo é um PNG de verdade (magic bytes + getimagesize)
 *    e que não passa de 1080x1920;
 *  - responde só em JSON, sem vazar stack trace.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

const MAX_BODY_BYTES = 6 * 1024 * 1024;   // corpo bruto (base64 ocupa ~4/3 do binário)
const MAX_IMAGE_BYTES = 4 * 1024 * 1024;  // PNG já decodificado
const MAX_WIDTH = 1080;
const MAX_HEIGHT = 1920;
const PREFIXO_PNG = 'data:image/png;base64,';
const PNG_MAGIC = "\x89PNG\r\n\x1a\n";

// Warning de PHP não pode vazar no meio de uma resposta JSON.
ini_set('display_errors', '0');

/** Responde em JSON e encerra a requisição. */
function responderJson(bool $ok, string $mensagem, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        ['ok' => $ok, 'message' => $mensagem],
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    );

    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responderJson(false, 'Envie os dados por POST.', 405);
}

// Tamanho conferido pelo header antes de carregar o corpo para a memória.
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_BODY_BYTES) {
    responderJson(false, 'Imagem grande demais: o limite é 4 MB.', 413);
}

$raw = file_get_contents('php://input');
if ($raw === false) {
    responderJson(false, 'Não consegui ler a requisição.', 400);
}
if (strlen($raw) > MAX_BODY_BYTES) {
    responderJson(false, 'Imagem grande demais: o limite é 4 MB.', 413);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    responderJson(false, 'Corpo da requisição inválido.', 400);
}

$email = is_string($data['email'] ?? '') ? trim($data['email']) : '';
$imagem = is_string($data['image'] ?? '') ? $data['image'] : '';

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    responderJson(false, 'E-mail inválido.', 422);
}

if (!str_starts_with($imagem, PREFIXO_PNG)) {
    responderJson(false, 'Só aceito imagens PNG.', 422);
}

$binario = base64_decode(substr($imagem, strlen(PREFIXO_PNG)), true);
if ($binario === false) {
    responderJson(false, 'A imagem chegou corrompida (base64 inválido).', 422);
}

if (strlen($binario) > MAX_IMAGE_BYTES) {
    responderJson(false, 'Imagem grande demais: o limite é 4 MB.', 413);
}

// Assinatura binária do PNG: 89 50 4E 47 0D 0A 1A 0A.
if (!str_starts_with($binario, PNG_MAGIC)) {
    responderJson(false, 'O arquivo não é um PNG válido.', 422);
}

// @ evita warning de arquivo truncado corromper a resposta JSON.
$dimensoes = @getimagesizefromstring($binario);
if ($dimensoes === false || $dimensoes[2] !== IMAGETYPE_PNG) {
    responderJson(false, 'O arquivo não é um PNG válido.', 422);
}

if ($dimensoes[0] > MAX_WIDTH || $dimensoes[1] > MAX_HEIGHT) {
    responderJson(false, 'O card deve ter no máximo 1080x1920.', 422);
}

if (SMTP_HOST === 'smtp.example.com' || SMTP_USER === 'seu_usuario') {
    responderJson(false, 'Envio por e-mail ainda não configurado: preencha as constantes SMTP_* no config.php.', 501);
}

try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->Port = SMTP_PORT;
    $mail->SMTPSecure = SMTP_PORT === 465
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($email);

    $mail->Subject = 'Seu Devvrapped ' . date('Y') . ' chegou!';
    $mail->Body = 'Olá! O card do seu ano no GitHub está em anexo.'
        . PHP_EOL . PHP_EOL
        . 'Se não foi você quem pediu, pode ignorar esta mensagem.';
    $mail->isHTML(false);

    $mail->addStringAttachment(
        $binario,
        'devvrapped-' . date('Y') . '.png',
        PHPMailer::ENCODING_BASE64,
        'image/png',
    );

    $mail->send();
} catch (MailerException $exception) {
    // Detalhe técnico fica no log do servidor; para o usuário, mensagem genérica.
    error_log('Devvrapped: falha no envio de e-mail: ' . $exception->getMessage());

    responderJson(false, 'Não consegui enviar o e-mail agora. Tente de novo em instantes.', 502);
}

responderJson(true, 'PNG enviado para ' . $email . '!');
