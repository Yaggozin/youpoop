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
            border-radius: 2px;
            font-family: Verdana, Arial, Helvetica, sans-serif;
            border-style: none;
            color: #ffffffff;
            cursor: pointer;
            font-size: 11px;
            line-height: 23px;
            padding-right: 5px;
            text-align: center;
            text-decoration: none;
            /* background: #de4b39; */
            background-image: linear-gradient(#f35744, #de4b39);
        }

        .yt-classic-btn-subscribe:before {
            background: url(images/youpoophd/share/youtube_icon.png) no-repeat 4px 4px transparent;
            content: "";
            float: left;
            height: 24px;
            margin-right: 5px;
            width: 24px;
            border-right: 1px solid #d04938;
            box-shadow: 1px 0px #f26655;
        }

        /* Efeito Hover (para interação) */
        .yt-classic-btn-subscribe:hover {
            color: #ffffffff;
        }

        /* Efeito Active (Pressionado) */
        .yt-classic-btn-subscribe:active {
            color: #333;
            background: #cc0000cc;
        }

        .number {
            border-radius: 2px;
            background-color: #f0f0f0;
            border: 1px solid #e0e0e0;
            font-size: 11px;
            height: 13px;
            margin-left: 5px;
            padding: 5px;
            position: relative;
            text-align: center;
            width: 45px;
            margin-left: 7px;
            line-height: 13px;
        }

        .number:before {
            content: "";
            border: 5px solid transparent;
            border-right-color: #e0e0e0;
            left: -10px;
            position: absolute;
            top: 30%;
        }

        .number:after {
            content: "";
            border: 5px solid transparent;
            border-right-color: #f0f0f0;
            left: -8px;
            position: absolute;
            top: 30%;
        }

    </style>
</head>
<body>

    <?php
        // Ação de exemplo
        $action_url = '#'; 
    ?>

    <a href="<?php echo $action_url; ?>" class="yt-classic-btn-subscribe" role="button">
        YouPoop
    </a>

    <span href="<?php echo $action_url; ?>" class="number" role="button">
        20
    </span>

</body>
</html>