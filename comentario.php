<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caixa de Comentários Funcional</title>
    <style>
        /* Estilos gerais */
        body {
            font-family: Arial, sans-serif;
            background-color: #f1f1f1;
            padding: 20px;
        }

        .comment-box-container {
            width: 500px;
            background-color: #fff;
            padding: 10px;
            border: 1px solid #ccc;
            max-width: 100%;
        }

        /* Linha superior: Avatar e campo de texto */
        .comment-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        /* Estilizando o Avatar */
        .avatar-wrapper {
            margin-right: 10px;
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            position: relative;
        }
        
        /* Avatar AGORA QUADRADO e COM FUNDO BRANCO LEVEMENTE ESCURO */
        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background-color: #e0e0e0; /* Cor branca um pouco mais escura */
        }
        
        /* Estilo para simular o texto "F | F 22" */
        .avatar-text-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            line-height: 1;
            text-align: center;
            color: #333;
            font-weight: bold;
            pointer-events: none; /* Permite clicar na imagem por baixo */
        }

        /* Campo de texto de comentário (AGORA É UM TEXTAREA) */
        .comment-input-area {
            flex-grow: 1;
            position: relative;
        }

        .comment-input-box {
            font-family: arial;
            width: 100%;
            border: 1px solid #c6c6c6;
            padding: 8px;
            box-sizing: border-box;
            min-height: 40px;
            resize: none;
            font-size: 13px;
            color: #333;
            outline: none;
            overflow: hidden; /* Garante que a barra de rolagem não apareça em altura mínima */
            
            /* Remove a borda para cima e para baixo para imitar a imagem */
            border-top: none;
            border-bottom: 1px solid #ccc;
            
            /* Tenta simular a linha azul quando focado, mas sem precisar focar de fato */
            box-shadow: 0 1px 0 #4d90fe inset; 
        }

        /* O "X" para fechar */
        .close-btn {
            position: absolute;
            top: 10px; /* Ajuste de alinhamento vertical */
            right: 5px;
            color: #999;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            font-size: 20px;
        }

        /* Linha inferior: Opções e botão Postar */
        .comment-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 5px;
            padding-left: 50px; 
        }

        .left-options {
            display: flex;
            align-items: center;
        }

        .google-plus-share {
            font-size: 12px;
            color: #666;
            margin-right: 15px;
        }

        /* Seletor de privacidade */
        .privacy-selector {
            display: flex;
            align-items: center;
            padding: 4px 8px;
            border: 1px solid #ccc;
            background-color: #f7f7f7;
            cursor: pointer;
            font-size: 12px;
            color: #333;
            position: relative; /* CRUCIAL para o dropdown ser absoluto */
            padding-left: 25px; /* Ajusta o padding para o ícone 'P' */
            transition: background-color 0.2s;
        }

        /* Ícone de Público (TEXTO SIMPLES 'P') */
        .privacy-selector::before {
            content: "P"; /* Mantido como aproximação textual 'P' */
            font-weight: bold;
            font-family: Arial, sans-serif;
            position: absolute;
            left: 6px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: #333;
        }

        /* NOVO: Estilos do Dropdown */
        .privacy-dropdown {
            position: absolute;
            top: 100%; /* Posiciona abaixo do seletor */
            left: 0;
            width: 100%;
            background-color: #fff;
            border: 1px solid #ccc;
            z-index: 10;
            margin-top: 5px; /* Pequena separação visual */
        }

        .dropdown-item {
            padding: 4px 8px;
            font-size: 13px;
            cursor: pointer;
            color: #333;
        }

        .dropdown-item:hover {
            background-color: #f0f0f0;
        }

        .hidden {
            display: none !important;
        }

        /* Botão Postar */
        .post-button {
            background-color: #f7f7f7; /* Cor Cinza: Desativado por padrão */
            color: #999;
            border: 1px solid #ccc;
            padding: 6px 12px;
            cursor: not-allowed; /* Cursor 'não permitido' quando desativado */
            font-size: 13px;
        }
        
        .post-button.active {
            background-color: #4d90fe; /* Cor Azul: Ativado */
            color: #fff;
            border: 1px solid #3079ed;
            cursor: pointer;
        }

        .post-button.active:hover {
            background-color: #357ae8;
        }
        
        /* Mensagem de Feedback */
        #feedback-message {
            margin-top: 10px;
            padding-left: 50px;
            font-size: 14px;
            color: green;
        }
    </style>
</head>
<body>

