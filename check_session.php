<?php
// Inicializar sessão
session_start();
require_once 'functions.php';

// Verificar se a sessão é válida
$response = [
    'valid' => false,
    'reason' => 'unknown'
];

if (isset($_SESSION['user']) && isset($_SESSION['sessionId'])) {
    $user = $_SESSION['user'];
    
    // Verificar se o acesso expirou
    if (!$user['isAdmin'] && strtotime($user['expiresAt']) < time()) {
        $response['valid'] = false;
        $response['reason'] = 'expired';
        // Remover a sessão expirada
        removeSession($_SESSION['sessionId']);
    } 
    // Verificar se a sessão ainda é válida
    else if (!isSessionValid($_SESSION['sessionId'])) {
        $response['valid'] = false;
        $response['reason'] = 'terminated';
    } 
    else {
        $response['valid'] = true;
        
        // Atualizar o timestamp da sessão para indicar atividade
        updateSessionTimestamp($_SESSION['sessionId']);
    }
}

// Retornar resposta em JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
