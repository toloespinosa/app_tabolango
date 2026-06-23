<?php
// ============================================================
// API DTE Manual — generación de DTEs ingresados a mano.
// Soporta: 33 Factura Afecta, 46 Factura Compra,
//          52 Guía Despacho, 61 Nota de Crédito.
// ============================================================
require_once 'auth.php'; // expone $conn, $email_auth, $rol_final

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// Solo Admin / Editor
$is_admin_editor = ($rol_final === 1 || $rol_final === 2);

function ok($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}
function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ────────────────────────────────────────────────────────────
// CONFIGURACIÓN: mismo patrón que procesar_facturacion.php
// ────────────────────────────────────────────────────────────
$API_KEY              = "7165-N580-6393-2899-7690";
$RUT_EMISOR_CLEAN     = "77121854-7";
$RAZON_SOCIAL_EMISOR  = "TABOLANGO SPA";
$GIRO_EMISOR          = "VENTA AL POR MAYOR DE FRUTAS Y VERDURAS";
$DIR_EMISOR           = "LA ENSENADA TABOLANGO S/N";
$COMUNA_EMISOR        = "LIMACHE";
$RUT_CERTIFICADO      = "8201627-9";
$PASS_CERTIFICADO     = "Sofia2020";

// Ruta física a /uploads/ — mismo patrón que enviar_lote_sii.php
function rutaUploads() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $raiz = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if (strpos($host, 'erp.tabolango.cl') !== false || strpos($raiz, 'erp.tabolango.cl') !== false) {
        $public = str_replace('erp.tabolango.cl', 'public_html', $raiz);
    } else {
        $public = $raiz;
    }
    if ($public === '') $public = dirname(__DIR__, 4);
    return rtrim($public, '/') . '/uploads/';
}

function pathCertificado() {
    return rutaUploads() . 'certificados/certificado.pfx';
}

/**
 * Helper genérico para calcular ruta física y URL base de cualquier subcarpeta
 * dentro de /uploads/.  Mismo patrón que el resto del proyecto.
 */
function pathsUploadsSubdir($subdir) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $raiz = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if (strpos($host, 'erp.tabolango.cl') !== false || strpos($raiz, 'erp.tabolango.cl') !== false) {
        $public = str_replace('erp.tabolango.cl', 'public_html', $raiz);
    } else {
        $public = $raiz;
    }
    if ($public === '') $public = dirname(__DIR__, 4);

    $subdir = trim($subdir, '/') . '/';
    $fisica = rtrim($public, '/') . '/uploads/' . $subdir;

    if (strpos($host, 'tabolango.cl') !== false) {
        $urlBase = 'https://tabolango.cl/uploads/' . $subdir;
    } else {
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $script    = dirname($_SERVER['SCRIPT_NAME']);
        $base_url  = substr($script, 0, strpos($script, 'wp-content'));
        $urlBase   = $protocolo . '://' . $host . rtrim($base_url, '/') . '/uploads/' . $subdir;
    }

    if (!is_dir($fisica)) @mkdir($fisica, 0777, true);
    return ['fisica' => $fisica, 'url' => $urlBase];
}

function pathsDteReales()     { return pathsUploadsSubdir('dte_manuales'); }

/**
 * Devuelve rutas físicas y URLs públicas de uploads/dte_simulados/.
 * Aquí se escriben los XML y PDF generados en modo local.
 */
function pathsDteSimulados() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $raiz = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if (strpos($host, 'erp.tabolango.cl') !== false || strpos($raiz, 'erp.tabolango.cl') !== false) {
        $public = str_replace('erp.tabolango.cl', 'public_html', $raiz);
    } else {
        $public = $raiz;
    }
    if ($public === '') $public = dirname(__DIR__, 4);

    $fisica = rtrim($public, '/') . '/uploads/dte_simulados/';

    if (strpos($host, 'tabolango.cl') !== false) {
        $urlBase = 'https://tabolango.cl/uploads/dte_simulados/';
    } else {
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $script    = dirname($_SERVER['SCRIPT_NAME']);
        $base_url  = substr($script, 0, strpos($script, 'wp-content'));
        $urlBase   = $protocolo . '://' . $host . rtrim($base_url, '/') . '/uploads/dte_simulados/';
    }

    return ['fisica' => $fisica, 'url' => $urlBase];
}

/**
 * Genera un XML representativo del DTE (estructura SII básica, sin firma/CAF).
 * Lo guarda en /uploads/dte_simulados/ y devuelve la URL pública.
 * Solo para uso local — NO es válido para el SII.
 */
