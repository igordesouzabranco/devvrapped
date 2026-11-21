<?php
declare(strict_types=1);

/**
 * Erro amigável vindo da API do GitHub.
 *
 * Estende RuntimeException para que o resultado possa fazer
 * `catch (GitHubApiException $e)` e mostrar a mensagem direto
 * para o usuário (sem vazar detalhe técnico).
 */
final class GitHubApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly bool $rateLimited = false,
    ) {
        parent::__construct($message);
    }
}
