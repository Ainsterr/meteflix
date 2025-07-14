<?php
// Inicializar sessão e configurações
session_start();
require_once 'functions.php';

// Verificar autenticação e permissão de admin
if (!isset($_SESSION['user']) || $_SESSION['user']['isAdmin'] != 1) {
    header('Location: login.php');
    exit;
}

// Processar ações
$message = '';
$error = '';

// Adicionar usuário
if (isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $duration = (int)($_POST['duration'] ?? 0);
    $durationType = $_POST['duration_type'] ?? 'days';
    $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
    $deviceLimit = (int)($_POST['device_limit'] ?? 1);
    
    if (empty($username) || empty($password) || $duration <= 0 || $deviceLimit <= 0) {
        $error = 'Todos os campos são obrigatórios.';
    } else {
        // Calcular data de expiração
        $expiresAt = calculateExpiryDate($duration, $durationType);
        
        // Adicionar usuário
        $result = addUser($username, $password, $expiresAt, $isAdmin, $deviceLimit);
        
        if ($result === true) {
            $message = 'Usuário adicionado com sucesso!';
        } else {
            $error = $result;
        }
    }
}

// Editar usuário
if (isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $id = $_POST['id'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
    $deviceLimit = (int)($_POST['device_limit'] ?? 1);
    
    if (empty($id) || empty($username) || $deviceLimit <= 0) {
        $error = 'ID, nome de usuário e limite de dispositivos são obrigatórios.';
    } else {
        $data = [
            'username' => $username,
            'isActive' => $isActive,
            'isAdmin' => $isAdmin,
            'deviceLimit' => $deviceLimit
        ];
        
        // Atualizar senha apenas se fornecida
        if (!empty($password)) {
            $data['password'] = $password;
        }
        
        $result = updateUser($id, $data);
        
        if ($result === true) {
            $message = 'Usuário atualizado com sucesso!';
        } else {
            $error = $result;
        }
    }
}

// Renovar acesso
if (isset($_POST['action']) && $_POST['action'] == 'renew_access') {
    $id = $_POST['id'] ?? '';
    $duration = (int)($_POST['duration'] ?? 0);
    $durationType = $_POST['duration_type'] ?? 'days';
    
    if (empty($id) || $duration <= 0) {
        $error = 'ID e duração são obrigatórios.';
    } else {
        // Calcular nova data de expiração
        $expiresAt = calculateExpiryDate($duration, $durationType);
        
        $result = updateUser($id, ['expiresAt' => $expiresAt]);
        
        if ($result === true) {
            $message = 'Acesso renovado com sucesso!';
        } else {
            $error = $result;
        }
    }
}

// Excluir usuário
if (isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        $error = 'ID é obrigatório.';
    } else {
        $result = deleteUser($id);
        
        if ($result === true) {
            $message = 'Usuário excluído com sucesso!';
        } else {
            $error = $result;
        }
    }
}

// Encerrar sessões de um usuário
if (isset($_POST['action']) && $_POST['action'] == 'terminate_sessions') {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        $error = 'ID é obrigatório.';
    } else {
        $result = removeUserSessions($id);
        
        if ($result === true) {
            $message = 'Todas as sessões do usuário foram encerradas com sucesso!';
        } else {
            $error = $result;
        }
    }
}

// Salvar configurações
if (isset($_POST['action']) && $_POST['action'] == 'save_config') {
    $videoSiteUrl = $_POST['video_site_url'] ?? '';
    $whatsappNumber = $_POST['whatsapp_number'] ?? '';
    $whatsappMessage = $_POST['whatsapp_message'] ?? '';
    $defaultDeviceLimit = (int)($_POST['default_device_limit'] ?? 1);
    
    if (empty($videoSiteUrl) || $defaultDeviceLimit <= 0) {
        $error = 'URL do site de vídeos e limite padrão de dispositivos são obrigatórios.';
    } else {
        $config = getConfig();
        $config['videoSiteUrl'] = $videoSiteUrl;
        $config['whatsappNumber'] = $whatsappNumber;
        $config['whatsappMessage'] = $whatsappMessage;
        $config['defaultDeviceLimit'] = $defaultDeviceLimit;
        saveConfig($config);
        $message = 'Configurações salvas com sucesso!';
    }
}

