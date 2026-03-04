<?php
require_once 'auth.php';
// procesar_facturacion.php - V83: ASÍNCRONO + TABLA dte_emitidos + BLINDAJE LOCAL (SUFIJO "S")

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '256M');

// -----------------------------------------------------------------------------
// 1. DETECCIÓN AUTOMÁTICA DE ENTORNO
// -----------------------------------------------------------------------------
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$es_entorno_local = ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, '.local') !== false);
$MODO_SIMULACION = $es_entorno_local ? true : false; // TRUE en Local, FALSE en Prod

function cleanStr($str) {
    if (!$str) return "";
    $str = mb_convert_encoding($str, 'UTF-8', 'auto');
    $unwanted = ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n', 'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U', 'Ñ'=>'N', '"'=>'', "'"=>"", "`"=>"", "´"=>"", "¨"=>""];
    $str = strtr($str, $unwanted);
    return trim(mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $str), 0, 80));
}

// Búsqueda de Autoload
$rutas_posibles = [__DIR__ . '/vendor/autoload.php', dirname(__DIR__) . '/vendor/autoload.php', dirname(dirname(__DIR__)) . '/vendor/autoload.php'];
$autoload_encontrado = false;
foreach ($rutas_posibles as $ruta) { if (file_exists($ruta)) { require_once $ruta; $autoload_encontrado = true; break; } }
if (!$autoload_encontrado) { echo json_encode(["status" => "error", "message" => "Falta autoload.php"]); exit; }

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $DOMINIO_BASE = "https://tabolango.cl/"; 
    $API_KEY = "7165-N580-6393-2899-7690"; 
    $RUT_EMISOR_FMT = "77.121.854-7"; 
    $RUT_EMISOR_CLEAN = "77121854-7"; 
    $RAZON_SOCIAL_EMISOR = "Tabolango SpA";
    $GIRO_EMISOR = "COMERCIALIZACION DE HUEVOS Y ALIMENTOS NATURALES";
    $DIR_EMISOR = "CAMINO AL VOLCAN 29775";
    $COMUNA_EMISOR = "SAN JOSE DE MAIPO";

    $RUT_CERTIFICADO = "8201627-9"; 
    $PASS_CERTIFICADO = "Sofia2020"; 
    $INICIO_CAF_FACTURA = 251; 
    $INICIO_CAF_GUIA    = 146; 
    $path_certificado = __DIR__ . "/uploads/certificados/certificado.pfx"; 

    // Conexión Dinámica a BD
    $db_host = $es_entorno_local ? "localhost" : "localhost";
    $db_user = $es_entorno_local ? "root" : "tabolang_app";
    $db_pass = $es_entorno_local ? "" : "m{Hpj.?IZL\$Kz\${S";
    $db_name = $es_entorno_local ? "tabolang_pedidos" : "tabolang_pedidos";
    
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8mb4");

    $input = json_decode(file_get_contents('php://input'), true);
    $id_pedido = trim($conn->real_escape_string($_POST['id_pedido'] ?? $input['id_pedido'] ?? ''));
    $tipo_doc  = $_POST['tipo_doc'] ?? $input['tipo_doc'] ?? 'factura';

    if (empty($id_pedido)) throw new Exception("Falta ID pedido");

    if ($tipo_doc === 'guia') {
        $codigo_dte = 52;
        $nombre_dte = "GUIA DE DESPACHO ELECTRONICA";
        $carpeta_pdf = "uploads/guia_de_despacho/";
        $carpeta_xml = "uploads/guias_xml/";
        $col_folio = "numero_guia"; 
        $inicio_rango = $INICIO_CAF_GUIA;
    } else {
        $codigo_dte = 33;
        $nombre_dte = "FACTURA ELECTRONICA";
        $carpeta_pdf = "uploads/facturas_api/";
        $carpeta_xml = "uploads/facturas_xml/";
        $col_folio = "numero_factura"; 
        $inicio_rango = $INICIO_CAF_FACTURA;
    }

    if (!is_dir(__DIR__ . "/" . $carpeta_pdf)) mkdir(__DIR__ . "/" . $carpeta_pdf, 0755, true);
    if (!is_dir(__DIR__ . "/" . $carpeta_xml)) mkdir(__DIR__ . "/" . $carpeta_xml, 0755, true);

    // -------------------------------------------------------------------------
    // 2. CÁLCULO DE FOLIO A PRUEBA DE BALAS (LÓGICA CON LETRA "S")
    // -------------------------------------------------------------------------
    $codigo_dte_db = $MODO_SIMULACION ? $codigo_dte . "S" : (string)$codigo_dte;

    if ($MODO_SIMULACION) {
        $sql_sim = "SELECT MAX(folio) as max_folio FROM dte_emitidos WHERE tipo_documento = '$codigo_dte_db'";
        $res_sim = $conn->query($sql_sim)->fetch_assoc();
        $folio_a_usar = (int)($res_sim['max_folio'] ?? 0) + 1;
    } else {
        $sql_max_master = "SELECT MAX(folio) as max_folio FROM dte_emitidos WHERE tipo_documento = '$codigo_dte'";
        $res_master = $conn->query($sql_max_master)->fetch_assoc();
        $max_master = (int)($res_master['max_folio'] ?? 0);

        $sql_max_legacy = "SELECT MAX(CAST($col_folio AS UNSIGNED)) as max_legacy FROM pedidos_activos WHERE $col_folio > 0";
        $res_legacy = $conn->query($sql_max_legacy)->fetch_assoc();
        $max_legacy = (int)($res_legacy['max_legacy'] ?? 0);

        $ultimo_folio_usado = max($max_master, $max_legacy);
        $folio_a_usar = ($ultimo_folio_usado < $inicio_rango) ? $inicio_rango : $ultimo_folio_usado + 1;
    }
    
    // -------------------------------------------------------------------------
    // 3. EXTRACCIÓN DE DATOS SQL
    // -------------------------------------------------------------------------
    $sql = "SELECT P.producto, P.cantidad, P.precio_unitario, P.numero_guia, P.fecha_despacho, M.Variedad, M.calibre, M.formato, M.unidad, C.rut_cliente, C.razon_social, C.giro, C.direccion, C.comuna, C.ciudad, C.direccion_factura, C.comuna_factura, C.ciudad_factura 
            FROM pedidos_activos P 
            LEFT JOIN clientes C ON (P.cliente = C.cliente OR P.id_interno_cliente = C.id_interno)
            LEFT JOIN productos M ON P.id_producto = M.id_producto
            WHERE P.id_pedido = '$id_pedido'";
    $res = $conn->query($sql);
    $detalles = []; $cliente = null; $suma_neto = 0; $folio_guia_ref = 0; $fecha_guia_ref = date("Y-m-d");

    while ($row = $res->fetch_assoc()) {
        if (!$cliente) {
            $dir_f = $row['direccion_factura'] ?? ''; $com_f = $row['comuna_factura'] ?? ''; $ciu_f = $row['ciudad_factura'] ?? '';
            $usar_f = (!empty($dir_f) && !empty($com_f) && !empty($ciu_f));
            
            $cliente = [
                "Rut" => str_replace('.', '', $row['rut_cliente']), 
                "RazonSocial" => cleanStr($row['razon_social']), 
                "Giro" => cleanStr($row['giro'] ?: "PARTICULAR"), 
                "Direccion" => cleanStr($usar_f ? $dir_f : $row['direccion']), 
                "Comuna" => cleanStr($usar_f ? $com_f : $row['comuna']),
                "Ciudad" => cleanStr($usar_f ? $ciu_f : ($row['ciudad'] ?? $row['comuna']))
            ];
            
            if ($codigo_dte == 33 && !empty($row['numero_guia']) && intval($row['numero_guia']) > 0) {
                $folio_guia_ref = (int)$row['numero_guia'];
                $fecha_guia_ref = !empty($row['fecha_despacho']) ? date("Y-m-d", strtotime($row['fecha_despacho'])) : date("Y-m-d");
            }
        }

        $desc = [];
        if (!empty($row['calibre'])) $desc[] = "Cal: " . $row['calibre'];
        if (!empty($row['formato'])) $desc[] = $row['formato'];
        
        $uni = !empty($row['unidad']) ? $row['unidad'] : "Unid";
        $cant = floatval($row['cantidad']);
        $p_neto = floatval($row['precio_unitario']);
        if ($cant > 1 && $uni == 'Caja') $uni = 'Cajas';
        
        $detalles[] = ["Nombre" => cleanStr($row['producto'] . " " . $row['Variedad']), "Descripcion" => cleanStr(implode(" - ", $desc)), "Cantidad" => $cant, "Precio" => $p_neto, "MontoItem" => round($p_neto * $cant), "UnidadMedida" => cleanStr($uni)];
        $suma_neto += round($p_neto * $cant);
    }

    if (empty($detalles)) throw new Exception("Error: No hay productos en este pedido.");
    $suma_iva = round($suma_neto * 0.19);
    $suma_total = $suma_neto + $suma_iva;

    // JSON PAYLOAD
    $encabezado = [
        "IdentificacionDTE" => ["TipoDTE" => $codigo_dte, "Folio" => $folio_a_usar, "FechaEmision" => date("Y-m-d"), "FormaPago" => ($codigo_dte == 33 ? 2 : 1)],
        "Emisor" => ["Rut" => $RUT_EMISOR_CLEAN, "RazonSocial" => $RAZON_SOCIAL_EMISOR, "Giro" => $GIRO_EMISOR, "ActividadEconomica" => [472190], "DireccionOrigen" => $DIR_EMISOR, "ComunaOrigen" => $COMUNA_EMISOR, "Telefono" => []],
        "Receptor" => $cliente, "Totales" => ["MontoNeto" => $suma_neto, "TasaIVA" => 19, "IVA" => $suma_iva, "MontoTotal" => $suma_total]
    ];
    if ($codigo_dte == 33) $encabezado['IdentificacionDTE']["FechaVencimiento"] = date("Y-m-d", strtotime("+1 day"));
    if ($codigo_dte == 52) { $encabezado['IdentificacionDTE']['TipoDespacho'] = 2; $encabezado['IdentificacionDTE']['IndTraslado'] = 1; }

    $json_payload = ["Documento" => ["Encabezado" => $encabezado, "Detalles" => array_map(function($d) { return array_merge(["IndicadorExento" => 0], $d); }, $detalles)], "Certificado" => ["Rut" => $RUT_CERTIFICADO, "Password" => $PASS_CERTIFICADO]];
    if ($codigo_dte == 33 && $folio_guia_ref > 0) {
        $json_payload['Documento']['Referencia'] = [["NroLinRef" => 1, "TpoDocRef" => 52, "FolioRef" => $folio_guia_ref, "FchRef" => $fecha_guia_ref, "RazonRef" => "GUIA DE DESPACHO RELACIONADA"]];
    }

    $sufijo_sim = $MODO_SIMULACION ? "_SIM" : "";
    $prefijo = ($tipo_doc == 'guia') ? "G" : "F";
    $base_name = "{$prefijo}{$folio_a_usar}{$sufijo_sim}_{$id_pedido}";
    $ruta_xml = __DIR__ . "/$carpeta_xml$base_name.xml"; $url_xml = $DOMINIO_BASE . "$carpeta_xml$base_name.xml";
    $ruta_pdf = __DIR__ . "/$carpeta_pdf$base_name.pdf"; $url_pdf = $DOMINIO_BASE . "$carpeta_pdf$base_name.pdf";

    // -------------------------------------------------------------------------
    // 4. API DTE/GENERAR (SIN ENSOBRAR)
    // -------------------------------------------------------------------------
    $html_ted_code = "<div style='border:2px solid red; padding:10px; color:red; font-weight:bold; text-align:center;'>DOCUMENTO BORRADOR<br>SIN VALIDEZ TRIBUTARIA</div>";
    
    if (!$MODO_SIMULACION) {
        $caf = glob(__DIR__ . "/uploads/certificados/*caf_$codigo_dte*.xml");
        $caf_valido = "";
        foreach ($caf as $c) {
            $x = @simplexml_load_file($c);
            if ($x && $folio_a_usar >= (int)$x->CAF->DA->RNG->D && $folio_a_usar <= (int)$x->CAF->DA->RNG->H) { $caf_valido = $c; break; }
        }
        if (!$caf_valido) throw new Exception("Falta CAF válido para Folio $folio_a_usar (DTE $codigo_dte)");

        $ch = curl_init("https://api.simpleapi.cl/api/v1/dte/generar");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['input' => json_encode($json_payload, JSON_UNESCAPED_UNICODE), 'files' => new CURLFile($path_certificado, 'application/x-pkcs12', 'cert.pfx'), 'files2' => new CURLFile($caf_valido, 'text/xml', 'caf.xml')]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
        $response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

        if (strpos($response, '<?xml') === false) {
            // Guardamos el error en la tabla maestra
            $api_error = json_decode($response, true);
            $msg = is_array($api_error) ? ($api_error['message'] ?? 'Error desconocido') : "HTTP $http_code | Respuesta No XML";
            $stmt_err = $conn->prepare("INSERT INTO dte_emitidos (id_pedido, tipo_documento, folio, estado_envio, respuesta_api) VALUES (?, ?, ?, 'ERROR', ?)");
            $stmt_err->bind_param("ssis", $id_pedido, $codigo_dte_db, $folio_a_usar, $msg);
            $stmt_err->execute();
            throw new Exception("Rechazo API al Generar XML: " . $msg);
        }

        file_put_contents($ruta_xml, $response);

        $ted_node = simplexml_load_string($response)->xpath('//*[local-name()="TED"]');
        if ($ted_node) {
            $html_ted_code = "<img src='https://bwipjs-api.metafloor.com/?bcid=pdf417&text=" . urlencode($ted_node[0]->asXML()) . "&rowheight=2&colwidth=3' style='width:100%; max-height:100px;'><div style='font-size:9px;'>Timbre Electrónico SII<br>Verifique en www.sii.cl</div>";
        }
    } else {
        file_put_contents($ruta_xml, json_encode($json_payload)); // Falso XML en local
    }

    // -------------------------------------------------------------------------
    // 5. GENERAR PDF LOCAL 
    // -------------------------------------------------------------------------
    $filas = "";
    foreach ($detalles as $d) {
        $desc_h = !empty($d['Descripcion']) ? "<br><span style='font-size:10px; color:#555;'>{$d['Descripcion']}</span>" : '';
        $filas .= "<tr><td style='border-bottom:1px solid #ddd; padding:5px;'>{$d['Nombre']}$desc_h</td><td style='border-bottom:1px solid #ddd; text-align:right;'>{$d['Cantidad']}</td><td style='border-bottom:1px solid #ddd;'>{$d['UnidadMedida']}</td><td style='border-bottom:1px solid #ddd; text-align:right;'>$".number_format($d['Precio'],0,'','.')."</td><td style='border-bottom:1px solid #ddd; text-align:right;'>$".number_format($d['MontoItem'],0,'','.')."</td></tr>";
    }
    
    $ref_h = ($codigo_dte == 33 && $folio_guia_ref > 0) ? "<div style='margin-top:10px; border:1px solid #ccc; padding:5px; font-size:11px;'><strong>REF:</strong> Guía de Despacho (52) - Folio $folio_guia_ref - Fecha ".date("d-m-Y", strtotime($fecha_guia_ref))."</div>" : "";
    $folio_str = $MODO_SIMULACION ? "N° $folio_a_usar (SIM)" : "N° $folio_a_usar";

    $html = "<html><head><meta charset='UTF-8'><style>body{font-family:Helvetica;font-size:11px;color:#333;}.h-left{float:left;width:60%;}.h-right{float:right;width:33%;border:3px solid #C00;padding:10px;text-align:center;color:#C00;font-weight:bold;}.clear{clear:both;}th{background:#f5f5f5;text-align:left;border:1px solid #000;padding:5px;}</style></head><body><div class='h-left'><img src='https://tabolango.cl/media/logo_tabolango.png' width='180'><br><b>$RAZON_SOCIAL_EMISOR</b><br>$GIRO_EMISOR<br>$DIR_EMISOR, $COMUNA_EMISOR</div><div class='h-right'>R.U.T.: $RUT_EMISOR_FMT<br><br>$nombre_dte<br>$folio_str</div><div class='clear'></div><div style='border:1px solid #000; padding:5px; margin:15px 0;'><b>SEÑOR(ES):</b> {$cliente['RazonSocial']} &nbsp;&nbsp;&nbsp;<b>FECHA:</b> ".date("d-m-Y")."<br><b>RUT:</b> {$cliente['Rut']}<br><b>DIRECCIÓN:</b> {$cliente['Direccion']}, {$cliente['Comuna']}</div><table width='100%' style='border-collapse:collapse;'><tr><th>DESCRIPCIÓN</th><th>CANT.</th><th>UNIDAD</th><th>PRECIO</th><th>TOTAL</th></tr>$filas</table>$ref_h<div style='margin-top:20px;'><div style='float:left; width:350px; text-align:center;'>$html_ted_code</div><div style='float:right; width:200px;'><table width='100%'><tr><td>NETO:</td><td align='right'>$".number_format($suma_neto,0,'','.')."</td></tr><tr><td>IVA:</td><td align='right'>$".number_format($suma_iva,0,'','.')."</td></tr><tr><td><b>TOTAL:</b></td><td align='right'><b>$".number_format($suma_total,0,'','.')."</b></td></tr></table></div></div></body></html>";

    $dompdf = new Dompdf((new Options())->set('isRemoteEnabled', true));
    $dompdf->loadHtml($html); $dompdf->setPaper('A4'); $dompdf->render();
    file_put_contents($ruta_pdf, $dompdf->output());

    // -------------------------------------------------------------------------
    // 6. GUARDAR EN TABLA MAESTRA dte_emitidos
    // -------------------------------------------------------------------------
    $estado_api = $MODO_SIMULACION ? "SIMULADO" : "PENDIENTE_SII"; 
    $log_msg = $MODO_SIMULACION ? "Simulacion Local Aislada" : "XML Generado. Esperando envío (Cron)";

    $stmt = $conn->prepare("INSERT INTO dte_emitidos (id_pedido, tipo_documento, folio, url_xml, url_pdf, estado_envio, respuesta_api) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissss", $id_pedido, $codigo_dte_db, $folio_a_usar, $url_xml, $url_pdf, $estado_api, $log_msg);
    $stmt->execute();

    if ($MODO_SIMULACION) {
        $conn->query("UPDATE pedidos_activos SET url_$tipo_doc = '$url_pdf' WHERE id_pedido = '$id_pedido'");
    } else {
        $conn->query("UPDATE pedidos_activos SET $col_folio = '$folio_a_usar', url_$tipo_doc = '$url_pdf' WHERE id_pedido = '$id_pedido'");
    }

    $sim_msg = $MODO_SIMULACION ? " (Modo Simulación)" : " (Se enviará al SII automáticamente)";
    echo json_encode(["status" => "success", "message" => "Generado OK" . $sim_msg, "folio" => $folio_a_usar, "url" => $url_pdf]);

} catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
?>