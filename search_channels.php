<?php
// ===============================================
// CONFIGURAÇÃO DO BANCO DE DADOS
// Mude essas variáveis para os seus dados reais!
// ===============================================
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Mude o usuário
define('DB_PASSWORD', '');     // Mude a senha
define('DB_NAME', 'ytp_db');   // Nome do seu banco de dados

// ===============================================
// 1. LÓGICA PARA PROCESSAR O TERMO DA URL E BUSCAR DADOS
// ===============================================

$search_term = '';
$results = [];
$error_message = '';

// Verifica se o termo de pesquisa foi enviado via URL (parâmetro 'q')
if (isset($_GET['q'])) {
    $search_term = trim($_GET['q']);

    if (empty($search_term)) {
        $error_message = "Por favor, forneça um termo de pesquisa na URL (ex: ?q=Hello%20World).";
    } else {
        // Conexão ao banco de dados
        $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if ($conn->connect_error) {
            $error_message = "Falha na conexão com o banco de dados: " . $conn->connect_error;
        } else {
            // Query SQL: Agora inclui 'channel_banner_path'
            $sql = "SELECT id, username, channel_slogan, profile_icon_path, join_date, channel_banner_path
                    FROM users 
                    WHERE username LIKE CONCAT('%', ?, '%') 
                    ORDER BY username ASC";
            
            // Prepared Statement para segurança
            if ($stmt = $conn->prepare($sql)) {
                
                $stmt->bind_param("s", $search_term);

                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $results[] = $row;
                    }
                } else {
                    $error_message = "Erro ao executar a pesquisa: " . $stmt->error;
                }

                $stmt->close();
            } else {
                $error_message = "Erro na preparação da query: " . $conn->error;
            }

            $conn->close();
        }
    }
} else {
    $error_message = "Esta é a página de resultados. Você deve acessá-la com um termo de pesquisa na URL (ex: /search_results.php?q=TERMO).";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados da Pesquisa: <?php echo htmlspecialchars($search_term); ?></title>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 900px;
            margin: auto;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        h1 {
            border-bottom: 2px solid #CC0000;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #333;
        }

        /* Novo Estilo de Container de Resultado (Não-Minimalista) */
        .channel-result {
            margin-bottom: 25px;
            border: 1px solid #ddd;
            background-color: #fff;
            overflow: hidden; 
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); 
        }

        /* Banner de Fundo */
        .channel-banner {
            height: 80px; 
            background-size: cover;
            background-position: center 30%; 
        }

        /* Área de Informação Principal */
        .channel-info-area {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            position: relative;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Estilo do Ícone com Borda Elaborada */
        .profile-icon-wrapper {
            position: relative;
            margin-right: 15px;
            
            margin-top: -30px; /* Puxa o ícone para cima */
        }

        .profile-icon {
            width: 80px;
            height: 80px;
            object-fit: cover;
            display: block;

        }

        /* Conteúdo de Texto */
        .channel-text-content {
            padding-top: 10px; 
        }

        .channel-text-content h3 {
            margin: 0 0 5px 0;
            font-size: 1.5em; 
        }

        .channel-text-content h3 a {
            color: #CC0000;
            text-decoration: none;
        }

        .channel-stats {
            display: flex;
            gap: 15px;
            font-size: 0.9em;
            color: #555;
            margin-bottom: 8px;
        }

        .channel-slogan {
            font-size: 1em;
            font-style: italic;
            color: #333;
            line-height: 1.4;
        }

        .join-date {
            display: block;
            margin-top: 8px;
            font-size: 0.8em;
            color: #777;
        }
        
        .error-message {
            padding: 20px;
            background-color: #ffe5e5;
            border: 1px solid #ff9999;
            color: #cc0000;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Resultados da Pesquisa de Canais</h1>
    
    <?php 
    // 3. EXIBIÇÃO DE ERROS OU RESULTADOS

    if (!empty($error_message)) {
        echo "<div class='error-message'>" . htmlspecialchars($error_message) . "</div>";
    } 
    
    else if (!empty($search_term)) {
        echo "<h2>Canais encontrados para: <strong>" . htmlspecialchars($search_term) . "</strong></h2>";
        
        if (count($results) > 0) {
            foreach($results as $row) {
                // Define o caminho do ícone, usando um padrão se for NULL
                $icon_path = $row['profile_icon_path'] ? htmlspecialchars($row['profile_icon_path']) : 'uploads/icons/default_icon.png';
                
                // Define o caminho do banner ou um placeholder se for NULL
                $banner_path = $row['channel_banner_path'] ? htmlspecialchars($row['channel_banner_path']) : 'https://via.placeholder.com/900x80/222222?text=Banner+Não+Disponível';
                
                // Formata a data de ingresso
                $formatted_date = date("d/m/Y", strtotime($row['join_date']));
                
                // Saída HTML para cada resultado com o novo visual
                ?>
                <div class="channel-result">
                    
                    <div class="channel-banner" style="background-image: url('<?php echo $banner_path; ?>');"></div>
                    
                    <div class="channel-info-area">
                        
                        <div class="profile-icon-wrapper">
                            <img src="<?php echo $icon_path; ?>" alt="Ícone de <?php echo htmlspecialchars($row['username']); ?>" class="profile-icon">
                        </div>
                        
                        <div class="channel-text-content">
                            <h3>
                                <a href="channel.php?id=<?php echo $row['id']; ?>">
                                    <?php echo htmlspecialchars($row['username']); ?>
                                </a>
                            </h3>
                            
                            <div class="channel-stats">
                                <span>0 Vídeos</span> 
                                <span>0 Inscritos</span> 
                            </div>
                            
                            <?php if ($row['channel_slogan']): ?>
                                <p class="channel-slogan"><?php echo htmlspecialchars($row['channel_slogan']); ?></p>
                            <?php else: ?>
                                <p class="channel-slogan">Canal sem slogan definido.</p>
                            <?php endif; ?>

                            <span class="join-date">Membro desde: <?php echo $formatted_date; ?></span>
                        </div>
                    </div>
                </div>
                <?php
            }
        } else {
            echo "<p>Nenhum canal encontrado com o termo <strong>" . htmlspecialchars($search_term) . "</strong>.</p>";
        }
    } 
    ?>
</div>

</body>
</html>