<?php
// Arquivo de funções

// Diretório e arquivos de dados
$dataDir = 'data';
$usersFile = $dataDir . '/users.json';
$configFile = $dataDir . '/config.json';
$sessionsFile = $dataDir . '/sessions.json';

// Criar diretório de dados se não existir
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Inicializar arquivo de usuários se não existir
if (!file_exists($usersFile)) {
    $initialUsers = [
        [
            'id' => '1',
            'username' => 'admin',
            'password' => 'admin123',
            'isAdmin' => 1,
            'isActive' => 1,
            'expiresAt' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'deviceLimit' => 1
        ]
    ];
    file_put_contents($usersFile, json_encode($initialUsers, JSON_PRETTY_PRINT));
}

// Inicializar arquivo de configuração se não existir
if (!file_exists($configFile)) {
    $initialConfig = [
        'videoSiteUrl' => 'https://seu-site-de-videos.com',
        'whatsappNumber' => '5511999999999',
        'whatsappMessage' => 'Olá! Gostaria de informações sobre o acesso ao Portal de Vídeos.',
        'defaultDeviceLimit' => 1,
        'sessionCheckInterval' => 2 // Intervalo em segundos para verificar a sessão
    ];
    file_put_contents($configFile, json_encode($initialConfig, JSON_PRETTY_PRINT));
}

// Inicializar arquivo de sessões se não existir
if (!file_exists($sessionsFile)) {
    $initialSessions = [];
    file_put_contents($sessionsFile, json_encode($initialSessions, JSON_PRETTY_PRINT));
}

// Função para obter todos os usuários
function getUsers() {
    global $usersFile;
    $json = file_get_contents($usersFile);
    return json_decode($json, true) ?: [];
}

// Função para salvar todos os usuários
function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}

// Função para obter configurações
function getConfig() {
    global $configFile;
    $json = file_get_contents($configFile);
    return json_decode($json, true) ?: [];
}

// Função para salvar configurações
function saveConfig($config) {
    global $configFile;
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
}

// Função para obter todas as sessões
function getSessions() {
    global $sessionsFile;
    $json = file_get_contents($sessionsFile);
    return json_decode($json, true) ?: [];
}

// Função para salvar todas as sessões
function saveSessions($sessions) {
    global $sessionsFile;
    file_put_contents($sessionsFile, json_encode($sessions, JSON_PRETTY_PRINT));
}

// Função para adicionar um usuário
function addUser($username, $password, $expiresAt, $isAdmin = 0, $deviceLimit = null) {
    $users = getUsers();
    $config = getConfig();
    
    // Verificar se o usuário já existe
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return 'Usuário já existe.';
        }
    }
    
    // Usar limite de dispositivos padrão se não for especificado
    if ($deviceLimit === null) {
        $deviceLimit = $config['defaultDeviceLimit'] ?? 1;
    }
    
    // Gerar ID único
    $id = uniqid();
    
    // Adicionar usuário
    $users[] = [
        'id' => $id,
        'username' => $username,
        'password' => $password,
        'isAdmin' => $isAdmin,
        'isActive' => 1,
        'expiresAt' => $expiresAt,
        'deviceLimit' => $deviceLimit
    ];
    
    saveUsers($users);
    return true;
}

// Função para atualizar um usuário
function updateUser($id, $data) {
    $users = getUsers();
    $updated = false;
    
    foreach ($users as $key => $user) {
        if ($user['id'] === $id) {
            // Atualizar campos
            foreach ($data as $field => $value) {
                $users[$key][$field] = $value;
            }
            $updated = true;
            break;
        }
    }
    
    if (!$updated) {
        return 'Usuário não encontrado.';
    }
    
    saveUsers($users);
    return true;
}

// Função para excluir um usuário
function deleteUser($id) {
    $users = getUsers();
    $found = false;
    
    foreach ($users as $key => $user) {
        if ($user['id'] === $id) {
            unset($users[$key]);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        return 'Usuário não encontrado.';
    }
    
    // Reindexar array
    $users = array_values($users);
    
    saveUsers($users);
    
    // Remover todas as sessões deste usuário
    removeUserSessions($id);
    
    return true;
}

// Função para calcular data de expiração
function calculateExpiryDate($duration, $durationType) {
    $now = new DateTime();
    
    switch ($durationType) {
        case 'minutes':
            $now->add(new DateInterval('PT' . $duration . 'M'));
            break;
        case 'hours':
            $now->add(new DateInterval('PT' . $duration . 'H'));
            break;
        case 'days':
        default:
            $now->add(new DateInterval('P' . $duration . 'D'));
            break;
    }
    
    return $now->format('Y-m-d H:i:s');
}

// Função para registrar uma nova sessão
function registerSession($userId, $sessionId) {
    $sessions = getSessions();
    $user = getUserById($userId);
    
    if (!$user) {
        return false;
    }
    
    // Obter informações do dispositivo
    $deviceInfo = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'timestamp' => time(),
        'lastActivity' => time()
    ];
    
    // Verificar sessões existentes para este usuário
    $userSessions = [];
    foreach ($sessions as $session) {
        if ($session['userId'] === $userId) {
            $userSessions[] = $session;
        }
    }
    
    // Verificar se o usuário já atingiu o limite de dispositivos
    $deviceLimit = $user['deviceLimit'] ?? 1;
    
    if (count($userSessions) >= $deviceLimit) {
        // Ordenar sessões por timestamp (mais antiga primeiro)
        usort($userSessions, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });
        
        // Remover todas as sessões antigas se o limite for 1
        if ($deviceLimit == 1) {
            foreach ($userSessions as $oldSession) {
                removeSession($oldSession['sessionId']);
            }
        } else {
            // Remover apenas as sessões mais antigas que excedem o limite
            $sessionsToRemove = count($userSessions) - $deviceLimit + 1;
            for ($i = 0; $i < $sessionsToRemove; $i++) {
                removeSession($userSessions[$i]['sessionId']);
            }
        }
    }
    
    // Adicionar nova sessão
    $sessions[] = [
        'sessionId' => $sessionId,
        'userId' => $userId,
        'ip' => $deviceInfo['ip'],
        'userAgent' => $deviceInfo['userAgent'],
        'timestamp' => $deviceInfo['timestamp'],
        'lastActivity' => $deviceInfo['lastActivity']
    ];
    
    saveSessions($sessions);
    return true;
}

