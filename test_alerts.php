<?php
// Inclui o ficheiro que criámos anteriormente
require_once 'alerts.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Teste de Alertas - Cosmic Panda 2012</title>
    <style>
        body {
            background-color: #ebebeb; /* Cor de fundo típica do YT 2012 */
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .container {
            width: 970px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            min-height: 400px;
        }
        h2 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>Página de Teste de Alertas</h2>
        <p>Abaixo estão os exemplos dos alertas aplicados com o teu CSS:</p>

        <?php
        // Teste 1: Alerta de Informação (Azul)
        show_alert("Voce não está logado ainda, faça o login e habilite novas funções para sua conta.", "info");

        // Teste 2: Alerta de Erro/Privacidade (Vermelho - Padrão)
        show_alert("Ocorreu um problema ao carregar as tuas definições de privacidade.");

        // Teste 3: Alerta de Atividade (Podes usar o tipo 'ok' também)
        show_alert("A tua atividade foi publicada com sucesso no feed.", "ok");

        ?>

        <div style="margin-top: 50px; color: #666; font-size: 12px;">
            <p>Nota: Certifica-te que a imagem <code>images/exclamacao_icon.png</code> existe para o ícone aparecer.</p>
        </div>
    </div>

</body>
</html>