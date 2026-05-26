<?php
// render_xml_recibido.php
// Toma un XML DTE de un proveedor y lo dibuja estéticamente clonando el diseño de procesar_facturacion.php
header("Content-Type: text/html; charset=UTF-8");

$xml_url = $_GET['xml_url'] ?? '';
if (empty($xml_url)) die("Falta parámetro xml_url");

$filename = basename(parse_url($xml_url, PHP_URL_PATH));
$host_actual = $_SERVER['HTTP_HOST'] ?? '';
$ruta_raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
    $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
} else {
    $ruta_public = $ruta_raiz; 
}

$ruta_fisica = rtrim($ruta_public, '/') . '/uploads/recibidos_xml/' . $filename;
$xml_raw = false;

if (file_exists($ruta_fisica)) {
    $xml_raw = file_get_contents($ruta_fisica);
} else {
    $ctx = stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]]);
    $xml_raw = @file_get_contents($xml_url, false, $ctx);
}

if (!$xml_raw) die("<div style='padding:20px; font-family:sans-serif;'><h3 style='color:red;'>❌ Error al cargar el XML</h3></div>");

// Limpieza de Namespaces y atributos problemáticos
$xml_clean = preg_replace('/xmlns="[^"]+"/', '', $xml_raw);
$xml_clean = preg_replace('/xmlns:[a-zA-Z0-9_]+="[^"]+"/', '', $xml_clean);
$xml_clean = preg_replace('/<[a-zA-Z0-9_]+:([a-zA-Z0-9_]+)/', '<$1', $xml_clean);
$xml_clean = preg_replace('/<\/[a-zA-Z0-9_]+:([a-zA-Z0-9_]+)/', '</$1', $xml_clean);
$xml_clean = preg_replace('/<Documento[^>]*>/', '<Documento>', $xml_clean); 

$xml = @simplexml_load_string($xml_clean);
if (!$xml) die("El archivo XML está corrupto.");

$nodos_encabezado = $xml->xpath('//Encabezado');
$nodos_detalle = $xml->xpath('//Detalle');
$nodos_ted = $xml->xpath('//TED');

if (empty($nodos_encabezado)) die("No se encontró el Encabezado en este XML.");

$encabezado = $nodos_encabezado[0];

// 🔥 DETERMINAMOS EL TIPO DE DOCUMENTO DINÁMICAMENTE
$tipo_dte = (int)($encabezado->IdDoc->TipoDTE ?? 33);
$nombre_documento = 'FACTURA ELECTRÓNICA';

switch ($tipo_dte) {
    case 34: $nombre_documento = 'FACTURA EXENTA ELECTRÓNICA'; break;
    case 52: $nombre_documento = 'GUÍA DE DESPACHO ELECTRÓNICA'; break;
    case 56: $nombre_documento = 'NOTA DE DÉBITO ELECTRÓNICA'; break;
    case 61: $nombre_documento = 'NOTA DE CRÉDITO ELECTRÓNICA'; break;
    case 33: 
    default: $nombre_documento = 'FACTURA ELECTRÓNICA'; break;
}

$folio = $encabezado->IdDoc->Folio ?? 'N/A';
$fecha = isset($encabezado->IdDoc->FchEmis) ? date("d-m-Y", strtotime($encabezado->IdDoc->FchEmis)) : 'N/A';
$rut_p = $encabezado->Emisor->RUTEmisor ?? 'N/A';
$proveedor = $encabezado->Emisor->RznSoc ?? 'Proveedor';
$giro = $encabezado->Emisor->GiroEmis ?? $encabezado->Emisor->Giro ?? 'N/A';
$dir = ($encabezado->Emisor->DirOrigen ?? '') . ", " . ($encabezado->Emisor->CmnaOrigen ?? '');

// RECEPTOR DATA (Para la caja)
$receptor_name = $encabezado->Receptor->RznSocRecep ?? 'TABOLANGO SPA';
$receptor_rut = $encabezado->Receptor->RUTRecep ?? '77.121.854-7';
$receptor_dir = $encabezado->Receptor->DirRecep ?? 'CAMINO AL VOLCAN 29775';
$receptor_comuna = $encabezado->Receptor->CmnaRecep ?? 'SAN JOSE DE MAIPO';

