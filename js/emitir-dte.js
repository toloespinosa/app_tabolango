// ============================================================
// EMITIR DTE MANUAL — form dinámico para 4 tipos de DTE
// ============================================================
(function () {
    if (!document.getElementById('contenedor-emitir-dte')) return;

    const URL_API = window.getApi('api_dte_manual.php');
    const fmt     = window.formatCLP || (v => '$' + v);

    // Estado
    let tipoActivo  = 33;            // 33 | 46 | 52 | 61
    let clientes    = [];
    let referencias = [];            // DTEs disponibles para Nota de Crédito

    // ── Inicializar fecha (hoy) ──────────────────────────────
    document.getElementById('dte-fecha').value = new Date().toISOString().slice(0, 10);

    // ────────────────────────────────────────────────────────
    // CHIPS DE TIPO DTE
    // ────────────────────────────────────────────────────────
    document.querySelectorAll('.dte-chip').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.dte-chip').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            tipoActivo = parseInt(this.dataset.tipo, 10);
            ajustarFormPorTipo();
        });
    });

    function ajustarFormPorTipo() {
        // Forma de pago: solo aplica a 33 (Factura Afecta)
        document.getElementById('dte-forma-pago-wrap').style.display =
            tipoActivo === 33 ? 'block' : 'none';

        // Días de crédito: solo si tipo=33 y forma=crédito
        actualizarCreditoVisible();

        // Bloque referencia: solo Nota de Crédito (61)
        document.getElementById('dte-referencia-wrap').style.display =
            tipoActivo === 61 ? 'block' : 'none';

        // Bloque traslado: solo Guía de Despacho (52)
        document.getElementById('dte-traslado-wrap').style.display =
            tipoActivo === 52 ? 'block' : 'none';

        // Label del receptor cambia según el tipo
        const lbl = document.getElementById('dte-label-receptor');
        if (tipoActivo === 46)      lbl.textContent = 'Proveedor (vendedor)';
        else if (tipoActivo === 52) lbl.textContent = 'Receptor / Cliente del traslado';
        else                        lbl.textContent = 'Receptor (Cliente)';
    }

    function actualizarCreditoVisible() {
        const formaPago = parseInt(document.getElementById('dte-forma-pago').value, 10);
        document.getElementById('dte-credito-wrap').style.display =
            (tipoActivo === 33 && formaPago === 2) ? 'grid' : 'none';
    }
    document.getElementById('dte-forma-pago').addEventListener('change', actualizarCreditoVisible);

    // ────────────────────────────────────────────────────────
    // CARGAR CLIENTES
    // ────────────────────────────────────────────────────────
    async function cargarClientes() {
        try {
            const res = await fetch(URL_API + '&action=get_clientes');
            clientes  = await res.json();
            const sel = document.getElementById('dte-cliente-sel');
            const manualOpt = sel.querySelector('option[value="manual"]');
            clientes.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_interno;
                opt.textContent = c.cliente + (c.razon_social ? ' — ' + c.razon_social : '');
                sel.insertBefore(opt, manualOpt);
            });
        } catch (e) {
            console.error('Error cargando clientes:', e);
        }
    }

    document.getElementById('dte-cliente-sel').addEventListener('change', function () {
        const val = this.value;
        const ids = ['dte-rec-rut','dte-rec-razon','dte-rec-giro',
                     'dte-rec-direccion','dte-rec-comuna','dte-rec-email'];

        if (val === '') {
            ids.forEach(id => { const e = document.getElementById(id); e.value = ''; e.disabled = true; });
            return;
        }
        if (val === 'manual') {
            ids.forEach(id => { const e = document.getElementById(id); e.value = ''; e.disabled = false; });
            document.getElementById('dte-rec-rut').focus();
            return;
        }
        // Rellenar desde el cliente seleccionado (campos editables por si necesitas ajustar)
        const c = clientes.find(x => String(x.id_interno) === String(val));
        if (!c) return;
        document.getElementById('dte-rec-rut').value       = c.rut_cliente   || '';
        document.getElementById('dte-rec-razon').value     = c.razon_social  || c.cliente || '';
        document.getElementById('dte-rec-giro').value      = c.giro          || '';
        document.getElementById('dte-rec-direccion').value = c.direccion     || '';
        document.getElementById('dte-rec-comuna').value    = c.comuna || c.ciudad || '';
        document.getElementById('dte-rec-email').value     = c.email         || '';
        ids.forEach(id => document.getElementById(id).disabled = false);
    });

    // ────────────────────────────────────────────────────────
    // CARGAR REFERENCIAS (DTEs previos para Nota de Crédito)
    // ────────────────────────────────────────────────────────
    async function cargarReferencias() {
        try {
            const res = await fetch(URL_API + '&action=get_referencias');
            referencias = await res.json();
            const sel = document.getElementById('dte-ref-dte');
            referencias.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.id;
                const fecha = new Date(r.fecha_emision).toLocaleDateString('es-CL');
                opt.textContent = 'Tipo ' + r.tipo_documento + ' · Folio ' + r.folio + ' · ' + fecha;
                opt.dataset.tipo  = r.tipo_documento;
                opt.dataset.folio = r.folio;
                opt.dataset.fecha = r.fecha_emision.slice(0, 10);
                sel.appendChild(opt);
            });
        } catch (e) {
            console.error('Error cargando referencias:', e);
        }
    }

    // ────────────────────────────────────────────────────────
    // ITEMS (tabla dinámica)
    // ────────────────────────────────────────────────────────
    function nuevaFilaItem() {
        const tr = document.createElement('tr');
        tr.className = 'dte-fila-item';
        tr.innerHTML = `
            <td><input type="text"   class="it-nombre"   placeholder="Nombre"></td>
            <td><input type="number" class="it-cant"     value="1" min="0" step="any"></td>
            <td><input type="text"   class="it-unidad"   value="Unid"></td>
            <td><input type="number" class="it-precio"   value="0" min="0" step="1"></td>
            <td class="dte-item-subtotal it-subtotal">$0</td>
            <td style="text-align:center;">
                <input type="checkbox" class="it-exento" title="Exento de IVA">
            </td>
            <td style="text-align:center;">
                <button type="button" class="dte-item-quitar" title="Quitar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </td>`;
        document.getElementById('dte-items-tbody').appendChild(tr);

        tr.querySelectorAll('.it-cant, .it-precio, .it-exento').forEach(inp => {
            inp.addEventListener('input',  recalcularTotales);
            inp.addEventListener('change', recalcularTotales);
        });
        tr.querySelector('.dte-item-quitar').addEventListener('click', () => {
            tr.remove();
            recalcularTotales();
        });
        return tr;
    }

    function recalcularTotales() {
        let neto = 0, exento = 0;
        document.querySelectorAll('.dte-fila-item').forEach(fila => {
            const cant   = parseFloat(fila.querySelector('.it-cant').value)   || 0;
            const precio = parseFloat(fila.querySelector('.it-precio').value) || 0;
            const ex     = fila.querySelector('.it-exento').checked;
            const sub    = Math.round(cant * precio);
            fila.querySelector('.it-subtotal').textContent = fmt(sub);
            if (ex) exento += sub; else neto += sub;
        });
        const iva   = Math.round(neto * 0.19);
        const total = neto + exento + iva;
        document.getElementById('dte-total-neto').textContent   = fmt(neto);
        document.getElementById('dte-total-iva').textContent    = fmt(iva);
        document.getElementById('dte-total-exento').textContent = fmt(exento);
        document.getElementById('dte-total-grand').textContent  = fmt(total);
        document.getElementById('dte-row-exento').style.display = exento > 0 ? 'flex' : 'none';
    }

    document.getElementById('dte-btn-add-item').addEventListener('click', () => {
        nuevaFilaItem();
    });
    // Una fila inicial vacía
    nuevaFilaItem();

    // ────────────────────────────────────────────────────────
    // GENERAR DTE
    // ────────────────────────────────────────────────────────
    document.getElementById('dte-btn-generar').addEventListener('click', async function () {
        const msg = document.getElementById('dte-msg');
        msg.innerHTML = '';

        // Recolectar items
        const items = [];
        let huboError = false;
        document.querySelectorAll('.dte-fila-item').forEach(fila => {
            const nombre = fila.querySelector('.it-nombre').value.trim();
            const cant   = parseFloat(fila.querySelector('.it-cant').value)   || 0;
            const precio = parseFloat(fila.querySelector('.it-precio').value) || 0;
            const unidad = fila.querySelector('.it-unidad').value.trim() || 'Unid';
            const exento = fila.querySelector('.it-exento').checked;
            if (!nombre || cant <= 0 || precio < 0) {
                fila.querySelector('.it-nombre').classList.toggle('dte-error', !nombre);
                huboError = true;
                return;
            }
            items.push({ nombre, cantidad: cant, precio, unidad, exento });
        });
        if (huboError || items.length === 0) {
            msg.innerHTML = '<span style="color:#c00;">Revisa los ítems — falta nombre, cantidad o precio.</span>';
            return;
        }

        // Receptor
        const receptor = {
            rut:          document.getElementById('dte-rec-rut').value.trim(),
            razon_social: document.getElementById('dte-rec-razon').value.trim(),
            giro:         document.getElementById('dte-rec-giro').value.trim(),
            direccion:    document.getElementById('dte-rec-direccion').value.trim(),
            comuna:       document.getElementById('dte-rec-comuna').value.trim(),
            email:        document.getElementById('dte-rec-email').value.trim(),
        };
        if (!receptor.rut || !receptor.razon_social) {
            msg.innerHTML = '<span style="color:#c00;">El receptor necesita RUT y Razón Social.</span>';
            return;
        }

        // Folio + fecha + forma pago
        const folio       = document.getElementById('dte-folio').value.trim();
        const fecha       = document.getElementById('dte-fecha').value;
        const formaPago   = tipoActivo === 33 ? parseInt(document.getElementById('dte-forma-pago').value, 10) : 1;
        const diasCredito = parseInt(document.getElementById('dte-dias-credito').value, 10) || 30;

        // Referencia (solo NC)
        let referencia = null;
        if (tipoActivo === 61) {
            const refSel = document.getElementById('dte-ref-dte');
            const opt    = refSel.options[refSel.selectedIndex];
            if (!opt || !opt.value) {
                msg.innerHTML = '<span style="color:#c00;">Selecciona el DTE que referencia esta Nota de Crédito.</span>';
                return;
            }
            referencia = {
                referencia_dte_id: parseInt(opt.value, 10),
                tipo_doc_ref: parseInt(opt.dataset.tipo,  10),
                folio_ref:    parseInt(opt.dataset.folio, 10),
                fecha_ref:    opt.dataset.fecha,
                codigo_ref:   parseInt(document.getElementById('dte-ref-codigo').value, 10),
                razon_ref:    document.getElementById('dte-ref-razon').value.trim() || 'Anula documento',
            };
        }

        // Traslado (solo Guía)
        let traslado = null;
        if (tipoActivo === 52) {
            const tipoTr = document.getElementById('dte-traslado-tipo').value;
            const indTr  = document.getElementById('dte-traslado-ind').value;
            if (!tipoTr || !indTr) {
                msg.innerHTML = '<span style="color:#c00;">La Guía de Despacho necesita tipo e indicador de traslado.</span>';
                return;
            }
            traslado = { tipo_traslado: parseInt(tipoTr, 10), ind_traslado: parseInt(indTr, 10) };
        }

        // Disparar
        const btn = this;
        btn.disabled  = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando con SimpleAPI...';
        msg.innerHTML = '<span style="color:#999; font-size:12px;">Esto puede tardar unos segundos...</span>';

        try {
            const res = await fetch(URL_API + '&action=generar_dte', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tipo_dte:      tipoActivo,
                    folio:         folio || null,
                    fecha_emision: fecha,
                    forma_pago:    formaPago,
                    dias_credito:  diasCredito,
                    receptor:      receptor,
                    items:         items,
                    referencia:    referencia,
                    traslado:      traslado,
                }),
            });
            let data;
            try { data = await res.json(); }
            catch { throw new Error('Respuesta inválida del servidor (HTTP ' + res.status + ')'); }
            if (!data.success) throw new Error(data.error || 'Error al generar');

            const bgOk    = data.simulado ? '#fff8e1' : '#e8f5e9';
            const brdOk   = data.simulado ? '#ffe082' : '#c8e6c9';
            const txtOk   = data.simulado ? '#b26a00' : '#1b5e20';
            const titulo  = data.simulado ? '🧪 DTE simulado (modo LOCAL)' : '✅ DTE generado';
            msg.innerHTML = `
                <div style="background:${bgOk}; border:1px solid ${brdOk}; border-radius:8px; padding:12px 14px; color:${txtOk};">
                    <strong>${titulo}.</strong> Tipo ${data.tipo}, Folio ${data.folio}.<br>
                    <span style="font-size:12px;">${data.mensaje || ''}</span>
                    ${data.url_pdf ? `<br><a href="${data.url_pdf}" target="_blank" style="color:${txtOk}; font-weight:700; margin-right:8px;">📄 Ver PDF</a>` : ''}
                    ${data.url_xml ? `<a href="${data.url_xml}" target="_blank" style="color:${txtOk}; font-weight:700;">📄 Ver XML</a>` : ''}
                </div>`;

            // Limpiar form para emitir el próximo
            limpiarFormParcialmente();
            cargarRecientes();
        } catch (e) {
            msg.innerHTML = '<span style="color:#c00;">❌ ' + (e.message || 'Error') + '</span>';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rocket"></i> Generar DTE';
        }
    });

    function limpiarFormParcialmente() {
        document.getElementById('dte-folio').value = '';
        document.querySelectorAll('.dte-fila-item').forEach(f => f.remove());
        nuevaFilaItem();
        recalcularTotales();
    }

    // ────────────────────────────────────────────────────────
    // LISTA DE DTEs MANUALES RECIENTES
    // ────────────────────────────────────────────────────────
    async function cargarRecientes() {
        const cont = document.getElementById('dte-recientes-list');
        try {
            const res  = await fetch(URL_API + '&action=list_recientes');
            const list = await res.json();
            if (!list.length) {
                cont.innerHTML = '<div style="text-align:center; color:#aaa; padding:30px; font-size:12px;">Aún no emites DTEs manuales.</div>';
                return;
            }
            cont.innerHTML = list.map(d => {
                const fecha = new Date(d.fecha_emision).toLocaleDateString('es-CL');
                const hora  = new Date(d.fecha_emision).toLocaleTimeString('es-CL', {hour:'2-digit', minute:'2-digit'});
                return `
                    <div class="dte-reciente-card">
                        <div class="dte-rec-head">
                            <span class="dte-rec-tipo">Tipo ${escapeHtml(d.tipo_documento)}</span>
                            <span class="dte-rec-folio">Folio ${d.folio}</span>
                        </div>
                        <div class="dte-rec-meta">
                            ${fecha} ${hora}
                            ${d.emitido_por ? ' · ' + escapeHtml(d.emitido_por) : ''}
                            ${d.track_id ? '<br>Track: ' + escapeHtml(d.track_id) : ''}
                        </div>
                        <div class="dte-rec-acciones">
                            <span class="dte-estado ${escapeHtml(d.estado_envio)}">${escapeHtml(d.estado_envio)}</span>
                            <a href="${d.url_pdf || '#'}" target="_blank" class="${d.url_pdf ? '' : 'disabled'}">PDF</a>
                            <a href="${d.url_xml || '#'}" target="_blank" class="${d.url_xml ? '' : 'disabled'}">XML</a>
                        </div>
                    </div>`;
            }).join('');
        } catch (e) {
            cont.innerHTML = '<div style="color:#c00; font-size:12px; padding:20px;">Error al cargar.</div>';
        }
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, ch => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        })[ch]);
    }

    // ── Init ─────────────────────────────────────────────────
    ajustarFormPorTipo();
    cargarClientes();
    cargarReferencias();
    cargarRecientes();
})();
