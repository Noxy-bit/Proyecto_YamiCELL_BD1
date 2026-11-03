<?php
/** ============================================================
 *  DEFENSA INF-312 — SQL Lab: 8 consultas multitabla, consola
 *  SELECT de solo lectura, DER Mermaid, diccionario de datos y
 *  objetos nativos (views, triggers, índices).
 *  ============================================================ */

$consultas = [
    ['n' => 1, 'titulo' => 'Reporte Maestro-Detalle (JOIN de 5 tablas)',
     'obj' => 'Cruzar ventas con su detalle, productos, sucursal y cliente para un reporte completo de cada línea vendida.',
     'sql' => "SELECT\n    v.codigo_recibo,\n    v.fecha_hora,\n    s.nombre_sucursal,\n    c.nombre_completo AS cliente,\n    p.sku,\n    p.nombre_producto,\n    dv.cantidad,\n    dv.precio_unitario,\n    dv.subtotal\nFROM ventas v\nINNER JOIN detalle_venta dv ON dv.id_venta   = v.id_venta\nINNER JOIN productos    p  ON p.id_producto  = dv.id_producto\nINNER JOIN sucursales   s  ON s.id_sucursal  = v.id_sucursal\nINNER JOIN clientes     c  ON c.id_cliente   = v.id_cliente\nORDER BY v.fecha_hora DESC;"],
    ['n' => 2, 'titulo' => 'Rentabilidad por Sucursal y Categoría',
     'obj' => 'Comparar Ingresos Brutos, Costo Total y Ganancia Neta en Bs. del Puesto 98 (Tarija) vs Puesto 121 (Las Américas).',
     'sql' => "SELECT\n    s.nombre_sucursal,\n    cat.nombre_categoria,\n    ROUND(SUM(dv.subtotal), 2)                        AS ingreso_bruto_bs,\n    ROUND(SUM(dv.cantidad * dv.costo_unitario), 2)    AS costo_total_bs,\n    ROUND(SUM(dv.subtotal - dv.cantidad * dv.costo_unitario), 2) AS ganancia_neta_bs\nFROM detalle_venta dv\nINNER JOIN ventas     v   ON v.id_venta      = dv.id_venta\nINNER JOIN sucursales s   ON s.id_sucursal   = v.id_sucursal\nINNER JOIN productos  p   ON p.id_producto   = dv.id_producto\nINNER JOIN categorias cat ON cat.id_categoria = p.id_categoria\nGROUP BY s.nombre_sucursal, cat.nombre_categoria\nORDER BY s.nombre_sucursal, ganancia_neta_bs DESC;"],
    ['n' => 3, 'titulo' => 'Matriz Pivot de Stock (CASE WHEN) — Desbalances P98 vs P121',
     'obj' => 'Comparativa cruzada del stock por SKU en ambos puestos, detectando SKUs agotados en un puesto con sobre-stock en el otro.',
     'sql' => "SELECT\n    p.sku,\n    p.nombre_producto,\n    COALESCE(SUM(CASE WHEN i.id_sucursal = 1 THEN i.stock_actual END), 0) AS stock_p98_tarija,\n    COALESCE(SUM(CASE WHEN i.id_sucursal = 2 THEN i.stock_actual END), 0) AS stock_p121_americas,\n    COALESCE(SUM(i.stock_actual), 0) AS stock_total,\n    CASE\n        WHEN COALESCE(SUM(CASE WHEN i.id_sucursal = 1 THEN i.stock_actual END),0) = 0\n         AND COALESCE(SUM(CASE WHEN i.id_sucursal = 2 THEN i.stock_actual END),0) > 5\n            THEN 'DESBALANCE: agotado en P98'\n        WHEN COALESCE(SUM(CASE WHEN i.id_sucursal = 2 THEN i.stock_actual END),0) = 0\n         AND COALESCE(SUM(CASE WHEN i.id_sucursal = 1 THEN i.stock_actual END),0) > 5\n            THEN 'DESBALANCE: agotado en P121'\n        ELSE 'Equilibrado'\n    END AS diagnostico\nFROM productos p\nLEFT JOIN inventario_sucursal i ON i.id_producto = p.id_producto\nGROUP BY p.id_producto\nORDER BY diagnostico DESC, stock_total DESC;"],
    ['n' => 4, 'titulo' => 'Ranking de Clientes VIP (GROUP BY + HAVING)',
     'obj' => 'Técnicos y mayoristas que superan Bs. 150 en compras acumuladas y más de 1 transacción.',
     'sql' => "SELECT\n    c.ci_nit,\n    c.nombre_completo,\n    c.tipo_cliente,\n    COUNT(DISTINCT v.id_venta)   AS transacciones,\n    ROUND(SUM(v.total_neto), 2)  AS total_acumulado_bs\nFROM clientes c\nINNER JOIN ventas v ON v.id_cliente = c.id_cliente\nGROUP BY c.id_cliente\nHAVING SUM(v.total_neto) > 150 AND COUNT(DISTINCT v.id_venta) > 1\nORDER BY total_acumulado_bs DESC;"],
    ['n' => 5, 'titulo' => 'Capital Inmovilizado — Baja Rotación (LEFT JOIN)',
     'obj' => 'Repuestos con stock positivo que no registran ventas en los últimos 15 días.',
     'sql' => "SELECT\n    p.sku,\n    p.nombre_producto,\n    s.nombre_sucursal,\n    i.stock_actual,\n    ROUND(i.stock_actual * p.costo_compra, 2) AS capital_inmovilizado_bs\nFROM inventario_sucursal i\nINNER JOIN productos  p ON p.id_producto = i.id_producto\nINNER JOIN sucursales s ON s.id_sucursal = i.id_sucursal\nWHERE i.stock_actual > 0\n  AND NOT EXISTS (\n      SELECT 1\n      FROM detalle_venta dv\n      INNER JOIN ventas v ON v.id_venta = dv.id_venta\n      WHERE dv.id_producto = i.id_producto\n        AND v.id_sucursal  = i.id_sucursal\n        AND v.fecha_hora  >= datetime('now','localtime','-15 days')\n  )\nORDER BY capital_inmovilizado_bs DESC;"],
    ['n' => 6, 'titulo' => 'Auditoría de Integridad: Kardex vs Stock Actual',
     'obj' => 'Verificar que el último stock_resultante del kardex coincide exactamente con stock_actual del inventario.',
     'sql' => "SELECT\n    p.sku,\n    s.nombre_sucursal,\n    i.stock_actual,\n    (SELECT k.stock_resultante\n       FROM kardex_movimientos k\n      WHERE k.id_producto = i.id_producto\n        AND k.id_sucursal = i.id_sucursal\n      ORDER BY k.fecha_hora DESC, k.id_movimiento DESC\n      LIMIT 1)                        AS ultimo_kardex,\n    CASE WHEN i.stock_actual =\n         (SELECT k.stock_resultante\n            FROM kardex_movimientos k\n           WHERE k.id_producto = i.id_producto\n             AND k.id_sucursal = i.id_sucursal\n           ORDER BY k.fecha_hora DESC, k.id_movimiento DESC LIMIT 1)\n         THEN 'CONSISTENTE' ELSE '¡ERROR DE INTEGRIDAD!' END AS auditoria\nFROM inventario_sucursal i\nINNER JOIN productos  p ON p.id_producto = i.id_producto\nINNER JOIN sucursales s ON s.id_sucursal = i.id_sucursal\nORDER BY p.sku, s.id_sucursal;"],
    ['n' => 7, 'titulo' => 'Desglose Financiero por Método de Pago y Sucursal',
     'obj' => 'Cobros por EFECTIVO, QR_SIMPLE, TRANSFERENCIA y MIXTO en cada puesto.',
     'sql' => "SELECT\n    s.nombre_sucursal,\n    v.metodo_pago,\n    COUNT(*)                       AS num_cobros,\n    ROUND(SUM(v.total_neto), 2)    AS total_bs,\n    ROUND(AVG(v.total_neto), 2)    AS ticket_promedio_bs\nFROM ventas v\nINNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal\nGROUP BY s.nombre_sucursal, v.metodo_pago\nORDER BY s.nombre_sucursal, total_bs DESC;"],
    ['n' => 8, 'titulo' => 'Trazabilidad de Traspasos (doble JOIN a sucursales)',
     'obj' => 'Historial logístico entre Pasillo Tarija y Pasillo Las Américas con origen/destino, producto y responsable.',
     'sql' => "SELECT\n    t.codigo_traspaso,\n    t.fecha_hora,\n    p.sku,\n    p.nombre_producto,\n    so.nombre_sucursal AS sucursal_origen,\n    sd.nombre_sucursal AS sucursal_destino,\n    t.cantidad,\n    u.nombre_completo  AS responsable,\n    t.estado\nFROM traspasos_sucursal t\nINNER JOIN sucursales so ON so.id_sucursal = t.id_sucursal_origen\nINNER JOIN sucursales sd ON sd.id_sucursal = t.id_sucursal_destino\nINNER JOIN productos  p  ON p.id_producto  = t.id_producto\nLEFT  JOIN usuarios   u  ON u.id_usuario   = t.id_usuario\nORDER BY t.fecha_hora DESC;"],
];

