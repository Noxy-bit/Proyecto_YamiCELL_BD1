<?php
/**
 * YAMICELL — API JSON / acciones POST (PDO + transacciones ACID)
 * Toda mutación de datos pasa por aquí con sentencias preparadas.
 */
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function out($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $msg, int $code = 400) { out(['ok' => false, 'error' => $msg], $code); }

try {
    switch ($action) {

    // ---------- BÚSQUEDA DE PRODUCTOS EN TIEMPO REAL (POS / Inventario) ----------
    case 'search_productos': {
        $term = trim($_GET['q'] ?? '');
        $suc  = (int)($_GET['sucursal'] ?? 1);
        $stmt = q($pdo, "SELECT p.id_producto, p.sku, p.nombre_producto, p.marca_compatible,
                    p.modelo_compatible, c.nombre_categoria,
                    p.precio_publico, p.precio_tecnico, p.costo_compra,
                    COALESCE(i.stock_actual, 0) AS stock_actual
                 FROM productos p
                 JOIN categorias c ON c.id_categoria = p.id_categoria
                 LEFT JOIN inventario_sucursal i ON i.id_producto = p.id_producto AND i.id_sucursal = :suc
                 WHERE p.estado = 'ACTIVO' AND (
                    p.sku LIKE :t OR p.nombre_producto LIKE :t OR
                    p.marca_compatible LIKE :t OR p.modelo_compatible LIKE :t)
                 ORDER BY p.nombre_producto LIMIT 25",
                 [':suc' => $suc, ':t' => "%$term%"]);
        out(['ok' => true, 'items' => $stmt->fetchAll()]);
    }

    // ---------- DATOS DEL DASHBOARD BI ----------
    case 'dashboard': {
        $suc = (int)($_GET['sucursal'] ?? 0);   // 0 = consolidado
        $cond = $suc > 0 ? 'WHERE v.id_sucursal = :suc' : '';
        $pr   = $suc > 0 ? [':suc' => $suc] : [];

        $kpis = q($pdo, "SELECT COUNT(*) AS num_ventas,
                    COALESCE(SUM(v.total_neto),0) AS ingresos,
                    COALESCE(AVG(v.total_neto),0) AS ticket_promedio,
                    COALESCE(SUM(d.cantidad * d.costo_unitario),0) AS costo_total,
                    COALESCE(SUM(d.cantidad * (d.precio_unitario - d.costo_unitario)),0) AS ganancia
                 FROM ventas v LEFT JOIN detalle_venta d ON d.id_venta = v.id_venta $cond", $pr)->fetch();

        $hoy = q($pdo, "SELECT COALESCE(SUM(total_neto),0) FROM ventas v
                 WHERE date(v.fecha_hora) = date('now','localtime')" . ($suc ? ' AND v.id_sucursal = :suc' : ''), $pr)->fetchColumn();

        $alertas = q($pdo, "SELECT COUNT(*) FROM vw_alertas_reposicion" . ($suc ? ' WHERE codigo_puesto = :cs' : ''),
                     $suc ? [':cs' => ($suc == 1 ? 'PUESTO-98' : 'PUESTO-121')] : [])->fetchColumn();

        $por_dia = q($pdo, "SELECT date(v.fecha_hora) AS dia, COUNT(*) AS ventas, ROUND(SUM(v.total_neto),2) AS total
                 FROM ventas v $cond GROUP BY dia ORDER BY dia", $pr)->fetchAll();

        $por_cat = q($pdo, "SELECT c.nombre_categoria AS cat, ROUND(SUM(d.subtotal),2) AS total
                 FROM detalle_venta d JOIN ventas v ON v.id_venta = d.id_venta
                 JOIN productos p ON p.id_producto = d.id_producto
                 JOIN categorias c ON c.id_categoria = p.id_categoria
                 $cond GROUP BY c.nombre_categoria ORDER BY total DESC", $pr)->fetchAll();

        $por_pago = q($pdo, "SELECT v.metodo_pago, COUNT(*) AS n, ROUND(SUM(v.total_neto),2) AS total
                 FROM ventas v $cond GROUP BY v.metodo_pago ORDER BY total DESC", $pr)->fetchAll();

        $por_suc = q($pdo, "SELECT s.nombre_sucursal, COUNT(v.id_venta) AS ventas, ROUND(COALESCE(SUM(v.total_neto),0),2) AS total
                 FROM sucursales s LEFT JOIN ventas v ON v.id_sucursal = s.id_sucursal
                 GROUP BY s.id_sucursal ORDER BY s.id_sucursal")->fetchAll();

        $top = q($pdo, "SELECT p.nombre_producto, p.sku, SUM(d.cantidad) AS unidades, ROUND(SUM(d.subtotal),2) AS total
                 FROM detalle_venta d JOIN ventas v ON v.id_venta = d.id_venta
                 JOIN productos p ON p.id_producto = d.id_producto
                 $cond GROUP BY p.id_producto ORDER BY unidades DESC LIMIT 6", $pr)->fetchAll();

        $criticos = q($pdo, "SELECT sku, nombre_producto, nombre_sucursal, stock_actual, stock_minimo_alerta
                 FROM vw_alertas_reposicion" . ($suc ? ' WHERE codigo_puesto = :cs' : '') .
                     ' ORDER BY stock_actual ASC LIMIT 8',
                     $suc ? [':cs' => ($suc == 1 ? 'PUESTO-98' : 'PUESTO-121')] : [])->fetchAll();

        $ultimas = q($pdo, "SELECT v.codigo_recibo, v.fecha_hora, s.nombre_sucursal, c.nombre_completo AS cliente,
                    v.metodo_pago, v.total_neto
                 FROM ventas v JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                 JOIN clientes c ON c.id_cliente = v.id_cliente
                 $cond ORDER BY v.fecha_hora DESC LIMIT 8", $pr)->fetchAll();

        out(['ok' => true, 'kpis' => $kpis, 'ventas_hoy' => $hoy, 'alertas' => $alertas,
             'por_dia' => $por_dia, 'por_categoria' => $por_cat, 'por_pago' => $por_pago,
             'por_sucursal' => $por_suc, 'top_productos' => $top,
             'stock_critico' => $criticos, 'ultimas_ventas' => $ultimas]);
    }

    // ---------- VENTA POS: TRANSACCIÓN ACID MAESTRO-DETALLE ----------
    case 'venta': {
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id_sucursal   = (int)($body['id_sucursal'] ?? 0);
        $id_cliente    = (int)($body['id_cliente'] ?? 0);
        $metodo_pago   = $body['metodo_pago'] ?? 'EFECTIVO';
        $tipo_precio   = $body['tipo_precio'] ?? 'PUBLICO';
        $descuento     = (float)($body['descuento_bs'] ?? 0);
        $items         = $body['items'] ?? [];
        $id_usuario    = (int)($body['id_usuario'] ?? 2);

        if (!$id_sucursal || !$id_cliente || empty($items)) fail('Datos de venta incompletos.');
        if (!in_array($metodo_pago, ['EFECTIVO','QR_SIMPLE','TRANSFERENCIA','MIXTO'])) fail('Método de pago inválido.');
        if (!in_array($tipo_precio, ['PUBLICO','TECNICO'])) fail('Tipo de precio inválido.');
        if ($descuento < 0) fail('Descuento inválido.');

        try {
            $pdo->beginTransaction();   // === BEGIN TRANSACTION (ACID) ===

            $suc = q($pdo, "SELECT codigo_puesto, nombre_sucursal FROM sucursales WHERE id_sucursal = ?",
                     [$id_sucursal])->fetch();
            if (!$suc) throw new Exception('Sucursal inexistente.');

            // Paso 1 — Validación de consistencia de stock por ítem
            $lineas = [];
            $subtotal = 0.0;
            foreach ($items as $it) {
                $pid = (int)$it['id_producto'];
                $qty = (int)$it['cantidad'];
                if ($qty <= 0) throw new Exception("Cantidad inválida para producto #$pid.");
                $row = q($pdo, "SELECT p.sku, p.nombre_producto, p.costo_compra, p.precio_publico, p.precio_tecnico,
                                i.stock_actual
                         FROM productos p
                         JOIN inventario_sucursal i ON i.id_producto = p.id_producto AND i.id_sucursal = ?
                         WHERE p.id_producto = ? AND p.estado = 'ACTIVO'", [$id_sucursal, $pid])->fetch();
                if (!$row) throw new Exception("Producto #$pid sin registro de inventario en {$suc['nombre_sucursal']}.");
                if ($row['stock_actual'] < $qty) {
                    throw new Exception("Transacción abortada (ROLLBACK ACID): Stock insuficiente para el producto {$row['sku']} en {$suc['nombre_sucursal']} (disponible: {$row['stock_actual']}, solicitado: $qty).");
                }
                $precio = $tipo_precio === 'TECNICO' ? (float)$row['precio_tecnico'] : (float)$row['precio_publico'];
                $lineas[] = ['id' => $pid, 'qty' => $qty, 'precio' => $precio,
                             'costo' => (float)$row['costo_compra'], 'stock' => (int)$row['stock_actual']];
                $subtotal += $qty * $precio;
            }
            $subtotal = round($subtotal, 2);
            $total    = round(max($subtotal - $descuento, 0), 2);

            // Paso 2 — Cabecera maestro con código de recibo único por sucursal
            $pref = $suc['codigo_puesto'] === 'PUESTO-98' ? 'YC-P98' : 'YC-P121';
            $n = (int)q($pdo, "SELECT COUNT(*) FROM ventas WHERE id_sucursal = ?", [$id_sucursal])->fetchColumn() + 1;
            $codigo = sprintf('%s-%05d', $pref, $n);

            q($pdo, "INSERT INTO ventas (codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario,
                      metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto)
                     VALUES (?, datetime('now','localtime'), ?, ?, ?, ?, ?, ?, ?, ?)",
              [$codigo, $id_sucursal, $id_cliente, $id_usuario, $metodo_pago, $tipo_precio, $descuento, $subtotal, $total]);
            $id_venta = (int)$pdo->lastInsertId();

            // Pasos 3 y 4 — Detalle + descuento de inventario local + kardex
            $stDet = $pdo->prepare("INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal)
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stInv = $pdo->prepare("UPDATE inventario_sucursal SET stock_actual = stock_actual - ?
                                    WHERE id_producto = ? AND id_sucursal = ?");
            $stKar = $pdo->prepare("INSERT INTO kardex_movimientos
                                    (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia,
                                     cantidad, stock_anterior, stock_resultante, id_usuario, detalle)
                                    VALUES (datetime('now','localtime'), ?, ?, 'SALIDA_VENTA', ?, ?, ?, ?, ?, 'Venta POS')");
            foreach ($lineas as $l) {
                $stDet->execute([$id_venta, $l['id'], $l['qty'], $l['precio'], $l['costo'], round($l['qty'] * $l['precio'], 2)]);
                $stInv->execute([$l['qty'], $l['id'], $id_sucursal]);   // trigger valida stock >= 0
                $stKar->execute([$l['id'], $id_sucursal, $codigo, -$l['qty'], $l['stock'], $l['stock'] - $l['qty'], $id_usuario]);
            }

            $pdo->commit();             // === COMMIT ===
            out(['ok' => true, 'codigo_recibo' => $codigo, 'id_venta' => $id_venta,
                 'subtotal' => $subtotal, 'descuento' => $descuento, 'total' => $total,
                 'mensaje' => "Transacción ACID completada (COMMIT): Recibo $codigo por " . bs($total)]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            fail($e->getMessage(), 422);
        }
    }

    // ---------- NUEVO CLIENTE RÁPIDO (modal POS) ----------
    case 'cliente_nuevo': {
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $nombre = trim($b['nombre_completo'] ?? '');
        $ci     = trim($b['ci_nit'] ?? '');
        $tipo   = in_array($b['tipo_cliente'] ?? '', ['TECNICO_AFILIADO','MAYORISTA','CLIENTE_FINAL']) ? $b['tipo_cliente'] : 'CLIENTE_FINAL';
        $tel    = trim($b['telefono'] ?? '');
        if ($nombre === '') fail('Nombre requerido.');
        $ci = $ci !== '' ? $ci : null;
        try {
            q($pdo, "INSERT INTO clientes (ci_nit, nombre_completo, tipo_cliente, telefono) VALUES (?,?,?,?)",
              [$ci, $nombre, $tipo, $tel]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) fail('Ya existe un cliente con ese CI/NIT.');
            throw $e;
        }
        out(['ok' => true, 'id_cliente' => (int)$pdo->lastInsertId()]);
    }

    // ---------- CRUD PRODUCTO (crear / editar catálogo maestro) ----------
    case 'producto_save': {
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id  = (int)($b['id_producto'] ?? 0);
        $f = [
            trim($b['sku'] ?? ''), trim($b['nombre_producto'] ?? ''), trim($b['marca_compatible'] ?? ''),
            trim($b['modelo_compatible'] ?? ''), (int)$b['id_categoria'],
            ($b['id_proveedor'] ?? null) !== '' ? (int)$b['id_proveedor'] : null,
            (float)$b['costo_compra'], (float)$b['precio_tecnico'], (float)$b['precio_publico'],
            (int)($b['stock_minimo_alerta'] ?? 5),
        ];
        if ($f[0] === '' || $f[1] === '' || !$f[4]) fail('SKU, nombre y categoría son obligatorios.');
        if ($id) {
            q($pdo, "UPDATE productos SET sku=?, nombre_producto=?, marca_compatible=?, modelo_compatible=?,
                     id_categoria=?, id_proveedor=?, costo_compra=?, precio_tecnico=?, precio_publico=?,
                     stock_minimo_alerta=? WHERE id_producto=?", array_merge($f, [$id]));
        } else {
            q($pdo, "INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible,
                     id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta)
                     VALUES (?,?,?,?,?,?,?,?,?,?)", $f);
            $nid = (int)$pdo->lastInsertId();
            q($pdo, "INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta)
                     SELECT ?, s.id_sucursal, 0, ? FROM sucursales s", [$nid, (int)($b['stock_minimo_alerta'] ?? 5)]);
            $id = $nid;
        }
        out(['ok' => true, 'id_producto' => $id]);
    }

    // ---------- INGRESO DE MERCADERÍA / COMPRA A PROVEEDOR (ENTRADA_COMPRA) ----------
    case 'compra': {
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $pid = (int)$b['id_producto']; $suc = (int)$b['id_sucursal'];
        $qty = (int)$b['cantidad'];    $costo = (float)($b['costo_unitario'] ?? 0);
        $usu = (int)($b['id_usuario'] ?? 4);
        if ($pid <= 0 || $suc <= 0 || $qty <= 0) fail('Producto, sucursal y cantidad válidos requeridos.');
        try {
            $pdo->beginTransaction();
            $inv = q($pdo, "SELECT stock_actual FROM inventario_sucursal WHERE id_producto=? AND id_sucursal=?",
                     [$pid, $suc])->fetch();
            if (!$inv) throw new Exception('El producto no tiene inventario en esa sucursal.');
            $nuevo = $inv['stock_actual'] + $qty;
            q($pdo, "UPDATE inventario_sucursal SET stock_actual=? WHERE id_producto=? AND id_sucursal=?",
              [$nuevo, $pid, $suc]);
            if ($costo > 0) q($pdo, "UPDATE productos SET costo_compra=? WHERE id_producto=?", [$costo, $pid]);
            $doc = 'COM-' . str_pad((string)((int)q($pdo, "SELECT COUNT(*) FROM kardex_movimientos WHERE tipo_movimiento='ENTRADA_COMPRA'")->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
            q($pdo, "INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento,
                      documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle)
                     VALUES (datetime('now','localtime'), ?, ?, 'ENTRADA_COMPRA', ?, ?, ?, ?, ?, ?)",
              [$pid, $suc, $doc, $qty, $inv['stock_actual'], $nuevo, $usu, 'Compra a proveedor']);
            $pdo->commit();
            out(['ok' => true, 'documento' => $doc, 'stock_resultante' => $nuevo]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            fail($e->getMessage(), 422);
        }
    }

    // ---------- TRASPASO ENTRE PUESTOS (ACID doble asiento kardex) ----------
    case 'traspaso': {
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $pid = (int)$b['id_producto']; $o = (int)$b['id_sucursal_origen']; $d = (int)$b['id_sucursal_destino'];
        $qty = (int)$b['cantidad'];    $obs = trim($b['observacion'] ?? '');
        $usu = (int)($b['id_usuario'] ?? 4);
        if ($pid <= 0 || $o <= 0 || $d <= 0 || $o === $d || $qty <= 0) fail('Datos de traspaso inválidos.');
        try {
            $pdo->beginTransaction();
            $invO = q($pdo, "SELECT stock_actual FROM inventario_sucursal WHERE id_producto=? AND id_sucursal=?", [$pid, $o])->fetch();
            $invD = q($pdo, "SELECT stock_actual FROM inventario_sucursal WHERE id_producto=? AND id_sucursal=?", [$pid, $d])->fetch();
            if (!$invO || !$invD) throw new Exception('Inventario inexistente para el producto.');
            if ($invO['stock_actual'] < $qty)
                throw new Exception("Transacción abortada (ROLLBACK ACID): Stock insuficiente en origen ({$invO['stock_actual']} disponibles).");
            $n = (int)q($pdo, "SELECT COUNT(*) FROM traspasos_sucursal")->fetchColumn() + 1;
            $cod = sprintf('TR-%04d', $n);
            q($pdo, "INSERT INTO traspasos_sucursal (codigo_traspaso, fecha_hora, id_producto, id_sucursal_origen,
                      id_sucursal_destino, cantidad, id_usuario, observacion)
                     VALUES (?, datetime('now','localtime'), ?, ?, ?, ?, ?, ?)", [$cod, $pid, $o, $d, $qty, $usu, $obs]);
            q($pdo, "UPDATE inventario_sucursal SET stock_actual = stock_actual - ? WHERE id_producto=? AND id_sucursal=?", [$qty, $pid, $o]);
            q($pdo, "UPDATE inventario_sucursal SET stock_actual = stock_actual + ? WHERE id_producto=? AND id_sucursal=?", [$qty, $pid, $d]);
            q($pdo, "INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento,
                      documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle)
                     VALUES (datetime('now','localtime'), ?, ?, 'TRASPASO_ENVIO', ?, ?, ?, ?, ?, ?)",
              [$pid, $o, $cod, -$qty, $invO['stock_actual'], $invO['stock_actual'] - $qty, $usu, 'Salida hacia ' . $d]);
            q($pdo, "INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento,
                      documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle)
                     VALUES (datetime('now','localtime'), ?, ?, 'TRASPASO_RECEPCION', ?, ?, ?, ?, ?, ?)",
              [$pid, $d, $cod, $qty, $invD['stock_actual'], $invD['stock_actual'] + $qty, $usu, 'Recepción desde ' . $o]);
            $pdo->commit();
            out(['ok' => true, 'codigo_traspaso' => $cod]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            fail($e->getMessage(), 422);
        }
    }

    // ---------- AJUSTE DE INVENTARIO (con asiento kardex) ----------
    case 'ajuste': {
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $pid = (int)$b['id_producto']; $suc = (int)$b['id_sucursal'];
        $nuevo = (int)$b['nuevo_stock']; $motivo = trim($b['motivo'] ?? 'Ajuste manual');
        $usu = (int)($b['id_usuario'] ?? 4);
        if ($pid <= 0 || $suc <= 0 || $nuevo < 0) fail('Datos de ajuste inválidos.');
        try {
            $pdo->beginTransaction();
            $inv = q($pdo, "SELECT stock_actual FROM inventario_sucursal WHERE id_producto=? AND id_sucursal=?", [$pid, $suc])->fetch();
            if (!$inv) throw new Exception('Inventario inexistente.');
            $delta = $nuevo - $inv['stock_actual'];
            q($pdo, "UPDATE inventario_sucursal SET stock_actual=? WHERE id_producto=? AND id_sucursal=?", [$nuevo, $pid, $suc]);
            q($pdo, "INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento,
                      documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle)
                     VALUES (datetime('now','localtime'), ?, ?, 'AJUSTE_INVENTARIO', ?, ?, ?, ?, ?, ?)",
              [$pid, $suc, 'AJ-' . date('YmdHis'), $delta, $inv['stock_actual'], $nuevo, $usu, $motivo]);
            $pdo->commit();
            out(['ok' => true, 'delta' => $delta, 'stock_resultante' => $nuevo]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            fail($e->getMessage(), 422);
        }
    }

    // ---------- CONSOLA SQL SOLO LECTURA (Modo Defensa) ----------
    case 'exec_sql': {
        $b = json_decode(file_get_contents('php://input'), true) ?? [];
        $sql = trim($b['sql'] ?? $_POST['sql'] ?? $_GET['sql'] ?? '');
        if ($sql === '') fail('Consulta vacía.');
        if (!preg_match('/^\s*SELECT\b/i', $sql) || preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|ATTACH|DETACH|PRAGMA\s+(?!table_info|index_list|index_info|foreign_key_list)|REPLACE|VACUUM|REINDEX)\b/i', $sql))
            fail('Solo se permiten sentencias SELECT de lectura.');
        $t0 = microtime(true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $ms = round((microtime(true) - $t0) * 1000, 2);
        $cols = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) $cols[] = $stmt->getColumnMeta($i)['name'] ?? "col$i";
        out(['ok' => true, 'columns' => $cols, 'rows' => array_slice($rows, 0, 500),
             'count' => count($rows), 'ms' => $ms]);
    }

    // ---------- EXPORTACIÓN CSV ----------
    case 'export': {
        $tipo = $_GET['tipo'] ?? '';
        $map = [
            'inventario' => ["SELECT sku, nombre_producto, marca_compatible, modelo_compatible, nombre_categoria,
                             costo_compra, precio_tecnico, precio_publico,
                             stock_puesto_98, stock_puesto_121, stock_total, valor_total_costo_bs
                             FROM vw_inventario_consolidado_puestos ORDER BY nombre_producto", 'inventario.csv'],
            'kardex'     => ["SELECT k.fecha_hora, k.documento_referencia, s.nombre_sucursal, p.sku,
                             p.nombre_producto, k.tipo_movimiento, k.cantidad, k.stock_anterior,
                             k.stock_resultante, u.nombre_completo AS responsable
                             FROM kardex_movimientos k
                             JOIN productos p ON p.id_producto = k.id_producto
                             JOIN sucursales s ON s.id_sucursal = k.id_sucursal
                             LEFT JOIN usuarios u ON u.id_usuario = k.id_usuario
                             ORDER BY k.fecha_hora DESC", 'kardex.csv'],
            'ventas'     => ["SELECT v.codigo_recibo, v.fecha_hora, s.nombre_sucursal, c.nombre_completo AS cliente,
                             v.metodo_pago, v.tipo_precio, v.descuento_bs, v.subtotal_bruto, v.total_neto
                             FROM ventas v JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                             JOIN clientes c ON c.id_cliente = v.id_cliente ORDER BY v.fecha_hora DESC", 'ventas.csv'],
        ];
        if (!isset($map[$tipo])) fail('Tipo de exportación inválido.');
        [$sqlE, $fname] = $map[$tipo];
        $stmtE = $pdo->prepare($sqlE); $stmtE->execute();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        $fh = fopen('php://output', 'w');
        fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));   // BOM UTF-8 para Excel
        $colsE = [];
        for ($i = 0; $i < $stmtE->columnCount(); $i++) $colsE[] = $stmtE->getColumnMeta($i)['name'] ?? "c$i";
        fputcsv($fh, $colsE);
        while ($r = $stmtE->fetch(PDO::FETCH_NUM)) fputcsv($fh, $r);
        exit;
    }

    // ---------- RESET DE DATOS DEMO ----------
    case 'reset': {
        $pdo->exec(file_get_contents($sql_script));
        out(['ok' => true, 'mensaje' => 'Base de datos regenerada con datos de demostración.']);
    }

    default: fail('Acción no reconocida.', 404);
    }
} catch (Throwable $e) {
    fail('Error interno: ' . $e->getMessage(), 500);
}
