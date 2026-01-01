<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Guia (Chrome 2011 Style - Hover)</title>
    <link href='https://fonts.googleapis.com/css?family=Arial' rel='stylesheet'>
    <style>
        /* ========================================================= */
        /* ESTILOS DE BASE (Mantendo o Visual 2011) */
        /* ========================================================= */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #e0e0e0;
            color: #333;
        }
        .browser-chrome {
            background: linear-gradient(to bottom, #ededed 0%, #d8d8d8 100%);
            border-bottom: 1px solid #c5c5c5;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .bookmark-bar {
            background-color: #f0f0f0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #c5c5c5;
            padding: 5px 20px;
            font-size: 13px;
            color: #555;
            display: flex;
            align-items: center;
        }
        .bookmark-bar .bar-link {
            padding: 3px 10px;
            margin: 0 5px;
            color: #1a0dab;
            text-decoration: none;
            border-radius: 2px;
            white-space: nowrap;
        }
        .bookmark-bar .bar-link:hover {
            background-color: #e0e0e0;
        }
        .bookmark-bar .message {
            flex-grow: 1;
        }
        .bookmark-bar .other-bookmarks {
            margin-left: auto;
            padding-left: 10px;
        }
        .bookmark-bar .other-bookmarks span {
            font-size: 18px; 
            line-height: 0; 
            vertical-align: middle;
            color: #fbbc05;
        }
        .ntp-content {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
            text-align: center;
        }
        .most-visited-section {
            text-align: left;
            margin-top: 50px; 
            margin-bottom: 40px;
        }
        .most-visited-section h2 {
            font-size: 15px;
            font-weight: bold;
            color: #555;
            margin-bottom: 15px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .thumbnails-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); 
            gap: 20px;
        }
        .thumbnail {
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 10px;
            height: auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: #333;
            text-align: center;
            position: relative; /* Essencial para posicionar os botões de controle */
            transition: box-shadow 0.2s, border-color 0.2s;
        }
        
        /* NOVO: Estilo da borda no hover */
        .thumbnail:hover {
            border-color: #92c0ed; /* Borda azul clara no hover */
            background-color: #62A7E8; /* Fundo levemente azulado no hover */
        }

        /* NOVO: Mostra os botões de controle no hover */
        .thumbnail:hover .control-buttons {
            opacity: 1; /* Torna os botões visíveis */
            pointer-events: auto; /* Permite interação com os botões */
        }
        
        .thumbnail-content {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            width: 100%;
            height: 120px;
            line-height: 120px; 
            margin-bottom: 5px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 30px; 
            color: #777;
        }
        
        .thumbnail p {
            margin: 0;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .thumbnail p .favicon-icon {
            width: 16px;
            height: 16px;
            margin-right: 5px; 
            object-fit: contain;
            display: inline-block; 
        }

        .thumbnail p .favicon-icon:not([src]) {
            display: none;
        }


        .add-shortcut-button {
            background-color: #f5f5f5;
            border: 1px dashed #ccc;
            color: #555;
            cursor: pointer;
            height: 142px; 
            justify-content: center;
            align-items: center;
            display: flex;
            flex-direction: column;
            transition: background-color 0.2s;
            text-decoration: none; 
        }
        .add-shortcut-button:hover {
            background-color: #ececec;
        }
        .add-shortcut-button .plus-icon {
            font-size: 40px;
            line-height: 1;
            margin-bottom: 5px;
        }
        .add-shortcut-button p {
            font-size: 13px;
            color: #555;
        }
        .recently-closed-section {
            border-top: 1px solid #ccc;
            padding-top: 15px;
            text-align: left;
            font-size: 13px;
            color: #555;
        }
        .recently-closed-section span {
            font-weight: bold;
            color: #333;
            margin-right: 10px;
        }
        .recently-closed-section a {
            color: #1a0dab;
            text-decoration: none;
            margin-right: 15px;
        }

        /* ========================================================= */
        /* NOVO: ESTILOS DOS BOTÕES DE CONTROLE NO HOVER */
        /* ========================================================= */
        .control-buttons {
            position: absolute;
            top: 0;
            right: 0;
            background: linear-gradient(to left, #d0e0ed 0%, #e0eff9 100%); /* Fundo azul claro para a "janela" */
            border-bottom-left-radius: 3px;
            padding: 2px 5px;
            display: flex;
            align-items: center;
            opacity: 0; /* Começa invisível */
            pointer-events: none; /* Não interfere com cliques quando invisível */
            border-left: 1px solid #a8c1e2;
            border-bottom: 1px solid #a8c1e2;
        }

        .control-buttons .btn {
            width: 20px;
            height: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            border-radius: 2px;
            margin-left: 3px;
            transition: background-color 0.1s, box-shadow 0.1s;
        }

        .control-buttons .btn:hover {
            background-color: rgba(255, 255, 255, 0.5); /* Efeito de hover nos botões */
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        /* Ícone de Pino (Fixar) */
        .control-buttons .pin-icon {
            /* Usando um SVG inline para o ícone de pino */
            width: 14px;
            height: 14px;
            fill: #666; /* Cor do pino */
        }

        /* Ícone de Fechar (Configurar/Remover) */
        .control-buttons .close-icon {
            width: 14px;
            height: 14px;
            fill: #666; /* Cor do X */
        }
        .control-buttons .close-icon:hover {
            fill: #e74c3c; /* Cor de destaque no hover do X */
        }


        /* ========================================================= */
        /* ESTILOS DA MODAL (Sem alterações) */
        /* ========================================================= */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5); 
            display: none; 
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background-color: #f0f0f0;
            border: 1px solid #c5c5c5;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            width: 400px;
            font-size: 13px;
        }
        .modal-header {
            background: linear-gradient(to bottom, #f9f9f9 0%, #e8e8e8 100%);
            border-bottom: 1px solid #ccc;
            padding: 10px 15px;
            font-weight: bold;
            color: #333;
        }
        .modal-body {
            padding: 20px 15px;
        }
        .modal-body label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .modal-body input[type="text"] {
            width: 95%;
            padding: 5px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 2px;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        button:focus, input:focus, textarea:focus, select:focus {
            outline: none;
        }
        .modal-footer {
            background-color: #e8e8e8;
            border-top: 1px solid #ccc;
            padding: 10px 15px;
            text-align: right;
        }
        .modal-footer button {
            padding: 5px 15px;
            margin-left: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
            background: linear-gradient(to bottom, #ffffff 0%, #f0f0f0 100%);
            cursor: pointer;
            font-size: 13px;
            font-weight: normal;
        }
        .modal-footer button:hover {
            border-color: #a0a0a0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .modal-footer button#saveShortcut {
            border-color: #387ad6;
            color: #fff;
            background: linear-gradient(to bottom, #4d90fe 0%, #4787ed 100%);
        }
        .modal-footer button#saveShortcut:hover {
            background: linear-gradient(to bottom, #5d9aff 0%, #5797f7 100%);
        }
    </style>
</head>
<body>

    <div class="browser-chrome">
        <div class="bookmark-bar">
            
            <div class="message">
                Para acesso rápido, coloque seus favoritos aqui.
            </div>

            <div class="other-bookmarks">
                <span style="font-size: 18px; line-height: 0; vertical-align: middle;">★</span>
                Outros favoritos
            </div>
        </div>
    </div>
    
    <div class="ntp-content">
        
        <div class="most-visited-section">
            <h2>Mais visitados</h2>
            <div class="thumbnails-grid" id="shortcutsGrid">
                
                <a href="#" class="thumbnail add-shortcut-button" id="openModalButton" title="Adicionar atalho à Nova Guia">
                    <span class="plus-icon">+</span>
                    <p>Adicionar atalho</p>
                </a>
            </div>
        </div>

        <div class="recently-closed-section">
            <span>Fechados recentemente</span>
            <a href="#">Loja de Temas</a>
            <a href="#">Painel de Desenvol.</a>
            <a href="#">Guia Anterior</a>
            
            <span style="float: right;">
                <a href="#">Histórico</a> |
                <a href="#">Downloads</a> |
                <a href="#">Ajuda</a>
            </span>
        </div>
    </div>

    <div class="modal-overlay" id="addShortcutModal">
        <div class="modal-content">
            <div class="modal-header">
                Adicionar Atalho
            </div>
            <div class="modal-body">
                <label for="shortcutName">Nome:</label>
                <input type="text" id="shortcutName" maxlength="20" placeholder="Ex: Meu Site Favorito">
                
                <label for="shortcutUrl">URL:</label>
                <input type="text" id="shortcutUrl" placeholder="Ex: https://www.meusite.com">
            </div>
            <div class="modal-footer">
                <button id="cancelShortcut">Cancelar</button>
                <button id="saveShortcut">Salvar</button>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('addShortcutModal');
        const openButton = document.getElementById('openModalButton');
        const saveButton = document.getElementById('saveShortcut');
        const cancelButton = document.getElementById('cancelShortcut');
        const shortcutsGrid = document.getElementById('shortcutsGrid');
        const nameInput = document.getElementById('shortcutName');
        const urlInput = document.getElementById('shortcutUrl');

        function getDomain(url) {
            try {
                let fullUrl = url.startsWith('http') ? url : 'https://' + url;
                const parsedUrl = new URL(fullUrl);
                return parsedUrl.origin;
            } catch (e) {
                return null;
            }
        }

        function createShortcutHtml(name, url) {
            const domainOrigin = getDomain(url);
            let iconHtml = '';
            
            if (domainOrigin) {
                const iconUrl = domainOrigin + '/favicon.ico'; 
                iconHtml = `<img src="${iconUrl}" 
                             onerror="this.style.display='none';" 
                             class="favicon-icon"
                             alt="${name} icon"
                           />`;
            }
            
            const initial = name.substring(0, 2).toUpperCase();
            
            const newShortcut = document.createElement('a');
            newShortcut.href = url.startsWith('http') ? url : 'https://' + url;
            newShortcut.target = '_blank';
            newShortcut.className = 'thumbnail';
            
            newShortcut.innerHTML = `
                <div class="thumbnail-content">
                    [${initial}]
                </div>
                <p title="${name}">
                    ${iconHtml}
                    <span>${name}</span>
                </p>
                <div class="control-buttons">
                    <div class="btn pin-btn" title="Fixar atalho">
                        <svg class="pin-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14 4h-4c-1.1 0-2 .9-2 2v2H6c-1.1 0-2 .9-2 2v2h2c1.1 0 2 .9 2 2v4h4c1.1 0 2-.9 2-2v-2h2c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4zm0 2h-4v2h4V6zm-2 10h-4v-2h4v2zM6 10h2v2H6v-2zm10 0h2v2h-2v-2z"/>
                        </svg>
                    </div>
                    <div class="btn close-btn" title="Remover atalho">
                        <svg class="close-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </div>
                </div>
            `;
            return newShortcut;
        }

        openButton.addEventListener('click', (e) => {
            e.preventDefault();
            if (shortcutsGrid.children.length > 7) {
                alert("Você atingiu o limite de atalhos (8 na grade).");
                return;
            }
            modal.style.display = 'flex';
            nameInput.focus();
        });

        cancelButton.addEventListener('click', () => {
            modal.style.display = 'none';
            nameInput.value = '';
            urlInput.value = '';
        });

        saveButton.addEventListener('click', () => {
            const name = nameInput.value.trim();
            let url = urlInput.value.trim();

            if (name === "" || url === "") {
                alert("Por favor, preencha o Nome e a URL.");
                return;
            }
            
            if (shortcutsGrid.children.length > 7) {
                alert("Você atingiu o limite de atalhos (8 na grade).");
                return;
            }

            const addButton = document.getElementById('openModalButton');
            shortcutsGrid.removeChild(addButton);
            
            const newShortcutElement = createShortcutHtml(name, url);
            shortcutsGrid.appendChild(newShortcutElement);
            
            shortcutsGrid.appendChild(addButton);

            modal.style.display = 'none';
            nameInput.value = '';
            urlInput.value = '';
        });
        
        urlInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                saveButton.click();
            }
        });

        // NOVO: Adiciona um listener para o grid para lidar com cliques nos botões de controle
        shortcutsGrid.addEventListener('click', (e) => {
            // Verifica se o clique foi em um botão dentro de um atalho
            const clickedBtn = e.target.closest('.control-buttons .btn');
            if (!clickedBtn) return; // Não é um botão de controle

            e.preventDefault(); // Impede que o clique no botão siga o link do atalho
            
            const shortcutElement = clickedBtn.closest('.thumbnail');
            if (!shortcutElement) return; // Garante que estamos dentro de um atalho

            if (clickedBtn.classList.contains('pin-btn')) {
                alert('Funcionalidade "Fixar Atalho" será implementada aqui para: ' + shortcutElement.querySelector('span').textContent);
                // TODO: Implementar lógica de fixar atalho (salvar em localStorage, reordenar, etc.)
            } else if (clickedBtn.classList.contains('close-btn')) {
                const confirmDelete = confirm('Tem certeza que deseja remover o atalho "' + shortcutElement.querySelector('span').textContent + '"?');
                if (confirmDelete) {
                    shortcutElement.remove();
                    // TODO: Implementar lógica de remover atalho (remover do localStorage)
                }
            }
        });

    </script>

</body>
</html>