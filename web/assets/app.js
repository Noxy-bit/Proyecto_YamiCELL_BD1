/* YAMICELL — lógica frontend (vanilla JS + Chart.js + Mermaid) */

// ---------- Utilidades ----------
const fmtBs = n => 'Bs. ' + Number(n).toLocaleString('es-BO', {minimumFractionDigits: 2, maximumFractionDigits: 2});

function toast(msg, kind = 'ok') {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = 'toast show ' + kind;
    setTimeout(() => t.classList.remove('show'), 5000);
}
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
async function api(action, opts = {}) {
    const method = opts.method || (opts.body ? 'POST' : 'GET');
    const res = await fetch('api.php?action=' + action, {
        method,
        headers: opts.body ? {'Content-Type': 'application/json'} : {},
        body: opts.body ? JSON.stringify(opts.body) : undefined,
    });
    return res.json();
}
function renderTable(cols, rows) {
    let h = '<div class="table-wrap"><table class="data"><thead><tr>' +
        cols.map(c => `<th>${c}</th>`).join('') + '</tr></thead><tbody>';
    for (const r of rows) h += '<tr>' + cols.map((_, i) => `<td>${r[i] ?? ''}</td>`).join('') + '</tr>';
    return h + '</tbody></table></div>';
}

// ---------- Reset demo (topbar) ----------
document.getElementById('btnReset')?.addEventListener('click', async () => {
    if (!confirm('¿Regenerar la base de datos con los datos de demostración?')) return;
    const r = await api('reset', {method: 'POST'});
    if (r.ok) { toast(r.mensaje); setTimeout(() => location.reload(), 800); }
    else toast(r.error, 'err');
});

// ---------- Dashboard ----------
let charts = {};
async function loadDashboard(suc) {
    const d = await api('dashboard&sucursal=' + suc);
    if (!d.ok) return toast(d.error, 'err');
    document.getElementById('k-ing').textContent    = fmtBs(d.kpis.ingresos);
    document.getElementById('k-hoy').textContent    = 'Hoy: ' + fmtBs(d.ventas_hoy);
    document.getElementById('k-num').textContent    = d.kpis.num_ventas;
    document.getElementById('k-ticket').textContent = fmtBs(d.kpis.ticket_promedio);
    document.getElementById('k-gan').textContent    = fmtBs(d.kpis.ganancia);
    document.getElementById('k-alert').textContent  = d.alertas;

    const palette = ['#0ea5e9', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444', '#14b8a6', '#f97316'];
    const mk = (id, cfg) => { charts[id]?.destroy(); charts[id] = new Chart(document.getElementById(id), cfg); };

    mk('chDia', {type: 'line', data: {labels: d.por_dia.map(r => r.dia),
        datasets: [{label: 'Ingresos (Bs.)', data: d.por_dia.map(r => r.total), borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,.12)', fill: true, tension: .35}]},
        options: {plugins: {legend: {display: false}}}});
    mk('chCat', {type: 'doughnut', data: {labels: d.por_categoria.map(r => r.cat),
        datasets: [{data: d.por_categoria.map(r => r.total), backgroundColor: palette}]},
        options: {plugins: {legend: {position: 'right'}}}});
    mk('chSuc', {type: 'bar', data: {labels: d.por_sucursal.map(r => r.nombre_sucursal),
        datasets: [{label: 'Ingresos (Bs.)', data: d.por_sucursal.map(r => r.total),
                    backgroundColor: ['#0284c7', '#8b5cf6']}]},
        options: {plugins: {legend: {display: false}}}});
    mk('chPago', {type: 'polarArea', data: {labels: d.por_pago.map(r => r.metodo_pago),
        datasets: [{data: d.por_pago.map(r => r.total), backgroundColor: palette.map(c => c + 'cc')}]}});
    mk('chTop', {type: 'bar', data: {labels: d.top_productos.map(r => r.sku),
        datasets: [{label: 'Unidades', data: d.top_productos.map(r => r.unidades), backgroundColor: '#0ea5e9'}]},
        options: {indexAxis: 'y', plugins: {legend: {display: false}}}});

    document.getElementById('tblCrit').innerHTML = d.stock_critico.map(r =>
        `<tr><td><span class="chip">${r.sku}</span></td><td>${r.nombre_producto}</td><td class="muted">${r.nombre_sucursal}</td>
         <td class="num qty-neg">${r.stock_actual}</td><td class="num">${r.stock_minimo_alerta}</td></tr>`).join('');
    document.getElementById('tblUlt').innerHTML = d.ultimas_ventas.map(r =>
        `<tr><td><span class="chip">${r.codigo_recibo}</span></td><td class="muted">${r.fecha_hora}</td>
         <td>${r.nombre_sucursal}</td><td>${r.cliente}</td><td><span class="badge badge-mut">${r.metodo_pago}</span></td>
         <td class="num">${fmtBs(r.total_neto)}</td></tr>`).join('');
}
function initDashboard() {
    const sw = document.getElementById('sucSwitcher');
    sw.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
        sw.querySelectorAll('button').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
        loadDashboard(+b.dataset.suc);
    }));
    loadDashboard(0);
}