function escribirXmlSimulado($documento, $tipo, $folio, $nombreBase) {
    $paths = pathsDteSimulados();
    if (!is_dir($paths['fisica'])) @mkdir($paths['fisica'], 0777, true);

    $enc = $documento['Encabezado'] ?? [];
    $det = $documento['Detalles']   ?? [];
    $ref = $documento['Referencias'] ?? [];

    $xml  = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
    $xml .= '<DTE version="1.0" xmlns="http://www.sii.cl/SiiDte">' . "\n";
    $xml .= "  <!-- SIMULADO LOCAL: este XML NO es válido para el SII -->\n";
    $xml .= '  <Documento ID="F' . $folio . 'T' . $tipo . '">' . "\n";

    // Encabezado
    $idDte = $enc['IdentificacionDTE'] ?? [];
    $emi   = $enc['Emisor'] ?? [];
    $rec   = $enc['Receptor'] ?? [];
    $tot   = $enc['Totales'] ?? [];

    $xml .= "    <Encabezado>\n";
    $xml .= "      <IdDoc>\n";
    $xml .= "        <TipoDTE>" . htmlspecialchars($idDte['TipoDTE'] ?? '') . "</TipoDTE>\n";
    $xml .= "        <Folio>"   . htmlspecialchars($idDte['Folio']   ?? '') . "</Folio>\n";
    $xml .= "        <FchEmis>" . htmlspecialchars($idDte['FechaEmision'] ?? '') . "</FchEmis>\n";
    $xml .= "        <FmaPago>" . htmlspecialchars($idDte['FormaPago'] ?? '1') . "</FmaPago>\n";
    if (!empty($idDte['FechaVencimiento'])) $xml .= "        <FchVenc>" . htmlspecialchars($idDte['FechaVencimiento']) . "</FchVenc>\n";
    if (!empty($idDte['TipoDespacho']))     $xml .= "        <TipoDespacho>" . htmlspecialchars($idDte['TipoDespacho']) . "</TipoDespacho>\n";
    if (!empty($idDte['IndTraslado']))      $xml .= "        <IndTraslado>"  . htmlspecialchars($idDte['IndTraslado'])  . "</IndTraslado>\n";
    $xml .= "      </IdDoc>\n";

    $xml .= "      <Emisor>\n";
    $xml .= "        <RUTEmisor>"   . htmlspecialchars($emi['Rut']             ?? '') . "</RUTEmisor>\n";
    $xml .= "        <RznSoc>"      . htmlspecialchars($emi['RazonSocial']     ?? '') . "</RznSoc>\n";
    $xml .= "        <GiroEmis>"    . htmlspecialchars($emi['Giro']            ?? '') . "</GiroEmis>\n";
    $xml .= "        <DirOrigen>"   . htmlspecialchars($emi['DireccionOrigen'] ?? '') . "</DirOrigen>\n";
    $xml .= "        <CmnaOrigen>"  . htmlspecialchars($emi['ComunaOrigen']    ?? '') . "</CmnaOrigen>\n";
    $xml .= "      </Emisor>\n";

    $xml .= "      <Receptor>\n";
    $xml .= "        <RUTRecep>"    . htmlspecialchars($rec['Rut']             ?? '') . "</RUTRecep>\n";
    $xml .= "        <RznSocRecep>" . htmlspecialchars($rec['RazonSocial']     ?? '') . "</RznSocRecep>\n";
    $xml .= "        <GiroRecep>"   . htmlspecialchars($rec['Giro']            ?? '') . "</GiroRecep>\n";
    $xml .= "        <DirRecep>"    . htmlspecialchars($rec['DireccionRecep']  ?? '') . "</DirRecep>\n";
    $xml .= "        <CmnaRecep>"   . htmlspecialchars($rec['ComunaRecep']     ?? '') . "</CmnaRecep>\n";
    $xml .= "      </Receptor>\n";

    $xml .= "      <Totales>\n";
    if (isset($tot['MontoNeto']))   $xml .= "        <MntNeto>"   . (int)$tot['MontoNeto']   . "</MntNeto>\n";
    if (isset($tot['MontoExento'])) $xml .= "        <MntExe>"    . (int)$tot['MontoExento'] . "</MntExe>\n";
    if (isset($tot['TasaIVA']))     $xml .= "        <TasaIVA>"   . (float)$tot['TasaIVA']   . "</TasaIVA>\n";
    if (isset($tot['IVA']))         $xml .= "        <IVA>"       . (int)$tot['IVA']         . "</IVA>\n";
    if (isset($tot['MontoTotal']))  $xml .= "        <MntTotal>"  . (int)$tot['MontoTotal']  . "</MntTotal>\n";
    $xml .= "      </Totales>\n";
    $xml .= "    </Encabezado>\n";

    // Detalles
    foreach ($det as $i => $d) {
        $xml .= "    <Detalle>\n";
        $xml .= "      <NroLinDet>" . ($i + 1) . "</NroLinDet>\n";
        if (!empty($d['IndicadorExento'])) $xml .= "      <IndExe>" . (int)$d['IndicadorExento'] . "</IndExe>\n";
        $xml .= "      <NmbItem>"     . htmlspecialchars($d['Nombre'] ?? '') . "</NmbItem>\n";
        if (!empty($d['Descripcion'])) $xml .= "      <DscItem>" . htmlspecialchars($d['Descripcion']) . "</DscItem>\n";
        $xml .= "      <QtyItem>"     . (float)($d['Cantidad'] ?? 0) . "</QtyItem>\n";
        $xml .= "      <UnmdItem>"    . htmlspecialchars($d['UnidadMedida'] ?? '') . "</UnmdItem>\n";
        $xml .= "      <PrcItem>"     . (float)($d['Precio']   ?? 0) . "</PrcItem>\n";
        $xml .= "      <MontoItem>"   . (int)($d['MontoItem']  ?? 0) . "</MontoItem>\n";
        $xml .= "    </Detalle>\n";
    }

    // Referencias (Nota de Crédito)
    foreach ($ref as $i => $r) {
        $xml .= "    <Referencia>\n";
        $xml .= "      <NroLinRef>" . ($i + 1) . "</NroLinRef>\n";
        $xml .= "      <TpoDocRef>" . htmlspecialchars($r['TipoDocRef'] ?? '') . "</TpoDocRef>\n";
        $xml .= "      <FolioRef>"  . htmlspecialchars($r['FolioRef']   ?? '') . "</FolioRef>\n";
        $xml .= "      <FchRef>"    . htmlspecialchars($r['FechaRef']   ?? '') . "</FchRef>\n";
        $xml .= "      <CodRef>"    . htmlspecialchars($r['CodigoRef']  ?? '') . "</CodRef>\n";
        $xml .= "      <RazonRef>"  . htmlspecialchars($r['RazonRef']   ?? '') . "</RazonRef>\n";
        $xml .= "    </Referencia>\n";
    }

    $xml .= "  </Documento>\n";
    $xml .= "  <!-- TED y Signature omitidos: este es un XML simulado local -->\n";
    $xml .= '</DTE>' . "\n";

    $nombre = $nombreBase . '.xml';
    if (file_put_contents($paths['fisica'] . $nombre, $xml) === false) return null;
    return $paths['url'] . $nombre;
}

