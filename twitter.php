<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Twitter - Descubra o que está acontecendo agora, em qualquer lugar</title>
    <style>
        /* ======================== */
        /* Estilos Globais e Reset */
        /* ======================== */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #e6e6e6; /* Cor de fundo sutil, embora o conteúdo principal seja branco */
            margin: 0;
            padding: 0;
            color: #333;
            font-size: 13px; /* Tamanho de fonte comum em 2010 */
        }

        a {
            color: #0077b5; /* Azul clássico dos links */
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* Estrutura principal do site */
        .container {
            width: 1010px;
            margin: auto auto 20px auto;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* Sombra sutil ao redor do corpo principal */
            min-height: 800px;
        }

        /* ======================== */
        /* Cabeçalho (Header) */
        /* ======================== */
        header {
            background-image: linear-gradient(to bottom, #7fcde9 0%, #3ba6cf 100%);
            border-bottom: 1px solid #1e709a;
            padding: 15px 0 0;
            height: 80px;
            position: relative;
        }

        .header-content {
            width: 980px;
            margin: 0 auto;
            position: relative;
        }

        .logo {
            float: left;
            margin-right: 20px;
        }

        .logo img {
            height: 40px; /* Apenas um espaço para a logo */
            width: auto;
            background-color: transparent;
        }

        .tagline {
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
            padding-top: 10px;
            float: left;
        }

        .search-box {
            float: left;
            margin-left: 20px;
            padding-top: 5px;
        }

        .search-box input[type="text"] {
            padding: 5px;
            border: 1px solid #888;
            border-radius: 3px;
            width: 200px;
            font-size: 12px;
        }

        .search-box button {
            background-color: #eee;
            border: 1px solid #888;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
            font-size: 12px;
        }

        .account-nav {
            float: right;
            color: #fff;
            font-size: 13px;
            padding-top: 5px;
        }

        .account-nav a {
            color: #fff;
            margin-left: 15px;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.1);
        }

        /* ======================== */
        /* Navegação Inferior (Sub-Header) */
        /* ======================== */
        .sub-header {
            background-color: #27495d;
            height: 30px;
            border-top: 1px solid #1e709a;
        }

        .sub-header-content {
            text-align: center;
            margin: 0 auto;
            line-height: 30px;
        }

        .sub-header a {
            color: #fff;
            margin: 0 10px;
            font-size: 12px;
            opacity: 0.8;
            text-shadow: none;
        }

        .sub-header a:hover {
            opacity: 1;
            text-decoration: none;
        }

        /* ======================== */
        /* Layout de Colunas */
        /* ======================== */
        .main-content {
            padding: 20px;
            overflow: hidden; /* Para conter as colunas flutuantes */
        }

        .left-column {
            float: left;
            width: 200px;
            margin-right: 20px;
        }

        .center-column {
            float: left;
            width: 480px;
            margin-right: 20px;
        }

        .right-column {
            float: right;
            width: 240px;
        }

        /* ======================== */
        /* Elementos de Coluna */
        /* ======================== */

        /* Caixa Lateral de "New to Twitter?" */
        .new-to-twitter {
            border: 1px solid #ccc;
            background-color: #f7f7f7;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .new-to-twitter h3 {
            color: #333;
            font-size: 18px;
            margin: 0 0 10px 0;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        .create-account-btn {
            display: block;
            background: linear-gradient(0deg, #FFAA22, #FFEE66);
            color: #333;
            text-align: center;
            padding: 10px 0;
            border-radius: 5px;
            text-shadow: 0 1px 0 #fe6;
            font-size: 14px;
            margin: 15px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            border: 1px solid #fa2;
            font: bold 18px Arial, Sans-serif;
        }

        .create-account-btn:hover {
            background-color: #ffaa33;
            text-decoration: none;
        }

        /* Top Tweets (Feed principal) */
        .tweets-section h2 {
            font-size: 16px;
            color: #333;
            margin: 0 0 15px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        .tweet {
            padding: 10px 0;
            border-bottom: 1px dotted #eee;
            overflow: hidden;
        }

        .tweet:last-child {
            border-bottom: none;
        }

        .tweet-avatar {
            float: left;
            width: 48px;
            height: 48px;
            background-color: #ccc;
            margin-right: 10px;
            border: 1px solid #eee;
        }

        .tweet-content {
            overflow: hidden;
            line-height: 1.5;
        }

        .tweet-user {
            font-weight: bold;
            color: #22a2e4;
        }

        .tweet-username {
            font-weight: normal;
            color: #666;
            margin-left: 5px;
        }

        /* Módulos Esquerda (World Cup 2010, See who's here) */
        .module {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            background-color: #fff;
            border-radius: 5px;
        }

        .module h3 {
            font-size: 14px;
            color: #333;
            margin: 0 0 10px 0;
            font-weight: bold;
        }

        .module-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .module-list li {
            display: inline-block;
            margin: 2px;
        }

        .module-list li img {
            width: 32px;
            height: 32px;
            background-color: #ccc;
            border: 1px solid #eee;
            border-radius: 3px;
            display: block;
        }

        /* ======================== */
        /* Rodapé (Footer) */
        /* ======================== */
        footer {
            clear: both;
            border-top: 1px solid #ccc;
            padding: 15px 0;
            text-align: center;
            background-color: #f7f7f7;
            font-size: 11px;
            color: #666;
        }

        footer a {
            color: #666;
            margin: 0 5px;
        }

        .clearfix:after {
            content: "";
            display: table;
            clear: both;
        }

    </style>
</head>
<body>

    <header>
        <div class="header-content clearfix">
            <div class="logo">
                <h1 style="color: white; font-size: 60px; margin: 0; text-shadow: 2px 2px 0px rgba(0,0,0,0.3);">twitter</h1>
            </div>

            <div class="account-nav">
                <a href="#">Have an account?</a>
                <a href="#">Sign in</a>
            </div>

            <div class="tagline">
                Discover what's happening right now, anywhere in the world
            </div>
        </div>
    </header>

    <div class="sub-header">
        <div class="sub-header-content">
            <a href="#">Assunto 1</a>
            <a href="#">Assunto 2</a>
            <a href="#">Assunto 3</a>
            <a href="#">Assunto 4</a>
            <a href="#">Assunto 5</a>
            <a href="#">Assunto 6</a>
        </div>
    </div>

    <div class="container">
        <div class="main-content clearfix">

            <div class="left-column">
                <div class="module" style="text-align: center; background-color: #daf5ff; border: 1px solid #a8e3ff;">
                    <h3 style="display: inline; color: #0077b5;">World Cup 2010</h3>
                </div>

                <div class="module">
                    <h3>See who's here</h3>
                    <ul class="module-list">
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                        <li><img src="test/download (1).png" alt="Avatar"/></li>
                    </ul>
                    <p style="font-size: 11px; margin-top: 10px;">Friends and industry peers you know. Celebrities you watch. Businesses you frequent. Find them all on Twitter.</p>
                </div>
            </div>

            <div class="center-column tweets-section">
                <h2>Top Tweets <a href="#" style="font-weight: normal; font-size: 11px;">View all</a></h2>

                <div class="tweet clearfix">
                    <div class="tweet-avatar"></div>
                    <div class="tweet-content">
                        <span class="tweet-user">AboutAquarius</span><span class="tweet-username">@Aquarius</span> tend to be good at mostly everything, its just that they sometimes get bored with just one thing.
                    </div>
                </div>

                <div class="tweet clearfix">
                    <div class="tweet-avatar"></div>
                    <div class="tweet-content">
                        <span class="tweet-user">PurpleVirgo06</span><span class="tweet-username">@Virgo</span> A virgo woman is just a perfect face of delicacy with brains. She is a true lifter when criticizing the weak points of anything or person.
                    </div>
                </div>

                <div class="tweet clearfix">
                    <div class="tweet-avatar"></div>
                    <div class="tweet-content">
                        <span class="tweet-user">tabiwilliamfield</span> I wish people would stop trying out musicians on <a href="#">@twitter</a> against each other as if it were some competition. Music is an art, not a sport.
                    </div>
                </div>
                 <div class="tweet clearfix">
                    <div class="tweet-avatar"></div>
                    <div class="tweet-content">
                        <span class="tweet-user">KattWilliams</span> <a href="#">#DontUnderstandWhy</a> often black teenagers get <a href="#">@cutchill</a> gingergot, they got sent to Moany, but white women get their own TV shows.
                    </div>
                </div>
            </div>

            <div class="right-column">
                <div class="new-to-twitter">
                    <h3>New to Twitter?</h3>
                    <p>Twitter is a rich source of instant information. Stay updated. Keep others updated. It's a whole thing.</p>
                    <a href="#" class="create-account-btn">Create an account</a>
                    <p style="font-size: 11px; color: #666;">
                        Customize Twitter by choosing who to follow. Then see tweets from those folks as soon as they're posted.
                    </p>
                    <p style="font-size: 11px; margin-top: 15px;">
                        Using Twitter for a business? <a href="#">Check out Twitter 101</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>
            &copy; 2010 Twitter &middot; 
            <a href="#">About Us</a> &middot; 
            <a href="#">Contact</a> &middot; 
            <a href="#">Blog</a> &middot; 
            <a href="#">Status</a> &middot; 
            <a href="#">API</a> &middot; 
            <a href="#">Businesses</a> &middot; 
            <a href="#">Help</a> &middot; 
            <a href="#">Jobs</a> &middot; 
            <a href="#">Terms</a> &middot; 
            <a href="#">Privacy</a>
            <span style="float: right;">Language: English ▾</span>
        </p>
    </footer>

</body>
</html>