<div class="comment-box-container" id="commentBox">
    <div class="comment-header">
        <div class="avatar-wrapper">
            <img src="https://upload.wikimedia.org/wikipedia/en/0/05/Custom_YouTube_Avatar.png" alt="Avatar do Usuário" class="avatar-img" onerror="this.onerror=null; this.src='https://placehold.co/40x40/e0e0e0/333?text=F|22'">
            <div class="avatar-text-overlay">
                <!-- Simulação do texto estilizado. A imagem de placeholder já tem um texto simples. --></div>
        </div>
        <div class="comment-input-area">
            <textarea id="commentInput" class="comment-input-box" placeholder="deixe seu comentario"></textarea>
            <a href="#" class="close-btn" onclick="document.getElementById('commentBox').style.display = 'none'; return false;">×</a>
        </div>
    </div>
    
    <div class="comment-footer">
        <div class="left-options">
            <label class="google-plus-share">
                <input type="checkbox" checked> Fazer postagem divulgando
            </label>
            
            <!-- SELETOR DE PRIVACIDADE COM DROPDOWN -->
            <div id="privacySelector" class="privacy-selector">
                <span id="privacyStatusText">Público</span>
                <span style="margin-left: 5px; font-size: 8px;">▼</span>
                
                <!-- Dropdown Menu -->
                <div id="privacyDropdown" class="privacy-dropdown hidden">
                    <div class="dropdown-item" data-status="public">Público</div>
                    <div class="dropdown-item private-item" data-status="private">Privado</div>
                </div>
            </div>
            <!-- FIM DO SELETOR -->
        </div>
        <button id="postButton" class="post-button" disabled>Postar</button>
    </div>
</div>

<div id="feedback-message"></div>

<script>
    const commentInput = document.getElementById('commentInput');
    const postButton = document.getElementById('postButton');
    const feedbackMessage = document.getElementById('feedback-message');
    const privacySelector = document.getElementById('privacySelector');
    const privacyStatusText = document.getElementById('privacyStatusText');
    const privacyDropdown = document.getElementById('privacyDropdown'); // Novo elemento
    const dropdownItems = document.querySelectorAll('.dropdown-item'); // Novos elementos

    let isPublic = true; // Estado inicial: Público
    
    // Função para verificar o campo de texto e ativar/desativar o botão
    function checkComment() {
        if (commentInput.value.trim().length > 0) {
            postButton.classList.add('active');
            postButton.disabled = false;
        } else {
            postButton.classList.remove('active');
            postButton.disabled = true;
        }
    }

    // Função para mostrar/esconder o dropdown
    function toggleDropdown(event) {
        // Impede que o clique no selector feche imediatamente se já estiver aberto
        event.stopPropagation(); 
        privacyDropdown.classList.toggle('hidden');
    }

    // Função para configurar a privacidade com base na seleção (NOVA LÓGICA)
    function setPrivacy(status) {
        privacyDropdown.classList.add('hidden'); // Fecha o dropdown
        
        if (status === 'public' && !isPublic) {
            isPublic = true;
            privacyStatusText.textContent = 'Público';
            privacySelector.classList.remove('private');
            feedbackMessage.textContent = 'Comentário definido como Público.';
        } else if (status === 'private' && isPublic) {
            isPublic = false;
            privacyStatusText.textContent = 'Privado';
            privacySelector.classList.add('private');
            feedbackMessage.textContent = 'Comentário definido como Privado.';
        } else {
            // Se o status for o mesmo, apenas fecha e sai
            return;
        }

        setTimeout(() => {
            feedbackMessage.textContent = '';
        }, 3000);
    }
    
    // Adiciona o listener para abrir/fechar o dropdown
    privacySelector.addEventListener('click', toggleDropdown);

    // Adiciona listener para fechar o dropdown ao clicar em qualquer lugar fora dele
    document.addEventListener('click', function(event) {
        if (!privacySelector.contains(event.target)) {
            privacyDropdown.classList.add('hidden');
        }
    });

    // Adiciona listeners aos itens do dropdown
    dropdownItems.forEach(item => {
        item.addEventListener('click', function(event) {
            event.stopPropagation(); // Impede que o clique no item feche/abra o dropdown duas vezes
            const status = this.getAttribute('data-status');
            setPrivacy(status);
        });
    });

    // Adiciona o listener para monitorar o input de texto
    commentInput.addEventListener('input', checkComment);
    
    // Adiciona o listener para o botão de postar
    postButton.addEventListener('click', function() {
        if (postButton.classList.contains('active')) {
            const commentText = commentInput.value.trim();
            const status = isPublic ? 'Público' : 'Privado';

            // Simula o envio do comentário
            feedbackMessage.textContent = `Comentário (${status}) enviado com sucesso: "${commentText.substring(0, 50)}..."`;
            
            // Limpa o campo de texto após o "envio"
            commentInput.value = '';
            checkComment(); // Desativa o botão
            
            // Opcional: Esconde a mensagem após 5 segundos
            setTimeout(() => {
                feedbackMessage.textContent = '';
            }, 5000);
        }
    });

    // Chama a função ao carregar a página para garantir o estado inicial
    document.addEventListener('DOMContentLoaded', checkComment);
</script>

</body>
</html>