$tablas = ['sucursales','categorias','proveedores','clientes','usuarios','productos',
           'inventario_sucursal','ventas','detalle_venta','traspasos_sucursal','kardex_movimientos'];
$objetos = q($pdo, "SELECT type, name, tbl_name, sql FROM sqlite_master
                    WHERE type IN ('view','trigger','index') AND name NOT LIKE 'sqlite_%'
                    ORDER BY type, name")->fetchAll();
?>
<div class="tabs" id="defTabs">
    <button class="active" data-tab="lab">⚗️ Laboratorio SQL (8 consultas)</button>
    <button data-tab="consola">💻 Consola SELECT (solo lectura)</button>
    <button data-tab="der">🗺️ DER en vivo (Mermaid)</button>
    <button data-tab="dicc">📖 Diccionario de datos & 3FN</button>
    <button data-tab="objs">⚙️ Objetos SQL (Views/Triggers/Índices)</button>
</div>

<!-- ================= TAB 1: SQL LAB ================= -->
<section data-pane="lab">
    <div class="grid grid-2">
    <?php foreach ($consultas as $c): ?>
        <div class="sql-card">
            <strong>Query <?= $c['n'] ?>: <?= htmlspecialchars($c['titulo']) ?></strong>
            <p class="obj"><?= htmlspecialchars($c['obj']) ?></p>
            <pre><?= htmlspecialchars($c['sql']) ?></pre>
            <button class="btn btn-primary btn-sm" onclick="execLab(this, <?= $c['n'] ?>)"><i class="fa-solid fa-play"></i> Ejecutar Consulta en Vivo</button>
            <span class="muted" id="ms-<?= $c['n'] ?>"></span>
            <div class="sql-result" id="res-<?= $c['n'] ?>"></div>
        </div>
    <?php endforeach; ?>
    </div>
</section>

<!-- ================= TAB 2: CONSOLA ================= -->
<section data-pane="consola" hidden>
    <div class="card">
        <h3><i class="fa-solid fa-terminal"></i> Consola SQL de solo lectura para el docente</h3>
        <p class="muted">Solo sentencias <code>SELECT</code>. Cualquier escritura es rechazada por el backend. Ej.: <code>SELECT * FROM vw_inventario_consolidado_puestos;</code></p>
        <textarea id="consoleSql" class="console-area" placeholder="Escribe un SELECT...">SELECT s.nombre_sucursal, COUNT(*) AS num_ventas, ROUND(SUM(v.total_neto),2) AS total_bs
FROM ventas v
JOIN sucursales s ON s.id_sucursal = v.id_sucursal
GROUP BY s.nombre_sucursal;</textarea>
        <div style="margin-top:10px;display:flex;gap:10px">
            <button class="btn btn-primary" onclick="execConsole()"><i class="fa-solid fa-play"></i> Ejecutar (Ctrl+Enter)</button>
            <button class="btn btn-ghost" onclick="document.getElementById('consoleSql').value=''">Limpiar</button>
            <span class="muted" id="consoleMs"></span>
        </div>
        <div id="consoleOut" style="margin-top:14px"></div>
    </div>
</section>

<!-- ================= TAB 3: DER ================= -->
<section data-pane="der" hidden>
    <div class="card">
        <h3><i class="fa-solid fa-diagram-project"></i> Diagrama Entidad-Relación — 11 tablas 3FN <span class="muted">(rueda = zoom, arrastrar = pan)</span></h3>
        <div class="mermaid-wrap" id="derBox"></div>
<script type="text/plain" id="derSrc">
erDiagram
    SUCURSALES ||--o{ USUARIOS : "emplea (1:N)"
    SUCURSALES ||--o{ INVENTARIO_SUCURSAL : "almacena (1:N)"
    SUCURSALES ||--o{ VENTAS : "emite (1:N)"
    SUCURSALES ||--o{ KARDEX_MOVIMIENTOS : "audita (1:N)"
    SUCURSALES ||--o{ TRASPASOS_SUCURSAL : "origen (1:N)"
    SUCURSALES ||--o{ TRASPASOS_SUCURSAL : "destino (1:N)"
    CATEGORIAS ||--o{ PRODUCTOS : "clasifica (1:N)"
    PROVEEDORES ||--o{ PRODUCTOS : "abastece (1:N)"
    CLIENTES ||--o{ VENTAS : "compra (1:N)"
    USUARIOS ||--o{ VENTAS : "registra (1:N)"
    USUARIOS ||--o{ KARDEX_MOVIMIENTOS : "firma (1:N)"
    USUARIOS ||--o{ TRASPASOS_SUCURSAL : "autoriza (1:N)"
    PRODUCTOS ||--o{ INVENTARIO_SUCURSAL : "stock_por_puesto (N:M puente)"
    PRODUCTOS ||--o{ DETALLE_VENTA : "se_vende_en (1:N)"
    PRODUCTOS ||--o{ TRASPASOS_SUCURSAL : "se_traslada (1:N)"
    PRODUCTOS ||--o{ KARDEX_MOVIMIENTOS : "movimiento (1:N)"
    VENTAS ||--o{ DETALLE_VENTA : "contiene (1:N)"

    SUCURSALES {
        int id_sucursal PK
        string codigo_puesto UK
        string nombre_sucursal
        string pasillo_ubicacion
        string encargado
        string estado
    }
    CATEGORIAS {
        int id_categoria PK
        string nombre_categoria UK
        string descripcion
    }
    PROVEEDORES {
        int id_proveedor PK
        string razon_social
        string nit_ci UK
        string ciudad_origen
    }
    CLIENTES {
        int id_cliente PK
        string ci_nit UK
        string nombre_completo
        string tipo_cliente
    }
    USUARIOS {
        int id_usuario PK
        string nombre_usuario UK
        string rol
        int id_sucursal FK
    }
    PRODUCTOS {
        int id_producto PK
        string sku UK
        string nombre_producto
        int id_categoria FK
        int id_proveedor FK
        real costo_compra
        real precio_tecnico
        real precio_publico
    }
    INVENTARIO_SUCURSAL {
        int id_inventario PK
        int id_producto FK
        int id_sucursal FK
        int stock_actual
        int stock_minimo_alerta
    }
    VENTAS {
        int id_venta PK
        string codigo_recibo UK
        string fecha_hora
        int id_sucursal FK
        int id_cliente FK
        int id_usuario FK
        string metodo_pago
        real total_neto
    }
    DETALLE_VENTA {
        int id_detalle PK
        int id_venta FK
        int id_producto FK
        int cantidad
        real precio_unitario
        real subtotal
    }
    TRASPASOS_SUCURSAL {
        int id_traspaso PK
        string codigo_traspaso UK
        int id_producto FK
        int id_sucursal_origen FK
        int id_sucursal_destino FK
        int cantidad
    }
    KARDEX_MOVIMIENTOS {
        int id_movimiento PK
        string fecha_hora
        int id_producto FK
        int id_sucursal FK
        string tipo_movimiento
        int cantidad
        int stock_anterior
        int stock_resultante
    }
</script>
    </div>
</section>

<!-- ================= TAB 4: DICCIONARIO ================= -->
<section data-pane="dicc" hidden>
    <div class="grid grid-3">
        <div class="card"><h3>1FN — Atomicidad</h3><p class="muted">Todos los atributos son atómicos y sin grupos repetitivos: las líneas de venta viven en <code>detalle_venta</code>, separadas de la cabecera <code>ventas</code>.</p></div>
        <div class="card"><h3>2FN — Dependencia total</h3><p class="muted">En <code>inventario_sucursal</code> (puente N:M) cada columna depende del par <code>id_producto + id_sucursal</code>; el stock de un puesto no depende solo del producto.</p></div>
        <div class="card"><h3>3FN — Sin transitivas</h3><p class="muted"><code>categorias</code>, <code>proveedores</code>, <code>clientes</code> y <code>sucursales</code> son entidades independientes: ningún atributo no-clave depende de otro no-clave.</p></div>
    </div>
    <?php foreach ($tablas as $t):
        $cols = q($pdo, "PRAGMA table_info($t)")->fetchAll();
        $fks  = q($pdo, "PRAGMA foreign_key_list($t)")->fetchAll();
        $fkmap = [];
        foreach ($fks as $fk) $fkmap[$fk['from']] = $fk['table'] . '.' . $fk['to'];
    ?>
    <div class="card">
        <h3><i class="fa-solid fa-table"></i> <?= $t ?> <span class="muted">(metadatos reales vía PRAGMA)</span></h3>
        <div class="table-wrap"><table class="data"><thead><tr>
            <th>Columna</th><th>Tipo</th><th>NOT NULL</th><th>Default</th><th>Clave</th>
        </tr></thead><tbody>
        <?php foreach ($cols as $col): ?>
        <tr>
            <td><code><?= $col['name'] ?></code></td>
            <td><?= $col['type'] ?></td>
            <td><?= $col['notnull'] ? '✔' : '—' ?></td>
            <td><?= $col['dflt_value'] ?? '—' ?></td>
            <td>
                <?php if ($col['pk']): ?><span class="badge badge-err">PK</span><?php endif; ?>
                <?php if (isset($fkmap[$col['name']])): ?><span class="badge badge-info">FK → <?= $fkmap[$col['name']] ?></span><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
    <?php endforeach; ?>
</section>

<!-- ================= TAB 5: OBJETOS ================= -->
<section data-pane="objs" hidden>
    <div class="card">
        <h3><i class="fa-solid fa-gears"></i> Objetos nativos activos en el motor (sqlite_master)</h3>
        <?php foreach (['view' => 'Vistas (CREATE VIEW)', 'trigger' => 'Triggers (CREATE TRIGGER)', 'index' => 'Índices (CREATE INDEX)'] as $tp => $tit): ?>
        <h4 style="margin:18px 0 8px;font-size:.9rem"><?= $tit ?></h4>
        <?php foreach ($objetos as $o): if ($o['type'] !== $tp) continue; ?>
            <div class="sql-card" style="margin-bottom:10px">
                <strong><?= htmlspecialchars($o['name']) ?></strong>
                <span class="muted">— tabla: <?= htmlspecialchars($o['tbl_name']) ?></span>
                <?php if ($o['sql']): ?><pre><?= htmlspecialchars($o['sql']) ?></pre><?php endif; ?>
                <?php if ($tp === 'view'): ?>
                <button class="btn btn-primary btn-sm" onclick="execView(this, '<?= $o['name'] ?>')"><i class="fa-solid fa-play"></i> Probar vista</button>
                <div class="sql-result"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; endforeach; ?>
    </div>
</section>

<script>window.LAB_QUERIES = <?= json_encode(array_column($consultas, 'sql', 'n'), JSON_UNESCAPED_UNICODE) ?>;</script>
