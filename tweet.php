<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compor Novo Tweet</title>
    <style>
        /* ======================== */
        /* Estilos Globais e Reset */
        /* ======================== */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f0f4f7; /* Fundo levemente cinza/azul */
            margin: 20px;
            padding: 0;
            color: #333;
            font-size: 13px;
        }

        /* ======================== */
        /* Caixa de Composição (Tweet Box) */
        /* ======================== */
        .tweet-box-container {
            width: 590px;
            height: auto;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #e1e8ed;
        }
        
        /* Cabeçalho da caixa */
        .tweet-header {
            overflow: hidden; /* Para conter os elementos flutuantes */
            margin-bottom: 10px;
        }

        .char-counter {
            float: right;
            font-size: 40px;
            font-weight: normal;
            color: #aaa; /* Cinza claro, como na imagem */
            margin: 2px 0 0 0;
            padding-right: 5px; /* Ajuste fino */
        }

        /* Área de Texto (Textarea) */
        .tweet-textarea {
            font-family: arial;
            background: whitesmoke;
            width: 100%;
            height: 60px; /* Altura da caixa de texto */
            padding: 10px;
            border: 1px solid #5599BB;
            resize: none; /* Desabilita redimensionamento manual */
            font-size: 13px;
            box-sizing: border-box; /* Garante que o padding não aumente a largura total */
            outline: none; /* Remove o contorno padrão ao focar */
            margin-bottom: 8px;
        }
        
        .tweet-textarea:focus {
            border-color: #0084B4; /* Borda azul ao focar */
        }

        /* Área de Ações (Botão e Status) */
        .tweet-actions {
            overflow: hidden;
            padding-top: 5px;
        }
        
        /* Mensagem de Status (Latest) */
        .latest-status {
            float: left;
            font-size: 11px;
            color: #657786;
        }

        /* Botão Tweet */
        .tweet-btn {
            float: right;
            background-color: #f7f7f7; /* Cor de fundo cinza claro */
            color: #555; /* Cor do texto cinza sutil */
            border: 1px solid #ccc; /* Borda cinza clara */
            padding: 6px 15px;
            font-size: 18px;
            font-weight: normal;
            cursor: pointer;
            outline: none;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
        }

        .tweet-btn:hover {
            background-color: #eee;
            border-color: #bbb;
        }
        
        .tweet-btn:active {
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        h2 {
            float: none;
            font-size: 27px;
            font-weight: normal;
            color: #333;
            margin: 10px 0px 0px 0px;
        }

        .tweet-rule {
            width: 100%;
            height: 1px;
            background-color: #ccc;
            margin: 20px 0;
        }

    </style>
</head>
<body>

    <div class="tweet-box-container">
        <div class="tweet-header">
            <h2 style="float: left;">Postar</h2>
            <p class="char-counter">140</p>
        </div>
        <textarea class="tweet-textarea"></textarea>
        <div class="tweet-actions">
            <p class="latest-status">
                faça um tweet de algo que voçe viu ou qualquer coisa.
            </p>
            <button class="tweet-btn">Tweet</button>
        </div>
        <div class="tweet-rule"></div>
        <h2>Seus Posts</h2>
    </div>

</body>
</html>