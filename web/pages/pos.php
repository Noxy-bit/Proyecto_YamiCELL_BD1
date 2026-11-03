<?php
/** Punto de Venta — carrito multi-ítem con validación de stock y transacción ACID */
$clientes   = q($pdo, "SELECT * FROM clientes ORDER BY nombre_completo")->fetchAll();
$sucursales = q($pdo, "SELECT * FROM sucursales WHERE estado='ACTIVO' ORDER BY id_sucursal")->fetchAll();
$vendedores = q($pdo, "SELECT * FROM usuarios WHERE rol IN ('VENDEDOR','ADMIN') ORDER BY id_usuario")->fetchAll();
?>
<div class="pos-layout">
    <div class="card">
        <h3><i class="fa-solid fa-magnifying-glass"></i> Catálogo en vivo</h3>
        <div class="inline">
            <div class="field" style="flex:2"><label>Sucursal de venta</label>
                <select id="posSucursal">
                    <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre_sucursal']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="flex:3; position:relative"><label>Buscar por SKU, marca o modelo (ej. "A54", "Note 13", "iPhone")</label>
                <input id="posSearch" placeholder="Escribe para filtrar al instante…" autocomplete="off">
                <div id="posResults" class="results" style="display:none"></div>
            </div>
        </div>
        <div class="table-wrap" style="margin-top:14px">
            <table class="data"><thead><tr>
                <th>SKU</th><th>Repuesto / Descripción</th><th>Marca/Modelo</th>
                <th class="num">Stock puesto</th><th class="num">Precio</th><th></th>
            </tr></thead><tbody id="posCatalog"></tbody></table>
        </div>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-cart-shopping"></i> Carrito / Recibo</h3>
        <div class="field"><label>Cliente</label>
            <div style="display:flex;gap:8px">
                <select id="posCliente" style="flex:1">
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id_cliente'] ?>"><?= htmlspecialchars($c['nombre_completo']) ?> (<?= $c['tipo_cliente'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-ghost btn-sm" id="btnNewCliente" title="Nuevo Cliente Rápido"><i class="fa-solid fa-user-plus"></i></button>
            </div>
        </div>
        <div class="inline">
            <div class="field"><label>Lista de precios</label>
                <select id="posTipoPrecio"><option value="PUBLICO">Precio Público (Bs.)</option><option value="TECNICO">Precio Técnico (Bs.)</option></select>
            </div>
            <div class="field"><label>Método de pago</label>
                <select id="posMetodo"><option>EFECTIVO</option><option>QR_SIMPLE</option><option>TRANSFERENCIA</option><option>MIXTO</option></select>
            </div>
            <div class="field"><label>Vendedor</label>
                <select id="posUsuario">
                    <?php foreach ($vendedores as $u): ?><option value="<?= $u['id_usuario'] ?>"><?= htmlspecialchars($u['nombre_completo']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div id="cartLines"></div>
        <div class="inline" style="margin-top:12px">
            <div class="field"><label>Descuento (Bs.)</label><input id="posDesc" type="number" min="0" step="0.5" value="0"></div>
            <div class="pos-total" id="posTotal">Bs. 0.00</div>
        </div>
        <button class="btn btn-ok" id="btnCheckout" style="width:100%;margin-top:10px;justify-content:center;padding:13px">
            <i class="fa-solid fa-lock"></i> Procesar Venta (Transacción ACID)
        </button>
        <div id="posMsg" style="margin-top:10px;font-size:.82rem"></div>
    </div>
</div>

<!-- Modal: Nuevo Cliente Rápido -->
<div class="modal-bg" id="modalCliente"><div class="modal">
    <h3><i class="fa-solid fa-user-plus"></i> Nuevo Cliente Rápido</h3>
    <div class="field"><label>CI / NIT</label><input id="ncCI" placeholder="Ej. 5233418"></div>
    <div class="field"><label>Nombre completo *</label><input id="ncNombre"></div>
    <div class="inline">
        <div class="field"><label>Tipo</label>
            <select id="ncTipo"><option>CLIENTE_FINAL</option><option>TECNICO_AFILIADO</option><option>MAYORISTA</option></select></div>
        <div class="field"><label>Teléfono</label><input id="ncTel"></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn btn-ghost" onclick="closeModal('modalCliente')">Cancelar</button>
        <button class="btn btn-primary" id="btnSaveCliente"><i class="fa-solid fa-save"></i> Guardar</button>
    </div>
</div></div>

