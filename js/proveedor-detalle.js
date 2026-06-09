// ============================================================
// PROVEEDOR DETALLE — catálogo, cotizaciones e historial
// ============================================================
(function () {
    if (!document.getElementById('contenedor-proveedor-detalle')) return;

    const URL_API   = window.getApi('api_proveedores.php');
    const params    = new URLSearchParams(window.location.search);
    const ID_PROV   = parseInt(params.get('id') || '0', 10);
    let proveedor   = null;
    let productos   = [];
    let modoCot     = 'existente'; // 'existente' | 'nuevo' — default a actualizar

    const fmt = window.formatCLP || (v => '$' + v);

    if (ID_PROV <= 0) {
        document.getElementById('pd-info').innerHTML =
            '<div class="pd-loading">Proveedor no especificado. <a href="/proveedores/" style="color:#fff; text-decoration:underline;">Volver</a></div>';
        return;
    }

    // ── Cargar info del proveedor ─────────────────────────────
    async function cargarProveedor() {
        try {
            const res = await fetch(URL_API + '&action=get_proveedor&id=' + ID_PROV);
            proveedor = await res.json();
            renderHeader();
        } catch (e) {
            document.getElementById('pd-info').innerHTML = '<div class="pd-loading">Error al cargar proveedor</div>';
        }
    }

    function renderHeader() {
        if (!proveedor || !proveedor.id_proveedor) {
            document.getElementById('pd-info').innerHTML = '<div class="pd-loading">Proveedor no encontrado.</div>';
            return;
        }
        const ubicacion = [proveedor.direccion, proveedor.ciudad].filter(Boolean).join(', ');
        document.getElementById('pd-info').innerHTML = `
            <h2 class="pd-nombre"><i class="fa-solid fa-store"></i> ${escapeHtml(proveedor.nombre)}</h2>
            <div class="pd-meta">
                ${proveedor.rut      ? `<span><i class="fa-solid fa-id-card"></i>${escapeHtml(proveedor.rut)}</span>` : ''}
                ${proveedor.contacto ? `<span><i class="fa-solid fa-user"></i>${escapeHtml(proveedor.contacto)}</span>` : ''}
                ${proveedor.telefono ? `<span><i class="fa-solid fa-phone"></i>${escapeHtml(proveedor.telefono)}</span>` : ''}
                ${proveedor.email    ? `<span><i class="fa-solid fa-envelope"></i>${escapeHtml(proveedor.email)}</span>` : ''}
                ${ubicacion          ? `<span><i class="fa-solid fa-location-dot"></i>${escapeHtml(ubicacion)}</span>` : ''}
            </div>
            ${proveedor.notas ? `<div class="pd-notas"><i class="fa-solid fa-note-sticky"></i> ${escapeHtml(proveedor.notas)}</div>` : ''}
        `;
        document.getElementById('btnEditarProveedor').style.display = 'inline-flex';
    }

    // ── Cargar productos del proveedor ────────────────────────
    async function cargarProductos() {
        try {
            const res = await fetch(URL_API + '&action=list_productos&id_proveedor=' + ID_PROV);
            productos = await res.json();
            renderTabla();
            llenarSelectExistentes();
        } catch (e) {
            document.getElementById('pd-tbody').innerHTML =
                '<tr><td colspan="9" style="text-align:center; padding:30px; color:#c00;">Error al cargar productos</td></tr>';
        }
    }

    function renderTabla() {
        const tbody = document.getElementById('pd-tbody');
        const q     = (document.getElementById('pd-buscar').value || '').toLowerCase();
        const list  = productos.filter(p =>
            (p.producto_nombre + ' ' + (p.variedad || '')).toLowerCase().includes(q)
        );

        if (!list.length) {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:40px; color:#aaa;">
                ${productos.length === 0
                    ? 'Aún no hay productos cotizados. Agrega el primero con "Nueva cotización".'
                    : 'Sin resultados'}
            </td></tr>`;
            return;
        }

        tbody.innerHTML = list.map(p => {
            const vence = p.valido_hasta
                ? new Date(p.valido_hasta + 'T00:00:00').toLocaleDateString('es-CL')
                : '—';
            const precio = p.precio ? fmt(p.precio) : '<span style="color:#aaa;">—</span>';
            return `
                <tr>
                    <td>
                        ${p.producto_link_icono ? `<span class="pd-prod-icono">${p.producto_link_icono}</span>` : ''}
                        <span class="pd-prod-nombre">${escapeHtml(p.producto_nombre)}</span>
                        ${p.variedad ? `<span class="pd-prod-variedad">${escapeHtml(p.variedad)}</span>` : ''}
                        ${p.id_producto_link ? `<span class="pd-linked" title="Vinculado a ${escapeHtml(p.producto_link_nombre || '')}">🔗 vinculado</span>` : ''}
                    </td>
                    <td style="text-align:center; color:#666;">${escapeHtml(p.calibre || '-')}</td>
                    <td style="text-align:center; color:#666;">${escapeHtml(p.unidad || '-')}</td>
                    <td style="text-align:center; color:#666;">${escapeHtml(p.formato || '-')}</td>
                    <td class="pd-precio">${precio}</td>
                    <td style="text-align:center;">
                        ${p.validez ? `<span class="badge-validez">${p.validez}</span>` : '<span style="color:#aaa;">—</span>'}
                    </td>
                    <td style="text-align:center; color:#666;">${vence}</td>
                    <td style="text-align:center;">
                        <span class="badge-vigencia ${p.estado_vigencia}">${textoEstado(p.estado_vigencia)}</span>
                    </td>
                    <td style="text-align:center; white-space:nowrap;">
                        <button class="pd-btn-hist" ${p.n_cotizaciones > 0 ? '' : 'disabled'}
                                title="Ver historial completo"
                                onclick="pd_verHistorial(${p.id}, '${escapeJs(p.producto_nombre)}')">
                            <i class="fa-solid fa-clock-rotate-left"></i> ${p.n_cotizaciones || 0}
                        </button>
                        <button class="pd-btn-hist" ${p.n_cotizaciones > 0 ? '' : 'disabled'}
                                title="Consultar precio en fecha"
                                onclick="pd_consultarFecha(${p.id}, '${escapeJs(p.producto_nombre)}')">
                            <i class="fa-solid fa-calendar-day"></i>
                        </button>
                    </td>
                </tr>`;
        }).join('');
    }

    function llenarSelectExistentes() {
        const sel = document.getElementById('pd-prod-existente');
        sel.innerHTML = '<option value="">— Selecciona —</option>';
        productos.forEach(p => {
            const txt = p.producto_nombre + (p.variedad ? ' (' + p.variedad + ')' : '') +
                        (p.calibre ? ' · ' + p.calibre : '');
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = txt;
            sel.appendChild(opt);
        });
    }

    function textoEstado(s) {
        return ({
            vigente:    'Vigente',
            vence_hoy:  'Vence hoy',
            vencido:    'Vencido',
            sin_precio: 'Sin precio'
        })[s] || s;
    }

    window.pd_filtrar = renderTabla;

    // ── Catálogo de productos propios para vincular ───────────
    async function cargarCatalogoVinculable() {
        try {
            const res  = await fetch(URL_API + '&action=productos_catalogo');
            const cat  = await res.json();
            const sel  = document.getElementById('nc-link-producto');
            cat.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id_producto;
                opt.textContent = (p.icono || '📦') + ' ' + p.producto + (p.variedad ? ' — ' + p.variedad : '');
                sel.appendChild(opt);
            });
        } catch (e) { /* no es crítico */ }
    }

    // ── Modal nueva cotización ────────────────────────────────
    document.getElementById('btnAbrirNuevoProducto').addEventListener('click', () => {
        document.getElementById('modal-nueva-cotizacion').style.display = 'flex';
        // Si ya hay productos cargados → default "actualizar existente".
        // Si está vacío (proveedor nuevo) → default "producto nuevo".
        const modoDefault = productos.length > 0 ? 'existente' : 'nuevo';
        document.querySelector(`.pd-modo-btn[data-modo="${modoDefault}"]`).click();
        // Focus apropiado para cada modo
        setTimeout(() => {
            if (modoDefault === 'existente') {
                document.getElementById('pd-prod-existente').focus();
            } else {
                document.getElementById('nc-nombre').focus();
            }
        }, 50);
    });

    document.querySelectorAll('.pd-modo-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.pd-modo-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            modoCot = this.dataset.modo;
            document.getElementById('modo-nuevo').style.display     = modoCot === 'nuevo'     ? 'block' : 'none';
            document.getElementById('modo-existente').style.display = modoCot === 'existente' ? 'block' : 'none';
            document.getElementById('modal-cot-titulo').textContent =
                modoCot === 'existente' ? 'Actualizar precio' : 'Nueva cotización';
        });
    });

    window.pd_cerrarModal = function () {
        document.getElementById('modal-nueva-cotizacion').style.display = 'none';
        ['nc-nombre','nc-variedad','nc-calibre','nc-unidad','nc-formato','nc-precio','nc-notas']
            .forEach(id => { const e = document.getElementById(id); e.value = ''; e.classList.remove('nc-error'); });
        document.getElementById('nc-link-producto').value = '';
        document.getElementById('nc-validez').value = 'semanal';
        document.getElementById('pd-prod-existente').value = '';
        limpiarFoto();
        // Reset al modo default (existente si hay productos, nuevo si no)
        const modoDefault = productos.length > 0 ? 'existente' : 'nuevo';
        document.querySelector(`.pd-modo-btn[data-modo="${modoDefault}"]`).click();
    };

    // ── Selector de foto (con preview) ────────────────────────
    const inpFoto = document.getElementById('nc-foto');
    const preview = document.getElementById('nc-foto-preview');
    const imgPrev = document.getElementById('nc-foto-img');
    const labelFoto = document.getElementById('nc-foto-label');
    const btnQuitar = document.getElementById('nc-foto-quitar');

    inpFoto.addEventListener('change', function () {
        const f = this.files && this.files[0];
        if (!f) { limpiarFoto(); return; }
        imgPrev.src = URL.createObjectURL(f);
        preview.style.display = 'block';
        labelFoto.textContent = f.name.length > 22 ? f.name.slice(0, 19) + '...' : f.name;
        btnQuitar.style.display = 'inline-flex';
    });

    btnQuitar.addEventListener('click', limpiarFoto);

    function limpiarFoto() {
        inpFoto.value = '';
        preview.style.display = 'none';
        imgPrev.src = '';
        labelFoto.textContent = 'Tomar / Elegir foto';
        btnQuitar.style.display = 'none';
    }

    /**
     * Comprime una imagen y la devuelve como string base64 (data URI JPEG).
     * Redimensiona a maxLado px por lado.
     * Una foto de 5MB queda en una cadena base64 de ~400KB.
     */
    async function comprimirImagenBase64(file, maxLado = 1280, calidad = 0.78) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                let { naturalWidth: w, naturalHeight: h } = img;
                if (w > maxLado || h > maxLado) {
                    if (w >= h) { h = Math.round(h * maxLado / w); w = maxLado; }
                    else        { w = Math.round(w * maxLado / h); h = maxLado; }
                }
                const canvas = document.createElement('canvas');
                canvas.width  = w;
                canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                try {
                    // toDataURL es síncrono y devuelve base64 directo
                    const b64 = canvas.toDataURL('image/jpeg', calidad);
                    resolve(b64);
                } catch (e) {
                    reject(e);
                }
            };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('No se pudo leer la imagen')); };
            img.src = url;
        });
    }

    document.getElementById('btnGuardarCotizacion').addEventListener('click', async function () {
        const precio  = parseFloat(document.getElementById('nc-precio').value) || 0;
        const validez = document.getElementById('nc-validez').value;
        const notas   = document.getElementById('nc-notas').value.trim();

        if (precio <= 0) {
            document.getElementById('nc-precio').classList.add('nc-error');
            document.getElementById('nc-precio').focus();
            return;
        }

        const btn = this;
        btn.disabled  = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';

        try {
            // Foto opcional como base64 en el body JSON (evita multipart/form-data,
            // que da problemas raros con LocalWP). Comprimimos primero para mantener
            // el JSON bajo 1MB incluso con la inflación de base64.
            let fotoB64 = '';
            const fotoFile = inpFoto.files && inpFoto.files[0];
            if (fotoFile) {
                console.log('[foto] original:', fotoFile.name, fotoFile.type, (fotoFile.size/1024).toFixed(0) + ' KB');
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Comprimiendo foto...';
                try {
                    fotoB64 = await comprimirImagenBase64(fotoFile, 1280, 0.78);
                    console.log('[foto] base64 size:', (fotoB64.length/1024).toFixed(0) + ' KB');
                    // Si quedó demasiado grande, intenta una vez más con menos calidad
                    if (fotoB64.length > 900 * 1024) {
                        fotoB64 = await comprimirImagenBase64(fotoFile, 1024, 0.68);
                        console.log('[foto] base64 size (re-compr):', (fotoB64.length/1024).toFixed(0) + ' KB');
                    }
                } catch (errImg) {
                    console.warn('[foto] compresión falló:', errImg.message);
                    throw new Error('No se pudo procesar la imagen. Intenta con otra o sin foto.');
                }
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Subiendo...';
            }

            let action, body;
            if (modoCot === 'existente') {
                const id_pp = parseInt(document.getElementById('pd-prod-existente').value, 10);
                if (!id_pp) throw new Error("Selecciona un producto");
                action = 'add_cotizacion';
                body   = {
                    id_proveedor_producto: id_pp,
                    precio, validez, notas,
                    foto_b64: fotoB64
                };
            } else {
                const nombre = document.getElementById('nc-nombre').value.trim();
                if (!nombre) {
                    document.getElementById('nc-nombre').classList.add('nc-error');
                    throw new Error("Falta nombre");
                }
                action = 'add_producto';
                body   = {
                    id_proveedor:     ID_PROV,
                    producto_nombre:  nombre,
                    variedad:         document.getElementById('nc-variedad').value.trim(),
                    calibre:          document.getElementById('nc-calibre').value.trim(),
                    unidad:           document.getElementById('nc-unidad').value.trim(),
                    formato:          document.getElementById('nc-formato').value.trim(),
                    id_producto_link: document.getElementById('nc-link-producto').value || null,
                    precio, validez, notas: notas,
                    foto_b64: fotoB64
                };
            }

            const res = await fetch(URL_API + '&action=' + action, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(body)
            });
            let data;
            try { data = await res.json(); }
            catch { throw new Error('Respuesta no válida del servidor (HTTP ' + res.status + ')'); }
            if (!data.success) throw new Error(data.error || ('HTTP ' + res.status));

            pd_cerrarModal();
            await cargarProductos();
        } catch (e) {
            btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + (e.message || 'Error');
            btn.style.background = '#e74c3c';
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar';
                btn.style.background = '';
            }, 2500);
            return;
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar';
    });

    // ── Historial de un producto ──────────────────────────────
    let histDataActual = [];   // historial completo del producto abierto
    let histChart      = null; // instancia Chart.js

    window.pd_verHistorial = async function (id_pp, nombre) {
        document.getElementById('hist-producto').textContent = nombre;
        document.getElementById('modal-historial').style.display = 'flex';
        const tbody = document.getElementById('hist-tbody');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#aaa;">Cargando...</td></tr>';

        try {
            const res = await fetch(URL_API + '&action=get_historial&id_proveedor_producto=' + id_pp);
            histDataActual = await res.json();
            if (!histDataActual.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#aaa;">Sin historial</td></tr>';
                document.getElementById('hist-grafico-vacio').style.display = 'block';
                document.getElementById('hist-grafico').style.display = 'none';
                return;
            }
            // Sets de fechas por defecto: del primer cotizado al último
            const fechas = histDataActual.map(h => h.fecha_cotizacion.slice(0,10)).sort();
            document.getElementById('hist-desde').value = fechas[0];
            document.getElementById('hist-hasta').value = fechas[fechas.length-1];
            marcarBotonRango(null); // ninguno activo (default = todo el rango)
            renderHistorial();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#c00;">Error al cargar</td></tr>';
        }
    };

    function renderHistorial() {
        const desde = document.getElementById('hist-desde').value;
        const hasta = document.getElementById('hist-hasta').value;
        const filtrado = histDataActual.filter(h => {
            const f = h.fecha_cotizacion.slice(0,10);
            if (desde && f < desde) return false;
            if (hasta && f > hasta) return false;
            return true;
        });
        renderGrafico(filtrado);
        renderTablaHistorial(filtrado);
    }

    function renderTablaHistorial(list) {
        const tbody = document.getElementById('hist-tbody');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:#aaa;">Sin datos en el rango</td></tr>';
            return;
        }
        // Orden: más reciente arriba en tabla; pero el delta se calcula
        // contra la cotización inmediatamente anterior (en orden cronológico).
        const cronologico = [...list].sort((a,b) => a.fecha_cotizacion.localeCompare(b.fecha_cotizacion));
        const deltas = new Map(); // id -> { txt, klass }
        cronologico.forEach((h, i) => {
            if (i === 0) { deltas.set(h.id, { txt: '—', klass: 'pd-delta-eq' }); return; }
            const prev = parseFloat(cronologico[i-1].precio);
            const cur  = parseFloat(h.precio);
            if (prev === 0) { deltas.set(h.id, { txt: '—', klass: 'pd-delta-eq' }); return; }
            const diff = cur - prev;
            const pct  = ((diff / prev) * 100);
            if (diff === 0) deltas.set(h.id, { txt: '0%', klass: 'pd-delta-eq' });
            else if (diff > 0) deltas.set(h.id, { txt: '▲ ' + pct.toFixed(1) + '%', klass: 'pd-delta-up' });
            else                deltas.set(h.id, { txt: '▼ ' + Math.abs(pct).toFixed(1) + '%', klass: 'pd-delta-down' });
        });

        const ordenadoDesc = [...list].sort((a,b) => b.fecha_cotizacion.localeCompare(a.fecha_cotizacion));
        tbody.innerHTML = ordenadoDesc.map(h => {
            const d = deltas.get(h.id) || { txt: '—', klass: 'pd-delta-eq' };
            const foto = h.foto_url
                ? `<img src="${escapeHtml(h.foto_url)}" class="pd-foto-thumb" alt="" onclick="pd_lightbox('${escapeJs(h.foto_url)}')">`
                : '<span style="color:#ccc; font-size:14px;">—</span>';
            return `
                <tr>
                    <td>${new Date(h.fecha_cotizacion).toLocaleDateString('es-CL')}<br>
                        <span style="font-size:10px; color:#999;">${new Date(h.fecha_cotizacion).toLocaleTimeString('es-CL', {hour:'2-digit', minute:'2-digit'})}</span>
                    </td>
                    <td style="text-align:center;">${foto}</td>
                    <td class="pd-precio">${fmt(h.precio)}</td>
                    <td style="text-align:center;"><span class="${d.klass}">${d.txt}</span></td>
                    <td style="text-align:center;"><span class="badge-validez">${h.validez}</span></td>
                    <td style="font-size:12px; color:#666;">${escapeHtml(h.notas || '—')}</td>
                    <td style="font-size:11px; color:#888;">${escapeHtml(h.registrado_por || '—')}</td>
                </tr>`;
        }).join('');
    }

    function renderGrafico(list) {
        const canvas = document.getElementById('hist-grafico');
        const vacio  = document.getElementById('hist-grafico-vacio');
        if (!list.length) {
            canvas.style.display = 'none';
            vacio.style.display  = 'block';
            if (histChart) { histChart.destroy(); histChart = null; }
            return;
        }
        canvas.style.display = 'block';
        vacio.style.display  = 'none';
        // Orden cronológico ascendente para el gráfico
        const cronologico = [...list].sort((a,b) => a.fecha_cotizacion.localeCompare(b.fecha_cotizacion));
        const labels  = cronologico.map(h => new Date(h.fecha_cotizacion).toLocaleDateString('es-CL', {day:'2-digit', month:'2-digit'}));
        const datos   = cronologico.map(h => parseFloat(h.precio));

        if (histChart) histChart.destroy();
        histChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Precio',
                    data:  datos,
                    borderColor: '#6f4a1f',
                    backgroundColor: 'rgba(111,74,31,0.12)',
                    pointBackgroundColor: '#6f4a1f',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.25,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => fmt(ctx.parsed.y)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: v => fmt(v),
                            font: { size: 10 }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        ticks: { font: { size: 10 } },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Cambios manuales del rango → re-render
    document.getElementById('hist-desde').addEventListener('change', () => { marcarBotonRango(null); renderHistorial(); });
    document.getElementById('hist-hasta').addEventListener('change', () => { marcarBotonRango(null); renderHistorial(); });

    function setRango(dias) {
        if (!histDataActual.length) return;
        const fechas = histDataActual.map(h => h.fecha_cotizacion.slice(0,10)).sort();
        const ultima = fechas[fechas.length-1];
        if (dias === null) {
            // "Todo"
            document.getElementById('hist-desde').value = fechas[0];
            document.getElementById('hist-hasta').value = ultima;
        } else {
            const desde = new Date(ultima + 'T00:00:00');
            desde.setDate(desde.getDate() - dias);
            document.getElementById('hist-desde').value = desde.toISOString().slice(0,10);
            document.getElementById('hist-hasta').value = ultima;
        }
        renderHistorial();
    }

    function marcarBotonRango(activo) {
        document.querySelectorAll('.pd-btn-rango').forEach(b => b.classList.remove('active'));
        if (activo) document.getElementById(activo).classList.add('active');
    }

    document.getElementById('btnRangoTodo').addEventListener('click', () => { setRango(null); marcarBotonRango('btnRangoTodo'); });
    document.getElementById('btnRango90')  .addEventListener('click', () => { setRango(90);   marcarBotonRango('btnRango90');   });
    document.getElementById('btnRango30')  .addEventListener('click', () => { setRango(30);   marcarBotonRango('btnRango30');   });

    window.pd_cerrarHistorial = function () {
        document.getElementById('modal-historial').style.display = 'none';
        if (histChart) { histChart.destroy(); histChart = null; }
    };

    // ── Edición del proveedor ─────────────────────────────────
    document.getElementById('btnEditarProveedor').addEventListener('click', () => {
        if (!proveedor) return;
        document.getElementById('ep-nombre')   .value = proveedor.nombre    || '';
        document.getElementById('ep-rut')      .value = proveedor.rut       || '';
        document.getElementById('ep-contacto') .value = proveedor.contacto  || '';
        document.getElementById('ep-telefono') .value = proveedor.telefono  || '';
        document.getElementById('ep-email')    .value = proveedor.email     || '';
        document.getElementById('ep-ciudad')   .value = proveedor.ciudad    || '';
        document.getElementById('ep-direccion').value = proveedor.direccion || '';
        document.getElementById('ep-notas')    .value = proveedor.notas     || '';
        document.getElementById('modal-editar-proveedor').style.display = 'flex';
        document.getElementById('ep-nombre').focus();
    });

    window.pd_cerrarEdicion = function () {
        document.getElementById('modal-editar-proveedor').style.display = 'none';
    };

    document.getElementById('btnGuardarEdicion').addEventListener('click', async function () {
        const nombre = document.getElementById('ep-nombre').value.trim();
        if (!nombre) {
            document.getElementById('ep-nombre').classList.add('nc-error');
            document.getElementById('ep-nombre').focus();
            return;
        }
        const btn = this;
        btn.disabled  = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
        try {
            const res = await fetch(URL_API + '&action=update_proveedor', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_proveedor: ID_PROV,
                    nombre:       nombre,
                    rut:          document.getElementById('ep-rut').value.trim(),
                    contacto:     document.getElementById('ep-contacto').value.trim(),
                    telefono:     document.getElementById('ep-telefono').value.trim(),
                    email:        document.getElementById('ep-email').value.trim(),
                    ciudad:       document.getElementById('ep-ciudad').value.trim(),
                    direccion:    document.getElementById('ep-direccion').value.trim(),
                    notas:        document.getElementById('ep-notas').value.trim()
                })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Error');
            pd_cerrarEdicion();
            await cargarProveedor();
        } catch (e) {
            btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Error';
            btn.style.background = '#e74c3c';
            setTimeout(() => {
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar cambios';
                btn.style.background = '';
            }, 2000);
        }
        btn.disabled = false;
        if (!btn.innerHTML.includes('Error')) {
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar cambios';
        }
    });

    // ── Consultar precio en fecha específica ──────────────────
    let fecPpActual = null;
    window.pd_consultarFecha = function (id_pp, nombre) {
        fecPpActual = id_pp;
        document.getElementById('fec-producto').textContent = nombre;
        document.getElementById('fec-input').value = new Date().toISOString().slice(0,10);
        document.getElementById('fec-resultado').innerHTML =
            '<div style="color:#999; font-size:12px;">Selecciona una fecha para consultar el precio que estaba registrado ese día.</div>';
        document.getElementById('modal-fecha').style.display = 'flex';
        // Disparar consulta inicial (con la fecha de hoy)
        consultarPrecioEnFecha();
    };

    window.pd_cerrarFecha = function () {
        document.getElementById('modal-fecha').style.display = 'none';
        fecPpActual = null;
    };

    async function consultarPrecioEnFecha() {
        if (!fecPpActual) return;
        const fecha = document.getElementById('fec-input').value;
        if (!fecha) return;
        const cont = document.getElementById('fec-resultado');
        cont.innerHTML = '<div style="color:#999; font-size:12px;">Consultando...</div>';
        try {
            const res  = await fetch(URL_API + '&action=precio_en_fecha&id_proveedor_producto=' + fecPpActual + '&fecha=' + fecha);
            const data = await res.json();
            if (!data.encontrado) {
                cont.innerHTML = `
                    <div style="text-align:center; padding:20px; background:#fafafa; border:1px dashed #ddd; border-radius:8px; color:#999;">
                        <i class="fa-solid fa-circle-info" style="font-size:22px; margin-bottom:6px;"></i>
                        <div style="font-size:13px; font-weight:700;">Sin precio registrado para esa fecha</div>
                        <div style="font-size:11px; margin-top:4px;">No hay cotizaciones anteriores o iguales a ${formatFecha(fecha)}.</div>
                    </div>`;
                return;
            }
            const c = data.cotizacion;
            const fechaCot = new Date(c.fecha_cotizacion).toLocaleDateString('es-CL');
            const venceEl  = new Date(c.valido_hasta + 'T00:00:00').toLocaleDateString('es-CL');
            const fotoBlock = c.foto_url
                ? `<div style="margin-top:12px;">
                       <strong style="font-size:12px;">Foto del producto:</strong><br>
                       <img src="${escapeHtml(c.foto_url)}" alt="" onclick="pd_lightbox('${escapeJs(c.foto_url)}')"
                            style="margin-top:6px; max-width:140px; max-height:140px; border-radius:8px; border:1px solid #ddd; cursor:zoom-in;">
                   </div>`
                : '';
            cont.innerHTML = `
                <div style="background:#fff; border:1px solid #e0e0e0; border-radius:10px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                        <div>
                            <div style="font-size:10px; text-transform:uppercase; color:#888; letter-spacing:0.4px; font-weight:700; margin-bottom:3px;">Precio en ${formatFecha(fecha)}</div>
                            <div class="pd-precio" style="font-size:24px;">${fmt(c.precio)}</div>
                        </div>
                        <span class="badge-vigencia ${data.estado}">${data.estado === 'vigente' ? 'Vigente ese día' : 'Vencido ese día'}</span>
                    </div>
                    <hr style="margin: 12px 0; border: none; border-top: 1px dashed #eee;">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; font-size:12px;">
                        <div><strong>Cotizado el:</strong><br><span style="color:#666;">${fechaCot}</span></div>
                        <div><strong>Validez:</strong><br><span class="badge-validez">${c.validez}</span></div>
                        <div><strong>Vencía el:</strong><br><span style="color:#666;">${venceEl}</span></div>
                        <div><strong>Registrado por:</strong><br><span style="color:#666; font-size:11px;">${escapeHtml(c.registrado_por || '—')}</span></div>
                        ${c.notas ? `<div style="grid-column:1/-1;"><strong>Notas:</strong><br><span style="color:#666;">${escapeHtml(c.notas)}</span></div>` : ''}
                    </div>
                    ${fotoBlock}
                </div>`;
        } catch (e) {
            cont.innerHTML = '<div style="color:#c00; font-size:12px;">Error al consultar</div>';
        }
    }

    function formatFecha(yyyymmdd) {
        return new Date(yyyymmdd + 'T00:00:00').toLocaleDateString('es-CL');
    }

    // El listener del input de fecha — se ejecuta al cambiar
    document.getElementById('fec-input').addEventListener('change', consultarPrecioEnFecha);

    // ── Lightbox ─────────────────────────────────────────────
    window.pd_lightbox = function (url) {
        const lb = document.getElementById('pd-lightbox');
        document.getElementById('pd-lightbox-img').src = url;
        lb.style.display = 'flex';
    };
    window.pd_cerrarLightbox = function () {
        document.getElementById('pd-lightbox').style.display = 'none';
        document.getElementById('pd-lightbox-img').src = '';
    };
    // ESC cierra el lightbox
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.getElementById('pd-lightbox').style.display === 'flex') {
            pd_cerrarLightbox();
        }
    });

    // ── Util ─────────────────────────────────────────────────
    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, ch => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        })[ch]);
    }
    function escapeJs(s) {
        return String(s ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    // ── Init ─────────────────────────────────────────────────
    cargarProveedor();
    cargarProductos();
    cargarCatalogoVinculable();
})();
