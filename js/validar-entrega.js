(function () {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    const API_URL = window.getApi('api.php');
    const UPLOAD_URL = window.getApi('upload.php');
    const API_USERS = window.getApi('usuarios.php');
    const CLIENT_API_URL = window.getApi('detalle-cliente.php');

    let pedidoActual = null;
    let ultimaUbicacion = { lat: null, lng: null };
    let firmaGuardadaBlob = null;
    let seHaFirmado = false;
    let entregaForzada = false;
    let esAdminGlobal = false;

    // VARIABLES TEMP
    let tempNombre = "";
    let tempRut = "";
    let tempObs = "";
    let emailClienteDetectado = "";

    window.finalizarExito = function () {
        document.getElementById('order-ui').style.display = 'none';
        document.getElementById('custom-modal').style.display = 'none';
        document.getElementById('gps-modal').style.display = 'none';
        document.getElementById('email-modal').style.display = 'none';
        document.getElementById('success-screen').style.display = 'block';
        setTimeout(() => location.reload(), 2000);
    };

    const canvasFirma = document.getElementById('canvas-firma');
    const ctxFirma = canvasFirma.getContext('2d');
    let dibujando = false;

    // REEMPLAZA TU ANTIGUA FUNCIÓN trimCanvas POR ESTA:
    function normalizarFirma(c) {
        // --- CONFIGURACIÓN: TAMAÑO FIJO DE SALIDA ---
        const ANCHO_FINAL = 800;
        const ALTO_FINAL = 400;
        const PADDING = 20; // Margen interno para que la firma no toque los bordes
        // --------------------------------------------

        const ctx = c.getContext('2d');
        const w = c.width;
        const h = c.height;
        const imageData = ctx.getImageData(0, 0, w, h);
        const data = imageData.data;

        // 1. ESCANEAR: Encontrar los límites del dibujo (Bounding Box)
        let minX = w, minY = h, maxX = 0, maxY = 0;
        let found = false;

        for (let y = 0; y < h; y++) {
            for (let x = 0; x < w; x++) {
                if (data[(y * w + x) * 4 + 3] > 0) { // Si el pixel no es transparente
                    if (x < minX) minX = x;
                    if (x > maxX) maxX = x;
                    if (y < minY) minY = y;
                    if (y > maxY) maxY = y;
                    found = true;
                }
            }
        }

        // Si el usuario no dibujó nada, devolvemos un canvas vacío del tamaño correcto
        if (!found) {
            const empty = document.createElement('canvas');
            empty.width = ANCHO_FINAL;
            empty.height = ALTO_FINAL;
            return empty;
        }

        // Dimensiones originales del garabato
        const contentW = maxX - minX + 1;
        const contentH = maxY - minY + 1;

        // 2. CALCULAR ESCALA: ¿Cuánto debemos agrandar/achicar para que quepa?
        // Calculamos el factor de escala manteniendo la proporción (aspect ratio)
        const scaleX = (ANCHO_FINAL - (PADDING * 2)) / contentW;
        const scaleY = (ALTO_FINAL - (PADDING * 2)) / contentH;
        const scale = Math.min(scaleX, scaleY); // Usamos el menor para que quepa todo

        // Nuevas dimensiones del garabato escalado
        const scaledW = contentW * scale;
        const scaledH = contentH * scale;

        // 3. CENTRAR: Calcular posición X e Y para que quede al medio
        const posX = (ANCHO_FINAL - scaledW) / 2;
        const posY = (ALTO_FINAL - scaledH) / 2;

        // 4. DIBUJAR: Crear el canvas final estandarizado
        const finalCanvas = document.createElement('canvas');
        finalCanvas.width = ANCHO_FINAL;
        finalCanvas.height = ALTO_FINAL;
        const fCtx = finalCanvas.getContext('2d');

        // (Opcional) Si quieres ver el fondo blanco en el PDF, descomenta esto:
        // fCtx.fillStyle = "white";
        // fCtx.fillRect(0,0, ANCHO_FINAL, ALTO_FINAL);

        // Dibujamos el recorte original -> Escalado y Centrado en el nuevo canvas
        fCtx.drawImage(c,
            minX, minY, contentW, contentH,  // Qué parte tomamos del original
            posX, posY, scaledW, scaledH     // Dónde y de qué tamaño lo pegamos
        );

        return finalCanvas;
    }

    function normalizarTexto(txt) {
        if (!txt) return "";
        return txt.trim().toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    }

    const inputRut = document.getElementById('input-rut-rx');
    if (inputRut) {
        inputRut.addEventListener('input', function (e) {
            let valor = e.target.value.replace(/[^0-9kK]/g, '');
            if (valor.length > 1) {
                const cuerpo = valor.slice(0, -1);
                const dv = valor.slice(-1).toUpperCase();
                e.target.value = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, ".") + "-" + dv;
            } else { e.target.value = valor.toUpperCase(); }
        });
    }

    function obtenerEmailLimpio() {
        const bridge = document.getElementById('session-email-bridge');
        if (!bridge) return "";
        let contenido = bridge.textContent || bridge.innerText;
        const match = contenido.match(/current_user_email=([^;]+)/);
        if (match && match[1]) return match[1].replace(/["']/g, '').trim().toLowerCase();
        const emailMatch = contenido.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
        return emailMatch ? emailMatch[0].toLowerCase().trim() : "";
    }

    function obtenerGPS() {
        return new Promise((resolve) => {
            if (!navigator.geolocation) return resolve({ lat: null, lng: null });
            navigator.geolocation.getCurrentPosition(
                (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
                (err) => resolve({ lat: null, lng: null }),
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        });
    }

    function calcularDistancia(lat1, lon1, lat2, lon2) {
        if (!lat1 || !lon1 || !lat2 || !lon2) return 999999;
        const R = 6371e3;
        const p1 = lat1 * Math.PI / 180;
        const p2 = lat2 * Math.PI / 180;
        const dp = (lat2 - lat1) * Math.PI / 180;
        const dl = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dp / 2) * Math.sin(dp / 2) + Math.cos(p1) * Math.cos(p2) * Math.sin(dl / 2) * Math.sin(dl / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    async function cargarPedido() {
        if (!token) return;
        try {
            obtenerGPS();
            const userEmail = obtenerEmailLimpio();

            // Validar admin
            try {
                // SOLUCIÓN: Separador dinámico
                const sepUsers = API_USERS.includes('?') ? '&' : '?';
                const resUsers = await fetch(`${API_USERS}${sepUsers}action=get_all_users_with_roles&admin_email=${encodeURIComponent(userEmail)}`);
                const dataUsers = await resUsers.json();
                if (dataUsers && dataUsers.usuarios) {
                    const myUser = dataUsers.usuarios.find(u => u.email === userEmail);
                    if (myUser && myUser.roles_ids.some(r => String(r).trim() === '1')) {
                        esAdminGlobal = true;
                    }
                }
            } catch (e) { }

            // 1. Obtener Pedido (SOLUCIÓN: Separador dinámico)
            const sepApi = API_URL.includes('?') ? '&' : '?';
            const res = await fetch(`${API_URL}${sepApi}action=get_order_by_token&token=${token}&wp_user=${encodeURIComponent(userEmail)}&t=${Date.now()}`);
            const data = await res.json();

            if (Array.isArray(data) && data.length > 0) {
                pedidoActual = data[0];
                document.getElementById('view-cliente').innerText = pedidoActual.cliente;
                document.getElementById('view-id').innerText = pedidoActual.id_pedido;

                // 2. BUSCAR EMAIL CLIENTE (SOLUCIÓN: Separador dinámico)
                if (pedidoActual.id_interno_cliente) {
                    try {
                        const sepClient = CLIENT_API_URL.includes('?') ? '&' : '?';
                        const resClient = await fetch(`${CLIENT_API_URL}${sepClient}id=${pedidoActual.id_interno_cliente}`);
                        const dataClient = await resClient.json();
                        if (dataClient.perfil && dataClient.perfil.email) {
                            emailClienteDetectado = dataClient.perfil.email;
                            document.getElementById('input-email-final').value = emailClienteDetectado;
                        }
                    } catch (e) { console.error("Error buscando email cliente", e); }
                }

                const container = document.getElementById('lista-productos-container');
                container.innerHTML = "";
                data.forEach(item => {
                    const sep = '<span style="color:#E98C00;font-weight:bold;margin:0 5px;">|</span>';
                    container.innerHTML += `<div class="product-item"><span class="product-name">${item.producto} ${sep} ${item.calibre || 'S/C'} ${sep} ${item.formato || 'S/F'}</span><span class="product-qty">${parseFloat(item.cantidad)} ${item.unidad_real || ''}</span></div>`;
                });
                configurarInterfaz(pedidoActual.estado);
                document.getElementById('loading-box').style.display = 'none';
                document.getElementById('order-ui').style.display = 'block';
            }
        } catch (e) { console.error(e); }
    }

    // --- FUNCIONES DE ACCIÓN SEPARADAS ---

    // 1. ACCIÓN: Abrir cámara
    window.accionFotoDirecta = function () {
        document.getElementById('foto-input').click();
    };

    // 2. ACCIÓN: Solo visual (NO navega a ninguna parte)
    window.fotoCapturadaUI = function () {
        const input = document.getElementById('foto-input');
        const btnFoto = document.getElementById('btn-solo-foto');
        const txtFoto = document.getElementById('txt-btn-foto');

        if (input.files && input.files[0]) {
            btnFoto.classList.add('foto-ok');
            btnFoto.style.backgroundColor = "#27ae60"; // Verde Exito

            // Actualizar texto del botón a "FOTO LISTA"
            if (txtFoto) {
                txtFoto.innerText = "FOTO LISTA";
            } else {
                btnFoto.innerHTML = '<i class="fa-solid fa-check-circle"></i> FOTO LISTA';
            }
        }
    };

    // 3. ACCIÓN: Abrir modal de firma
    window.abrirFirmaYDatos = function () {
        document.getElementById('custom-modal').style.display = 'none';
        document.getElementById('firma-modal').style.display = 'flex';
        document.getElementById('indicador-firma').style.display = 'none';
        seHaFirmado = false;
        firmaGuardadaBlob = null;
        limpiarCanvasFirma();
    };

    // 4. ACCIÓN: Validar GPS -> Luego abrir firma
    window.verificarUbicacion = async function () {
        // Validar que la foto exista antes de dejar firmar
        const inputFoto = document.getElementById('foto-input');
        if (!inputFoto.files || !inputFoto.files[0]) {
            alert("⚠️ Primero debes tomar la foto de evidencia.");
            return;
        }

        entregaForzada = false;
        const btnFirma = document.getElementById('btn-solo-firma');
        let textoOriginal = "";

        // Feedback visual
        if (btnFirma) {
            textoOriginal = btnFirma.innerHTML;
            btnFirma.disabled = true;
            btnFirma.innerHTML = '<i class="fa-solid fa-satellite-dish fa-spin"></i> GPS...';
        } else {
            const btnMain = document.getElementById('btn-accion-principal');
            textoOriginal = btnMain.innerText;
            btnMain.disabled = true;
            btnMain.innerText = "Verificando GPS...";
        }

        const coords = await obtenerGPS();
        const dLat = parseFloat(pedidoActual.lat_despacho);
        const dLng = parseFloat(pedidoActual.lng_despacho);

        // Restaurar botón
        if (btnFirma) {
            btnFirma.disabled = false;
            btnFirma.innerHTML = textoOriginal;
        } else {
            const btnMain = document.getElementById('btn-accion-principal');
            btnMain.disabled = false;
            btnMain.innerText = textoOriginal;
        }

        if (coords.lat && coords.lng && !isNaN(dLat) && !isNaN(dLng)) {
            const dist = calcularDistancia(coords.lat, coords.lng, dLat, dLng);
            if (dist > 200) {
                mostrarModalDistancia(dist, pedidoActual.direccion, dLat, dLng);
                return;
            }
        }

        // Si GPS OK, abrir firma
        abrirFirmaYDatos();
    };

    function configurarInterfaz(estadoRaw) {
        const btnMain = document.getElementById('btn-accion-principal');
        const containerDespacho = document.getElementById('botones-despacho-container');
        const badge = document.getElementById('view-estado');
        const card = document.getElementById('status-card');
        const inst = document.getElementById('main-instruction');

        // --- NUEVO: REFERENCIA AL BOTÓN ---
        const btnMapaCard = document.getElementById('btn-card-mapa');
        if (btnMapaCard) btnMapaCard.style.display = 'none'; // Ocultar por defecto
        // ----------------------------------

        const estado = normalizarTexto(estadoRaw);
        badge.innerText = estadoRaw;
        card.className = "app-card";

        if (containerDespacho) containerDespacho.style.display = 'none';
        btnMain.style.display = 'block';

        if (estado === 'confirmado') {
            card.classList.add('status-confirmado');
            inst.innerText = "El pedido ha sido recibido. Presiona abajo cuando estés cargando el camión.";
            btnMain.innerText = "📦 INICIAR PREPARACIÓN";
            btnMain.onclick = () => abrirConfirmacion();
        }
        else if (estado.includes('prepara')) {
            card.classList.add('status-preparacion');
            inst.innerText = "Los productos están listos. Presiona abajo para entregar al repartidor.";
            btnMain.innerText = "🚚 DESPACHAR PEDIDO";
            btnMain.onclick = () => abrirConfirmacion();
        }
        else if (estado.includes('despacho')) {
            card.classList.add('status-despacho');
            inst.innerText = "Estás en ruta. 1) Toma la foto. 2) Verifica ubicación y entrega.";

            // --- NUEVO: MOSTRAR BOTÓN MAPA ---
            if (btnMapaCard) btnMapaCard.style.display = 'flex';
            // ---------------------------------

            // MOSTRAR BOTONES DOBLES
            if (containerDespacho) {
                btnMain.style.display = 'none';
                containerDespacho.style.display = 'flex';
            } else {
                btnMain.innerText = "📸 SACAR FOTO Y ENTREGAR";
                btnMain.onclick = () => verificarUbicacion();
            }
        }
        else {
            card.classList.add('status-entregado');
            inst.innerText = "Este pedido ya ha sido finalizado correctamente.";
            btnMain.style.display = 'none';
            if (containerDespacho) containerDespacho.style.display = 'none';
        }
    }
    function mostrarModalDistancia(dist, direccion, lat, lng) {
        const modal = document.getElementById('custom-modal');
        const content = modal.querySelector('.modal-content');
        const title = document.getElementById('modal-title');
        const text = document.getElementById('modal-text');
        const icon = document.getElementById('modal-icon');
        const btnConfirmar = document.getElementById('btn-confirmar-modal');
        const previo = document.getElementById('admin-force-wrapper');
        if (previo) previo.remove();

        modal.style.display = 'flex';
        icon.innerText = "📍";
        title.innerText = "FUERA DE RANGO";
        title.style.color = "#e67e22";
        text.innerHTML = `Estás a <b>${Math.round(dist)}m</b> de la entrega.<br><br>Dirección:<br><b>${direccion}</b>`;

        btnConfirmar.innerText = "📍 ABRIR MAPA";
        btnConfirmar.style.background = "#e67e22";
        // SOLUCIÓN: Corrección URL Google Maps
        btnConfirmar.onclick = () => window.location.href = `https://maps.google.com/?q=${lat},${lng}`;

        if (esAdminGlobal) {
            const adminDiv = document.createElement('div');
            adminDiv.id = 'admin-force-wrapper';
            adminDiv.style.cssText = "margin-top: 20px; text-align: center; width: 100%;";
            adminDiv.innerHTML = `<div onclick="forzarEntrega()" style="color: #0F4B29; text-decoration: underline; font-size: 16px; cursor: pointer; font-weight: 700; padding: 10px; display: inline-block;">Entregar de todos modos</div>`;
            content.appendChild(adminDiv);
        }
    }

    window.forzarEntrega = function () {
        entregaForzada = true;
        document.getElementById('custom-modal').style.display = 'none';

        const inputFoto = document.getElementById('foto-input');
        if (!inputFoto.files || !inputFoto.files[0]) {
            alert("El GPS fue omitido, pero necesitamos la foto.");
            return;
        }
        abrirFirmaYDatos();
    }

    function ajustarCanvas() {
        const container = document.getElementById('canvas-container');

        // Ajustamos al tamaño real del contenedor
        canvasFirma.width = container.clientWidth;
        canvasFirma.height = container.clientHeight;

        // --- AQUÍ ADELGAZAMOS EL TRAZO ---
        ctxFirma.lineWidth = 3; // Antes era 9. Un valor de 3 o 4 es ideal para bolígrafo.

        ctxFirma.lineCap = 'round';
        ctxFirma.lineJoin = 'round';
        ctxFirma.strokeStyle = '#000000';
    }

    function getPos(e) {
        const rect = canvasFirma.getBoundingClientRect();
        const t = e.touches ? e.touches[0] : e;
        return { x: t.clientX - rect.left, y: t.clientY - rect.top };
    }

    ['mousedown', 'touchstart'].forEach(evt => canvasFirma.addEventListener(evt, (e) => {
        if (evt === 'touchstart') e.preventDefault();
        dibujando = true;
        ctxFirma.beginPath();
        const p = getPos(e);
        ctxFirma.moveTo(p.x, p.y);
    }, { passive: false }));

    ['mousemove', 'touchmove'].forEach(evt => canvasFirma.addEventListener(evt, (e) => {
        if (evt === 'touchmove') e.preventDefault();
        if (!dibujando) return;
        const p = getPos(e);
        ctxFirma.lineTo(p.x, p.y);
        ctxFirma.stroke();
        seHaFirmado = true;
    }, { passive: false }));

    ['mouseup', 'touchend'].forEach(evt => canvasFirma.addEventListener(evt, () => dibujando = false));
    window.addEventListener('resize', ajustarCanvas);

    window.limpiarCanvasFirma = () => { ctxFirma.clearRect(0, 0, canvasFirma.width, canvasFirma.height); seHaFirmado = false; };

    window.abrirPantallaFirma = function () {
        seHaFirmado = false;
        document.getElementById('firma-modal').style.display = 'none';
        document.getElementById('full-screen-signature').style.display = 'flex';
        setTimeout(ajustarCanvas, 100);
    };

    window.guardarFirmaTemp = function () {
        if (!seHaFirmado) { alert("⚠️ La firma está vacía."); return; }
        const canvasRecortado = normalizarFirma(canvasFirma);
        canvasRecortado.toBlob(blob => {
            firmaGuardadaBlob = blob;
            document.getElementById('full-screen-signature').style.display = 'none';
            document.getElementById('firma-modal').style.display = 'flex';
            document.getElementById('indicador-firma').style.display = 'block';
            document.getElementById('btn-pre-enviar').disabled = false;
            document.getElementById('btn-pre-enviar').style.opacity = '1';
        }, 'image/png');
    };

    window.abrirConfirmacion = function () {
        const estado = normalizarTexto(pedidoActual.estado);
        const modal = document.getElementById('custom-modal');
        const title = document.getElementById('modal-title');
        const text = document.getElementById('modal-text');
        const icon = document.getElementById('modal-icon');
        const btnConfirmar = document.getElementById('btn-confirmar-modal');
        const previo = document.getElementById('admin-force-wrapper');
        if (previo) previo.remove();
        document.getElementById('modal-title').style.color = "#1a1a1a";

        // Si es despacho, ya no usamos esta funcion normalmente.
        if (estado.includes('despacho')) {
            verificarUbicacion();
            return;
        }

        btnConfirmar.innerText = "Confirmar";
        btnConfirmar.disabled = false;
        btnConfirmar.style.background = "#0F4B29";
        btnConfirmar.onclick = () => procesarEnvio(false);

        if (estado.includes('prepara')) {
            title.innerText = "¿Iniciar Despacho?";
            text.innerText = "El pedido saldrá a ruta ahora.";
            icon.innerText = "🚚";
            btnConfirmar.innerText = "Iniciar Despacho";
        } else {
            title.innerText = "¿Cambiar Estado?";
            text.innerText = "El pedido pasará a la siguiente etapa.";
            icon.innerText = "📦";
        }
        modal.style.display = 'flex';
    };

    window.cerrarModal = function () { document.getElementById('custom-modal').style.display = 'none'; };

    window.procesarEnvio = async function (forzarAdmin = false) {
        document.getElementById('btn-confirmar-modal').disabled = true;
        document.getElementById('btn-confirmar-modal').innerText = "Procesando...";
        enviarDatosGenerico(forzarAdmin);
    };

    // --- LÓGICA MODAL CORREO ---
    window.prepararEnvio = function () {
        tempNombre = document.getElementById('input-nombre-rx').value.trim();
        tempRut = document.getElementById('input-rut-rx').value.trim();
        tempObs = document.getElementById('input-obs-rx').value.trim();

        if (!tempNombre || !tempRut) { alert("Faltan datos"); return; }
        if (!firmaGuardadaBlob) { alert("Falta la firma"); return; }

        document.getElementById('firma-modal').style.display = 'none';

        const modalMail = document.getElementById('email-modal');
        const displayMode = document.getElementById('email-display-mode');
        const editMode = document.getElementById('email-edit-mode');
        const label = document.getElementById('email-label-static');
        const input = document.getElementById('input-email-final');

        const currentEmail = emailClienteDetectado || "";
        input.value = currentEmail;

        if (currentEmail && currentEmail.includes('@')) {
            label.innerText = currentEmail;
            displayMode.style.display = 'block';
            editMode.style.display = 'none';
        } else {
            displayMode.style.display = 'none';
            editMode.style.display = 'block';
        }
        modalMail.style.display = 'flex';
    }

    window.activarEdicionEmail = function () {
        document.getElementById('email-display-mode').style.display = 'none';
        document.getElementById('email-edit-mode').style.display = 'block';
        document.getElementById('input-email-final').focus();
    }

    window.finalizarConCorreo = function () {
        const mail = document.getElementById('input-email-final').value.trim();
        if (!mail || !mail.includes('@')) { alert("Correo inválido"); return; }

        document.getElementById('email-modal').style.display = 'none'; // Ocultar el modal de inmediato
        enviarEntregaDefinitiva(mail);
    }

    window.finalizarSinCorreo = function () {
        document.getElementById('email-modal').style.display = 'none'; // Ocultar el modal de inmediato
        enviarEntregaDefinitiva("SKIP");
    }

    async function enviarDatosGenerico(forzarAdmin, extraData = null) {
        try {
            // 🔥 1. LEVANTAR PANTALLA DE CARGA BLOQUEANTE 🔥
            Swal.fire({
                title: 'Procesando entrega...',
                html: 'Generando documento firmado y enviando correo.<br><b>Por favor, no cierres esta pantalla.</b>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const coords = await obtenerGPS();
            const estOriginal = normalizarTexto(pedidoActual.estado);
            const fd = new FormData();

            fd.append('qr_token', token);
            fd.append('lat_gps', coords.lat || '');
            fd.append('lng_gps', coords.lng || '');

            if (forzarAdmin) fd.append('forzado_admin', '1');

            if (extraData) {
                fd.append('nombre_receptor', extraData.nombre);
                fd.append('rut_receptor', extraData.rut);
                fd.append('observaciones', extraData.obs);
                fd.append('img_firma', extraData.firma, "firma.png");
                if (extraData.email_envio) fd.append('email_envio', extraData.email_envio);

                const foto = document.getElementById('foto-input').files[0];
                if (foto) {
                    const fotoBlob = await comprimirImagen(foto, 1200, 0.7);
                    fd.append('foto', fotoBlob, "evidencia.jpg");
                }
            }

            const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
            const textResponse = await res.text();

            let result;
            try {
                const jsonStart = textResponse.indexOf('{');
                const jsonEnd = textResponse.lastIndexOf('}');
                if (jsonStart !== -1 && jsonEnd !== -1) {
                    const jsonString = textResponse.substring(jsonStart, jsonEnd + 1);
                    result = JSON.parse(jsonString);
                } else { throw new Error("Invalido"); }
            } catch (e) {
                Swal.close(); // Ocultar carga si falla
                console.error("Error parseando:", textResponse);
                Swal.fire('Error', 'El servidor devolvió una respuesta inesperada.', 'error');
                return;
            }

            Swal.close(); // 🔥 2. OCULTAR PANTALLA DE CARGA AL TERMINAR 🔥

            if (result.status === 'success') {
                document.getElementById('custom-modal').style.display = 'none';
                document.getElementById('firma-modal').style.display = 'none';
                document.getElementById('email-modal').style.display = 'none';

                if (estOriginal.includes('prepara')) {
                    pedidoActual.estado = "En despacho";
                    window.abrirGpsModal();
                } else {
                    window.finalizarExito();
                }
            } else {
                Swal.fire('Atención', result.message || "Error al procesar", 'warning');
            }
        } catch (e) {
            Swal.close(); // Ocultar carga si falla la red
            console.error("Error en envío:", e);
            Swal.fire('Error de Conexión', 'No se pudo conectar con el servidor. Revisa tu internet e intenta de nuevo.', 'error');
        }
    }

    async function enviarDatosGenerico(forzarAdmin, extraData = null) {
        try {
            const coords = await obtenerGPS();
            const estOriginal = normalizarTexto(pedidoActual.estado);
            const fd = new FormData();

            fd.append('qr_token', token);
            fd.append('lat_gps', coords.lat || '');
            fd.append('lng_gps', coords.lng || '');

            if (forzarAdmin) fd.append('forzado_admin', '1');

            if (extraData) {
                fd.append('nombre_receptor', extraData.nombre);
                fd.append('rut_receptor', extraData.rut);
                fd.append('observaciones', extraData.obs);
                fd.append('img_firma', extraData.firma, "firma.png");
                if (extraData.email_envio) fd.append('email_envio', extraData.email_envio);

                const foto = document.getElementById('foto-input').files[0];
                if (foto) {
                    const fotoBlob = await comprimirImagen(foto, 1200, 0.7);
                    fd.append('foto', fotoBlob, "evidencia.jpg");
                }
            }

            const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
            const textResponse = await res.text();

            let result;
            try {
                const jsonStart = textResponse.indexOf('{');
                const jsonEnd = textResponse.lastIndexOf('}');
                if (jsonStart !== -1 && jsonEnd !== -1) {
                    const jsonString = textResponse.substring(jsonStart, jsonEnd + 1);
                    result = JSON.parse(jsonString);
                } else { throw new Error("Invalido"); }
            } catch (e) {
                console.error("Error parseando:", textResponse);
                alert("Error de respuesta del servidor.");
                if (extraData) document.getElementById('btn-enviar-final').disabled = false;
                return;
            }

            if (result.status === 'success') {
                document.getElementById('custom-modal').style.display = 'none';
                document.getElementById('firma-modal').style.display = 'none';
                document.getElementById('email-modal').style.display = 'none';

                if (estOriginal.includes('prepara')) {
                    pedidoActual.estado = "En despacho";
                    window.abrirGpsModal();
                } else {
                    window.finalizarExito();
                }
            } else {
                alert(result.message || "Error al procesar");
                if (extraData) {
                    document.getElementById('btn-enviar-final').disabled = false;
                    document.getElementById('btn-enviar-final').innerText = "✅ ENVIAR";
                }
            }
        } catch (e) {
            console.error("Error en envío:", e);
            alert("Error de red.");
            if (extraData) document.getElementById('btn-enviar-final').disabled = false;
        }
    }

    // --- ESTAS FUNCIONES DEBEN ESTAR FUERA DE ENVIARDATOSGENERICO ---

    async function enviarEntregaDefinitiva(emailDestino) {
        enviarDatosGenerico(entregaForzada, {
            nombre: tempNombre,
            rut: tempRut,
            obs: tempObs,
            firma: firmaGuardadaBlob,
            email_envio: emailDestino
        });
    }
    // REEMPLAZAR TU FUNCIÓN comprimirImagen POR ESTA:
    function comprimirImagen(archivo, maxWidth, calidad) {
        // -----------------------------------------------------------
        // 1. PEGA AQUÍ EL CÓDIGO BASE64 DE TU LOGO (Debe ser largo)
        // Empieza por "data:image/png;base64,..."
        // Si no tienes uno, usa una herramienta online para convertir tu PNG a Base64
        const LOGO_BASE64 = "data:image/png;base64,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"; // <--- BORRA ESTO Y PEGA TU CÓDIGO REAL ENTRE LAS COMILLAS
        // -----------------------------------------------------------

        // AJUSTE: Más transparente (0.4 es muy sutil, 0.5 es medio)
        const TRANSPARENCIA = 0.4;

        return new Promise(resolve => {
            const reader = new FileReader();
            reader.readAsDataURL(archivo);

            reader.onload = eFoto => {
                const imgFoto = new Image();
                imgFoto.src = eFoto.target.result;

                imgFoto.onload = () => {
                    const canvas = document.createElement('canvas');
                    let w = imgFoto.width, h = imgFoto.height;

                    if (w > maxWidth) {
                        h = (maxWidth * h) / w;
                        w = maxWidth;
                    }

                    canvas.width = w;
                    canvas.height = h;
                    const ctx = canvas.getContext('2d');

                    // 1. Dibujar Foto Original
                    ctx.drawImage(imgFoto, 0, 0, w, h);

                    // 2. Preparar Logo
                    const imgLogo = new Image();
                    imgLogo.src = LOGO_BASE64;

                    imgLogo.onload = () => {
                        ctx.save();

                        // Configurar transparencia global (Afecta a logo y texto)
                        ctx.globalAlpha = TRANSPARENCIA;

                        // Cálculos de tamaño y posición
                        const logoW = w * 0.30; // Logo al 30% del ancho
                        const logoH = (imgLogo.height / imgLogo.width) * logoW;

                        // Fuente un poco más pequeña y discreta
                        const fontSize = Math.floor(w * 0.03);
                        ctx.font = `${fontSize}px sans-serif`; // Quitamos 'bold' para que sea más fino

                        const espacio = fontSize; // Espacio entre logo y texto
                        const totalH = logoH + espacio + fontSize;

                        // Centro absoluto
                        const x = w / 2;
                        const y = (h - totalH) / 2;

                        // A. Dibujar Logo
                        ctx.drawImage(imgLogo, x - (logoW / 2), y, logoW, logoH);

                        // B. Dibujar Texto (SOLO BLANCO, SIN BORDES)
                        ctx.textAlign = "center";
                        ctx.textBaseline = "top";
                        ctx.fillStyle = "#ffffff"; // Blanco puro

                        // Dibujar fecha debajo del logo
                        ctx.fillText(new Date().toLocaleString('es-CL'), x, y + logoH + espacio);

                        ctx.restore(); // Restaurar para que no afecte nada más

                        // Exportar
                        canvas.toBlob(b => resolve(b), 'image/jpeg', calidad);
                    };

                    imgLogo.onerror = () => {
                        // Fallback si no hay logo
                        canvas.toBlob(b => resolve(b), 'image/jpeg', calidad);
                    };
                };
            };
        });
    }
    window.verGuiaPdf = () => pedidoActual?.url_guia ? window.open(pedidoActual.url_guia, '_blank') : alert("Sin guía");

    // REEMPLAZA TU FUNCIÓN cerrarGpsModal POR ESTA:
    window.cerrarGpsModal = () => {
        document.getElementById('gps-modal').style.display = 'none';

        const estado = normalizarTexto(pedidoActual.estado);

        // Si el estado ya es "En despacho" (porque lo acabamos de cambiar),
        // lo ideal es recargar la página para que el operario vea la nueva UI 
        // con los botones de "Foto" y "Firma".
        if (estado.includes('despacho')) {
            location.reload(); // Esto refresca la UI al nuevo estado "En ruta"
        }
    };

    window.abrirGpsModal = () => document.getElementById('gps-modal').style.display = 'flex';

    window.irAMapa = (t) => {
        if (!pedidoActual || !pedidoActual.lat_despacho) { alert("Sin coordenadas"); return; }
        // SOLUCIÓN: Corrección URLs mapas
        const url = t === 'google' ? `https://maps.google.com/?q=${pedidoActual.lat_despacho},${pedidoActual.lng_despacho}` :
            t === 'waze' ? `https://waze.com/ul?ll=${pedidoActual.lat_despacho},${pedidoActual.lng_despacho}&navigate=yes` :
                `http://maps.apple.com/?q=${pedidoActual.lat_despacho},${pedidoActual.lng_despacho}`;
        window.location.href = url;
    };

    document.addEventListener('DOMContentLoaded', () => setTimeout(cargarPedido, 500));
})();