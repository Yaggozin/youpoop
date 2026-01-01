<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mozilla Firefox Start Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: sans-serif; /* Fonte padrão do sistema, como era no original */
            background-color: #ffffff;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* O efeito de horizonte azulado sutil no meio da tela */
        .horizon-bg {
            background: linear-gradient(to bottom, #ffffff 0%, #edf6ff 45%, #e0ecf8 50%, #ffffff 100%);
            height: 100%;
            width: 100%;
            position: absolute;
            z-index: -1;
            top: 0;
            left: 0;
        }

        /* Estilo clássico do botão de busca */
        .btn-search {
            background: #f0f0f0; /* Cinza claro */
            border: 1px solid #7f9db9; /* Borda azulada padrão Windows XP/antigo */
            color: #777777;
            font-size: 16px;
            padding: 2px 6px;
            cursor: pointer;
        }
        .btn-search:hover {
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        /* Estilo da caixa de texto */
        .input-search {
            border: 1px solid #7f9db9;
            padding: 4px;
            font-size: 14px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }

        /* Logo do Google feito com CSS para garantir que carregue sempre */
        /* (Estilos removidos pois agora usamos imagem, mas mantive o bloco vazio ou pode remover) */
        
        /* Caixa de notificação estilo "balão" */
        .notification-box {
            background-color: #fffff0; /* Fundo levemente amarelado/branco */
            border: 1px solid #dcdcdc;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        /* Links do rodapé */
        .footer-link {
            color: #0000EE; /* Azul padrão de link antigo */
            text-decoration: none;
            font-size: 11px;
        }
        .footer-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen relative">

    <!-- Fundo com gradiente sutil -->
    <div class="horizon-bg"></div>

    <!-- Conteúdo Principal Centralizado -->
    <div class="flex-grow flex flex-col items-center justify-center -mt-20">
        
        <!-- Logo Firefox -->
        <div class="mb-8">
            <img src="https://upload.wikimedia.org/wikipedia/commons/2/26/Mozilla_Firefox_logo_2004.svg" 
                 alt="Firefox Logo" 
                 class="w-32 h-32 md:w-40 md:h-40 drop-shadow-lg">
        </div>

        <!-- Área de Busca -->
        <div class="flex items-center justify-center gap-2 mb-10 w-full max-w-2xl px-4">
            <!-- Logo Google (Imagem 2010-2013) -->
            <img src="https://upload.wikimedia.org/wikipedia/commons/8/8d/Google_logo_%282010-2013%29.svg" 
                 alt="Google" 
                 class="h-9 mr-1 select-none">
            
            <!-- Formulário -->
            <form action="https://www.google.com/search" method="get" target="_blank" class="flex items-center gap-2 w-full">
                <input type="text" name="q" class="input-search flex-grow outline-none" autofocus>
                <button type="submit" class="btn-search">Search</button>
            </form>
        </div>

        <!-- Caixa de Notificação "Features" -->
        <div class="notification-box flex items-start p-3 max-w-xl w-full rounded-sm">
            <!-- Ícone da casa (simulado com SVG) -->
            <div class="mr-3 mt-1">
                <div class="bg-blue-500 p-1 rounded-sm shadow-sm border border-blue-700">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
            </div>
            <!-- Texto -->
            <div class="text-sm text-gray-700 leading-snug">
                <span class="font-bold">Thanks for choosing Firefox!</span> To get the most out of your browser, learn more about the <a href="#" class="text-blue-600 hover:underline">latest features</a>.
            </div>
            <!-- Botão de fechar (X) -->
            <button class="ml-auto text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

    </div>

    <!-- Rodapé -->
    <footer class="pb-4 w-full text-center">
        <div class="flex justify-center space-x-6 text-xs text-gray-500">
            <a href="https://www.mozilla.org/about/" class="footer-link">About Mozilla</a>
            <a href="#" class="footer-link">Set Up Sync</a>
            <a href="#" class="footer-link">Pair a Device</a>
        </div>
        <div class="mt-2 text-[10px] text-gray-400">
            &copy; 2004-2025 YouPoop Corporation
        </div>
    </footer>

</body>
</html>