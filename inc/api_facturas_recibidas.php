<?php
// api_facturas_recibidas.php
require_once 'auth.php';
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    if (!isset($conn) || $conn->connect_error) throw new Exception("Error de conexión");

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $filtro = isset($_GET['filtro']) ? $conn->real_escape_string($_GET['filtro']) : '';

    $sql = "SELECT * FROM facturas_recibidas WHERE 1=1 ";
    if (!empty($filtro)) {
        $sql .= " AND (proveedor LIKE '%$filtro%' OR rut_proveedor LIKE '%$filtro%' OR folio LIKE '%$filtro%') ";
    }
    $sql .= " ORDER BY fecha_emision DESC LIMIT $limit OFFSET $offset";
    
    $res = $conn->query($sql);
    if (!$res) throw new Exception("Error SQL: " . $conn->error);
    
    $data = [];

    // Determinamos la ruta física de los XMLs
    $host_actual = $_SERVER['HTTP_HOST'] ?? '';
    $ruta_raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
        $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
    } else {
        $ruta_public = $ruta_raiz; 
    }
    $base_xml_dir = rtrim($ruta_public, '/') . '/uploads/recibidos_xml/';

    while($row = $res->fetch_assoc()) {
        $row['total_fmt'] = "$" . number_format($row['total_bruto'], 0, ',', '.');
        $row['fecha_fmt'] = date("d/m/Y", strtotime($row['fecha_emision']));
        
        $items_str = "Esperando archivo XML...";
        $archivo_fisico_existe = false;

        // Verificamos si la URL existe físicamente en el disco duro
        if (!empty($row['url_xml'])) {
            $filename = basename(parse_url($row['url_xml'], PHP_URL_PATH));
            $ruta_fisica = $base_xml_dir . $filename;
            
            if (file_exists($ruta_fisica)) {
                $archivo_fisico_existe = true;
                $xml_raw = file_get_contents($ruta_fisica);
                
                // Limpieza a prueba de balas para leer los ítems
                $xml_clean = preg_replace('/xmlns="[^"]+"/', '', $xml_raw);
                $xml_clean = preg_replace('/xmlns:[a-zA-Z0-9_]+="[^"]+"/', '', $xml_clean);
                $xml_clean = preg_replace('/<[a-zA-Z0-9_]+:([a-zA-Z0-9_]+)/', '<$1', $xml_clean);
                $xml_clean = preg_replace('/<\/[a-zA-Z0-9_]+:([a-zA-Z0-9_]+)/', '</$1', $xml_clean);
                $xml_clean = preg_replace('/<Documento[^>]*>/', '<Documento>', $xml_clean); 
                
                $xml_obj = @simplexml_load_string($xml_clean);
                
                if ($xml_obj) {
                    $detalles = $xml_obj->xpath('//Detalle');
                    $nombres = [];
                    
                    if (!empty($detalles)) {
                        foreach($detalles as $det) {
                            $qty = number_format((float)($det->QtyItem ?? 1), 0, ',', '.');
                            $nmb = (string)($det->NmbItem ?? 'Item');
                            $nombres[] = "• " . $qty . "x " . $nmb;
                        }
                        
                        $items_str = implode("&#10;", array_slice($nombres, 0, 8));
                        if (count($nombres) > 8) {
                            $items_str .= "&#10;... y " . (count($nombres) - 8) . " más";
                        }
                    } else {
                        $items_str = "Sin detalle en el XML";
                    }
                }
            }
        }
        
        // 🔥 MAGIA: Si el archivo aún no llega desde el correo, anulamos la URL en la respuesta
        if (!$archivo_fisico_existe) {
            $row['url_xml'] = null; // Esto hace que el JS salte la alerta "Documento no disponible"
            $items_str = "⚠️ Falta sincronizar XML desde el correo.";
        }
        
        $row['items_hover'] = htmlspecialchars($items_str, ENT_QUOTES);
        $data[] = $row;
    }
    
    echo json_encode($data);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>