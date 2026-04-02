<?php
require_once 'auth.php';
require_once __DIR__ . '/vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. CONEXIÓN
$conn = new mysqli("localhost", "tabolang_app", 'm{Hpj.?IZL$Kz${S', "tabolang_pedidos");
$conn->set_charset("utf8mb4");

// 2. LÓGICA DE FECHA DINÁMICA (Próximo Viernes)
function obtenerFechaVigencia() {
    // strtotime('next Friday') busca automáticamente el viernes siguiente a la fecha actual
    $proximoViernes = strtotime('next Friday');
    
    // Extraemos el día, mes y año de ese próximo viernes
    $diaFin = date('j', $proximoViernes); // Día del mes sin ceros iniciales
    $mesFin = (int)date('n', $proximoViernes); // Mes sin ceros iniciales
    $anioActual = date('Y', $proximoViernes); // Año de 4 dígitos
    
    $meses = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    
    return "$diaFin de " . $meses[$mesFin] . " de $anioActual";
}
$fechaVigencia = obtenerFechaVigencia();

// 3. CONSULTA SQL
$sql = "SELECT p.*, 
        (CASE WHEN p.precio_actual > 0 THEN p.precio_actual ELSE IFNULL(p.precio_por_kilo, 0) END) AS precio_neto,
        MAX(CASE WHEN pc.id_categoria_cliente = 2 THEN pc.precio END) AS precio_100,
        MAX(CASE WHEN pc.id_categoria_cliente = 1 THEN pc.precio END) AS precio_200
        FROM productos p
        LEFT JOIN productos_precios_categorias pc ON p.id_producto = pc.id_producto
        WHERE p.activo = 1 
        GROUP BY p.id_producto 
        ORDER BY p.producto ASC";

$res = $conn->query($sql);

