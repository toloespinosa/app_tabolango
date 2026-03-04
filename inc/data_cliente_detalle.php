<?php
// 1. CARGAR AUTH (Esto ya conecta a la BD correcta y maneja CORS)
require_once 'auth.php'; 

// data_cliente_detalle.php - VERSIÓN FINAL Y BLINDADA
ini_set('display_errors', 0); // En 0 para evitar que warnings rompan el formato JSON
error_reporting(E_ALL);
header("Content-Type: application/json; charset=utf-8");

// --- DETECCIÓN DINÁMICA DE ENTORNO ---
$host = $_SERVER['HTTP_HOST'] ?? '';
$is_local = (strpos($host, 'localhost') !== false || strpos($host, '.local') !== false || $host === '127.0.0.1' || strpos($host, ':8000') !== false);
$base_url_app = $is_local ? "http://" . $host : "https://tabolango.cl";

// --- FUNCIONES GLOBALES ---

function json_die($msg) {
    echo json_encode(["error" => $msg]);
    exit;
}

// Captura de errores fatales para no romper el Front-end
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        json_die("Error Fatal PHP: " . $error['message']);
    }
});

function getStats($conn, $where, $filter="") {
    $sql = "SELECT COUNT(DISTINCT pa.id_pedido) as pedidos, SUM(pa.total_venta) as inversion 
            FROM pedidos_activos pa 
            WHERE $where AND pa.estado='Entregado' $filter";
    $res = $conn->query($sql);
    return $res ? $res->fetch_assoc() : ['pedidos'=>0, 'inversion'=>0];
}

$inputJSON = json_decode(file_get_contents('php://input'), true);
$id_interno = $_POST['id_interno'] ?? $inputJSON['id_interno'] ?? '';
$action = $_POST['action'] ?? $inputJSON['action'] ?? $_GET['action'] ?? '';

