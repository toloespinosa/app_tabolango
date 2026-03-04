<?php
/* Template Name: Precios */
get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
 <link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri();?>/css/<?php global $post; echo $post->post_name; ?>.css?v=<?php echo time(); ?>"></script>

<div style="display:none;" id="session-email-bridge">[user_email_js]</div>

<h4>Descargar listas de precio</h4>
<div class="pdf-container">
    <button id="btnDescargarLista" class="btn-pdf">
        <span class="btn-icon">📄</span>
        <span class="btn-text">Lista Precio General</span>
    </button>

    <button id="btnDescargarNorte" class="btn-pdf btn-norte">
        <span class="btn-icon">🚛</span>
        <span class="btn-text">Lista Precio V. Norte</span>
    </button>
</div>

<div id="contenedor-matriz" class="tabolango-admin-panel">
    <div class="matriz-header">
        <div class="titulo-seccion">
            <h3><i class="fa-solid fa-money-bill-trend-up"></i> Matriz de Precios Tabolango</h3>
            <p>Formato de visualización: CLP ($)</p>
        </div>
        <div class="search-container" style="position:relative;">
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
<script src="<?php echo get_stylesheet_directory_uri(); ?>/js/global.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo get_stylesheet_directory_uri(); ?>/js/<?php global $post; echo $post->post_name; ?>.js?v=<?php echo time(); ?>"></script>


<?php
get_footer();
?>