// 4. HTML
$html = '
<!DOCTYPE html>
<html>
<head>
<style>
    @page { margin: 100px 50px; }
    header { position: fixed; top: -75px; left: 0; right: 0; height: 70px; border-bottom: 2px solid #0f4b29; }
    footer { position: fixed; bottom: -60px; left: 0; right: 0; height: 50px; text-align: center; font-size: 9px; color: #777; border-top: 1px solid #eee; padding-top: 10px; }
    body { font-family: "Helvetica", sans-serif; color: #333; font-size: 11px; }
    .logo-box img { height: 55px; float: left; }
    .info-header { float: right; text-align: right; color: #0f4b29; margin-top: 5px; }
    .main-title { text-align: center; margin-top: 15px; }
    .main-title h1 { color: #0f4b29; font-size: 18px; margin: 0; text-transform: uppercase; }
    .vigencia-text { color: #666; font-size: 10px; margin-top: 4px; font-style: italic; }
    
    .promo-box { background: #f4f7f5; border: 1px solid #e1e8e3; border-left: 6px solid #0f4b29; padding: 12px; margin: 20px 0; border-radius: 4px; }
    .promo-box strong { color: #0f4b29; }

    .cta-pedido { background: #0f4b29; color: white; padding: 8px; text-align: center; border-radius: 5px; margin: 10px 0; font-weight: bold; font-size: 12px; text-decoration: none; display: block; }

    table { width: 100%; border-collapse: collapse; }
    th { background-color: #0f4b29; color: white; padding: 10px 5px; text-align: left; font-size: 9px; text-transform: uppercase; }
    td { padding: 8px 5px; border-bottom: 1px solid #eee; vertical-align: middle; }
    tr:nth-child(even) { background-color: #fafafa; }
    
    .price-neto { font-weight: bold; color: #0f4b29; font-size: 11px; }
    .prod-icon { width: 16px; height: 16px; margin-right: 8px; vertical-align: middle; }

    .condiciones-pago { margin-top: 25px; font-size: 10px; border-top: 1px dashed #ccc; padding-top: 10px; }
    .condiciones-pago ul { list-style: none; padding: 0; margin: 0; }
    .condiciones-pago li { margin-bottom: 4px; color: #444; }
    .condiciones-pago .highlight { color: #831100; font-weight: bold; }
</style>
</head>
<body>

<header>
    <div class="logo-box"><img src="https://tabolango.cl/media/logo_tabolango.png"></div>
    <div class="info-header"><strong>TABOLANGO SpA</strong><br>Lista Mayorista de Precios</div>
</header>

<footer>La Ensenada, Tabolango, Limache, Chile | ventas@tabolango.cl | www.tabolango.cl</footer>

<div class="main-title">
    <h1>Catálogo de Productos</h1>
    <div class="vigencia-text">Precios Netos válidos hasta el ' . $fechaVigencia . '</div>
</div>

<div class="promo-box">
    <strong>VOLUMEN POR PEDIDO:</strong><br>
    Los descuentos por tramos se calculan sumando las unidades de <strong>todos</strong> los productos de su pedido.<br>
    <small>Ejemplo: 250kg Tomates + 250kg Paltas = Aplica automáticamente precio tramo > 500 Unidades.</small>
</div>

<a href="https://wa.me/56962751651" class="cta-pedido">SOLICITA TU PEDIDO AL WHATSAPP: +56 9 6275 1651</a>

<table>
    <thead>
        <tr>
            <th width="42%">Descripción del Producto</th>
            <th align="center">Calibre</th>
            <th align="center">Formato</th>
            <th align="right">P. Neto Lista</th>
            <th align="right">> 100 Unid.</th>
            <th align="right">> 500 Unid.</th>
        </tr>
    </thead>
    <tbody>';

while($row = $res->fetch_assoc()) {
    // 1. Obtener Datos Básicos
    $nombre_producto = $row['producto']; // Ej: "Lechuga"
    
    // 2. Obtener Variedad (Asegurando que la variable exista)
    // Nota: Asegúrate que tu columna en la BD se llame 'variedad'
    $variedad = isset($row['variedad']) ? $row['variedad'] : ''; // Ej: "Escarola"
    
    // 3. Concatenación Lógica
    // Si hay variedad, unimos: "Producto" + " " + "Variedad"
    if (!empty($variedad)) {
        $nombre_completo = $nombre_producto . ' ' . $variedad;
    } else {
        $nombre_completo = $nombre_producto;
    }

    // 4. Convertir a Mayúsculas para mostrar
    $nombre_display = mb_strtoupper($nombre_completo);
    
    // 5. Calibre (Limpio, sin modificaciones)
    $calibre_display = ($row['calibre'] ?: '-');
    
    // Lógica de Icono (Se mantiene igual, usando solo la primera palabra)
    $slug = strtolower(explode(' ', trim($nombre_producto))[0]); 
    $url_icon = "https://tabolango.cl/media/iconos/{$slug}.png";

    // Formateo de precios
    $p_neto = number_format($row['precio_neto'], 0, ',', '.');
    $p_100  = ($row['precio_100'] > 0) ? '$' . number_format($row['precio_100'], 0, ',', '.') : '<span style="color:#ccc">---</span>';
    $p_200  = ($row['precio_200'] > 0) ? '$' . number_format($row['precio_200'], 0, ',', '.') : '<span style="color:#ccc">---</span>';

    // Generar la fila HTML
    $html .= '
        <tr>
            <td>
                <img src="'.$url_icon.'" class="prod-icon" onerror="this.style.display=\'none\'">
                <strong>' . $nombre_display . '</strong>
            </td>
            <td align="center">' . $calibre_display . '</td>
            <td align="center">' . ($row['formato'] ?: '-') . '</td>
            <td align="right" class="price-neto">$' . $p_neto . '</td>
            <td align="right">' . $p_100 . '</td>
            <td align="right">' . $p_200 . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>

<div class="condiciones-pago">
    <strong>CONDICIONES COMERCIALES:</strong>
    <ul>
    <br>
        <li>* Condición de pago: <span class="highlight">Al contado</span>. Si hay facturas impagas no se despacha el siguiente pedido.</li>
        <li>* Opción de crédito se podrá evaluar con una relación comercial estable.</li>
        <li>* Todo pedido se factura el mismo día o al día siguiente del despacho.</li>
        <li>* Precio incluye despacho dentro del circulo urbano en RM y en las comunas de Concon, Viña del Mar, Quillota, Limache. Otras zonas consultar precio despacho.</li>
    </ul>
</div>

</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true); 
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Lista_Precios_Tabolango.pdf", ["Attachment" => false]);
exit;