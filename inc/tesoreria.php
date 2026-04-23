<?php
/**
 * MÓDULO DE TESORERÍA - BACKEND (inc/tesoreria.php)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// --- CREDENCIALES DE PRUEBA (TABOLANGO) ---
define('FINTOC_SECRET', 'sk_test_Ns-k-1ekNfhfJKA-6yyX_e_yHzscGFzSoA1cz8b345M');
define('FINTOC_LINK_TOKEN', 'link_J0WLYbi4yQjxXxAB_token_YGWrWdAuhG3QYrig_Wwv-G8u');

// =================================================================================
// 1. REGISTRO DE RUTAS API (WEBHOOK DE FINTOC)
// =================================================================================
add_action('rest_api_init', function () {
    register_rest_route('tabolango/v1', '/fintoc', [
        'methods'  => 'POST',
        'callback' => 'procesar_webhook_fintoc',
        'permission_callback' => '__return_true'
    ]);
});

function procesar_webhook_fintoc($request) {
    $payload = $request->get_json_params();
    if (empty($payload) || $payload['type'] !== 'account.refresh_intent.succeeded') {
        return rest_ensure_response(['status' => 'ignored']);
    }

    $account_id = $payload['data']['account_id'] ?? '';
    if ($account_id) {
        tabolango_pull_fintoc_movements($account_id);
    }
    return rest_ensure_response(['status' => 'success']);
}

// =================================================================================
// 2. FUNCIÓN DE DESCARGA DE MOVIMIENTOS (PULL)
// =================================================================================
function tabolango_pull_fintoc_movements($account_id) {
    $app_db = tabolango_get_app_db();
    $url = "https://api.fintoc.com/v1/accounts/{$account_id}/movements?link_token=" . FINTOC_LINK_TOKEN;

    $response = wp_remote_get($url, [
        'headers' => ['Authorization' => FINTOC_SECRET],
        'timeout' => 20
    ]);

    if (is_wp_error($response)) return;

    $movements = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($movements)) return;

    foreach ($movements as $m) {
        $fintoc_id = sanitize_text_field($m['id']);
        $monto = floatval($m['amount']);
        if ($monto <= 0) continue; // Solo nos importan los ingresos

        // Extracción de datos del remitente
        $rut_remitente = isset($m['sender_account']['holder_id']) ? sanitize_text_field($m['sender_account']['holder_id']) : '';
        $nombre_remitente = isset($m['sender_account']['holder_name']) ? sanitize_text_field($m['sender_account']['holder_name']) : '';

        $app_db->query($app_db->prepare(
            "INSERT IGNORE INTO app_movimientos_bancarios 
            (fintoc_id, fecha_movimiento, descripcion, rut_remitente, nombre_remitente, monto, tipo, estado, saldo_disponible) 
            VALUES (%s, %s, %s, %s, %s, %f, 'ingreso', 'pendiente', %f)",
            $fintoc_id, 
            date('Y-m-d', strtotime($m['post_date'])), 
            sanitize_text_field($m['description']), 
            $rut_remitente,
            $nombre_remitente,
            $monto, 
            $monto
        ));
    }
}

// =================================================================================
// 3. SINCRONIZACIÓN MANUAL DIRECTA A FINTOC (Botón de UI)
// =================================================================================
add_action('wp_ajax_sincronizar_fintoc', 'tabolango_sincronizar_fintoc_manual');

function tabolango_sincronizar_fintoc_manual() {
    $rol = tabolango_get_user_role();
    if (!in_array($rol, [1, 2])) {
        wp_send_json(['status' => 'error', 'message' => 'No autorizado']); exit;
    }

    $app_db = tabolango_get_app_db();
    
    $url_accounts = "https://api.fintoc.com/v1/accounts?link_token=" . FINTOC_LINK_TOKEN;
    $res_accounts = wp_remote_get($url_accounts, [
        'headers' => ['Authorization' => FINTOC_SECRET],
        'timeout' => 15
    ]);
    
    if (is_wp_error($res_accounts)) wp_send_json(['status' => 'error', 'message' => 'Error con Fintoc.']);
    $accounts = json_decode(wp_remote_retrieve_body($res_accounts), true);
    if (empty($accounts)) wp_send_json(['status' => 'error', 'message' => 'No hay cuentas bancarias.']);

    $nuevos_ingresos = 0;

    foreach ($accounts as $account) {
        $acc_id = $account['id'];
        $url_movs = "https://api.fintoc.com/v1/accounts/{$acc_id}/movements?link_token=" . FINTOC_LINK_TOKEN;
        
        $res_movs = wp_remote_get($url_movs, [
            'headers' => ['Authorization' => FINTOC_SECRET],
            'timeout' => 15
        ]);
        
        if (!is_wp_error($res_movs)) {
            $movements = json_decode(wp_remote_retrieve_body($res_movs), true);
            
            if (is_array($movements)) {
                foreach ($movements as $m) {
                    if (!isset($m['id'])) continue;
                    $monto = floatval($m['amount']);
                    if ($monto <= 0) continue; 

                    $fintoc_id = sanitize_text_field($m['id']);
                    $existe = $app_db->get_var($app_db->prepare("SELECT id FROM app_movimientos_bancarios WHERE fintoc_id = %s", $fintoc_id));
                    
                    if (!$existe) {
                        // Extracción de datos del remitente
                        $rut_remitente = isset($m['sender_account']['holder_id']) ? sanitize_text_field($m['sender_account']['holder_id']) : '';
                        $nombre_remitente = isset($m['sender_account']['holder_name']) ? sanitize_text_field($m['sender_account']['holder_name']) : '';

                        $app_db->insert('app_movimientos_bancarios', [
                            'fintoc_id' => $fintoc_id,
                            'fecha_movimiento' => date('Y-m-d', strtotime($m['post_date'])),
                            'descripcion' => sanitize_text_field($m['description']),
                            'rut_remitente' => $rut_remitente,
                            'nombre_remitente' => $nombre_remitente,
                            'monto' => abs($monto),
                            'tipo' => 'ingreso',
                            'estado' => 'pendiente',
                            'saldo_disponible' => abs($monto)
                        ]);
                        $nuevos_ingresos++;
                    }
                }
            }
        }
    }
    wp_send_json(['status' => 'success', 'nuevos' => $nuevos_ingresos]);
}

// =================================================================================
// 4. MOTOR DE MATCH (Conciliación de Cuentas por Cobrar)
// =================================================================================
add_action('wp_ajax_conciliar_pago_tabolango', 'procesar_conciliacion_tabolango');

function procesar_conciliacion_tabolango() {
    $rol = tabolango_get_user_role();
    if (!in_array($rol, [1, 2])) {
        wp_send_json(['status' => 'error', 'message' => 'No tienes permisos.']); exit;
    }

    $app_db = tabolango_get_app_db();
    $id_mov = intval($_POST['id_movimiento'] ?? 0);
    $id_ped = intval($_POST['id_pedido'] ?? 0);
    $monto_solicitado  = floatval($_POST['monto_aplicado'] ?? 0);
    $usuario = wp_get_current_user()->user_email;

    if (!$id_mov || !$id_ped || $monto_solicitado <= 0) {
        wp_send_json(['status' => 'error', 'message' => 'Datos inválidos.']); exit;
    }

    $app_db->query("START TRANSACTION");

    try {
        $mov = $app_db->get_row($app_db->prepare("SELECT saldo_disponible FROM app_movimientos_bancarios WHERE id = %d FOR UPDATE", $id_mov));
        $ped = $app_db->get_row($app_db->prepare("SELECT total_venta, monto_pagado FROM pedidos_activos WHERE id_interno = %d FOR UPDATE", $id_ped));

        if (!$mov || !$ped) throw new Exception("Movimiento o pedido no existe.");
        
        // --- INICIO LÓGICA DE TOLERANCIA AL COBRE ---
        $diferencia_saldo = $monto_solicitado - $mov->saldo_disponible;
        $cierre_forzado = false;
        
        // Si el JS pide más de lo que hay en el banco, pero es por un "sencillo" (<= 100 pesos)
        if ($diferencia_saldo > 0 && $diferencia_saldo <= 100) {
            $monto_a_procesar = $mov->saldo_disponible; // Vaciamos exactamente el banco
            $cierre_forzado = true; // Forzamos el estado a 'Pagado' aunque falten 10 pesos
        } else {
            $monto_a_procesar = $monto_solicitado;
        }
        
        if ($monto_a_procesar <= 0) throw new Exception("No hay saldo para aplicar.");
        // --- FIN LÓGICA DE TOLERANCIA ---

        if ($mov->saldo_disponible < $monto_a_procesar) throw new Exception("Saldo insuficiente en el banco.");
        
        // Permitimos una tolerancia en la validación por si el sistema ajusta centavos
        $deuda_actual = $ped->total_venta - $ped->monto_pagado;
        if ($monto_a_procesar > ($deuda_actual + 100)) throw new Exception("Intento de sobrepago de factura.");

        // 1. Guardamos el ticket con el monto REAL
        $app_db->insert('app_conciliaciones', [
            'id_movimiento' => $id_mov, 
            'id_pedido_interno' => $id_ped,
            'monto_aplicado' => $monto_a_procesar, 
            'conciliado_por' => $usuario
        ]);

        // 2. Descontamos la plata real del banco
        $nuevo_saldo = $mov->saldo_disponible - $monto_a_procesar;
        $app_db->update('app_movimientos_bancarios', 
            ['saldo_disponible' => $nuevo_saldo, 'estado' => ($nuevo_saldo <= 0) ? 'conciliado' : 'parcial'], 
            ['id' => $id_mov]
        );

        // 3. Sumamos la plata real al pedido
        $nuevo_pagado = $ped->monto_pagado + $monto_a_procesar;
        
        // Si la suma cubre el total, o si activamos el "cierre forzado", o si queda debiendo 100 pesos o menos... ¡Se da por Pagado!
        $estado_pago = 'Parcial';
        if ($nuevo_pagado >= $ped->total_venta || $cierre_forzado || ($ped->total_venta - $nuevo_pagado) <= 100) {
            $estado_pago = 'Pagado';
        }
        
        $app_db->update('pedidos_activos', 
            ['monto_pagado' => $nuevo_pagado, 'estado_pago' => $estado_pago], 
            ['id_interno' => $id_ped]
        );

        $app_db->query("COMMIT");
        wp_send_json(['status' => 'success']);
    } catch (Exception $e) {
        $app_db->query("ROLLBACK");
        wp_send_json(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// =================================================================================
// 5. DESCARTAR MOVIMIENTO BANCARIO (IGNORAR)
// =================================================================================
add_action('wp_ajax_descartar_movimiento_tabolango', 'tabolango_descartar_movimiento');

function tabolango_descartar_movimiento() {
    $rol = tabolango_get_user_role();
    if (!in_array($rol, [1, 2])) { wp_send_json(['status' => 'error', 'message' => 'No autorizado']); exit; }

    $app_db = tabolango_get_app_db();
    $id_mov = intval($_POST['id_movimiento'] ?? 0);

    if ($id_mov) {
        $app_db->update('app_movimientos_bancarios', ['estado' => 'ignorado'], ['id' => $id_mov]);
        wp_send_json(['status' => 'success']);
    }
    wp_send_json(['status' => 'error', 'message' => 'ID inválido']);
}

// =================================================================================
// 6. DESHACER CONCILIACIÓN EN RÁFAGA (Soporta múltiples productos)
// =================================================================================
add_action('wp_ajax_deshacer_conciliacion_tabolango', 'tabolango_deshacer_conciliacion');

function tabolango_deshacer_conciliacion() {
    $rol = tabolango_get_user_role();
    if (!in_array($rol, [1, 2])) { wp_send_json(['status' => 'error', 'message' => 'No autorizado']); exit; }

    $app_db = tabolango_get_app_db();
    
    // Recibimos un string de IDs separados por coma (ej: "45,46,47")
    $ids_raw = sanitize_text_field($_POST['ids_conciliaciones'] ?? '');
    if (empty($ids_raw)) { wp_send_json(['status' => 'error', 'message' => 'IDs inválidos']); exit; }

    // Convertimos a array de números
    $ids_array = array_map('intval', explode(',', $ids_raw));

    $app_db->query("START TRANSACTION");

    try {
        // Hacemos el reverso de cada producto uno por uno
        foreach ($ids_array as $id_conciliacion) {
            if (!$id_conciliacion) continue;

            $conc = $app_db->get_row($app_db->prepare("SELECT * FROM app_conciliaciones WHERE id = %d FOR UPDATE", $id_conciliacion));
            if (!$conc) continue;

            $monto = floatval($conc->monto_aplicado);

            // Devolvemos la plata al Banco
            $mov = $app_db->get_row($app_db->prepare("SELECT saldo_disponible, monto FROM app_movimientos_bancarios WHERE id = %d FOR UPDATE", $conc->id_movimiento));
            if ($mov) {
                $nuevo_saldo = $mov->saldo_disponible + $monto;
                $estado_mov = ($nuevo_saldo >= $mov->monto) ? 'pendiente' : 'parcial';
                $app_db->update('app_movimientos_bancarios', ['saldo_disponible' => $nuevo_saldo, 'estado' => $estado_mov], ['id' => $conc->id_movimiento]);
            }

            // Devolvemos la deuda al Pedido/Producto
            $ped = $app_db->get_row($app_db->prepare("SELECT total_venta, monto_pagado FROM pedidos_activos WHERE id_interno = %d FOR UPDATE", $conc->id_pedido_interno));
            if ($ped) {
                $nuevo_pagado = max(0, $ped->monto_pagado - $monto);
                $estado_pago = ($nuevo_pagado <= 0) ? 'Pendiente' : 'Parcial';
                $app_db->update('pedidos_activos', ['monto_pagado' => $nuevo_pagado, 'estado_pago' => $estado_pago], ['id_interno' => $conc->id_pedido_interno]);
            }

            // Borramos el ticket de conciliación
            $app_db->delete('app_conciliaciones', ['id' => $id_conciliacion]);
        }

        $app_db->query("COMMIT");
        wp_send_json(['status' => 'success']);
    } catch (Exception $e) {
        $app_db->query("ROLLBACK");
        wp_send_json(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>