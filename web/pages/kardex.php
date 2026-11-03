<?php
/** Kardex físico-valorado — auditoría de movimientos por puesto/producto/tipo */
$f_suc  = (int)($_GET['suc'] ?? 0);
$f_prod = (int)($_GET['prod'] ?? 0);
$f_tipo = $_GET['tipo'] ?? '';

$sql = "SELECT k.*, p.sku, p.nombre_producto, s.nombre_sucursal, s.codigo_puesto, u.nombre_completo AS responsable
        FROM kardex_movimientos k
        JOIN productos p  ON p.id_producto = k.id_producto
        JOIN sucursales s ON s.id_sucursal = k.id_sucursal
        LEFT JOIN usuarios u ON u.id_usuario = k.id_usuario
        WHERE 1=1";
$pr = [];
if ($f_suc)  { $sql .= " AND k.id_sucursal = :suc";   $pr[':suc']  = $f_suc; }
if ($f_prod) { $sql .= " AND k.id_producto = :prod";  $pr[':prod'] = $f_prod; }
if ($f_tipo) { $sql .= " AND k.tipo_movimiento = :t"; $pr[':t']    = $f_tipo; }
$sql .= " ORDER BY k.fecha_hora DESC, k.id_movimiento DESC LIMIT 400";
$movs = q($pdo, $sql, $pr)->fetchAll();

$sucs  = q($pdo, "SELECT * FROM sucursales ORDER BY id_sucursal")->fetchAll();
$prods = q($pdo, "SELECT id_producto, sku, nombre_producto FROM productos ORDER BY nombre_producto")->fetchAll();
$tipos = ['ENTRADA_COMPRA','SALIDA_VENTA','TRASPASO_ENVIO','TRASPASO_RECEPCION','AJUSTE_INVENTARIO'];
?>
<div class="card">
    <form class="inline" method="get">
        <input type="hidden" name="page" value="kardex">
        <div class="field"><label>Sucursal</label>
            <select name="suc"><option value="0">Ambos puestos</option>
                <?php foreach ($sucs as $s): ?><option value="<?= $s['id_sucursal'] ?>" <?= $f_suc==$s['id_sucursal']?'selected':'' ?>><?= htmlspecialchars($s['nombre_sucursal']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="field" style="flex:2"><label>Producto</label>
            <select name="prod"><option value="0">Todos</option>
                <?php foreach ($prods as $p): ?><option value="<?= $p['id_producto'] ?>" <?= $f_prod==$p['id_producto']?'selected':'' ?>><?= htmlspecialchars($p['sku'].' — '.$p['nombre_producto']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="field"><label>Tipo de movimiento</label>
            <select name="tipo"><option value="">Todos</option>
                <?php foreach ($tipos as $t): ?><option <?= $f_tipo===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
            </select></div>
        <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filtrar</button>
        <a class="btn btn-ghost" href="api.php?action=export&tipo=kardex"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
    </form>
</div>

<div class="card">
    <h3><i class="fa-solid fa-clipboard-list"></i> Auditoría Kardex (<?= count($movs) ?> movimientos — inmutable por trigger)</h3>
    <div class="table-wrap">
        <table class="data"><thead><tr>
            <th>Fecha / Hora</th><th>Documento</th><th>Sucursal</th><th>Producto</th><th>Tipo</th>
            <th class="num">Cantidad</th><th class="num">Saldo Anterior</th><th class="num">Saldo Resultante</th><th>Responsable</th>
        </tr></thead><tbody>
        <?php foreach ($movs as $m): ?>
        <tr>
            <td class="muted"><?= $m['fecha_hora'] ?></td>
            <td><span class="chip"><?= htmlspecialchars($m['documento_referencia'] ?? '—') ?></span></td>
            <td><?= htmlspecialchars($m['nombre_sucursal']) ?></td>
            <td><?= htmlspecialchars($m['sku'] . ' · ' . $m['nombre_producto']) ?></td>
            <td><span class="badge t-<?= $m['tipo_movimiento'] ?>"><?= $m['tipo_movimiento'] ?></span></td>
            <td class="num <?= $m['cantidad'] >= 0 ? 'qty-pos' : 'qty-neg' ?>"><?= $m['cantidad'] >= 0 ? '+' : '' ?><?= $m['cantidad'] ?></td>
            <td class="num"><?= $m['stock_anterior'] ?></td>
            <td class="num"><strong><?= $m['stock_resultante'] ?></strong></td>
            <td class="muted"><?= htmlspecialchars($m['responsable'] ?? 'Sistema') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
