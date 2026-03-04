<?php
// 1. CARGAR AUTH (Maneja conexión DB local/prod y CORS)
require_once 'auth.php'; 

// Configuración de Headers y Errores
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// --- FUNCIONES GLOBALES ---

function json_die($msg) {
    if(ob_get_length()) ob_clean();
    echo json_encode(["error" => $msg]);
    exit;
}

// Captura errores fatales de PHP
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        if(ob_get_length()) ob_clean();
        echo json_encode(["status" => "error", "message" => "Error Fatal PHP: " . $error['message']]);
    }
});

// Función auxiliar para estadísticas
function getStats($conn, $where, $filter="") {
    $sql = "SELECT COUNT(DISTINCT pa.id_pedido) as pedidos, SUM(pa.total_venta) as inversion 
            FROM pedidos_activos pa 
            WHERE $where AND pa.estado='Entregado' $filter";
    $res = $conn->query($sql);
    return $res ? $res->fetch_assoc() : ['pedidos'=>0, 'inversion'=>0];
}

// 2. CAPTURA DE DATOS
$inputJSON = json_decode(file_get_contents('php://input'), true);
$id_interno = $_POST['id_interno'] ?? $inputJSON['id_interno'] ?? '';
$action = $_POST['action'] ?? $inputJSON['action'] ?? $_GET['action'] ?? '';

// =================================================================================
// BLOQUE DE LÓGICA
// =================================================================================

// --- ACCIÓN: OBTENER CATEGORÍAS ---
if ($action === 'get_categories') {
    $sql = "SELECT id_categoria as id, nombre_categoria as nombre FROM categorias_clientes ORDER BY nombre_categoria ASC";
    $res = $conn->query($sql);
    $cats = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cats[] = $row;
        }
    }
    echo json_encode($cats);
    exit;
}

// --- ACCIÓN: LISTAR CLIENTES (CORREGIDO CON JOIN) ---
// --- ACCIÓN: LISTAR CLIENTES (CON CÁLCULOS DE VENTAS Y RANKING) ---
if ($action === 'list_clients') {
    // Esta consulta calcula:
    // 1. Venta del mes actual (monto_mes_actual)
    // 2. Promedio de los últimos 3 meses (promedio_mensual)
    // 3. Posición en el ranking según ventas totales
    $sql = "SELECT c.*, cat.nombre_categoria as nombre_categoria_cliente,
            
            /* Cálculo de Venta Mes Actual */
            (SELECT SUM(pa.total_venta) FROM pedidos_activos pa 
             WHERE pa.id_interno_cliente = c.id_interno 
             AND pa.estado = 'Entregado' 
             AND MONTH(pa.fecha_despacho) = MONTH(CURRENT_DATE()) 
             AND YEAR(pa.fecha_despacho) = YEAR(CURRENT_DATE())) as monto_mes_actual,
            
            /* Cálculo de Promedio Mensual (Últimos 90 días / 3) */
            (SELECT SUM(pa.total_venta) / 3 FROM pedidos_activos pa 
             WHERE pa.id_interno_cliente = c.id_interno 
             AND pa.estado = 'Entregado' 
             AND pa.fecha_despacho >= DATE_SUB(CURRENT_DATE(), INTERVAL 90 DAY)) as promedio_mensual,
             
            /* Cálculo de Ranking */
            (SELECT rnk FROM (
                SELECT id_interno_cliente, RANK() OVER (ORDER BY SUM(total_venta) DESC) as rnk
                FROM pedidos_activos WHERE estado = 'Entregado'
                GROUP BY id_interno_cliente
            ) as ranking_table WHERE id_interno_cliente = c.id_interno) as ranking

            FROM clientes c
            LEFT JOIN categorias_clientes cat ON c.tipo_cliente = cat.id_categoria
            WHERE c.activo = 1 
            ORDER BY c.cliente ASC";
            
    $res = $conn->query($sql);
    $clients = []; 
    if($res) {
        while ($row = $res->fetch_assoc()) {
            // 🔥 BLINDAJE DE SEGURIDAD (Ya verificado que funciona)
            if (!$puede_ver_dinero) {
                unset($row['monto_mes_actual']);
                unset($row['promedio_mensual']);
                unset($row['ranking']);
            }
            $clients[] = $row;
        }
    }
    
    echo json_encode([
        'clientes' => $clients, 
        'is_admin' => ($rol_id === 1),
        'debug' => [
            'email_recibido' => $email_solicitante,
            'rol_id_detectado' => $rol_id,
            'permiso_dinero' => $puede_ver_dinero
        ]
    ]); 
    exit;
}

// --- ACCIÓN: ELIMINAR / REACTIVAR CLIENTE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'delete_client' || $action === 'reactivate_client')) {
    $nuevo_estado = ($action === 'delete_client') ? 0 : 1;
    if (empty($id_interno)) json_die("ID faltante");

    $stmt = $conn->prepare("UPDATE clientes SET activo = ? WHERE id_interno = ?");
    $stmt->bind_param("ii", $nuevo_estado, $id_interno);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    exit;
}

