<!DOCTYPE html>
<html>
<head>
    <title>Site Nostálgico com Interruptor Flash</title>
    <style>
        /* CSS Básico para o corpo da página */
        body {
            transition: background-color 0.5s ease; /* Transição suave */
            color: #333;
            padding: 20px;
        }

        /* 🛑 CLASSE CSS PARA O MODO ESCURO 🛑 */
        .dark-mode {
            background-color: #1a1a1a; /* Cor de fundo escura */
            color: #f0f0f0; /* Texto claro */
        }
    </style>
</head>

<body id="siteBody">

    <object width="150" height="150">
        <param name="movie" value="interruptor.swf">
        <embed src="interruptor.swf" width="150" height="150">
    </object>

    <h1>Conteúdo do Site</h1>
    <p>Clique no botão Flash para apagar a luz!</p>

    <script>
        function toggleWebsiteDarkness() {
            // Pega o elemento body
            const body = document.getElementById('siteBody');
            
            // Alterna a classe 'dark-mode'
            // Se tiver, remove; se não tiver, adiciona.
            body.classList.toggle('dark-mode');

            // Opcional: Mude o texto/estado para feedback visual
            const isDark = body.classList.contains('dark-mode');
            console.log("Modo escuro: " + isDark);
        }
    </script>
</body>
</html>