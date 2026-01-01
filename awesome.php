<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Botão YouTube Clássico Idêntico</title>
    <style>
        body {
            /* Estilos básicos para centralizar o botão na tela */
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f1f1f1;
            margin: 0;
        }

        /* =========================================
           Estilo do Botão YouTube Clássico
           Recriado pixel a pixel.
        ========================================= */
        .yt-classic-btn-subscribe {
            /* Tipografia */
            font-size: 18px;
            font-family: Arial, sans-serif;
            font-weight: normal;
            text-transform: uppercase;
            color: #333;
            text-decoration: none;
            display: inline-block;
            padding: 10px 16px;
            cursor: pointer;
            border-radius: 6px;
            border: 1px solid #ADADAD;
            line-height: 1.3333333;
            background-image: linear-gradient(to bottom, #fff 0, #e0e0e0 100%);
            box-shadow: 
                inset 0 1px 0 rgba(255, 255, 255, 0.4),
                0 1px 0 rgba(0, 0, 0, 0.05);
            text-shadow: 0 1px 0 #fff;
        }

        /* Efeito Hover (para interação) */
        .yt-classic-btn-subscribe:hover {
            color: #787575;
        }

        /* Efeito Active (Pressionado) */
        .yt-classic-btn-subscribe:active {
            color: #333;
            background-image: linear-gradient(to bottom, #ffacac 0, #dc5e5e 100%);
            text-shadow: 0 1px 0 #d25757;
        }
    </style>
</head>
<body>

    <?php
        // Ação de exemplo
        $action_url = '#'; 
    ?>

    <a href="<?php echo $action_url; ?>" class="yt-classic-btn-subscribe" role="button">
        SUBSCRIBE
    </a>

</body>
</html>