// ---------- POS ----------
const cart = new Map();   // id_producto -> {sku, nombre, qty, precio, stock, costo}

async function posCatalog(qstr = '') {
    const suc = document.getElementById('posSucursal').value;
    const d = await api(`search_productos&sucursal=${suc}&q=${encodeURIComponent(qstr)}`);
    const tb = document.getElementById('posCatalog');
    tb.innerHTML = (d.items || []).map(p => `
        <tr><td><span class="chip">${p.sku}</span></td><td>${p.nombre_producto}</td>
            <td class="muted">${p.marca_compatible ?? ''} ${p.modelo_compatible ?? ''}</td>
            <td class="num ${p.stock_actual <= 0 ? 'qty-neg' : 'qty-pos'}">${p.stock_actual}</td>
            <td class="num">${fmtBs(p.precio_publico)}</td>
            <td><button class="btn btn-primary btn-sm" ${p.stock_actual <= 0 ? 'disabled' : ''}
                onclick='addToCart(${JSON.stringify(p)})'><i class="fa-solid fa-plus"></i></button></td></tr>`).join('');
}
function addToCart(p) {
    const tipo = document.getElementById('posTipoPrecio').value;
    const precio = tipo === 'TECNICO' ? +p.precio_tecnico : +p.precio_publico;
    const cur = cart.get(p.id_producto);
    if (cur) {
        if (cur.qty + 1 > cur.stock) return toast(`Stock insuficiente: ${cur.stock} disponibles en este puesto.`, 'err');
        cur.qty++;
    } else {
        cart.set(p.id_producto, {id: p.id_producto, sku: p.sku, nombre: p.nombre_producto,
                                 qty: 1, precio, stock: p.stock_actual, costo: +p.costo_compra});
    }
    renderCart();
}
function renderCart() {
    const box = document.getElementById('cartLines');
    if (cart.size === 0) { box.innerHTML = '<p class="muted">Carrito vacío — agrega ítems del catálogo.</p>'; }
    else {
        box.innerHTML = [...cart.values()].map(l => `
            <div class="cart-line">
                <div><strong>${l.sku}</strong> · ${l.nombre}<br><span class="muted">máx. ${l.stock} en puesto</span></div>
                <input type="number" min="1" max="${l.stock}" value="${l.qty}"
                       onchange="setQty(${l.id}, this.value)">
                <div class="num">${fmtBs(l.qty * l.precio)}</div>
                <button class="btn btn-err btn-sm" onclick="cart.delete(${l.id}); renderCart();"><i class="fa-solid fa-trash"></i></button>
            </div>`).join('');
    }
    const sub = [...cart.values()].reduce((a, l) => a + l.qty * l.precio, 0);
    const desc = +document.getElementById('posDesc').value || 0;
    document.getElementById('posTotal').textContent = fmtBs(Math.max(sub - desc, 0));
}
function setQty(id, v) {
    const l = cart.get(id); if (!l) return;
    const req = +v || 1;
    if (req > l.stock) toast(`Cantidad limitada al stock del puesto (${l.stock}).`, 'err');
    l.qty = Math.max(1, Math.min(req, l.stock));
    renderCart();
}
async function checkout() {
    if (cart.size === 0) return toast('Agrega productos al carrito.', 'err');
    const msg = document.getElementById('posMsg');
    msg.innerHTML = '<span class="muted">Procesando transacción ACID…</span>';
    const r = await api('venta', {body: {
        id_sucursal: +document.getElementById('posSucursal').value,
        id_cliente:  +document.getElementById('posCliente').value,
        id_usuario:  +document.getElementById('posUsuario').value,
        metodo_pago: document.getElementById('posMetodo').value,
        tipo_precio: document.getElementById('posTipoPrecio').value,
        descuento_bs:+document.getElementById('posDesc').value || 0,
        items: [...cart.values()].map(l => ({id_producto: l.id, cantidad: l.qty})),
    }});
    if (r.ok) {
        cart.clear(); renderCart();
        msg.innerHTML = `<div class="alert alert-ok" style="margin:0"><i class="fa-solid fa-check-circle"></i> ${r.mensaje}</div>`;
        toast(r.mensaje);
        posCatalog(document.getElementById('posSearch').value);
    } else {
        msg.innerHTML = `<div class="alert alert-err" style="margin:0"><i class="fa-solid fa-shield-halved"></i> ${r.error}</div>`;
        toast(r.error, 'err');
    }
}
async function saveCliente(prefix, modalId) {
    const r = await api('cliente_nuevo', {body: {
        ci_nit: document.getElementById(prefix + 'CI').value,
        nombre_completo: document.getElementById(prefix + 'Nombre').value,
        tipo_cliente: document.getElementById(prefix + 'Tipo').value,
        telefono: document.getElementById(prefix + 'Tel').value,
    }});
    if (r.ok) { toast('Cliente registrado.'); closeModal(modalId); setTimeout(() => location.reload(), 700); }
    else toast(r.error, 'err');
}
function initPOS() {
    posCatalog();
    let deb;
    document.getElementById('posSearch').addEventListener('input', e => {
        clearTimeout(deb); deb = setTimeout(() => posCatalog(e.target.value), 180);
    });
    document.getElementById('posSucursal').addEventListener('change', () => {
        cart.clear(); renderCart(); posCatalog(document.getElementById('posSearch').value);
    });
    document.getElementById('posTipoPrecio').addEventListener('change', () => {
        for (const l of cart.values()) { /* re-preciar */ }
        cart.clear(); renderCart();   // los precios dependen de la lista; carrito limpio evita mezclas
    });
    document.getElementById('posDesc').addEventListener('input', renderCart);
    document.getElementById('btnCheckout').addEventListener('click', checkout);
    document.getElementById('btnNewCliente').addEventListener('click', () => openModal('modalCliente'));
    document.getElementById('btnSaveCliente').addEventListener('click', () => saveCliente('nc', 'modalCliente'));
    renderCart();
}

