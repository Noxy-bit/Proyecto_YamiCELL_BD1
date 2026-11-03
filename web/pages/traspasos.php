<?php
/** Traspasos entre puestos — ACID: baja origen + alta destino + doble asiento kardex */
$sucs  = q($pdo, "SELECT * FROM sucursales ORDER BY id_sucursal")->fetchAll();
$prods = q($pdo, "SELECT p.id_producto, p.sku, p.nombre_producto FROM productos p WHERE p.estado='ACTIVO' ORDER BY p.nombre_producto")->fetchAll();
$hist  = q($pdo, "SELECT t.*, p.sku, p.nombre_producto,
                  so.nombre_sucursal AS origen, sd.nombre_sucursal AS destino,
                  u.nombre_completo AS responsable
                  FROM traspasos_sucursal t
                  JOIN productos p  ON p.id_producto = t.id_producto
                  JOIN sucursales so ON so.id_sucursal = t.id_sucursal_origen
                  JOIN sucursales sd ON sd.id_sucursal = t.id_sucursal_destino
                  LEFT JOIN usuarios u ON u.id_usuario = t.id_usuario
                  ORDER BY t.fecha_hora DESC LIMIT 100")->fetchAll();
?>
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <h3><i class="fa-solid fa-exchange-alt"></i> Nuevo traspaso P98 ⇄ P121</h3>
        <div class="field"><label>Producto</label>
            <select id="tProd"><?php foreach ($prods as $p): ?><option value="<?= $p['id_producto'] ?>"><?= htmlspecialchars($p['sku'].' — '.$p['nombre_producto']) ?></option><?php endforeach; ?></select></div>
        <div class="inline">
            <div class="field"><label>Origen</label>
                <select id="tOrigen"><?php foreach ($sucs as $s): ?><option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre_sucursal']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Destino</label>
                <select id="tDestino"><?php foreach (array_reverse($sucs) as $s): ?><option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre_sucursal']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Cantidad</label><input id="tQty" type="number" min="1" value="1"></div>
        </div>
        <div class="field"><label>Observación</label><input id="tObs" placeholder="Ej. Reposición de vitrina"></div>
        <button class="btn btn-primary" id="btnTraspaso"><i class="fa-solid fa-truck"></i> Ejecutar Traspaso (ACID)</button>
        <p class="muted" style="margin-top:10px">En una sola transacción: descuenta en origen (<code>TRASPASO_ENVIO</code>), incrementa en destino (<code>TRASPASO_RECEPCION</code>) y registra el documento <code>TR-####</code>.</p>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-clock-rotate-left"></i> Historial de traspasos (doble JOIN a sucursales)</h3>
        <div class="table-wrap">
            <table class="data"><thead><tr>
                <th>Código</th><th>Fecha</th><th>Producto</th><th>Origen → Destino</th>
                <th class="num">Cantidad</th><th>Estado</th><th>Responsable</th>
            </tr></thead><tbody>
            <?php foreach ($hist as $t): ?>
            <tr>
                <td><span class="chip"><?= htmlspecialchars($t['codigo_traspaso']) ?></span></td>
                <td class="muted"><?= $t['fecha_hora'] ?></td>
                <td><?= htmlspecialchars($t['sku'].' · '.$t['nombre_producto']) ?></td>
                <td><?= htmlspecialchars($t['origen']) ?> <i class="fa-solid fa-arrow-right muted"></i> <?= htmlspecialchars($t['destino']) ?></td>
                <td class="num"><strong><?= $t['cantidad'] ?></strong></td>
                <td><span class="badge badge-ok"><?= $t['estado'] ?></span></td>
                <td class="muted"><?= htmlspecialchars($t['responsable'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
</div>