// Função para atualizar o timestamp de última atividade de uma sessão
function updateSessionTimestamp($sessionId) {
    $sessions = getSessions();
    $updated = false;
    
    foreach ($sessions as $key => $session) {
        if ($session['sessionId'] === $sessionId) {
            $sessions[$key]['lastActivity'] = time();
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        saveSessions($sessions);
    }
    
    return $updated;
}

// Função para remover sessões inativas
function removeInactiveSessions($maxInactiveTime = 3600) { // 1 hora por padrão
    $sessions = getSessions();
    $now = time();
    $newSessions = [];
    
    foreach ($sessions as $session) {
        // Manter apenas sessões ativas
        if (($now - $session['lastActivity']) < $maxInactiveTime) {
            $newSessions[] = $session;
        }
    }
    
    saveSessions($newSessions);
    return count($sessions) - count($newSessions); // Retorna o número de sessões removidas
}

// Função para remover uma sessão específica
function removeSession($sessionId) {
    $sessions = getSessions();
    $newSessions = [];
    
    foreach ($sessions as $session) {
        if ($session['sessionId'] !== $sessionId) {
            $newSessions[] = $session;
        }
    }
    
    saveSessions($newSessions);
    return true;
}

// Função para remover todas as sessões de um usuário
function removeUserSessions($userId) {
    $sessions = getSessions();
    $newSessions = [];
    
    foreach ($sessions as $session) {
        if ($session['userId'] !== $userId) {
            $newSessions[] = $session;
        }
    }
    
    saveSessions($newSessions);
    return true;
}

// Função para verificar se uma sessão é válida
function isSessionValid($sessionId) {
    $sessions = getSessions();
    
    foreach ($sessions as $session) {
        if ($session['sessionId'] === $sessionId) {
            return true;
        }
    }
    
    return false;
}

// Função para obter um usuário pelo ID
function getUserById($userId) {
    $users = getUsers();
    
    foreach ($users as $user) {
        if ($user['id'] === $userId) {
            return $user;
        }
    }
    
    return null;
}

// Função para obter todas as sessões de um usuário
function getUserSessions($userId) {
    $sessions = getSessions();
    $userSessions = [];
    
    foreach ($sessions as $session) {
        if ($session['userId'] === $userId) {
            $userSessions[] = $session;
        }
    }
    
    return $userSessions;
}

// Função para gerar um ID de sessão único
function generateSessionId() {
    return md5(uniqid() . rand(1000, 9999) . time());
}

// Função para obter informações resumidas do dispositivo
function getDeviceInfo($userAgent) {
    // Detectar sistema operacional
    $os = "Desconhecido";
    if (strpos($userAgent, 'Windows') !== false) {
        $os = "Windows";
    } elseif (strpos($userAgent, 'Mac') !== false) {
        $os = "Mac";
    } elseif (strpos($userAgent, 'iPhone') !== false) {
        $os = "iPhone";
    } elseif (strpos($userAgent, 'iPad') !== false) {
        $os = "iPad";
    } elseif (strpos($userAgent, 'Android') !== false) {
        $os = "Android";
    } elseif (strpos($userAgent, 'Linux') !== false) {
        $os = "Linux";
    }
    
    // Detectar navegador
    $browser = "Desconhecido";
    if (strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Edg') === false) {
        $browser = "Chrome";
    } elseif (strpos($userAgent, 'Firefox') !== false) {
        $browser = "Firefox";
    } elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
        $browser = "Safari";
    } elseif (strpos($userAgent, 'Edg') !== false) {
        $browser = "Edge";
    } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
        $browser = "Internet Explorer";
    }
    
    return "$os - $browser";
}

// Limpar sessões inativas periodicamente (10% das vezes)
if (mt_rand(1, 10) === 1) {
    removeInactiveSessions();
}
?>
