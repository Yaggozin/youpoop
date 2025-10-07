<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Hospedado e Estilos CSS</title>
<style>
    body {
        background-image: linear-gradient(to bottom, #add8e6, #98cbdc);
        background-repeat: no-repeat;
        height: 100vh;
    }

    h1 {
        font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif;
        font-size: 60px;
        background-color: #d4d4d4;
        color: brown;
    }

    h3 {
        font-family: Georgia, 'Times New Roman', Times, serif;
        color: rgb(34, 112, 180);
        text-align: left;
    }

</style>

</head>
<body>
    <h1 style="font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif;">video esta hospedado localmente</h1>
    <video src="videos/QUADRADO FODA002.mp4" width="500" controls></video>

    <video width="500" poster="test/Image24.png" autoplay controls>
        <source src="videos/QUADRADO FODA002.mp4" type="video/mp4">
        <h2>seu navegador não vai</h2>
    </video>

    <h1>video esta hospedado no YouTube</h1>
    <h3>video não esta visivel pelo caso do codigo</h3>
    <!--- <iframe width="500" height="500" src="https://www.youtube.com/embed/UDoFMOwxDWg?si=37v7batTb_ocZKuH" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe> -->

    <!--- <div style="padding:66.67% 0 0 0;position:relative;"><iframe src="https://player.vimeo.com/video/138398832?badge=0&amp;autopause=0&amp;player_id=0&amp;app_id=58479" frameborder="0" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share" referrerpolicy="strict-origin-when-cross-origin" style="position:absolute;top:0;left:0;width:50%;height:50%;" title="YTPBR TreinSiri Fracassado"></iframe></div><script src="https://player.vimeo.com/api/player.js"></script> -->
    
</body>
</html>