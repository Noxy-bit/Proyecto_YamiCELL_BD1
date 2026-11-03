<?php
/** Directorio: Clientes (con historial de compras) y Proveedores */
$clientes = q($pdo, "SELECT c.*,
                     COUNT(v.id_venta) AS num_compras,
                     COALESCE(SUM(v.total_neto),0) AS total_comprado
                     FROM clientes c LEFT JOIN ventas v ON v.id_cliente = c.id_cliente
                     GROUP BY c.id_cliente ORDER BY total_comprado DESC")->fetchAll();
$proveedores = q($pdo, "SELECT pv.*, COUNT(p.id_producto) AS num_productos
                        FROM proveedores pv LEFT JOIN productos p ON p.id_proveedor = pv.id_proveedor
                        GROUP BY pv.id_proveedor ORDER BY pv.razon_social")->fetchAll();
?>
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <h3><i class="fa-solid fa-users"></i> Clientes y Técnicos Afiliados</h3>
        <div class="table-wrap"><table class="data"><thead><tr>
            <th>CI / NIT</th><th>Nombre</th><th>Tipo</th><th>Teléfono</th>
            <th class="num">Compras</th><th class="num">Total comprado (Bs.)</th>
        </tr></thead><tbody>
        <?php foreach ($clientes as $c):
            $bc = ['TECNICO_AFILIADO'=>'badge-info','MAYORISTA'=>'badge-warn','CLIENTE_FINAL'=>'badge-mut'][$c['tipo_cliente']] ?? 'badge-mut'; ?>
        <tr>
            <td><?= htmlspecialchars($c['ci_nit'] ?? '—') ?></td>
            <td><?= htmlspecialchars($c['nombre_completo']) ?></td>
            <td><span class="badge <?= $bc ?>"><?= $c['tipo_cliente'] ?></span></td>
            <td class="muted"><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
            <td class="num"><?= $c['num_compras'] ?></td>
            <td class="num"><strong><?= bs((float)$c['total_comprado']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <button class="btn btn-primary btn-sm" style="margin-top:12px" onclick="openModal('modalClienteDir')"><i class="fa-solid fa-user-plus"></i> Nuevo cliente</button>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-truck-field"></i> Proveedores / Importadoras</h3>
        <div class="table-wrap"><table class="data"><thead><tr>
            <th>Razón social</th><th>NIT/CI</th><th>Contacto</th><th>Origen</th><th class="num">SKUs en catálogo</th>
        </tr></thead><tbody>
        <?php foreach ($proveedores as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['razon_social']) ?></td>
            <td><?= htmlspecialchars($p['nit_ci'] ?? '—') ?></td>
            <td class="muted"><?= htmlspecialchars(($p['contacto_nombre'] ?? '—') . ' ' . ($p['telefono'] ?? '')) ?></td>
            <td><?= htmlspecialchars($p['ciudad_origen'] ?? '—') ?></td>
            <td class="num"><?= $p['num_productos'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>

<div class="modal-bg" id="modalClienteDir"><div class="modal">
    <h3><i class="fa-solid fa-user-plus"></i> Nuevo cliente</h3>
    <div class="field"><label>CI / NIT</label><input id="dncCI"></div>
    <div class="field"><label>Nombre completo *</label><input id="dncNombre"></div>
    <div class="inline">
        <div class="field"><label>Tipo</label>
            <select id="dncTipo"><option>CLIENTE_FINAL</option><option>TECNICO_AFILIADO</option><option>MAYORISTA</option></select></div>
        <div class="field"><label>Teléfono</label><input id="dncTel"></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn btn-ghost" onclick="closeModal('modalClienteDir')">Cancelar</button>
        <button class="btn btn-primary" onclick="saveCliente('dnc', 'modalClienteDir')"><i class="fa-solid fa-save"></i> Guardar</button>
    </div>
</div></div>

