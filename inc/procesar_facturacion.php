<?php
require_once 'auth.php';
// procesar_facturacion.php - V83: ASÍNCRONO + TABLA dte_emitidos + BLINDAJE LOCAL (SUFIJO "S") + RUTAS CENTRALIZADAS
ob_start(); 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(120); 

// -----------------------------------------------------------------------------
// DETECCIÓN AUTOMÁTICA DE ENTORNO (LOCAL vs PRODUCCIÓN)
$host_req = $_SERVER['HTTP_HOST'] ?? '';
$es_entorno_local = (strpos($host_req, 'localhost') !== false || strpos($host_req, '127.0.0.1') !== false || strpos($host_req, '.local') !== false);

$MODO_SIMULACION = $es_entorno_local ? true : false; // TRUE: Usa "52S" (Local), FALSE: Envia al SII (Prod)
// -----------------------------------------------------------------------------

// Ampliamos el scope de búsqueda considerando la estructura clásica de temas en WP
$rutas_posibles = [
    __DIR__ . '/vendor/autoload.php',          // Si está en el mismo directorio
    __DIR__ . '/../vendor/autoload.php',       // <-- CLAVE: Sube un nivel (ej: desde /inc hacia el root del tema)
    __DIR__ . '/../../vendor/autoload.php',    // <-- Sube dos niveles por blindaje extra
    __DIR__ . '/autoload.php', 
    __DIR__ . '/librerias/autoload.php'
];
$autoload_encontrado = false;
foreach ($rutas_posibles as $ruta) { if (file_exists($ruta)) { require_once $ruta; $autoload_encontrado = true; break; } }
if (!$autoload_encontrado) { 
    while(ob_get_level() > 0) ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Falta autoload.php"]); 
    exit; 
}

use Dompdf\Dompdf;
use Dompdf\Options;

function cleanStr($str) {
    if (!$str) return "";
    $str = mb_convert_encoding($str, 'UTF-8', 'auto');
    $unwanted = ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n', 'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U', 'Ñ'=>'N', '"'=>'', "'"=>"", "`"=>"", "´"=>"", "¨"=>""];
    $str = strtr($str, $unwanted);
    $str = preg_replace('/[\x00-\x1F\x7F]/u', '', $str);
    return trim(mb_substr($str, 0, 80));
}

