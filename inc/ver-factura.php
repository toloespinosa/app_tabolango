<?php
require_once 'auth.php';
/**
 * Visualizador de DTE Tabolango - V3 (Corrección Diccionario + Vista Móvil)
 */
header('Content-Type: text/html; charset=utf-8');

$archivo = $_GET['xml'] ?? '';
$nombre_archivo = basename($archivo);
$path = "uploads/facturas_xml/" . $nombre_archivo;

if (empty($archivo) || !file_exists($path)) {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Error: El archivo XML no existe.</h2>");
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($path);

// --- 1. FUNCIÓN PARA REPARAR PALABRAS MAL ESCRITAS (DICCIONARIO) ---
function repararPalabras($texto) {
    if (!$texto) return '';

    // Primero limpiamos codificación extraña (ISO vs UTF8)
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
    }

    // LISTA DE PALABRAS A "ADIVINAR" Y CORREGIR
    // Formato: 'PALABRA_MALA' => 'PALABRA_BUENA'
    // El sistema buscará sin importar mayúsculas o minúsculas
    $diccionario = [
        'CHAMPIN'          => 'CHAMPIÑÓN',
        'VINA DEL MAR'     => 'VIÑA DEL MAR',
        'vi�a del mar'      => 'VIÑA DEL MAR',
        'COMERCIALIZACI�N'  => 'COMERCIALIZACIÓN',
        'REGION'           => 'REGIÓN',
        'ALMACEN'          => 'ALMACÉN',
        'DEPOSITO'         => 'DEPÓSITO',
        'CHAMPI�ON'         => 'CHAMPIÑÓN',
        'Champi��n'        => 'CHAMPIÑÓN',
        'ESPA�OLA'        => 'ESPAÑOLA'
    ];

    // Reemplazo inteligente (Case Insensitive)
    foreach ($diccionario as $mal => $bien) {
        $texto = str_ireplace($mal, $bien, $texto);
    }

    // Retorno limpio para HTML
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function getVal($node, $path) {
    $res = $node->xpath('.//*[local-name()="' . $path . '"]');
    if ($res) {
        // Pasamos el texto por nuestra función reparadora
        return repararPalabras((string)$res[0]);
    }
    return '';
}

function formatRut($rut) {
    $rut = preg_replace('/[^k0-9]/i', '', $rut);
    if(strlen($rut) < 2) return $rut;
    $dv  = substr($rut, -1);
    $num = substr($rut, 0, -1);
    return number_format($num, 0, '', '.') . '-' . $dv;
}

// Datos Identificación
$folio = getVal($xml, 'Folio');
$fecha = getVal($xml, 'FchEmis');
$tipo_dte = getVal($xml, 'TipoDTE');
$tipo_nombre = ($tipo_dte == '33') ? 'FACTURA ELECTRÓNICA' : (($tipo_dte == '52') ? 'GUÍA DE DESPACHO ELECTRÓNICA' : 'DTE ELECTRÓNICO');

// Datos Emisor
$emisor_rut = formatRut(getVal($xml, 'RUTEmisor'));
$emisor_rs = getVal($xml, 'RznSoc');
$emisor_giro = getVal($xml, 'GiroEmis'); // Tomamos del XML y corregimos
$emisor_dir = getVal($xml, 'DirOrigen');
$emisor_comuna = getVal($xml, 'CmnaOrigen');

// Datos Receptor
$recep_rut = formatRut(getVal($xml, 'RUTRecep'));
$recep_rs = getVal($xml, 'RznSocRecep');
$recep_giro = getVal($xml, 'GiroRecep');
$recep_dir = getVal($xml, 'DirRecep');
$recep_comuna = getVal($xml, 'CmnaRecep');
$recep_ciudad = getVal($xml, 'CiudadRecep');

// Totales
$mnt_neto = getVal($xml, 'MntNeto');
$iva = getVal($xml, 'IVA');
$mnt_total = getVal($xml, 'MntTotal');

$ted_node = $xml->xpath('//*[local-name()="TED"]');
$barcode_url = "";
if ($ted_node) {
    $ted_content = $ted_node[0]->asXML();
    $barcode_url = "https://bwipjs-api.metafloor.com/?bcid=pdf417&text=" . urlencode($ted_content) . "&rowheight=2&colwidth=3";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1024">
    
    <title><?php
require_once 'auth.php'; echo $recep_rs . '_' . $folio; ?></title>
    <style>
    /* --- ESTILOS VISUALES (PANTALLA) --- */
    body { 
        font-family: 'Helvetica', Arial, sans-serif; 
        background-color: #525659; 
        margin: 0; 
        padding: 20px; 
        display: flex; 
        justify-content: center; 
        min-width: 900px; /* Importante para que no se desarme en móvil */
    }

    .invoice-card { 
        background: #fff; 
        width: 210mm;
        min-height: 297mm; 
        padding: 15mm;
        box-sizing: border-box; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.5); 
        position: relative;
        flex-shrink: 0; 
    }

    /* Elementos internos */
    .header-top { width: 100%; display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .company-info { width: 60%; }
    .logo-img { width: 220px; margin-bottom: 10px; display: block; }
    .company-name { font-size: 14px; font-weight: bold; color: #333; text-transform: uppercase; }
    .company-detail { font-size: 11px; color: #333; line-height: 1.4; margin-top: 5px; }
    .sii-box { border: 3px solid #FF0000; color: #FF0000; width: 280px; text-align: center; padding: 15px; font-weight: bold; }
    .sii-box h2 { font-size: 18px; margin: 5px 0; }
    .sii-box h1 { font-size: 19px; margin: 10px 0; text-transform: uppercase; }
    .sii-oficina { font-size: 14px; margin-top: 5px; }
    .client-section { width: 100%; border: 1.5px solid #000; margin-top: 10px; border-collapse: collapse; }
    .client-section td { padding: 5px 10px; font-size: 12px; vertical-align: top; border: none; }
    .label { font-weight: bold; width: 80px; text-transform: uppercase; }
    .val { text-transform: uppercase; }
    table.items-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
    .items-table th { border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; padding: 10px; text-align: left; background: #f9f9f9; }
    .items-table td { padding: 8px 10px; border-bottom: 1px solid #eee; }
    .footer-container { margin-top: 30px; display: flex; justify-content: space-between; }
    .ted-area { width: 350px; text-align: center; }
    .ted-area img { width: 100%; height: auto; max-height: 100px; }
    .ted-text { font-size: 9px; font-weight: bold; margin-top: 5px; }
    /* --- AJUSTE PARA PANTALLA MÓVIL --- */
@media screen and (max-width: 600px) {
    .ted-area { 
        width: 350px; /* Versión compacta para la vista previa */
    }
    .ted-text { 
        font-size: 7px; 
    }
}

/* --- AJUSTE PARA EL PDF (IMPRESIÓN) --- */
@media print {
    /* Mantenemos el estándar para escritorio */
    .ted-area { 
        width: 250px !important; /* Lo bajamos de 350 a 250 para que no sea tan invasivo */
        text-align: center; 
    }
    
    /* Si el ancho de impresión detectado es pequeño (como en móviles) */
    @media (max-width: 210mm) { 
        .ted-area {
            width: 250px !important; /* Ancho reducido para el PDF del móvil */
        }
        .ted-area img {
            max-height: 80px !important; /* Achicamos la imagen un poco */
        }
        .ted-text {
            font-size: 7pt !important; /* Texto legal más pequeño para que quepa */
        }
    }

    .ted-area img { width: 100%; height: auto; }
}
    .totals-wrapper { width: 250px; }
    .total-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; }
    .total-row.grand-total { border-top: 1.5px solid #000; font-size: 16px; font-weight: bold; margin-top: 8px; padding-top: 8px; }
    .btn-print { position: fixed; bottom: 30px; right: 30px; background: #27ae60; color: white; padding: 15px 30px; border-radius: 50px; font-weight: bold; border: none; cursor: pointer; z-index: 100; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }

    /* --- CONFIGURACIÓN DE IMPRESIÓN --- */
    @media print {
        @page { size: A4; margin: 0; }
        body { display: block; margin: 0; padding: 0; background: white; min-width: 0; }
        .invoice-card { 
            width: 100%; max-width: 210mm; 
            height: auto; min-height: 0; 
            padding: 10mm 15mm; 
            margin: 0 auto; 
            border: none; box-shadow: none; 
            page-break-after: avoid; 
        }
        .btn-print { display: none; }
        ::-webkit-scrollbar { display: none; }
    }
    </style>
</head>
<body>

<button onclick="window.print()" class="btn-print">📥 DESCARGAR / IMPRIMIR PDF</button>

<div class="invoice-card">
    <div class="header-top">
        <div class="company-info">
            <img src="/media/logo_tabolango.png" alt="Logo Tabolango" class="logo-img">
            <div class="company-name"><?php
require_once 'auth.php'; echo $emisor_rs; ?></div>
            <div class="company-detail">
                Giro: <?php
require_once 'auth.php'; echo $emisor_giro; ?><br>
                <?php
require_once 'auth.php'; echo $emisor_dir; ?>, <?php echo $emisor_comuna; ?><br>
                eMail: admin@tabolango.cl
            </div>
        </div>

        <div class="sii-box">
            <h2>R.U.T.: <?php
require_once 'auth.php'; echo $emisor_rut; ?></h2>
            <h1><?php
require_once 'auth.php'; echo $tipo_nombre; ?></h1>
            <h2>N° <?php
require_once 'auth.php'; echo $folio; ?></h2>
            <div class="sii-oficina">S.I.I. - LA FLORIDA</div>
        </div>
    </div>

    <table class="client-section">
        <tr>
            <td class="label">SEÑORES:</td>
            <td class="val" colspan="2"><?php
require_once 'auth.php'; echo $recep_rs; ?></td>
            <td class="label" style="width: 150px; text-align: right;">FECHA DE EMISIÓN:</td>
            <td class="val" style="width: 100px;"><?php
require_once 'auth.php'; echo date("d-m-Y", strtotime($fecha)); ?></td>
        </tr>
        <tr>
            <td class="label">RUT:</td>
            <td class="val" colspan="4"><?php
require_once 'auth.php'; echo $recep_rut; ?></td>
        </tr>
        <tr>
            <td class="label">GIRO:</td>
            <td class="val" colspan="4"><?php
require_once 'auth.php'; echo $recep_giro; ?></td>
        </tr>
        <tr>
            <td class="label">DIRECCIÓN:</td>
            <td class="val" colspan="4"><?php
require_once 'auth.php'; echo $recep_dir; ?></td>
        </tr>
        <tr>
            <td class="label">COMUNA:</td>
            <td class="val" style="width: 200px;"><?php
require_once 'auth.php'; echo $recep_comuna; ?></td>
            <td class="label" style="width: 80px;">CIUDAD:</td>
            <td class="val" colspan="2"><?php
require_once 'auth.php'; echo $recep_ciudad; ?></td>
        </tr>
    </table>
<br>
    <table class="items-table">
        <thead>
            <tr>
                <th width="55%">DESCRIPCIÓN</th>
                <th style="text-align: center;">CANTIDAD</th>
                <th style="text-align: right;">PRECIO UNIT.</th>
                <th style="text-align: right;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php
require_once 'auth.php';
            $detalles = $xml->xpath('//*[local-name()="Detalle"]');
            foreach ($detalles as $item):
                $nombre = getVal($item, 'NmbItem');
                $cant = getVal($item, 'QtyItem');
                $precio = getVal($item, 'PrcItem');
                $subtotal = getVal($item, 'MontoItem');
            ?>
            <tr>
                <td class="val"><?php
require_once 'auth.php'; echo $nombre; ?></td>
                <td style="text-align: center;"><?php
require_once 'auth.php'; echo number_format($cant, 0, '', '.'); ?></td>
                <td style="text-align: right;">$<?php
require_once 'auth.php'; echo number_format($precio, 0, ',', '.'); ?></td>
                <td style="text-align: right;">$<?php
require_once 'auth.php'; echo number_format($subtotal, 0, ',', '.'); ?></td>
            </tr>
            <?php
require_once 'auth.php'; endforeach; ?>
        </tbody>
    </table>
<br>
    <div class="footer-container">
        <div class="ted-area">
            <?php
require_once 'auth.php'; if ($barcode_url): ?>
                <img src="<?php
require_once 'auth.php'; echo $barcode_url; ?>" alt="Timbre Electrónico">
                <div class="ted-text">
                    Timbre Electrónico SII<br>
                    Res. 99 de 2014 Verifique documento: www.sii.cl
                </div>
            <?php
require_once 'auth.php'; endif; ?>
        </div>

        <div class="totals-wrapper">
            <?php
require_once 'auth.php'; if($mnt_neto): ?>
            <div class="total-row">
                <span>MONTO NETO $</span>
                <span><?php
require_once 'auth.php'; echo number_format($mnt_neto, 0, ',', '.'); ?></span>
            </div>
            <div class="total-row">
                <span>IVA (19%) $</span>
                <span><?php
require_once 'auth.php'; echo number_format($iva, 0, ',', '.'); ?></span>
            </div>
            <?php
require_once 'auth.php'; endif; ?>
            <div class="total-row grand-total">
                <span>TOTAL $</span>
                <span><?php
require_once 'auth.php'; echo number_format($mnt_total, 0, ',', '.'); ?></span>
            </div>
        </div>
    </div>
</div>

</body>
</html>