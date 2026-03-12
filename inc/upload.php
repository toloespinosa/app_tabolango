<?php
require_once 'auth.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (file_exists('notifications.php')) {
    include_once('notifications.php');
}

$pdf_libs_disponibles = false;
if (class_exists('\setasign\Fpdi\Fpdi') && class_exists('FPDF')) {
    $pdf_libs_disponibles = true;
}

// 🔥 URL BASE DINÁMICA: Detecta Local, Producción o ERP automáticamente 🔥
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) ? "https" : "http";
$ruta_directorio = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$DOMINIO_BASE = $protocolo . "://" . $_SERVER['HTTP_HOST'] . $ruta_directorio . "/";

$action = $_POST['action'] ?? '';
$id_pedido = $_POST['id_pedido'] ?? '';

// --- 1. ELIMINAR ---
if ($action === 'delete_document') {
    $tipo = $_POST['tipo'] ?? '';
    $columna = ($tipo === 'factura') ? 'url_factura' : 'url_guia';
    
    $stmt = $conn->prepare("SELECT $columna FROM pedidos_activos WHERE id_pedido = ?");
    $stmt->bind_param("s", $id_pedido);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    if ($res && !empty($res[$columna])) {
        // 🔥 FIX: Extracción inteligente de la ruta física (sin importar el dominio)
        $partes_path = explode('uploads/', $res[$columna]);
        $path = (count($partes_path) > 1) ? 'uploads/' . end($partes_path) : '';

        if ($path && file_exists($path)) { unlink($path); }
        
        $sql_upd = "UPDATE pedidos_activos SET $columna = NULL " . ($tipo === 'factura' ? ", numero_factura = NULL " : "") . " WHERE id_pedido = ?";
        $stmt_upd = $conn->prepare($sql_upd);
        $stmt_upd->bind_param("s", $id_pedido);
        $stmt_upd->execute();
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Documento no encontrado"]);
    }
    exit;
}

// --- 2. SUBIR GUÍA ---
if ($action === 'upload_guia_despacho') {
    $numero_guia = $_POST['numero_guia'] ?? null;
    $url_final = null;
    $hubo_cambios = false;

    if (isset($_FILES['foto_guia']) && $_FILES['foto_guia']['error'] === UPLOAD_ERR_OK) {
        $folder = 'uploads/guia_de_despacho/'; 
        if (!file_exists($folder)) mkdir($folder, 0777, true);
        
        $file_info = pathinfo($_FILES['foto_guia']['name']);
        $ext = strtolower($file_info['extension']);
        if (empty($ext)) $ext = 'jpg';

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) { 
            echo json_encode(["status" => "error", "message" => "Formato no permitido"]); 
            exit; 
        }
        
        $filename = "guia_" . $id_pedido . "_" . time() . "." . $ext;
        
        if (move_uploaded_file($_FILES['foto_guia']['tmp_name'], $folder . $filename)) {
            // 🔥 FIX: Guardado con dominio dinámico
            $url_final = $DOMINIO_BASE . $folder . $filename;
        } else {
            echo json_encode(["status" => "error", "message" => "Error al guardar archivo"]);
            exit;
        }
    }

    if ($url_final && $numero_guia) {
        $stmt = $conn->prepare("UPDATE pedidos_activos SET url_guia = ?, numero_guia = ? WHERE id_pedido = ?");
        $stmt->bind_param("sss", $url_final, $numero_guia, $id_pedido);
        $hubo_cambios = $stmt->execute();
    } elseif ($url_final) {
        $stmt = $conn->prepare("UPDATE pedidos_activos SET url_guia = ? WHERE id_pedido = ?");
        $stmt->bind_param("ss", $url_final, $id_pedido);
        $hubo_cambios = $stmt->execute();
    } elseif ($numero_guia) {
        $stmt = $conn->prepare("UPDATE pedidos_activos SET numero_guia = ? WHERE id_pedido = ?");
        $stmt->bind_param("ss", $numero_guia, $id_pedido);
        $hubo_cambios = $stmt->execute();
    } else {
        echo json_encode(["status" => "error", "message" => "No se enviaron datos para actualizar"]);
        exit;
    }

    if ($hubo_cambios) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "No se pudo actualizar la BD: " . $conn->error]);
    }
    exit;
}