// =================================================================================
// BLOQUE 1: LECTURA DE DATOS (GET)
// =================================================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? '';
    
    // A. CATEGORÍAS
    if ($action === 'get_categories') {
        $res = $conn->query("SELECT id_categoria as id, nombre_categoria as nombre FROM categorias_clientes ORDER BY nombre_categoria ASC");
        $cats = []; 
        if($res) while ($row = $res->fetch_assoc()) $cats[] = $row;
        echo json_encode($cats); 
        exit;
    }

    if (empty($id)) json_die("ID Requerido");

    // B. PERFIL
    $stmt = $conn->prepare("SELECT c.*, u.nombre as responsable_nombre FROM clientes c LEFT JOIN app_usuarios u ON c.responsable = u.email WHERE c.id_interno = ?");
    $stmt->bind_param("i", $id); 
    $stmt->execute();
    $perfil = $stmt->get_result()->fetch_assoc();
    
    if (!$perfil) json_die("Cliente no encontrado");

    $id_madre = $perfil['id_cliente'] ?? '';
    $es_global = $perfil['es_global'] ?? 0;
    
    $where_condition = ($es_global && $id_madre) 
        ? "pa.id_interno_cliente IN (SELECT id_interno FROM clientes WHERE id_cliente = '" . $conn->real_escape_string($id_madre) . "')"
        : "pa.id_interno_cliente = " . intval($id);

    // C. FILTROS (Optimizado para el Request AJAX del mes/año)
    $filtro_sql = "";
    if (isset($_GET['filtro_tipo']) && $_GET['filtro_tipo'] !== 'total') {
        $val = $conn->real_escape_string($_GET['filtro_valor']);
        if ($_GET['filtro_tipo'] == 'mes_año') $filtro_sql = "AND pa.fecha_despacho LIKE '$val%'";
        elseif ($_GET['filtro_tipo'] == 'solo_mes') $filtro_sql = "AND pa.fecha_despacho LIKE '%-$val-%'";
        elseif ($_GET['filtro_tipo'] == 'solo_año') $filtro_sql = "AND pa.fecha_despacho LIKE '$val-%'";
    }

    // D. PRODUCTOS TOP (Consulta BLINDADA contra el error ONLY_FULL_GROUP_BY de MySQL 8)
    $productos = [];
    $sql_prods = "SELECT 
                    pa.producto, 
                    SUM(pa.cantidad) as cant, 
                    MAX(pr.unidad) as unidad,      
                    MAX(pr.Variedad) as variedad,
                    MAX(pr.formato) as formato,
                    MAX(pr.calibre) as calibre
                  FROM pedidos_activos pa
                  LEFT JOIN productos pr ON pa.id_producto = pr.id_producto
                  WHERE $where_condition AND pa.estado='Entregado' $filtro_sql
                  GROUP BY pa.producto 
                  ORDER BY cant DESC LIMIT 5";
                  
    $res_p = $conn->query($sql_prods);
    if($res_p) {
        while($r = $res_p->fetch_assoc()) $productos[] = $r;
    }

    // --- RESPUESTA EXCLUSIVA PARA EL FILTRO AJAX DEL MES ---
    if (isset($_GET['filtro_tipo'])) {
        $stats = getStats($conn, $where_condition, $filtro_sql);
        echo json_encode([
            "success" => true,
            "stats_personalizada" => ["pedidos" => (int)$stats['pedidos'], "monto" => (int)$stats['inversion']],
            "productos_filtrados" => $productos // Ahora SÍ llegará con datos
        ]);
        exit;
    }

    // E. ANÁLISIS RECURRENTE (LISTA DETALLADA CON PIPES)
    $analisis_recurrente = null;
    try {
        $sql_last_3 = "SELECT DISTINCT pa.id_pedido FROM pedidos_activos pa WHERE $where_condition AND pa.estado='Entregado' ORDER BY pa.fecha_despacho DESC LIMIT 3";
        $res_ids = $conn->query($sql_last_3);
        $ids_pedidos = [];
        if($res_ids) while($row = $res_ids->fetch_assoc()) $ids_pedidos[] = "'" . $row['id_pedido'] . "'";

        if (count($ids_pedidos) > 0) {
            $ids_string = implode(",", $ids_pedidos);
            
            $sql_trend = "SELECT 
                            pa.producto, 
                            MAX(pr.unidad) as unidad,
                            MAX(pr.Variedad) as variedad,
                            MAX(pr.calibre) as calibre,
                            MAX(pr.formato) as formato,
                            AVG(pa.cantidad) as promedio, 
                            COUNT(*) as frecuencia 
                          FROM pedidos_activos pa 
                          LEFT JOIN productos pr ON pa.id_producto = pr.id_producto
                          WHERE pa.id_pedido IN ($ids_string) 
                          GROUP BY pa.producto 
                          ORDER BY frecuencia DESC, promedio DESC LIMIT 4";
            
            $res_trend = $conn->query($sql_trend);
            $items_lista = [];
            
            if($res_trend) {
                while($p = $res_trend->fetch_assoc()) {
                    $cant = round($p['promedio']);
                    if ($cant < 1) $cant = 1;
                    
                    $uni = $p['unidad'] ?? 'Un';
                    if ($cant > 1 && stripos($uni, 'kg') === false) {
                        if(substr($uni, -1) !== 's' && substr($uni, -1) !== 'S') $uni .= 's';
                    }
                    
                    $detalles_parts = [];
                    if (!empty($p['variedad'])) $detalles_parts[] = $p['variedad'];
                    if (!empty($p['calibre']))  $detalles_parts[] = "Cal: " . $p['calibre']; 
                    if (!empty($p['formato']))  $detalles_parts[] = $p['formato'];

                    $detalles_str = count($detalles_parts) > 0 ? " " . implode(" | ", $detalles_parts) : "";
                    $items_lista[] = "&bull; <b>$cant $uni</b> de " . $p['producto'] . $detalles_str;
                }
            }

            if (count($items_lista) > 0) {
                $html_lista = implode("<br>", $items_lista);
                $analisis_recurrente = [
                    "titulo" => "Tendencia de pedidos encontrada",
                    "mensaje" => "Suele pedir:<br>" . $html_lista
                ];
            }
        }
    } catch (Exception $e) { $analisis_recurrente = null; }
    
    // F. FACTURAS
    $facturas = [];
    $sql_fact = "SELECT 
                    pa.id_pedido, 
                    COALESCE(pa.numero_factura, pa.id_pedido) as folio, 
                    DATE_FORMAT(pa.fecha_despacho, '%d-%m-%Y') as fecha, 
                    pa.total_venta as monto_neto, 
                    pa.url_factura as url_pdf 
                 FROM pedidos_activos pa 
                 WHERE $where_condition AND pa.estado='Entregado' AND pa.url_factura IS NOT NULL AND pa.url_factura != ''
                 GROUP BY pa.id_pedido
                 ORDER BY pa.fecha_despacho DESC LIMIT 3";
                 
    $res_f = $conn->query($sql_fact);
    if($res_f) while($r = $res_f->fetch_assoc()) $facturas[] = $r;

    // G. SUCURSALES
    $sucursales = [];
    if ($es_global && $id_madre) {
        $res_suc = $conn->query("SELECT id_interno, cliente FROM clientes WHERE id_cliente='" . $conn->real_escape_string($id_madre) . "' AND id_interno != " . intval($id) . " AND activo=1");
        if($res_suc) while($s = $res_suc->fetch_assoc()) $sucursales[] = $s;
    }

    // H. EXTRAS
    $stats_total = getStats($conn, $where_condition);
    $last_date_res = $conn->query("SELECT fecha_despacho FROM pedidos_activos pa WHERE $where_condition AND pa.estado='Entregado' ORDER BY pa.fecha_despacho DESC LIMIT 1");
    $ultimo = ($last_date_res && $row_l = $last_date_res->fetch_assoc()) ? date("d/m/Y", strtotime($row_l['fecha_despacho'])) : "-";

    // RESPUESTA JSON FINAL (Carga inicial)
    echo json_encode([
        "perfil" => $perfil,
        "sucursales" => $sucursales,
        "productos" => $productos,
        "stats" => [
            "total" => ["pedidos" => (int)$stats_total['pedidos'], "monto" => (int)$stats_total['inversion']],
            "fecha_ultimo" => $ultimo,
            "prediccion" => "-"
        ],
        "analisis_recurrente" => $analisis_recurrente,
        "facturas" => $facturas,
        "is_admin_user" => true
    ]);
    exit;
}

