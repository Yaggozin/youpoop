<?php
// dashboard.php - Estilo YouTube 2012

session_start();

// 1. CONFIGURAÇÃO E CONEXÃO COM O BANCO
// ATENÇÃO: Certifique-se de que o arquivo 'db_connect.php' existe e define a variável $pdo (conexão PDO)
require_once 'db_connect.php'; 

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Criador';
$message = '';
$db_error = false;

// Tabs disponíveis: overview-tab, global-stats-tab, my-videos, upload-tab, edit-annotation
$active_tab = $_GET['tab'] ?? 'overview-tab';

// --------------------------------------------------------
// 2. LÓGICA DE PROCESSAMENTO DE FORMULÁRIOS
// --------------------------------------------------------

// Lógica de Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_video'])) {

    // Diretórios de destino (verifique se as permissões de escrita estão corretas no servidor)
    $upload_dir = 'uploads/videos/';
    $thumb_dir = 'uploads/thumbnails/';
    
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0777, true);

    // Campos do formulário
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $duration = trim($_POST['duration'] ?? '00:00:00'); // Campo oculto preenchido por JS
    $tags = trim($_POST['tags'] ?? ''); 
    $category = $_POST['category'] ?? 1;
    $visibility = $_POST['visibility'] ?? 'public';
    $upload_date = trim($_POST['upload_date'] ?? date('Y-m-d H:i:s'));
    // Opção de comentários (pode ser 'allow', 'approve' ou 'disable')
    $comment_option = $_POST['comments'] ?? 'allow'; 
    // ----------------------------------------------------
    // Variáveis que o servidor gera (Valores iniciais)
    $duration = '0:00'; // Será atualizado após o processamento do vídeo
    $views = 0;
    
    // Recupera os arquivos
    $video_file = $_FILES['video_file'] ?? []; 
    $thumb_file = $_FILES['thumbnail_file'] ?? []; 
    
    // Validação dos arquivos
    $video_upload_ok = isset($video_file['error']) && $video_file['error'] === UPLOAD_ERR_OK;
    $thumb_upload_ok = isset($thumb_file['error']) && $thumb_file['error'] === UPLOAD_ERR_OK;

    // Condição de validação completa
    if (!empty($title) && !empty($description) && $video_upload_ok && $thumb_upload_ok) {
        
        // 1. SALVAR ARQUIVO DE VÍDEO
        $video_ext = pathinfo($video_file['name'], PATHINFO_EXTENSION);
        $video_name = uniqid('vid_') . '.' . $video_ext;
        $video_path = $upload_dir . $video_name;
        
        // 2. SALVAR ARQUIVO DE THUMBNAIL
        $thumb_ext = pathinfo($thumb_file['name'], PATHINFO_EXTENSION);
        $thumb_name = uniqid('thumb_') . '.' . $thumb_ext;
        $thumbnail_path = $thumb_dir . $thumb_name;
        
        if (move_uploaded_file($video_file['tmp_name'], $video_path) && move_uploaded_file($thumb_file['tmp_name'], $thumbnail_path)) {
            
            // 3. INSERIR NO BANCO DE DADOS (QUERY CORRIGIDA)
                try {
                    // CORREÇÃO: Removido 'likes' e 'dislikes'. Adicionado 'rating' e 'rating_count'.
                    $stmt = $pdo->prepare("
                        INSERT INTO videos (
                            user_id, title, description, video_path, thumbnail_path, duration, 
                            visibility, category, tags, comment_options, upload_date, views, rating_sum, rating_count
                        ) 
                        VALUES (
                            :user_id, :title, :description, :video_path, :thumbnail_path, :duration, 
                            :visibility, :category, :tags, :comment_options, NOW(), 0, 0.0, 0
                        )
                    ");
                    
                    $stmt->execute([
                        ':user_id'         => $user_id, 
                        ':title'           => $title, 
                        ':description'     => $description, 
                        ':video_path'      => $video_path, 
                        ':thumbnail_path'  => $thumbnail_path, 
                        ':duration'        => $duration, 
                        ':visibility'      => $visibility,
                        ':category'        => $category,    
                        ':tags'            => $tags,          
                        ':comment_options' => $comment_option 
                    ]);
                    
                    $message = "<span style='color: green;'>Vídeo '<strong>" . htmlspecialchars($title) . "</strong>' enviado com sucesso!</span>";

                    // --- ADIÇÃO PARA BARRA DE PROGRESSO REAL ---
                    if (isset($_POST['ajax_upload'])) {
                        echo "UPLOAD_OK";
                        exit; // Para a execução aqui para não carregar o resto da página HTML
                    }

                    
                } catch (PDOException $e) {
                    // Remove os arquivos se a inserção no DB falhar
                    if (file_exists($video_path)) unlink($video_path);
                    if (file_exists($thumbnail_path)) unlink($thumbnail_path);
                    $message = "<span style='margin-left: 55px; color: red;'>Erro ao salvar no banco de dados: " . $e->getMessage() . "</span>";
                }
            } else {
            $message = "<span style='margin-left: 55px; color: red;'>Erro ao mover o arquivo de vídeo/thumbnail. Verifique as permissões.</span>";
        }
        
    } else {
    // Mapa de códigos de erro de upload do PHP
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => "O arquivo excede o limite definido no servidor (upload_max_filesize).",
            UPLOAD_ERR_FORM_SIZE  => "O arquivo excede o limite de MAX_FILE_SIZE do formulário.",
            UPLOAD_ERR_PARTIAL    => "O upload do arquivo foi interrompido ou feito parcialmente.",
            UPLOAD_ERR_NO_FILE    => "Nenhum arquivo foi selecionado.",
            UPLOAD_ERR_NO_TMP_DIR => "Faltando uma pasta temporária no servidor (Erro 6).",
            UPLOAD_ERR_CANT_WRITE => "Falha ao gravar o arquivo no disco (Erro 7).",
            UPLOAD_ERR_EXTENSION  => "Uma extensão PHP interrompeu o upload (Erro 8).",
        ];

        $err = '';
        
        if (empty($title) || empty($description)) {
            // 1. Erro de validação de campos
            $err = "Título e Descrição são obrigatórios.";
            
        } elseif (($video_file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($thumb_file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            // 2. Erro de arquivo não selecionado
            $err = "Por favor, selecione os arquivos de vídeo e miniatura.";
            
        } elseif (($video_file['error'] ?? 0) !== UPLOAD_ERR_OK && ($video_file['error'] ?? 0) !== 0) {
            // 3. Erro específico no upload do VÍDEO
            $code = $video_file['error'];
            $err_msg = $upload_errors[$code] ?? "Código de Erro PHP: {$code}.";
            $err = "Erro no upload do vídeo. Detalhe: " . $err_msg;
            
        } elseif (($thumb_file['error'] ?? 0) !== UPLOAD_ERR_OK && ($thumb_file['error'] ?? 0) !== 0) {
            // 4. Erro específico no upload da THUMBNAIL
            $code = $thumb_file['error'];
            $err_msg = $upload_errors[$code] ?? "Código de Erro PHP: {$code}.";
            $err = "Erro no upload da thumbnail. Detalhe: " . $err_msg;

        } else {
            // 5. Fallback - Se tudo mais falhar
            $err = "Erro desconhecido no formulário ou nos arquivos. Tente novamente.";
        }
        
        $message = "<span style='margin-left: 55px; color: red;'>Falha no Envio: " . $err . "</span>";
        }
    }

// Lógica de Edição, Exclusão e Anotação (Omitidas para simplificar, mas mantidas as chamadas)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_video'])) {
    $video_id = $_POST['video_id'];
    $title = trim($_POST['edit_title']);
    $description = trim($_POST['edit_description']);
    $visibility = $_POST['edit_visibility'];
    
    try {
        $stmt = $pdo->prepare("UPDATE videos SET title = ?, description = ?, visibility = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $description, $visibility, $video_id, $user_id]);
        $message = "<span style='margin-left: 55px; color: green;'>Vídeo atualizado com sucesso!</span>";
    } catch (PDOException $e) { /* Tratamento de erro */ }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_video'])) {
    // Lógica de exclusão...
    // Redireciona para my-videos
    $active_tab = 'my-videos';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_annotation'])) {
    // Lógica de salvar anotação...
    // Redireciona para edit-annotation
    $active_tab = 'edit-annotation';
}


// --------------------------------------------------------
// 3. LÓGICA DE CARREGAMENTO DE DADOS (Visão Geral e Estatísticas)
// --------------------------------------------------------
$video_count = 0;
$total_views = 0;
$my_videos = [];
$subscribers = 0; // Valor padrão em caso de erro
$annotation_video_id = $_GET['v'] ?? null; 
$video_info = []; // Para a edição de anotação

try {

    // 1. Consulta para contar os inscritos no canal do usuário logado ($user_id)
    // ATENÇÃO: Substitua 'subscriptions' pelo nome da sua tabela de inscrições 
    // e 'channel_id' pela coluna que armazena o ID do criador de conteúdo.
    $stmt_subs = $pdo->prepare("SELECT COUNT(*) AS total_subs FROM subscriptions WHERE channel_id = :user_id");
    $stmt_subs->execute([':user_id' => $user_id]);
    
    $result_subs = $stmt_subs->fetch(PDO::FETCH_ASSOC);
    
    // Armazena o resultado na variável $subscribers
    $subscribers = $result_subs['total_subs'] ?? 0;

    // 2. Estatísticas
    $stmt_stats = $pdo->prepare("SELECT COUNT(id) AS video_count, SUM(views) AS total_views FROM videos WHERE user_id = ?");
    $stmt_stats->execute([$user_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    $video_count = $stats['video_count'] ?? 0;
    $total_views = $stats['total_views'] ?? 0;

    // 3. Meus Vídeos
    $stmt_videos = $pdo->prepare("SELECT id, title, views, duration, upload_date, visibility, thumbnail_path, description FROM videos WHERE user_id = ? ORDER BY upload_date DESC");
    $stmt_videos->execute([$user_id]);
    $my_videos = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $db_error = true;
    $message = "<span style='margin-left: 55px; color: red;'>Erro ao carregar dados: " . $e->getMessage() . "</span>";
}


// Dados simulados para a aba de estatísticas globais
$global_views_data = [5000, 12000, 8500, 25000, 30000, 18000, 35000];
$global_subscribers_data = [20, 50, 45, 100, 150, 80, 200];
$views_last_7_days = array_sum($global_views_data);

// Lógica para carregar dados de anotação (se a aba for ativa)
if ($active_tab == 'edit-annotation' && $annotation_video_id && is_numeric($annotation_video_id)) {
    try {
        $stmt_video = $pdo->prepare("SELECT id, title FROM videos WHERE id = ? AND user_id = ?");
        $stmt_video->execute([$annotation_video_id, $user_id]);
        $video_info = $stmt_video->fetch(PDO::FETCH_ASSOC);
        
        $stmt_ann = $pdo->prepare("SELECT * FROM video_annotations WHERE video_id = ?");
        $stmt_ann->execute([$annotation_video_id]);
        $annotation = $stmt_ann->fetch(PDO::FETCH_ASSOC);

        $link_url = $annotation['link_url'] ?? '';
        $link_text = $annotation['link_text'] ?? '';
        $start_time = $annotation['start_time_seconds'] ?? 0;
        $bg_color = $annotation['annotation_color'] ?? '#FF0000';

    } catch (PDOException $e) { /* Tratamento de erro */ }
}

// =========================================================
// NOVO: LÓGICA DE ENVIO DE MENSAGEM (PARA QUALQUER USUÁRIO)
// =========================================================
// Lógica de Envio de Nova Mensagem (Adaptada da sua estrutura)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_username = trim($_POST['receiver_username']);
    $message_text = trim($_POST['message_text']);
    
    // Supondo que você queira um assunto padrão para mensagens enviadas pelo dashboard
    $subject = 'Mensagem do Criador'; 

    if (empty($receiver_username) || empty($message_text)) {
        $message = "Preencha todos os campos para enviar a mensagem.";
    } else {
        try {
            // 1. Busca o ID do destinatário
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute(['username' => $receiver_username]);
            $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($receiver) {
                $receiver_id = $receiver['id'];

                // 2. Insere a nova mensagem no banco de dados
                $sql = "INSERT INTO messages (sender_id, receiver_id, subject, message_text, sent_date) 
                        VALUES (:sender_id, :receiver_id, :subject, :message_text, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'sender_id' => $user_id, 
                    'receiver_id' => $receiver_id, 
                    'subject' => $subject,
                    'message_text' => $message_text
                ]);
                $message = "Mensagem enviada com sucesso para " . htmlspecialchars($receiver_username) . "!";

            } else {
                $message = "Erro: Usuário destinatário não encontrado.";
            }
        } catch (PDOException $e) {
            $message = "Erro ao enviar a mensagem: " . $e->getMessage();
            $db_error = true;
        }
    }
}

// Lógica de Envio de Resposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $original_message_id = (int) $_POST['original_message_id'];
    $reply_text = trim($_POST['reply_text']);
    $original_subject = trim($_POST['subject'] ?? 'Sem Assunto');
    $subject = 'Re: ' . $original_subject; 
    $recipient_id = (int) $_POST['recipient_id']; 

    if (empty($reply_text) || $recipient_id <= 0) {
        $message = "A resposta não pode estar vazia ou o destinatário é inválido.";
    } else {
        try {
            // Insere a resposta como uma NOVA mensagem do criador para o remetente original
            $sql = "INSERT INTO messages (sender_id, receiver_id, subject, message_text, sent_date, is_creator_reply) 
                    VALUES (:sender_id, :receiver_id, :subject, :message_text, NOW(), 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'sender_id' => $user_id, 
                'receiver_id' => $recipient_id, 
                'subject' => $subject,
                'message_text' => $reply_text
            ]);
            $message = "Resposta enviada com sucesso!";
            header('Location: dashboard.php?tab=messages-tab');
            exit;

        } catch (PDOException $e) {
            $message = "Erro ao enviar a resposta: " . $e->getMessage();
            $db_error = true;
        }
    }
}