// --- 3. ACTUALIZAR FACTURA ---
if ($action === 'update_admin_order') {
    $num_factura = $_POST['numero_factura'] ?? ''; 
    $url_final = null;
    $debug_info = "Sin cambios"; 

    if (isset($_FILES['pdf_factura']) && $_FILES['pdf_factura']['error'] === UPLOAD_ERR_OK) {
        $tmp_path = $_FILES['pdf_factura']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['pdf_factura']['name'], PATHINFO_EXTENSION));

        if ($ext === 'xml') {
            $xmlContent = file_get_contents($tmp_path);
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);
            if ($xml) {
                $folios = $xml->xpath('//folio | //Folio | //*[local-name()="Folio"]');
                if (!empty($folios)) {
                    $num_factura = (string)$folios[0];
                    $debug_info = "Extraído de XML: " . $num_factura;
                }
            }
            $carpeta = "uploads/facturas_xml/"; 
        } else {
            $carpeta = "uploads/facturas/";
            $debug_info = "Archivo PDF/Img recibido";
        }

        if (!file_exists($carpeta)) { mkdir($carpeta, 0777, true); }
        $nombre_archivo = "fact_" . $id_pedido . "_" . time() . "." . $ext;
        if (move_uploaded_file($tmp_path, $carpeta . $nombre_archivo)) {
            // 🔥 FIX: Guardado con dominio dinámico
            $url_final = $DOMINIO_BASE . $carpeta . $nombre_archivo;
        }
    }
    
    if ($url_final) {
        $sql = "UPDATE pedidos_activos SET numero_factura = ?, url_factura = ? WHERE id_pedido = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $num_factura, $url_final, $id_pedido);
    } else {
        $sql = "UPDATE pedidos_activos SET numero_factura = ? WHERE id_pedido = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $num_factura, $id_pedido);
    }
    echo json_encode(["status" => $stmt->execute() ? "success" : "error", "numero_factura" => $num_factura, "info" => $debug_info, "url" => $url_final]);
    exit;
}

