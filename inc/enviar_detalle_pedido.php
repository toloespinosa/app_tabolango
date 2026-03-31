<?php
// enviar_detalle_pedido.php - V5: AUTOLOAD BLINDADO, RUTAS PUBLIC_HTML Y CREDENCIALES EXACTAS WP-CONFIG

mb_internal_encoding("UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

// --- 1. BLINDAJE DEL AUTOLOAD ---
$rutas_posibles = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',       
    __DIR__ . '/../../vendor/autoload.php',    
    __DIR__ . '/autoload.php'
];
$autoload_encontrado = false;
foreach ($rutas_posibles as $ruta) { 
    if (file_exists($ruta)) { require_once $ruta; $autoload_encontrado = true; break; } 
}
if (!$autoload_encontrado) {
    echo json_encode(["status" => "error", "message" => "Falta autoload.php"]);
    exit;
}

use Twilio\Rest\Client;
use Dompdf\Dompdf;
use Dompdf\Options;

// --- 2. CARGAR WP-CONFIG PARA LAS CREDENCIALES ---
// Buscamos la raíz de WordPress dinámicamente cortando en 'wp-content'
$wp_root = substr(__DIR__, 0, strpos(__DIR__, 'wp-content'));
if (file_exists($wp_root . 'wp-config.php')) {
    require_once $wp_root . 'wp-config.php';
} else {
    echo json_encode(["status" => "error", "message" => "No se encontró wp-config.php para leer las credenciales."]);
    exit;
}

// 3. CONEXIÓN BD
$conn = new mysqli("localhost", "tabolang_app", 'm{Hpj.?IZL$Kz${S', "tabolang_pedidos");
$conn->set_charset("utf8mb4");

// --- 4. CONFIGURACIÓN TWILIO (Usando exactamente tus variables de wp-config) ---
$sid    = TWILIO_ACCOUNT_SID;
$token  = TWILIO_AUTH_TOKEN; 
$twilio = new Client($sid, $token);

$templateSid = "HXb9760d3c2d270c4e262ac09eb73d8794"; 
// --------------------------------------------------------------------------------

// 5. RECEPCIÓN DE DATOS
$telefonoCliente = isset($_POST['telefono']) ? $_POST['telefono'] : ''; 
$idPedido        = isset($_POST['id_pedido']) ? $_POST['id_pedido'] : '0'; 
$nombreSaludo    = isset($_POST['nombre']) ? $_POST['nombre'] : '';  