// Lógica de Busca de Mensagens Recebidas
$received_messages = [];
$reply_form_id = $_GET['reply_to'] ?? null;

try {
    // ATENÇÃO: Esta query agora funciona porque a coluna 'm.subject' foi adicionada
    $sql = "
        SELECT 
            m.id, 
            m.subject,             /* AGORA EXISTE NO BANCO */
            m.message_text, 
            m.sent_date, 
            u.username AS sender_username, 
            u.id AS sender_id
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.receiver_id = :user_id 
        AND m.is_creator_reply = 0 /* AGORA EXISTE NO BANCO */
        ORDER BY m.sent_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $received_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Mantenha esta linha para debug, mas agora deve parar de dar erro de coluna
    $message = "Erro ao carregar a Caixa de Entrada: " . $e->getMessage();
    $db_error = true;
}

// --------------------------------------------------------
// NOVO: LÓGICA DE CUSTOMIZAÇÃO DO CANAL
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_channel_customization'])) {

    // Define diretórios de upload (Certifique-se de que as permissões 0777 estão corretas)
    $upload_dir_icon = 'uploads/icons/';
    $upload_dir_banner = 'uploads/banners/';
    $upload_dir_background = 'uploads/backgrounds/'; 

    if (!is_dir($upload_dir_icon)) mkdir($upload_dir_icon, 0777, true);
    if (!is_dir($upload_dir_banner)) mkdir($upload_dir_banner, 0777, true);
    if (!is_dir($upload_dir_background)) mkdir($upload_dir_background, 0777, true);

    $update_fields = [];
    $update_values = [];

    // --- FUNÇÃO AUXILIAR PARA UPLOAD DE IMAGEM ---
    function handle_upload($file_key, $upload_dir, $db_field, $pdo, $user_id) {
        global $message, $update_fields, $update_values;

        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$file_key];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            // Nome de arquivo baseado no ID do usuário para evitar duplicatas e sobrescrever
            $file_name = $user_id . '_' . $db_field . '.' . $ext; 
            $file_path = $upload_dir . $file_name;

            // Tenta mover o arquivo
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Adiciona o campo e o caminho à lista de atualização do DB
                $update_fields[] = "{$db_field} = :{$db_field}";
                $update_values[":{$db_field}"] = $file_path;
                return true;
            } else {
                $message .= "Erro ao mover o arquivo de {$db_field}. Verifique as permissões de pasta. ";
                return false;
            }
        }
        return true; // Arquivo não enviado, continua o processo
    }

    // --- 1. Upload de Ícone de Perfil (profile_icon_path) ---
    handle_upload('profile_icon', $upload_dir_icon, 'profile_icon_path', $pdo, $user_id);

    // --- 2. Upload de Banner (channel_banner_path) ---
    handle_upload('channel_banner', $upload_dir_banner, 'channel_banner_path', $pdo, $user_id);
    
    // --- 3. Upload de Background (channel_background_path) ---
    handle_upload('channel_background', $upload_dir_background, 'channel_background_path', $pdo, $user_id);

    if (!empty($update_fields)) {
        try {
            // Constrói e executa a query de atualização
            $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :user_id";
            $stmt = $pdo->prepare($sql);
            
            // Adiciona o user_id aos valores
            $update_values[':user_id'] = $user_id;

            $stmt->execute($update_values);
            
            $message = "<span style='color: green;'>Customizações do canal salvas com sucesso!</span>";
            header('Location: dashboard.php?tab=customize-channel');
            exit;

        } catch (PDOException $e) {
            $message = "<span style='color: red;'>Erro ao salvar customizações no banco de dados: " . $e->getMessage() . "</span>";
        }
    } else {
        $message = "Nenhum arquivo de customização foi enviado. Use a aba para enviar novas imagens.";
    }
    // Garante que a aba de customização esteja ativa após o POST
    $active_tab = 'customize-channel'; 
}

// --------------------------------------------------------
// NOVO: LÓGICA DE CUSTOMIZAÇÃO DO CANAL
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_channel_customization'])) {

    // Define diretórios de upload (Certifique-se de que as permissões 0777 estão corretas)
    $upload_dir_icon = 'uploads/icons/';
    $upload_dir_banner = 'uploads/banners/';
    $upload_dir_background = 'uploads/backgrounds/'; 

    if (!is_dir($upload_dir_icon)) mkdir($upload_dir_icon, 0777, true);
    if (!is_dir($upload_dir_banner)) mkdir($upload_dir_banner, 0777, true);
    if (!is_dir($upload_dir_background)) mkdir($upload_dir_background, 0777, true);

    $update_fields = [];
    $update_values = [];

    // --- FUNÇÃO AUXILIAR PARA UPLOAD DE IMAGEM ---
    function handle_upload($file_key, $upload_dir, $db_field, $pdo, $user_id) {
        global $message, $update_fields, $update_values;

        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$file_key];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            // Nome de arquivo baseado no ID do usuário para evitar duplicatas e sobrescrever
            $file_name = $user_id . '_' . $db_field . '.' . $ext; 
            $file_path = $upload_dir . $file_name;

            // Tenta mover o arquivo
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Adiciona o campo e o caminho à lista de atualização do DB
                $update_fields[] = "{$db_field} = :{$db_field}";
                $update_values[":{$db_field}"] = $file_path;
                return true;
            } else {
                $message .= "Erro ao mover o arquivo de {$db_field}. Verifique as permissões de pasta. ";
                return false;
            }
        }
        return true; // Arquivo não enviado, continua o processo
    }

    // --- 1. Upload de Ícone de Perfil (profile_icon_path) ---
    handle_upload('profile_icon', $upload_dir_icon, 'profile_icon_path', $pdo, $user_id);

    // --- 2. Upload de Banner (channel_banner_path) ---
    handle_upload('channel_banner', $upload_dir_banner, 'channel_banner_path', $pdo, $user_id);
    
    // --- 3. Upload de Background (channel_background_path) ---
    handle_upload('channel_background', $upload_dir_background, 'channel_background_path', $pdo, $user_id);

    if (!empty($update_fields)) {
        try {
            // Constrói e executa a query de atualização
            $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :user_id";
            $stmt = $pdo->prepare($sql);
            
            // Adiciona o user_id aos valores
            $update_values[':user_id'] = $user_id;

            $stmt->execute($update_values);
            
            $message = "<span style='color: green;'>Customizações do canal salvas com sucesso!</span>";
            header('Location: dashboard.php?tab=customize-channel');
            exit;

        } catch (PDOException $e) {
            $message = "<span style='color: red;'>Erro ao salvar customizações no banco de dados: " . $e->getMessage() . "</span>";
        }
    } else {
        $message = "Nenhum arquivo de customização foi enviado. Use a aba para enviar novas imagens.";
    }
    // Garante que a aba de customização esteja ativa após o POST
    $active_tab = 'customize-channel'; 
}

// --------------------------------------------------------
// 4. LÓGICA DE CONFIGURAÇÃO DE LAYOUT (NOVA)
// --------------------------------------------------------

// Carrega o layout mode atual do usuário
$current_layout_mode = '2011'; // Padrão se não for encontrado

try {
    $stmt = $pdo->prepare("SELECT layout_mode FROM users WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty($result['layout_mode'])) {
        $current_layout_mode = $result['layout_mode'];
    }
} catch (PDOException $e) {
    $message = "Erro ao carregar o modo de layout.";
}


// Lógica de Salvar o Modo de Layout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_layout'])) {
    $new_layout = $_POST['layout_mode'] ?? '2011';

    if ($new_layout === '2011' || $new_layout === '2013') {
        try {
            // Atualiza a coluna layout_mode na tabela users
            $sql = "UPDATE users SET layout_mode = :layout_mode WHERE id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'layout_mode' => $new_layout,
                'user_id' => $user_id
            ]);

            $message = "Modo de Layout atualizado para **{$new_layout}** com sucesso!";
            $current_layout_mode = $new_layout;

            // Redireciona para evitar re-submissão e recarregar a tela com a nova mensagem
            header('Location: dashboard.php?tab=layout-tab');
            exit;

        } catch (PDOException $e) {
            $message = "Erro ao salvar a configuração de layout: " . $e->getMessage();
            $db_error = true;
        }
    } else {
        $message = "Opção de layout inválida.";
    }
}

