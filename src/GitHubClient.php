<?php
declare(strict_types=1);

/**
 * Cliente da API do GitHub usando cURL puro (sem framework).
 *
 * Endpoints usados:
 *   GET /users/{user}/repos               → repositórios do usuário (paginação, sem forks)
 *   GET /repos/{owner}/{repo}/languages   → bytes de cada linguagem do repositório
 *   GET /repos/{owner}/{repo}/commits     → commits (filtrados por author + since, com paginação)
 *
 * Toda requisição passa por request(), que centraliza headers, paginação,
 * decodificação do JSON e o tratamento de erros (404, 401, 409, rate limit).
 */
final class GitHubClient
{
    private const BASE_URL = 'https://api.github.com';
    private const PER_PAGE = 100;
    private const MAX_REQUESTS = 500;

    private string $token;
    private int $requestCount = 0;

    public function __construct(string $token)
    {
        // Quebras de linha no token permitiriam injetar headers extras na requisição.
        $this->token = trim((string) preg_replace('/[\r\n]+/', '', $token));
    }

    /**
     * Busca tudo de uma vez: repositórios → linguagens → commits do ano.
     *
     * @return array{
     *     username: string,
     *     year: int,
     *     repositories: list<array{name: string, owner: string, url: string, stars: int}>,
     *     languages: array<string, int>,
     *     commits: list<array{repository: string, email: string, date: string}>
     * }
     * @throws GitHubApiException
     */
    public function collect(string $username, int $year): array
    {
        $repositories = $this->getRepositories($username);

        return [
            'username' => $username,
            'year' => $year,
            'repositories' => $repositories,
            'languages' => $this->sumLanguages($repositories),
            'commits' => $this->getCommits($repositories, $username, $year),
        ];
    }

    /**
     * Lista os repositórios do usuário (paginação), ignorando forks.
     *
     * @return list<array{name: string, owner: string, url: string, stars: int}>
     * @throws GitHubApiException
     */
    public function getRepositories(string $username): array
    {
        $repositories = [];
        $page = 1;

        try {
            do {
                /** @var list<array<string, mixed>> $batch */
                $batch = $this->request('/users/' . rawurlencode($username) . '/repos', [
                    'type' => 'owner',
                    'sort' => 'full_name',
                    'per_page' => self::PER_PAGE,
                    'page' => $page,
                ]);

                foreach ($batch as $repo) {
                    if (($repo['fork'] ?? false) === true) {
                        continue;
                    }

                    $repositories[] = [
                        'name' => (string) ($repo['name'] ?? ''),
                        'owner' => (string) ($repo['owner']['login'] ?? $username),
                        'url' => (string) ($repo['html_url'] ?? ''),
                        'stars' => (int) ($repo['stargazers_count'] ?? 0),
                    ];
                }

                $page++;
            } while (count($batch) === self::PER_PAGE);
        } catch (GitHubApiException $exception) {
            if ($exception->statusCode === 404) {
                throw new GitHubApiException(
                    'Usuário do GitHub não encontrado: @' . $username . '. Confira a URL digitada.',
                    404,
                );
            }

            throw $exception;
        }

        return $repositories;
    }

    /**
     * Soma os bytes de cada linguagem em todos os repositórios.
     *
     * @param list<array{name: string, owner: string, url: string, stars: int}> $repositories
     * @return array<string, int> linguagem => total de bytes, do maior para o menor
     * @throws GitHubApiException
     */
    public function sumLanguages(array $repositories): array
    {
        $totals = [];

        foreach ($repositories as $repo) {
            foreach ($this->getLanguages($repo['owner'], $repo['name']) as $language => $bytes) {
                $totals[$language] = ($totals[$language] ?? 0) + $bytes;
            }
        }

        arsort($totals);

        return $totals;
    }

    /**
     * GET /repos/{owner}/{repo}/languages → linguagem => bytes.
     *
     * @return array<string, int>
     * @throws GitHubApiException
     */
    public function getLanguages(string $owner, string $repo): array
    {
        $data = $this->request(
            '/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/languages',
        );

        $bytes = [];
        foreach ($data as $language => $value) {
            $bytes[(string) $language] = (int) $value;
        }

        return $bytes;
    }