// --- 4. ACCIÓN DE OPERARIO (ENTREGA FINAL) ---
if (isset($_POST['qr_token'])) {
    $token = $_POST['qr_token'];
    $lat_gps = $_POST['lat_gps'] ?? null;
    $lng_gps = $_POST['lng_gps'] ?? null;
    $forzado_admin = $_POST['forzado_admin'] ?? '0';
    $nombre_receptor = $_POST['nombre_receptor'] ?? '';
    $rut_receptor = $_POST['rut_receptor'] ?? '';
    $obs_input = $_POST['observaciones'] ?? '';
    $email_final = $_POST['email_envio'] ?? '';

    // Obtener datos actuales
    $sql_join = "
        SELECT 
            p.id_pedido, 
            p.cliente, 
            p.estado, 
            p.observaciones, 
            p.url_guia, 
            c.lat_despacho, 
            c.lng_despacho 
        FROM pedidos_activos p
        LEFT JOIN clientes c ON p.id_interno_cliente = c.id_interno
        WHERE p.qr_token = ? 
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql_join);
    
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Error SQL: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("s", $token);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();

    if (!$pedido) { echo json_encode(["status" => "error", "message" => "Pedido no encontrado"]); exit; }

    $id_p = $pedido['id_pedido'];
    $nombre_cliente = $pedido['cliente'];
    
    $actual = mb_strtolower(trim($pedido['estado']), 'UTF-8');
    $nuevo = "";
    $sql_gps_part = "";
    
    $foto_evidencia_url = null;
    $ruta_foto_evidencia = null;
    $pdf_firmado_url = null;
    $ruta_pdf_fisica = null;

    // Determinar nuevo estado
    if ($actual == 'confirmado') {
        $nuevo = 'En Preparación';
        if ($lat_gps) $sql_gps_part = ", lat_preparacion = '$lat_gps', lng_preparacion = '$lng_gps'";
    } elseif (strpos($actual, 'preparaci') !== false) {
        $nuevo = 'En Despacho';
    } elseif (strpos($actual, 'despacho') !== false) {
        $nuevo = 'Entregado';
        
        // Coordenadas
        if ($forzado_admin === '1') {
            $lat_final = $pedido['lat_despacho'];
            $lng_final = $pedido['lng_despacho'];
            
            $obs = ($pedido['observaciones'] ?? '') . " | [!] Entrega forzada por Admin.";
            $sql_gps_part = ", lat_entrega = '$lat_final', lng_entrega = '$lng_final', observaciones = '" . $conn->real_escape_string($obs) . "'";
        } else {
            if ($lat_gps) $sql_gps_part = ", lat_entrega = '$lat_gps', lng_entrega = '$lng_gps'";
        }

        // Subir foto evidencia
        if (isset($_FILES['foto'])) {
            $folder = 'uploads/evidencia_entrega/';
            if (!file_exists($folder)) mkdir($folder, 0777, true);
            $filename = "ev_" . $id_p . "_" . time() . ".jpg";
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $folder . $filename)) {
                $ruta_foto_evidencia = $folder . $filename;
                // 🔥 FIX: Evidencia con dominio dinámico
                $foto_evidencia_url = $DOMINIO_BASE . $folder . $filename;
            }
        }

        // Generar PDF Firmado
        if (isset($_FILES['img_firma']) && !empty($pedido['url_guia'])) {
            $folder_firmas = 'uploads/temp_firmas/';
            if (!file_exists($folder_firmas)) mkdir($folder_firmas, 0777, true);
            $firma_path = $folder_firmas . "s_" . $id_p . "_" . time() . ".png";
            move_uploaded_file($_FILES['img_firma']['tmp_name'], $firma_path);

            // 🔥 FIX CRÍTICO: Buscar archivo independientemente del dominio
            $partes_guia = explode('uploads/', $pedido['url_guia']);
            $ruta_relativa_guia = (count($partes_guia) > 1) ? 'uploads/' . end($partes_guia) : '';
            
            if (!empty($ruta_relativa_guia) && file_exists($ruta_relativa_guia) && $pdf_libs_disponibles) {
                try {
                    $pdf = new \setasign\Fpdi\Fpdi();
                    $total_paginas = $pdf->setSourceFile($ruta_relativa_guia);
                    $templateId = $pdf->importPage($total_paginas);
                    $size = $pdf->getTemplateSize($templateId);
                    $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
                    
                    $pdf->AddPage($orientation, array($size['width'], $size['height']));
                    $pdf->useTemplate($templateId);

                    // Estampar "Entregado"
                    $logo_local = 'media/logo_tabolango.png';
                    $stampX = $size['width'] - 65; 
                    $stampY = $size['height'] - 35; 
                    
                    $pdf->SetTextColor(80, 80, 80);
                    $pdf->SetDrawColor(80, 80, 80);
                    $pdf->SetLineWidth(0.5);
                    $pdf->Rect($stampX, $stampY, 55, 18, 'D'); 
                    
                    if (file_exists($logo_local)) {
                        $pdf->Image($logo_local, $stampX + 2, $stampY + 2, 14); 
                    }
                    
                    $pdf->SetFont('Arial', 'B', 12);
                    $pdf->SetXY($stampX + 18, $stampY + 4);
                    $pdf->Cell(35, 5, "ENTREGADO", 0, 1, 'C');
                    
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->SetXY($stampX + 18, $stampY + 10);
                    $pdf->Cell(35, 4, date("d/m/Y H:i"), 0, 0, 'C');

                    // Estampar Firma y datos
                    $pdf->SetTextColor(0, 0, 0); 
                    $y_pos = $size['height'] - 60; 
                    $x_pos = 10;
                    $pdf->Image($firma_path, $x_pos, $y_pos, 80);
                    
                    $pdf->SetFont('Arial', 'B', 11);
                    $x_text = $x_pos + 85; 
                    
                    $pdf->SetXY($x_text, $y_pos + 15);
                    $pdf->Cell(0, 6, "Recibido por: " . iconv('UTF-8', 'ISO-8859-1', $nombre_receptor), 0, 1);
                    $pdf->SetX($x_text);
                    $pdf->Cell(0, 6, "RUT: " . $rut_receptor, 0, 1);
                    $pdf->SetX($x_text);
                    $pdf->Cell(0, 6, "Fecha: " . date('d/m/Y H:i'), 0, 1);

                    if (!empty($obs_input)) {
                        $pdf->SetX($x_text);
                        $pdf->SetFont('Arial', 'I', 9);
                        $texto_obs = "Obs: " . iconv('UTF-8', 'ISO-8859-1', $obs_input);
                        $pdf->MultiCell(90, 5, $texto_obs); 
                    }

                    $folder_final = 'uploads/guia_firmada/';
                    if (!file_exists($folder_final)) mkdir($folder_final, 0777, true);
                    $nombre_pdf_final = "recepcion_" . $id_p . "_" . time() . ".pdf";
                    $ruta_pdf_final = $folder_final . $nombre_pdf_final;
                    
                    $pdf->Output('F', $ruta_pdf_final);
                    // 🔥 FIX: Dominio dinámico para el PDF de recepción firmado
                    $pdf_firmado_url = $DOMINIO_BASE . $ruta_pdf_final;
                    $ruta_pdf_fisica = $ruta_pdf_final;

                    // Enviar Email al Cliente
                    if (!empty($email_final) && $email_final !== 'SKIP' && filter_var($email_final, FILTER_VALIDATE_EMAIL)) {
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'notificaciones@tabolango.cl'; 
                            $mail->Password   = 'ychh fnhy hhew stgw'; 
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = 587;
                            $mail->CharSet    = 'UTF-8';

                            $mail->setFrom('notificaciones@tabolango.cl', 'Tabolango Despachos');
                            $mail->addAddress($email_final, $nombre_cliente);

                            if ($ruta_pdf_fisica && file_exists($ruta_pdf_fisica)) {
                                $mail->addAttachment($ruta_pdf_fisica, "Guia_Despacho_$id_p.pdf");
                            }
                            if ($ruta_foto_evidencia && file_exists($ruta_foto_evidencia)) {
                                $mail->addAttachment($ruta_foto_evidencia, "Foto_Despacho.jpg");
                            }

                            $mail->isHTML(true);
                            $mail->Subject = "✅ Tu pedido #$id_p ha sido entregado correctamente";
                            
                            $htmlContent = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 10px; overflow: hidden;'>
                                <div style='background-color: #ffffff; padding: 20px; text-align: center; border-bottom: 4px solid #0F4B29;'>
                                    <img src='https://tabolango.cl/media/logo_tabolango.png' alt='Tabolango' style='max-width: 150px;'>
                                </div>
                                <div style='background-color: #0F4B29; padding: 10px; text-align: center;'>
                                    <h2 style='color: #ffffff; margin: 0; font-size: 20px;'>¡Entrega Exitosa!</h2>
                                </div>
                                <div style='padding: 25px; background-color: #f9f9f9;'>
                                    <p style='color: #333; font-size: 16px;'>Hola <strong>$nombre_cliente</strong>,</p>
                                    <p style='color: #555; line-height: 1.5;'>
                                        Te informamos que tu pedido <strong>#$id_p</strong> ha sido entregado exitosamente.
                                    </p>
                                    <div style='background-color: #fff; padding: 15px; border-left: 4px solid #27ae60; margin: 20px 0;'>
                                        <p style='margin: 5px 0; color: #555;'><strong>Recibido por:</strong> $nombre_receptor</p>
                                        <p style='margin: 5px 0; color: #555;'><strong>RUT:</strong> $rut_receptor</p>
                                        <p style='margin: 5px 0; color: #555;'><strong>Fecha:</strong> " . date('d/m/Y H:i') . "</p>
                                        " . ($obs_input ? "<p style='margin: 5px 0; color: #777; font-style: italic;'><strong>Nota:</strong> $obs_input</p>" : "") . "
                                    </div>
                                    <p style='color: #555;'>Adjuntamos a este correo la guía firmada y la foto del despacho.</p>
                                </div>
                                <div style='background-color: #eee; padding: 15px; text-align: center; font-size: 12px; color: #888;'>
                                    © " . date('Y') . " Tabolango.
                                </div>
                            </div>";

                            $mail->Body = $htmlContent;
                            $mail->AltBody = "Pedido #$id_p entregado.";
                            $mail->send();
                        } catch (Exception $e) { error_log("Mail Error: " . $mail->ErrorInfo); }
                    }

                } catch (Exception $e) { error_log($e->getMessage()); }
            }
            if(file_exists($firma_path)) unlink($firma_path);
        }
    }

    if ($nuevo) {
        $update_fields = ["estado = '$nuevo'"];
        
        if ($sql_gps_part) {
            $gps_clean = ltrim($sql_gps_part, ', ');
            if ($gps_clean) $update_fields[] = $gps_clean;
        }
        
        if ($foto_evidencia_url) $update_fields[] = "evidencia_entrega = '$foto_evidencia_url'";
        if ($pdf_firmado_url) $update_fields[] = "url_factura_firmada = '$pdf_firmado_url'";

        $obs_historial = "";
        if ($nombre_receptor) $obs_historial .= " | Recibido por: $nombre_receptor ($rut_receptor)";
        if ($obs_input) $obs_historial .= " | Nota Entrega: $obs_input";

        if ($obs_historial) {
            $update_fields[] = "observaciones = CONCAT(IFNULL(observaciones, ''), '" . $conn->real_escape_string($obs_historial) . "')";
        }

        if (!empty($obs_input)) {
            $update_fields[] = "observacion_entrega = '" . $conn->real_escape_string($obs_input) . "'";
        }

        $sql_final = "UPDATE pedidos_activos SET " . implode(', ', $update_fields) . " WHERE id_pedido = '$id_p'";
        
        if ($conn->query($sql_final)) {
            
            $titulo = "";
            $cuerpo = "";
            $categoria = ""; 

            if ($nuevo === 'Entregado') {
                $titulo = "✅ Pedido Entregado";
                $cuerpo = "Cliente: $nombre_cliente | El pedido ha sido entregado exitosamente.";
                $categoria = "notify_pedido_entregado";
            } 
            elseif ($nuevo === 'En Preparación') {
                $titulo = "📦 Pedido En Preparación";
                $cuerpo = "Cliente: $nombre_cliente | Se ha comenzado a armar el pedido.";
                $categoria = "notify_cambio_estado";
            } 
            elseif ($nuevo === 'En Despacho') {
                $titulo = "🚚 Pedido En Despacho";
                $cuerpo = "Cliente: $nombre_cliente | El pedido va en ruta hacia el destino.";
                $categoria = "notify_cambio_estado";
            } 
            else {
                $titulo = "🔄 Estado: $nuevo";
                $cuerpo = "Cliente: $nombre_cliente | El estado del pedido ha cambiado.";
                $categoria = "notify_cambio_estado";
            }

            $destinatario = (trim(strtolower($nombre_cliente)) === 'prueba') ? 'jandres@tabolango.cl' : null;

            if ($destinatario !== null) {
                $titulo = "🧪 [TEST] " . $titulo;
            }

            if (function_exists('enviarNotificacionFCM')) {
                enviarNotificacionFCM($destinatario, $titulo, $cuerpo, "", $categoria);
            }

            echo json_encode(["status" => "success", "nuevo_estado" => $nuevo]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error BD: " . $conn->error]);
        }
    }
    exit;
}

if ($action === 'upload_evidencia_manual') {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $folder = 'uploads/evidencia_entrega/';
        if (!file_exists($folder)) mkdir($folder, 0777, true);
        $filename = "ev_manual_" . $id_pedido . "_" . time() . ".jpg";
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $folder . $filename)) {
            // 🔥 FIX: Dominio dinámico para carga manual
            $url = $DOMINIO_BASE . $folder . $filename;
            $stmt = $conn->prepare("UPDATE pedidos_activos SET evidencia_entrega = ? WHERE id_pedido = ?");
            $stmt->bind_param("ss", $url, $id_pedido);
            echo json_encode(["status" => $stmt->execute() ? "success" : "error"]);
        } else { echo json_encode(["status" => "error", "message" => "Error al guardar"]); }
    }
    exit;
}

$conn->close();
?>