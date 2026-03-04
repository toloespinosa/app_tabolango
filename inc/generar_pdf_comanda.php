<?php
require_once 'auth.php';
// generar_pdf_comanda.php
require_once __DIR__ . '/vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. RECIBIR LA FECHA (Por defecto hoy)
$fecha = $_GET['fecha'] ?? date('Y-m-d');

// Validar formato de fecha simple
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha)) {
    die("Formato de fecha inválido.");
}

// 2. CONEXIÓN A BASE DE DATOS
$conn = new mysqli("localhost", "tabolang_app", 'm{Hpj.?IZL$Kz${S', "tabolang_pedidos");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// 3. CONSULTA MAESTRA (AGRUPADA)
// Suma las cantidades de todos los pedidos activos para la fecha seleccionada.
// Agrupa por Producto, Variedad, Calibre y Formato.
$sql = "SELECT 
            pr.producto, 
            pr.Variedad, 
            pr.calibre, 
            pr.formato, 
            pr.unidad, 
            SUM(pa.cantidad) as total_cantidad
        FROM pedidos_activos pa
        LEFT JOIN productos pr ON pa.id_producto = pr.id_producto
        WHERE pa.fecha_despacho = ? 
          AND pa.estado != 'Entregado'
        GROUP BY pr.producto, pr.Variedad, pr.calibre, pr.formato
        ORDER BY pr.producto ASC, pr.Variedad ASC, pr.calibre ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $fecha);
$stmt->execute();
$res = $stmt->get_result();

// Formatear fecha para mostrar
$fechaDisplay = date("d/m/Y", strtotime($fecha));
$horaGeneracion = date("H:i");

// 4. HTML DEL PDF (Diseño limpio para impresión)
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 100px 40px 60px 40px; }
    
    header { 
        position: fixed; 
        top: -80px; 
        left: 0; 
        right: 0; 
        height: 80px; 
        background-color: #0f4b29; /* Verde Corporativo */
        color: white; 
        padding: 0 20px;
    }
    
    footer { 
        position: fixed; 
        bottom: -40px; 
        left: 0; 
        right: 0; 
        height: 30px; 
        text-align: center; 
        font-size: 9px; 
        color: #777; 
        border-top: 1px solid #eee; 
        padding-top: 5px; 
    }

    body { 
        font-family: "Helvetica", sans-serif; 
        color: #333; 
        font-size: 11px; 
        margin-top: 20px;
    }

    /* Logo Blanco (Asegúrate de que la URL sea accesible desde el servidor) */
    .logo-img { 
        height: 50px; 
        float: right; 
        margin-top: 15px; 
    }
    
    .header-title { 
        float: left; 
        margin-top: 25px; 
        font-size: 20px; 
        font-weight: bold; 
        text-transform: uppercase; 
        letter-spacing: 1px;
    }
    
    .info-bar { 
        margin-bottom: 20px; 
        border: 1px solid #ddd; 
        padding: 10px; 
        background: #f9f9f9; 
        border-radius: 5px; 
        text-align: center;
        font-size: 12px;
    }

    table { width: 100%; border-collapse: collapse; }
    
    th { 
        background-color: #e98c00; /* Naranja Corporativo */
        color: white; 
        padding: 10px 5px; 
        text-align: left; 
        font-size: 10px; 
        text-transform: uppercase; 
        font-weight: bold; 
    }
    
    td { 
        padding: 8px 5px; 
        border-bottom: 1px solid #eee; 
        vertical-align: middle; 
    }
    
    tr:nth-child(even) { background-color: #fcfcfc; }
    
    .product-name { font-weight: bold; font-size: 12px; color: #333; }
    .variedad-tag { font-style: italic; color: #666; font-size: 10px; margin-left: 5px; }
    
    .qty-col { 
        font-weight: bold; 
        color: #0f4b29; 
        font-size: 13px; 
        text-align: right;
        padding-right: 15px;
    }
    
    .check-box { 
        border: 1px solid #ccc; 
        width: 15px; 
        height: 15px; 
        display: inline-block; 
        background: white;
    }
</style>
</head>
<body>

<header>
    <div class="header-title">Lista de Preparación</div>
    <img src="https://tabolango.cl/media/Logo_tabolango_blanco.png" class="logo-img">
</header>

<footer>
    Generado el ' . date("d/m/Y") . ' a las ' . $horaGeneracion . ' | Tabolango SpA
</footer>

<div class="info-bar">
    <strong>FECHA DE DESPACHO:</strong> <span style="color:#0f4b29; font-size:14px;">' . $fechaDisplay . '</span>
</div>

<table>
    <thead>
        <tr>
            <th width="45%">Producto / Variedad</th>
            <th width="25%">Detalle (Calibre - Formato)</th>
            <th width="20%" align="right">Cantidad Total</th>
            <th width="10%" align="center">Check</th>
        </tr>
    </thead>
    <tbody>';

if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
        $nombre = mb_strtoupper($row['producto'], 'UTF-8');
        $variedad = (!empty($row['Variedad'])) ? '<br><span class="variedad-tag">(' . mb_strtoupper($row['Variedad'], 'UTF-8') . ')</span>' : '';
        
        $calibre = ($row['calibre'] && $row['calibre'] != 'S/C') ? $row['calibre'] : '-';
        $formato = ($row['formato']) ? $row['formato'] : '-';
        $detalle = $calibre . ' - ' . $formato;
        
        // Formato número: 1.050,5 Kg (Estilo chileno)
        $cantidadNum = number_format($row['total_cantidad'], 1, ',', '.');
        // Si termina en ,0 lo quitamos para que se vea más limpio (ej: 5,0 -> 5)
        if (substr($cantidadNum, -2) === ',0') {
            $cantidadNum = substr($cantidadNum, 0, -2);
        }
        $cantidadDisplay = $cantidadNum . ' ' . $row['unidad'];

        $html .= '
        <tr>
            <td><span class="product-name">' . $nombre . '</span>' . $variedad . '</td>
            <td>' . $detalle . '</td>
            <td class="qty-col">' . $cantidadDisplay . '</td>
            <td align="center"><div class="check-box">&nbsp;</div></td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="4" align="center" style="padding:30px; color:#777;">No hay pedidos activos para despachar el ' . $fechaDisplay . '.</td></tr>';
}

$html .= '
    </tbody>
</table>

</body>
</html>';

// 5. GENERAR EL PDF
$options = new Options();
$options->set('isRemoteEnabled', true); // Necesario para cargar el logo desde URL
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Forzar descarga con nombre dinámico
$dompdf->stream("Comanda_Tabolango_$fecha.pdf", ["Attachment" => false]);

$conn->close();
?>