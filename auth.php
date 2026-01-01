// auth.php - Incluir no topo de todas as páginas restritas
session_start();
require 'db_connect.php'; // Sua conexão com o banco

// Se já está logado pela sessão, não faz mais nada
if (isset($_SESSION['user_id'])) {
    return;
}

// Tenta verificar o cookie "Manter Logado"
if (isset($_COOKIE['remember_token'])) {
    $cookie_value = $_COOKIE['remember_token'];
    
    // Divide o valor do cookie: user_id:selector:token
    list($user_id, $selector, $token_hex) = explode(':', $cookie_value);
    
    // Validação básica
    if (!empty($user_id) && !empty($selector) && !empty($token_hex)) {
        
        // 1. Busca o token no BD usando o selector
        $stmt = $pdo->prepare("SELECT user_id, hashed_token, expires_at, username FROM remembered_logins rl JOIN users u ON rl.user_id = u.id WHERE selector = ? AND user_id = ?");
        $stmt->execute([$selector, $user_id]);
        $login_data = $stmt->fetch();

        if ($login_data) {
            
            // 2. Verifica a expiração
            if (strtotime($login_data['expires_at']) > time()) {
                
                // 3. Verifica se o token REAL (do cookie) corresponde ao hash (do BD)
                $token_binary = hex2bin($token_hex);
                $cookie_hashed_token = hash('sha256', $token_binary);

                if ($cookie_hashed_token === $login_data['hashed_token']) {
                    
                    // Sucesso! Cria as variáveis de SESSÃO e loga o usuário
                    $_SESSION['user_id'] = $login_data['user_id'];
                    $_SESSION['username'] = $login_data['username'];
                    
                    // IMPORTANTE: REGENERE O TOKEN (para evitar ataques 'Theft of Token')
                    // Neste ponto, você deveria:
                    // 1. Excluir o token antigo do BD.
                    // 2. Gerar um NOVO par (selector/token) e um NOVO cookie.
                    // 3. Inserir o novo par no BD (o mesmo processo do login.php).
                    // Para simplificar, vou pular a regeneração, mas é altamente recomendada.

                    return; // Logado com sucesso
                }
            }
        }
        
        // Se a verificação falhar (token inválido, expirado, ou não encontrado), exclua o cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

// Se não conseguiu logar por sessão NEM por cookie, redireciona para o login
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header('Location: login.php');
    exit;
}