try {
    // --- CAMBIO: DOMINIO DINÁMICO (Local vs Producción) ---
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $DOMINIO_BASE = $es_entorno_local ? $protocolo . "://" . $_SERVER['HTTP_HOST'] . "/" : "https://tabolango.cl/";
    // ------------------------------------------------------

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

    // --- CAMBIO 1: NUEVO SISTEMA DE RUTAS (FORZADO AL DOMINIO PRINCIPAL) ---
    $host_actual = $_SERVER['HTTP_HOST'] ?? '';
    $ruta_raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/'); // /home/tabolang/erp.tabolango.cl

    // Si estamos ejecutando desde el subdominio de Producción
    if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
        // En Hostinger, el dominio principal está en public_html
        $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
    } else {
        // Si estamos en LocalWP 
        $ruta_public = $ruta_raiz;
    }

    $ruta_base_uploads = rtrim($ruta_public, '/') . '/uploads/';
    $path_certificado = $ruta_base_uploads . "certificados/certificado.pfx"; 
    // ---------------------------------------------------------------------------

    

    // --- CAMBIO: CAPTURA OMNIDIRECCIONAL DE PARÁMETROS ---
    $input = json_decode(file_get_contents('php://input'), true);
    
    // $_REQUEST atrapa tanto si viene por POST (Form) como por GET (URL)
    $raw_id = $_REQUEST['id_pedido'] ?? $input['id_pedido'] ?? '';
    $raw_tipo = $_REQUEST['tipo_doc'] ?? $input['tipo_doc'] ?? 'factura';
    
    $id_pedido = trim($conn->real_escape_string((string)$raw_id));
    $tipo_doc  = trim($conn->real_escape_string((string)$raw_tipo));

    if (empty($id_pedido)) { 
        // Agregamos info extra al error para saber qué método se usó si falla
        $metodo = $_SERVER['REQUEST_METHOD'];
        throw new Exception("Falta ID pedido. Método usado: $metodo"); 
    }
    // -----------------------------------------------------

    // --- CONTINUACIÓN CAMBIO 1: DEFINICIÓN DE CARPETAS FÍSICAS Y RELATIVAS ---
    if ($tipo_doc === 'guia') {
        $codigo_dte = 52;
        $nombre_dte = "GUIA DE DESPACHO ELECTRONICA";
        $carpeta_fisica_pdf = $ruta_base_uploads . "guia_de_despacho/";
        $carpeta_fisica_xml = $ruta_base_uploads . "guias_xml/";
        $url_relativa_pdf   = "uploads/guia_de_despacho/";
        $url_relativa_xml   = "uploads/guias_xml/";
        $columna_bd_folio   = "numero_guia"; 
        $inicio_rango       = $INICIO_CAF_GUIA;
    } else {
        $codigo_dte = 33;
        $nombre_dte = "FACTURA ELECTRONICA";
        $carpeta_fisica_pdf = $ruta_base_uploads . "facturas_api/";
        $carpeta_fisica_xml = $ruta_base_uploads . "facturas_xml/";
        $url_relativa_pdf   = "uploads/facturas_api/";
        $url_relativa_xml   = "uploads/facturas_xml/";
        $columna_bd_folio   = "numero_factura"; 
        $inicio_rango       = $INICIO_CAF_FACTURA;
    }

    if (!is_dir($carpeta_fisica_pdf)) mkdir($carpeta_fisica_pdf, 0755, true);
    if (!is_dir($carpeta_fisica_xml)) mkdir($carpeta_fisica_xml, 0755, true);
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

        $sql_max_legacy = "SELECT MAX(CAST($columna_bd_folio AS UNSIGNED)) as max_legacy FROM pedidos_activos";
        $res_legacy = $conn->query($sql_max_legacy)->fetch_assoc();
        $max_legacy = (int)($res_legacy['max_legacy'] ?? 0);

        $ultimo_folio_usado = max($max_master, $max_legacy);
        $folio_a_usar = ($ultimo_folio_usado < $inicio_rango) ? $inicio_rango : $ultimo_folio_usado + 1;
    }
    
    // SQL DATOS
    $check_email = $conn->query("SHOW COLUMNS FROM clientes LIKE 'email'");
    $campo_email = ($check_email && $check_email->num_rows > 0) ? ", C.email" : "";
    $check_col = $conn->query("SHOW COLUMNS FROM clientes LIKE 'ciudad'");
    $campo_ciudad = ($check_col && $check_col->num_rows > 0) ? ", C.ciudad" : "";

    // --- CAMBIO: Se agrega C.dias_credito a la consulta ---
    $sql = "SELECT P.producto, P.cantidad, P.precio_unitario, P.fecha_despacho, P.numero_guia,
            M.Variedad, M.calibre, M.formato, M.unidad, 
            C.rut_cliente, C.razon_social, C.giro, C.direccion, C.comuna, C.cliente as nombre_fantasia, C.dias_credito $campo_ciudad $campo_email
            FROM pedidos_activos P 
            LEFT JOIN clientes C ON (P.cliente = C.cliente OR P.id_interno_cliente = C.id_interno)
            LEFT JOIN productos M ON P.id_producto = M.id_producto
            WHERE P.id_pedido = '$id_pedido'";

    $res = $conn->query($sql);
    
    // --- CAMBIO 2: Se inicializa la variable global de crédito ---
    $detalles = []; $cliente = null; $suma_neto = 0; $dias_credito_cliente = 0; 
    
    $fecha_hoy = date("Y-m-d");
    $folio_guia_ref = 0;
    $fecha_guia_ref = $fecha_hoy;

    while ($row = $res->fetch_assoc()) {
        if (!$cliente) {
            
            // Capturamos los días de crédito de forma segura (fallback a 0 si es nulo)
            $dias_credito_cliente = (int)($row['dias_credito'] ?? 0);

            $ciudad_defecto = !empty($row['ciudad']) ? $row['ciudad'] : ($row['comuna'] ?? "Santiago");
            $dir_normal = $row['direccion'] ?? "Sin Direccion";
            $com_normal = $row['comuna'] ?? "Santiago";

            $dir_fac = $row['direccion_factura'] ?? '';
            $com_fac = $row['comuna_factura'] ?? '';
            $ciu_fac = $row['ciudad_factura'] ?? '';

            if (!empty($dir_fac) && !empty($com_fac) && !empty($ciu_fac)) {
                $final_direccion = $dir_fac; $final_comuna = $com_fac; $final_ciudad = $ciu_fac;
            } else {
                $final_direccion = $dir_normal; $final_comuna = $com_normal; $final_ciudad = $ciudad_defecto;
            }

            $rut_cliente_limpio = str_replace('.', '', $row['rut_cliente']);

            $cliente = [
                "Rut" => $rut_cliente_limpio, 
                "RazonSocial" => cleanStr($row['razon_social']), 
                "Giro" => cleanStr($row['giro'] ?: "PARTICULAR"), 
                "Direccion" => cleanStr($final_direccion), 
                "Comuna" => cleanStr($final_comuna),
                "Ciudad" => cleanStr($final_ciudad),
            ];
            
            if (!empty($row['numero_guia']) && !strpos((string)$row['numero_guia'], 'S')) {
                $folio_guia_ref = (int)$row['numero_guia'];
                $fecha_guia_ref = !empty($row['fecha_despacho']) ? date("Y-m-d", strtotime($row['fecha_despacho'])) : $fecha_hoy;
            }
        }

        $nombre_principal = $row['producto'];
        if (!empty($row['Variedad'])) $nombre_principal .= " " . $row['Variedad'];
        $desc_extras = [];
        if (!empty($row['calibre']))  $desc_extras[] = "Cal: " . $row['calibre'];
        if (!empty($row['formato']))  $desc_extras[] = $row['formato'];
        
        $unidad_raw = !empty($row['unidad']) ? $row['unidad'] : "Unid";
        $cant = floatval($row['cantidad']); 
        if ($cant > 1) {
            if (strcasecmp($unidad_raw, 'Caja') == 0) $unidad_raw = 'Cajas';
            if (strcasecmp($unidad_raw, 'Bandeja') == 0) $unidad_raw = 'Bandejas';
        }
        $p_neto = floatval($row['precio_unitario']); 
        $monto = round($p_neto * $cant);
        
        $detalles[] = [
            "Nombre" => cleanStr($nombre_principal), 
            "Descripcion" => cleanStr(implode(" - ", $desc_extras)), 
            "Cantidad" => $cant, 
            "Precio" => $p_neto, 
            "MontoItem" => $monto, 
            "UnidadMedida" => cleanStr($unidad_raw)
        ];
        $suma_neto += $monto;
    }

    if (empty($detalles)) throw new Exception("ERROR: No hay productos para ID: [$id_pedido]");

    $suma_iva = round($suma_neto * 0.19);
    $suma_total = $suma_neto + $suma_iva;

    $detalles_api = [];
    foreach ($detalles as $d) {
        $item = ["IndicadorExento" => 0, "Nombre" => $d['Nombre'], "Cantidad" => $d['Cantidad'], "Precio" => $d['Precio'], "MontoItem" => $d['MontoItem'], "UnidadMedida" => $d['UnidadMedida']];
        if (!empty($d['Descripcion'])) $item["Descripcion"] = $d['Descripcion'];
        $detalles_api[] = $item;
    }

    // --- LÓGICA DE CRÉDITO: 1:Contado, 2:Crédito ---
    // Si es Factura (33) y tiene 1 día o más de crédito, se marca como Crédito (2)
    $forma_pago = ($codigo_dte == 33 && $dias_credito_cliente >= 1) ? 2 : 1;

    $encabezado_base = [
        "IdentificacionDTE" => [
            "TipoDTE" => $codigo_dte, 
            "Folio" => $folio_a_usar, 
            "FechaEmision" => $fecha_hoy, 
            "FormaPago" => $forma_pago
        ],
        "Emisor" => ["Rut" => $RUT_EMISOR_CLEAN, "RazonSocial" => $RAZON_SOCIAL_EMISOR, "Giro" => $GIRO_EMISOR, "ActividadEconomica" => [472190], "DireccionOrigen" => $DIR_EMISOR, "ComunaOrigen" => $COMUNA_EMISOR, "Telefono" => []],
        "Receptor" => $cliente, 
        "Totales" => ["MontoNeto" => $suma_neto, "TasaIVA" => 19, "IVA" => $suma_iva, "MontoTotal" => $suma_total]
    ];

    // --- CÁLCULO DE VENCIMIENTO ---
    if ($codigo_dte == 33) {
        // Si tiene crédito (>=1), sumamos esos días. Si no, dejamos 1 día por defecto para facturas al contado.
        $plazo_vencimiento = ($dias_credito_cliente >= 1) ? $dias_credito_cliente : 1;
        $encabezado_base['IdentificacionDTE']["FechaVencimiento"] = date("Y-m-d", strtotime("$fecha_hoy + $plazo_vencimiento days"));
    }
    if ($codigo_dte == 52) {
        $encabezado_base['IdentificacionDTE']['TipoDespacho'] = 2; 
        $encabezado_base['IdentificacionDTE']['IndTraslado'] = 1; 
    }

    $json_payload = [
        "Documento" => ["Encabezado" => $encabezado_base, "Detalles" => $detalles_api],
        "Certificado" => ["Rut" => $RUT_CERTIFICADO, "Password" => $PASS_CERTIFICADO]
    ];
    
    if ($codigo_dte == 33 && $folio_guia_ref > 0) {
        $json_payload['Documento']['Referencia'] = [["NroLinRef" => 1, "TpoDocRef" => 52, "FolioRef" => $folio_guia_ref, "FchRef" => $fecha_guia_ref, "RazonRef" => "GUIA DE DESPACHO RELACIONADA"]];
    }

    $folio_final = $folio_a_usar;
    $sufijo_sim = $MODO_SIMULACION ? "_SIM" : "";
    $prefijo = ($tipo_doc == 'guia') ? "G" : "F";
    $nombre_base = "{$prefijo}{$folio_final}{$sufijo_sim}_{$id_pedido}";
    
    // --- CAMBIO 2: RUTAS ABSOLUTAS Y URLs DEFINITIVAS ---
    $ruta_xml = $carpeta_fisica_xml . $nombre_base . ".xml";
    $url_xml  = $DOMINIO_BASE . $url_relativa_xml . $nombre_base . ".xml";
    $ruta_pdf = $carpeta_fisica_pdf . $nombre_base . ".pdf";
    $url_pdf  = $DOMINIO_BASE . $url_relativa_pdf . $nombre_base . ".pdf";
    // ----------------------------------------------------

    // =========================================================================
    // COMUNICACIÓN CON SIMPLE API
    // =========================================================================
    $html_ted_code = ""; // Se inicializa vacío por defecto

    if (!$MODO_SIMULACION) {
        if (!file_exists($path_certificado)) throw new Exception("Falta certificado.pfx"); 
        
        $path_caf_actual = "";
        // --- CAMBIO 3: BUSQUEDA DEL CAF EN LA CARPETA CENTRALIZADA ---
        $archivos_caf = glob($ruta_base_uploads . "certificados/*caf_" . $codigo_dte . "*.xml");
        // -------------------------------------------------------------
        foreach ($archivos_caf as $archivo) {
            $xml_caf = @simplexml_load_file($archivo);
            if ($xml_caf && isset($xml_caf->CAF->DA->RNG)) {
                $desde = (int)$xml_caf->CAF->DA->RNG->D;
                $hasta = (int)$xml_caf->CAF->DA->RNG->H;
                if ($folio_final >= $desde && $folio_final <= $hasta) $path_caf_actual = $archivo;
                elseif ($hasta < $folio_final) @unlink($archivo); 
            }
        }
        if (empty($path_caf_actual)) throw new Exception("Error: No se encontró un archivo CAF válido para el folio $folio_final.");

        $ch = curl_init("https://api.simpleapi.cl/api/v1/dte/generar");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'input' => json_encode($json_payload, JSON_UNESCAPED_UNICODE),
            'files' => new CURLFile($path_certificado, 'application/x-pkcs12', basename($path_certificado)),
            'files2' => new CURLFile($path_caf_actual, 'text/xml', basename($path_caf_actual)) 
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (strpos($response, '<?xml') === false) {
            $api_error = json_decode($response, true);
            $raw_clean = trim(strip_tags($response)); 
            $msg = is_array($api_error) ? ($api_error['message'] ?? 'Error desconocido') : "HTTP $http_code | Respuesta: $raw_clean";
            
            $stmt_err = $conn->prepare("INSERT INTO dte_emitidos (id_pedido, tipo_documento, folio, estado_envio, respuesta_api) VALUES (?, ?, ?, 'ERROR', ?)");
            $stmt_err->bind_param("ssis", $id_pedido, $codigo_dte_db, $folio_final, $msg);
            $stmt_err->execute();
            
            throw new Exception("Rechazo API: " . $msg);
        }

        file_put_contents($ruta_xml, $response);
        $estado_envio_db = 'PENDIENTE_SII';
        $resp_api_db = 'Generado Exitosamente';

        // ---------------------------------------------------------------------
        // BLINDAJE CÓDIGO DE BARRAS (TIMBRE TED)
        // ---------------------------------------------------------------------
        $xmlObj = @simplexml_load_string($response);
        if ($xmlObj) {
            $ted_node = $xmlObj->xpath('//*[local-name()="TED"]');
            if (!empty($ted_node)) {
                $barcode_url = "https://bwipjs-api.metafloor.com/?bcid=pdf417&text=" . urlencode($ted_node[0]->asXML()) . "&rowheight=2&colwidth=3";
                
                // cURL Rápido y Seguro
                $ch_b = curl_init($barcode_url);
                curl_setopt($ch_b, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_b, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch_b, CURLOPT_TIMEOUT, 5); // Timeout corto de 5 segundos
                $bcode_img = curl_exec($ch_b);
                $http_code_bcode = curl_getinfo($ch_b, CURLINFO_HTTP_CODE);
                curl_close($ch_b);

                // Solo inyectar si la respuesta es una imagen válida
                if ($bcode_img && $http_code_bcode == 200) {
                    $base64 = base64_encode($bcode_img);
                    $html_ted_code = "<img src='data:image/png;base64,{$base64}' style='width:100%; max-height:100px;'><div style='font-size:9px;'>Timbre Electrónico SII<br>Verifique documento: www.sii.cl</div>";
                } else {
                    // Fallback visual si bwipjs-api falla
                    $html_ted_code = "<div style='border:1px solid #ccc; padding:10px; font-size:9px; text-align:center;'>[Timbre Electrónico Generado. Verifique en XML]</div>";
                }
            }
        }

      // =========================================================================
        // SINCRONIZACIÓN EN TIEMPO REAL CON DUEMINT (SOLO FACTURAS)
        // =========================================================================
        if ($codigo_dte == 33) {
            $token_duemint = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzZXNzaW9uIjoiNjlhNDdhNzdiZTY1NTU1NTNhYzRiNDg3IiwidXNlciI6IjE3MTgzIiwicHJvdmlkZXJUeXBlIjoidXNlciIsImdlbmVyYXRlZEF0IjoiMjAyNi0wMy0wMVQxNzo0MjoxNS4xNTlaIiwiYWN0aW9uIjoic2lnbiIsImlhdCI6MTc3MjM4NjkzNX0.S6a9I4H2OtXsU2B6YE-UWnKexcLFV0EjAIkb3UPn4Vc"; 
            
            $company_id_duemint = "8568"; 
            
            // Probamos enviando el XML codificado en Base64 (lo más estándar y seguro para APIs)
            $payload_duemint = json_encode([
                "xml" => base64_encode($response) 
            ]);

            $ch_due = curl_init("https://api.duemint.com/api/v1/collection-documents/xml");
            curl_setopt($ch_due, CURLOPT_POST, 1);
            curl_setopt($ch_due, CURLOPT_POSTFIELDS, $payload_duemint);
            curl_setopt($ch_due, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_due, CURLOPT_TIMEOUT, 10); 
            
            // Cabeceras IDÉNTICAS al ejemplo de su documentación
            curl_setopt($ch_due, CURLOPT_HTTPHEADER, [
                "accept: application/json",
                "authorization: Bearer " . $token_duemint,
                "content-type: application/json",
                "X-DUEMINT-COMPANY-ID: " . $company_id_duemint
            ]);
            
            $respuesta_duemint = curl_exec($ch_due);
            $http_code_due = curl_getinfo($ch_due, CURLINFO_HTTP_CODE);
            curl_close($ch_due);

            // El chismoso para saber qué dice Duemint si nos rechaza
            if ($http_code_due !== 200 && $http_code_due !== 201) {
                error_log("DUEMINT ERROR (HTTP $http_code_due): " . $respuesta_duemint);
            }
        }
        // =========================================================================
        
    } else {
        // MODO SIMULACIÓN: Nunca intenta generar el código de barras, evita timeout
        $html_ted_code = "<div style='border:2px solid red; padding:10px; color:red; font-weight:bold; text-align:center;'>DOCUMENTO BORRADOR<br>SOLO VALIDO PARA CERTIFICAR LA ENTREGA DE PEDIDO</div>";
        file_put_contents($ruta_xml, json_encode($json_payload));
        $estado_envio_db = 'SIMULADO';
        $resp_api_db = 'Simulación Local Externa a SII';
    }

    // =========================================================================
    // GENERAR PDF LOCAL
    // =========================================================================
    $logo_path = __DIR__ . '/media/logo_tabolango.png';
    if (file_exists($logo_path)) {
        $logo_b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
    } else {
        $logo_b64 = 'https://tabolango.cl/media/logo_tabolango.png'; 
    }

    $fecha_visual = date("d-m-Y");
    $neto_fmt = number_format($suma_neto, 0, ',', '.');
    $iva_fmt = number_format($suma_iva, 0, ',', '.');
    $total_fmt = number_format($suma_total, 0, ',', '.');

    $html_condicion_pago = "";
    if ($codigo_dte == 33) {
        if ($dias_credito_cliente >= 1) {
            $dias_restar = $dias_credito_cliente - 1;
            $fecha_limite_visual = date("d-m-Y", strtotime("$fecha_hoy + $dias_restar days"));
            $html_condicion_pago = '<div style="margin-top:8px; text-align:right; font-size:11px; color:#CC0000; font-weight:bold; border-top:1px solid #ccc; padding-top:6px;">PAGAR ANTES DEL:<br>' . $fecha_limite_visual . '</div>';
        } else {
            $html_condicion_pago = '<div style="margin-top:8px; text-align:right; font-size:11px; color:#0F4B29; font-weight:bold; border-top:1px solid #ccc; padding-top:6px;">CONDICIÓN:<br>AL CONTADO</div>';
        }
    }
    
    $html_referencia_bottom = "";
    if ($codigo_dte == 33 && $folio_guia_ref > 0) {
        $html_referencia_bottom = '<div style="margin-top: 15px; border: 1px solid #ccc; background-color: #f9f9f9; padding: 8px; font-size: 11px;"><strong>REFERENCIA:</strong><table width="100%" style="margin-top:4px;"><tr><td width="20%"><strong>Tipo Doc:</strong> Gu&iacute;a de Despacho (52)</td><td width="20%"><strong>Folio:</strong> ' . $folio_guia_ref . '</td><td width="20%"><strong>Fecha:</strong> ' . date("d-m-Y", strtotime($fecha_guia_ref)) . '</td><td><strong>Razón:</strong> Gu&iacute;a de despacho relacionada</td></tr></table></div>';
    }

    $filas = "";
    foreach ($detalles as $d) {
        $desc_html = !empty($d['Descripcion']) ? '<br><span style="font-size:10px; color:#555; font-style:italic;">' . $d['Descripcion'] . '</span>' : '';
        $filas .= '<tr><td style="padding:5px 5px 5px 10px; border-bottom:1px solid #ddd;"><strong>'.$d['Nombre'].'</strong>'.$desc_html.'</td><td style="text-align:right; padding:5px 2px 5px 5px; border-bottom:1px solid #ddd; width:30px;">'.number_format($d['Cantidad'], 0, '', '.').'</td><td style="text-align:left; padding:5px 5px 5px 2px; border-bottom:1px solid #ddd; width:50px; font-size:10px; color:#444;">'.$d['UnidadMedida'].'</td><td style="text-align:right; padding:5px 10px 5px 5px; border-bottom:1px solid #ddd;">$'.number_format($d['Precio'], 0, ',', '.').'</td><td style="text-align:right; padding:5px 10px 5px 5px; border-bottom:1px solid #ddd;">$'.number_format($d['MontoItem'], 0, ',', '.').'</td></tr>';
    }

    $str_folio_impreso = $MODO_SIMULACION ? "N° " . $folio_final : "N° " . $folio_final;