// No bloco de carregamento de dados (aprox. linha 150)
$edit_video_data = null;
if ($active_tab == 'edit-video' && isset($_GET['v'])) {
    $stmt_edit = $pdo->prepare("SELECT * FROM videos WHERE id = ? AND user_id = ?");
    $stmt_edit->execute([$_GET['v'], $user_id]);
    $edit_video_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}


?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Dashboard do Criador - YouPoop™</title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <style>
        /* ============================================== */
        /* ESTILOS YOUTUBE 2012 GERAIS */
        /* ============================================== */
        body {
            overflow-x: hidden;
            font-family: Arial, Helvetica, sans-serif; /* Padrão 2012 */
            /*background-color: #F8F8F8;*/
            background: #A7C3FF;
            color: #333333;
            margin: 0;
            padding: 0;
            font-size: 13px;
        }

        body::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        a {
            color: #0066CC; /* Azul clássico do YouTube */
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        
        /* --- CABEÇALHO/LOGO --- */
        .ytp-logo {
            background-color: #FFFFFF;
            border-bottom: 1px solid #E5E5E5;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Estilo do Logo (para o ícone e nome ficarem na mesma linha) */
        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo img {
            height: 30px;
            filter: drop-shadow(0px 0px 1px black);
        }
        
        /* --- MENU DE NAVEGAÇÃO PRINCIPAL (Cores 2012) --- */
        .main-nav {
            background: #F1F1F1; /* Mais claro */
            border-bottom: 1px solid #E5E5E5;
            padding: 0 20px;
            display: flex;
            justify-content: flex-start;
        }
        .main-nav-item {
            display: inline-block;
            padding: 10px 15px;
            color: #555;
            font-weight: 500;
            font-size: 13px;
            border-bottom: 3px solid transparent;
            transition: background-color 0.1s;
        }
        .main-nav-item:hover {
            background-color: #E5E5E5;
            color: #333;
        }
        .main-nav-item.active-nav {
            border-bottom-color: #CC181E; 
            color: #000;
            font-weight: bold;
            background-color: #E5E5E5;
        }

        /* --- CONTAINER DA DASHBOARD --- */
        .dashboard-container {
            width: 90%;
            max-width: 1280px; 
            margin: 20px auto;
            display: flex;
        }
        .sidebar {
            width: 220px;
            flex-shrink: 0;
            background-color: #f4f4f4; 
            padding: 10px 0;
            height: fit-content;
            box-shadow: inset 0 0px 9px 1px rgb(0 0 0 / 8%);
        }
        .sidebar h2 {
            font-size: 1.2em;
            color: #555;
            padding: 0 15px 5px 15px;
            border-bottom: 1px solid #EEE;
            margin: 0 0 10px 0;
            font-weight: bold;
        }
        .sidebar a {
            display: block;
            padding: 10px 15px;
            color: #333;
            font-size: 14px;
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        .sidebar a:hover {
            background-color: #F0F0F0;
            border-left-color: #D3D3D3;
        }
        .sidebar a.active-tab {
            background-color: #E5ECF9; 
            font-weight: bold;
            border-left-color: #f88475; 
            color: #000;
        }

        .content {
            flex-grow: 1;
            background: linear-gradient(0deg, #f2f2f2, #FFF);
            border: 1px solid #CCC;
            padding: 20px;
            min-height: 500px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .content h1 {
            font-size: 1.8em;
            font-weight: 500;
            color: #333;
            border-bottom: 1px solid #E5E5E5;
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 20px;
        }

        /* --- ESTILOS DE BOTÕES E TABELAS --- */
        .message {
            background: linear-gradient(0deg, #eec85a, #FFF2CD);
            padding: 10px;
            border: 1px solid #FFE79D;
            margin-bottom: 15px;
            color: #333;
            font-weight: bold;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            border: 1px solid #DDD;
        }
        th, td {
            border: 1px solid #E5E5E5;
            padding: 10px 8px;
            font-size: 13px;
            text-align: left;
        }
        th {
            background-color: #F5F5F5;
            font-weight: bold;
        }
        .video-thumbnail {
            width: 80px;
            height: 45px;
            object-fit: cover;
            border: 1px solid #DDD;
        }

        /* Estilo Botão Primário (Azul Gradiente 2012) */
        .primary-btn-2012 {
            display: inline-block;
            background-color: #007ED7; 
            background-image: linear-gradient(to bottom, #0088CC, #006AA9);
            color: white;
            padding: 8px 15px;
            font-size: 13px;
            font-weight: bold;
            border: 1px solid #005A8C;
            border-radius: 2px;
            cursor: pointer;
            box-shadow: 0 1px 1px rgba(0,0,0,0.2);
            text-shadow: 0 -1px 0 rgba(0,0,0,0.3);
            text-transform: none; 
        }
        .primary-btn-2012:hover {
            background-color: #006AA9;
            background-image: linear-gradient(to bottom, #006AA9, #004D70);
            border-color: #004D70;
        }
        
        .action-button {
            padding: 5px 10px;
            font-size: 12px;
            background-color: #F8F8F8;
            border: 1px solid #CCC;
            border-radius: 2px;
            box-shadow: 0 1px 0 rgba(0,0,0,0.05);
            cursor: pointer;
            margin-right: 5px;
        }
        .action-button:hover {
            background-color: #E5E5E5;
        }


        /* ============================================== */
        /* ABA UPLOAD (2012 Look) */
        /* ============================================== */
        #upload-tab {
            text-align: center;
        }
        .upload-area {
            border: 1px solid #CCC; 
            background-color: #f3f3f3;
            padding: 30px;
            margin-bottom: 30px;
            transition: all 0.2s;
            cursor: default; 
            border-radius: 2px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .upload-area .upload-icon {
            background: url('images/youpoophd/upload/upload_icon_arrow.png') no-repeat center center; /* Placeholder para o ícone */
            background-size: 150px;
            width: 150px;
            height: 150px;
            margin: 0 auto 10px auto;
            filter: saturate(0) contrast(0.3) brightness(1.5);
        }
        .upload-text h2 {
            font-size: 2em;
            font-weight: 300;
            color: #333;
            margin: 0;
        }
        .upload-text p {
            color: #666;
            font-size: 1.1em;
            margin-top: 5px;
        }
        .upload-area button {
            margin-top: 20px;
        }
        #video_file_real {
            display: none;
        }

        input[type="checkbox"] {
            /* Remove a aparência padrão do navegador */
            -webkit-appearance: none;
            appearance: none; 
            
            /* Seus estilos de tamanho e layout */
            width: 13px;
            height: 13px;
            margin: 0;
            cursor: pointer;
            vertical-align: bottom;
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 1px;
            box-sizing: border-box; 
            position: relative; /* Necessário para posicionar o ícone dentro dele */
            
            /* Garante que o ícone de marcação comece invisível */
            display: block; 
        }

        /* 2. Estilização do ícone de Marcação (O que aparece APÓS o clique) */
        input[type="checkbox"]:checked::after {
            content: ''; /* O conteúdo pode ser vazio, pois usaremos uma imagem de fundo */
            
            /* Define o tamanho e a posição do ícone */
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;

            /* --- ONDE VOCÊ INSERE A IMAGEM DE ATIVADO --- */
            /* Substitua 'caminho/para/sua/imagem-check.svg' pelo link da sua imagem. */
            background-image: url('certo.svg'); 
            background-size: cover; /* Ajusta a imagem para cobrir a caixa */
            background-repeat: no-repeat;
            background-position: center;
        }

        /* 3. Estilo adicional (Opcional: para quando a caixa está marcada) */
        input[type="checkbox"]:checked {
            /* Altera a borda quando ativado (para indicar sucesso/seleção) */
            border-color: #007bff; 
        }

        /* ============================================== */
        /* NOVO ESTILO DA BARRA DE PROGRESSO (STRIPED) */
        /* ============================================== */

        /* Container da Barra (A parte cinza/fundo) */
        .progress-container-2013 {
            width: 80%;
            height: 25px; /* Altura um pouco maior */
            background-color: #332f2f;
            border: 1px solid #CCC;
            margin: 20px auto;
            overflow: hidden;
            display: none;
            box-shadow: inset 0 1px 2px 3px rgba(0, 0, 0, .1)
        }

        /* Barra de Progresso em Si (A parte colorida) */
        .progress-bar-2013 {
            height: 100%;
            width: 0%;
            
            /* Cores em Azul Claro */
            background-color: #5BC0DE; /* Azul base */
            background-image: linear-gradient(to bottom, #5BC0DE, #46B8DA); /* Gradiente suave */
            
            /* Efeito Listrado (Striped) */
            background-size: 40px 40px;
            background-image: linear-gradient(
                -45deg, 
                rgba(255, 255, 255, .15) 25%, 
                transparent 25%, 
                transparent 50%, 
                rgba(255, 255, 255, .15) 50%, 
                rgba(255, 255, 255, .15) 75%, 
                transparent 75%, 
                transparent
            );
            
            /* Animação para o efeito listrado (opcional, mas legal!) */
            animation: progress-bar-stripes 2s linear infinite;
            
            transition: width 0.4s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            color: white;
            font-weight: bold;
            font-size: 14px; /* Texto um pouco maior */
            padding-right: 10px;
            box-sizing: border-box;
            text-shadow: 0 0px 2px rgb(0 0 0);
        }

        /* Keyframes para a Animação do Listrado */
        @keyframes progress-bar-stripes {
            from { background-position: 40px 0; }
            to { background-position: 0 0; }
        }
        
        /* --- ESTILOS DAS ABAS INTERNAS DE UPLOAD --- */
        .upload-tabs-header {
            display: flex;
            justify-content: flex-start;
            border-bottom: 1px solid #CCC;
            margin-bottom: 15px;
            text-align: left;
        }
        .upload-tab-btn {
            padding: 10px 15px;
            cursor: pointer;
            background-color: #F8F8F8;
            border: 1px solid #CCC;
            border-bottom: none;
            margin-right: 5px;
            border-radius: 2px 2px 0 0;
            font-weight: bold;
            color: #555;
            font-size: 13px;
        }
        .upload-tab-btn.active-tab-inner {
            background-color: #FAFAFA;
            border-bottom: 1px solid #FAFAFA; /* Esconde a borda de baixo */
            color: #000;
            margin-bottom: -1px; 
            position: relative;
            z-index: 1;
        }
        
        .video-details-form {
            text-align: left;
            padding: 20px 0;
        }

        .zero-padding {
            padding: 0 0;
        }

        .video-details-form label {
            font-family: Arial, Helvetica, sans-serif;
            display: block;
            margin-top: 15px;
            margin-bottom: 7px;
            font-weight: bolder;
            color: #333;
        }
        .video-details-form input[type="text"],
        .video-details-form textarea,
        .video-details-form select,
        .video-details-form input[type="number"],
        .video-details-form input[type="color"] {
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(to bottom, #fff 0, #e0e0e0 100%);
            font-weight: normal;
            width: 60%;
            padding: 9px;
            border: 1px solid #CCC;
            box-sizing: border-box;
            margin-bottom: 10px;
            font-size: 13px;
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
            color: #757575;
        }

        input:focus {
            outline: none;
        }

        textarea:focus {
            outline: none;
        }

        select:focus {
            outline: none;
        }

        .file-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .file-input-group input[type="file"] {
            border: 1px solid #D9D9D9;
            padding: 5px;
        }

        .submit-upload-btn {
            background-color: #DD4B39; 
            background-image: linear-gradient(to bottom, #DD4B39, #C53727);
            border: 1px solid #C53727;
        }
        .submit-upload-btn:hover {
            background-color: #C53727;
            background-image: linear-gradient(to bottom, #C53727, #AF3223);
        }
        
        /* --- ESTILO DA PRÉ-VISUALIZAÇÃO --- */
        .video-preview-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            background-color: #F8F8F8;
            border: 1px solid #E0E0E0;
            padding: 15px;
            border-radius: 2px;
        }
        .video-player-frame {
            width: 320px; /* Padrão player 2012 */
            height: 180px;
            border: 1px solid #000;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            background-color: #000;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .video-player-frame video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .video-info-preview {

        }
        .video-info-preview h3 {
            margin-top: 0;
            font-size: 1.2em;
            color: #333;
        }

        @keyframes slideInFade {
            0% {
                    opacity: 0;
                }
            100% {
                    opacity: 1;
                }
            }

        #thumbnailPreviewImage {
            max-width: 230px;
        }

        /* Estilos para a área de pré-visualização da Thumbnail */
        .thumbnail-preview-area {
            /* Mantenha seus estilos anteriores: */
            margin-top: 15px; 
            text-align: center; 
            border: 1px solid #ddd; 
            padding: 10px;
            
            /* Configurações INICIAIS para a animação */
            opacity: 0; /* Inicia invisível */
            transition: opacity 0.2s ease-out; /* Transição para o fallback */
            
            /* Garante que o display seja flexível para a animação */
            /* ATENÇÃO: Se você está usando `display: none;` para esconder, vamos remover essa linha */
        }

        .thumbnail-preview-area.show {
            animation: slideInFade 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards; /* Animação elegante */
            opacity: 1;
        }

        /* ============================================== */
        /* ABA DE ESTATÍSTICAS */
        /* ============================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: #F9F9F9;
            border: 1px solid #E0E0E0;
            padding: 20px;
            text-align: center;
            border-radius: 2px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.05);
        }
        .stat-card .value {
            font-size: 3em;
            font-weight: 300;
            margin: 0 0 5px 0;
            color: #333;
        }
        .stat-card .label {
            font-size: 1.1em;
            color: #666;
        }
        .chart-placeholder {
            width: 100%;
            height: 250px;
            background-color: #F0F0F0;
            border: 1px solid #CCC;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            font-weight: bold;
        }
        /* Cor de destaque da borda para as estatísticas */
        /* Nota: Usei 'border-left' na visão geral e 'border-bottom' nas estatísticas para diferenciação de estilo */
        .stat-card[style*="#007ED7"] { border-color: #007ED7 !important; border-width: 1px 1px 5px 1px; }
        .stat-card[style*="#CC181E"] { border-color: #CC181E !important; border-width: 1px 1px 5px 1px; }
        .stat-card[style*="#DD4B39"] { border-color: #DD4B39 !important; border-width: 1px 1px 5px 1px; }
        .stat-card[style*="#00AA00"] { border-color: #00AA00 !important; border-width: 1px 1px 5px 1px; }

        /* NOVO: Estilo da aba de Mensagens */
        .message-form input[type="text"], .message-form textarea {
            display: block;
            width: 100%;
            margin-top: 5px;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
        }
        .message-box { 
            border: 1px solid #ddd; 
            padding: 10px; 
            margin-bottom: 10px; 
            background-color: #f9f9f9; 
            border-left: 5px solid #e95757; /* Linha de destaque para nova mensagem */
            position: relative;
        }

        .message-box::before {
            content: "";
            width: 0px;
            height: 0px;
            border-left: 10px solid transparent; 
            border-right: 10px solid transparent; 
            border-bottom: 10px solid #f9f9f9;
            position: absolute;
            top: -10px;
            left: 1px;
            filter: drop-shadow(0px -1px 0px #ddd);
        }

        .message-meta { font-size: 12px; color: #666; margin-top: 5px; }
        .message-sender { font-weight: bold; color: #003fff; }
        .message-content { margin-top: 9px; font-family: sans-serif; font-size: 15px;}

        .send_message {
            background-color: #DD4B39; 
            background-image: linear-gradient(to bottom, #DD4B39, #C53727);
            border: 1px solid #C53727;
        }

        .send_message:hover {
            background-color: #C53727;
            background-image: linear-gradient(to bottom, #C53727, #AF3223);
        }

        .message-form label {
            font-family: Arial, Helvetica, sans-serif;
            display: block;
            margin-top: 15px;
            margin-bottom: 7px;
            font-weight: bolder;
            color: #333;
        }

        .message-form input[type="text"],
        .message-form textarea,
        .message-form select,
        .message-form input[type="number"],
        .message-form input[type="color"] {
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(to bottom, #fff 0, #e0e0e0 100%);
            font-weight: normal;
            width: 60%;
            padding: 9px;
            border: 1px solid #CCC;
            box-sizing: border-box;
            margin-bottom: 10px;
            font-size: 13px;
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
            color: #757575;
        }

        #my-layout-tab input[type="text"],
        #my-layout-tab textarea,
        #my-layout-tab select,
        #my-layout-tab input[type="number"],
        #my-layout-tab input[type="color"] {
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(to bottom, #fff 0, #e0e0e0 100%);
            font-weight: normal;
            width: 60%;
            padding: 9px;
            border: 1px solid #CCC;
            box-sizing: border-box;
            margin-bottom: 10px;
            font-size: 13px;
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
            color: #757575;
        }

        #basic-info-tab input[type="text"],
        #basic-info-tab textarea,
        #basic-info-tab select,
        #basic-info-tab input[type="number"],
        #basic-info-tab input[type="color"] {
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(to bottom, #fff 0, #e0e0e0 100%);
            font-weight: normal;
            width: 60%;
            padding: 9px;
            border: 1px solid #CCC;
            box-sizing: border-box;
            margin-bottom: 10px;
            font-size: 13px;
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
            color: #757575;
        }

        #edit-annotation input[type="text"],
        #edit-annotation textarea,
        #edit-annotation select,
        #edit-annotation input[type="number"],
        #edit-annotation input[type="color"] {
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(to bottom, #fff 0, #e0e0e0 100%);
            font-weight: normal;
            width: 60%;
            padding: 9px;
            border: 1px solid #CCC;
            box-sizing: border-box;
            margin-bottom: 10px;
            font-size: 13px;
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
            color: #757575;
        }

        /* Estilo para o contêiner de cada categoria */
        .nav-category {
            margin-bottom: 10px;
            padding-bottom: 5px;
        }

        /* Estilo para o Título da Categoria */
        .nav-category h3 {
            font-size: 11px;
            color: #b7b7b7; /* Cinza escuro para destaque da categoria */
            font-weight: bold;
            text-transform: uppercase;
            margin: 10px 0px 10px 0;
            padding: 0 10px;
            border-top: 1px solid #E5E5E5; /* Linha divisória fina */
            padding-top: 10px;
        }

        /* Remove a linha divisória do primeiro cabeçalho para não ficar estranho no topo */
        .nav-category:first-child h3 {
            border-top: none;
            padding-top: 0;
        }

        /* Garante que os links dentro das categorias fiquem alinhados */
        .sidebar .nav-link {
            display: block;
            padding: 5px 10px;
        }

        /* Reafirma o estilo do link ativo (vermelho na lateral) */
        .sidebar .nav-link.active {
            border-left: 3px solid #D12323;
            padding-left: 7px;
        }

        #basic-info-tab label {
            font-family: Arial, Helvetica, sans-serif;
            display: block;
            margin-top: 15px;
            margin-bottom: 7px;
            font-weight: bolder;
            color: #333;
        }

        .video-count-badge {
            font-size: 0.8em;
            color: #e5e5e5;
            font-weight: normal;
            /* margin-left: 5px; */
            background: #767676;
            padding: 2px 8px;
            border-radius: 4px;
        }

        /* ============================================== */
        /* ESTILOS DA BARRA DE AÇÕES (BULK ACTIONS)       */
        /* ============================================== */
        .actions-toolbar {
            background-color: #f1f1f1;
            border: 1px solid #e2e2e2;
            padding: 8px 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative; /* Para o dropdown funcionar */
        }

        .actions-dropdown-menu {
            display: none; /* Oculto por padrão */
            position: absolute;
            top: 100%;
            left: 40px; /* Alinhado com o botão */
            background-color: #fff;
            border: 1px solid #ccc;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            min-width: 150px;
            z-index: 100;
        }

        .actions-dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 8px 15px;
            color: #333;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background-color: #eee;
            color: #000;
        }

        .checkbox-col {
            justify-items: center;
            width: 20px;
            text-align: center;
            background-color: #fafafa;
        }

        .topbar {
            display: flex;
            padding: 12px 10px 12px 10px;
            border-bottom: 1px solid #f1f1f1;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
        }

        .tabs-fuck {
            text-decoration: none;
            color: #606060;
            font-size: 14px;
            padding-bottom: 7px;
            display: block;
            border-bottom: 3px solid transparent;
        }

        .tabs-button {
            color: #666;
            display: block;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: normal;
            font-size: 13px;
            padding: 6px 12px;
            box-sizing: border-box;
            cursor: pointer;
            background: transparent;
            border: none;
        }

        .blue-btn {
            background-color: #6891e7;
            border-color: #0053a6 #0053a6 #000;
            text-shadow: 0 -1px 0 rgba(0, 0, 0, .5);
            -moz-box-shadow: inset 0 1px 0 rgba(256, 256, 256, .35);
            -ms-box-shadow: inset 0 1px 0 rgba(256, 256, 256, .35);
            -webkit-box-shadow: inset 0 1px 0 rgba(256, 256, 256, .35);
            box-shadow: inset 0 1px 0 rgba(256, 256, 256, .35);
            filter: progid:DXImageTransform.Microsoft.Gradient(GradientType=0, StartColorStr=#ff4495e7, EndColorStr=#ff0053a6);
            background-image: -moz-linear-gradient(top, #4495e7 0, #0053a6 100%);
            background-image: -ms-linear-gradient(top, #4495e7 0, #0053a6 100%);
            background-image: -o-linear-gradient(top, #4495e7 0, #0053a6 100%);
            background-image: -webkit-gradient(linear, left top, left bottom, color-stop(0, #4495e7), color-stop(100%, #0053a6));
            background-image: -webkit-linear-gradient(top, #4495e7 0, #0053a6 100%);
            background-image: linear-gradient(to bottom, #4495e7 0, #0053a6 100%);
        }

        .blue-btn:hover {
            border-color: #002d59 #002d59 #000;
            filter: progid:DXImageTransform.Microsoft.Gradient(GradientType=0, StartColorStr=#ff096bd2, EndColorStr=#ff0053a6);
            background-image: -moz-linear-gradient(top, #096bd2 0, #0053a6 100%);
            background-image: -ms-linear-gradient(top, #096bd2 0, #0053a6 100%);
            background-image: -o-linear-gradient(top, #096bd2 0, #0053a6 100%);
            background-image: -webkit-gradient(linear, left top, left bottom, color-stop(0, #096bd2), color-stop(100%, #0053a6));
            background-image: -webkit-linear-gradient(top, #096bd2 0, #0053a6 100%);
            background-image: linear-gradient(to bottom, #096bd2 0, #0053a6 100%);
        }

        .uix-button {
            height: 2.95em;
            padding: 0 .91em;
            border: 1px solid;
            outline: 0;
            font-weight: bold;
            font-size: 11px;
            white-space: nowrap;
            word-wrap: normal;
            vertical-align: middle;
            cursor: pointer;
            *overflow: visible;
            -moz-border-radius: 2px;
            -webkit-border-radius: 2px;
            border-radius: 2px;
            font: 12px arial, sans-serif;
            width: max-content;
        }

        .color-white {
            color: #fff;
        }

    </style>
</head>
<body>

    <div class="ytp-logo">
        <div class="logo">
            <a href="index.php">
                <img src="images/youpoophd/logo/youtube_logo_2005_v1.png" alt="logo">
            </a>
        </div>
        <div class="user-info">
            <?php if (isset($_SESSION['user_id'])): ?>
                Olá, <strong><?php echo htmlspecialchars($username); ?></strong>
                <a href="logout.php">SAIR</a>
            <?php else: ?>
                <a href="login.php" class="primary-btn-2012" style="text-decoration: none; margin-left: 10px;">FAZER LOGIN</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <h2>PAINEL DO CANAL</h2>
            <div class="nav-category">
                <h3>ANALYTICS</h3>
                <a href="dashboard.php?tab=overview-tab" class="nav-link <?php if ($active_tab == 'overview-tab') echo 'active'; ?>">Estatísticas</a>
                <a href="dashboard.php?tab=global-stats-tab" class="nav-link <?php if ($active_tab == 'global-stats-tab') echo 'active'; ?>">Estatísticas Avançadas</a>
            </div>
            <div class="nav-category">
                <h3>GERENCIAMENTO DE VÍDEOS</h3>
                <a href="dashboard.php?tab=my-videos" class="nav-link <?php if ($active_tab == 'my-videos') echo 'active'; ?>">Meus Vídeos</a>
                <a href="dashboard.php?tab=upload-tab" class="nav-link <?php if ($active_tab == 'upload-tab') echo 'active'; ?>">Enviar Vídeos</a>
            </div>
            <div class="nav-category">
                <h3>CAIXA DE ENTRADA</h3>
                <a href="dashboard.php?tab=messages-tab" class="nav-link <?php if ($active_tab == 'messages-tab') echo 'active'; ?>">Mensagens</a>
            </div>
            <div class="nav-category">
                <h3>CONFIGURAÇÕES DO CANAL</h3>
                <a href="dashboard.php?tab=basic-info-tab" class="nav-link <?php if ($active_tab == 'basic-info-tab') echo 'active'; ?>">Informações</a>
                <a href="dashboard.php?tab=customize-channel-tab" class="nav-link <?php if ($active_tab == 'customize-channel-tab') echo 'active'; ?>">Customizar Canal</a>
                <a href="dashboard.php?tab=my-layout-tab" class="nav-link <?php if ($active_tab == 'my-layout-tab') echo 'active'; ?>">Meu Layout</a>
            </div>

            <div class="nav-category" style="">
                <h3>ACESSAR CANAL</h3>
                <?php 
                    // Esta variável deve ser definida na lógica PHP no topo do dashboard.php 
                    // (como fizemos na etapa anterior, carregando do banco de dados)
                    
                    // Determina o link com base no modo de layout atual
                    $channel_base_url = ($current_layout_mode == '2013') ? 'channel2013.php' : 'channel2011.php';
                    $channel_url = $channel_base_url . '?user=' . urlencode($username);
                ?>
                <a href="<?php echo $channel_url; ?>" target="_blank" 
                    class="nav-link" 
                    style="background-color: #DD4B39; background-image: linear-gradient(to bottom, #DD4B39, #C53727); color: white; padding: 8px 10px; text-align: center; font-weight: bold; margin-top: 10px;">
                    Ver Canal (<?php echo $current_layout_mode; ?>)
                </a>
                <p style="font-size: 10px; color: #999; margin-top: 5px; text-align: center;">Abre em nova aba</p>
            </div>

        </div>

        <div class="content">
            
            <?php 
            // Exibe mensagens de sucesso ou erro
            if (!empty($message)) {
                echo "<div class='message'>" . $message . "</div>";
            }
            ?>

            <div id="overview-tab" style="display: <?php echo ($active_tab == 'overview-tab' ? 'block' : 'none'); ?>;">
                <h1>Visão Geral da Conta</h1>
                <p>Olá, <strong><?php echo htmlspecialchars($username); ?></strong>! Bem-vindo ao seu painel de controle.</p>
                
                <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <div class="stat-card" style="border-bottom: 5px solid #007ED7;">
                        <div class="value"><?php echo $video_count; ?></div>
                        <div class="label">Vídeos Publicados</div>
                    </div>
                    <div class="stat-card" style="border-bottom: 5px solid #CC181E;">
                        <div class="value"><?php echo number_format($total_views, 0, ',', '.'); ?></div>
                        <div class="label">Total de Visualizações</div>
                    </div>
                </div>

                <h2>Últimos 3 Vídeos</h2>
                <?php if (empty($my_videos)): ?>
                    <p>Você ainda não enviou nenhum vídeo.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th></th>
                                <th>Título</th>
                                <th>Visualizações</th>
                                <th>Publicação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 0;
                            foreach ($my_videos as $video): 
                                if ($count >= 3) break;
                            ?>
                                <tr>
                                    <td style="width: 120px;"><img src="<?php echo htmlspecialchars($video['thumbnail_path']); ?>" class="video-thumbnail" alt="Thumbnail" style="width: 120px; height: 68px;"></td>
                                    <td><a href="watch.php?v=<?php echo $video['id']; ?>" target="_blank"><?php echo htmlspecialchars($video['title']); ?></a></td>
                                    <td><?php echo number_format($video['views'], 0, ',', '.'); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($video['upload_date'])); ?></td>
                                </tr>
                            <?php 
                                $count++;
                            endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div id="global-stats-tab" style="display: <?php echo ($active_tab == 'global-stats-tab' ? 'block' : 'none'); ?>;">
                <h1>Estatísticas do Canal</h1>
                
                <div class="stats-grid">
                    <div class="stat-card" style="border-bottom: 5px solid #007ED7;">
                        <div class="value"><?php echo number_format($subscribers, 0, ',', '.'); ?></div>
                        <div class="label">Total de Inscritos</div>
                    </div>
                    <div class="stat-card" style="border-bottom: 5px solid #DD4B39;">
                        <div class="value"><?php echo number_format($views_last_7_days, 0, ',', '.'); ?></div>
                        <div class="label">Visualizações (últimos 7 dias)</div>
                    </div>
                    <div class="stat-card" style="border-bottom: 5px solid #00AA00;">
                        <div class="value"><?php echo number_format($total_views, 0, ',', '.'); ?></div>
                        <div class="label">Visualizações (Total)</div>
                    </div>
                </div>
                
                <h2>Gráfico de Visualizações Recentes</h2>
                <div class="chart-placeholder">
                    Simulação de Gráfico de Linha (Visualizações por Dia)
                </div>

                <h2>Origem do Tráfego</h2>
                <div class="chart-placeholder" style="height: 150px;">
                    Simulação de Gráfico de Pizza (Busca: 50%, Sugestões: 30%, Externo: 20%)
                </div>
                
            </div>

            <div id="my-videos" style="display: <?php echo ($active_tab == 'my-videos' ? 'block' : 'none'); ?>;">
                <h1>
                    Meus Vídeos 
                    <span class="video-count-badge"><?php echo $video_count; ?></span>
                </h1>

                <div class="actions-toolbar">
                    <input type="checkbox" id="select-all-checkbox" onclick="toggleAllCheckboxes(this)">
                    
                    <div style="position: relative;">
                        <button id="bulk-actions-btn" class="action-button" onclick="toggleBulkMenu()">
                            Ações
                        </button>
                        
                        <div id="bulk-actions-menu" class="actions-dropdown-menu">
                            <div class="dropdown-item" onclick="openBulkDeleteModal()">Excluir</div>
                            </div>
                    </div>
                    <span style="font-size: 12px; color: #666;">Selecione os vídeos para editar</span>
                </div>
                
                <?php if (empty($my_videos)): ?>
                    <p>Você ainda não enviou nenhum vídeo. <a href="dashboard.php?tab=upload-tab">Clique aqui para começar!</a></p>
                <?php else: ?>
                    <table>
                        <tbody>
                            <?php foreach ($my_videos as $video): ?>
                                <tr>
                                    <td class="checkbox-col">
                                        <input type="checkbox" class="video-checkbox" value="<?php echo $video['id']; ?>">
                                    </td>
                                    <td style="width: 120px;">
                                        <img src="<?php echo htmlspecialchars($video['thumbnail_path']); ?>" class="video-thumbnail" alt="Thumbnail" style="width: 120px; height: 68px;">
                                    </td>
                                    <td>
                                        <a href="watch.php?v=<?php echo $video['id']; ?>" target="_blank" style="font-weight: bold; display: block; margin-bottom: 5px;"><?php echo htmlspecialchars($video['title']); ?></a>
                                        
                                        <div style="font-size: 11px; color: #666; margin-bottom: 10px;">
                                            Visibilidade: <strong><?php echo ucfirst($video['visibility']); ?></strong> | 
                                            Publicação: <strong><?php echo date("d/m/Y", strtotime($video['upload_date'])); ?></strong>
                                        </div>

                                        <a href="?tab=edit-video&v=<?= $video['id'] ?>" class="action-button">Editar</a>
                                        <button class="action-button" onclick="confirmDelete(<?php echo $video['id']; ?>)">Excluir</button>
                                        <a href="dashboard.php?tab=edit-annotation&v=<?php echo $video['id']; ?>" class="action-button">Anotação</a>
                                    </td>
                                    <td style="font-size: 12px; color: #555; width: 90px;">
                                        <div>
                                        <div style="margin-bottom: 10px;">
                                            <span style="font-weight: bold; color: #333;"><?php echo number_format($video['views'], 0, ',', '.'); ?></span> Visualizações
                                        </div>
                                        
                                        <div style="margin-bottom: 10px;">
                                            <span style="font-weight: bold; color: #333;"><?php echo htmlspecialchars($video['duration']); ?></span> Duração
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div id="bulkDeleteModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 350px; padding: 20px; background-color: white; border: 1px solid #CCC; box-shadow: 0 5px 15px rgba(0,0,0,0.3); border-radius: 2px;">
                    <h2>Excluir Vídeos Selecionados</h2>
                    <p>Tem certeza de que deseja excluir <strong id="selected-count">0</strong> vídeo(s)?</p>
                    <p style="font-size: 11px; color: red;">Esta ação não pode ser desfeita (Backend pendente).</p>
                    
                    <form method="POST" action="dashboard.php?tab=my-videos">
                        <input type="hidden" name="bulk_delete_ids" id="bulk-delete-ids-input">
                        
                        <div style="text-align: right; margin-top: 15px;">
                            <button type="button" class="primary-btn-2012" style="background-color: #DD4B39; background-image: none; margin-right: 5px;" onclick="submitBulkDelete()">Sim, Excluir</button>
                            <button type="button" onclick="document.getElementById('bulkDeleteModal').style.display='none'" class="action-button">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($active_tab == 'edit-video' && $edit_video_data): ?>
                <div class="topbar">
                    <a class="tabs-fuck" href="#">
                        <button class="tabs-button">Info e Configurações</button>
                        <button class="tabs-button">Audio</button>
                        <button class="tabs-button">Anotações e Legendas</button>
                    </a>
                    
                </div>
                <div class="video-details-form zero-padding">
                    <form method="POST" action="dashboard.php?tab=my-videos">
                        <input type="hidden" name="video_id" value="<?= $edit_video_data['id'] ?>">
                        
                        <label>Título:</label>
                        <input type="text" name="edit_title" value="<?= htmlspecialchars($edit_video_data['title']) ?>" required>
                        
                        <label>Descrição:</label>
                        <textarea name="edit_description" rows="5"><?= htmlspecialchars($edit_video_data['description']) ?></textarea>
                        
                        <label>Privacidade:</label>
                        <select name="edit_visibility">
                            <option value="public" <?= $edit_video_data['visibility'] == 'public' ? 'selected' : '' ?>>Público</option>
                            <option value="unlisted" <?= $edit_video_data['visibility'] == 'unlisted' ? 'selected' : '' ?>>Não Listado</option>
                            <option value="private" <?= $edit_video_data['visibility'] == 'private' ? 'selected' : '' ?>>Privado</option>
                        </select>
                        
                        <div style="margin-top: 20px;">
                            <button type="submit" name="edit_video" class="primary-btn-2012">Salvar Alterações</button>
                            <a href="?tab=my-videos" class="action-button" style="padding: 8px 15px;">Cancelar</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div id="upload-tab" style="display: <?php echo ($active_tab == 'upload-tab' ? 'block' : 'none'); ?>;">
                <h1>Enviar Vídeo</h1>
                
                <form method="POST" enctype="multipart/form-data" id="uploadForm" action="dashboard.php?tab=upload-tab">
                    
                    <input type="hidden" name="upload_video" value="1">
                    
                    <div class="upload-area" id="dropArea">
                        <div class="upload-icon"></div>
                        <div class="upload-text">
                            <h2>Selecione arquivos do seu computador</h2>
                            <p>Arraste e solte seu arquivo de vídeo para fazer o upload.</p>
                            <button type="button" class="primary-btn-2012" onclick="document.getElementById('video_file_real').click()">
                                Enviar Vídeo
                            </button>
                            <input type="file" name="video_file" id="video_file_real" accept="video/*" required>
                        </div>
                    </div>

                    <p>Isso não esta funcionando ainda, então <a href="simpleupload.php">Clique aqui</a></p>
                    
                    <div class="progress-container-2013" id="simulatedProgressContainer">
                        <div class="progress-bar-2013" id="simulatedProgressBar">
                            <span id="progressText">0%</span>
                        </div>
                    </div>
                    
                    <div class="video-preview-container" id="videoPreviewContainer" style="display: none;">
                        <div class="video-player-frame">
                            <video id="videoPreviewPlayer" controls style="max-width: 100%; height: 100%;">
                                Seu navegador não suporta a tag de vídeo.
                            </video>
                        </div>
                        <div class="video-info-preview">
                            <h3>Pré-visualização do Vídeo</h3>
                            <p>Nome do Arquivo: <strong id="previewFileName">N/A</strong></p>
                            <p>Duração: <strong id="previewDuration">00:00:00</strong></p>
                        </div>
                    </div>

                    <div id="videoUploadDetails" style="display: none;">
                        <div class="upload-tabs-header" style="margin-top: 20px;">
                            <button type="button" class="upload-tab-btn active-tab-inner" onclick="showUploadTab('basic-settings')">Informações Básicas</button>
                            <button type="button" class="upload-tab-btn" onclick="showUploadTab('advanced-settings')">Configurações Avançadas</button>
                        </div>

                        <div class="video-details-form">
                            
                            <div id="basic-settings" class="upload-tab-content">
                                <label for="title">Título</label>
                                <input type="text" id="title" name="title" required maxlength="100" autocorrect="off" spellcheck="false" style="background: white;">
                                
                                <label for="description">Descrição</label>
                                <textarea id="description" name="description" rows="5" maxlength="5000" required style="background: white;"></textarea>
                                
                                <label for="thumbnail_file">Miniatura</label>
                                <div class="file-input-group">
                                    <input type="file" name="thumbnail_file" id="thumbnail_file" accept="image/jpeg, image/png" required>
                                </div>
                                
                                <div class="thumbnail-preview-area" id="thumbnailPreviewArea" style="margin-top: 15px; display: none; text-align: center; border: 1px solid #ddd; padding: 10px; margin-right: 750px;">
                                    <p style="margin-top: -5px; margin-bottom: 5px; font-weight: bold;">Pré-visualização da Miniatura:</p>
                                    <img id="thumbnailPreviewImage" src="" alt="Thumbnail Preview"/>
                                </div>

                                <label for="duration">Duração</label>
                                <input type="text" id="video_duration_input" name="duration" pattern="\d{2}:\d{2}:\d{2}" maxlength="8" autocomplete="off" style="background: white;">

                                <label for="visibility">Visibilidade</label>
                                <select id="visibility" name="visibility" required style="width: 30%;">
                                    <option value="public">Público</option>
                                    <option value="unlisted">Não Listado</option>
                                    <option value="private">Privado</option>
                                </select>
                            </div>

                            <div id="advanced-settings" class="upload-tab-content" style="display: none;">
                                <h2>Configurações de Distribuição</h2>
                                
                                <label for="category">Categoria</label>
                                <select id="category" name="category">
                                    <option value="" disabled selected hidden>Selecione uma categoria</option>
                                    <option value="none">Nenhuma</option>
                                    <option value="ytp">YTP</option>
                                    <option value="mv">YTPMV</option>
                                    <option value="gaming">Jogos</option>
                                    <option value="music">Música</option>
                                    <option value="comedy">Comédia</option>
                                </select>
                                
                                <label for="tags">Tags</label>
                                <input type="text" id="tags" name="tags" style="background: white;">
                                
                                <label for="comments">Opções de Comentários</label>
                                <select id="comments" name="comments">
                                    <option value="allow">Permitir todos os comentários</option>
                                    <option value="approve">Comentários sujeitos à aprovação</option>
                                    <option value="disable">Desativar comentários</option>
                                </select>
                                
                            </div>
                            
                            <div style="overflow: auto; text-align: right; border-top: 1px solid #E5E5E5; padding-top: 15px; margin-top: 15px;">
                                <button type="submit" class="primary-btn-2012 submit-upload-btn">Concluir</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div id="messages-tab" class="tab-content <?php echo $active_tab == 'messages-tab' ? 'active' : ''; ?>" style="display: <?php echo ($active_tab == 'messages-tab' ? 'block' : 'none'); ?>;">
                <h1>Minhas Mensagens</h1>
                
                <div style="border: 1px solid #d6d0d0; padding: 15px; margin-bottom: 20px; background: #f6f6f6;">
                    <h3 style="margin-top: 10px; font-size: 16px; color: #676767;">Enviar Nova Mensagem</h3>
                    <form action="dashboard.php?tab=messages-tab" method="POST" class="message-form">
                        
                        <label for="receiver_username">Enviar para</label>
                        <input type="text" id="receiver_username" name="receiver_username" required autocomplete="off" style="background: white; margin-bottom: 15px;">

                        <label for="message_text">Mensagem</label>
                        <textarea id="message_text" name="message_text" rows="4" required autocomplete="off" style="background: white;"></textarea>

                        <button class="uix-button blue-btn color-white" type="submit" name="send_message">Enviar Mensagem</button>
                    </form>
                </div>

                <h3>Caixa de Entrada</h3>
                
                <?php if (empty($received_messages)): ?>
                    <p>Você não tem nenhuma mensagem na sua caixa de entrada.</p>
                <?php else: ?>
                    <?php 
                        // Obtém o ID da mensagem para a qual o formulário de resposta deve ser mostrado
                        $reply_form_id = $_GET['reply_to'] ?? null;
                    ?>
                    <?php foreach ($received_messages as $msg): ?>
                        <?php 
                            $is_reply_form_active = ($reply_form_id == $msg['id']);
                        ?>
                        <div class="message-box" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; background: #fff;">
                            
                            <div class="message-meta" style="font-size: 11px; color: #666; margin-bottom: 5px;">
                                Recebido de: <span class="message-sender" style="font-weight: bold;"><?php echo htmlspecialchars($msg['sender_username']); ?></span> em <?php echo date("d/m/Y H:i", strtotime($msg['sent_date'])); ?>
                            </div>
                            
                            <div class="message-content" style="padding-left: 10px;">
                                <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                            </div>
                            
                            <div style="margin-top: 10px;">
                                <a class="uix-button blue-btn color-white" href="dashboard.php?tab=messages-tab&reply_to=<?php echo $msg['id']; ?>" 
                                    style="cursor: pointer; display: inline-block; text-decoration: none;">
                                    Responder
                                </a>
                            </div>
                            
                            <?php if ($is_reply_form_active): ?>
                                <div class="reply-form" style="border-top: 1px solid #eee; margin-top: 15px; padding-top: 15px;">
                                    <form method="POST" action="dashboard.php?tab=messages-tab" style="display: flex; flex-direction: column;">
                                        <h4 style="margin-top: 0; margin-bottom: 10px; font-size: 17px;">Sua Resposta para <?php echo htmlspecialchars($msg['sender_username']); ?>:</h4>
                                        
                                        <input type="hidden" name="send_reply" value="1">
                                        <input type="hidden" name="original_message_id" value="<?php echo $msg['id']; ?>">
                                        <input type="hidden" name="recipient_id" value="<?php echo $msg['sender_id']; ?>">
                                        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($msg['subject'] ?? 'Mensagem'); ?>">

                                        <textarea name="reply_text" rows="4" maxlength="500" 
                                            style="font-family: Arial, Helvetica, sans-serif; background: white; font-weight: normal; border: 1px solid #CCC; font-size: 13px; border-radius: 4px; box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1); color: #757575; width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box;"></textarea>

                                        <button class="uix-button blue-btn color-white" type="submit">
                                            Enviar Resposta
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            </div>

            <div id="customize-channel-tab" style="display: <?php echo ($active_tab == 'customize-channel-tab' ? 'block' : 'none'); ?>;">
                <h1>Customizar Canal</h1>

                    <p style="color: #606060; margin-bottom: 20px;">Atualize as imagens de perfil, banner e background do seu canal.</p>

                    <div style="border: 1px solid #d6d0d0; padding: 15px; margin-bottom: 20px; background: #f6f6f6; text-align: left;">
                        <form method="POST" action="dashboard.php?tab=customize-channel" enctype="multipart/form-data">
                            <input type="hidden" name="save_channel_customization" value="1">
                            
                            <h3 style="margin-top: 5px; font-size: 16px; color: #676767;">Foto de Perfil</h3>
                            <label for="profile_icon" style="display: block; margin-bottom: 8px;">Imagem de perfil (Recomendado 98x98, JPG/PNG):</label>
                            <input type="file" id="profile_icon" name="profile_icon" accept="image/jpeg, image/png" style="margin-bottom: 10px;">
                            <?php if (!empty($profile_icon_path)): ?>
                                <div style="margin-bottom: 15px;">
                                    <strong style="display: block; font-size: 12px; color: #555;">Atual:</strong>
                                    <img src="<?php echo htmlspecialchars($profile_icon_path); ?>" alt="Profile Icon Preview" style="width: 80px; height: 80px; object-fit: cover; border: 1px solid #CCC; border-radius: 50%;">
                                </div>
                            <?php endif; ?>

                            <hr style="border: 0; border-top: 1px dashed #DDD; margin: 20px 0;">

                            <h3 style="margin-top: 5px; font-size: 16px; color: #676767;">Imagem de Fundo</h3>
                            <p style="font-size: 12px; color: #999; margin-top: -10px; margin-bottom: 15px;">A imagem de fundo da página (coluna: `channel_background_path`).</p>
                            <label for="channel_background" style="display: block; margin-bottom: 8px;">Imagem de Fundo (JPG/PNG):</label>
                            <input type="file" id="channel_background" name="channel_background" accept="image/jpeg, image/png" style="margin-bottom: 10px;">
                            <?php if (!empty($channel_background_path)): ?>
                                <div style="margin-bottom: 15px;">
                                    <strong style="display: block; font-size: 12px; color: #555;">Atual:</strong>
                                    <img src="<?php echo htmlspecialchars($channel_background_path); ?>" alt="Background Preview" style="max-width: 400px; height: 50px; border: 1px solid #CCC; object-fit: cover;">
                                </div>
                            <?php endif; ?>

                            <hr style="border: 0; border-top: 1px dashed #DDD; margin: 20px 0;">

                            <h3 style="margin-top: 5px; font-size: 16px; color: #676767;">Banner do Canal</h3>
                            <label for="channel_banner" style="display: block; margin-bottom: 8px;">Imagem do Banner (Recomendado 2560x423, JPG/PNG):</label>
                            <input type="file" id="channel_banner" name="channel_banner" accept="image/jpeg, image/png" style="margin-bottom: 10px;">
                            <?php if (!empty($channel_banner_path)): ?>
                                <div style="margin-bottom: 15px;">
                                    <strong style="display: block; font-size: 12px; color: #555;">Atual:</strong>
                                    <img src="<?php echo htmlspecialchars($channel_banner_path); ?>" alt="Banner Preview" style="width: 100%; max-width: 400px; height: 90px; object-fit: cover; border: 1px solid #CCC;">
                                </div>
                            <?php endif; ?>
                            </div>
                        </form>
                        <div style="overflow: auto; text-align: right; border-top: 1px solid #E5E5E5; padding-top: 15px; margin-top: 15px;">
                        <button type="submit" class="primary-btn-2012" name="save_channel_customization"> 
                            Concluir
                        </button>
                    </div>

                </div>

            <div id="my-layout-tab" style="display: <?php echo ($active_tab == 'my-layout-tab' ? 'block' : 'none'); ?>;">
                <h1>Meu Layout</h1>
                <p style="color: #606060; margin-bottom: 20px;">Escolha o estilo de design do seu canal.</p>

                <div style="border: 1px solid #d6d0d0; padding: 15px; margin-bottom: 20px; background: #f6f6f6;">
                    <form method="POST" action="dashboard.php?tab=layout-tab">
                        
                        <h3 style="margin-top: 5px; font-size: 16px; color: #676767;">Modo de Layout</h3>
                        
                        <label for="layout_mode" style="display: block; margin-bottom: 8px;">Layout Atual: <strong><?php echo $current_layout_mode; ?></strong></label>

                        <select id="layout_mode" name="layout_mode" required 
                                style="width: 250px; margin-bottom: 15px;">
                            <option value="2011" <?php if ($current_layout_mode == '2011') echo 'selected'; ?>>2011 (Padrão)</option>
                            <option value="2013" <?php if ($current_layout_mode == '2013') echo 'selected'; ?>>2013</option>
                        </select>

                        <button type="submit" name="save_layout" 
                                style="background-color: #DD4B39; background-image: linear-gradient(to bottom, #DD4B39, #C53727); border: 1px solid #C53727; font-weight: bold; color: white; padding: 8px 15px; border-radius: 2px; cursor: pointer; box-shadow: 0 1px 1px rgba(0, 0, 0, 0.2); text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.3);">
                            Salvar Layout
                        </button>
                    </form>
                </div>
            </div>

            <div id="basic-info-tab" style="display: <?php echo ($active_tab == 'basic-info-tab' ? 'block' : 'none'); ?>;">
                <h1>Informações Basicas</h1>
                    <label for="title">Nome do canal</label>
                    <input type="text" value="<?php echo htmlspecialchars($username); ?>" required autocomplete="off" style="background: white; margin-bottom: 15px;">
                    <div style="overflow: auto; text-align: right; border-top: 1px solid #E5E5E5; padding-top: 15px; margin-top: 15px;">
                        <button type="submit" class="primary-btn-2012 submit-upload-btn">Salvar</button>
                    </div>
                </div>

            <div id="edit-annotation" style="display: <?php echo ($active_tab == 'edit-annotation' ? 'block' : 'none'); ?>;">
                <h1>Editor de Anotação</h1>
                <?php if ($video_info): ?>
                    <p>Editando anotação para: <strong><?php echo htmlspecialchars($video_info['title']); ?></strong> (ID: <?php echo $annotation_video_id; ?>)</p>
                    
                    <form method="POST" action="dashboard.php?tab=edit-annotation&v=<?php echo $annotation_video_id; ?>" style="
                    padding: 20px;
                    border: 1px solid #CCC;
                    background-color: #F8F8F8;
                    display: flex;
                    align-items: flex-start;
                    flex-direction: column;
                    ">
                        <input type="hidden" name="save_annotation" value="1">
                        <input type="hidden" name="annotation_video_id" value="<?php echo $annotation_video_id; ?>">

                        <label for="link_text">Texto da Anotação:</label>
                        <input type="text" id="link_text" name="link_text" value="<?php echo htmlspecialchars($link_text ?? ''); ?>" maxlength="50" required style="background: white;">

                        <label for="link_url">URL de Destino (ex: http://www.site.com):</label>
                        <input type="text" id="link_url" name="link_url" value="<?php echo htmlspecialchars($link_url ?? ''); ?>" required style="background: white;">
                        
                        <label for="start_time">Tempo de Início</label>
                        <input type="number" id="start_time" name="start_time" value="<?php echo htmlspecialchars($start_time ?? 0); ?>" min="0" required style="background: white;">
                        
                        <label for="annotation_color">Cor de Fundo da Anotação:</label>
                        <input type="color" id="annotation_color" name="annotation_color" value="<?php echo htmlspecialchars($bg_color ?? '#FF0000'); ?>" style="
                        width: 100px;
                        height: 100px;
                        display: block;
                        background: linear-gradient(0deg, black, #4b4b4b);
                        box-shadow: 0px 0px 3px 0px #0000009c;
                        border: 0px solid transparent;
                        ">
                        
                        <div style="margin-top: 20px; text-align: right;">
                            <button type="submit" class="primary-btn-2012">Salvar Anotação</button>
                        </div>
                    </form>
                    
                <?php else: ?>
                    <p>Selecione um vídeo na aba "Meus Vídeos" para adicionar uma anotação.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div id="editModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 400px; background-color: white; padding: 20px; border: 1px solid #CCC; box-shadow: 0 5px 15px rgba(0,0,0,0.3); border-radius: 2px;">
            <h2>Editar Detalhes do Vídeo</h2>
            <form method="POST" action="dashboard.php?tab=my-videos">
                <input type="hidden" name="edit_video" value="1">
                <input type="hidden" name="video_id" id="edit-video-id-input">
                
                <label for="edit-title">Título</label>
                <input type="text" id="edit-title" name="edit_title" required>
                
                <label for="edit-description">Descrição</label>
                <textarea id="edit-description" name="edit_description" rows="5"></textarea>
                
                <label for="edit-visibility">Visibilidade</label>
                <select id="edit-visibility" name="edit_visibility" required>
                    <option value="public">Público</option>
                    <option value="unlisted">Não Listado</option>
                    <option value="private">Privado</option>
                </select>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="primary-btn-2012" style="background-color: #DD4B39; background-image: none; margin-right: 5px;">Salvar</button>
                    <button type="button" onclick="closeEditModal()" class="action-button">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 350px; padding: 20px; background-color: white; border: 1px solid #CCC; box-shadow: 0 5px 15px rgba(0,0,0,0.3); border-radius: 2px;">
            <h2>Confirmar Exclusão</h2>
            <p>Tem certeza de que deseja excluir este vídeo permanentemente? Esta ação não pode ser desfeita.</p>
            <form method="POST" action="dashboard.php?tab=my-videos">
                <input type="hidden" name="delete_video" value="1">
                <input type="hidden" name="video_id_to_delete" id="delete-video-id-input">
                <div style="text-align: right;">
                    <button type="submit" class="primary-btn-2012" style="background-color: #DD4B39; background-image: none; margin-right: 5px;">Sim, Excluir</button>
                    <button type="button" onclick="closeDeleteModal()" class="action-button">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

<script>

    // --------------------------------------------------------
    // LÓGICA DE ABAS INTERNAS DE UPLOAD
    // --------------------------------------------------------
    function showUploadTab(tabId) {
        // Oculta todos os conteúdos de abas internas
        document.querySelectorAll('.upload-tab-content').forEach(el => {
            el.style.display = 'none';
        });
        // Remove a classe ativa de todos os botões
        document.querySelectorAll('.upload-tab-btn').forEach(btn => {
            btn.classList.remove('active-tab-inner');
        });

        // Mostra o conteúdo da aba selecionada
        document.getElementById(tabId).style.display = 'block';
        
        // Adiciona a classe ativa ao botão clicado
        document.querySelector(`.upload-tab-btn[onclick*="${tabId}"]`).classList.add('active-tab-inner');
    }
    window.showUploadTab = showUploadTab;

    // --------------------------------------------------------
    // LÓGICA DE UPLOAD, PROGRESSO E PRÉ-VISUALIZAÇÃO (AJUSTADA)
    // --------------------------------------------------------
    const videoFileReal = document.getElementById('video_file_real');
    const dropArea = document.getElementById('dropArea');
    const simulatedProgressContainer = document.getElementById('simulatedProgressContainer');
    const simulatedProgressBar = document.getElementById('simulatedProgressBar');
    const progressText = document.getElementById('progressText');
    const durationInput = document.getElementById('video_duration_input');
    const videoPreviewContainer = document.getElementById('videoPreviewContainer');
    const videoPreviewPlayer = document.getElementById('videoPreviewPlayer');
    const previewFileName = document.getElementById('previewFileName');
    const previewDuration = document.getElementById('previewDuration');
    
// =========================================================
    // 1. SELEÇÃO DO ARQUIVO (PREVIEW E TÍTULO)
    // =========================================================
    videoFileReal.addEventListener('change', () => {
        if (videoFileReal.files.length > 0) {
            const file = videoFileReal.files[0];
            const fileName = file.name;
            
            // Preenche o título automaticamente
            const titleInput = document.getElementById('title');
            if (titleInput) titleInput.value = fileName;

            // Carrega o Preview
            const fileURL = URL.createObjectURL(file);
            videoPreviewPlayer.src = fileURL;
            videoPreviewPlayer.load();

            videoPreviewPlayer.onloadedmetadata = function() {
                const totalSeconds = Math.floor(videoPreviewPlayer.duration) || 0; 
                
                const h = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
                const m = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
                const s = String(totalSeconds % 60).padStart(2, '0');
                const formattedDuration = `${h}:${m}:${s}`;
                
                durationInput.value = formattedDuration;
                previewDuration.textContent = formattedDuration;
                
                // Exibe a seção de preview
                videoPreviewContainer.style.display = 'flex';
                
                // Mostra o formulário de detalhes
                const detailsDiv = document.getElementById('videoUploadDetails');
                if (detailsDiv) detailsDiv.style.display = 'block';
            };

            // Atualiza a UI visual (Muda a cor da caixa de drop)
            dropArea.style.backgroundColor = '#E9F1FF'; 
            dropArea.style.borderColor = '#007ED7'; 
            dropArea.innerHTML = `<p style="font-size: 1.2em; color: #333;">Vídeo selecionado: <strong>${fileName}</strong></p>`;
            previewFileName.textContent = fileName;
            
            // NOTA: A barra de progresso NÃO inicia aqui mais. Ela iniciará no envio real.
            simulatedProgressContainer.style.display = 'none'; 

        } else {
            videoPreviewContainer.style.display = 'none';
            const detailsDiv = document.getElementById('videoUploadDetails');
            if (detailsDiv) detailsDiv.style.display = 'none';
            durationInput.value = '';
        }
    });

    // =========================================================
    // 2. ENVIO REAL COM PROGRESSO (AJAX)
    // =========================================================
    const uploadForm = document.getElementById('uploadForm');

    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault(); // Impede o recarregamento padrão da página

        // Validação básica
        if (videoFileReal.files.length === 0) {
            alert("Selecione um vídeo.");
            return;
        }

        // Prepara os dados para envio
        const formData = new FormData(uploadForm);
        formData.append('ajax_upload', '1'); // Avisa o PHP que é via AJAX

        // Configura a requisição AJAX
        const xhr = new XMLHttpRequest();
        
        // Mostra a barra de progresso
        simulatedProgressContainer.style.display = 'block';
        const uploadBtn = document.querySelector('.submit-upload-btn');
        if(uploadBtn) {
            uploadBtn.disabled = true;
            uploadBtn.textContent = "Enviando...";
        }

        // --- EVENTO DE PROGRESSO REAL ---
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                
                simulatedProgressBar.style.width = percentComplete + '%';
                progressText.textContent = percentComplete + '%';
            }
        });

        // --- EVENTO DE CONCLUSÃO ---
        xhr.onload = function() {
            if (xhr.status === 200) {
                // Verifica se o PHP retornou o sinal de sucesso que criamos
                if (xhr.responseText.includes("UPLOAD_OK")) {
                    progressText.textContent = 'Envio Concluído!';
                    simulatedProgressBar.style.width = '100%';
                    
                    // Redireciona para a aba "Meus Vídeos" após 1 segundo
                    setTimeout(() => {
                        window.location.href = 'dashboard.php?tab=my-videos';
                    }, 1000);
                } else {
                    // Se houve erro no PHP (ex: arquivo muito grande, erro de banco)
                    // Tenta pegar a mensagem de erro do HTML retornado ou mostra erro genérico
                    alert("Erro no processamento do servidor. O vídeo pode ser muito grande ou formato inválido.");
                    if(uploadBtn) {
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = "Tentar Novamente";
                    }
                }
            } else {
                alert("Erro no envio: " + xhr.statusText);
            }
        };

        xhr.onerror = function() {
            alert("Falha na conexão com a internet.");
            if(uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.textContent = "Tentar Novamente";
            }
        };

        // Envia os dados
        xhr.open('POST', 'dashboard.php?tab=upload-tab', true);
        xhr.send(formData);
    });

    // 2. Lógica de Drag and Drop (Sem alterações significativas)
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });
    
    dropArea.addEventListener('dragover', () => {
        dropArea.style.borderColor = '#007ED7'; 
        dropArea.style.boxShadow = '0 0 5px rgba(0, 126, 215, 0.5)';
    }, false);

    dropArea.addEventListener('dragleave', () => {
        dropArea.style.borderColor = '#CCC';
        dropArea.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';
    }, false);

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    dropArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        let dt = e.dataTransfer;
        let files = dt.files;
        
        if (files.length > 0 && files[0].type.startsWith('video/')) {
            videoFileReal.files = files;
            videoFileReal.dispatchEvent(new Event('change'));
        } else {
            alert("Por favor, solte um arquivo de vídeo válido.");
            dropArea.style.borderColor = '#CCC';
            dropArea.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';
        }
    }
    
    // 3. Validação Final
    function validateUpload() {
        if (videoFileReal.files.length === 0 || document.getElementById('thumbnail_file').files.length === 0) {
            alert("Por favor, certifique-se de que o VÍDEO e a MINIATURA foram selecionados.");
            return false;
        }
        return true;
    }
    
    window.validateUpload = validateUpload;


    // --------------------------------------------------------
    // LÓGICA DOS MODAIS (Edição e Exclusão)
    // --------------------------------------------------------
    const editModal = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');
    
    function openEditModal(id, title, description, visibility) {
        document.getElementById('edit-video-id-input').value = id;
        document.getElementById('edit-title').value = title;
        // CORREÇÃO: Substitui '\n' por quebras de linha reais no textarea
        document.getElementById('edit-description').value = description.replace(/\\n/g, '\n'); 
        document.getElementById('edit-visibility').value = visibility;
        editModal.style.display = 'block';
    }

    function closeEditModal() {
        editModal.style.display = 'none';
    }

    function confirmDelete(id) {
        document.getElementById('delete-video-id-input').value = id;
        deleteModal.style.display = 'block';
    }

    function closeDeleteModal() {
        deleteModal.style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target == editModal) {
            closeEditModal();
        }
        if (event.target == deleteModal) {
            closeDeleteModal();
        }
    }
    
    window.openEditModal = openEditModal;
    window.closeEditModal = closeEditModal;
    window.confirmDelete = confirmDelete;
    window.closeDeleteModal = closeDeleteModal;

    // ====================================================================
    const thumbnailFileInput = document.getElementById('thumbnail_file');
    const thumbnailPreviewArea = document.getElementById('thumbnailPreviewArea');
    const thumbnailPreviewImage = document.getElementById('thumbnailPreviewImage');

    // ====================================================================
    // LÓGICA DE PRÉ-VISUALIZAÇÃO DA THUMBNAIL (COM ANIMAÇÃO)
    // ====================================================================

    thumbnailFileInput.addEventListener('change', function() {
        const file = this.files[0];
        
        // 1. Remove a classe de animação e esconde (reset)
        thumbnailPreviewArea.classList.remove('show');
        thumbnailPreviewArea.style.display = 'none';

        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                thumbnailPreviewImage.src = e.target.result;
                
                // --- NOVA LÓGICA DE ANIMAÇÃO ---
                
                // 2. Torna o container visível (ainda na posição inicial, invisível)
                thumbnailPreviewArea.style.display = 'block';

                // 3. Usa requestAnimationFrame para garantir que a DOM foi atualizada
                // antes de adicionar a classe que dispara a animação.
                requestAnimationFrame(() => {
                    // Dispara a animação (slideInFade)
                    thumbnailPreviewArea.classList.add('show');
                });
                // -------------------------------
            }
            
            reader.readAsDataURL(file);
        } else {
            // Se nenhum arquivo for selecionado
            thumbnailPreviewImage.src = '';
        }
    });

// =========================================================
    // LÓGICA DE AÇÕES EM MASSA (CHECKBOXES E MENU)
    // =========================================================
    
    // 1. Selecionar/Deselecionar Todos
    function toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll('.video-checkbox');
        checkboxes.forEach(cb => cb.checked = source.checked);
    }

    // 2. Abrir/Fechar Menu de Ações
    function toggleBulkMenu() {
        const menu = document.getElementById('bulk-actions-menu');
        // Verifica se já está aberto
        if (menu.classList.contains('show')) {
            menu.classList.remove('show');
        } else {
            menu.classList.add('show');
        }
    }

    // Fecha o menu se clicar fora dele
    window.addEventListener('click', function(e) {
        const btn = document.getElementById('bulk-actions-btn');
        const menu = document.getElementById('bulk-actions-menu');
        if (!btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('show');
        }
    });

    // 3. Abrir Modal de Exclusão em Massa
    function openBulkDeleteModal() {
        // Fecha o menu dropdown
        document.getElementById('bulk-actions-menu').classList.remove('show');

        // Pega todos os checkboxes marcados
        const checkedBoxes = document.querySelectorAll('.video-checkbox:checked');
        
        if (checkedBoxes.length === 0) {
            alert("Por favor, selecione pelo menos um vídeo.");
            return;
        }

        // Conta quantos vídeos e atualiza o texto do modal
        document.getElementById('selected-count').innerText = checkedBoxes.length;

        // Coleta os IDs (apenas visualização/preparação, já que o backend não está pronto)
        let ids = [];
        checkedBoxes.forEach(cb => ids.push(cb.value));
        document.getElementById('bulk-delete-ids-input').value = ids.join(',');

        // Abre o modal
        document.getElementById('bulkDeleteModal').style.display = 'block';
    }

    // 4. Simulação de envio (já que o backend não está pronto)
    function submitBulkDelete() {
        alert("A funcionalidade de exclusão em massa no backend ainda não foi implementada.\nIDs a deletar: " + document.getElementById('bulk-delete-ids-input').value);
        document.getElementById('bulkDeleteModal').style.display = 'none';
    }

</script>
</html>