/**
 * Extrae el nodo <TED> del XML que devuelve SimpleAPI y lo convierte en
 * una imagen pdf417 (timbre electrónico). Devuelve el HTML listo para embeber.
 * Si falla la generación del código de barras, retorna un fallback simple.
 */
function extraerTedHtmlDesdeXml($xmlContent) {
    $xmlObj = @simplexml_load_string($xmlContent);
    if (!$xmlObj) return '';

    $ted = $xmlObj->xpath('//*[local-name()="TED"]');
    if (empty($ted)) return '';

    $url = "https://bwipjs-api.metafloor.com/?bcid=pdf417&text="
         . urlencode($ted[0]->asXML()) . "&rowheight=2&colwidth=3";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $img  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($img && $code === 200) {
        $b64 = base64_encode($img);
        return "<img src='data:image/png;base64,{$b64}' style='width:100%; max-height:100px;'>"
             . "<div style='font-size:9px;'>Timbre Electrónico SII<br>Verifique documento: www.sii.cl</div>";
    }
    return "<div style='border:1px solid #ccc; padding:10px; font-size:9px; text-align:center;'>[Timbre Electrónico Generado. Verifique en XML]</div>";
}

/**
 * Genera un PDF con EL MISMO formato que las facturas de procesar_facturacion.php
 * (logo, recuadro rojo SII, tabla de items, totales).
 *
 * Modo simulado (default): muestra banner amarillo "SIMULACIÓN LOCAL" arriba
 *                          y reemplaza el TED por un placeholder rojo.
 * Modo real: agrega el TED real extraído del XML del SII.
 *
 * @param array  $opts  ['simulado' => bool, 'ted_html' => string, 'paths' => array|null]
 */
