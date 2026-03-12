<?php
// inc/guardar_producto.php
require_once 'auth.php'; // Inyecta $conn, CORS y la función de permisos

// 1. BLINDAJE DE SEGURIDAD: Solo Admin (1) o Editor (2)
verificarPermisoEscritura();

// 2. Captura de datos de forma segura
$id_producto         = !empty($_POST['id_producto']) ? (int)$_POST['id_producto'] : null;
$producto            = trim($_POST['producto'] ?? '');
$icono               = trim($_POST['icono'] ?? '📦');
$unidad              = trim($_POST['unidad'] ?? 'Bandeja');
$precio_actual       = (float)($_POST['precio_actual'] ?? 0);
$costo_actual        = (float)($_POST['costo_actual'] ?? 0);
$kg_por_unidad       = (float)($_POST['kg_por_unidad'] ?? 1.000);
$activo              = isset($_POST['activo']) ? (int)$_POST['activo'] : 1; 
$color_diferenciador = $_POST['color_diferenciador'] ?? '#0F4B29';
$variedad            = trim($_POST['variedad'] ?? '');
$calibre             = trim($_POST['calibre'] ?? '');
$formato             = trim($_POST['formato'] ?? '');

// Recálculos automáticos
$precio_por_kilo = ($kg_por_unidad > 0) ? $precio_actual / $kg_por_unidad : 0;
$costo_por_kilo  = ($kg_por_unidad > 0) ? $costo_actual / $kg_por_unidad : 0;

try {
    if (empty($id_producto)) {
        // INSERTAR NUEVO PRODUCTO
        $stmt = $conn->prepare("INSERT INTO productos (producto, icono, unidad, precio_actual, costo_actual, precio_por_kilo, costo_por_kilo, kg_por_unidad, activo, color_diferenciador, variedad, calibre, formato) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssdddddissss", $producto, $icono, $unidad, $precio_actual, $costo_actual, $precio_por_kilo, $costo_por_kilo, $kg_por_unidad, $activo, $color_diferenciador, $variedad, $calibre, $formato);
        
    } else {
        // ACTUALIZAR PRODUCTO EXISTENTE
        $stmt = $conn->prepare("UPDATE productos SET producto=?, icono=?, unidad=?, precio_actual=?, costo_actual=?, precio_por_kilo=?, costo_por_kilo=?, kg_por_unidad=?, activo=?, color_diferenciador=?, variedad=?, calibre=?, formato=? WHERE id_producto=?");
        
        $stmt->bind_param("sssdddddissssi", $producto, $icono, $unidad, $precio_actual, $costo_actual, $precio_por_kilo, $costo_por_kilo, $kg_por_unidad, $activo, $color_diferenciador, $variedad, $calibre, $formato, $id_producto);
    }

    if ($stmt->execute()) { 
        echo json_encode(["status" => "success", "message" => "Guardado con éxito"]); 
    } else { 
        echo json_encode(["status" => "error", "message" => $stmt->error]); 
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>