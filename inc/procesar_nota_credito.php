<?php
require_once 'auth.php';
// procesar_nota_credito.php - V16: DOMINIO DINÁMICO Y SINGLETON DB

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = $_SERVER['HTTP_HOST'] ?? '';
$is_local = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, 'ngrok') !== false || strpos($host, '.local') !== false);

$rutas_posibles = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',    // Sube un nivel hacia el root del tema
    __DIR__ . '/../../vendor/autoload.php', // Sube dos niveles por blindaje extra
    __DIR__ . '/autoload.php', 
    __DIR__ . '/librerias/autoload.php'
];
$autoload_encontrado = false;
foreach ($rutas_posibles as $ruta) { 
    if (file_exists($ruta)) { 
        require_once $ruta; 
        $autoload_encontrado = true; 
        break; 
    } 
}
if (!$autoload_encontrado) {
    echo json_encode(["status" => "error", "message" => "Falta autoload.php para generar PDF"]);
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

function cleanStr($str) {
    $str = mb_convert_encoding($str, 'UTF-8', 'auto');
    $unwanted = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','"'=>'','`'=>"","'"=>""];
    return trim(mb_substr(strtr($str, $unwanted), 0, 80));
}

function generarPDFNotaCredito($ruta_destino, $datos, $html_ted_code) {
    extract($datos);
    $fecha_visual = date("d-m-Y");
    $neto_fmt = number_format($monto_neto, 0, ',', '.');
    $iva_fmt = number_format($monto_iva, 0, ',', '.');
    $total_fmt = number_format($monto_total, 0, ',', '.');
    
    $texto_accion = ($tipo_nc === 'parcial') ? 'CORRIGE MONTO FACTURA N° ' : 'ANULA FACTURA N° ';
    
    $filas = '<tr>
                <td style="padding:5px 5px 5px 10px; border-bottom:1px solid #ddd; color:#000;"><strong>'.$texto_accion.$numero_factura.'</strong><br><span style="font-size:10px; font-style:italic;">'.$razon_ref.'</span></td>
                <td style="text-align:right; padding:5px 2px 5px 5px; border-bottom:1px solid #ddd; width:30px; color:#000;">1</td>
                <td style="text-align:left; padding:5px 5px 5px 2px; border-bottom:1px solid #ddd; width:50px; font-size:10px; color:#000;">Unid</td>
                <td style="text-align:right; padding:5px 10px 5px 5px; border-bottom:1px solid #ddd; color:#000;">$'.$neto_fmt.'</td>
                <td style="text-align:right; padding:5px 10px 5px 5px; border-bottom:1px solid #ddd; color:#000;">$'.$neto_fmt.'</td>
              </tr>';

    $html_referencia_bottom = '<div style="margin-top: 15px; border: 1px solid #000; background-color: #f9f9f9; padding: 8px; font-size: 11px; color:#000;">
        <strong>REFERENCIA:</strong>
        <table width="100%" style="margin-top:4px; color:#000;">
            <tr>
                <td width="20%"><strong>Tipo Doc:</strong> Factura Electrónica (33)</td>
                <td width="20%"><strong>Folio:</strong> ' . $numero_factura . '</td>
                <td width="20%"><strong>Fecha:</strong> ' . date("d-m-Y", strtotime($fecha_despacho)) . '</td>
                <td><strong>Razón:</strong> ' . $razon_ref . '</td>
            </tr>
        </table>
    </div>';

    $html = '<html><head><meta charset="UTF-8"><style>@page{margin:15mm 15mm 15mm 15mm;}body{font-family:Helvetica,sans-serif;font-size:11px;color:#000;line-height:1.3;}.header{width:100%;margin-bottom:30px;}.col-left{float:left;width:60%;}.col-right{float:right;width:33%;border:3px solid #CC0000;padding:15px 10px;text-align:center;color:#CC0000;font-weight:bold;}.clear{clear:both;}.logo-img{width:180px;margin-bottom:10px;}.box{border:1px solid #000;padding:5px;margin-bottom:15px;}.box table{width:100%;color:#000;}.items-table{width:100%;border-collapse:collapse;margin-top:10px;color:#000;}.items-table th{background-color:#eee;border:1px solid #000;padding:6px;text-align:left;font-size:10px;font-weight:bold;}.footer{margin-top:30px;}.ted-box{float:left;width:350px;text-align:center;padding-top:10px;}.totals-box{float:right;width:220px;}.total-table{width:100%;border-collapse:collapse;color:#000;}.total-table td{padding:4px;font-size:12px;}.grand-total{border-top:2px solid #000;font-weight:bold;font-size:14px;padding-top:8px !important;}</style></head><body>
    <div class="header">
        <div class="col-left"><img src="https://tabolango.cl/media/logo_tabolango.png" class="logo-img"><br><div style="font-size:14px;font-weight:bold;text-transform:uppercase;">Tabolango SpA</div><div>Giro: COMERCIALIZACION DE HUEVOS</div><div>CAMINO AL VOLCAN 29775, SAN JOSE DE MAIPO</div><div>Email: admin@tabolango.cl</div></div>
        <div class="col-right"><div style="font-size:16px;margin-bottom:8px;">R.U.T.: 77.121.854-7</div><div style="font-size:14px;margin-bottom:8px;background-color:#fff;">NOTA DE CRÉDITO ELECTRÓNICA</div><div style="font-size:16px;margin-bottom:8px;">N° '.$folio_nc.'</div><div style="font-size:11px;color:#CC0000;">S.I.I. - LA FLORIDA</div></div><div class="clear"></div>
    </div>
    <div class="box"><table cellspacing="0" cellpadding="0" border="0"><tr><td width="80"><strong>SE&Ntilde;OR(ES):</strong></td><td>'.$razon_social.'</td><td width="100" align="right"><strong>FECHA:</strong> '.$fecha_visual.'</td></tr><tr><td><strong>RUT:</strong></td><td colspan="2">'.$rut_cliente_fmt.'</td></tr><tr><td><strong>DIRECCI&Oacute;N:</strong></td><td colspan="2">'.$direccion.'</td></tr><tr><td><strong>COMUNA:</strong></td><td colspan="2">'.$comuna.'</td></tr></table></div>
    <table class="items-table"><thead><tr><th width="50%">DESCRIPCI&Oacute;N</th><th width="15%" colspan="2" style="text-align:center;">CANTIDAD</th><th width="15%" style="text-align:right;">PRECIO UNIT.</th><th width="20%" style="text-align:right;">TOTAL</th></tr></thead><tbody>'.$filas.'</tbody></table>
    '.$html_referencia_bottom.'
    <div class="footer"><div class="ted-box">'.$html_ted_code.'</div><div class="totals-box"><table class="total-table"><tr><td>MONTO NETO $</td><td align="right">'.$neto_fmt.'</td></tr><tr><td>IVA (19%) $</td><td align="right">'.$iva_fmt.'</td></tr><tr><td class="grand-total">TOTAL $</td><td class="grand-total" align="right">'.$total_fmt.'</td></tr></table></div><div class="clear"></div></div>
    </body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    file_put_contents($ruta_destino, $dompdf->output());
}

try {
    // --- 1. SISTEMA DE RUTAS CENTRALIZADAS (Homologado con Facturación) ---
    $host_actual = $_SERVER['HTTP_HOST'] ?? '';
    $ruta_raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

    // Si estamos ejecutando desde el subdominio de Producción (ERP)
    if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
        $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
    } else {
        $ruta_public = $ruta_raiz; // LocalWP
    }

    $ruta_base_uploads = rtrim($ruta_public, '/') . '/uploads/';
    $PATH_CERT = $ruta_base_uploads . "certificados/certificado.pfx"; 
    // -----------------------------------------------------------------------

    // 🔥 URL BASE DINÁMICA: Para evitar romper enlaces al migrar al ERP
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $ruta_directorio = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $DOMINIO_BASE = $protocolo . "://" . $_SERVER['HTTP_HOST'] . $ruta_directorio . "/";
    
    $API_KEY = "7165-N580-6393-2899-7690"; 
    $RUT_EMISOR = "77121854-7";
    $RUT_CERTIFICADO = "8201627-9";
    $PASS_CERTIFICADO = "Sofia2020";
    $NUM_RESOLUCION = 80; 
    $FECHA_RESOLUCION = "2014-08-22"; 

    // Imprimimos la ruta en el error por si llega a fallar para depurar al instante
    if (!$is_local && !file_exists($PATH_CERT)) throw new Exception("Error: No se encuentra el certificado en: " . $PATH_CERT);

    // 🔥 USAMOS LA CONEXIÓN HEREDADA DE auth.php 🔥
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Error crítico: Conexión a base de datos perdida.");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id_pedido']) || empty($input['id_pedido'])) {
        throw new Exception("Error: No se recibió un id_pedido válido.");
    }
    $id_pedido = $conn->real_escape_string($input['id_pedido']);
    
    $tipo_nc = isset($input['tipo_nc']) ? $input['tipo_nc'] : 'total';
    $monto_neto_parcial = isset($input['monto_neto_parcial']) ? (int)$input['monto_neto_parcial'] : 0;

    $sql = "SELECT P.*, C.*, SUM(P.precio_unitario * P.cantidad) as neto_db
            FROM pedidos_activos P 
            LEFT JOIN clientes C ON (P.cliente = C.cliente OR P.id_interno_cliente = C.id_interno)
            WHERE P.id_pedido = '$id_pedido' GROUP BY P.id_pedido"; 
    $res = $conn->query($sql);
    if ($res->num_rows === 0) throw new Exception("Error: No se encontró el pedido o cliente.");
    $row = $res->fetch_assoc();

    $neto_original = (int)$row['neto_db'];

    if ($tipo_nc === 'parcial' && $monto_neto_parcial > 0) {
        if ($monto_neto_parcial > $neto_original) {
            throw new Exception("El monto parcial ($monto_neto_parcial) no puede ser mayor al neto original ($neto_original).");
        }
        $monto_neto = $monto_neto_parcial;
        $codigo_referencia = 3; 
        $razon_ref = "CORRIGE MONTO FACTURA POR DESCUENTO/DEVOLUCION";
        $nombre_detalle = "CORRIGE MONTO FACTURA " . $row['numero_factura'];
    } else {
        $monto_neto = $neto_original;
        $codigo_referencia = 1; 
        $razon_ref = "ANULA POR ERROR DE SISTEMA";
        $nombre_detalle = "ANULACION FACTURA " . $row['numero_factura'];
    }

    $monto_iva   = round($monto_neto * 0.19);
    $monto_total = $monto_neto + $monto_iva;

    $sql_f = "SELECT MAX(CAST(folio_sii as UNSIGNED)) as max_h FROM facturas_historial WHERE tipo_documento = 'nota_credito'";
    $row_f = $conn->query($sql_f)->fetch_assoc();
    $max_historial = (int)($row_f['max_h'] ?? 0);

    $sql_p = "SELECT MAX(CAST(numero_nc as UNSIGNED)) as max_p FROM pedidos_activos";
    $row_p = $conn->query($sql_p)->fetch_assoc();
    $max_pedidos = (int)($row_p['max_p'] ?? 0);

    $ultimo_folio_usado = max($max_historial, $max_pedidos);
    if ($ultimo_folio_usado < 19) { $ultimo_folio_usado = 19; }
    $folio_nc = $ultimo_folio_usado + 1; 

    // MODO LOCAL SIMULADO
    if ($is_local) {
        $folio_nc_simulado = 990000 + rand(1, 999); 
        $url_pdf_simulada = "https://tabolango.cl/media/logo_tabolango.png"; 
        $conn->query("UPDATE pedidos_activos SET estado_nota_credito = 'EMITIDA', numero_nc = '$folio_nc_simulado', url_nc = '$url_pdf_simulada' WHERE id_pedido = '$id_pedido'");

        echo json_encode([
            "status" => "success", 
            "message" => "SIMULACIÓN LOCAL: Nota de Crédito Nº $folio_nc_simulado generada sin tocar el SII.", 
            "url_pdf" => $url_pdf_simulada,
            "url_xml" => "#",
            "track_id" => "SIM_TRACK_LOCAL"
        ]);
        exit; 
    }

    $rut_cliente_fmt = $row['rut_cliente'];
    $rut_cliente = str_replace(['.',' '],'',$row['rut_cliente']);

    $dir_certificados = $ruta_base_uploads . "certificados/";
    $PATH_CAF = "";
    $archivos_caf = glob($dir_certificados . "*caf_61*.xml");

    foreach ($archivos_caf as $archivo) {
        $xml_caf = @simplexml_load_file($archivo);
        if ($xml_caf && isset($xml_caf->CAF->DA->RNG)) {
            $desde = (int)$xml_caf->CAF->DA->RNG->D;
            $hasta = (int)$xml_caf->CAF->DA->RNG->H;

            if ($folio_nc >= $desde && $folio_nc <= $hasta) {
                $PATH_CAF = $archivo;
            } elseif ($hasta < $folio_nc) {
                @unlink($archivo);
            }
        }
    }

    if (empty($PATH_CAF)) {
        throw new Exception("Error crítico: No se encontró un archivo CAF válido para emitir el folio $folio_nc. Por favor descargue nuevos folios desde el panel.");
    }

    $payload_dte = [
        "Documento" => [
            "Encabezado" => [
                "IdentificacionDTE" => ["TipoDTE" => 61, "Folio" => $folio_nc, "FechaEmision" => date("Y-m-d"), "FormaPago" => 1],
                "Emisor" => ["Rut" => $RUT_EMISOR, "RazonSocial" => "Tabolango SpA", "Giro" => "COMERCIALIZACION DE HUEVOS", "ActividadEconomica" => [472190], "DireccionOrigen" => "CAMINO AL VOLCAN 29775", "ComunaOrigen" => "SAN JOSE DE MAIPO"],
                "Receptor" => ["Rut" => $rut_cliente, "RazonSocial" => cleanStr($row['razon_social']), "Giro" => cleanStr($row['giro']), "Direccion" => cleanStr($row['direccion']), "Comuna" => cleanStr($row['comuna'])],
                "Totales" => ["MontoNeto" => (int)$monto_neto, "TasaIVA" => 19, "IVA" => (int)$monto_iva, "MontoTotal" => (int)$monto_total]
            ],
            "Detalles" => [["NroLinDet" => 1, "Nombre" => $nombre_detalle, "Cantidad" => 1, "Precio" => (int)$monto_neto, "MontoItem" => (int)$monto_neto]],
            "Referencias" => [["FechaDocumentoReferencia" => date("Y-m-d", strtotime($row['fecha_despacho'])), "TipoDocumento" => 33, "FolioReferencia" => (int)$row['numero_factura'], "CodigoReferencia" => $codigo_referencia, "RazonReferencia" => $razon_ref]]
        ],
        "Certificado" => ["Rut" => $RUT_CERTIFICADO, "Password" => $PASS_CERTIFICADO]
    ];

    $ch1 = curl_init("https://api.simpleapi.cl/api/v1/dte/generar");
    curl_setopt($ch1, CURLOPT_POST, 1);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, [
        'input' => json_encode($payload_dte, JSON_UNESCAPED_UNICODE),
        'files' => new CURLFile($PATH_CERT, 'application/x-pkcs12', 'certificado.pfx'),
        'files2' => new CURLFile($PATH_CAF, 'text/xml', 'caf_61.xml')
    ]);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
    $resp1 = curl_exec($ch1);
    curl_close($ch1);

    if (strpos($resp1, '<?xml') === false) throw new Exception("Paso 1 Falló (API no devolvió XML). RAW: " . $resp1);

    $xml_dte_generado = $resp1;
    $tmp_dte_path = sys_get_temp_dir() . "/dte_61_" . $folio_nc . "_" . time() . ".xml";
    file_put_contents($tmp_dte_path, $xml_dte_generado);

    $payload_sobre = [
        "Certificado" => ["Rut" => $RUT_CERTIFICADO, "Password" => $PASS_CERTIFICADO],
        "Caratula" => [
            "RutEmisor" => str_replace('.', '', $RUT_EMISOR), 
            "RutReceptor" => "60803000-K", 
            "FechaResolucion" => $FECHA_RESOLUCION, 
            "NumeroResolucion" => $NUM_RESOLUCION
        ]
    ];

    $ch2 = curl_init("https://api.simpleapi.cl/api/v1/envio/generar");
    curl_setopt($ch2, CURLOPT_POST, 1);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, [
        'input' => json_encode($payload_sobre, JSON_UNESCAPED_UNICODE),
        'files' => new CURLFile($PATH_CERT, 'application/x-pkcs12', 'certificado.pfx'),
        'files2' => new CURLFile($tmp_dte_path, 'text/xml', 'dte.xml')
    ]);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
    $resp2 = curl_exec($ch2);
    curl_close($ch2);

    if (strpos($resp2, 'EnvioDTE') === false) {
        @unlink($tmp_dte_path);
        throw new Exception("Paso 2 Falló (Fallo Sobre). RAW: " . $resp2);
    }

    $tmp_sobre_path = sys_get_temp_dir() . "/sobre_nc_" . $folio_nc . "_" . time() . ".xml";
    file_put_contents($tmp_sobre_path, $resp2);

    $payload_sii = ["Certificado" => ["Rut" => $RUT_CERTIFICADO, "Password" => $PASS_CERTIFICADO], "Ambiente" => 1, "Tipo" => 1];
    $ch3 = curl_init("https://api.simpleapi.cl/api/v1/envio/enviar");
    curl_setopt($ch3, CURLOPT_POST, 1);
    curl_setopt($ch3, CURLOPT_POSTFIELDS, [
        'input' => json_encode($payload_sii),
        'files' => new CURLFile($PATH_CERT, 'application/x-pkcs12', 'certificado.pfx'),
        'files2' => new CURLFile($tmp_sobre_path, 'text/xml', 'sobre.xml')
    ]);
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
    $resp3 = curl_exec($ch3);
    curl_close($ch3);

    @unlink($tmp_dte_path);
    @unlink($tmp_sobre_path);

    $api_data3 = json_decode($resp3, true);
    if (isset($api_data3['ok']) && $api_data3['ok'] === false) {
        throw new Exception("Paso 3 Rechazado: " . $resp3);
    }
    $track_id = $api_data3['trackId'] ?? $api_data3['TrackId'] ?? 'OK_SIN_TRACKID';

    $carpeta_xml = "nc_xml/";
    $carpeta_pdf = "nc_pdf/";
    
    // Usamos la ruta absoluta centralizada
    if (!is_dir($ruta_base_uploads . $carpeta_xml)) mkdir($ruta_base_uploads . $carpeta_xml, 0755, true);
    if (!is_dir($ruta_base_uploads . $carpeta_pdf)) mkdir($ruta_base_uploads . $carpeta_pdf, 0755, true);

    $nombre_base = "NC" . $folio_nc . "_" . $id_pedido;
    
    $ruta_xml_local = $ruta_base_uploads . $carpeta_xml . $nombre_base . ".xml";
    $url_xml_web = $DOMINIO_BASE . "uploads/" . $carpeta_xml . $nombre_base . ".xml";
    file_put_contents($ruta_xml_local, $xml_dte_generado);

    $xmlObj = simplexml_load_string($xml_dte_generado);
    $ted_node = $xmlObj->xpath('//*[local-name()="TED"]');
    $html_ted_code = "";
    if ($ted_node) {
        $ted_content = $ted_node[0]->asXML();
        $barcode_url = "https://bwipjs-api.metafloor.com/?bcid=pdf417&text=" . urlencode($ted_content) . "&rowheight=2&colwidth=3";
        $html_ted_code = "<img src='{$barcode_url}' style='width:100%; max-height:100px;'><div style='font-size:9px;'>Timbre Electrónico SII<br>Verifique documento: www.sii.cl</div>";
    }

    $ruta_pdf_local = $ruta_base_uploads . $carpeta_pdf . $nombre_base . ".pdf";
    $url_pdf_web = $DOMINIO_BASE . "uploads/" . $carpeta_pdf . $nombre_base . ".pdf";
    
    $datos_pdf = [
        'folio_nc' => $folio_nc,
        'razon_social' => cleanStr($row['razon_social']),
        'rut_cliente_fmt' => $rut_cliente_fmt,
        'direccion' => cleanStr($row['direccion']),
        'comuna' => cleanStr($row['comuna']),
        'monto_neto' => $monto_neto,
        'monto_iva' => $monto_iva,
        'monto_total' => $monto_total,
        'numero_factura' => $row['numero_factura'],
        'fecha_despacho' => $row['fecha_despacho'],
        'tipo_nc' => $tipo_nc,
        'razon_ref' => $razon_ref
    ];
    
    generarPDFNotaCredito($ruta_pdf_local, $datos_pdf, $html_ted_code);

    $stmt_ins = $conn->prepare("INSERT INTO facturas_historial (id_pedido_sistema, folio_sii, tipo_documento, ruta_xml_local, estado_api, json_respuesta) VALUES (?, ?, 'nota_credito', ?, 'OK', ?)");
    $log_msg = "Enviado SII | TrackID: " . $track_id . " | Tipo: " . strtoupper($tipo_nc);
    $stmt_ins->bind_param("siss", $id_pedido, $folio_nc, $url_xml_web, $log_msg); 
    $stmt_ins->execute();

    $conn->query("UPDATE pedidos_activos SET estado_nota_credito = 'EMITIDA', numero_nc = '$folio_nc', url_nc = '$url_pdf_web' WHERE id_pedido = '$id_pedido'");

    echo json_encode([
        "status" => "success", 
        "message" => "Nota de Crédito Nº $folio_nc generada correctamente.", 
        "url_pdf" => $url_pdf_web,
        "url_xml" => $url_xml_web,
        "track_id" => $track_id
    ]);

} catch (Exception $e) {
    http_response_code(400); 
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>