function escribirPdfSimulado($documento, $tipo, $folio, $nombreBase, $opts = []) {
    $simulado  = !empty($opts['simulado']) || empty($opts);
    $ted_html  = $opts['ted_html'] ?? '';
    $paths     = $opts['paths']    ?? null;
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoload)) return null;
    require_once $autoload;

    if ($paths === null) $paths = pathsDteSimulados();
    if (!is_dir($paths['fisica'])) @mkdir($paths['fisica'], 0777, true);

    // Mapeo tipo → nombre (igual que en el procesador real)
    $nombre_dte = [
        33 => 'FACTURA ELECTRÓNICA',
        46 => 'FACTURA DE COMPRA ELECTRÓNICA',
        52 => 'GUÍA DE DESPACHO ELECTRÓNICA',
        61 => 'NOTA DE CRÉDITO ELECTRÓNICA',
    ][$tipo] ?? "DTE TIPO $tipo";

    $enc   = $documento['Encabezado'] ?? [];
    $idDte = $enc['IdentificacionDTE'] ?? [];
    $emi   = $enc['Emisor']    ?? [];
    $rec   = $enc['Receptor']  ?? [];
    $tot   = $enc['Totales']   ?? [];
    $det   = $documento['Detalles']   ?? [];
    $refs  = $documento['Referencias'] ?? [];

    // Logo embed (mismo path que el procesador real)
    $logo_path = __DIR__ . '/media/logo_tabolango.png';
    if (file_exists($logo_path)) {
        $logo_b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
    } else {
        $logo_b64 = 'https://tabolango.cl/media/logo_tabolango.png';
    }

    // Formato RUT chileno (77121854-7 → 77.121.854-7)
    $rut_emisor_raw = preg_replace('/[^0-9kK]/', '', $emi['Rut'] ?? '');
    $RUT_EMISOR_FMT = $rut_emisor_raw;
    if (strlen($rut_emisor_raw) > 1) {
        $dv = strtoupper(substr($rut_emisor_raw, -1));
        $cuerpo = substr($rut_emisor_raw, 0, -1);
        $RUT_EMISOR_FMT = number_format((int)$cuerpo, 0, '', '.') . '-' . $dv;
    }

    $fecha_visual = !empty($idDte['FechaEmision'])
        ? date("d-m-Y", strtotime($idDte['FechaEmision']))
        : date("d-m-Y");
    $str_folio_impreso = "N° " . $folio;

    // Filas de items (mismo HTML que procesar_facturacion.php)
    $filas = '';
    foreach ($det as $d) {
        $nombre_item = htmlspecialchars($d['Nombre'] ?? '');
        $desc_html   = !empty($d['Descripcion'])
            ? '<br><span style="font-size:10px; color:#555; font-style:italic;">' . htmlspecialchars($d['Descripcion']) . '</span>'
            : '';
        $exe_badge   = !empty($d['IndicadorExento'])
            ? ' <span style="background:#fff3e0; color:#b26a00; font-size:8px; padding:1px 4px; border-radius:3px;">EXENTO</span>'
            : '';
        $cant   = (int)($d['Cantidad'] ?? 0);
        $unidad = htmlspecialchars($d['UnidadMedida'] ?? 'Unid');
        $precio = (int)($d['Precio']    ?? 0);
        $monto  = (int)($d['MontoItem'] ?? 0);
        $filas .= '<tr>'
                . '<td style="padding:5px 5px 5px 10px; border-bottom:1px solid #ddd;"><strong>' . $nombre_item . '</strong>' . $exe_badge . $desc_html . '</td>'
                . '<td style="text-align:right; padding:5px 2px 5px 5px; border-bottom:1px solid #ddd; width:30px;">' . $cant . '</td>'
                . '<td style="text-align:left;  padding:5px 5px 5px 2px; border-bottom:1px solid #ddd; width:50px; font-size:10px; color:#444;">' . $unidad . '</td>'
                . '<td style="text-align:right; padding:5px 10px 5px 5px; border-bottom:1px solid #ddd;">$' . number_format($precio, 0, ',', '.') . '</td>'
                . '<td style="text-align:right; padding:5px 10px 5px 5px; border-bottom:1px solid #ddd;">$' . number_format($monto, 0, ',', '.') . '</td>'
                . '</tr>';
    }

    // Referencias (para Nota de Crédito)
    $html_referencia = '';
    foreach ($refs as $r) {
        $html_referencia .= '<div style="margin-top:15px; border:1px solid #ccc; background-color:#f9f9f9; padding:8px; font-size:11px;">'
                          . '<strong>REFERENCIA:</strong>'
                          . '<table width="100%" style="margin-top:4px;"><tr>'
                          . '<td width="25%"><strong>Tipo Doc:</strong> ' . htmlspecialchars($r['TipoDocRef'] ?? '') . '</td>'
                          . '<td width="20%"><strong>Folio:</strong> ' . htmlspecialchars($r['FolioRef'] ?? '') . '</td>'
                          . '<td width="20%"><strong>Fecha:</strong> ' . htmlspecialchars($r['FechaRef'] ?? '') . '</td>'
                          . '<td><strong>Razón:</strong> ' . htmlspecialchars($r['RazonRef'] ?? '') . '</td>'
                          . '</tr></table></div>';
    }

    // Condición de pago (solo Factura Afecta)
    $html_condicion_pago = '';
    if ($tipo == 33) {
        if (!empty($idDte['FechaVencimiento'])) {
            $fv = date("d-m-Y", strtotime($idDte['FechaVencimiento']));
            $html_condicion_pago = '<div style="margin-top:8px; text-align:right; font-size:11px; color:#CC0000; font-weight:bold; border-top:1px solid #ccc; padding-top:6px;">PAGAR ANTES DEL:<br>' . $fv . '</div>';
        } else {
            $html_condicion_pago = '<div style="margin-top:8px; text-align:right; font-size:11px; color:#0F4B29; font-weight:bold; border-top:1px solid #ccc; padding-top:6px;">CONDICIÓN:<br>AL CONTADO</div>';
        }
    }

    // TED: si vino del XML real, lo usamos; si no, advertencia de borrador
    if (!empty($ted_html)) {
        $html_ted_code = $ted_html;
    } else {
        $html_ted_code = '<div style="border:2px solid red; padding:10px; color:red; font-weight:bold; text-align:center;">'
                       . 'DOCUMENTO BORRADOR<br>SIMULACIÓN LOCAL — NO VÁLIDO ANTE EL SII'
                       . '</div>';
    }
    $banner_html = $simulado
        ? '<div class="banner-sim">🧪 SIMULACIÓN LOCAL — NO VÁLIDO ANTE EL SII (sin firma electrónica ni timbre CAF)</div>'
        : '';

    $neto_fmt  = number_format((int)($tot['MontoNeto']  ?? 0), 0, ',', '.');
    $iva_fmt   = number_format((int)($tot['IVA']        ?? 0), 0, ',', '.');
    $total_fmt = number_format((int)($tot['MontoTotal'] ?? 0), 0, ',', '.');

    $RAZON_SOCIAL_EMISOR = htmlspecialchars($emi['RazonSocial']     ?? '');
    $GIRO_EMISOR         = htmlspecialchars($emi['Giro']            ?? '');
    $DIR_EMISOR          = htmlspecialchars($emi['DireccionOrigen'] ?? '');
    $COMUNA_EMISOR       = htmlspecialchars($emi['ComunaOrigen']    ?? '');

    $cliente_razon = htmlspecialchars($rec['RazonSocial']    ?? '');
    $cliente_rut   = htmlspecialchars($rec['Rut']            ?? '');
    $cliente_dir   = htmlspecialchars($rec['DireccionRecep'] ?? '');
    $cliente_com   = htmlspecialchars($rec['ComunaRecep']    ?? '');
    $cliente_ciu   = $cliente_com;

    // ── Plantilla HTML idéntica al PDF de procesar_facturacion.php ──
    $html = '<html><head><meta charset="UTF-8"><style>'
          . '@page{margin:15mm 15mm 15mm 15mm;}'
          . 'body{font-family:Helvetica,sans-serif;font-size:11px;color:#333;line-height:1.3;}'
          . '.header{width:100%;margin-bottom:30px;}'
          . '.col-left{float:left;width:60%;}'
          . '.col-right{float:right;width:33%;border:3px solid #CC0000;padding:15px 10px;text-align:center;color:#CC0000;font-weight:bold;}'
          . '.clear{clear:both;}'
          . '.logo-img{width:180px;margin-bottom:10px;}'
          . '.box{border:1px solid #000;padding:5px;margin-bottom:15px;}'
          . '.box table{width:100%;}'
          . '.items-table{width:100%;border-collapse:collapse;margin-top:10px;}'
          . '.items-table th{background-color:#f5f5f5;border:1px solid #000;padding:6px;text-align:left;font-size:10px;font-weight:bold;}'
          . '.footer{margin-top:30px;}'
          . '.ted-box{float:left;width:350px;text-align:center;padding-top:10px;}'
          . '.totals-box{float:right;width:220px;}'
          . '.total-table{width:100%;border-collapse:collapse;}'
          . '.total-table td{padding:4px;font-size:12px;}'
          . '.grand-total{border-top:2px solid #000;font-weight:bold;font-size:14px;padding-top:8px !important;}'
          . '.banner-sim{background:#fff8e1;border:2px dashed #ffb300;padding:6px 10px;text-align:center;color:#b26a00;font-weight:bold;font-size:11px;border-radius:6px;margin-bottom:14px;}'
          . '</style></head><body>'
          . $banner_html
          . '<div class="header">'
          .   '<div class="col-left">'
          .     '<img src="' . $logo_b64 . '" class="logo-img"><br>'
          .     '<div style="font-size:14px;font-weight:bold;text-transform:uppercase;">' . $RAZON_SOCIAL_EMISOR . '</div>'
          .     '<div>Giro: ' . $GIRO_EMISOR . '</div>'
          .     '<div>' . $DIR_EMISOR . ', ' . $COMUNA_EMISOR . '</div>'
          .     '<div>Email: admin@tabolango.cl</div>'
          .   '</div>'
          .   '<div class="col-right">'
          .     '<div style="font-size:16px;margin-bottom:8px;">R.U.T.: ' . $RUT_EMISOR_FMT . '</div>'
          .     '<div style="font-size:16px;margin-bottom:8px;background-color:#fff;">' . $nombre_dte . '</div>'
          .     '<div style="font-size:16px;margin-bottom:8px;">' . $str_folio_impreso . '</div>'
          .     '<div style="font-size:11px;color:#CC0000;">S.I.I. - LA FLORIDA</div>'
          .   '</div>'
          .   '<div class="clear"></div>'
          . '</div>'
          . '<div class="box"><table cellspacing="0" cellpadding="0" border="0">'
          .   '<tr><td width="80"><strong>SE&Ntilde;OR(ES):</strong></td><td>' . $cliente_razon . '</td><td width="100" align="right"><strong>FECHA:</strong> ' . $fecha_visual . '</td></tr>'
          .   '<tr><td><strong>RUT:</strong></td><td colspan="2">' . $cliente_rut . '</td></tr>'
          .   '<tr><td><strong>DIRECCI&Oacute;N:</strong></td><td colspan="2">' . $cliente_dir . '</td></tr>'
          .   '<tr><td><strong>COMUNA:</strong></td><td colspan="2">' . $cliente_com . ' &nbsp;&nbsp;&nbsp;&nbsp; <strong>CIUDAD:</strong> ' . $cliente_ciu . '</td></tr>'
          . '</table></div>'
          . '<table class="items-table"><thead><tr>'
          .   '<th width="50%">DESCRIPCI&Oacute;N</th>'
          .   '<th width="15%" colspan="2" style="text-align:center;">CANTIDAD</th>'
          .   '<th width="15%" style="text-align:right;">PRECIO UNIT.</th>'
          .   '<th width="20%" style="text-align:right;">TOTAL</th>'
          . '</tr></thead><tbody>' . $filas . '</tbody></table>'
          . $html_referencia
          . '<div class="footer">'
          .   '<div class="ted-box">' . $html_ted_code . '</div>'
          .   '<div class="totals-box"><table class="total-table">'
          .     '<tr><td>MONTO NETO $</td><td align="right">' . $neto_fmt . '</td></tr>'
          .     '<tr><td>IVA (19%) $</td><td align="right">' . $iva_fmt . '</td></tr>'
          .     '<tr><td class="grand-total">TOTAL $</td><td class="grand-total" align="right">' . $total_fmt . '</td></tr>'
          .   '</table>' . $html_condicion_pago . '</div>'
          .   '<div class="clear"></div>'
          . '</div></body></html>';

    try {
        $opts = new \Dompdf\Options();
        $opts->set('isRemoteEnabled', true);
        $opts->set('isHtml5ParserEnabled', true);
        $pdf = new \Dompdf\Dompdf($opts);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $nombre = $nombreBase . '.pdf';
        file_put_contents($paths['fisica'] . $nombre, $pdf->output());
        return $paths['url'] . $nombre;
    } catch (\Throwable $e) {
        error_log('[api_dte_manual] Error generando PDF simulado: ' . $e->getMessage());
        return null;
    }
}

