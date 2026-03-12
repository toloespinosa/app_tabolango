<?php
// inc/admin_auto.php
require_once 'auth.php'; // Inyecta CORS, DB ($conn), y roles ($puede_editar)

// 1. BLINDAJE: Solo Admin (1) o Editor (2) pueden ejecutar acciones aquí
verificarPermisoEscritura(); 

$action = $_POST['action'] ?? '';

// --- ACCIÓN 1: GUARDAR O EDITAR VEHÍCULO Y DOCUMENTOS ---
if ($action === 'guardar_vehiculo_full') {
    $patente = strtoupper(trim($_POST['patente'] ?? ''));
    $marca   = trim($_POST['marca'] ?? '');
    $modelo  = trim($_POST['modelo'] ?? '');
    $tipo    = trim($_POST['tipo_vehiculo'] ?? '');
    $clase   = trim($_POST['clase_licencia'] ?? '');
    
    // Manejo de fechas vacías
    $f_permiso  = !empty($_POST['fecha_permiso'])  ? $_POST['fecha_permiso']  : '0000-00-00';
    $f_soap     = !empty($_POST['fecha_soap'])     ? $_POST['fecha_soap']     : '0000-00-00';
    $f_revision = !empty($_POST['fecha_revision']) ? $_POST['fecha_revision'] : '0000-00-00';

    // Función Helper optimizada para subir archivos
    function procesarArchivo($nameInput, $patentePrefix) {
        if (!isset($_FILES[$nameInput]) || $_FILES[$nameInput]['error'] !== UPLOAD_ERR_OK) return null;
        
        $ext = strtolower(pathinfo($_FILES[$nameInput]['name'], PATHINFO_EXTENSION));
        $newName = $nameInput . "_" . $patentePrefix . "_" . time() . "." . $ext; 
        
        // Usamos DOCUMENT_ROOT para asegurar la ruta absoluta en cualquier servidor
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/docs_autos/';
        if (!file_exists($upload_dir)) { 
            mkdir($upload_dir, 0777, true); 
        }
        
        $target = $upload_dir . $newName;
        
        if (move_uploaded_file($_FILES[$nameInput]['tmp_name'], $target)) {
            // Retorna URL pública dinámica (Local o Prod)
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            return $protocol . $_SERVER['HTTP_HOST'] . "/uploads/docs_autos/" . $newName;
        }
        return null;
    }

    // Procesar subidas
    $url_foto     = procesarArchivo('foto_auto', $patente); // Soporte para la foto del HTML
    $url_permiso  = procesarArchivo('pdf_permiso', $patente);
    $url_soap     = procesarArchivo('pdf_soap', $patente);
    $url_revision = procesarArchivo('pdf_revision', $patente);

    // Lógica SQL dinámica (Upsert)
    $sql = "INSERT INTO vehiculos (patente, marca, modelo, tipo_vehiculo, clase_licencia, venc_permiso, venc_soap, venc_revision, pdf_permiso, pdf_soap, pdf_revision, foto)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            marca=VALUES(marca), modelo=VALUES(modelo), tipo_vehiculo=VALUES(tipo_vehiculo), clase_licencia=VALUES(clase_licencia),
            venc_permiso=VALUES(venc_permiso), venc_soap=VALUES(venc_soap), venc_revision=VALUES(venc_revision)";
            
    // Si subieron archivos nuevos, los adjuntamos al UPDATE dinámicamente
    if($url_permiso)  $sql .= ", pdf_permiso='$url_permiso'";
    if($url_soap)     $sql .= ", pdf_soap='$url_soap'";
    if($url_revision) $sql .= ", pdf_revision='$url_revision'";
    if($url_foto)     $sql .= ", foto='$url_foto'"; 

    $stmt = $conn->prepare($sql);
    
    // Variables por defecto para el INSERT inicial (si es nuevo)
    $p_permiso = $url_permiso ?: ''; 
    $p_soap    = $url_soap ?: ''; 
    $p_rev     = $url_revision ?: '';
    $p_foto    = $url_foto ?: 'https://tabolango.cl/media/ilustracion_auto.png'; // Placeholder

    $stmt->bind_param("ssssssssssss", $patente, $marca, $modelo, $tipo, $clase, $f_permiso, $f_soap, $f_revision, $p_permiso, $p_soap, $p_rev, $p_foto);
    
    if($stmt->execute()){
        echo json_encode(["status" => "success", "message" => "Vehículo procesado con éxito."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error SQL: " . $stmt->error]);
    }
    exit;
}

// --- ACCIÓN 2: VINCULAR CONDUCTORES (MASIVO) ---
if ($action === 'vincular_conductores_masivo') {
    $patente = strtoupper($_POST['patente_vincular'] ?? '');
    $conductores = $_POST['conductores'] ?? []; // Array de correos desde el checkbox

    if (empty($patente)) { 
        echo json_encode(["status" => "error", "message" => "Falta patente"]); 
        exit; 
    }

    // 1. Reseteo: Borramos los actuales
    $del = $conn->prepare("DELETE FROM vehiculo_usuarios WHERE patente_vehiculo = ?");
    $del->bind_param("s", $patente);
    $del->execute();

    // 2. Insertamos las nuevas selecciones
    if (!empty($conductores)) {
        $stmt = $conn->prepare("INSERT INTO vehiculo_usuarios (patente_vehiculo, user_email) VALUES (?, ?)");
        foreach ($conductores as $email) {
            $stmt->bind_param("ss", $patente, $email);
            $stmt->execute();
        }
        echo json_encode(["status" => "success", "message" => "Se asignaron " . count($conductores) . " conductores."]);
    } else {
        echo json_encode(["status" => "success", "message" => "Se limpiaron los conductores del vehículo."]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Acción desconocida."]);