$neto = number_format((int)($encabezado->Totales->MntNeto ?? 0), 0, ',', '.');
$iva = number_format((int)($encabezado->Totales->IVA ?? 0), 0, ',', '.');
$total = number_format((int)($encabezado->Totales->MntTotal ?? 0), 0, ',', '.');

$html_ted_code = "";
if (!empty($nodos_ted)) {
    $ted_content = $nodos_ted[0]->asXML();
    $barcode_url = "https://bwipjs-api.metafloor.com/?bcid=pdf417&text=" . urlencode($ted_content) . "&rowheight=2&colwidth=3";
    $html_ted_code = "<img src='{$barcode_url}' style='width:100%; max-height:100px;'><div style='font-size:9px;'>Timbre Electrónico SII<br>Verifique documento: www.sii.cl</div>";
}

// 🔥 INICIO DE CAPTURA DE HTML PARA EL RENDER / DOMPDF 🔥
ob_start();
?>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 15mm 15mm 15mm 15mm; }
        body { font-family: Helvetica, sans-serif; font-size: 11px; color: #000; line-height: 1.3; background: #fff; }
        .header { width: 100%; margin-bottom: 30px; }
        .col-left { float: left; width: 60%; }
        .col-right { float: right; width: 33%; border: 3px solid #CC0000; padding: 15px 10px; text-align: center; color: #CC0000; font-weight: bold; }
        .clear { clear: both; }
        .box { border: 1px solid #000; padding: 5px; margin-bottom: 15px; }
        .box table { width: 100%; color: #000; font-size: 11px; }
        
        /* Optimización de Tabla: Letra compacta de 10px para evitar saturación visual */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; color: #000; }
        .items-table th { background-color: #f5f5f5; border: 1px solid #000; padding: 6px; text-align: left; font-size: 10px; font-weight: bold; }
        .items-table td { padding: 4px 6px; font-size: 10px; border-bottom: 1px solid #ddd; color: #000; }
        
        .footer { margin-top: 30px; }
        .ted-box { float: left; width: 350px; text-align: center; padding-top: 10px; }
        .totals-box { float: right; width: 220px; }
        .total-table { width: 100%; border-collapse: collapse; color: #000; }
        .total-table td { padding: 4px; font-size: 12px; }
        .grand-total { border-top: 2px solid #000; font-weight: bold; font-size: 14px; padding-top: 8px !important; }
        
        <?php if (!isset($_GET['download'])): ?>
        /* Estilos Web exclusivos (No se compilan en el PDF) */
        body { background: #f8fafc; padding: 20px; }
        .invoice-paper { max-width: 750px; margin: 0 auto; background: #fff; border: 1px solid #ccc; padding: 40px; border-radius: 4px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .btn-download { position: fixed; bottom: 30px; right: 30px; background: #0F4B29; color: white; border: none; padding: 15px 25px; border-radius: 50px; font-size: 14px; font-weight: bold; cursor: pointer; box-shadow: 0 10px 20px rgba(15,75,41,0.3); transition: 0.2s; z-index: 1000; font-family: sans-serif; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-download:hover { transform: translateY(-3px); box-shadow: 0 15px 25px rgba(15,75,41,0.4); background: #165c38; }
        <?php endif; ?>
    </style>
</head>
<body>

    <?php if (!isset($_GET['download'])): ?>
    <button class="btn-download" onclick="window.location.href = window.location.href + '&download=1';">
        <i class="fa-solid fa-file-pdf"></i> Descargar PDF
    </button>
    <div class="invoice-paper">
    <?php endif; ?>

    <div class="header">
        <div class="col-left">
            <div style="font-size:14px; font-weight:bold; text-transform:uppercase; color:#000;"><?php echo $proveedor; ?></div>
            <div style="margin-top:4px;">Giro: <?php echo $giro; ?></div>
            <div><?php echo $dir; ?></div>
        </div>
        <div class="col-right">
            <div style="font-size:16px; margin-bottom:8px;">R.U.T.: <?php echo $rut_p; ?></div>
            <div style="font-size:14px; margin-bottom:8px; background-color:#fff;"><?php echo $nombre_documento; ?></div>
            <div style="font-size:16px; margin-bottom:8px;">N° <?php echo $folio; ?></div>
            <div style="font-size:11px; color:#CC0000;">S.I.I. - CHILE</div>
        </div>
        <div class="clear"></div>
    </div>

    <div class="box">
        <table cellspacing="0" cellpadding="0" border="0">
            <tr>
                <td width="80"><strong>SE&Ntilde;OR(ES):</strong></td>
                <td><?php echo $receptor_name; ?></td>
                <td width="140" align="right" style="white-space: nowrap;"><strong>FECHA:</strong> <?php echo $fecha; ?></td>
            </tr>
            <tr>
                <td><strong>RUT:</strong></td>
                <td colspan="2"><?php echo $receptor_rut; ?></td>
            </tr>
            <tr>
                <td><strong>DIRECCI&Oacute;N:</strong></td>
                <td colspan="2"><?php echo $receptor_dir; ?></td>
            </tr>
            <tr>
                <td><strong>COMUNA:</strong></td>
                <td colspan="2"><?php echo $receptor_comuna; ?></td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="50%">DESCRIPCI&Oacute;N</th>
                <th width="15%" colspan="2" style="text-align:center;">CANTIDAD</th>
                <th width="15%" style="text-align:right;">PRECIO UNIT.</th>
                <th width="20%" style="text-align:right;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (!empty($nodos_detalle)) {
                foreach ($nodos_detalle as $item) {
                    $item_nom = (string)($item->NmbItem ?? 'Sin nombre');
                    $item_qty = number_format((float)($item->QtyItem ?? 1), 0, ',', '.');
                    $unmd = isset($item->UnmdItem) ? " " . trim((string)$item->UnmdItem) : "Unid";
                    $item_prc = number_format((float)($item->PrcItem ?? 0), 0, ',', '.');
                    $item_tot = number_format((int)($item->MontoItem ?? 0), 0, ',', '.');
                    
                    echo '<tr>
                            <td style="padding-left:10px;"><strong>' . $item_nom . '</strong></td>
                            <td style="text-align:right; width:30px;">' . $item_qty . '</td>
                            <td style="text-align:left; width:50px; font-size:9px; color:#444;">' . $unmd . '</td>
                            <td style="text-align:right;">$' . $item_prc . '</td>
                            <td style="text-align:right; padding-right:10px;">$' . $item_tot . '</td>
                          </tr>';
                }
            } else {
                echo '<tr><td colspan="5" style="text-align:center; padding:15px;">No hay detalle de ítems en este XML</td></tr>';
            }
            ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="ted-box">
            <?php echo $html_ted_code; ?>
        </div>
        <div class="totals-box">
            <table class="total-table">
                <tr><td>MONTO NETO $</td><td align="right"><?php echo $neto; ?></td></tr>
                <tr><td>IVA (19%) $</td><td align="right"><?php echo $iva; ?></td></tr>
                <tr><td class="grand-total">TOTAL $</td><td class="grand-total" align="right"><?php echo $total; ?></td></tr>
            </table>
        </div>
        <div class="clear"></div>
    </div>

    <?php if (!isset($_GET['download'])): ?>
    </div> <?php endif; ?>

</body>
</html>
<?php
// Capturamos la salida HTML completa
$html = ob_get_clean();

// 🔥 EJECUCIÓN DE DESCARGA DIRECTA DE DOMPDF 🔥
if (isset($_GET['download']) && $_GET['download'] == '1') {
    
    $rutas_posibles = [ 
        __DIR__ . '/vendor/autoload.php', 
        __DIR__ . '/../vendor/autoload.php', 
        __DIR__ . '/../../vendor/autoload.php' 
    ];
    $autoload_encontrado = false;
    foreach ($rutas_posibles as $ruta) { 
        if (file_exists($ruta)) { require_once $ruta; $autoload_encontrado = true; break; } 
    }
    
    if (!$autoload_encontrado) {
        die("Error: No se encontró la librería DomPDF.");
    }

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true); 
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Cambiamos el nombre de descarga también para que sea dinámico
    $nombre_pdf = "Documento_" . $rut_p . "_Folio_" . $folio . ".pdf";
    $dompdf->stream($nombre_pdf, ["Attachment" => true]);
    exit;
}

echo $html;
?>