/**
 * Etapa 4 — desenha o card vertical de 1080x1920 no <canvas>.
 *
 * Os dados chegam prontos do PHP em window.DEVVRAPPED (JSON injetado
 * no result.php). Este arquivo não calcula nada: só desenhar.
 */
(function () {
    'use strict';

    const data = window.DEVVRAPPED;
    const canvas = document.getElementById('card');

    if (!data || !canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');
    const W = canvas.width;               // 1080
    const H = canvas.height;              // 1920
    const M = 80;                         // margem lateral
    const CONTENT = W - M * 2;            // 920 de largura útil

    const C = {
        bg: '#0b0b0f',
        panel: '#17171d',
        track: '#2a2a33',
        teal: '#00ffdd',
        pink: '#ff2d75',
        yellow: '#ffd60a',
        green: '#1ed760',
        violet: '#7c6cff',
        text: '#f2f2f2',
        muted: '#8a8a94',
    };

    const FONT = '"Segoe UI", Arial, sans-serif';
    const HEAVY = '"Arial Black", "Segoe UI", Arial, sans-serif';
    const BAR_COLORS = [C.teal, C.pink, C.yellow, C.green, C.violet];

    /** Corta o texto para não estourar a margem do card. */
    function truncate(text, maxChars) {
        const value = String(text == null ? '' : text);
        return value.length > maxChars ? value.slice(0, maxChars - 1) + '…' : value;
    }

    function panel(x, y, w, h, color) {
        ctx.fillStyle = color;
        if (typeof ctx.roundRect === 'function') {
            ctx.beginPath();
            ctx.roundRect(x, y, w, h, 24);
            ctx.fill();
        } else {
            ctx.fillRect(x, y, w, h);
        }
    }

    /** Título de seção em caixa alta; devolve a nova posição do cursor Y. */
    function sectionTitle(y, text) {
        ctx.fillStyle = C.teal;
        ctx.font = '700 30px ' + FONT;
        ctx.textAlign = 'left';
        ctx.textBaseline = 'alphabetic';
        ctx.fillText(text.toUpperCase(), M, y);

        return y + 50;
    }

    function drawHeader(y) {
        ctx.textAlign = 'left';
        ctx.textBaseline = 'alphabetic';

        ctx.fillStyle = C.teal;
        ctx.font = '900 96px ' + HEAVY;
        ctx.fillText('DEVVRAPPED', M, y);

        ctx.fillStyle = C.text;
        ctx.font = '900 170px ' + HEAVY;
        ctx.fillText(String(data.year), M, y + 160);

        ctx.fillStyle = C.muted;
        ctx.font = '600 40px ' + FONT;
        ctx.fillText(truncate(data.name + '  (@' + data.username + ')', 32), M, y + 230);

        return y + 280;
    }

    function drawTotal(y) {
        const h = 240;
        panel(M, y, CONTENT, h, C.panel);

        ctx.fillStyle = C.teal;
        ctx.font = '900 140px ' + HEAVY;
        ctx.textAlign = 'left';
        ctx.fillText(String(data.total_commits), M + 40, y + 155);

        ctx.fillStyle = C.muted;
        ctx.font = '700 32px ' + FONT;
        ctx.fillText('COMMITS EM ' + data.year, M + 40, y + 210);

        return y + h + 60;
    }

    function drawLanguages(y) {
        const languages = data.languages || [];
        if (languages.length === 0) {
            return y;
        }

        y = sectionTitle(y, 'Linguagens do ano');

        languages.forEach(function (lang, index) {
            const color = BAR_COLORS[index % BAR_COLORS.length];
            const barY = y + 12;
            const barH = 36;

            ctx.fillStyle = C.text;
            ctx.font = '700 36px ' + FONT;
            ctx.textAlign = 'left';
            ctx.fillText(truncate(lang.name, 20), M, y);

            ctx.fillStyle = color;
            ctx.textAlign = 'right';
            ctx.fillText(lang.percent + '%', M + CONTENT, y);

            // trilho cinza + preenchimento colorido na proporção do percentual
            ctx.fillStyle = C.track;
            ctx.fillRect(M, barY, CONTENT, barH);
            ctx.fillStyle = color;
            ctx.fillRect(M, barY, CONTENT * (lang.percent / 100), barH);

            y = barY + barH + 30;
        });

        return y + 20;
    }

    function drawDetails(y) {
        const rows = [];

        if (data.weekday) {
            rows.push(['DIA FAVORITO', data.weekday.name + ' (' + data.weekday.count + ')']);
        }
        if (data.hour) {
            rows.push(['HORÁRIO DE PICO', data.hour.hour + 'h (' + data.hour.count + ')']);
        }
        if (data.top_repository) {
            rows.push([
                'REPOSITÓRIO TOP',
                truncate(data.top_repository.name, 22) + ' (' + data.top_repository.count + ')',
            ]);
        }
        if (data.top_language) {
            rows.push(['LINGUAGEM PRINCIPAL', data.top_language]);
        }

        if (rows.length === 0) {
            return y;
        }

        y = sectionTitle(y, 'Seu ano em detalhes');

        rows.forEach(function (row) {
            ctx.textBaseline = 'alphabetic';

            ctx.fillStyle = C.muted;
            ctx.font = '700 28px ' + FONT;
            ctx.textAlign = 'left';
            ctx.fillText(row[0], M, y);

            ctx.fillStyle = C.text;
            ctx.font = '700 34px ' + FONT;
            ctx.textAlign = 'right';
            ctx.fillText(truncate(row[1], 30), M + CONTENT, y);

            y += 56;
        });

        return y + 20;
    }

    function drawEmails(y) {
        const emails = data.emails || [];
        if (emails.length === 0) {
            return y;
        }

        y = sectionTitle(y, 'E-mails com mais commits');

        emails.slice(0, 3).forEach(function (entry, index) {
            ctx.textBaseline = 'alphabetic';

            ctx.fillStyle = BAR_COLORS[index % BAR_COLORS.length];
            ctx.font = '900 34px ' + HEAVY;
            ctx.textAlign = 'left';
            ctx.fillText(String(index + 1) + 'º', M, y);

            ctx.fillStyle = C.text;
            ctx.font = '600 32px ' + FONT;
            ctx.fillText(truncate(entry.email, 28), M + 90, y);

            ctx.fillStyle = C.muted;
            ctx.font = '600 30px ' + FONT;
            ctx.textAlign = 'right';
            ctx.fillText(entry.count + ' commits', M + CONTENT, y);

            y += 54;
        });

        return y + 20;
    }

    function drawFooter() {
        ctx.textBaseline = 'alphabetic';

        ctx.fillStyle = C.muted;
        ctx.font = '600 30px ' + FONT;
        ctx.textAlign = 'left';
        ctx.fillText('github.com/' + data.username, M, H - 70);

        ctx.fillStyle = C.teal;
        ctx.textAlign = 'right';
        ctx.fillText('DEVVRAPPED', M + CONTENT, H - 70);
    }

    function draw() {
        // fundo + faixa de destaque no topo
        ctx.fillStyle = C.bg;
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = C.teal;
        ctx.fillRect(0, 0, W, 18);

        let y = 160;
        y = drawHeader(y);
        y = drawTotal(y);
        y = drawLanguages(y);
        y = drawDetails(y);
        y = drawEmails(y);
        drawFooter();

        // Depuração: avisa se o conteúdo passou da área segura do card.
        if (y > H - 140) {
            console.warn('DEVVRAPPED: conteúdo do card estourou o limite (y=' + y + ')');
        }
    }

    function baixarImagem() {
        const link = document.createElement('a');
        link.download = 'devvrapped-' + data.username + '-' + data.year + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    function atualizarStatus(texto, tipo) {
        const status = document.getElementById('status-email');
        if (!status) {
            return;
        }

        status.textContent = texto;
        if (tipo === 'ok') {
            status.className = 'alerta-bom';
        } else if (tipo === 'erro') {
            status.className = 'alerta-ruim';
        } else {
            status.className = 'dica';
        }
    }

    async function enviarPorEmail() {
        const email = botaoEnviar.getAttribute('data-email');

        botaoEnviar.disabled = true;
        atualizarStatus('Enviando o PNG...', 'neutro');

        try {
            const resposta = await fetch('send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    image: canvas.toDataURL('image/png'),
                    email: email,
                }),
            });

            const resultado = await resposta.json();
            atualizarStatus(resultado.message, resultado.ok ? 'ok' : 'erro');
        } catch (erro) {
            atualizarStatus('Não consegui falar com o servidor. Tente de novo.', 'erro');
        } finally {
            botaoEnviar.disabled = false;
        }
    }

    const botaoBaixar = document.getElementById('baixar');
    if (botaoBaixar) {
        botaoBaixar.addEventListener('click', baixarImagem);
    }

    const botaoEnviar = document.getElementById('enviar');
    if (botaoEnviar) {
        botaoEnviar.addEventListener('click', enviarPorEmail);
    }

    // Espera as fontes carregarem para não desenhar com a fonte trocada.
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(draw);
    } else {
        draw();
    }
})();