// --- ACCIÓN: ACTUALIZAR PERFIL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_client_profile') {
    $id_edit = $_POST['id_interno'] ?? 0;
    
    // Validar ID
    $res_curr = $conn->query("SELECT * FROM clientes WHERE id_interno = $id_edit");
    if (!$res_curr || $res_curr->num_rows === 0) json_die("Cliente no encontrado");
    $curr = $res_curr->fetch_assoc();

    // Mapeo de datos (Si no viene en POST, mantenemos el actual)
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

    // Manejo de Logo
    $logo = $curr['logo'];
    if (isset($_FILES['logo_cliente']) && $_FILES['logo_cliente']['error'] === 0) {
        $ext = pathinfo($_FILES['logo_cliente']['name'], PATHINFO_EXTENSION);
        $name = "logo_" . $id_edit . "_" . time() . "." . $ext;
        $upload_dir = '../logos/'; 
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if (move_uploaded_file($_FILES['logo_cliente']['tmp_name'], $upload_dir . $name)) {
            $logo = "https://tabolango.cl/logos/" . $name; 
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

    if ($stmt->execute()) echo json_encode(["success"=>true]);
    else echo json_encode(["success"=>false, "error"=>$stmt->error]);
    exit;
}

// --- DETALLE INDIVIDUAL (GET) ---
$id = $_GET['id'] ?? '';
if (!empty($id)) {
    // Consulta preparada usando mysqli en lugar de $wpdb->prepare
    $stmt = $conn->prepare("
        SELECT c.*, u.nombre as responsable_nombre, cat.nombre_categoria as nombre_categoria_cliente
        FROM clientes c 
        LEFT JOIN app_usuarios u ON c.responsable = u.email 
        LEFT JOIN categorias_clientes cat ON c.tipo_cliente = cat.id_categoria
        WHERE c.id_interno = ? LIMIT 1
    ");
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cliente = $result->fetch_assoc();
    
    if (!$cliente) json_die("No existe el cliente");

    // Lógica para estadísticas (Sucursales, Globales, etc.)
    $id_madre = $cliente['id_cliente'] ?? '';
    $es_global = $cliente['es_global'] ?? 0;
    
    $where_condition = ($es_global && $id_madre) 
        ? "pa.id_interno_cliente IN (SELECT id_interno FROM clientes WHERE id_cliente = '$id_madre')"
        : "pa.id_interno_cliente = $id";

    // Productos Top
    $productos = [];
    $sql_prods = "SELECT pa.producto, SUM(pa.cantidad) as cant, pr.unidad, pr.Variedad as variedad, pr.formato, pr.calibre
                  FROM pedidos_activos pa
                  LEFT JOIN productos pr ON pa.id_producto = pr.id_producto
                  WHERE $where_condition AND pa.estado='Entregado'
                  GROUP BY pa.producto, pr.Variedad, pr.formato 
                  ORDER BY cant DESC LIMIT 5";
    $res_p = $conn->query($sql_prods);
    if($res_p) while($r = $res_p->fetch_assoc()) $productos[] = $r;

    // Facturas
    $facturas = [];
    $sql_fact = "SELECT pa.id_pedido, COALESCE(pa.numero_factura, pa.id_pedido) as folio, 
                 DATE_FORMAT(pa.fecha_despacho, '%d-%m-%Y') as fecha, pa.total_venta as monto_neto, pa.url_factura as url_pdf 
                 FROM pedidos_activos pa WHERE $where_condition AND pa.estado='Entregado' AND pa.url_factura IS NOT NULL AND pa.url_factura != ''
                 GROUP BY pa.id_pedido ORDER BY pa.fecha_despacho DESC LIMIT 3";
    $res_f = $conn->query($sql_fact);
    if($res_f) while($r = $res_f->fetch_assoc()) $facturas[] = $r;

    // Stats generales
    $stats_total = getStats($conn, $where_condition);
    $last_date_res = $conn->query("SELECT fecha_despacho FROM pedidos_activos pa WHERE $where_condition AND pa.estado='Entregado' ORDER BY pa.fecha_despacho DESC LIMIT 1");
    $ultimo = ($last_date_res && $row_l = $last_date_res->fetch_assoc()) ? date("d/m/Y", strtotime($row_l['fecha_despacho'])) : "-";

    // Sucursales
    $sucursales = [];
    if ($es_global && $id_madre) {
        $res_suc = $conn->query("SELECT id_interno, cliente FROM clientes WHERE id_cliente='$id_madre' AND id_interno != $id AND activo=1");
        if($res_suc) while($s = $res_suc->fetch_assoc()) $sucursales[] = $s;
    }

    echo json_encode([
        "perfil" => $cliente, 
        "productos" => $productos,
        "facturas" => $facturas,
        "stats" => [
            "total" => ["pedidos" => (int)$stats_total['pedidos'], "monto" => (int)$stats_total['inversion']],
            "fecha_ultimo" => $ultimo
        ],
        "sucursales" => $sucursales
    ]);
    exit;
}
?>