$html = '<html><head><meta charset="UTF-8"><style>@page{margin:15mm 15mm 15mm 15mm;}body{font-family:Helvetica,sans-serif;font-size:11px;color:#333;line-height:1.3;}.header{width:100%;margin-bottom:30px;}.col-left{float:left;width:60%;}.col-right{float:right;width:33%;border:3px solid #CC0000;padding:15px 10px;text-align:center;color:#CC0000;font-weight:bold;}.clear{clear:both;}.logo-img{width:180px;margin-bottom:10px;}.box{border:1px solid #000;padding:5px;margin-bottom:15px;}.box table{width:100%;}.items-table{width:100%;border-collapse:collapse;margin-top:10px;}.items-table th{background-color:#f5f5f5;border:1px solid #000;padding:6px;text-align:left;font-size:10px;font-weight:bold;}.footer{margin-top:30px;}.ted-box{float:left;width:350px;text-align:center;padding-top:10px;}.totals-box{float:right;width:220px;}.total-table{width:100%;border-collapse:collapse;}.total-table td{padding:4px;font-size:12px;}.grand-total{border-top:2px solid #000;font-weight:bold;font-size:14px;padding-top:8px !important;}</style></head><body><div class="header"><div class="col-left"><img src="'.$logo_b64.'" class="logo-img"><br><div style="font-size:14px;font-weight:bold;text-transform:uppercase;">'.$RAZON_SOCIAL_EMISOR.'</div><div>Giro: '.$GIRO_EMISOR.'</div><div>'.$DIR_EMISOR.', '.$COMUNA_EMISOR.'</div><div>Email: admin@tabolango.cl</div></div><div class="col-right"><div style="font-size:16px;margin-bottom:8px;">R.U.T.: '.$RUT_EMISOR_FMT.'</div><div style="font-size:16px;margin-bottom:8px;background-color:#fff;">'.$nombre_dte.'</div><div style="font-size:16px;margin-bottom:8px;">'.$str_folio_impreso.'</div><div style="font-size:11px;color:#CC0000;">S.I.I. - LA FLORIDA</div></div><div class="clear"></div></div><div class="box"><table cellspacing="0" cellpadding="0" border="0"><tr><td width="80"><strong>SE&Ntilde;OR(ES):</strong></td><td>'.$cliente['RazonSocial'].'</td><td width="100" align="right"><strong>FECHA:</strong> '.$fecha_visual.'</td></tr><tr><td><strong>RUT:</strong></td><td colspan="2">'.$cliente['Rut'].'</td></tr><tr><td><strong>DIRECCI&Oacute;N:</strong></td><td colspan="2">'.$cliente['Direccion'].'</td></tr><tr><td><strong>COMUNA:</strong></td><td colspan="2">'.$cliente['Comuna'].' &nbsp;&nbsp;&nbsp;&nbsp; <strong>CIUDAD:</strong> '.$cliente['Ciudad'].'</td></tr></table></div><table class="items-table"><thead><tr><th width="50%">DESCRIPCI&Oacute;N</th><th width="15%" colspan="2" style="text-align:center;">CANTIDAD</th><th width="15%" style="text-align:right;">PRECIO UNIT.</th><th width="20%" style="text-align:right;">TOTAL</th></tr></thead><tbody>'.$filas.'</tbody></table>'.$html_referencia_bottom.'<div class="footer"><div class="ted-box">'.$html_ted_code.'</div><div class="totals-box"><table class="total-table"><tr><td>MONTO NETO $</td><td align="right">'.$neto_fmt.'</td></tr><tr><td>IVA (19%) $</td><td align="right">'.$iva_fmt.'</td></tr><tr><td class="grand-total">TOTAL $</td><td class="grand-total" align="right">'.$total_fmt.'</td></tr></table>'.$html_condicion_pago.'</div><div class="clear"></div></div></body></html>';    
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    file_put_contents($ruta_pdf, $dompdf->output());

    $stmt_ins = $conn->prepare("INSERT INTO dte_emitidos (id_pedido, tipo_documento, folio, url_xml, url_pdf, estado_envio, respuesta_api) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_ins->bind_param("ssissss", $id_pedido, $codigo_dte_db, $folio_final, $url_xml, $url_pdf, $estado_envio_db, $resp_api_db); 
    $stmt_ins->execute();

    // =========================================================================
    // GUARDAR EN BASE DE DATOS (URL Y FOLIO) PARA AMBOS ENTORNOS
    // =========================================================================
    if ($tipo_doc === 'guia') {
        $conn->query("UPDATE pedidos_activos SET numero_guia = '$folio_final', url_guia = '$url_pdf' WHERE id_pedido = '$id_pedido'");
    } else {
        $conn->query("UPDATE pedidos_activos SET numero_factura = '$folio_final', url_factura = '$url_pdf' WHERE id_pedido = '$id_pedido'");
    }

    $sim_msg = $MODO_SIMULACION ? " (Modo Borrador Seguro)" : " Esperando envío nocturno al SII.";
    
    while (ob_get_level() > 0) { ob_end_clean(); } 
    
    echo json_encode([
        "status" => "success", 
        "message" => "Generado OK." . $sim_msg, 
        "folio" => $folio_final, 
        "url" => $url_pdf
    ]);
    exit;

} catch (Exception $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}
?>