/**
 * Busca el archivo CAF apropiado para un (tipo_dte, folio).
 * Los CAF están en uploads/certificados/ con nombre tipo "caf_33_*.xml".
 * Cada CAF cubre un rango de folios autorizados (D..H en el XML).
 * Devuelve la ruta física del CAF que contiene el folio, o null si no hay.
 */
function buscarCafParaFolio($tipo_dte, $folio) {
    $patron = rutaUploads() . 'certificados/*caf_' . $tipo_dte . '*.xml';
    $archivos = glob($patron);
    if (!$archivos) return null;
    foreach ($archivos as $archivo) {
        $xml = @simplexml_load_file($archivo);
        if ($xml && isset($xml->CAF->DA->RNG)) {
            $desde = (int)$xml->CAF->DA->RNG->D;
            $hasta = (int)$xml->CAF->DA->RNG->H;
            if ($folio >= $desde && $folio <= $hasta) {
                return $archivo;
            }
        }
    }
    return null;
}

// Limpia caracteres acentuados para evitar problemas con SimpleAPI / SII
function cleanStr($txt) {
    $txt = preg_replace('/[áÁ]/u', 'a', $txt);
    $txt = preg_replace('/[éÉ]/u', 'e', $txt);
    $txt = preg_replace('/[íÍ]/u', 'i', $txt);
    $txt = preg_replace('/[óÓ]/u', 'o', $txt);
    $txt = preg_replace('/[úÚ]/u', 'u', $txt);
    $txt = preg_replace('/[ñÑ]/u', 'n', $txt);
    return trim(preg_replace('/[^a-zA-Z0-9 .,\-\/()]/u', '', $txt));
}

