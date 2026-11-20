<?php

$aviso = '';

if ($_POST['nome'] !== '' && $_POST['email'] !== '' && $_POST['github'] !== '') {
    $aviso = 'Dados salvos com sucesso!';
} else {
    $aviso = 'Preencha todos os campos!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>DEVVRAPPED</title>
</head>
<body>

<h1 class="title">DEVVRAPPED</h1>

<h3 class="subtitle">Sua retrospectiva anual de linguagens de programação</h3>


<div class="formulario-github">
    <form action="index.php" method="POST">
        <label class="label">Seu Nome</label>
        <input type="text" name="nome" id="nome">
        <label class="label">Seu Email</label>
        <input type="text" name="email" id="email">
        <label class="label">Seu Github</label>
        <input type="text" name="github" id="github">
        <input type="submit" value="Enviar" class="button"/>
    </form>
</div>
<?php if ($_POST['nome'] !== '' && $_POST['email'] !== '' && $_POST['github'] !== ''): ?>
    <div class="alerta-bom"><?= htmlspecialchars($aviso) ?></div>
<?php else: ?>
    <div class="alerta-ruim"><?= htmlspecialchars($aviso) ?></div>
<?php endif; ?>
</body>