    /**
     * Commits do ano feitos por $username em cada repositório (paginação).
     *
     * O e-mail vem de `commit.author.email` — é o e-mail gravado no commit.
     *
     * @param list<array{name: string, owner: string, url: string, stars: int}> $repositories
     * @return list<array{repository: string, email: string, date: string}>
     * @throws GitHubApiException
     */
    public function getCommits(array $repositories, string $username, int $year): array
    {
        // `since`/`until` delimitam o ano. Só o `since` bastaria para o ano
        // corrente, mas sem `until` uma busca por 2015 pegaria 2015→hoje.
        $since = sprintf('%04d-01-01T00:00:00Z', $year);
        $until = sprintf('%04d-12-31T23:59:59Z', $year);
        $commits = [];

        foreach ($repositories as $repo) {
            $page = 1;

            do {
                try {
                    $batch = $this->request(
                        '/repos/' . rawurlencode($repo['owner']) . '/' . rawurlencode($repo['name']) . '/commits',
                        [
                            'author' => $username,
                            'since' => $since,
                            'until' => $until,
                            'per_page' => self::PER_PAGE,
                            'page' => $page,
                        ],
                    );
                } catch (GitHubApiException $exception) {
                    // 409 = "Git Repository is empty": repositório novo, sem commits.
                    // Não derruba o Wrapped inteiro: seguimos para o próximo repositório.
                    if ($exception->statusCode === 409) {
                        break;
                    }

                    throw $exception;
                }

                foreach ($batch as $item) {
                    $commits[] = [
                        'repository' => (string) ($repo['name']),
                        'email' => (string) ($item['commit']['author']['email'] ?? ''),
                        'date' => (string) ($item['commit']['author']['date'] ?? ''),
                    ];
                }

                $page++;
            } while (count($batch) === self::PER_PAGE);
        }

        return $commits;
    }

    /**
     * Faz o GET e devolve o JSON decodificado.
     *
     * @param array<string, string|int> $query
     * @return array<int|string, mixed>
     * @throws GitHubApiException
     */
    private function request(string $path, array $query = []): array
    {
        if ($this->requestCount >= self::MAX_REQUESTS) {
            throw new GitHubApiException(
                'Esse perfil exigiu muitas requisições. Tente novamente mais tarde.',
            );
        }

        $url = self::BASE_URL . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $this->requestCount++;

        [$status, $headers, $body] = $this->httpGet($url);

        if ($status >= 200 && $status < 300) {
            return $this->decodeJson($body);
        }

        $this->raiseHttpError($status, $headers, $body);
    }

    /**
     * @return array{0: int, 1: array<string, string>, 2: string} [status, headers, body]
     * @throws GitHubApiException
     */
    private function httpGet(string $url): array
    {
        $headers = [];

        $handle = curl_init();
        if ($handle === false) {
            throw new GitHubApiException('Falha ao iniciar o cURL neste servidor.');
        }

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $this->defaultHeaders(),
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $curl, string $line) use (&$headers): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);

        if ($body === false) {
            throw new GitHubApiException('Falha ao conectar no GitHub: ' . $error);
        }

        return [$status, $headers, (string) $body];
    }

    /**
     * @return list<string>
     */
    private function defaultHeaders(): array
    {
        $headers = [
            // A API do GitHub recusa requisições sem User-Agent.
            'User-Agent: Devvrapped',
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        return $headers;
    }

    /**
     * Converte erro HTTP em exceção com mensagem amigável.
     *
     * @param array<string, string> $headers
     * @return never
     * @throws GitHubApiException
     */
    private function raiseHttpError(int $status, array $headers, string $body): never
    {
        $remaining = (int) ($headers['x-ratelimit-remaining'] ?? '1');
        $rateLimited = $status === 429
            || ($status === 403 && $remaining === 0)
            || stripos($body, 'rate limit') !== false;

        if ($rateLimited) {
            $resetAt = (int) ($headers['x-ratelimit-reset'] ?? 0);
            $message = 'O GitHub limitou as requisições da API (limite atingido). '
                . 'Aguarde alguns minutos e tente de novo.';

            if ($resetAt > 0) {
                $message .= ' O limite volta às ' . date('H:i', $resetAt) . '.';
            }

            throw new GitHubApiException($message, $status, true);
        }

        $apiMessage = $this->readApiMessage($body);

        if ($status === 404) {
            throw new GitHubApiException(
                'Usuário ou repositório não encontrado no GitHub.',
                404,
            );
        }

        if ($status === 401) {
            throw new GitHubApiException(
                'O token do GitHub em config.php é inválido ou foi revogado.',
                401,
            );
        }

        if ($status === 409) {
            throw new GitHubApiException('Repositório vazio: ainda não há commits.', 409);
        }

        if ($status >= 500) {
            throw new GitHubApiException(
                'O GitHub está instável no momento (erro ' . $status . '). Tente novamente em instantes.',
                $status,
            );
        }

        throw new GitHubApiException(
            'A API do GitHub recusou a requisição (' . $status . ')'
            . ($apiMessage !== '' ? ': ' . $apiMessage : '.'),
            $status,
        );
    }

    /**
     * @return array<int|string, mixed>
     * @throws GitHubApiException
     */
    private function decodeJson(string $body): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GitHubApiException('Resposta inválida vinda do GitHub.');
        }

        if (!is_array($data)) {
            throw new GitHubApiException('Resposta inesperada vinda do GitHub.');
        }

        return $data;
    }

    private function readApiMessage(string $body): string
    {
        $data = json_decode($body, true);

        if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }

        return '';
    }
}
