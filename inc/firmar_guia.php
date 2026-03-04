<?php
require_once 'auth.php';
/**
 * FIRMADOR SIMPLE DTE SII
 * Este script agrega el Timbre (TED) y Firma Digitalmente el XML.
 */

// --- CONFIGURACIÓN CORREGIDA ---

// 1. Rutas de tus archivos (Asegúrate que la ruta sea relativa al script o absoluta)
$archivo_xml_entrada = 'Guia_Caso_3.xml';           // El XML que generaste con el script anterior
$archivo_caf         = 'uploads/certificados/caf_52.xml';  // Tu CAF
$archivo_cert        = 'uploads/certificados/certificado.pfx'; // Tu certificado PFX
$clave_cert          = 'Sofia2020'; // La contraseña que te dieron al comprar la firma
$rut_emisor          = '77121854-7';  // IMPORTANTE: RUT de la empresa, no del usuario.

// --- EJECUCIÓN ---
try {
    echo "1. Iniciando proceso para: $archivo_xml_entrada\n";

    if (!file_exists($archivo_xml_entrada)) throw new Exception("No existe el XML de entrada: $archivo_xml_entrada");
    if (!file_exists($archivo_caf)) throw new Exception("No existe el CAF: $archivo_caf");
    if (!file_exists($archivo_cert)) throw new Exception("No existe el Certificado: $archivo_cert");

    // Cargar XML
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = false;
    $dom->load($archivo_xml_entrada);

    // Leer contenido del CAF
    $caf_content = file_get_contents($archivo_caf);

    // Generar e Insertar Timbre (TED)
    echo "2. Generando Timbre Electrónico (TED)...\n";
    $ted_node = generarTED($dom, $caf_content, $rut_emisor);
    
    $root = $dom->documentElement;
    $documento = $root->getElementsByTagName('Documento')->item(0);
    $documento->appendChild($ted_node);

    // Firmar el XML Completo
    echo "3. Firmando digitalmente el documento...\n";
    $xml_con_ted = $dom->saveXML();
    $xml_firmado_str = firmarXML($xml_con_ted, $archivo_cert, $clave_cert);
    
    // Guardar
    $nombre_final = str_replace('.xml', '_firmado.xml', $archivo_xml_entrada);
    file_put_contents($nombre_final, $xml_firmado_str);
    
    echo "--------------------------------------------------\n";
    echo "¡ÉXITO! Archivo guardado como: $nombre_final\n";
    echo "--------------------------------------------------\n";

} catch (Exception $e) {
    echo "!!! ERROR !!!: " . $e->getMessage() . "\n";
}

// =================================================================
// 3. FUNCIONES (NO TOCAR)
// =================================================================

