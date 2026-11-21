<?php
declare(strict_types=1);

/**
 * Calcula as estatísticas do Wrapped a partir dos dados brutos do GitHub.
 *
 * Esta classe é pura: não faz requisição, não acessa banco, não lê config.
 * Recebe dois arrays (commits e linguagens) e devolve números prontos
 * para o card — o que a torna fácil de testar isoladamente.
 */
final class Statistics
{
    /** Nomes dos dias da semana em PT-BR (formato 'N': 1 = segunda … 7 = domingo). */
    private const WEEKDAYS_PT = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * @param list<array{repository: string, email: string, date: string}> $commits
     * @param array<string, int> $languages linguagem => bytes
     */
    public function __construct(
        private readonly array $commits,
        private readonly array $languages,
        private readonly DateTimeZone $timezone = new DateTimeZone('UTC'),
    ) {
    }

    /** Total de commits no ano. */
    public function totalCommits(): int
    {
        return count($this->commits);
    }

    /**
     * Top N linguagens com percentual de bytes.
     *
     * @return list<array{name: string, bytes: int, percent: float}>
     */
    public function topLanguages(int $limit = 5): array
    {
        $sorted = $this->languages;
        arsort($sorted);

        $totalBytes = array_sum($sorted);
        $result = [];

        foreach ($sorted as $name => $bytes) {
            if (count($result) >= $limit) {
                break;
            }

            $result[] = [
                'name' => (string) $name,
                'bytes' => (int) $bytes,
                'percent' => self::percent((int) $bytes, $totalBytes),
            ];
        }

        return $result;
    }

    /** Linguagem com mais bytes, ou null se não houver nenhuma. */
    public function topLanguage(): ?string
    {
        return $this->topLanguages(1)[0]['name'] ?? null;
    }

    /**
     * Ranking dos e-mails com mais commits.
     *
     * @return list<array{email: string, count: int, percent: float}>
     */
    public function emailRanking(int $limit = 5): array
    {
        $counts = [];

        foreach ($this->commits as $commit) {
            $email = strtolower(trim((string) ($commit['email'] ?? '')));

            if ($email === '') {
                continue;
            }

            $counts[$email] = ($counts[$email] ?? 0) + 1;
        }

        arsort($counts);

        $total = $this->totalCommits();
        $result = [];

        foreach ($counts as $email => $count) {
            if (count($result) >= $limit) {
                break;
            }

            $result[] = [
                'email' => (string) $email,
                'count' => $count,
                'percent' => self::percent($count, $total),
            ];
        }

        return $result;
    }

    /**
     * Dia da semana com mais commits.
     *
     * @return array{name: string, count: int}|null
     */
    public function busiestWeekday(): ?array
    {
        $counts = [];

        foreach ($this->commits as $commit) {
            $date = $this->parseDate((string) ($commit['date'] ?? ''));
            if ($date === null) {
                continue;
            }

            $day = (int) $date->format('N');
            $counts[$day] = ($counts[$day] ?? 0) + 1;
        }

        $best = $this->maxEntry($counts);
        if ($best === null) {
            return null;
        }

        return [
            'name' => self::WEEKDAYS_PT[(int) $best['key']] ?? (string) $best['key'],
            'count' => $best['count'],
        ];
    }

    /**
     * Horário (0–23) com mais commits, já convertido para o fuso do card.
     *
     * @return array{hour: int, count: int}|null
     */
    public function busiestHour(): ?array
    {
        $counts = [];

        foreach ($this->commits as $commit) {
            $date = $this->parseDate((string) ($commit['date'] ?? ''));
            if ($date === null) {
                continue;
            }

            $hour = (int) $date->format('G');
            $counts[$hour] = ($counts[$hour] ?? 0) + 1;
        }

        $best = $this->maxEntry($counts);
        if ($best === null) {
            return null;
        }

        return ['hour' => (int) $best['key'], 'count' => $best['count']];
    }

    /**
     * Repositório com mais commits.
     *
     * @return array{name: string, count: int}|null
     */
    public function topRepository(): ?array
    {
        $counts = [];

        foreach ($this->commits as $commit) {
            $repository = trim((string) ($commit['repository'] ?? ''));
            if ($repository === '') {
                continue;
            }

            $counts[$repository] = ($counts[$repository] ?? 0) + 1;
        }

        $best = $this->maxEntry($counts);
        if ($best === null) {
            return null;
        }

        return ['name' => (string) $best['key'], 'count' => $best['count']];
    }

    /**
     * Monta o array completo que o result.php injeta no JavaScript.
     *
     * @return array{
     *     total_commits: int,
     *     languages: list<array{name: string, bytes: int, percent: float}>,
     *     top_language: ?string,
     *     emails: list<array{email: string, count: int, percent: float}>,
     *     weekday: ?array{name: string, count: int},
     *     hour: ?array{hour: int, count: int},
     *     top_repository: ?array{name: string, count: int}
     * }
     */
    public function toArray(): array
    {
        return [
            'total_commits' => $this->totalCommits(),
            'languages' => $this->topLanguages(5),
            'top_language' => $this->topLanguage(),
            'emails' => $this->emailRanking(5),
            'weekday' => $this->busiestWeekday(),
            'hour' => $this->busiestHour(),
            'top_repository' => $this->topRepository(),
        ];
    }

    /** Percentual arredondado em 1 casa decimal, sem dividir por zero. */
    private static function percent(int $part, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round($part * 100 / $total, 1);
    }

    /**
     * Maior contador do array. Em caso de empate, vence o primeiro.
     *
     * @param array<int|string, int> $counts
     * @return array{key: int|string, count: int}|null
     */
    private function maxEntry(array $counts): ?array
    {
        $bestKey = null;
        $bestCount = -1;

        foreach ($counts as $key => $count) {
            if ($count > $bestCount) {
                $bestKey = $key;
                $bestCount = $count;
            }
        }

        if ($bestKey === null) {
            return null;
        }

        return ['key' => $bestKey, 'count' => $bestCount];
    }

    /**
     * Converte a data ISO do GitHub (que chega em UTC) para o fuso do card.
     * Retorna null se a data estiver vazia ou for inválida.
     */
    private function parseDate(string $date): ?DateTimeImmutable
    {
        if ($date === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($date))->setTimezone($this->timezone);
        } catch (Exception $exception) {
            return null;
        }
    }
}
