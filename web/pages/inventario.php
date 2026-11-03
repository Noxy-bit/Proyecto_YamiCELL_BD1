<?php
/** Matriz de Inventario cruzado — vista consolidada P98 vs P121 + CRUD + compras */
$f_cat    = (int)($_GET['cat'] ?? 0);
$f_marca  = trim($_GET['marca'] ?? '');
$f_estado = $_GET['estado'] ?? 'todos';
$f_q      = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM vw_inventario_consolidado_puestos WHERE 1=1";
$pr = [];
if ($f_cat)   { $sql .= " AND id_producto IN (SELECT id_producto FROM productos WHERE id_categoria = :cat)"; $pr[':cat'] = $f_cat; }
if ($f_marca) { $sql .= " AND marca_compatible LIKE :marca"; $pr[':marca'] = "%$f_marca%"; }
if ($f_q)     { $sql .= " AND (sku LIKE :q OR nombre_producto LIKE :q OR modelo_compatible LIKE :q)"; $pr[':q'] = "%$f_q%"; }
if ($f_estado === 'crit98')  $sql .= " AND stock_puesto_98  <= (SELECT stock_minimo_alerta FROM productos pp WHERE pp.id_producto = vw_inventario_consolidado_puestos.id_producto)";
if ($f_estado === 'crit121') $sql .= " AND stock_puesto_121 <= (SELECT stock_minimo_alerta FROM productos pp WHERE pp.id_producto = vw_inventario_consolidado_puestos.id_producto)";
$sql .= " ORDER BY nombre_producto";
$rows = q($pdo, $sql, $pr)->fetchAll();