// =================================================================================
// BLOQUE 2: ESCRITURA (POST)
// =================================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. ACTUALIZAR ESTADO (Borrar/Reactivar)
    if ($action === 'delete_client' || $action === 'reactivate_client') {
        $st = ($action === 'delete_client') ? 0 : 1;
        $stmt = $conn->prepare("UPDATE clientes SET activo = ? WHERE id_interno = ?");
        $stmt->bind_param("ii", $st, $id_interno);
        echo json_encode(["success" => $stmt->execute()]);
        exit;
    }

   // B. ACTUALIZAR PERFIL
    if ($action === 'update_client_profile') {
        $id_edit = $_POST['id_interno'] ?? 0;
        $curr = $conn->query("SELECT * FROM clientes WHERE id_interno = " . intval($id_edit))->fetch_assoc();
        if(!$curr) die(json_encode(["success"=>false, "error"=>"ID faltante"]));

        // Mapeo dinámico
        $rut = $_POST['rut_cliente'] ?? $curr['rut_cliente'];
        $cliente = $_POST['cliente'] ?? $curr['cliente'];
        $razon = $_POST['razon_social'] ?? $curr['razon_social'];
        $giro = $_POST['giro'] ?? $curr['giro'];
        $dir = $_POST['direccion'] ?? $curr['direccion'];
        $comuna = $_POST['comuna'] ?? $curr['comuna'];
        $email = $_POST['email'] ?? $curr['email'];
        $tel = $_POST['telefono'] ?? $curr['telefono'];
        $cat = $_POST['id_categoria'] ?? $curr['tipo_cliente'];
        $resp = $_POST['responsable_email'] ?? $curr['responsable'];
        $lat = $_POST['latitud'] ?? $curr['lat_despacho'];
        $lng = $_POST['longitud'] ?? $curr['lng_despacho'];

        $nom = $_POST['nombre'] ?? $curr['nombre'];
        $ape = $_POST['apellido'] ?? $curr['apellido'];
        $contacto_local = $_POST['contacto'] ?? $curr['contacto']; 

        $dir_fact = $_POST['direccion_factura'] ?? $curr['direccion_factura'];
        $com_fact = $_POST['comuna_factura'] ?? $curr['comuna_factura'];
        $ciu_fact = $_POST['ciudad_factura'] ?? $curr['ciudad_factura'];
        $email_fact = $_POST['email_factura'] ?? $curr['email_factura'];
        $tel_fact = $_POST['telefono_factura'] ?? $curr['telefono_factura'];

        $logo = $curr['logo'];
        if (isset($_FILES['logo_cliente']) && $_FILES['logo_cliente']['error'] === 0) {
            $name = "logo_" . $id_edit . "_" . time() . "." . pathinfo($_FILES['logo_cliente']['name'], PATHINFO_EXTENSION);
            if (!is_dir('logos')) mkdir('logos', 0755, true);
            if (move_uploaded_file($_FILES['logo_cliente']['tmp_name'], "logos/" . $name)) {
                // 🔥 Usa la base dinamica para que funcione en Local y Prod perfectamente
                $logo = $base_url_app . "/logos/" . $name;
            }
        }

        $stmt = $conn->prepare("UPDATE clientes SET 
                rut_cliente=?, cliente=?, razon_social=?, giro=?, tipo_cliente=?, responsable=?,
                direccion=?, comuna=?, email=?, telefono=?, lat_despacho=?, lng_despacho=?,
                nombre=?, apellido=?, contacto=?, 
                direccion_factura=?, comuna_factura=?, ciudad_factura=?, email_factura=?, telefono_factura=?,
                logo=?
                WHERE id_interno=?");
        
        $stmt->bind_param("sssssssssssssssssssssi", 
            $rut, $cliente, $razon, $giro, $cat, $resp,
            $dir, $comuna, $email, $tel, $lat, $lng,
            $nom, $ape, $contacto_local,
            $dir_fact, $com_fact, $ciu_fact, $email_fact, $tel_fact,
            $logo, $id_edit
        );

        if ($stmt->execute()) {
            echo json_encode(["success"=>true]);
        } else {
            echo json_encode(["success"=>false, "error"=>$stmt->error]);
        }
        exit;
    }
}
$conn->close();
?>