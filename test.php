<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>esse é um texto</title>
    <!--- caso voce queira colocar um css no html externamente, use <link rel="stylesheet" href="style.css"> -->
</head>
<body>
    <h1>TESTE</h1>

    <p>Aqui um arquivo de site pra abrir nesta aba <a href="youraccount.html">you account</a></p>
    <p>Ir para o arquivo de site em outra aba <a href="https://www.pudim.com.br" target="_blank" rel="nofollow">pudim.com</a></p>
    <p>voltando... <a href="../index.html">VOLTE</a></p>

    <h1>baixe aq um arquivo</h1>
    <ul>
        <li><a href="test/FOTO YAGGOZIT0.zip" download="FOTO YAGGOZIT0.zip" type="application/zip">ZIP</a></li>
        <li><a href="test/FOTO YAGGOZIT0.png" target="_blank" rel="nofollow">NORMAL (ABRE O ARQUIVO) </a></li>
    </ul>

    <h1>mas...  QUE MUSICA É ESSA?</h1>

    <audio src="audios/07. Milky Ways.mp3" controls autoplay></audio>
    <!--- MP3, WAV E OGG-->

    <audio preload="metadata" autoplay controls loop> <!--- loop é para loopar a musica -->
        <source src="audios/07. Milky Ways.mp3" type="audio/mpeg">
        <p>Infelizmente não dá</p>
    </audio>

    <h1>baixe aq um arquivo DE AUDIO</h1>
    <ul>
        <li><a href="audios/07. Milky Ways.mp3" target="_blank" rel="nofollow">AUDIO NORMAL (ABRE O ARQUIVO) </a></li>
    </ul>

</body>
</html>