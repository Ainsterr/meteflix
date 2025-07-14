<?php
// Inicializar sessão e configurações
session_start();
require_once 'functions.php';

// Verificar autenticação
if (!isset($_SESSION['user']) || !isset($_SESSION['sessionId'])) {
    header('Location: login.php');
    exit;
}

// Verificar se a sessão ainda é válida
if (!isSessionValid($_SESSION['sessionId'])) {
    // Sessão foi encerrada em outro dispositivo
    session_destroy();
    header('Location: login.php?session_expired=1');
    exit;
}

// Verificar se o acesso expirou
$user = $_SESSION['user'];
if (!$user['isAdmin'] && strtotime($user['expiresAt']) < time()) {
    if (isset($_SESSION['sessionId'])) {
        removeSession($_SESSION['sessionId']);
    }
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}

// Obter URL do site de vídeos
$config = getConfig();
$videoSiteUrl = $config['videoSiteUrl'] ?? 'https://seu-site-de-videos.com';
$sessionCheckInterval = $config['sessionCheckInterval'] ?? 5; // Intervalo em segundos para verificar a sessão

// Calcular tempo restante
$timeLeft = '';
if (!$user['isAdmin'] && isset($user['expiresAt'])) {
    $expiryTime = strtotime($user['expiresAt']);
    $now = time();
    $diff = $expiryTime - $now;
    
    if ($diff > 0) {
        $days = floor($diff / (60 * 60 * 24));
        $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
        $minutes = floor(($diff % (60 * 60)) / 60);
        
        if ($days > 0) {
            $timeLeft = "$days dias";
        } elseif ($hours > 0) {
            $timeLeft = "$hours horas";
        } else {
            $timeLeft = "$minutes minutos";
        }
    }
}

// Obter informações da sessão atual
$userSessions = getUserSessions($user['id']);
$currentSession = null;
foreach ($userSessions as $session) {
    if ($session['sessionId'] === $_SESSION['sessionId']) {
        $currentSession = $session;
        break;
    }
}

// Obter informações do dispositivo atual
$deviceInfo = '';
if ($currentSession) {
    $deviceInfo = getDeviceInfo($currentSession['userAgent']);
}

// Atualizar o timestamp da sessão para indicar atividade
updateSessionTimestamp($_SESSION['sessionId']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Vídeos</title>
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
            background-color: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }
        
        /* Layout principal */
        .app-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        
        /* Barra superior */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #1a1a2e;
            color: white;
            padding: 0 20px;
            height: 60px;
            z-index: 10;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .logo i {
            font-size: 20px;
            color: #4f46e5;
        }
        
        .user-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .expiration-info {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            background-color: rgba(255, 255, 255, 0.1);
            padding: 4px 10px;
            border-radius: 4px;
        }
        
        .device-info {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            background-color: rgba(255, 255, 255, 0.1);
            padding: 4px 10px;
            border-radius: 4px;
        }
        
        .top-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background-color: #4f46e5;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .top-button:hover {
            background-color: #4338ca;
        }
        
        /* Área de conteúdo */
        .content-container {
            flex: 1;
            position: relative;
            height: calc(100vh - 60px);
            overflow: hidden;
            background-color: #f5f5f5;
        }
        
        .video-frame {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #1a1a2e;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 100;
            color: white;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.2);
            border-top: 5px solid #4f46e5;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .user-controls {
                flex-wrap: wrap;
                justify-content: flex-end;
            }
            
            .logo span {
                display: none;
            }
            
            .expiration-info span, .device-info span {
                display: none;
            }
        }
        
        @media (max-width: 600px) {
            .user-controls {
                gap: 5px;
            }
            
            .top-button {
                padding: 4px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Barra superior -->
        <div class="top-bar">
            <div class="logo">
                <i class="fas fa-play-circle"></i>
                <span>MeteFlix</span>
            </div>
            <div class="user-controls">
                <?php if (!empty($deviceInfo)): ?>
                <div class="device-info">
                    <i class="fas fa-laptop"></i>
                    <span>Dispositivo:</span> <?php echo $deviceInfo; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!$user['isAdmin'] && !empty($timeLeft)): ?>
                <div class="expiration-info">
                    <i class="fas fa-clock"></i>
                    <span>Acesso restante:</span> <?php echo $timeLeft; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($user['isAdmin']): ?>
                <a href="admin.php" class="top-button">
                    <i class="fas fa-cog"></i> Painel Admin
                </a>
                <?php endif; ?>
                
                <a href="login.php?logout=1" class="top-button">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>
        
        <!-- Container do iframe -->
        <div class="content-container">
            <div class="loading-overlay" id="loading">
                <div class="spinner"></div>
                <p>Carregando seu conteúdo...</p>
            </div>
            <iframe src="<?php echo htmlspecialchars($videoSiteUrl); ?>" class="video-frame" id="videoFrame" frameborder="0" allowfullscreen></iframe>
        </div>
    </div>

    <script>
        // Esconder overlay quando o iframe carregar
        document.getElementById('videoFrame').onload = function() {
            document.getElementById('loading').style.display = 'none';
        };
        
        // Fallback se o onload não disparar
        setTimeout(function() {
            document.getElementById('loading').style.display = 'none';
        }, 5000);
        
        // Ajustar tamanho do iframe para ocupar todo o espaço disponível
        function resizeIframe() {
            const topBar = document.querySelector('.top-bar');
            const contentContainer = document.querySelector('.content-container');
            const iframe = document.getElementById('videoFrame');
            
            if (topBar && contentContainer && iframe) {
                const topBarHeight = topBar.offsetHeight;
                const windowHeight = window.innerHeight;
                
                contentContainer.style.height = (windowHeight - topBarHeight) + 'px';
                iframe.style.width = '100%';
                iframe.style.height = '90%';
            }
        }
        
        // Executar no carregamento e quando a janela for redimensionada
        window.addEventListener('load', resizeIframe);
        window.addEventListener('resize', resizeIframe);
        
        // Verificar periodicamente se a sessão ainda é válida
        let sessionCheckTimer = null;
        
        function startSessionCheck() {
            // Limpar timer existente se houver
            if (sessionCheckTimer) {
                clearInterval(sessionCheckTimer);
            }
            
            // Verificar a cada X segundos (definido nas configurações)
            sessionCheckTimer = setInterval(checkSession, <?php echo $sessionCheckInterval * 1000; ?>);
        }
        
        function checkSession() {
            fetch('check_session.php?t=' + new Date().getTime())
                .then(response => response.json())
                .then(data => {
                    if (!data.valid) {
                        // Redirecionar com base no motivo da invalidação
                        if (data.reason === 'expired') {
                            window.location.href = 'login.php?expired=1';
                        } else {
                            window.location.href = 'login.php?session_expired=1';
                        }
                    }
                })
                .catch(error => console.error('Erro ao verificar sessão:', error));
        }
        
        // Iniciar verificação de sessão
        startSessionCheck();
        
        // Verificar também quando a página voltar a ficar visível
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Verificar imediatamente quando a página ficar visível
                checkSession();
                // Reiniciar o timer
                startSessionCheck();
            }
        });
    </script>
</body>
</html>
