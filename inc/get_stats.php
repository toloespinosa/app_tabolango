<?php
require_once 'auth.php';
error_reporting(E_ALL);
ini_set('display_errors', 0); 

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// Usamos la conexión que ya viene de auth.php
if ($conn->connect_error) die(json_encode(["error" => "Conexión fallida"]));
$conn->set_charset("utf8mb4");

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$costos_fijos_mensuales = 4633000;

$response = [
    "kpis" => [],
    "mensual" => [
        "meses" => ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"], 
        "ventas" => array_fill(0, 12, 0), 
        "proyectadas" => array_fill(0, 12, 0),
        "proyectadas_margen" => array_fill(0, 12, 0), // 🔥 NUEVO: Margen de lo pendiente
        "metas" => array_fill(0, 12, 0)
    ],
    "vendedores" => ["nombres" => [], "ventas" => []],
    "detallado_mes" => [],
    "empresas" => [],
    "productos" => []
];

// 1. KPIs Globales (Corrección: COUNT DISTINCT para pedidos reales)
$sql_kpi = "SELECT 
    SUM(CASE WHEN MONTH(fecha_despacho) = MONTH(CURRENT_DATE()) AND YEAR(fecha_despacho) = YEAR(CURRENT_DATE()) AND LOWER(TRIM(estado)) = 'entregado' THEN total_venta ELSE 0 END) as v_mes,
    SUM(CASE WHEN MONTH(fecha_despacho) = MONTH(CURRENT_DATE()) AND YEAR(fecha_despacho) = YEAR(CURRENT_DATE()) AND LOWER(TRIM(estado)) = 'entregado' THEN margen ELSE 0 END) as m_mes,
    SUM(CASE WHEN YEAR(fecha_despacho) = $year AND LOWER(TRIM(estado)) = 'entregado' THEN total_venta ELSE 0 END) as v_anual,
    SUM(CASE WHEN YEAR(fecha_despacho) = $year AND LOWER(TRIM(estado)) = 'entregado' THEN margen ELSE 0 END) as m_anual,
    COUNT(DISTINCT CASE WHEN LOWER(TRIM(estado)) NOT IN ('entregado', 'cancelado') THEN id_pedido END) as p_pend
    FROM pedidos_activos";

$res_kpi = $conn->query($sql_kpi)->fetch_assoc();

$v_mes = (float)$res_kpi['v_mes'];
$m_mes = (float)$res_kpi['m_mes'];
$v_anual = (float)$res_kpi['v_anual'];
$m_anual = (float)$res_kpi['m_anual'];

$margen_ratio = ($v_mes > 0) ? ($m_mes / $v_mes) : 0.17; 
if($margen_ratio < 0.05) $margen_ratio = 0.17;

$meta_dinamica = ($costos_fijos_mensuales / $margen_ratio) * 1.25;

$response["kpis"] = [
    "venta_mes" => $v_mes,
    "venta_anual" => $v_anual,
    "margen_total_pesos" => $m_anual,
    "margen_porcentaje" => ($v_anual > 0) ? round(($m_anual / $v_anual) * 100, 1) : 0,
    "pedidos_pendientes" => (int)$res_kpi['p_pend'],
    "meta_mensual" => round($meta_dinamica)
];

// 2. Histórico Mensual (Agregamos margen_proyectado)
$sql_m = "SELECT MONTH(fecha_despacho) as mes, 
                 SUM(CASE WHEN LOWER(TRIM(estado)) = 'entregado' THEN total_venta ELSE 0 END) as total_real,
                 SUM(CASE WHEN LOWER(TRIM(estado)) NOT IN ('entregado', 'cancelado') THEN total_venta ELSE 0 END) as total_proyectado,
                 SUM(CASE WHEN LOWER(TRIM(estado)) NOT IN ('entregado', 'cancelado') THEN margen ELSE 0 END) as margen_proyectado
          FROM pedidos_activos WHERE YEAR(fecha_despacho) = $year GROUP BY MONTH(fecha_despacho)";
$res_m = $conn->query($sql_m);
while($row = $res_m->fetch_assoc()) {
    $idx = (int)$row['mes'] - 1;
    $response["mensual"]["ventas"][$idx] = (float)$row['total_real'];
    $response["mensual"]["proyectadas"][$idx] = (float)$row['total_proyectado'];
    $response["mensual"]["proyectadas_margen"][$idx] = (float)$row['margen_proyectado']; // 🔥 Guardamos el margen
    $response["mensual"]["metas"][$idx] = round($meta_dinamica);
}

// 3. Detallado (Incluimos el estado para que el JS filtre)
$sql_det = "SELECT 
                MONTH(p.fecha_despacho) as mes, DAY(p.fecha_despacho) as dia, 
                p.creado_por as vendedor, p.cliente, p.total_venta, p.margen, p.producto, p.estado,
                c.comuna, cat.nombre_categoria
            FROM pedidos_activos p
            LEFT JOIN clientes c ON p.id_interno_cliente = c.id_interno
            LEFT JOIN categorias_clientes cat ON c.tipo_cliente = cat.id_categoria
            WHERE YEAR(p.fecha_despacho) = $year AND LOWER(TRIM(p.estado)) != 'cancelado'";

$res_det = $conn->query($sql_det);
while($row = $res_det->fetch_assoc()) {
    $row['margen'] = (float)$row['margen'];
    $row['total_venta'] = (float)$row['total_venta'];
    $response["detallado_mes"][] = $row;
}

// 4. Vendedores (Solo entregados para el ranking)
$sql_v = "SELECT creado_por as vend, SUM(total_venta) as total 
          FROM pedidos_activos WHERE YEAR(fecha_despacho) = $year AND LOWER(TRIM(estado)) = 'entregado'
          GROUP BY vend ORDER BY total DESC";
$res_v = $conn->query($sql_v);
while($row = $res_v->fetch_assoc()) {
    $response["vendedores"]["nombres"][] = $row['vend'];
    $response["vendedores"]["ventas"][] = (float)$row['total'];
}

echo json_encode($response);
$conn->close();