// ============================================================================
// COTIZADOR DE PRECIOS — página propia
// ============================================================================
(function () {
    if (!document.getElementById('contenedor-cotizador')) return;

    const URL_API = window.getApi('api_cotizacion.php');

    let todosLosProductos = [];
    let categoriaActiva   = 'lista';

    // ── Cargar clientes ──────────────────────────────────────────────────────
    async function cargarClientes() {
        try {
            const res      = await fetch(URL_API + '&action=clientes');
            const clientes = await res.json();
            const sel      = document.getElementById('cot-cliente');
            sel.innerHTML  = '<option value="">— Selecciona un cliente —</option>';
            clientes.forEach(c => {
                const opt       = document.createElement('option');
                opt.value       = c.id_interno;
                opt.textContent = c.razon_social ? `${c.cliente} — ${c.razon_social}` : c.cliente;
                sel.appendChild(opt);
            });
            // Opción especial al final
            const optNuevo       = document.createElement('option');
            optNuevo.value       = 'nuevo';
            optNuevo.textContent = '➕ Cliente nuevo...';
            optNuevo.style.color = '#0f4b29';
            optNuevo.style.fontWeight = '700';
            sel.appendChild(optNuevo);
        } catch (e) {
            console.error('Error cargando clientes:', e);
        }
    }

    // ── Mostrar/ocultar panel nuevo cliente ──────────────────────────────────
    document.getElementById('cot-cliente').addEventListener('change', function () {
        const panel = document.getElementById('panel-nuevo-cliente');
        if (this.value === 'nuevo') {
            panel.style.display = 'block';
            document.getElementById('nc-nombre').focus();
        } else {
            panel.style.display = 'none';
        }
    });

    document.getElementById('btnCancelarCliente').addEventListener('click', function () {
        document.getElementById('panel-nuevo-cliente').style.display = 'none';
        document.getElementById('cot-cliente').value = '';
        limpiarFormNuevoCliente();
    });

    document.getElementById('btnGuardarCliente').addEventListener('click', async function () {
        const nombre      = document.getElementById('nc-nombre').value.trim();
        const responsable = document.getElementById('nc-responsable').value.trim();

        if (!nombre || !responsable) {
            document.getElementById('nc-nombre').classList.toggle('nc-error', !nombre);
            document.getElementById('nc-responsable').classList.toggle('nc-error', !responsable);
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';

        try {
            const res  = await fetch(URL_API + '&action=add_client', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    cliente:     nombre,
                    responsable: responsable,
                    rut:         document.getElementById('nc-rut').value.trim(),
                    telefono:    document.getElementById('nc-telefono').value.trim(),
                    email:       document.getElementById('nc-email').value.trim(),
                    ciudad:      document.getElementById('nc-ciudad').value.trim(),
                }),
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.error || 'Error al guardar');

            // Recargar lista completa y seleccionar el nuevo cliente
            const nuevoId = String(data.id_interno);
            document.getElementById('panel-nuevo-cliente').style.display = 'none';
            limpiarFormNuevoCliente();
            await cargarClientes();
            document.getElementById('cot-cliente').value = nuevoId;

            btn.innerHTML = '<i class="fa-solid fa-check"></i> Guardado';
            btn.style.background = '#27ae60';
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar cliente';
                btn.style.background = '';
            }, 2000);

        } catch (e) {
            console.error('Error guardando cliente:', e);
            btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Error';
            btn.style.background = '#e74c3c';
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar cliente';
                btn.style.background = '';
            }, 2500);
        }
    });

    function limpiarFormNuevoCliente() {
        ['nc-nombre','nc-responsable','nc-rut','nc-telefono','nc-email','nc-ciudad']
            .forEach(id => {
                const el = document.getElementById(id);
                el.value = '';
                el.classList.remove('nc-error');
            });
    }

    // ── Cargar productos ─────────────────────────────────────────────────────
    async function cargarProductos() {
        try {
            const res      = await fetch(URL_API + '&action=productos');
            todosLosProductos = await res.json();
            renderizarProductos();
        } catch (e) {
            console.error('Error cargando productos:', e);
        }
    }

    function getPrecio(p) {
        // Fallback al precio de lista cuando el producto no tiene precio
        // diferenciado en la categoría seleccionada (p1/p2/p4 = 0 o NULL).
        const lista = parseFloat(p.precio_actual) || 0;
        if (categoriaActiva === 'lista') return lista;
        if (categoriaActiva === '1')     return parseFloat(p.p1) || lista;
        if (categoriaActiva === '2')     return parseFloat(p.p2) || lista;
        if (categoriaActiva === '4')     return parseFloat(p.p4) || lista;
        return lista;
    }

    // ── Renderizar tabla ─────────────────────────────────────────────────────
    function renderizarProductos() {
        const tbody = document.getElementById('cot-tbody');
        const q     = (document.getElementById('cot-buscar').value || '').toLowerCase();

        const filtrados = todosLosProductos.filter(p =>
            (p.producto + ' ' + (p.variedad || '')).toLowerCase().includes(q)
        );

        if (!filtrados.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:#aaa;">Sin resultados</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        filtrados.forEach(p => {
            const precio   = getPrecio(p);
            const nombreHtml = p.variedad
                ? `${p.producto} <span class="cot-variedad">${p.variedad}</span>`
                : p.producto;

            const tr = document.createElement('tr');
            tr.className       = 'cot-fila';
            tr.dataset.id      = p.id_producto;
            tr.dataset.nombre  = p.producto + (p.variedad ? ' ' + p.variedad : '');
            tr.dataset.calibre = p.calibre || '-';
            tr.dataset.unidad  = p.unidad  || '-';
            tr.dataset.formato = p.formato || '-';

            tr.innerHTML = `
                <td style="text-align:center;">
                    <input type="checkbox" class="cot-check" onchange="cotizador_actualizarTotales()">
                </td>
                <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:20px;">${p.icono || '📦'}</span>
                        <span>${nombreHtml}</span>
                    </div>
                </td>
                <td style="text-align:center; color:#888;">${p.calibre || '-'}</td>
                <td style="text-align:center;">
                    <span class="badge-unidad">${p.unidad || '-'}</span>
                </td>
                <td style="text-align:center; color:#888;">${p.formato || '-'}</td>
                <td style="text-align:right;">
                    <input type="number" class="cot-precio-input" value="${precio}" min="0" step="1"
                        onchange="cotizador_actualizarTotales()" oninput="cotizador_actualizarTotales()">
                </td>
                <td style="text-align:center;">
                    <input type="number" class="cot-cant-input" value="1" min="1" step="1"
                        onchange="cotizador_actualizarTotales()" oninput="cotizador_actualizarTotales()">
                </td>
                <td class="cot-subtotal" style="text-align:right; font-weight:800; color:#0f4b29;">
                    ${window.formatCLP ? window.formatCLP(precio) : '$0'}
                </td>`;
            tbody.appendChild(tr);
        });

        cotizador_actualizarTotales();
    }

    // ── Cambio de categoría ──────────────────────────────────────────────────
    document.querySelectorAll('.btn-cat').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.btn-cat').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            categoriaActiva = this.dataset.cat;

            document.querySelectorAll('.cot-fila').forEach(tr => {
                const prod = todosLosProductos.find(p => p.id_producto == tr.dataset.id);
                if (!prod) return;
                tr.querySelector('.cot-precio-input').value = getPrecio(prod);
            });
            cotizador_actualizarTotales();
        });
    });

    // ── Seleccionar todos ────────────────────────────────────────────────────
    document.getElementById('cot-check-all').addEventListener('change', function () {
        document.querySelectorAll('.cot-check').forEach(cb => { cb.checked = this.checked; });
        cotizador_actualizarTotales();
    });

    // ── Totales (global para inline handlers) ────────────────────────────────
    window.cotizador_filtrar = function () { renderizarProductos(); };

    window.cotizador_actualizarTotales = function () {
        let neto = 0;

        document.querySelectorAll('.cot-fila').forEach(tr => {
            const check   = tr.querySelector('.cot-check');
            const precio  = parseFloat(tr.querySelector('.cot-precio-input').value) || 0;
            const cant    = parseInt(tr.querySelector('.cot-cant-input').value)      || 1;
            const sub     = check.checked ? precio * cant : 0;
            const fmt     = window.formatCLP || (v => '$' + v);

            tr.querySelector('.cot-subtotal').textContent = check.checked ? fmt(sub) : '—';
            neto += sub;
        });

        const iva   = Math.round(neto * 0.19);
        const total = neto + iva;
        const fmt   = window.formatCLP || (v => '$' + v);

        document.getElementById('cot-total-neto').textContent  = fmt(neto);
        document.getElementById('cot-total-iva').textContent   = fmt(iva);
        document.getElementById('cot-grand-total').textContent = fmt(total);
        document.getElementById('btnGenerarCot').disabled      = neto <= 0;
    };

    // ── Generar PDF ──────────────────────────────────────────────────────────
    document.getElementById('btnGenerarCot').addEventListener('click', function () {
        const productos = [];

        document.querySelectorAll('.cot-fila').forEach(tr => {
            if (!tr.querySelector('.cot-check').checked) return;
            productos.push({
                id:       tr.dataset.id,
                nombre:   tr.dataset.nombre,
                calibre:  tr.dataset.calibre,
                unidad:   tr.dataset.unidad,
                formato:  tr.dataset.formato,
                precio:   parseFloat(tr.querySelector('.cot-precio-input').value) || 0,
                cantidad: parseInt(tr.querySelector('.cot-cant-input').value)      || 1,
            });
        });

        if (!productos.length) return;

        const form = document.getElementById('form-pdf-cot');
        form.action = window.getApi('generar_pdf_cotizacion.php');
        document.getElementById('inp-cliente-id').value    = document.getElementById('cot-cliente').value || 0;
        document.getElementById('inp-productos').value     = JSON.stringify(productos);
        document.getElementById('inp-notas').value         = document.getElementById('cot-notas').value || '';
        document.getElementById('inp-ocultar-total').value = document.getElementById('cot-ocultar-total').checked ? '1' : '0';
        form.submit();
    });

    // ── Init ─────────────────────────────────────────────────────────────────
    cargarClientes();
    cargarProductos();
})();
