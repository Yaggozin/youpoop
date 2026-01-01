<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weegee.webp (WEBP Image, 525 x 956)</title>
    <style>
        body {
            background: #222;
        }

        img {
            text-align: center;
            position: absolute;
            inset: 0;
            margin: auto;
            margin-top: auto;
            image-orientation: from-image;
            display: block;
            object-fit: contain;
            overflow: hidden; /* Garante que não apareçam barras de rolagem */
            height: 100vh;
            width: 100vw;
        }

    </style>
</head>
<body>
    <img src="test/Weegee.webp" id="weegee-main"></img>

<script>
    // Lista de imagens para o fundo (podes adicionar mais caminhos aqui)
    const imagensFundo = [
        'test/Weegee.webp',
        'test/weegeelow.webp',
        'test/weegeevegas1.webp',
        'test/weegeewtf.webp',
        'test/weegeejumpscare.jpg'
    ];

    const imgElement = document.getElementById('weegee-main');
    let index = 0;

    function mudarImagemCentral() {
        
        setTimeout(() => {
            // Escolhe a próxima imagem da lista
            index = (index + 1) % imagensFundo.length;
            imgElement.src = imagensFundo[index];
            imgElement.style.opacity = 1;
        }, 500);
    }

    // Aguarda 5 segundos para começar a mudar
    setTimeout(() => {
        // A cada 1 segundo (1000ms), muda a imagem central
        setInterval(mudarImagemCentral, 100);
    }, 5000);
</script>
</body>
</html>