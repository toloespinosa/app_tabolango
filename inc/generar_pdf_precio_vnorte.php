<?php
require_once 'auth.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

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

// 3. CONSULTA SQL MODIFICADA PARA V. NORTE
// Necesitamos: P4, Precio Base, P2
$sql = "SELECT p.*, 
        p.precio_actual AS precio_base,
        MAX(CASE WHEN pc.id_categoria_cliente = 4 THEN pc.precio END) AS precio_p4,
        MAX(CASE WHEN pc.id_categoria_cliente = 2 THEN pc.precio END) AS precio_p2
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
    header { position: fixed; top: -75px; left: 0; right: 0; height: 70px; border-bottom: 2px solid #2c3e50; } /* Borde Azul Oscuro */
    footer { position: fixed; bottom: -60px; left: 0; right: 0; height: 50px; text-align: center; font-size: 9px; color: #777; border-top: 1px solid #eee; padding-top: 10px; }
    body { font-family: "Helvetica", sans-serif; color: #333; font-size: 11px; }
    .logo-box img { height: 55px; float: left; }
    .info-header { float: right; text-align: right; color: #2c3e50; margin-top: 5px; }
    .main-title { text-align: center; margin-top: 15px; }
    .main-title h1 { color: #2c3e50; font-size: 18px; margin: 0; text-transform: uppercase; }
    .vigencia-text { color: #666; font-size: 10px; margin-top: 4px; font-style: italic; }
    
    /* Promo box azulado para diferenciar */
    .promo-box { background: #f4f6f7; border: 1px solid #d5dbdb; border-left: 6px solid #2c3e50; padding: 12px; margin: 20px 0; border-radius: 4px; }
    .promo-box strong { color: #2c3e50; }

    .cta-pedido { background: #2c3e50; color: white; padding: 8px; text-align: center; border-radius: 5px; margin: 10px 0; font-weight: bold; font-size: 12px; text-decoration: none; display: block; }

    table { width: 100%; border-collapse: collapse; }
    th { background-color: #2c3e50; color: white; padding: 10px 5px; text-align: left; font-size: 9px; text-transform: uppercase; }
    td { padding: 8px 5px; border-bottom: 1px solid #eee; vertical-align: middle; }
    tr:nth-child(even) { background-color: #fafafa; }
    
    .price-neto { font-weight: bold; color: #2c3e50; font-size: 11px; }
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
    <div class="info-header"><strong>TABOLANGO SpA</strong><br>Lista Mayorista V. Norte</div>
</header>

<footer>La Ensenada, Tabolango, Limache, Chile | ventas@tabolango.cl | www.tabolango.cl</footer>

<div class="main-title">
    <h1>Catálogo de Productos - V. Norte</h1>
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
            <th align="right">P. Lista Neto</th>
            <th align="right">>100 Unid.</th>
            <th align="right">>500 Unid.</th>
        </tr>
    </thead>
    <tbody>';

while($row = $res->fetch_assoc()) {
    // 1. Datos Básicos
    $nombre_producto = $row['producto']; 
    $variedad = isset($row['variedad']) ? $row['variedad'] : ''; 
    
    if (!empty($variedad)) {
        $nombre_completo = $nombre_producto . ' ' . $variedad;
    } else {
        $nombre_completo = $nombre_producto;
    }

    $nombre_display = mb_strtoupper($nombre_completo);
    $calibre_display = ($row['calibre'] ?: '-');
    $slug = strtolower(explode(' ', trim($nombre_producto))[0]); 
    $url_icon = "https://tabolango.cl/media/iconos/{$slug}.png";

    // --- AQUÍ ESTÁ EL CAMBIO SOLICITADO ---
    // Columna 1: Precio P4
    $val_p4   = $row['precio_p4'];
    // Columna 2: Precio Base (Lista Original)
    $val_base = $row['precio_base'];
    // Columna 3: Precio P2
    $val_p2   = $row['precio_p2'];

    // Formateo
    $p_col1 = ($val_p4 > 0)   ? '$' . number_format($val_p4, 0, ',', '.')   : '<span style="color:#ccc">---</span>';
    $p_col2 = ($val_base > 0) ? '$' . number_format($val_base, 0, ',', '.') : '<span style="color:#ccc">---</span>';
    $p_col3 = ($val_p2 > 0)   ? '$' . number_format($val_p2, 0, ',', '.')   : '<span style="color:#ccc">---</span>';

    $html .= '
        <tr>
            <td>
                <img src="'.$url_icon.'" class="prod-icon" onerror="this.style.display=\'none\'">
                <strong>' . $nombre_display . '</strong>
            </td>
            <td align="center">' . $calibre_display . '</td>
            <td align="center">' . ($row['formato'] ?: '-') . '</td>
            
            <td align="right" class="price-neto">' . $p_col1 . '</td>
            <td align="right">' . $p_col2 . '</td>
            <td align="right">' . $p_col3 . '</td>
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
        <li>* Precio incluye despacho dentro en las zonas de Maitencillo, Zapallar, Cachagua, Puchuncaví. Otras zonas consultar precio despacho.</li>
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
$dompdf->stream("Lista_de_Precios_V_Norte.pdf", ["Attachment" => false]);
exit;