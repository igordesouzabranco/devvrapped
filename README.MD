<p align="center">
  <img src="DEV-readme.svg" alt="Devvrapped" width="520">
</p>

**Sua retrospectiva anual de commits do GitHub, no estilo Spotify Wrapped**

PHP · cURL · Canvas · PHPMailer · Vercel

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![JavaScript](https://img.shields.io/badge/JavaScript-Canvas-F7DF1E?logo=javascript&logoColor=black)](https://developer.mozilla.org/pt-BR/docs/Web/API/Canvas_API)
[![Composer](https://img.shields.io/badge/Composer-PHPMailer-885630?logo=composer&logoColor=white)](https://getcomposer.org/)
[![GitHub API](https://img.shields.io/badge/GitHub-API%20REST-181717?logo=github&logoColor=white)](https://docs.github.com/pt/rest)
[![Vercel](https://img.shields.io/badge/Vercel-deploy-000000?logo=vercel&logoColor=white)](https://devvrapped-gray.vercel.app)
[![Licença](https://img.shields.io/badge/Licen%C3%A7a-MIT-1ED760)](LICENSE)

**[Testar ao vivo](https://devvrapped-gray.vercel.app)**

---

## Sobre

O Devvrapped lê o seu ano no GitHub (commits, linguagens, horários, repositórios) e transforma tudo em um card vertical 1080×1920, pronto para postar. Você cola a URL do seu perfil, e em alguns segundos baixa o PNG ou recebe por e-mail.

Foi feito para praticar PHP puro: sem framework, sem banco de dados e sem nada salvo entre uma visita e outra. Tudo vive dentro da requisição, e o card existe só na aba do navegador.

## Recursos

- **Total de commits do ano:** só os commits feitos por você, no ano corrente.
- **Linguagens:** top 5 com percentual, somando os bytes de cada linguagem nos seus repositórios.
- **Quando você mais codifica:** dia da semana e horário de pico, já convertidos para o seu fuso.
- **Repositório mais ativo:** o que concentrou mais commits no ano.
- **E-mails mais usados nos commits:** ranking dos e-mails gravados nos seus commits.
- **Card em canvas:** desenhado no navegador com JavaScript puro, em 1080×1920.
- **Download em PNG ou envio por e-mail:** o envio usa PHPMailer via SMTP.
- **Erros amigáveis:** usuário inexistente, token inválido e limite de requisições da API viram mensagens claras em português.

## Como funciona

1. `api/index.php` valida nome, e-mail e URL do perfil no servidor e extrai o username.
2. `src/GitHubClient.php` busca repositórios, linguagens e commits na API do GitHub (paginação, limite de requisições e tratamento de rate limit).
3. `src/Statistics.php` calcula as estatísticas do ano. É uma classe pura: recebe arrays e devolve números, sem I/O.
4. `api/result.php` escapa tudo, injeta o JSON na página e renderiza o resultado.
5. `assets/js/card.js` desenha o card no `<canvas>` e gera o PNG com `toDataURL`.
6. `api/send.php` recebe o PNG em base64 por `fetch`, valida o arquivo (tamanho, assinatura e dimensões) e envia por e-mail.

## Stack

| Tecnologia           | Versão   | Papel                                              |
| -------------------- | -------- | -------------------------------------------------- |
| PHP                  | 8.1+     | Backend, sem framework (`curl`, `json`, `openssl`) |
| cURL                 | —        | Cliente da API do GitHub                           |
| JavaScript           | —        | Desenho do card no canvas, download e envio        |
| HTML5 Canvas         | —        | Geração da imagem 1080×1920                        |
| PHPMailer            | ^7.2     | Envio do PNG por SMTP                              |
| Composer             | —        | Gerenciador de dependências                        |
| Vercel (`vercel-php`)| 0.7.3    | Deploy                                             |

## Como rodar

**Requisitos:** PHP 8.1 ou superior (com as extensões `curl`, `json` e `openssl`) e [Composer](https://getcomposer.org/).

```bash
git clone https://github.com/igordesouzabranco/devvrapped.git
cd devvrapped
composer install
```

Crie um arquivo `.env` na raiz (ele já está no `.gitignore`):

```env
GITHUB_TOKEN=seu_token_do_github
TIMEZONE=America/Sao_Paulo

SMTP_HOST=smtp.exemplo.com
SMTP_PORT=587
SMTP_USER=seu_usuario
SMTP_PASS=sua_senha
SMTP_FROM_EMAIL=nao-responda@exemplo.com
SMTP_FROM_NAME=Devvrapped
```

Todas as variáveis são opcionais para ver o Wrapped e baixar o PNG. O SMTP só é necessário para o envio por e-mail.

Depois suba o servidor:

```bash
php -S localhost:8000 router.php
```

Abra `http://localhost:8000`, cole a URL do seu perfil do GitHub e gere o seu Wrapped.

> O `router.php` replica os rewrites do `vercel.json` para o servidor embutido do PHP, já que as páginas ficam em `api/`. Sem ele, `php -S` retorna 404.

### Variáveis de ambiente

| Variável          | O que é                                                    | Padrão              |
| ----------------- | ---------------------------------------------------------- | ------------------- |
| `GITHUB_TOKEN`    | Token do GitHub. Sem ele, o limite é de 60 requisições/hora | vazio               |
| `TIMEZONE`        | Fuso usado nos horários e dias do card                     | `America/Sao_Paulo` |
| `SMTP_HOST`       | Servidor SMTP                                              | vazio               |
| `SMTP_PORT`       | `587` (STARTTLS) ou `465` (SMTPS)                          | `587`               |
| `SMTP_USER`       | Usuário do SMTP                                            | vazio               |
| `SMTP_PASS`       | Senha do SMTP                                              | vazio               |
| `SMTP_FROM_EMAIL` | Remetente do e-mail                                        | vazio               |
| `SMTP_FROM_NAME`  | Nome do remetente                                          | `Devvrapped`        |

O `config.php` lê essas variáveis do `.env` (local) ou do ambiente (produção) e não guarda nenhum segredo.

## Produção

| Peça   | Onde                                                  |
| ------ | ----------------------------------------------------- |
| App    | [Vercel](https://devvrapped-gray.vercel.app) com `vercel-php` |
| Segredos | Variáveis de ambiente no painel da Vercel           |

O `vercel.json` aponta cada função PHP de `api/` para o runtime `vercel-php` e faz os rewrites de `/`, `/index.php` e `/send.php`. Para publicar a sua cópia, importe o repositório na Vercel e cadastre as mesmas variáveis do `.env`.

## Rotas

| Método | Rota         | Descrição                                           | Respostas             |
| ------ | ------------ | --------------------------------------------------- | --------------------- |
| GET    | `/`          | Formulário com nome, e-mail e URL do perfil         | 200                   |
| POST   | `/`          | Valida os campos e mostra o resultado com o card    | 200 (erros no campo)  |
| POST   | `/send.php`  | Envia o PNG do card por e-mail (JSON)               | 200 / 400 / 405 / 413 / 422 / 502 |

Corpo do `POST /send.php`:

```json
{
  "email": "voce@exemplo.com",
  "image": "data:image/png;base64,iVBORw0KGgo..."
}
```

Resposta (sempre JSON):

```json
{
  "ok": true,
  "message": "PNG enviado para voce@exemplo.com!"
}
```

| Status | Quando acontece                                                |
| ------ | -------------------------------------------------------------- |
| 400    | Corpo ilegível ou JSON inválido                                |
| 405    | Método diferente de POST                                       |
| 413    | Imagem maior que 4 MB                                          |
| 422    | E-mail inválido, arquivo que não é PNG ou dimensões acima de 1080×1920 |
| 502    | Falha no SMTP (o detalhe fica só no `error_log` do servidor)   |

## Segurança

- Validação sempre no servidor: o `novalidate` do formulário só melhora a experiência.
- Todo texto exibido passa por `htmlspecialchars`, e o JSON da API é injetado com `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.
- Token do GitHub sanitizado contra injeção de header (`\r\n`).
- `send.php` aceita só POST, limita o corpo a 6 MB, confere a assinatura binária do PNG e as dimensões máximas antes de qualquer outra coisa.
- Falhas de e-mail viram uma mensagem genérica para a pessoa, e o detalhe técnico fica só no log.
- Segredos ficam fora do código: `.env` no `.gitignore` e variáveis de ambiente na Vercel.

## Limitações

- Considera só repositórios públicos próprios, sem forks.
- Os commits contam pelo username (`author`), então só entram commits cujo e-mail está vinculado à conta no GitHub.
- A porcentagem de linguagens vem dos bytes dos repositórios, não apenas dos commits do ano.
- Cada Wrapped faz no máximo 500 requisições à API. Perfis enormes podem estourar esse teto.
- Sem token, o GitHub limita a 60 requisições por hora por IP.

## Roadmap

- Testes automatizados para a classe `Statistics`, que já é pura e fácil de testar.
- Escolher o ano do Wrapped (o `GitHubClient` já aceita o ano, a interface ainda usa o corrente).
- Reduzir o número de requisições usando a API GraphQL do GitHub em vez de paginar commit por commit.

## Estrutura

```
devvrapped/
├── api/
│   ├── index.php                  # formulário + validação no servidor
│   ├── result.php                 # página do resultado (mesma requisição)
│   └── send.php                   # envio do PNG por e-mail (JSON)
├── src/
│   ├── GitHubClient.php           # cliente cURL da API do GitHub
│   ├── GitHubApiException.php     # erro amigável (404, 401, rate limit…)
│   └── Statistics.php             # cálculos puros das estatísticas
├── assets/
│   ├── css/style.css              # tema escuro neon
│   └── js/card.js                 # desenho do card no canvas + download/envio
├── config.php                     # lê o .env / variáveis de ambiente
├── router.php                     # rewrites para rodar localmente (php -S)
├── vercel.json                    # runtime vercel-php e rewrites
├── composer.json                  # dependência: PHPMailer
├── DEV.svg                        # logo original (preto)
├── DEV-logo.svg                   # logo neon usada no site
├── DEV-readme.svg                 # logo com fundo branco (este README)
└── README.md
```

## Licença

[MIT](LICENSE) © Igor de Souza Branco
