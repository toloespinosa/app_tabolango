<?php
/* Template Name: Productos / Precios */
get_header();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matriz de Productos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div style="display:none;" id="session-email-bridge">[user_email_js]</div>

    <div id="contenedor-matriz" class="tabolango-admin-panel">
        <div class="matriz-header">
            <div class="titulo-seccion">
                <h3><i class="fa-solid fa-money-bill-trend-up"></i> Matriz de Precios Tabolango</h3>
                <p>Formato de visualización: CLP ($)</p>
            </div>
            
            <div class="prod-search-container" style="position:relative;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="busc-prod" placeholder="Filtrar por nombre..." onkeyup="filtrarPrecios()">
            </div>
        </div>

        <div class="matriz-scroll" style="overflow-x:auto;">
            <table class="precio-table-wp">
                <thead>
                    <tr>
                        <th class="sticky-col">Producto | Calibre</th>
                        <th>Costo</th>
                        <th class="th-lista"><i class="fa-solid fa-star"></i> Precio Lista</th>
                        <th>Gran Distribuidor (P1)</th>
                        <th>Mayorista (P2)</th>
                        <th class="col-highlight">V Norte (P4)</th>
                        <th style="text-align:center;">Acción</th>
                    </tr>
                </thead>
                <tbody id="body-matriz">
                    <tr><td colspan="7" style="text-align:center; padding:50px;">Cargando datos maestros...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>

<?php get_footer(); ?>