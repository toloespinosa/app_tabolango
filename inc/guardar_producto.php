<?php
// inc/guardar_producto.php
require_once 'auth.php'; 

// Seguridad: Verificar permisos de Admin o Editor
verificarPermisoEscritura();

// 1. Captura de datos básicos y logísticos
$id_producto         = !empty($_POST['id_producto']) ? (int)$_POST['id_producto'] : null;
$producto            = trim($_POST['producto'] ?? '');
$icono               = trim($_POST['icono'] ?? '📦');
$unidad              = trim($_POST['unidad'] ?? 'Bandeja');
$kg_por_unidad       = (float)($_POST['kg_por_unidad'] ?? 1.000);
$precio_actual       = (float)($_POST['precio_actual'] ?? 0);
$costo_actual        = (float)($_POST['costo_actual'] ?? 0);
$color_diferenciador = $_POST['color_diferenciador'] ?? '#0F4B29';
$activo              = (int)($_POST['activo'] ?? 1);
$variedad            = trim($_POST['variedad'] ?? '');
$calibre             = trim($_POST['calibre'] ?? '');
$formato             = trim($_POST['formato'] ?? '');

// 2. Captura de lógica de descuentos
$aplica_descuentos   = (int)($_POST['aplica_descuentos'] ?? 0);
$tramos_json         = $_POST['tramos_json'] ?? '[]';

// 3. Recálculos automáticos para la base de datos
$precio_por_kilo = ($kg_por_unidad > 0) ? $precio_actual / $kg_por_unidad : 0;
$costo_por_kilo  = ($kg_por_unidad > 0) ? $costo_actual / $kg_por_unidad : 0;

try {
    $conn->begin_transaction();

    if ($id_producto) {
        // --- ACTUALIZAR PRODUCTO EXISTENTE ---
        // Incluimos todos los campos que faltaban: icono, unidad, kg, costo, etc.
        $stmt = $conn->prepare("UPDATE productos SET 
            producto=?, icono=?, unidad=?, kg_por_unidad=?, 
            precio_actual=?, costo_actual=?, precio_por_kilo=?, costo_por_kilo=?, 
            color_diferenciador=?, activo=?, variedad=?, calibre=?, formato=?, 
            aplica_descuentos=? 
            WHERE id_producto=?");
        
        $stmt->bind_param("sssdiddddisssii", 
            $producto, $icono, $unidad, $kg_por_unidad, 
            $precio_actual, $costo_actual, $precio_por_kilo, $costo_por_kilo, 
            $color_diferenciador, $activo, $variedad, $calibre, $formato, 
            $aplica_descuentos, $id_producto
        );
        $stmt->execute();

        // --- GESTIÓN DE TRAMOS ---
        // Borramos los tramos anteriores para reemplazarlos por los nuevos
        $conn->query("DELETE FROM productos_tramos_descuento WHERE id_producto = $id_producto");

        if ($aplica_descuentos === 1) {
            $tramos = json_decode($tramos_json, true);
            if (!empty($tramos)) {
                $stmt_tr = $conn->prepare("INSERT INTO productos_tramos_descuento (id_producto, cantidad_minima, porcentaje_descuento) VALUES (?, ?, ?)");
                foreach ($tramos as $tr) {
                    $min = (float)$tr['min'];
                    $pct = (float)$tr['pct'];
                    $stmt_tr->bind_param("idd", $id_producto, $min, $pct);
                    $stmt_tr->execute();
                }
            }
        }
    } else {
        // --- INSERTAR NUEVO PRODUCTO ---
        $stmt = $conn->prepare("INSERT INTO productos 
            (producto, icono, unidad, kg_por_unidad, precio_actual, costo_actual, precio_por_kilo, costo_por_kilo, color_diferenciador, activo, variedad, calibre, formato, aplica_descuentos) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssdiddddisssi", 
            $producto, $icono, $unidad, $kg_por_unidad, $precio_actual, $costo_actual, $precio_por_kilo, $costo_por_kilo, $color_diferenciador, $activo, $variedad, $calibre, $formato, $aplica_descuentos);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Producto y tramos actualizados correctamente"]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>