// ---------- Inventario ----------
function openProductoModal(r = null) {
    document.getElementById('pId').value     = r ? r.id_producto : '';
    document.getElementById('pSku').value    = r ? r.sku : '';
    document.getElementById('pNombre').value = r ? r.nombre_producto : '';
    document.getElementById('pMarca').value  = r ? (r.marca_compatible ?? '') : '';
    document.getElementById('pModelo').value = r ? (r.modelo_compatible ?? '') : '';
    if (r) document.getElementById('pCat').value = r.id_categoria;
    if (r && r.id_proveedor) document.getElementById('pProv').value = r.id_proveedor;
    document.getElementById('pCosto').value = r ? r.costo_compra : '';
    document.getElementById('pTec').value   = r ? r.precio_tecnico : '';
    document.getElementById('pPub').value   = r ? r.precio_publico : '';
    document.getElementById('pMin').value   = r ? r.stock_minimo_alerta : 5;
    openModal('modalProducto');
}
async function saveProducto() {
    const r = await api('producto_save', {body: {
        id_producto: +document.getElementById('pId').value || null,
        sku: document.getElementById('pSku').value,
        nombre_producto: document.getElementById('pNombre').value,
        marca_compatible: document.getElementById('pMarca').value,
        modelo_compatible: document.getElementById('pModelo').value,
        id_categoria: +document.getElementById('pCat').value,
        id_proveedor: document.getElementById('pProv').value || null,
        costo_compra: +document.getElementById('pCosto').value || 0,
        precio_tecnico: +document.getElementById('pTec').value || 0,
        precio_publico: +document.getElementById('pPub').value || 0,
        stock_minimo_alerta: +document.getElementById('pMin').value || 5,
    }});
    if (r.ok) { toast('Producto guardado.'); setTimeout(() => location.reload(), 700); }
    else toast(r.error, 'err');
}
function openCompraModal() { openModal('modalCompra'); }
async function saveCompra() {
    const r = await api('compra', {body: {
        id_producto: +document.getElementById('cProd').value,
        id_sucursal: +document.getElementById('cSuc').value,
        cantidad:    +document.getElementById('cQty').value,
        costo_unitario: +document.getElementById('cCosto').value || 0,
    }});
    if (r.ok) { toast(`Ingreso ${r.documento} registrado. Stock resultante: ${r.stock_resultante}`); setTimeout(() => location.reload(), 900); }
    else toast(r.error, 'err');
}
function initInventario() {}

