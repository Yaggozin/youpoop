<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Botão Estilizado</title>
    <!-- Carregando Tailwind CSS para estilização do container e corpo --><script src="https://cdn.tailwindcss.com"></script>
    <style>

        /* Estilos específicos para o botão, conforme suas especificações */
        .meu-botao {
            /* Estilos de fundo e borda */
            color: #555;
            font-family: arial;
            border: 1px solid;
            border-color: #ccc #ccc #aaa;
            background-color: #e0e0e0;
            background-image: linear-gradient(to bottom, #fafafa 0, #dcdcdc 100%);
            box-shadow: inset 0 0 1px #fff;
            
            /* Estilos de texto e tipografia */
            text-shadow: 0 1px 0 #fff;
            font-weight: bold;
            font-size: 11px;
            white-space: nowrap;
            word-wrap: normal;
            
            /* Dimensões e espaçamento */
            height: 2.95em;
            padding: 0 .91em;
            vertical-align: middle;
            
            /* Interatividade */
            outline: 0;
            cursor: pointer;
            transition: all 0.1s ease-in-out;
            
            /* Propriedade Border-radius */
            -moz-border-radius: 2px;
            -webkit-border-radius: 2px;
            border-radius: 2px;

            /* Adicionado para centralizar e alinhar itens, incluindo o SVG */
            display: inline-flex;
            align-items: center;
            justify-content: center;

            /* Adicionado para posicionamento relativo do tooltip */
            position: relative; 
        }

        /* Estilo para a seta SVG (padrão) */
        .meu-botao .seta-icone {
            margin-left: 10px;
            width: 8px;
            height: 8px;
            fill: #555;
            transition: transform 0.1s ease-in-out;
        }
        
        /* Efeito de HOVER para melhor feedback visual (estado DESLIGADO) */
        .meu-botao:hover {
            background-image: linear-gradient(to bottom, #ffffff 0, #d3d3d3 100%);
            border-color: #aaa #aaa #999;
        }

        /* Efeito de ATIVO (pressionado) */
        .meu-botao:active {
            background-image: linear-gradient(to bottom, #dcdcdc 0, #fafafa 100%);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
            transform: translateY(1px);
        }
        
        /* ESTILO DO BOTÃO "LIGADO" */
        .btn-ligado {
            background-color: #4f74d0; 
            background-image: linear-gradient(to bottom, #5980e1 0, #4f74d0 100%);
            border-color: #3759ad;
            color: #ffffff;
            text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4);
        }

        /* Ajuste do ícone para o estado Ligado */
        .btn-ligado .seta-icone {
            fill: #ffffff;
        }

        /* Efeito HOVER no estado Ligado */
        .btn-ligado:hover {
            background-image: linear-gradient(to bottom, #608ce8 0, #547cd6 100%);
            border-color: #3759ad;
        }

        /* Efeito ACTIVE no estado Ligado */
        .btn-ligado:active {
            background-image: linear-gradient(to bottom, #4f74d0 0, #5980e1 100%);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.4);
            transform: translateY(1px);
        }

        /* NOVOS ESTILOS PARA O TOOLTIP */
        .tooltip-box {
            font-weight: normal;
            position: absolute;
            bottom: calc(100% + 10px); /* Posiciona acima do botão + espaço para a seta */
            left: 50%;
            transform: translateX(-50%); /* Centraliza horizontalmente */
            background: linear-gradient(0deg, black, #474747); /* Fundo escuro */
            color: #fff; /* Texto branco */
            padding: 8px 12px;
            border-radius: 2px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 10;
            opacity: 0; /* Começa invisível */
            visibility: hidden; /* Garante que não interfira no layout */
            transition: opacity 0.3s ease, visibility 0.3s ease; /* Transição suave */
            pointer-events: none; /* Permite clicar no botão por trás do tooltip invisível */
        }

        /* Estilo da seta (triângulo) do tooltip */
        .tooltip-box::after {
            content: '';
            position: absolute;
            top: 100%; /* Posiciona a seta na parte de baixo do tooltip */
            left: 50%;
            transform: translateX(-50%);
            border-width: 6px; /* Tamanho da seta */
            border-style: solid;
            border-color: #333 transparent transparent transparent; /* Cor da seta para cima */
        }

        /* Estado visível do tooltip */
        .meu-botao:hover .tooltip-box {
            opacity: 1;
            visibility: visible;
        }

    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center font-[Inter]">

    <div class="bg-white p-8 rounded-lg shadow-xl max-w-sm w-full text-center">
        <h1 class="text-xl font-semibold mb-4 text-gray-800">Teste de Botão Estilizado</h1>
        <p class="text-sm text-gray-600 mb-6">Este botão agora tem um tooltip que aparece ao passar o mouse.</p>

        <!-- O Botão HTML com a classe personalizada, a seta SVG e o Tooltip --><button id="testeBotao" class="meu-botao">
            2 vídeos
            <!-- Ícone de seta para baixo em SVG --><svg class="seta-icone" viewBox="0 0 10 6" xmlns="http://www.w3.org/2000/svg">
                <path d="M5 6L0 0h10L5 6z"/>
            </svg>
            <!-- O Tooltip --><span class="tooltip-box">Eu gosto disso</span>
        </button>
        
        <!-- Área para exibir a mensagem JavaScript --><div id="mensagem" class="mt-6 p-3 text-sm rounded-lg border border-gray-200 min-h-[40px] text-gray-700 bg-gray-50">
            Estado: Desligado (cor original).
        </div>
    </div>

    <script>
        // JavaScript para interatividade (lógica de LIGA/DESLIGA)
        document.addEventListener('DOMContentLoaded', function() {
            // Referências aos elementos HTML
            const botao = document.getElementById('testeBotao');
            const mensagem = document.getElementById('mensagem');
            
            // Variável de estado para rastrear se o botão está ligado ou desligado
            let isLigado = false; 

            // Função para manipular o clique no botão
            botao.addEventListener('click', function() {
                // Inverte o estado (de falso para verdadeiro, ou de verdadeiro para falso)
                isLigado = !isLigado;

                // Alterna a classe CSS 'btn-ligado' no botão
                botao.classList.toggle('btn-ligado', isLigado);
                
                // Atualiza a área de mensagem para confirmar a ação
                if (isLigado) {
                    mensagem.textContent = '✅ Ligado! O botão está agora com a cor "Ativa".';
                    // Estilos da mensagem no estado LIGADO
                    mensagem.classList.remove('bg-gray-50', 'text-gray-700', 'border-gray-200');
                    mensagem.classList.add('bg-blue-100', 'text-blue-800', 'border-blue-300', 'font-medium');
                } else {
                    mensagem.textContent = '❌ Desligado! O botão voltou para a cor original.';
                    // Estilos da mensagem no estado DESLIGADO
                    mensagem.classList.remove('bg-blue-100', 'text-blue-800', 'border-blue-300', 'font-medium');
                    mensagem.classList.add('bg-gray-50', 'text-gray-700', 'border-gray-200');
                }
                
                console.log('Botão Clicado. Estado atual: ' + (isLigado ? 'Ligado' : 'Desligado'));
            });
            
            // Configura a mensagem inicial
            mensagem.textContent = 'Estado: Desligado (cor original).';
        });
    </script>

</body>
</html>
