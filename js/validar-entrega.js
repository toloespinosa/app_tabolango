(function () {
    // --- FIX MÓVIL PWA / NAV BAR (Inyección de CSS de Alta Prioridad) ---
    const mobileFix = document.createElement('style');
    mobileFix.innerHTML = `
        /* Forzar z-index máximo para todos los modales de esta vista */
        #full-screen-signature, #firma-modal, #email-modal, #custom-modal, #gps-modal {
            z-index: 9999999 !important;
        }
        /* Ajuste de altura dinámica para evitar que quede debajo del menú */
        #full-screen-signature {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            height: 100dvh !important; /* Altura dinámica real del dispositivo */
            box-sizing: border-box;
            background: #fff;
        }
        /* Darle un margen de seguridad extra a los botones inferiores */
        #full-screen-signature > div:last-child,
        #firma-modal > div:last-child {
            padding-bottom: calc(40px + env(safe-area-inset-bottom)) !important;
        }
    `;
    document.head.appendChild(mobileFix);
    // ---------------------------------------------------------------------

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

    function normalizarFirma(c) {
        const ANCHO_FINAL = 800;
        const ALTO_FINAL = 400;
        const PADDING = 20;

        const ctx = c.getContext('2d');
        const w = c.width;
        const h = c.height;
        const imageData = ctx.getImageData(0, 0, w, h);
        const data = imageData.data;

        let minX = w, minY = h, maxX = 0, maxY = 0;
        let found = false;

        for (let y = 0; y < h; y++) {
            for (let x = 0; x < w; x++) {
                if (data[(y * w + x) * 4 + 3] > 0) {
                    if (x < minX) minX = x;
                    if (x > maxX) maxX = x;
                    if (y < minY) minY = y;
                    if (y > maxY) maxY = y;
                    found = true;
                }
            }
        }

        if (!found) {
            const empty = document.createElement('canvas');
            empty.width = ANCHO_FINAL;
            empty.height = ALTO_FINAL;
            return empty;
        }

        const contentW = maxX - minX + 1;
        const contentH = maxY - minY + 1;

        const scaleX = (ANCHO_FINAL - (PADDING * 2)) / contentW;
        const scaleY = (ALTO_FINAL - (PADDING * 2)) / contentH;
        const scale = Math.min(scaleX, scaleY);

        const scaledW = contentW * scale;
        const scaledH = contentH * scale;

        const posX = (ANCHO_FINAL - scaledW) / 2;
        const posY = (ALTO_FINAL - scaledH) / 2;

        const finalCanvas = document.createElement('canvas');
        finalCanvas.width = ANCHO_FINAL;
        finalCanvas.height = ALTO_FINAL;
        const fCtx = finalCanvas.getContext('2d');

        fCtx.drawImage(c,
            minX, minY, contentW, contentH,
            posX, posY, scaledW, scaledH
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

            try {
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

            const sepApi = API_URL.includes('?') ? '&' : '?';
            const res = await fetch(`${API_URL}${sepApi}action=get_order_by_token&token=${token}&wp_user=${encodeURIComponent(userEmail)}&t=${Date.now()}`);
            const data = await res.json();

            if (Array.isArray(data) && data.length > 0) {
                pedidoActual = data[0];
                document.getElementById('view-cliente').innerText = pedidoActual.cliente;
                document.getElementById('view-id').innerText = pedidoActual.id_pedido;

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

    window.accionFotoDirecta = function () {
        document.getElementById('foto-input').click();
    };

    window.fotoCapturadaUI = function () {
        const input = document.getElementById('foto-input');
        const btnFoto = document.getElementById('btn-solo-foto');
        const txtFoto = document.getElementById('txt-btn-foto');

        if (input.files && input.files[0]) {
            btnFoto.classList.add('foto-ok');
            btnFoto.style.backgroundColor = "#27ae60";

            if (txtFoto) {
                txtFoto.innerText = "FOTO LISTA";
            } else {
                btnFoto.innerHTML = '<i class="fa-solid fa-check-circle"></i> FOTO LISTA';
            }
        }
    };

    window.abrirFirmaYDatos = function () {
        document.getElementById('custom-modal').style.display = 'none';
        document.getElementById('firma-modal').style.display = 'flex';
        document.getElementById('indicador-firma').style.display = 'none';
        seHaFirmado = false;
        firmaGuardadaBlob = null;
        limpiarCanvasFirma();
    };

    window.verificarUbicacion = async function () {
        const inputFoto = document.getElementById('foto-input');
        if (!inputFoto.files || !inputFoto.files[0]) {
            alert("⚠️ Primero debes tomar la foto de evidencia.");
            return;
        }

        entregaForzada = false;
        const btnFirma = document.getElementById('btn-solo-firma');
        let textoOriginal = "";

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

        abrirFirmaYDatos();
    };

    function configurarInterfaz(estadoRaw) {
        const btnMain = document.getElementById('btn-accion-principal');
        const containerDespacho = document.getElementById('botones-despacho-container');
        const badge = document.getElementById('view-estado');
        const card = document.getElementById('status-card');
        const inst = document.getElementById('main-instruction');

        const btnMapaCard = document.getElementById('btn-card-mapa');
        if (btnMapaCard) btnMapaCard.style.display = 'none';

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

            if (btnMapaCard) btnMapaCard.style.display = 'flex';

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
        canvasFirma.width = container.clientWidth;
        canvasFirma.height = container.clientHeight;
        ctxFirma.lineWidth = 3;
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

        if (estado.includes('despacho')) {
            verificarUbicacion();
            return;
        }

        btnConfirmar.innerText = "Confirmar";
        btnConfirmar.disabled = false;
        btnConfirmar.style.background = "#0F4B29";

        // 🔥 FIX: AHORA LLAMA A enviarDatosGenerico PARA AVANZAR ETAPAS (Sin foto ni firma)
        btnConfirmar.onclick = () => enviarDatosGenerico(false, null);

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

        document.getElementById('email-modal').style.display = 'none';
        enviarEntregaDefinitiva(mail);
    }

    window.finalizarSinCorreo = function () {
        document.getElementById('email-modal').style.display = 'none';
        enviarEntregaDefinitiva("SKIP");
    }

    async function enviarDatosGenerico(forzarAdmin, extraData = null) {
        try {
            Swal.fire({
                title: 'Procesando...',
                html: 'Guardando datos.<br><b>Por favor, espera...</b>',
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
                Swal.close();
                console.error("Error parseando:", textResponse);
                Swal.fire('Error', 'El servidor devolvió una respuesta inesperada.', 'error');
                return;
            }

            Swal.close();

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
            Swal.close();
            console.error("Error en envío:", e);
            Swal.fire('Error de Conexión', 'No se pudo conectar con el servidor. Revisa tu internet e intenta de nuevo.', 'error');
        }
    }

    async function enviarEntregaDefinitiva(emailDestino) {
        enviarDatosGenerico(entregaForzada, {
            nombre: tempNombre,
            rut: tempRut,
            obs: tempObs,
            firma: firmaGuardadaBlob,
            email_envio: emailDestino
        });
    }

    function comprimirImagen(archivo, maxWidth, calidad) {
        const LOGO_BASE64 = "data:image/png;base64,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";
        const TRANSPARENCIA = 0.5;

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

                    ctx.drawImage(imgFoto, 0, 0, w, h);

                    const imgLogo = new Image();
                    imgLogo.src = LOGO_BASE64;

                    imgLogo.onload = () => {
                        ctx.save();
                        ctx.globalAlpha = TRANSPARENCIA;

                        const logoW = w * 0.30;
                        const logoH = (imgLogo.height / imgLogo.width) * logoW;
                        const fontSize = Math.floor(w * 0.03);
                        ctx.font = `${fontSize}px sans-serif`;

                        const espacio = fontSize;
                        const totalH = logoH + espacio + fontSize;

                        const x = w / 2;
                        const y = (h - totalH) / 2;

                        ctx.drawImage(imgLogo, x - (logoW / 2), y, logoW, logoH);

                        ctx.textAlign = "center";
                        ctx.textBaseline = "top";
                        ctx.fillStyle = "#ffffff";
                        ctx.fillText(new Date().toLocaleString('es-CL'), x, y + logoH + espacio);
                        ctx.restore();

                        canvas.toBlob(b => resolve(b), 'image/jpeg', calidad);
                    };

                    imgLogo.onerror = () => {
                        canvas.toBlob(b => resolve(b), 'image/jpeg', calidad);
                    };
                };
            };
        });
    }

    window.verGuiaPdf = () => pedidoActual?.url_guia ? window.open(pedidoActual.url_guia, '_blank') : alert("Sin guía");

    window.cerrarGpsModal = () => {
        document.getElementById('gps-modal').style.display = 'none';
        const estado = normalizarTexto(pedidoActual.estado);
        if (estado.includes('despacho')) {
            location.reload();
        }
    };

    window.abrirGpsModal = () => document.getElementById('gps-modal').style.display = 'flex';

    window.irAMapa = (t) => {
        if (!pedidoActual || !pedidoActual.lat_despacho) { alert("Sin coordenadas"); return; }
        const url = t === 'google' ? `https://maps.google.com/?q=${pedidoActual.lat_despacho},${pedidoActual.lng_despacho}` :
            t === 'waze' ? `https://waze.com/ul?ll=${pedidoActual.lat_despacho},${pedidoActual.lng_despacho}&navigate=yes` :
                `http://maps.apple.com/?q=${pedidoActual.lat_despacho},${pedidoActual.lng_despacho}`;
        window.location.href = url;
    };

    document.addEventListener('DOMContentLoaded', () => setTimeout(cargarPedido, 500));
})();