// ---------- Traspasos ----------
function initTraspasos() {
    document.getElementById('btnTraspaso').addEventListener('click', async () => {
        const r = await api('traspaso', {body: {
            id_producto: +document.getElementById('tProd').value,
            id_sucursal_origen: +document.getElementById('tOrigen').value,
            id_sucursal_destino: +document.getElementById('tDestino').value,
            cantidad: +document.getElementById('tQty').value,
            observacion: document.getElementById('tObs').value,
        }});
        if (r.ok) { toast('Traspaso ' + r.codigo_traspaso + ' completado (COMMIT).'); setTimeout(() => location.reload(), 900); }
        else toast(r.error, 'err');
    });
}

// ---------- Directorio ----------
function initDirectorio() {}

// ---------- Defensa INF-312 ----------
function initDefensa() {
    document.querySelectorAll('#defTabs button').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('#defTabs button').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
        document.querySelectorAll('[data-pane]').forEach(p => p.hidden = p.dataset.pane !== b.dataset.tab);
        if (b.dataset.tab === 'der') renderDER();
    }));
    document.getElementById('consoleSql')?.addEventListener('keydown', e => {
        if (e.ctrlKey && e.key === 'Enter') execConsole();
    });
    renderDER();
}
let derDone = false;
async function renderDER() {
    if (derDone || typeof mermaid === 'undefined') return;
    derDone = true;
    mermaid.initialize({startOnLoad: false, theme: 'default', securityLevel: 'loose'});
    const src = document.getElementById('derSrc').textContent;
    const box = document.getElementById('derBox');
    try {
        const {svg} = await mermaid.render('derSvg', src);
        box.innerHTML = svg;
    } catch (e) {
        box.innerHTML = '<div class="alert alert-err">Error renderizando DER: ' + (e.message || e) + '</div>';
        derDone = false;
    }
}
async function execLab(btn, n) {
    btn.disabled = true;
    const r = await api('exec_sql', {body: {sql: LAB_QUERIES[n]}});
    const ms = document.getElementById('ms-' + n);
    const box = document.getElementById('res-' + n);
    btn.disabled = false;
    if (!r.ok) { box.innerHTML = `<div class="alert alert-err" style="margin:8px 0">${r.error}</div>`; return; }
    ms.textContent = ` · ${r.count} filas en ${r.ms} ms`;
    box.innerHTML = renderTable(r.columns, r.rows.map(row => Object.values(row)));
}
async function execConsole() {
    const sql = document.getElementById('consoleSql').value;
    const out = document.getElementById('consoleOut');
    const ms = document.getElementById('consoleMs');
    out.innerHTML = '<span class="muted">Ejecutando…</span>';
    const r = await api('exec_sql', {body: {sql}});
    if (!r.ok) { out.innerHTML = `<div class="alert alert-err" style="margin:0">${r.error}</div>`; ms.textContent = ''; return; }
    ms.textContent = `${r.count} filas · ${r.ms} ms`;
    out.innerHTML = renderTable(r.columns, r.rows.map(row => Object.values(row)));
}
async function execView(btn, view) {
    const box = btn.parentElement.querySelector('.sql-result');
    const r = await api('exec_sql', {body: {sql: `SELECT * FROM ${view} LIMIT 50`}});
    box.innerHTML = r.ok ? renderTable(r.columns, r.rows.map(row => Object.values(row)))
                         : `<div class="alert alert-err" style="margin:8px 0">${r.error}</div>`;
}

// ---------- Boot: inicializar el módulo activo ----------
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('sucSwitcher')) initDashboard();
    if (document.getElementById('posSearch'))    initPOS();
    if (document.getElementById('btnTraspaso'))  initTraspasos();
    if (document.getElementById('defTabs'))      initDefensa();
});