// Obter dados
$users = getUsers();
$config = getConfig();
$sessions = getSessions();

// Agrupar sessões por usuário
$userSessions = [];
foreach ($sessions as $session) {
    $userId = $session['userId'];
    if (!isset($userSessions[$userId])) {
        $userSessions[$userId] = [];
    }
    $userSessions[$userId][] = $session;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Portal de Vídeos</title>
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
            min-height: 100vh;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        /* Layout principal */
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Barra lateral */
        .sidebar {
            width: 250px;
            background-color: #1a1a2e;
            color: white;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
            border-bottom: 1px solid #374151;
        }
        
        .sidebar-header i {
            color: #4f46e5;
        }
        
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }
        
        .sidebar-nav a {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #d1d5db;
            transition: all 0.2s;
        }
        
        .sidebar-nav a:hover {
            background-color: #374151;
            color: white;
        }
        
        .sidebar-nav a.active {
            background-color: #4f46e5;
            color: white;
        }
        
        .sidebar-nav a.back-link {
            margin-top: auto;
            border-top: 1px solid #374151;
        }
        
        /* Menu toggle para mobile */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 200;
            background-color: #4f46e5;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
        }
        
        /* Conteúdo principal */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        
        .admin-header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-header h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1a1a2e;
        }
        
        .admin-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Mensagens */
        .message, .error {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .error {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        /* Cards */
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Formulários */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }
        
        /* Botões */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: #4f46e5;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            background-color: #4338ca;
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 6px 10px;
            font-size: 12px;
        }
        
        .btn-danger {
            background-color: #ef4444;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .btn-success {
            background-color: #10b981;
        }
        
        .btn-success:hover {
            background-color: #059669;
        }
        
        .btn-warning {
            background-color: #f59e0b;
        }
        
        .btn-warning:hover {
            background-color: #d97706;
        }
        
        /* Tabelas */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }
        
        th {
            background-color: #f9fafb;
            font-weight: 500;
            font-size: 14px;
            color: #4b5563;
        }
        
        td {
            font-size: 14px;
            vertical-align: middle;
        }
        
        .table-actions {
            display: flex;
            gap: 5px;
        }
        
        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-admin {
            background-color: #818cf8;
            color: white;
        }
        
        .badge-user {
            background-color: #e5e7eb;
            color: #4b5563;
        }
        
        .badge-active {
            background-color: #10b981;
            color: white;
        }
        
        .badge-inactive {
            background-color: #ef4444;
            color: white;
        }
        
        .badge-device {
            background-color: #3b82f6;
            color: white;
        }
        
        /* Modais */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal {
            background-color: white;
            margin: 50px auto;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        
        .modal-close:hover {
            color: #111827;
        }
        
        /* Sessões ativas */
        .sessions-list {
            margin-top: 10px;
        }
        
        .session-item {
            background-color: #f9fafb;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .session-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .session-device {
            font-weight: 500;
        }
        
        .session-meta {
            font-size: 12px;
            color: #6b7280;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Menu toggle para mobile -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="admin-container">
        <!-- Barra lateral -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-play-circle"></i>
                <span>Painel Meteflix</span>
            </div>
            <nav class="sidebar-nav">
                <a href="#users" class="active" onclick="showTab('users')">
                    <i class="fas fa-users"></i>
                    <span>Cadastro de Usuários</span>
                </a>
                <a href="#sessions" onclick="showTab('sessions')">
                    <i class="fas fa-laptop"></i>
                    <span>Sessões Ativas</span>
                </a>
                <a href="#settings" onclick="showTab('settings')">
                    <i class="fas fa-cog"></i>
                    <span>Configurações</span>
                </a>
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Voltar ao Meteflix</span>
                </a>
            </nav>
        </div>
        
        <!-- Conteúdo principal -->
        <div class="main-content">
            <!-- Cabeçalho -->
            <div class="admin-header">
                <h1><i class="fas fa-tachometer-alt"></i> Painel Meteflix</h1>
                <div class="admin-user">
                    <span><?php echo htmlspecialchars($_SESSION['user']['username']); ?></span>
                    <a href="login.php?logout=1" class="btn btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>
            
            <!-- Mensagens -->
            <?php if (!empty($message)): ?>
            <div class="message">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <!-- Aba de Usuários -->
            <div id="users-tab" class="tab-content">
                <!-- Card de adicionar usuário -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-plus"></i> Adicionar Novo Usuário</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="add_user">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="username">
                                        <i class="fas fa-user"></i> Usuário
                                    </label>
                                    <input type="text" id="username" name="username" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">
                                        <i class="fas fa-lock"></i> Senha
                                    </label>
                                    <input type="password" id="password" name="password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="duration">
                                        <i class="fas fa-clock"></i> Duração
                                    </label>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <input type="number" id="duration" name="duration" min="1" value="30" required>
                                        </div>
                                        <div class="form-group">
                                            <select name="duration_type">
                                                <option value="minutes">Minutos</option>
                                                <option value="hours">Horas</option>
                                                <option value="days" selected>Dias</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="device_limit">
                                        <i class="fas fa-laptop"></i> Limite de Dispositivos
                                    </label>
                                    <input type="number" id="device_limit" name="device_limit" min="1" value="<?php echo $config['defaultDeviceLimit'] ?? 1; ?>" required>
                                    <small>Número máximo de dispositivos em que o usuário pode estar logado simultaneamente.</small>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_admin" name="is_admin">
                                        <label for="is_admin">
                                            <i class="fas fa-user-shield"></i> Usuário Admin
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn">
                                <i class="fas fa-plus-circle"></i> Adicionar Usuário
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Card de lista de usuários -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-users"></i> Usuários Cadastrados</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Usuário</th>
                                        <th>Status</th>
                                        <th>Tipo</th>
                                        <th>Limite de Dispositivos</th>
                                        <th>Sessões Ativas</th>
                                        <th>Expira em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td>
                                            <?php if ($user['isActive'] == 1): ?>
                                            <span class="badge badge-active">
                                                <i class="fas fa-check-circle"></i> Ativo
                                            </span>
                                            <?php else: ?>
                                            <span class="badge badge-inactive">
                                                <i class="fas fa-times-circle"></i> Inativo
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['isAdmin'] == 1): ?>
                                            <span class="badge badge-admin">
                                                <i class="fas fa-user-shield"></i> Admin
                                            </span>
                                            <?php else: ?>
                                            <span class="badge badge-user">
                                                <i class="fas fa-user"></i> Usuário
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-device">
                                                <i class="fas fa-laptop"></i> <?php echo $user['deviceLimit'] ?? 1; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $activeSessionsCount = isset($userSessions[$user['id']]) ? count($userSessions[$user['id']]) : 0;
                                            echo $activeSessionsCount;
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($user['isAdmin'] == 1) {
                                                echo 'N/A';
                                            } else {
                                                $expiryDate = new DateTime($user['expiresAt']);
                                                $now = new DateTime();
                                                
                                                if ($expiryDate < $now) {
                                                    echo '<span class="badge badge-inactive">Expirado</span>';
                                                } else {
                                                    echo $expiryDate->format('d/m/Y H:i');
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <button class="btn btn-sm" onclick="openEditModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['username']); ?>', <?php echo $user['isActive']; ?>, <?php echo $user['isAdmin']; ?>, <?php echo $user['deviceLimit'] ?? 1; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <?php if ($user['isAdmin'] != 1): ?>
                                                <button class="btn btn-sm btn-success" onclick="openRenewModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($activeSessionsCount > 0): ?>
                                                <button class="btn btn-sm btn-warning" onclick="confirmTerminateSessions('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['id'] != $_SESSION['user']['id']): ?>
                                                <button class="btn btn-sm btn-danger" onclick="confirmDelete('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Aba de Sessões Ativas -->
            <div id="sessions-tab" class="tab-content" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-laptop"></i> Sessões Ativas</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sessions)): ?>
                            <p>Não há sessões ativas no momento.</p>
                        <?php else: ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Dispositivo</th>
                                            <th>Endereço IP</th>
                                            <th>Data/Hora</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): 
                                            $sessionUser = getUserById($session['userId']);
                                            if (!$sessionUser) continue;
                                            $deviceInfo = getDeviceInfo($session['userAgent']);
                                            $timestamp = date('d/m/Y H:i:s', $session['timestamp']);
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sessionUser['username']); ?></td>
                                            <td><?php echo htmlspecialchars($deviceInfo); ?></td>
                                            <td><?php echo htmlspecialchars($session['ip']); ?></td>
                                            <td><?php echo $timestamp; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-danger" onclick="confirmTerminateSession('<?php echo $session['sessionId']; ?>', '<?php echo htmlspecialchars($sessionUser['username']); ?>')">
                                                    <i class="fas fa-times-circle"></i> Encerrar
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Aba de Configurações -->
            <div id="settings-tab" class="tab-content" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-cog"></i> Configurações do Sistema</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="save_config">
                            
                            <div class="form-group">
                                <label for="video_site_url">
                                    <i class="fas fa-link"></i> URL do Site
                                </label>
                                <input type="url" id="video_site_url" name="video_site_url" value="<?php echo htmlspecialchars($config['videoSiteUrl'] ?? ''); ?>" required>
                                <small>URL do site que será exibido no iframe do portal.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="default_device_limit">
                                    <i class="fas fa-laptop"></i> Limite Padrão de Dispositivos
                                </label>
                                <input type="number" id="default_device_limit" name="default_device_limit" min="1" value="<?php echo $config['defaultDeviceLimit'] ?? 1; ?>" required>
                                <small>Número máximo de dispositivos em que um usuário pode estar logado simultaneamente (padrão para novos usuários).</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="whatsapp_number">
                                    <i class="fab fa-whatsapp"></i> Número do WhatsApp
                                </label>
                                <input type="text" id="whatsapp_number" name="whatsapp_number" value="<?php echo htmlspecialchars($config['whatsappNumber'] ?? ''); ?>" placeholder="5511999999999">
                                <small>Número completo com código do país (ex: 5511999999999).</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="whatsapp_message">
                                    <i class="fas fa-comment"></i> Mensagem Padrão do WhatsApp
                                </label>
                                <textarea id="whatsapp_message" name="whatsapp_message"><?php echo htmlspecialchars($config['whatsappMessage'] ?? 'Olá! Gostaria de informações sobre o acesso ao Portal de Vídeos.'); ?></textarea>
                                <small>Mensagem que será pré-preenchida quando o usuário clicar no botão do WhatsApp.</small>
                            </div>
                            
                            <button type="submit" class="btn">
                                <i class="fas fa-save"></i> Salvar Configurações
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Editar Usuário -->
    <div id="edit-modal" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Editar Usuário</h2>
                <span class="modal-close" onclick="closeModal('edit-modal')">&times;</span>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label for="edit_username">
                        <i class="fas fa-user"></i> Usuário
                    </label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_password">
                        <i class="fas fa-lock"></i> Nova Senha (deixe em branco para manter a atual)
                    </label>
                    <input type="password" id="edit_password" name="password">
                </div>
                
                <div class="form-group">
                    <label for="edit_device_limit">
                        <i class="fas fa-laptop"></i> Limite de Dispositivos
                    </label>
                    <input type="number" id="edit_device_limit" name="device_limit" min="1" required>
                    <small>Número máximo de dispositivos em que o usuário pode estar logado simultaneamente.</small>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="edit_is_active" name="is_active">
                        <label for="edit_is_active">
                            <i class="fas fa-check-circle"></i> Usuário Ativo
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="edit_is_admin" name="is_admin">
                        <label for="edit_is_admin">
                            <i class="fas fa-user-shield"></i> Usuário Admin
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </form>
        </div>
    </div>
    
    <!-- Modal de Renovar Acesso -->
    <div id="renew-modal" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-sync-alt"></i> Renovar Acesso</h2>
                <span class="modal-close" onclick="closeModal('renew-modal')">&times;</span>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="renew_access">
                <input type="hidden" name="id" id="renew_id">
                
                <div class="form-group">
                    <label id="renew_username_label">Renovar acesso para o usuário:</label>
                </div>
                
                <div class="form-group">
                    <label for="renew_duration">
                        <i class="fas fa-clock"></i> Nova Duração
                    </label>
                    <div class="form-row">
                        <div class="form-group">
                            <input type="number" id="renew_duration" name="duration" min="1" value="30" required>
                        </div>
                        <div class="form-group">
                            <select name="duration_type">
                                <option value="minutes">Minutos</option>
                                <option value="hours">Horas</option>
                                <option value="days" selected>Dias</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-sync-alt"></i> Renovar Acesso
                </button>
            </form>
        </div>
    </div>
    
    <!-- Formulário de exclusão -->
    <form id="delete-form" method="post" action="" style="display: none;">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="id" id="delete_id">
    </form>
    
    <!-- Formulário de encerrar sessões -->
    <form id="terminate-sessions-form" method="post" action="" style="display: none;">
        <input type="hidden" name="action" value="terminate_sessions">
        <input type="hidden" name="id" id="terminate_sessions_id">
    </form>
    
    <!-- Formulário de encerrar sessão específica -->
    <form id="terminate-session-form" method="post" action="" style="display: none;">
        <input type="hidden" name="action" value="terminate_session">
        <input type="hidden" name="session_id" id="terminate_session_id">
    </form>

    <script>
        // Mostrar/esconder abas
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            document.querySelectorAll('.sidebar-nav a').forEach(link => {
                link.classList.remove('active');
            });
            
            document.getElementById(tabId + '-tab').style.display = 'block';
            document.querySelector(`.sidebar-nav a[href="#${tabId}"]`).classList.add('active');
        }
        
        // Abrir modal de edição
        function openEditModal(id, username, isActive, isAdmin, deviceLimit) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_is_active').checked = isActive == 1;
            document.getElementById('edit_is_admin').checked = isAdmin == 1;
            document.getElementById('edit_device_limit').value = deviceLimit;
            
            document.getElementById('edit-modal').style.display = 'block';
        }
        
        // Abrir modal de renovação
        function openRenewModal(id, username) {
            document.getElementById('renew_id').value = id;
            document.getElementById('renew_username_label').innerHTML = 
                '<i class="fas fa-user"></i> Renovar acesso para: <strong>' + username + '</strong>';
            
            document.getElementById('renew-modal').style.display = 'block';
        }
        
        // Fechar modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Confirmar exclusão
        function confirmDelete(id, username) {
            if (confirm('Tem certeza que deseja excluir o usuário "' + username + '"?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('delete-form').submit();
            }
        }
        
        // Confirmar encerramento de todas as sessões
        function confirmTerminateSessions(id, username) {
            if (confirm('Tem certeza que deseja encerrar todas as sessões do usuário "' + username + '"?')) {
                document.getElementById('terminate_sessions_id').value = id;
                document.getElementById('terminate-sessions-form').submit();
            }
        }
        
        // Confirmar encerramento de sessão específica
        function confirmTerminateSession(sessionId, username) {
            if (confirm('Tem certeza que deseja encerrar esta sessão do usuário "' + username + '"?')) {
                document.getElementById('terminate_session_id').value = sessionId;
                document.getElementById('terminate-session-form').submit();
            }
        }
        
        // Toggle menu para mobile
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Fechar modais ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-backdrop')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
