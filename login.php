<?php
// Inicializar sessão
session_start();
require_once 'functions.php';

// Processar logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['sessionId'])) {
        removeSession($_SESSION['sessionId']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

// Verificar se já está autenticado
if (isset($_SESSION['user']) && isset($_SESSION['sessionId'])) {
    // Verificar se a sessão ainda é válida
    if (!isSessionValid($_SESSION['sessionId'])) {
        // Sessão foi encerrada em outro dispositivo
        session_destroy();
        header('Location: login.php?session_expired=1');
        exit;
    }
    
    // Verificar se o acesso expirou
    if (!$_SESSION['user']['isAdmin'] && strtotime($_SESSION['user']['expiresAt']) < time()) {
        if (isset($_SESSION['sessionId'])) {
            removeSession($_SESSION['sessionId']);
        }
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }
    
    header('Location: index.php');
    exit;
}

// Processar login
$error = '';
$expired = isset($_GET['expired']) && $_GET['expired'] == 1;
$sessionExpired = isset($_GET['session_expired']) && $_GET['session_expired'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        $users = getUsers();
        $authenticated = false;
        
        foreach ($users as $user) {
            if ($user['username'] === $username && $user['password'] === $password && $user['isActive'] == 1) {
                $authenticated = true;
                
                // Verificar se o acesso expirou
                if (!$user['isAdmin'] && strtotime($user['expiresAt']) < time()) {
                    $error = 'Seu acesso expirou. Entre em contato com o administrador.';
                    break;
                }
                
                // Gerar ID de sessão único
                $sessionId = generateSessionId();
                
                // Remover todas as sessões existentes deste usuário antes de registrar a nova
                removeUserSessions($user['id']);
                
                // Registrar sessão
                registerSession($user['id'], $sessionId);
                
                // Salvar na sessão
                $_SESSION['user'] = $user;
                $_SESSION['sessionId'] = $sessionId;
                
                header('Location: index.php');
                exit;
            }
        }
        
        if (!$authenticated) {
            $error = 'Usuário ou senha incorretos.';
        }
    }
}

// Obter configurações para o WhatsApp
$config = getConfig();
$whatsappNumber = $config['whatsappNumber'] ?? '5511999999999'; // Número padrão se não estiver configurado
$whatsappMessage = urlencode($config['whatsappMessage'] ?? 'Olá! Gostaria de informações sobre o acesso ao Portal de Vídeos.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Portal de Vídeos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset e estilos gerais */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #1a1a2e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Container de login */
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        
        .login-box {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            padding: 30px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo {
            font-size: 48px;
            color: #4f46e5;
            margin-bottom: 20px;
        }
        
        .login-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #1a1a2e;
        }
        
        .login-header p {
            color: #6b7280;
            font-size: 14px;
        }
        
        /* Formulário */
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4b5563;
        }
        
        .form-group input {
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
        
        .login-button {
            background-color: #4f46e5;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .login-button:hover {
            background-color: #4338ca;
        }
        
        /* Mensagens */
        .error-message, .expired-message, .session-expired-message {
            color: #ef4444;
            font-size: 14px;
            margin-bottom: 20px;
            padding: 12px;
            background-color: #fee2e2;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .expired-message {
            color: #d97706;
            background-color: #fef3c7;
        }
        
        .session-expired-message {
            color: #1d4ed8;
            background-color: #dbeafe;
        }
        
        /* WhatsApp Button */
        .whatsapp-container {
            margin-top: 20px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .whatsapp-text {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 12px;
        }
        
        .whatsapp-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: #25D366;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            width: 100%;
        }
        
        .whatsapp-button:hover {
            background-color: #128C7E;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-play-circle"></i>
                </div>
                <h1>MeteFlix</h1>
                <p>Digite suas credenciais para acessar o conteúdo</p>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($expired): ?>
            <div class="expired-message">
                <i class="fas fa-clock"></i> Seu acesso expirou. Entre em contato com o administrador.
            </div>
            <?php endif; ?>
            
            <?php if ($sessionExpired): ?>
            <div class="session-expired-message">
                <i class="fas fa-user-slash"></i> Sua sessão foi encerrada porque você acessou em outro dispositivo.
            </div>
            <?php endif; ?>
            
            <form method="post" class="login-form">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Usuário Meteflix
                    </label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Senha Meteflix
                    </label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>
            
            <!-- WhatsApp Contact Option -->
            <div class="whatsapp-container">
                <p class="whatsapp-text">Quer comprar ou renovar seu acesso?</p>
                <a href="https://wa.me/<?php echo $whatsappNumber; ?>?text=<?php echo $whatsappMessage; ?>" 
                   class="whatsapp-button" target="_blank">
                    <i class="fab fa-whatsapp"></i> Fale conosco pelo WhatsApp
                </a>
            </div>
        </div>
    </div>
</body>
</html>