function generarTED($dom, $caf_xml, $rut_emisor) {
    // 1. LIMPIEZA Y BÚSQUEDA DE LA CLAVE PRIVADA (RSASK)
    // Eliminamos espacios extraños al inicio/final
    $caf_limpio = trim($caf_xml);
    
    // CORRECCIÓN: Usamos el modificador 's' (dotall) solamente.
    if (!preg_match('/<RSASK>(.*?)<\/RSASK>/s', $caf_limpio, $matches)) {
        // Intento 2: Búsqueda insensible a mayúsculas
        if (!preg_match('/<rsask>(.*?)<\/rsask>/is', $caf_limpio, $matches)) {
             throw new Exception("ERROR: No encuentro la etiqueta <RSASK> en el archivo CAF. Verifica que el archivo no esté vacío.");
        }
    }
    
    $pkey_raw = trim($matches[1]);

    // 2. VALIDAR QUE LA CLAVE SEA UN PEM VÁLIDO
    $pkey_resource = openssl_get_privatekey($pkey_raw);
    
    // Si falla, intentamos formatearla agregando las cabeceras si faltan
    if (!$pkey_resource) {
        $pkey_raw = str_replace(["-----BEGIN RSA PRIVATE KEY-----", "-----END RSA PRIVATE KEY-----", " "], "", $pkey_raw);
        $pkey_raw = "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($pkey_raw, 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
        $pkey_resource = openssl_get_privatekey($pkey_raw);
        
        if (!$pkey_resource) {
            // Último intento: obtener el error específico de OpenSSL
            while ($msg = openssl_error_string()) echo "OpenSSL Error: $msg\n";
            throw new Exception("La clave privada dentro del CAF no es válida o está corrupta.");
        }
    }

    // 3. EXTRAER DATOS DEL XML PARA EL NODO DD
    $xpath = new DOMXPath($dom);
    $folio = $xpath->query('//Folio')->item(0)->nodeValue;
    $tipo  = $xpath->query('//TipoDTE')->item(0)->nodeValue;
    $fch   = $xpath->query('//FchEmis')->item(0)->nodeValue;
    $rz    = $xpath->query('//RUTRecep')->item(0)->nodeValue;
    $rzn   = mb_substr($xpath->query('//RznSocRecep')->item(0)->nodeValue, 0, 40, 'UTF-8');
    $mnt   = $xpath->query('//MntTotal')->item(0)->nodeValue;
    $itm   = mb_substr($xpath->query('//NmbItem')->item(0)->nodeValue, 0, 40, 'UTF-8');

    // 4. EXTRAER EL NODO <CAF> COMPLETO
    // Buscamos exactamente desde <CAF version... hasta </CAF>
    if (!preg_match('/<CAF\b[^>]*>(.*?)<\/CAF>/s', $caf_xml, $caf_matches)) {
        throw new Exception("No se pudo extraer el bloque <CAF> del archivo.");
    }
    $caf_nodo_string = $caf_matches[0];

    // 5. CONSTRUIR STRING DD (Datos Documento)
    $dd_xml = "<DD>"
        . "<RE>" . $rut_emisor . "</RE>"
        . "<TD>" . $tipo . "</TD>"
        . "<F>"  . $folio . "</F>"
        . "<FE>" . $fch . "</FE>"
        . "<RR>" . $rz . "</RR>"
        . "<RSR>" . $rzn . "</RSR>"
        . "<MNT>" . $mnt . "</MNT>"
        . "<IT1>" . $itm . "</IT1>"
        . $caf_nodo_string
        . "<TSTED>" . date('Y-m-d\TH:i:s') . "</TSTED>"
        . "</DD>";

    // 6. FIRMAR DD (SHA1) CON LA CLAVE DEL CAF
    if (!openssl_sign($dd_xml, $firma_ted, $pkey_resource, OPENSSL_ALGO_SHA1)) {
        throw new Exception("Falló la firma del TED con la clave del CAF.");
    }
    $firma_b64 = base64_encode($firma_ted);

    // 7. INSERTAR EN EL DOM
    $ted = $dom->createElement('TED');
    $ted->setAttribute('version', '1.0');
    
    $fragment = $dom->createDocumentFragment();
    $fragment->appendXML($dd_xml);
    $ted->appendChild($fragment);
    
    $frmt = $dom->createElement('FRMT', $firma_b64);
    $frmt->setAttribute('alg', 'SHA1');
    $ted->appendChild($frmt);

    return $ted;
}

function firmarXML($xml_content, $cert_path, $cert_pass) {
    if (!file_exists($cert_path)) throw new Exception("No encuentro certificado en: $cert_path");
    
    $pkcs12 = file_get_contents($cert_path);
    if (!openssl_pkcs12_read($pkcs12, $certs, $cert_pass)) {
        throw new Exception("No se pudo leer el certificado PFX. ¿Clave incorrecta?");
    }
    
    $cert_data = openssl_x509_parse($certs['cert']);
    $priv_key = $certs['pkey'];
    $pub_cert_lines = explode("\n", $certs['cert']);
    $pub_cert = '';
    foreach($pub_cert_lines as $line) {
        if (strpos($line, 'BEGIN CERTIFICATE') === false && strpos($line, 'END CERTIFICATE') === false) {
            $pub_cert .= trim($line);
        }
    }

    $dom = new DOMDocument();
    $dom->formatOutput = false;
    $dom->preserveWhiteSpace = true; 
    $dom->loadXML($xml_content);
    
    $doc_node = $dom->getElementsByTagName('Documento')->item(0);
    $obj_id = $doc_node->getAttribute('ID');

    // Canonicalizar para firmar
    $c14n = $doc_node->C14N();
    $digest = base64_encode(sha1($c14n, true));

    $signed_info = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">' .
        '<CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>' .
        '<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>' .
        '<Reference URI="#' . $obj_id . '">' .
        '<Transforms>' .
        '<Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>' .
        '</Transforms>' .
        '<DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>' .
        '<DigestValue>' . $digest . '</DigestValue>' .
        '</Reference>' .
        '</SignedInfo>';

    openssl_sign($signed_info, $signature, $priv_key, OPENSSL_ALGO_SHA1);
    $signature_b64 = base64_encode($signature);

    $sig_xml = '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">' .
        $signed_info .
        '<SignatureValue>' . $signature_b64 . '</SignatureValue>' .
        '<KeyInfo>' .
        '<KeyValue><RSAKeyValue><Modulus>' . base64_encode($cert_data['subject']['N'] ?? $cert_data['details']['rsa']['n']) . '</Modulus><Exponent>' . base64_encode($cert_data['subject']['E'] ?? $cert_data['details']['rsa']['e']) . '</Exponent></RSAKeyValue></KeyValue>' .
        '<X509Data><X509Certificate>' . $pub_cert . '</X509Certificate></X509Data>' .
        '</KeyInfo>' .
        '</Signature>';

    return str_replace('</DTE>', $sig_xml . '</DTE>', $xml_content);
}
?>