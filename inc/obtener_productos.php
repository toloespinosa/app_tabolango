<?php
// inc/obtener_productos.php
require_once 'auth.php'; // Inyecta la conexión a BD ($conn) y las cabeceras CORS

try {
    // 1. CARGAMOS TODOS LOS TRAMOS PRIMERO (Optimización de velocidad)
    $tramos_db = [];
    $sql_tr = "SELECT id_producto, cantidad_minima, porcentaje_descuento FROM productos_tramos_descuento ORDER BY cantidad_minima DESC";
    $res_tr = $conn->query($sql_tr);
    
    if ($res_tr) {
        while($t = $res_tr->fetch_assoc()) {
            $id_p = $t['id_producto'];
            if (!isset($tramos_db[$id_p])) {
                $tramos_db[$id_p] = [];
            }
            // Los guardamos con las llaves 'min' y 'pct' que espera nuestro JS
            $tramos_db[$id_p][] = [
                "min" => (float)$t['cantidad_minima'],
                "pct" => (float)$t['porcentaje_descuento']
            ];
        }
    }

    // 2. CARGAMOS LOS PRODUCTOS Y LES ADJUNTAMOS SUS TRAMOS
    $sql = "SELECT * FROM productos ORDER BY producto ASC";
    $result = $conn->query($sql);

    $productos = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            // Formateamos los números para que JS los reciba como Float/Int
            $row['precio_actual']     = (float)$row['precio_actual'];
            $row['costo_actual']      = (float)$row['costo_actual'];
            $row['kg_por_unidad']     = (float)$row['kg_por_unidad']; 
            $row['activo']            = (int)$row['activo'];
            $row['aplica_descuentos'] = (int)($row['aplica_descuentos'] ?? 0);
            
            // Le inyectamos sus tramos correspondientes (si tiene) o un array vacío
            $id_actual = $row['id_producto'];
            $row['tramos'] = isset($tramos_db[$id_actual]) ? $tramos_db[$id_actual] : [];
            
            $productos[] = $row;
        }
    }

    // Salida limpia
    echo json_encode($productos, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>