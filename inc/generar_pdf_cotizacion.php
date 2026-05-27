<?php
require_once 'auth.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$cliente_id     = intval($_POST['cliente_id'] ?? 0);
$productos_json = $_POST['productos'] ?? '[]';
$notas          = htmlspecialchars(trim($_POST['notas'] ?? ''), ENT_QUOTES);
$ocultar_total  = ($_POST['ocultar_total'] ?? '0') === '1';
$productos_cot  = json_decode($productos_json, true) ?? [];

if (empty($productos_cot)) {
    echo "Error: No se recibieron productos."; exit;
}

// Obtener datos del cliente por id_interno (PK real de la tabla)
$cliente = null;
if ($cliente_id > 0) {
    $stmt = $conn->prepare("SELECT cliente, razon_social, rut_cliente, email, telefono, direccion, ciudad, comuna FROM clientes WHERE id_interno = ?");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fechas — vigencia al próximo viernes
function proximoViernes() {
    $ts  = strtotime('next Friday');
    $dia = date('j', $ts);
    $mes = (int)date('n', $ts);
    $ano = date('Y', $ts);
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    return "$dia de {$meses[$mes]} de $ano";
}

$num_cot        = 'COT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
$fecha_emision  = date('d/m/Y');
$fecha_vigencia = proximoViernes();

// Totales
$total_neto = 0;
foreach ($productos_cot as $p) {
    $total_neto += floatval($p['precio']) * intval($p['cantidad']);
}
$iva       = round($total_neto * 0.19);
$total_iva = $total_neto + $iva;

function clp($v) {
    return '$' . number_format($v, 0, ',', '.');
}

// Filas de productos
$filas_html = '';
$i = 1;
foreach ($productos_cot as $p) {
    $nombre_raw = $p['nombre'] ?? '';
    $nombre     = htmlspecialchars($nombre_raw);
    $calibre    = htmlspecialchars($p['calibre'] ?? '-');
    $unidad     = htmlspecialchars($p['unidad']  ?? '-');
    $formato    = htmlspecialchars($p['formato'] ?? '-');
    $precio     = floatval($p['precio']);
    $cantidad   = intval($p['cantidad']);
    $subtotal   = $precio * $cantidad;
    $bg         = ($i % 2 === 0) ? '#f7faf8' : '#ffffff';

    // Ícono: primera palabra del nombre en minúsculas → PNG remoto
    $slug     = strtolower(explode(' ', trim($nombre_raw))[0]);
    $url_icon = "https://tabolango.cl/media/iconos/{$slug}.png";

    $filas_html .= "
        <tr style='background:{$bg};'>
            <td style='text-align:center; color:#aaa; font-size:10px;'>{$i}</td>
            <td>
                <div style='display:flex; align-items:center; gap:0;'>
                    <img src='{$url_icon}' class='prod-icon'>
                    <strong style='color:#1a1a1a;'>{$nombre}</strong>
                </div>
            </td>
            <td style='text-align:center; color:#666;'>{$calibre}</td>
            <td style='text-align:center;'><span style='background:#e8f5e9; color:#0f4b29; font-size:9px; font-weight:700; padding:2px 7px; border-radius:4px;'>{$unidad}</span></td>
            <td style='text-align:center; color:#666;'>{$formato}</td>
            <td style='text-align:right; color:#0f4b29; font-weight:700;'>" . clp($precio) . "</td>
            <td style='text-align:center; font-weight:700;'>{$cantidad}</td>
            <td style='text-align:right; font-weight:800; font-size:12px;'>" . clp($subtotal) . "</td>
        </tr>";
    $i++;
}

// Datos del cliente
$nombre_cliente = 'Sin cliente especificado';
$rut_html = $dir_html = $contacto_html = '';

if ($cliente) {
    $nombre_cliente = htmlspecialchars($cliente['razon_social'] ?: $cliente['cliente']);
    if (!empty($cliente['rut_cliente']))
        $rut_html = '<div class="sub">RUT: ' . htmlspecialchars($cliente['rut_cliente']) . '</div>';
    $dir = trim(($cliente['direccion'] ?? '') . ($cliente['ciudad'] ? ', ' . $cliente['ciudad'] : ''));
    if ($dir)
        $dir_html = '<div class="sub">' . htmlspecialchars($dir) . '</div>';
    $contacto = trim(($cliente['email'] ?? '') . ($cliente['telefono'] ? '  |  ' . $cliente['telefono'] : ''));
    if ($contacto)
        $contacto_html = '<div class="sub">' . htmlspecialchars($contacto) . '</div>';
}

$bloque_notas = $notas
    ? "<tr><td colspan='2' style='padding:12px 14px; background:#fffbe6; border-top:1px solid #ffe58f; font-size:10px; color:#555;'><strong>Notas:</strong> " . nl2br($notas) . "</td></tr>"
    : '';

$html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 95px 45px 70px 45px; }

    header { position:fixed; top:-72px; left:0; right:0; height:65px; border-bottom:2px solid #0f4b29; }
    footer  { position:fixed; bottom:-55px; left:0; right:0; height:45px; border-top:1px solid #eee; text-align:center; font-size:8.5px; color:#999; padding-top:8px; }

    body { font-family:"Helvetica Neue","Helvetica",Arial,sans-serif; font-size:11px; color:#333; margin:0; }

    /* ── Cabecera ── */
    .logo-box img   { height:50px; float:left; }
    .hdr-right      { float:right; text-align:right; font-size:11px; color:#0f4b29; margin-top:6px; line-height:1.5; }
    .hdr-right b    { font-size:13px; }
    .cf::after      { content:""; display:table; clear:both; }

    /* ── Título ── */
    .doc-banner     { background:#0f4b29; color:#fff; text-align:center; padding:14px 0 10px; border-radius:6px; margin:0 0 16px 0; }
    .doc-banner h1  { margin:0; font-size:20px; letter-spacing:3px; text-transform:uppercase; }
    .doc-banner .num { font-size:10px; color:rgba(255,255,255,0.7); margin-top:4px; letter-spacing:1px; }

    /* ── Info boxes ── */
    .info-row       { width:100%; border-collapse:collapse; margin-bottom:16px; }
    .info-row td    { vertical-align:top; padding:0; }
    .info-box       { border:1px solid #e5e5e5; border-radius:6px; padding:11px 13px; background:#fafafa; }
    .info-box .ttl  { font-size:8.5px; text-transform:uppercase; color:#aaa; font-weight:700; letter-spacing:0.5px; margin-bottom:4px; }
    .info-box .main { font-size:12.5px; font-weight:800; color:#1a1a1a; margin-bottom:3px; }
    .info-box .sub  { font-size:10px; color:#666; line-height:1.5; }
    .badge          { display:inline-block; background:#e8f5e9; color:#0f4b29; border:1px solid #c8e6c9; font-size:9.5px; font-weight:700; padding:3px 10px; border-radius:4px; margin-top:5px; }

    /* ── Tabla productos ── */
    table.pt        { width:100%; border-collapse:collapse; }
    table.pt th     { background:#0f4b29; color:#fff; padding:9px 8px; font-size:8.5px; text-transform:uppercase; letter-spacing:0.5px; }
    table.pt td     { padding:9px 8px; border-bottom:1px solid #eff0f0; }
    table.pt tbody tr:last-child td { border-bottom:none; }
    .prod-icon      { width:20px; height:20px; margin-right:7px; vertical-align:middle; }

    /* ── Bloque totales + notas ── */
    .bottom-row     { width:100%; border-collapse:collapse; margin-top:16px; }
    .bottom-row td  { vertical-align:top; padding:0; }
    .notas-box      { border:1px solid #ffe58f; border-radius:6px; padding:11px 13px; background:#fffbe6; font-size:10px; color:#555; }
    .notas-box b    { color:#333; }

    .totales-box    { border:1px solid #e5e5e5; border-radius:6px; overflow:hidden; }
    table.tt        { width:100%; border-collapse:collapse; font-size:11px; }
    table.tt td     { padding:7px 12px; }
    table.tt .lbl   { color:#555; }
    table.tt .val   { text-align:right; font-weight:700; }
    table.tt .sub-r { color:#999; font-size:10px; }
    table.tt .grand { background:#0f4b29; color:#fff; font-weight:900; font-size:14px; }
    table.tt .grand .val { font-size:14px; }
    table.tt tr + tr td { border-top:1px solid #f0f0f0; }
    table.tt .grand td  { border-top:none; }

    /* ── Condiciones ── */
    .conds          { margin-top:22px; padding-top:10px; border-top:1px dashed #ddd; font-size:9px; color:#666; }
    .conds ul       { margin:5px 0 0 0; padding-left:14px; }
    .conds li       { margin-bottom:3px; }
</style>
</head>
<body>

<header>
    <div class="cf">
        <div class="logo-box"><img src="https://tabolango.cl/media/logo_tabolango.png"></div>
        <div class="hdr-right"><b>TABOLANGO SpA</b><br>RUT: 77.121.854-7 &nbsp;|&nbsp; La Ensenada, Limache</div>
    </div>
</header>

<footer>ventas@tabolango.cl &nbsp;|&nbsp; +56 9 6275 1651 &nbsp;|&nbsp; www.tabolango.cl &nbsp;|&nbsp; La Ensenada, Tabolango, Limache, Chile</footer>

<div class="doc-banner">
    <h1>Cotización</h1>
    <div class="num">' . $num_cot . ' &nbsp;·&nbsp; Emitida el ' . $fecha_emision . '</div>
</div>

<table class="info-row">
    <tr>
        <td width="56%" style="padding-right:8px;">
            <div class="info-box">
                <div class="ttl">Cliente</div>
                <div class="main">' . $nombre_cliente . '</div>
                ' . $rut_html . $dir_html . $contacto_html . '
            </div>
        </td>
        <td width="44%" style="padding-left:8px;">
            <div class="info-box">
                <div class="ttl">Vigencia</div>
                <div class="main">Válido hasta el viernes</div>
                <div class="badge">' . $fecha_vigencia . '</div>
                <div class="sub" style="margin-top:6px;">Precios netos — no incluyen IVA (19%)</div>
            </div>
        </td>
    </tr>
</table>

<table class="pt">
    <thead>
        <tr>
            <th width="4%" style="text-align:center;">#</th>
            <th width="42%">Producto</th>
            <th width="8%" style="text-align:center;">Calibre</th>
            <th width="8%" style="text-align:center;">Unidad</th>
            <th width="9%" style="text-align:center;">Formato</th>
            <th width="14%" style="text-align:right;">Precio Neto</th>
            <th width="7%" style="text-align:center;">Cant.</th>
            <th width="13%" style="text-align:right;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        ' . $filas_html . '
    </tbody>
</table>

' . ($ocultar_total
    ? ($notas
        ? '<div class="notas-box" style="margin-top:16px;"><b>Notas:</b><br>' . nl2br($notas) . '</div>'
        : '')
    : '<table class="bottom-row" style="margin-top:16px;">
    <tr>
        <td width="52%" style="padding-right:12px; vertical-align:top;">
            ' . ($notas ? '<div class="notas-box"><b>Notas:</b><br>' . nl2br($notas) . '</div>' : '') . '
        </td>
        <td width="48%" style="vertical-align:top;">
            <div class="totales-box">
                <table class="tt">
                    <tr>
                        <td class="lbl sub-r">Subtotal Neto</td>
                        <td class="val sub-r">' . clp($total_neto) . '</td>
                    </tr>
                    <tr>
                        <td class="lbl sub-r">IVA 19%</td>
                        <td class="val sub-r">' . clp($iva) . '</td>
                    </tr>
                    <tr class="grand">
                        <td class="lbl" style="padding:10px 12px; color: white;">TOTAL</td>
                        <td class="val" style="padding:10px 12px;">' . clp($total_iva) . '</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>') . '

<div class="conds">
    <strong>Condiciones comerciales:</strong>
    <ul>
        <li>Esta cotización es válida hasta el viernes de la semana en curso (' . $fecha_vigencia . ').</li>
        <li>Condición de pago: al contado. Con facturas impagas no se despacha el siguiente pedido.</li>
        <li>Precio incluye despacho en RM y comunas de Concon, Viña del Mar, Quillota y Limache. Otras zonas consultar.</li>
        <li>Todo pedido se factura el mismo día o al día siguiente del despacho.</li>
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
$dompdf->stream("Cotizacion_Tabolango_" . date('Ymd') . ".pdf", ["Attachment" => false]);
exit;