try {
    if (empty($idPedido) || $idPedido === '0') throw new Exception("ID del pedido vacío.");
    if (empty($telefonoCliente)) throw new Exception("Teléfono vacío.");

    $telefonoCliente = preg_replace('/[^0-9+]/', '', $telefonoCliente);
    if (strpos($telefonoCliente, '+') === false && strlen($telefonoCliente) >= 11) {
        $telefonoCliente = "+" . $telefonoCliente;
    }

    $nombreLimpio = trim($nombreSaludo);
    $variableSaludo = (!empty($nombreLimpio) && strtolower($nombreLimpio) !== 'cliente') ? " " . $nombreLimpio : " amigo/a"; 

    // 6. CONSULTA
    $sqlDetalle = "SELECT 
                        pa.cliente, pa.producto, pa.cantidad, pa.precio_unitario,
                        p.variedad, p.calibre, p.formato, p.unidad
                   FROM pedidos_activos pa 
                   LEFT JOIN productos p ON pa.id_producto = p.id_producto 
                   WHERE pa.id_pedido = '$idPedido'";
    $resDetalle = $conn->query($sqlDetalle);
    if (!$resDetalle) throw new Exception("Error BD: " . $conn->error);

    $filasResultados = [];
    $cliente_pdf = "Cliente";
    
    while($row = $resDetalle->fetch_assoc()) {
        if (empty($filasResultados)) {
            $cliente_pdf = mb_convert_encoding(trim($row['cliente']), 'UTF-8', 'auto');
        }
        $filasResultados[] = $row;
    }

    $suma_neto = 0;
    
    // 7. CONSTRUCCIÓN DEL HTML PARA EL PDF
    $htmlPDF = '
    <html><head><style>
        body { font-family: "Helvetica", sans-serif; color: #333; font-size: 15px; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 35px; border-bottom: 2px solid #0f4b29; padding-bottom: 20px; }
        .header-table td { border: none; padding: 0; vertical-align: middle; }
        .title { color: #0f4b29; margin: 0; font-size: 28px; text-transform: uppercase; font-weight: 900; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .items-table th { background-color: #0f4b29; color: white; padding: 14px 10px; text-align: left; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .items-table td { padding: 18px 10px; border-bottom: 1px solid #eee; font-size: 16px; vertical-align: middle; }
        .sub-text { color: #666; font-size: 13px; margin-top: 4px; display: block; }
        .prod-icon { width: 32px; height: 32px; vertical-align: middle; margin-right: 12px; }
        .prod-name-text { vertical-align: middle; font-size: 18px; color:#111; font-weight: bold; }
        .totales-table { width: 350px; border-collapse: collapse; margin-top: 40px; font-size: 17px; float: right; }
        .totales-table td { padding: 12px 0; border: none; text-align: left; color: #555; }
        .totales-table .amount { text-align: right; color: #111; font-weight: bold; }
        .totales-table .iva-row { border-bottom: 2px solid #ddd; padding-bottom: 15px; }
        .totales-table .total-label { padding-top: 15px; font-size: 22px; color: #0f4b29; font-weight: 900; }
        .totales-table .total-amount { padding-top: 15px; font-size: 24px; color: #0f4b29; font-weight: 900; text-align: right; }
    </style></head><body>
    
    <table class="header-table">
        <tr>
            <td style="width: 50%; text-align: left;">
                <img src="https://tabolango.cl/media/logo_tabolango.png" style="height: 80px;">
            </td>
            <td style="width: 50%; text-align: right;">
                <h1 class="title">Detalle de Pedido</h1>
                <div style="font-size: 18px; margin-top: 8px; color: #111;"><strong>Orden N° ' . $idPedido . '</strong></div>
                <div style="font-size: 16px; margin-top: 6px; color: #0f4b29;"><strong>Cliente:</strong> ' . $cliente_pdf . '</div>
                <div style="color: #666; font-size: 14px; margin-top: 4px;">Emitido: ' . date('d/m/Y H:i') . '</div>
            </td>
        </tr>
    </table>
    
    <table class="items-table">
        <thead>
            <tr>
                <th width="45%">Producto</th>
                <th width="25%">Calibre / Formato</th>
                <th width="15%" align="center">Cantidad</th>
                <th width="15%" align="right">Total</th>
            </tr>
        </thead>
        <tbody>';

    foreach($filasResultados as $row) {
        $nombre_base = trim($row['producto']);
        $nombre_prod = mb_convert_encoding($nombre_base, 'UTF-8', 'auto');
        $variedad    = mb_convert_encoding(trim($row['variedad'] ?? ''), 'UTF-8', 'auto');
        $calibre     = mb_convert_encoding(trim($row['calibre'] ?? '-'), 'UTF-8', 'auto');
        $formato     = mb_convert_encoding(trim($row['formato'] ?? '-'), 'UTF-8', 'auto');
        $unidad_txt  = mb_convert_encoding(trim($row['unidad'] ?? 'Unid'), 'UTF-8', 'auto'); 
        
        $slug = strtolower(explode(' ', $nombre_base)[0]); 
        $url_icon = "https://tabolango.cl/media/iconos/{$slug}.png";

        if (!empty($variedad)) { 
            $nombre_prod .= " <span class='sub-text'>($variedad)</span>"; 
        }

        $cantidad = floatval($row['cantidad']);
        $precio_u = floatval($row['precio_unitario']);
        $total_linea = $cantidad * $precio_u;
        $suma_neto  += $total_linea; 
        
        $unidad_minuscula = strtolower($unidad_txt);
        if ($cantidad > 1) {
            if ($unidad_minuscula === 'caja') {
                $unidad_txt = 'cajas';
            } elseif ($unidad_minuscula === 'bandeja') {
                $unidad_txt = 'bandejas';
            } elseif ($unidad_minuscula === 'malla') { 
                $unidad_txt = 'mallas';
            }
        }
        $unidad_txt = ucfirst(strtolower($unidad_txt)); 

        $htmlPDF .= '
            <tr>
                <td>
                    <img src="' . $url_icon . '" class="prod-icon">
                    <span class="prod-name-text">' . $nombre_prod . '</span>
                </td>
                <td><span style="color:#444;">' . $calibre . ' | ' . $formato . '</span></td>
                <td align="center"><strong style="font-size: 18px;">' . $cantidad . ' ' . $unidad_txt . '</strong></td>
                <td align="right" style="font-weight:bold; font-size: 16px;">$' . number_format($total_linea, 0, ',', '.') . '</td>
            </tr>';
    }

    $iva_calculado = round($suma_neto * 0.19);
    $total_final   = $suma_neto + $iva_calculado;

    $strSubtotal = "$" . number_format($suma_neto, 0, ',', '.');
    $strIva      = "$" . number_format($iva_calculado, 0, ',', '.');
    $strTotal    = "$" . number_format($total_final, 0, ',', '.');

    $htmlPDF .= '</tbody></table>';
    
    // BLOQUE DE TOTALES
    $htmlPDF .= '
    <table class="totales-table" align="right">
        <tr>
            <td>Subtotal Neto:</td>
            <td class="amount">' . $strSubtotal . '</td>
        </tr>
        <tr>
            <td class="iva-row">IVA (19%):</td>
            <td class="amount iva-row">' . $strIva . '</td>
        </tr>
        <tr>
            <td class="total-label">TOTAL:</td>
            <td class="total-amount">' . $strTotal . '</td>
        </tr>
    </table>
    <div style="clear: both;"></div>
    </body></html>';

    // --- 8. GENERACIÓN Y GUARDADO DEL PDF EN CARPETA CENTRAL (PUBLIC_HTML) ---
    $host_actual = $_SERVER['HTTP_HOST'] ?? '';
    $ruta_raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

    if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
        $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
    } else {
        $ruta_public = $ruta_raiz; 
    }

    $ruta_base_uploads = rtrim($ruta_public, '/') . '/uploads/';
    $rutaCarpeta = $ruta_base_uploads . 'pedidos/';
    // --------------------------------------------------------------------------
    
    if (!file_exists($rutaCarpeta)) { mkdir($rutaCarpeta, 0755, true); }
    
    $options = new Options();
    $options->set('isRemoteEnabled', true); 
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($htmlPDF);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $nombreArchivoPDF = "Pedido_" . $idPedido . "_" . time() . ".pdf";
    file_put_contents($rutaCarpeta . $nombreArchivoPDF, $dompdf->output());

    // 9. ENVÍO A TWILIO
    $variablesTwilio = [
        "1" => (string)$variableSaludo,
        "2" => (string)$idPedido,
        "3" => (string)$nombreArchivoPDF,
        "4" => (string)$strSubtotal,
        "5" => (string)$strIva,
        "6" => (string)$strTotal
    ];

    $message = $twilio->messages->create("whatsapp:" . $telefonoCliente, [
        "from" => "whatsapp:+12178583230",
        "contentSid" => $templateSid, 
        "contentVariables" => json_encode($variablesTwilio, JSON_UNESCAPED_UNICODE)
    ]);

    $conn->query("UPDATE pedidos_activos SET whatsapp_enviado = NOW() WHERE id_pedido = '$idPedido'");

    echo json_encode(["status" => "ok", "message" => "WhatsApp enviado con PDF y Diseño Maximizado", "pdf" => $nombreArchivoPDF]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>