$cats    = q($pdo, "SELECT * FROM categorias ORDER BY nombre_categoria")->fetchAll();
$marcas  = q($pdo, "SELECT DISTINCT marca_compatible FROM productos WHERE marca_compatible IS NOT NULL ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
$provs   = q($pdo, "SELECT * FROM proveedores ORDER BY razon_social")->fetchAll();
$sucs    = q($pdo, "SELECT * FROM sucursales ORDER BY id_sucursal")->fetchAll();
?>
<div class="card">
    <form class="inline" method="get">
        <input type="hidden" name="page" value="inventario">
        <div class="field"><label>Categoría</label>
            <select name="cat"><option value="0">Todas</option>
                <?php foreach ($cats as $c): ?><option value="<?= $c['id_categoria'] ?>" <?= $f_cat == $c['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre_categoria']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="field"><label>Marca compatible</label>
            <select name="marca"><option value="">Todas</option>
                <?php foreach ($marcas as $m): ?><option <?= $f_marca === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option><?php endforeach; ?>
            </select></div>
        <div class="field"><label>Estado de stock</label>
            <select name="estado">
                <option value="todos"  <?= $f_estado==='todos'?'selected':'' ?>>Todos</option>
                <option value="crit98" <?= $f_estado==='crit98'?'selected':'' ?>>Críticos en Puesto 98</option>
                <option value="crit121"<?= $f_estado==='crit121'?'selected':'' ?>>Críticos en Puesto 121</option>
            </select></div>
        <div class="field" style="flex:2"><label>Buscador instantáneo</label><input name="q" value="<?= htmlspecialchars($f_q) ?>" placeholder="SKU, nombre, modelo…"></div>
        <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filtrar</button>
        <button type="button" class="btn btn-ghost" onclick="openProductoModal()"><i class="fa-solid fa-plus"></i> Nuevo Producto</button>
        <button type="button" class="btn btn-ghost" onclick="openCompraModal()"><i class="fa-solid fa-truck-ramp-box"></i> Ingreso / Compra</button>
        <a class="btn btn-ghost" href="api.php?action=export&tipo=inventario"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
        <button type="button" class="btn btn-ghost" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir</button>
    </form>
</div>

<div class="card">
    <h3><i class="fa-solid fa-table-cells"></i> Matriz multi-puesto (<?= count($rows) ?> SKUs)</h3>
    <div class="table-wrap">
        <table class="data"><thead><tr>
            <th>SKU</th><th>Repuesto / Descripción</th><th>Marca / Modelo</th><th>Categoría</th>
            <th class="num">Costo (Bs.)</th><th class="num">P. Técnico</th><th class="num">P. Público</th>
            <th class="num">Stock P98<br><span class="muted">Tarija</span></th><th class="num">Stock P121<br><span class="muted">Las Américas</span></th>
            <th class="num">Total</th><th>Acciones</th>
        </tr></thead><tbody>
        <?php foreach ($rows as $r):
            $minimo = (int)$r['stock_minimo_alerta'] ?? 0;
            $c98  = $r['stock_puesto_98']  <= $minimo ? 'qty-neg' : 'qty-pos';
            $c121 = $r['stock_puesto_121'] <= $minimo ? 'qty-neg' : 'qty-pos';
        ?>
        <tr>
            <td><span class="chip"><?= htmlspecialchars($r['sku']) ?></span></td>
            <td><?= htmlspecialchars($r['nombre_producto']) ?></td>
            <td class="muted"><?= htmlspecialchars(($r['marca_compatible'] ?? '') . ' ' . ($r['modelo_compatible'] ?? '')) ?></td>
            <td><?= htmlspecialchars($r['nombre_categoria']) ?></td>
            <td class="num"><?= bs((float)$r['costo_compra']) ?></td>
            <td class="num"><?= bs((float)$r['precio_tecnico']) ?></td>
            <td class="num"><?= bs((float)$r['precio_publico']) ?></td>
            <td class="num <?= $c98 ?>"><?= $r['stock_puesto_98'] ?></td>
            <td class="num <?= $c121 ?>"><?= $r['stock_puesto_121'] ?></td>
            <td class="num"><strong><?= $r['stock_total'] ?></strong></td>
            <td><button class="btn btn-ghost btn-sm" onclick='openProductoModal(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'><i class="fa-solid fa-pen"></i></button></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>

<!-- Modal producto -->
<div class="modal-bg" id="modalProducto"><div class="modal">
    <h3><i class="fa-solid fa-box"></i> Producto del catálogo maestro</h3>
    <input type="hidden" id="pId">
    <div class="inline">
        <div class="field"><label>SKU *</label><input id="pSku"></div>
        <div class="field"><label>Categoría *</label>
            <select id="pCat"><?php foreach ($cats as $c): ?><option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field"><label>Nombre / descripción *</label><input id="pNombre"></div>
    <div class="inline">
        <div class="field"><label>Marca compatible</label><input id="pMarca"></div>
        <div class="field"><label>Modelo compatible</label><input id="pModelo"></div>
    </div>
    <div class="field"><label>Proveedor</label>
        <select id="pProv"><option value="">—</option>
            <?php foreach ($provs as $pv): ?><option value="<?= $pv['id_proveedor'] ?>"><?= htmlspecialchars($pv['razon_social']) ?></option><?php endforeach; ?></select></div>
    <div class="inline">
        <div class="field"><label>Costo compra (Bs.)</label><input id="pCosto" type="number" step="0.01" min="0"></div>
        <div class="field"><label>Precio técnico (Bs.)</label><input id="pTec" type="number" step="0.01" min="0"></div>
        <div class="field"><label>Precio público (Bs.)</label><input id="pPub" type="number" step="0.01" min="0"></div>
        <div class="field"><label>Stock mín. alerta</label><input id="pMin" type="number" min="0" value="5"></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn btn-ghost" onclick="closeModal('modalProducto')">Cancelar</button>
        <button class="btn btn-primary" onclick="saveProducto()"><i class="fa-solid fa-save"></i> Guardar</button>
    </div>
</div></div>

<!-- Modal ingreso/compra -->
<div class="modal-bg" id="modalCompra"><div class="modal">
    <h3><i class="fa-solid fa-truck-ramp-box"></i> Ingreso de mercadería / Compra a proveedor</h3>
    <div class="field"><label>Producto</label><select id="cProd">
        <?php foreach ($rows as $r): ?><option value="<?= $r['id_producto'] ?>"><?= htmlspecialchars($r['sku'] . ' — ' . $r['nombre_producto']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="inline">
        <div class="field"><label>Puesto destino</label>
            <select id="cSuc"><?php foreach ($sucs as $s): ?><option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre_sucursal']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Cantidad *</label><input id="cQty" type="number" min="1" value="1"></div>
        <div class="field"><label>Costo unitario (Bs.)</label><input id="cCosto" type="number" step="0.01" min="0"></div>
    </div>
    <p class="muted">Genera asiento <code>ENTRADA_COMPRA</code> en el kardex e incrementa <code>inventario_sucursal</code> en una transacción.</p>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:10px">
        <button class="btn btn-ghost" onclick="closeModal('modalCompra')">Cancelar</button>
        <button class="btn btn-ok" onclick="saveCompra()"><i class="fa-solid fa-check"></i> Registrar ingreso</button>
    </div>
</div></div>