// ────────────────────────────────────────────────────────────
// ACCIONES DE LECTURA (cualquier rol con sesión)
// ────────────────────────────────────────────────────────────
if ($action === 'get_clientes') {
    // Reutilizamos el mismo formato que api_cotizacion.php para consistencia
    $res = $conn->query("SELECT id_interno, cliente, razon_social, rut_cliente, email,
                                telefono, direccion, ciudad, comuna, giro
                           FROM clientes
                          WHERE activo = 1
                       ORDER BY cliente ASC");
    $list = [];
    if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;
    echo json_encode($list);
    exit;
}

if ($action === 'get_referencias') {
    // Últimos DTEs emitidos que sirven como referencia para una Nota de Crédito
    $res = $conn->query("SELECT id, tipo_documento, folio, fecha_emision, es_manual
                           FROM dte_emitidos
                          WHERE tipo_documento IN ('33','34','46','52','56')
                            AND estado_envio IN ('ENVIADO','PENDIENTE_SII','ACEPTADO')
                       ORDER BY fecha_emision DESC
                          LIMIT 200");
    $list = [];
    if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;
    echo json_encode($list);
    exit;
}

if ($action === 'list_recientes') {
    $res = $conn->query("SELECT id, tipo_documento, folio, estado_envio,
                                fecha_emision, emitido_por, url_pdf, url_xml,
                                track_id, respuesta_api
                           FROM dte_emitidos
                          WHERE es_manual = 1
                       ORDER BY fecha_emision DESC
                          LIMIT 30");
    $list = [];
    if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;
    echo json_encode($list);
    exit;
}

// ────────────────────────────────────────────────────────────
// GENERAR DTE MANUAL (solo admin/editor)
// ────────────────────────────────────────────────────────────
if ($action === 'generar_dte') {
    if (!$is_admin_editor) fail("Sin permisos para emitir DTE", 403);

    $tipo_dte         = intval($input['tipo_dte'] ?? 0);
    $folio_manual     = !empty($input['folio']) ? intval($input['folio']) : null;
    $fecha_emision    = $input['fecha_emision'] ?? date('Y-m-d');
    $forma_pago       = intval($input['forma_pago'] ?? 1); // 1=contado, 2=crédito
    $receptor         = $input['receptor'] ?? [];
    $items            = $input['items'] ?? [];
    $referencia       = $input['referencia'] ?? null; // para Nota de Crédito
    $traslado         = $input['traslado']   ?? null; // para Guía Despacho

    if (!in_array($tipo_dte, [33, 46, 52, 61], true)) {
        fail("Tipo de DTE no soportado: $tipo_dte");
    }
    if (!is_array($items) || count($items) === 0) {
        fail("Debes agregar al menos un ítem");
    }
    if (empty($receptor['rut']) || empty($receptor['razon_social'])) {
        fail("Faltan datos del receptor (RUT y Razón Social son obligatorios)");
    }
    if ($tipo_dte === 61 && (empty($referencia['folio_ref']) || empty($referencia['tipo_doc_ref']))) {
        fail("Una Nota de Crédito necesita el folio y tipo del DTE original");
    }
    if ($tipo_dte === 52 && (empty($traslado['tipo_traslado']) || empty($traslado['ind_traslado']))) {
        fail("Una Guía de Despacho necesita tipo e indicador de traslado");
    }

    // Folio: si el usuario lo dejó vacío, lo calculamos como max+1 para ese tipo
    if ($folio_manual === null) {
        $sql_max = "SELECT MAX(folio) AS m FROM dte_emitidos WHERE tipo_documento = '$tipo_dte'";
        $r       = $conn->query($sql_max);
        $row     = $r ? $r->fetch_assoc() : null;
        $folio_manual = ($row && $row['m']) ? intval($row['m']) + 1 : 1;
    }

    // ── Construir detalles + totales ────────────────────────
    $detalles    = [];
    $suma_neto   = 0;
    $suma_exento = 0;
    foreach ($items as $idx => $it) {
        $nombre   = cleanStr($it['nombre'] ?? '');
        $cant     = floatval($it['cantidad'] ?? 1);
        $precio   = floatval($it['precio']   ?? 0);
        $unidad   = cleanStr($it['unidad']   ?? 'Unid');
        $exento   = !empty($it['exento'])    ? 1 : 0;
        $monto    = round($cant * $precio);
        if ($nombre === '' || $cant <= 0 || $precio < 0) {
            fail("Ítem #".($idx+1).": faltan datos (nombre, cantidad, precio)");
        }
        $item_payload = [
            "IndicadorExento" => $exento,
            "Nombre"          => $nombre,
            "Cantidad"        => $cant,
            "Precio"          => $precio,
            "MontoItem"       => $monto,
            "UnidadMedida"    => $unidad ?: 'Unid',
        ];
        if (!empty($it['descripcion'])) {
            $item_payload["Descripcion"] = cleanStr($it['descripcion']);
        }
        $detalles[] = $item_payload;
        if ($exento) $suma_exento += $monto; else $suma_neto += $monto;
    }

    // Totales según tipo de DTE
    $totales = [];
    if ($tipo_dte === 33 || $tipo_dte === 46 || $tipo_dte === 61) {
        // Con IVA (Factura Afecta, Factura Compra, Nota Crédito de afecta)
        $iva        = round($suma_neto * 0.19);
        $total      = $suma_neto + $suma_exento + $iva;
        $totales    = ["MontoNeto" => $suma_neto, "TasaIVA" => 19, "IVA" => $iva, "MontoTotal" => $total];
        if ($suma_exento > 0) $totales["MontoExento"] = $suma_exento;
    } elseif ($tipo_dte === 52) {
        // Guía de Despacho: depende del indicador de traslado, pero por defecto con IVA
        $iva     = round($suma_neto * 0.19);
        $total   = $suma_neto + $suma_exento + $iva;
        $totales = ["MontoNeto" => $suma_neto, "TasaIVA" => 19, "IVA" => $iva, "MontoTotal" => $total];
        if ($suma_exento > 0) $totales["MontoExento"] = $suma_exento;
    }

    // ── Encabezado del documento ────────────────────────────
    $encabezado = [
        "IdentificacionDTE" => [
            "TipoDTE"      => $tipo_dte,
            "Folio"        => $folio_manual,
            "FechaEmision" => $fecha_emision,
            "FormaPago"    => $forma_pago,
        ],
        "Emisor" => [
            "Rut"                 => $RUT_EMISOR_CLEAN,
            "RazonSocial"         => $RAZON_SOCIAL_EMISOR,
            "Giro"                => $GIRO_EMISOR,
            "ActividadEconomica"  => [472190],
            "DireccionOrigen"     => $DIR_EMISOR,
            "ComunaOrigen"        => $COMUNA_EMISOR,
            "Telefono"            => [],
        ],
        "Receptor" => [
            "Rut"             => cleanStr($receptor['rut']),
            "RazonSocial"     => cleanStr($receptor['razon_social']),
            "Giro"            => cleanStr($receptor['giro'] ?? 'PARTICULAR'),
            "DireccionRecep"  => cleanStr($receptor['direccion'] ?? '-'),
            "ComunaRecep"     => cleanStr($receptor['comuna'] ?? $receptor['ciudad'] ?? '-'),
        ],
        "Totales" => $totales,
    ];

    // Plazo de pago si es a crédito (factura afecta)
    if ($tipo_dte === 33 && $forma_pago === 2) {
        $dias = intval($input['dias_credito'] ?? 30);
        $encabezado['IdentificacionDTE']['FechaVencimiento'] =
            date('Y-m-d', strtotime("$fecha_emision + $dias days"));
    }

    // Guía de Despacho: tipo + indicador de traslado
    if ($tipo_dte === 52 && $traslado) {
        $encabezado['IdentificacionDTE']['TipoDespacho'] = intval($traslado['tipo_traslado']);
        $encabezado['IdentificacionDTE']['IndTraslado']  = intval($traslado['ind_traslado']);
    }

    $documento = ["Encabezado" => $encabezado, "Detalles" => $detalles];

    // Nota de Crédito: agregar bloque de referencia al DTE original
    if ($tipo_dte === 61 && $referencia) {
        $documento["Referencias"] = [[
            "TipoDocRef"   => intval($referencia['tipo_doc_ref']),
            "FolioRef"     => intval($referencia['folio_ref']),
            "FechaRef"     => $referencia['fecha_ref'] ?? $fecha_emision,
            "CodigoRef"    => intval($referencia['codigo_ref'] ?? 1), // 1=anula, 2=corrige texto, 3=corrige monto
            "RazonRef"     => cleanStr($referencia['razon_ref'] ?? 'Anula documento'),
        ]];
    }

    // Factura de Compra: agregar declaración jurada con retención de IVA
    if ($tipo_dte === 46) {
        $documento["DeclaracionJurada"] = "Se deja constancia que el IVA es retenido en su totalidad por el comprador.";
    }

    $referencia_id = ($tipo_dte === 61 && !empty($referencia['referencia_dte_id']))
        ? intval($referencia['referencia_dte_id']) : null;

    // Datos del cliente para guardar en dte_emitidos (los lee el gestor de facturas)
    $cliente_razon = $receptor['razon_social'] ?? '';
    $cliente_rut   = $receptor['rut']          ?? '';
    $monto_neto    = isset($totales['MontoNeto']) ? (int)$totales['MontoNeto'] : 0;

    // ════════════════════════════════════════════════════════
    // MODO LOCAL: generamos XML+PDF ficticios y guardamos, sin SimpleAPI.
    // Útil para probar el flujo end-to-end sin folios reales.
    // ════════════════════════════════════════════════════════
    if ($es_local) {
        $nombreBase = 'dte_simulado_' . $tipo_dte . '_' . $folio_manual . '_' . time();
        $url_xml = escribirXmlSimulado($documento, $tipo_dte, $folio_manual, $nombreBase);
        $url_pdf = escribirPdfSimulado($documento, $tipo_dte, $folio_manual, $nombreBase);

        $resp_simulada = json_encode([
            'simulado' => true,
            'modo'     => 'LOCAL',
            'tipo'     => $tipo_dte,
            'folio'    => $folio_manual,
            'url_xml'  => $url_xml,
            'url_pdf'  => $url_pdf,
            'mensaje'  => 'DTE ficticio creado en local (no se llamó al SII)',
            'payload'  => $documento,
        ], JSON_UNESCAPED_UNICODE);

        $stmt = $conn->prepare("INSERT INTO dte_emitidos
            (id_pedido, tipo_documento, es_manual, folio, referencia_dte_id,
             url_xml, url_pdf, estado_envio, respuesta_api, emitido_por,
             cliente_razon_social, cliente_rut, monto_neto)
            VALUES (NULL, ?, 1, ?, ?, ?, ?, 'SIMULADO_LOCAL', ?, ?, ?, ?, ?)");
        $stmt->bind_param("siissssssi",
            $tipo_dte, $folio_manual, $referencia_id,
            $url_xml, $url_pdf, $resp_simulada, $email_auth,
            $cliente_razon, $cliente_rut, $monto_neto);
        if (!$stmt->execute()) fail("Error guardando DTE simulado: " . $conn->error, 500);
        $new_id = $conn->insert_id;
        $stmt->close();

        ok([
            'id'       => $new_id,
            'tipo'     => $tipo_dte,
            'folio'    => $folio_manual,
            'url_pdf'  => $url_pdf,
            'url_xml'  => $url_xml,
            'simulado' => true,
            'mensaje'  => '🧪 Modo LOCAL: XML y PDF generados sin tocar SimpleAPI.',
        ]);
    }

    // ════════════════════════════════════════════════════════
    // MODO PRODUCCIÓN: flujo real contra SimpleAPI
    // ════════════════════════════════════════════════════════
    $path_cert = pathCertificado();
    if (!file_exists($path_cert)) {
        fail("Falta el certificado PFX en: $path_cert", 500);
    }

    $path_caf = buscarCafParaFolio($tipo_dte, $folio_manual);
    if (!$path_caf) {
        fail("No se encontró un archivo CAF para tipo $tipo_dte y folio $folio_manual. "
           . "Asegúrate de tener un caf_{$tipo_dte}_*.xml vigente en uploads/certificados/.", 500);
    }

    $payload_api = [
        "Documento"   => $documento,
        "Certificado" => ["Rut" => $RUT_CERTIFICADO, "Password" => $PASS_CERTIFICADO],
    ];

    $post = [
        'input'  => json_encode($payload_api, JSON_UNESCAPED_UNICODE),
        'files'  => new CURLFile($path_cert, 'application/x-pkcs12', basename($path_cert)),
        'files2' => new CURLFile($path_caf,  'text/xml',              basename($path_caf)),
    ];

    $ch = curl_init("https://api.simpleapi.cl/api/v1/dte/generar");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $resp      = curl_exec($ch);
    $curl_err  = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $resp === '') {
        $err_msg = "cURL: $curl_err | HTTP=$http_code";
        $stmt = $conn->prepare("INSERT INTO dte_emitidos
            (id_pedido, tipo_documento, es_manual, folio, estado_envio, respuesta_api, emitido_por)
            VALUES (NULL, ?, 1, ?, 'ERROR', ?, ?)");
        $stmt->bind_param("siss", $tipo_dte, $folio_manual, $err_msg, $email_auth);
        $stmt->execute();
        fail("Respuesta vacía de SimpleAPI ($err_msg)", 500);
    }

    // SimpleAPI devuelve XML directo cuando funciona; JSON con mensaje cuando falla.
    if (strpos($resp, '<?xml') === false) {
        $jsonErr   = json_decode($resp, true);
        $err_msg   = is_array($jsonErr)
            ? ($jsonErr['message'] ?? $jsonErr['msg'] ?? json_encode($jsonErr))
            : trim(strip_tags($resp));
        $stmt = $conn->prepare("INSERT INTO dte_emitidos
            (id_pedido, tipo_documento, es_manual, folio, estado_envio, respuesta_api, emitido_por)
            VALUES (NULL, ?, 1, ?, 'ERROR', ?, ?)");
        $stmt->bind_param("siss", $tipo_dte, $folio_manual, $err_msg, $email_auth);
        $stmt->execute();
        fail("SimpleAPI rechazó (HTTP $http_code): " . substr($err_msg, 0, 400));
    }

    // Respuesta exitosa: el contenido es el XML del DTE firmado.
    // Lo guardamos y generamos el PDF localmente usando el mismo template
    // que las facturas automáticas, embebiendo el TED real.
    $paths_reales = pathsDteReales();
    $nombreBase   = 'dte_' . $tipo_dte . '_' . $folio_manual . '_' . time();

    // Guarda XML real
    $xmlFilename = $nombreBase . '.xml';
    file_put_contents($paths_reales['fisica'] . $xmlFilename, $resp);
    $url_xml = $paths_reales['url'] . $xmlFilename;

    // Extrae TED y genera PDF
    $ted_html = extraerTedHtmlDesdeXml($resp);
    $url_pdf  = escribirPdfSimulado($documento, $tipo_dte, $folio_manual, $nombreBase, [
        'simulado' => false,
        'ted_html' => $ted_html,
        'paths'    => $paths_reales,
    ]);

    $stmt = $conn->prepare("INSERT INTO dte_emitidos
        (id_pedido, tipo_documento, es_manual, folio, referencia_dte_id,
         url_xml, url_pdf, estado_envio, respuesta_api, emitido_por,
         cliente_razon_social, cliente_rut, monto_neto)
        VALUES (NULL, ?, 1, ?, ?, ?, ?, 'PENDIENTE_SII', ?, ?, ?, ?, ?)");
    $resp_short = 'Generado OK | XML guardado en ' . $url_xml;
    $stmt->bind_param("siissssssi",
        $tipo_dte, $folio_manual, $referencia_id,
        $url_xml, $url_pdf, $resp_short, $email_auth,
        $cliente_razon, $cliente_rut, $monto_neto);
    if (!$stmt->execute()) fail("Error guardando: " . $conn->error, 500);
    $new_id = $conn->insert_id;
    $stmt->close();

    ok([
        'id'       => $new_id,
        'tipo'     => $tipo_dte,
        'folio'    => $folio_manual,
        'url_pdf'  => $url_pdf,
        'url_xml'  => $url_xml,
        'mensaje'  => 'DTE generado. Quedó pendiente de envío al SII.',
    ]);
}

fail("Acción no reconocida: " . htmlspecialchars($action));
