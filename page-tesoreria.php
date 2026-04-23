<?php
/* Template Name: Tesorería y Conciliación */
tabolango_requerir_rol([1, 2]); 
get_header();

$app_db = tabolango_get_app_db();

// 1. Consultas "Pendientes" (CORREGIDO: c.cliente as nombre_fantasia)
$movimientos = $app_db->get_results("
    SELECT m.*, c.cliente as nombre_fantasia, c.razon_social 
    FROM app_movimientos_bancarios m
    LEFT JOIN clientes c ON m.rut_remitente = c.rut_cliente
    WHERE m.estado IN ('pendiente', 'parcial') AND m.tipo = 'ingreso' 
    ORDER BY m.fecha_movimiento DESC
");
if (!is_array($movimientos)) { echo "<div style='background:#f8d7da; color:#721c24; padding:15px;'><b>Error SQL (Movimientos):</b> " . $app_db->last_error . "</div>"; $movimientos = []; }

// 2. Pedidos pendientes (CORREGIDO: c.cliente as nombre_fantasia)
$pedidos_raw = $app_db->get_results("
    SELECT p.*, c.rut_cliente, c.razon_social, c.cliente as nombre_fantasia, (p.total_venta - p.monto_pagado) as deuda_actual
    FROM pedidos_activos p 
    LEFT JOIN clientes c ON p.id_interno_cliente = c.id_interno 
    WHERE p.estado_pago IN ('Pendiente', 'Parcial') AND p.estado = 'Entregado' 
    ORDER BY p.fecha_creacion DESC
");
if (!is_array($pedidos_raw)) { echo "<div style='background:#f8d7da; color:#721c24; padding:15px;'><b>Error SQL (Pedidos):</b> " . $app_db->last_error . "</div>"; $pedidos_raw = []; }

// 3. Consulta "Historial" (CORREGIDO: MAX(cl.cliente) as nombre_fantasia)
$historial = $app_db->get_results("
    SELECT 
        GROUP_CONCAT(c.id) as ids_conciliaciones, 
        SUM(c.monto_aplicado) as monto_total_aplicado, 
        MAX(c.fecha_conciliacion) as fecha_conciliacion, 
        MAX(c.conciliado_por) as conciliado_por,
        m.id as id_movimiento, 
        MAX(m.fecha_movimiento) as fecha_movimiento, 
        MAX(m.descripcion) as descripcion, 
        MAX(m.rut_remitente) as rut_remitente,
        MAX(m.monto) as monto_original_banco,
        MAX(p.id_pedido) as id_pedido, 
        MAX(p.numero_factura) as numero_factura,
        MAX(p.url_factura) as url_factura,
        MAX(p.cliente) as cliente,
        MAX(cl.cliente) as nombre_fantasia,
        (SELECT SUM(total_venta) FROM pedidos_activos WHERE id_pedido = p.id_pedido) as total_factura_real,
        (SELECT SUM(monto_pagado) FROM pedidos_activos WHERE id_pedido = p.id_pedido) as pagado_factura_real
    FROM app_conciliaciones c
    JOIN app_movimientos_bancarios m ON c.id_movimiento = m.id
    JOIN pedidos_activos p ON c.id_pedido_interno = p.id_interno
    LEFT JOIN clientes cl ON p.id_interno_cliente = cl.id_interno
    GROUP BY m.id, p.id_pedido
    ORDER BY fecha_conciliacion DESC LIMIT 50
");
if (!is_array($historial)) { echo "<div style='background:#f8d7da; color:#721c24; padding:15px;'><b>Error SQL (Historial):</b> " . $app_db->last_error . "</div>"; $historial = []; }
?>

<script>
    window.facturasPendientes = <?php echo json_encode($pedidos_raw); ?>;
    window.historialConciliado = <?php echo json_encode($historial); ?>;
    window.abonosPendientes = <?php echo json_encode($movimientos); ?>;
</script>

<div class="tabolango-dashboard">
    <div class="caja-blanca t-header-box">
        <h2 class="t-title"><i class="fa-solid fa-building-columns"></i> Tesorería</h2>
        <button id="btn-sync-fintoc" style="background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 800; cursor: pointer; float: right; margin-top: -35px;">
            <i class="fa-solid fa-rotate"></i> Actualizar Cartola
        </button>
        
        <div style="margin-top: 20px; border-bottom: 2px solid #eee; display:flex; gap: 20px;">
            <button class="t-tab-btn active" onclick="switchTab('pendientes')" id="tab-btn-pendientes" style="background:none; border:none; border-bottom: 3px solid #28a745; padding-bottom: 10px; font-weight: 700; color: #28a745; font-size: 16px; cursor:pointer;">Abonos por Conciliar</button>
            <button class="t-tab-btn" onclick="switchTab('historial')" id="tab-btn-historial" style="background:none; border:none; padding-bottom: 10px; font-weight: 600; color: #666; font-size: 16px; cursor:pointer;">Historial Conciliado</button>
        </div>
    </div>

    <div id="vista-pendientes" style="margin-top: 20px;">
        <div class="caja-blanca">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #eee;">
                        <th style="padding: 10px;">Fecha</th>
                        <th style="padding: 10px;">Detalle del Remitente</th>
                        <th style="padding: 10px;">Monto Disponible</th>
                        <th style="padding: 10px; text-align: right;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($movimientos)): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 30px; color:#666;">No hay abonos bancarios pendientes.</td></tr>
                    <?php else: ?>
                        <?php foreach($movimientos as $mov): ?>
                            <tr class="fila-banco" style="border-bottom: 1px solid #eee; transition: 0.2s;" data-id="<?php echo $mov->id; ?>" data-monto="<?php echo $mov->saldo_disponible; ?>" data-rut="<?php echo esc_attr($mov->rut_remitente ?? ''); ?>">
                                <td style="padding: 15px 10px; color:#666; font-size:14px;">
                                    <?php echo date('d/m/Y', strtotime($mov->fecha_movimiento)); ?>
                                </td>
                                
                                <td style="padding: 15px 10px;">
                                    <?php 
                                        $nombre_destacado = !empty($mov->nombre_fantasia) ? $mov->nombre_fantasia : (!empty($mov->razon_social) ? $mov->razon_social : ($mov->nombre_remitente ?? 'Transferencia Bancaria'));
                                    ?>
                                    <div style="font-weight: 800; color: #333; font-size: 15px; margin-bottom: 3px;">
                                        <i class="fa-solid fa-building" style="color:#0d6efd; margin-right: 4px;"></i> <?php echo esc_html($nombre_destacado); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        <i class="fa-solid fa-file-invoice" style="color:#E98C00;"></i> Glosa: <?php echo esc_html($mov->descripcion); ?>
                                    </div>
                                    <?php if(!empty($mov->rut_remitente)): ?>
                                        <div style="font-size: 11px; color: #999; margin-top: 3px; font-weight: 600;">RUT: <?php echo esc_html($mov->rut_remitente); ?></div>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 15px 10px; font-weight: bold; font-size: 16px; color: #27ae60;">
                                    $<?php echo number_format($mov->saldo_disponible, 0, ',', '.'); ?>
                                </td>
                                <td style="padding: 15px 10px; text-align: right; min-width: 280px;">
                                    <button class="btn-buscar-match" onclick="gestionarMatch(<?php echo $mov->id; ?>, '<?php echo esc_js($mov->rut_remitente); ?>', <?php echo $mov->saldo_disponible; ?>)" style="background: #e9ecef; color: #495057; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: 0.2s;">
                                        <i class="fa-solid fa-magnifying-glass"></i> Buscar Conciliación
                                    </button>
                                    <button class="btn-descartar" onclick="descartarMovimiento(<?php echo $mov->id; ?>, event)" style="background: none; color: #dc3545; border: 1px solid #dc3545; padding: 7px 10px; border-radius: 6px; cursor: pointer; font-size: 13px; margin-left: 5px;" title="Descartar ingreso">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="vista-historial" style="display:none; margin-top: 20px;">
        <div class="caja-blanca">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #eee;">
                        <th style="padding: 10px;">Fecha</th>
                        <th style="padding: 10px;">Remitente / Banco</th>
                        <th style="padding: 10px;">Factura Pagada</th>
                        <th style="padding: 10px;">Monto Aplicado</th>
                        <th style="padding: 10px; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($historial)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 30px; color:#666;">No hay historial de conciliaciones.</td></tr>
                    <?php else: ?>
                        <?php foreach($historial as $h): 
                            $textoDoc = !empty($h->numero_factura) ? 'Fac. N° ' . $h->numero_factura : $h->id_pedido;
                            $saldoPendiente = round($h->total_factura_real - $h->pagado_factura_real);
                            $nombre_historial = !empty($h->nombre_fantasia) ? $h->nombre_fantasia : $h->cliente;
                        ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px 10px; font-size:14px; color:#666;"><?php echo date('d/m/Y', strtotime($h->fecha_conciliacion)); ?></td>
                                
                                <td style="padding: 15px 10px;">
                                    <div style="font-weight: 800; color: #333; font-size: 14px; margin-bottom: 2px;">
                                        <i class="fa-solid fa-building" style="color:#0d6efd; margin-right: 4px;"></i> <?php echo esc_html($nombre_historial); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #666;">
                                        <i class="fa-solid fa-file-invoice" style="color:#E98C00;"></i> <?php echo esc_html($h->descripcion); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #999; font-weight: 600; margin-top:2px;">RUT: <?php echo esc_html($h->rut_remitente); ?></div>
                                </td>

                                <td style="padding: 15px 10px;">
                                    <span style="background:#e3f2fd; color:#0d6efd; padding:3px 6px; border-radius:4px; font-size:12px; font-weight: bold;"><?php echo esc_html($textoDoc); ?></span>
                                    
                                    <?php if($saldoPendiente > 0): ?>
                                        <br><span style="background:#fff3cd; color:#d35400; padding:3px 6px; border-radius:4px; font-size:11px; font-weight:bold; display:inline-block; margin-top:6px;"><i class="fa-solid fa-star-half-stroke"></i> Abono Parcial (Falta $<?php echo number_format($saldoPendiente, 0, ',', '.'); ?>)</span>
                                    <?php else: ?>
                                        <br><span style="background:#d4edda; color:#155724; padding:3px 6px; border-radius:4px; font-size:11px; font-weight:bold; display:inline-block; margin-top:6px;"><i class="fa-solid fa-check-double"></i> Pagada al 100%</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px 10px; font-weight: 800; color: #28a745; font-size:15px;">$<?php echo number_format($h->monto_total_aplicado, 0, ',', '.'); ?></td>
                                
                                <td style="padding: 15px 10px; text-align: right; min-width: 160px;">
                                    <button onclick="verCompanerosDePago(<?php echo $h->id_movimiento; ?>)" style="background: #f8f9fa; color: #0d6efd; border: 1px solid #0d6efd; padding: 6px 10px; border-radius: 5px; cursor: pointer; font-size: 13px;" title="Ver detalle del pago múltiple"><i class="fa-solid fa-eye"></i></button>
                                    <?php if(!empty($h->url_factura)): ?>
                                    <button onclick="verDocumento('<?php echo esc_url($h->url_factura); ?>')" style="background: #f8f9fa; color: #198754; border: 1px solid #198754; padding: 6px 10px; border-radius: 5px; cursor: pointer; font-size: 13px; margin-left: 3px;" title="Ver Documento PDF"><i class="fa-solid fa-file-pdf"></i></button>
                                    <?php endif; ?>
                                    <button onclick="deshacerConciliacion('<?php echo $h->ids_conciliaciones; ?>')" style="background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 5px; cursor: pointer; font-size: 13px; margin-left: 3px;" title="Deshacer conciliación"><i class="fa-solid fa-rotate-left"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('vista-pendientes').style.display = tab === 'pendientes' ? 'block' : 'none';
    document.getElementById('vista-historial').style.display = tab === 'historial' ? 'block' : 'none';
    document.getElementById('tab-btn-pendientes').style.borderBottom = tab === 'pendientes' ? '3px solid #28a745' : 'none';
    document.getElementById('tab-btn-pendientes').style.color = tab === 'pendientes' ? '#28a745' : '#666';
    document.getElementById('tab-btn-historial').style.borderBottom = tab === 'historial' ? '3px solid #28a745' : 'none';
    document.getElementById('tab-btn-historial').style.color = tab === 'historial' ? '#28a745' : '#666';
}
</script>

<?php get_footer(); ?>