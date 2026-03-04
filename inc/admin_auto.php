<?php
require_once 'auth.php';
// 1. Definir la ruta raíz del tema (Sube un nivel desde /inc/)
$theme_root = dirname(__DIR__);

// 2. Cargar el Autoload de Composer (Librerías PDF, etc.)
$autoload_path = $theme_root . '/vendor/autoload.php';

if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    // Si falla, mostramos la ruta donde lo buscó para depurar
    die(json_encode([
        "status" => "error", 
        "message" => "Falta autoload.php", 
        "debug_path" => $autoload_path
    ]));
}

// 3. Cargar el motor de WordPress (Para usar la base de datos y usuario actual)
// Buscamos wp-load.php subiendo niveles hasta encontrarlo
if (!defined('ABSPATH')) {
    $wp_load_path = $theme_root;
    while (!file_exists($wp_load_path . '/wp-load.php')) {
        $wp_load_path = dirname($wp_load_path);
        if ($wp_load_path == '/' || $wp_load_path == '.') break; // Evitar bucle infinito
    }
    
    if (file_exists($wp_load_path . '/wp-load.php')) {
        require_once($wp_load_path . '/wp-load.php');
    }
}

// 4. Ahora ya puedes usar $wpdb y funciones de plugins
global $wpdb;

// --- AQUI EMPIEZA TU LÓGICA DEL ARCHIVO ---
header("Content-Type: application/json; charset=UTF-8");
// ... resto del código ...
// 1. Configuración de errores y Cabeceras
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

// 2. Conexión BD
$servername = "localhost";
$username = "tabolang_app";
$password = 'm{Hpj.?IZL$Kz${S'; 
$dbname = "tabolang_pedidos";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) { die(json_encode(["status"=>"error", "msg"=>"DB Fail"])); }

$action = $_POST['action'] ?? '';

// --- ACCIÓN 1: GUARDAR VEHÍCULO, DOCS Y FECHAS ---
if ($action == 'guardar_vehiculo_full') {
    $patente = strtoupper($_POST['patente']);
    $marca = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $tipo = $_POST['tipo_vehiculo'];
    $clase = $_POST['clase_licencia'];
    
    // Recibimos las fechas. Si vienen vacías, usamos NULL o fecha antigua
    $f_permiso = !empty($_POST['fecha_permiso']) ? $_POST['fecha_permiso'] : '0000-00-00';
    $f_soap    = !empty($_POST['fecha_soap'])    ? $_POST['fecha_soap']    : '0000-00-00';
    $f_revision= !empty($_POST['fecha_revision'])? $_POST['fecha_revision']: '0000-00-00';

    // Función Helper para subir archivo
    function procesarArchivo($nameInput, $patentePrefix) {
        if (!isset($_FILES[$nameInput]) || $_FILES[$nameInput]['error'] != 0) return null;
        $ext = pathinfo($_FILES[$nameInput]['name'], PATHINFO_EXTENSION);
        $newName = $nameInput . "_" . $patentePrefix . "_" . time() . "." . $ext; // Agregamos time() para evitar cache
        $target = "uploads/docs_autos/" . $newName;
        
        // Crear carpeta si no existe
        if (!file_exists('uploads/docs_autos/')) { mkdir('uploads/docs_autos/', 0777, true); }
        
        if (move_uploaded_file($_FILES[$nameInput]['tmp_name'], $target)) {
            return "https://tabolango.cl/" . $target; // Retorna URL absoluta o relativa según tu server
        }
        return null;
    }

    $url_permiso = procesarArchivo('pdf_permiso', $patente);
    $url_soap = procesarArchivo('pdf_soap', $patente);
    $url_revision = procesarArchivo('pdf_revision', $patente);

    // Lógica SQL dinámica: Solo actualizamos los PDFs si se subió uno nuevo
    // Usamos INSERT ... ON DUPLICATE KEY UPDATE para crear o editar
    
    // Primero verificamos si existe para saber si hacemos INSERT o UPDATE manual (para control fino de campos nulos)
    // Pero para simplificar, haremos un UPDATE inteligente.
    
    $sql = "INSERT INTO vehiculos (patente, marca, modelo, tipo_vehiculo, clase_licencia, venc_permiso, venc_soap, venc_revision, pdf_permiso, pdf_soap, pdf_revision)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            marca=VALUES(marca), modelo=VALUES(modelo), 
            venc_permiso=VALUES(venc_permiso), venc_soap=VALUES(venc_soap), venc_revision=VALUES(venc_revision)";
            
    // Si subieron archivos nuevos, agregamos eso al query dinámicamente o actualizamos todo si no es null
    if($url_permiso) $sql .= ", pdf_permiso='$url_permiso'";
    if($url_soap)    $sql .= ", pdf_soap='$url_soap'";
    if($url_revision)$sql .= ", pdf_revision='$url_revision'";

    $stmt = $conn->prepare($sql);
    // Nota: Pasamos los valores iniciales para el INSERT. Si es UPDATE, tomará los VALUES() o las cadenas concatenadas arriba.
    // Para simplificar el bind_param con variables que pueden ser null en insert:
    $p_permiso = $url_permiso ? $url_permiso : ''; 
    $p_soap = $url_soap ? $url_soap : ''; 
    $p_rev = $url_revision ? $url_revision : '';

    $stmt->bind_param("sssssssssss", $patente, $marca, $modelo, $tipo, $clase, $f_permiso, $f_soap, $f_revision, $p_permiso, $p_soap, $p_rev);
    
    if($stmt->execute()){
        echo "Datos Guardados Correctamente";
    } else {
        echo "Error SQL: " . $stmt->error;
    }
}

// --- ACCIÓN 2: VINCULAR CONDUCTORES MASIVO ---
if ($action == 'vincular_conductores_masivo') {
    $patente = strtoupper($_POST['patente_vincular']);
    $conductores = $_POST['conductores'] ?? []; // Array de emails

    if (empty($patente)) { echo "Error: Falta patente"; exit; }

    // 1. Borramos todos los conductores actuales de este auto (Reset)
    $del = $conn->prepare("DELETE FROM vehiculo_usuarios WHERE patente_vehiculo = ?");
    $del->bind_param("s", $patente);
    $del->execute();

    // 2. Insertamos los seleccionados
    if (!empty($conductores)) {
        $stmt = $conn->prepare("INSERT INTO vehiculo_usuarios (patente_vehiculo, user_email) VALUES (?, ?)");
        foreach ($conductores as $email) {
            $stmt->bind_param("ss", $patente, $email);
            $stmt->execute();
        }
        echo "Se asignaron " . count($conductores) . " conductores.";
    } else {
        echo "Se eliminaron todos los conductores (ninguno seleccionado).";
    }
}
?>