<p align="center">
  <img src="DEV-readme.svg" alt="Devvrapped" width="520">
</p>

**Sua retrospectiva anual de commits do GitHub, no estilo Spotify Wrapped.**

O Devvrapped lê o seu ano no GitHub (commits, linguagens, horários,
repositórios), monta um card 1080×1920 no canvas e permite baixar em PNG
ou enviar por e-mail. Tudo vive na requisição: sem banco de dados, sem
framework, sem nada salvo entre uma visita e outra.

## Como funciona

1. `index.php` valida nome, e-mail e URL do perfil do GitHub no servidor.
2. `src/GitHubClient.php` busca repos e commits na API do GitHub
   (paginação, limite de requisições, tratamento de rate limit).
3. `src/Statistics.php` calcula as estatísticas puras do ano
   (linguagens, dia/hora de pico, ranking de e-mails, repo top).
4. `result.php` escapa tudo, injeta o JSON com flags `JSON_HEX_*` e
   renderiza a página com o botão de download e o de envio.
5. `assets/js/card.js` desenha o card no canvas (`#card`, 1080×1920)
   e gera o PNG com `toDataURL`.
6. `send.php` recebe o PNG em base64 por `fetch`, valida o arquivo
   (tamanho, magic bytes, dimensões) e envia por e-mail via PHPMailer.

## Requisitos

- PHP 8.1 ou superior com as extensões `curl`, `json` e `openssl`
- [Composer](https://getcomposer.org/) (para instalar o PHPMailer)
- Um token do GitHub opcional, mas recomendado (`GITHUB_TOKEN`)

## Instalação

```bash
git clone https://github.com/seu-usuario/devvrapped.git
cd devvrapped
composer install
```

Edite `config.php` (o arquivo é ignorado pelo git — não vai para o
repositório):

| Constante        | O que é                                                |
|------------------|--------------------------------------------------------|
| `GITHUB_TOKEN`   | Token de acesso do GitHub. Vazio = 60 requisições/hora |
| `TIMEZONE`       | Fuso das datas do card. Padrão: `America/Sao_Paulo`    |
| `SMTP_HOST`      | Servidor de e-mail. Mantenha o placeholder = sem envio |
| `SMTP_PORT`      | 587 (STARTTLS) ou 465 (SMTPS)                          |
| `SMTP_USER`      | Usuário do SMTP                                        |
| `SMTP_PASS`      | Senha do SMTP                                          |
| `SMTP_FROM_EMAIL`| Remetente do e-mail                                    |
| `SMTP_FROM_NAME` | Nome do remetente                                      |

Rode localmente:

```bash
php -S localhost:8000
```

Abra `http://localhost:8000`, cole a URL do seu perfil do GitHub e
gerando o Wrapped.

## Estrutura

```
devvrapped/
├── index.php              # formulário + validação no servidor
├── result.php             # página do resultado (mesma requisição)
├── send.php               # envio do PNG por e-mail (JSON)
├── config.php             # segredos (gitignored)
├── src/
│   ├── GitHubClient.php       # cliente cURL da API do GitHub
│   ├── GitHubApiException.php # erro amigável (404, 401, rate limit…)
│   └── Statistics.php         # cálculos puros das estatísticas
├── assets/
│   ├── css/style.css      # tema escuro neon
│   └── js/card.js         # desenho do card no canvas + download/envio
├── DEV.svg                # logo original (preto)
├── DEV-logo.svg           # logo neon usada no site
└── DEV-readme.svg         # logo com fundo branco (este README)
```

## Segurança

- Validação sempre no servidor (`novalidate` só melhora a experiência).
- Todo texto exibido passa por `htmlspecialchars` e o JSON da API é
  injetado com `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.
- Token do GitHub sanitizado contra injeção de header (`\r\n`).
- `send.php` aceita só POST, limita o corpo a 6 MB, confere a assinatura
  binária do PNG e as dimensões máximas (1080×1920) antes de qualquer coisa.
- Falhas de e-mail viram mensagem genérica no usuário; o detalhe fica no
  `error_log` do servidor.

## Licença

[MIT](LICENSE)
