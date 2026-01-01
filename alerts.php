<?php
/**
 * Sistema de Alertas Cosmic Panda 2011/2012
 * Tipos: 'info' (azul), 'error' (vermelho/padrão)
 */
function show_alert($message, $type = 'error') {
    $class = '';
    $icon_file = 'exclamacao_icon.png'; // Ícone padrão

    if ($type == 'info' || $type == 'blue') {
        $class = 'alert-info';
        $icon_file = 'obey_icon.png';
    } elseif ($type == 'ok' || $type == 'green') {
        $class = 'alert-yeah';
        $icon_file = 'check_icon.png';
    } elseif ($type == 'orange') {
        $class = 'alert-orange';
        $icon_file = 'exclamacao_icon.png';
    } elseif ($type == 'red' || $type == 'error') {
        $icon_file = 'exclamacao_icon.png';
    }
    ?>
    <style>
        .alert {
            width: 970px;
            justify-self: center;
            font-weight: bolder;
            text-shadow: 0 -1px #00000096;
            padding: 10px;
            margin: 13px auto;
            font-size: 84%;
            color: white;
            text-align: center;
            -webkit-box-shadow: 0 1px 2px rgba(0,0,0,.5),inset 0 0 1px rgba(0,0,0,.2);
            box-shadow: 0 1px 2px rgba(0,0,0,.5),inset 0 0 1px rgba(0,0,0,.2);
            -moz-border-radius: 3px;
            -webkit-border-radius: 3px;
            border-radius: 3px;
            text-align: center;
            display: flex;
            align-items: center; 
            justify-content: space-between;
            box-sizing: border-box;
        }

        .alert-icon {
            background-size: auto;
            width: 21px;
            height: 21px;
            vertical-align: middle;
            cursor: auto;
            float: inline-start;
        }

        /* Alerta Vermelho (Privacidade/Erro) */
        .alert {
            background-color: rgb(145, 61, 55);
            background-image: linear-gradient(rgb(201, 81, 69) 0px, rgb(145, 61, 55) 45px);
        }

        /* Alerta Azul (Informação) */
        .alert-info {
            background-color: #6683b3;
            background-image: linear-gradient(to bottom,#849fc2 0,#6683b3 100%);
        }

        /* Alerta Verde (Informação) */
        .alert-yeah {
            background-color: #70B75E;
            background-image: linear-gradient(to bottom, #70B75E 0, #38652E 100%);
        }

        .alert-orange {
            background-color: #cd6627;
            background-image: linear-gradient(to bottom, #e08a25 0, #cd6627 100%);
        }

        .alert-content {
            display: inline-block;
            width: 90%;
        }

        .alert-content a {
            color: white;
        }

        .close-x {
            float: right;
            cursor: pointer;
            text-decoration: none;
            color: white;
            opacity: 0.7;
            font-size: 22px;
            border-radius: 3px;
            width: 22px;
            height: 22px;
        }

        .close-x:hover {
            background-color: rgba(0,0,0,.15);
        }
    </style>

    <div class="alert <?php echo $class; ?>" id="yt-alert">
        <img class="alert-icon" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" style="background: url('images/<?php echo $icon_file; ?>') no-repeat center;">
        <span class="alert-content">
            <?php echo $message; ?>
        </span>
        <a class="close-x" onclick="this.parentElement.style.display='none';">×</a>
    </div>
    <?php
}
?>