<?php
require_once 'auth.php';
// ajustes_noti.php

// 1. HEADERS & CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. CONEXIÓN BD
$host = "localhost";
$db   = "tabolang_pedidos"; 
$user = "tabolang_app";     
$pass = 'm{Hpj.?IZL$Kz${S'; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión BD']);
    exit;
}

// 3. OBTENER EMAIL
$input = json_decode(file_get_contents('php://input'), true);
$email = $_GET['email'] ?? $input['email'] ?? null;

if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Email no proporcionado']);
    exit;
}

// --- FUNCIÓN PARA VERIFICAR SI ES ADMIN ---
// Usando tus tablas reales: app_usuario_roles y app_roles
function isUserAdmin($pdo, $email) {
    try {
        // Buscamos si este email tiene asignado el rol con nombre 'administrador'
        $sql = "SELECT r.nombre_rol 
                FROM app_roles r
                INNER JOIN app_usuario_roles ur ON r.id = ur.rol_id
                WHERE ur.usuario_email = ? AND r.nombre_rol = 'administrador'
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        
        // Si devuelve una fila, es admin.
        return $stmt->fetch() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

// Verificamos el rol
$es_admin = isUserAdmin($pdo, $email);

$method = $_SERVER['REQUEST_METHOD'];

// --- GET: OBTENER DATOS ---
if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM app_fcm_tokens WHERE email = ? ORDER BY fecha_registro DESC LIMIT 1");
    $stmt->execute([$email]);
    $prefs = $stmt->fetch();

    if (!$prefs) {
        // Valores por defecto
        $response = [
            'notify_pedido_creado' => 1,
            'notify_cambio_estado' => 1,
            'notify_pedido_entregado' => 1,
            'notify_pedido_editado' => 1,
            'notify_doc_por_vencer' => 1,
            'notify_doc_vencido' => 1
        ];
    } else {
        // Limpiamos datos técnicos y aseguramos enteros
        unset($prefs['id'], $prefs['token'], $prefs['dispositivo_id'], $prefs['fecha_registro']);
        $response = array_map('intval', $prefs);
    }

    // *** IMPORTANTE: Enviamos la bandera de admin al frontend ***
    $response['is_admin'] = $es_admin;

    echo json_encode($response);
} 

// --- POST: GUARDAR DATOS ---
elseif ($method === 'POST') {
    if (!$input) {
        http_response_code(400); 
        echo json_encode(['error' => 'Datos inválidos']); exit; 
    }

    // Aquí podríamos validar de nuevo $es_admin si quisiéramos seguridad estricta en el servidor
    
    $p_creado   = !empty($input['notify_pedido_creado']) ? 1 : 0;
    $p_estado   = !empty($input['notify_cambio_estado']) ? 1 : 0;
    $p_entrega  = !empty($input['notify_pedido_entregado']) ? 1 : 0;
    $p_editado  = !empty($input['notify_pedido_editado']) ? 1 : 0;
    $d_vencer   = !empty($input['notify_doc_por_vencer']) ? 1 : 0;
    $d_vencido  = !empty($input['notify_doc_vencido']) ? 1 : 0;

    $sql = "UPDATE app_fcm_tokens SET 
            notify_pedido_creado = ?,
            notify_cambio_estado = ?,
            notify_pedido_entregado = ?,
            notify_pedido_editado = ?,
            notify_doc_por_vencer = ?,
            notify_doc_vencido = ?
            WHERE email = ?";

    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([$p_creado, $p_estado, $p_entrega, $p_editado, $d_vencer, $d_vencido, $email]);
        echo json_encode(['success' => true, 'message' => 'Preferencias actualizadas']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar']);
    }
}
?>