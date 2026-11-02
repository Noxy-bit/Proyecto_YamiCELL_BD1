<?php
require_once __DIR__ . '/conexion.php';

$mensaje_exito = null;
$mensaje_error = null;
$tab_activa = $_GET['tab'] ?? 'dashboard';

// =========================================================================
// PROCESAMIENTO POS: TRANSACCIÓN ATÓMICA ACID (BEGIN -> COMMIT / ROLLBACK)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    if ($_POST['accion'] === 'registrar_venta') {
        $tab_activa = 'pos';
        $id_puesto   = (int) $_POST['id_puesto'];
        $id_cliente  = (int) $_POST['id_cliente'];
        $id_producto = (int) $_POST['id_producto'];
        $cantidad    = (int) $_POST['cantidad'];
        $metodo_pago = $_POST['metodo_pago'];
        $fecha       = date('Y-m-d');

        try {
            // INICIO DE TRANSACCIÓN ATÓMICA (Propiedades ACID exigidas por el Ing. Peinado)
            $pdo->beginTransaction();

            // 1. Obtener precio unitario actual
            $stmtProd = $pdo->prepare("SELECT nombre_producto, precio, stock FROM PRODUCTO WHERE id_producto = ?");
            $stmtProd->execute([$id_producto]);
            $producto = $stmtProd->fetch();

            if (!$producto) {
                throw new Exception("El producto seleccionado no existe en el catálogo.");
            }

            $subtotal = $producto['precio'] * $cantidad;

            // 2. Generar nuevos IDs secuenciales
            $nextVentaId   = (int) $pdo->query("SELECT COALESCE(MAX(id_venta), 1000) + 1 FROM VENTA")->fetchColumn();
            $nextDetalleId = (int) $pdo->query("SELECT COALESCE(MAX(id_detalle), 0) + 1 FROM DETALLE_VENTA")->fetchColumn();

            // 3. Insertar cabecera en VENTA
            $stmtVenta = $pdo->prepare("
                INSERT INTO VENTA (id_venta, fecha, metodo_pago, total, id_cliente, id_puesto)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtVenta->execute([$nextVentaId, $fecha, $metodo_pago, $subtotal, $id_cliente, $id_puesto]);

            // 4. Insertar renglón en DETALLE_VENTA
            $stmtDetalle = $pdo->prepare("
                INSERT INTO DETALLE_VENTA (id_detalle, cantidad, subtotal, id_venta, id_producto)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtDetalle->execute([$nextDetalleId, $cantidad, $subtotal, $nextVentaId, $id_producto]);

            // 5. Descontar stock en PRODUCTO (Si stock < cantidad, el CHECK (stock >= 0) de SQLite dispara excepción)
            $stmtUpdate = $pdo->prepare("
                UPDATE PRODUCTO SET stock = stock - ? WHERE id_producto = ?
            ");
            $stmtUpdate->execute([$cantidad, $id_producto]);

            // CONFIRMAR CAMBIOS PERMANENTEMENTE
            $pdo->commit();
            $mensaje_exito = "✅ Transacción ACID completada (COMMIT): Recibo #{$nextVentaId} por Bs. " . number_format($subtotal, 2) . " ({$cantidad}x {$producto['nombre_producto']}) registrado y descontado de stock.";

        } catch (Exception $e) {
            // REVERTIR CAMBIOS EN CASO DE FALLO O STOCK NEGATIVO
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje_error = "🛡️ TRANSACCIÓN REVERTIDA (ROLLBACK EJECUTADO): Se abortó el Recibo para proteger la integridad de la base de datos. Causa técnica: Violación de restricción CHECK (stock >= 0) o stock insuficiente. Detalle SQLite: " . $e->getMessage();
        }
    }

    // =========================================================================
    // PROCESAMIENTO TALLER: NUEVA ORDEN DE SERVICIO TÉCNICO
    // =========================================================================
    if ($_POST['accion'] === 'registrar_servicio') {
        $tab_activa = 'taller';
        $id_cliente      = (int) $_POST['id_cliente'];
        $id_puesto       = (int) $_POST['id_puesto'];
        $falla_reportada = trim($_POST['falla_reportada']);
        $costo           = (float) $_POST['costo'];
        $estado          = $_POST['estado'];
        $fecha_ingreso   = date('Y-m-d');

        try {
            $pdo->beginTransaction();
            $nextServId = (int) $pdo->query("SELECT COALESCE(MAX(id_servicio), 500) + 1 FROM SERVICIO_TECNICO")->fetchColumn();
            
            $stmtServ = $pdo->prepare("
                INSERT INTO SERVICIO_TECNICO (id_servicio, fecha_ingreso, falla_reportada, costo, estado, id_cliente, id_puesto)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtServ->execute([$nextServId, $fecha_ingreso, $falla_reportada, $costo, $estado, $id_cliente, $id_puesto]);
            $pdo->commit();
            $mensaje_exito = "🔧 Orden de Servicio Técnico #{$nextServId} registrada correctamente.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje_error = "Error al registrar servicio técnico (ROLLBACK): " . $e->getMessage();
        }
    }
}

// =========================================================================
// CONSULTAS PARA ALIMENTAR LAS VISTAS Y REPORTES DEL SISTEMA
// =========================================================================

// Vistas Corporativas (Unidad 4)
$ingresos_puestos = $pdo->query("SELECT * FROM V_INGRESOS_PUESTOS")->fetchAll();
$stock_critico    = $pdo->query("SELECT * FROM V_STOCK_CRITICO")->fetchAll();
$reparaciones     = $pdo->query("SELECT * FROM V_HISTORIAL_REPARACIONES ORDER BY id_servicio DESC")->fetchAll();

// Tablas Maestras y Especialización
$productos = $pdo->query("SELECT * FROM PRODUCTO ORDER BY id_producto ASC")->fetchAll();
$celulares = $pdo->query("
    SELECT c.imei, p.id_producto, p.nombre_producto, p.precio, p.stock, c.estado_fisico, c.estado_red
    FROM CELULAR c
    INNER JOIN PRODUCTO p ON c.id_producto = p.id_producto
")->fetchAll();
$clientes = $pdo->query("SELECT * FROM CLIENTE ORDER BY id_cliente ASC")->fetchAll();
$puestos  = $pdo->query("SELECT * FROM PUESTO ORDER BY id_puesto ASC")->fetchAll();

// Solucionario Completo del Ing. Peinado
$sol3_estadisticas = $pdo->query("
    SELECT categoria, COUNT(*) AS total_productos, ROUND(AVG(precio), 2) AS precio_promedio,
           MIN(precio) AS precio_minimo, MAX(precio) AS precio_maximo
    FROM PRODUCTO GROUP BY categoria
")->fetchAll();

$sol4_ventas_join = $pdo->query("
    SELECT v.id_venta, v.fecha, c.nombre_completo AS cliente, p.nombre_producto AS producto,
           dv.cantidad, dv.subtotal, v.metodo_pago, pu.nombre_puesto AS puesto
    FROM VENTA v
    INNER JOIN CLIENTE c ON v.id_cliente = c.id_cliente
    INNER JOIN DETALLE_VENTA dv ON v.id_venta = dv.id_venta
    INNER JOIN PRODUCTO p ON dv.id_producto = p.id_producto
    INNER JOIN PUESTO pu ON v.id_puesto = pu.id_puesto
    ORDER BY v.id_venta DESC
")->fetchAll();

$sol6_having = $pdo->query("
    SELECT c.id_cliente, c.nombre_completo AS cliente, COUNT(v.id_venta) AS compras_realizadas,
           SUM(v.total) AS total_gastado
    FROM CLIENTE c
    INNER JOIN VENTA v ON c.id_cliente = v.id_cliente
    GROUP BY c.id_cliente, c.nombre_completo
    HAVING SUM(v.total) > 100.00
    ORDER BY total_gastado DESC
")->fetchAll();

$sol8_sin_ventas = $pdo->query("
    SELECT p.id_producto, p.nombre_producto, p.categoria, p.precio, p.stock
    FROM PRODUCTO p
    LEFT JOIN DETALLE_VENTA dv ON p.id_producto = dv.id_producto
    WHERE dv.id_detalle IS NULL
")->fetchAll();

$sol9_top_categoria = $pdo->query("
    WITH UnidadesPorProducto AS (
        SELECT p.categoria, p.nombre_producto AS producto, SUM(dv.cantidad) AS total_unidades,
               DENSE_RANK() OVER (PARTITION BY p.categoria ORDER BY SUM(dv.cantidad) DESC) AS ranking
        FROM DETALLE_VENTA dv
        INNER JOIN PRODUCTO p ON dv.id_producto = p.id_producto
        GROUP BY p.categoria, p.id_producto, p.nombre_producto
    )
    SELECT categoria, producto, total_unidades FROM UnidadesPorProducto WHERE ranking = 1
")->fetchAll();

$sol10_running_total = $pdo->query("
    SELECT v.id_venta, v.fecha, c.nombre_completo AS cliente, v.total AS monto_venta,
           SUM(v.total) OVER (PARTITION BY v.id_cliente ORDER BY v.fecha ASC, v.id_venta ASC) AS acumulado_cliente
    FROM VENTA v
    INNER JOIN CLIENTE c ON v.id_cliente = c.id_cliente
    ORDER BY c.id_cliente, v.fecha ASC
")->fetchAll();

// Cálculo de totales globales
$total_global_ventas = array_sum(array_column($ingresos_puestos, 'total_ventas'));
$total_global_taller = array_sum(array_column($reparaciones, 'costo'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YamiCELL | ERP Comercial & Servicio Técnico (INF-312)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --yami-navy: #0f172a;
            --yami-slate: #1e293b;
            --yami-accent: #0284c7;
        }
        body {
            background-color: #f1f5f9;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #1e293b;
        }
        .navbar-yami {
            background: linear-gradient(90deg, var(--yami-navy) 0%, var(--yami-slate) 100%);
            border-bottom: 3px solid var(--yami-accent);
        }
        .nav-pills .nav-link {
            color: #475569;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 18px;
            margin-right: 6px;
        }
        .nav-pills .nav-link.active {
            background-color: var(--yami-navy);
            color: #ffffff;
        }
        .kpi-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
            transition: transform 0.15s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
        }
        .card-section {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
            background: #ffffff;
        }
        .table thead th {
            background-color: #f8fafc;
            color: #334155;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 2px solid #e2e8f0;
        }
        pre.sql-box {
            background: #0f172a;
            color: #38bdf8;
            padding: 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 0;
        }
    </style>
</head>
<body>

<!-- BARRA SUPERIOR CORPORATIVA -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-yami py-3 mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="index.php">
            <i class="bi bi-cpu-fill text-info fs-3 me-2"></i>
            <div>
                <span class="fs-5 tracking-tight">YamiCELL ERP</span>
                <span class="d-block text-secondary" style="font-size: 0.73rem; font-weight: 400;">
                    Puesto 98 (Pasillo Tarija) &bull; Puesto 121 (Pasillo Las Américas)
                </span>
            </div>
        </a>
        <div class="d-flex align-items-center text-light small">
            <span class="badge bg-info text-dark me-2"><i class="bi bi-database-check me-1"></i>SQLite3 + 3FN Activo</span>
            <span class="badge bg-secondary"><i class="bi bi-shield-lock me-1"></i>FK & ACID ON</span>
        </div>
    </div>
</nav>

<div class="container pb-5">

    <!-- ALERTAS DE TRANSACCIONES ACID (COMMIT / ROLLBACK) -->
    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-5 border-success" role="alert">
            <strong><?= htmlspecialchars($mensaje_exito) ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($mensaje_error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-5 border-danger" role="alert">
            <strong><?= htmlspecialchars($mensaje_error) ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- NAVEGACIÓN DE PESTAÑAS DEL SISTEMA -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 shadow-sm" id="yamiTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab_activa === 'dashboard' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-dashboard" type="button">
                <i class="bi bi-speedometer2 me-1"></i> 1. Dashboard & Inventario (3FN)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab_activa === 'pos' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-pos" type="button">
                <i class="bi bi-cart-check me-1"></i> 2. Punto de Venta (POS ACID)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab_activa === 'taller' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-taller" type="button">
                <i class="bi bi-tools me-1"></i> 3. Servicio Técnico
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab_activa === 'solucionario' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-solucionario" type="button">
                <i class="bi bi-journal-code me-1"></i> 4. Solucionario SQL (Ing. Peinado)
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- =============================================================== -->
        <!-- PESTAÑA 1: DASHBOARD CORPORATIVO, VISTAS Y ESPECIALIZACIÓN IMEI -->
        <!-- =============================================================== -->
        <div class="tab-pane fade <?= $tab_activa === 'dashboard' ? 'show active' : '' ?>" id="tab-dashboard">
            
            <!-- Tarjetas KPI de Puestos (Vista V_INGRESOS_PUESTOS) -->
            <div class="row g-3 mb-4">
                <?php foreach ($ingresos_puestos as $p): ?>
                <div class="col-md-4">
                    <div class="card kpi-card h-100 border-start border-4 border-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold text-uppercase"><?= htmlspecialchars($p['sector']) ?></span>
                                <span class="badge bg-primary-subtle text-primary">ID Puesto: <?= $p['id_puesto'] ?></span>
                            </div>
                            <h5 class="fw-bold mt-1 mb-2"><?= htmlspecialchars($p['nombre_puesto']) ?></h5>
                            <h3 class="fw-bold text-dark mb-1">Bs. <?= number_format((float)$p['total_ventas'], 2) ?></h3>
                            <small class="text-secondary"><i class="bi bi-receipt me-1"></i><?= $p['cantidad_ventas'] ?> ventas registradas (Vista SQL)</small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="col-md-4">
                    <div class="card kpi-card h-100 border-start border-4 border-success bg-dark text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <span class="text-info small fw-bold text-uppercase">Consolidado Global YamiCELL</span>
                                <i class="bi bi-graph-up-arrow text-info fs-5"></i>
                            </div>
                            <h5 class="fw-bold mt-1 mb-2">Ventas + Servicio Técnico</h5>
                            <h3 class="fw-bold text-warning mb-1">Bs. <?= number_format($total_global_ventas + $total_global_taller, 2) ?></h3>
                            <small class="text-light opacity-75">Vitrina: Bs. <?= number_format($total_global_ventas, 2) ?> | Taller: Bs. <?= number_format($total_global_taller, 2) ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Alerta Roja: Vista V_STOCK_CRITICO -->
                <div class="col-lg-5">
                    <div class="card card-section h-100 border-top border-4 border-danger">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Alerta de Stock Crítico (&lt; 5 u.)</h6>
                            <span class="badge bg-danger">Vista: V_STOCK_CRITICO</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Producto</th>
                                            <th>Categoría</th>
                                            <th class="text-center">Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stock_critico as $sc): ?>
                                        <tr>
                                            <td class="fw-bold">#<?= $sc['id_producto'] ?></td>
                                            <td><?= htmlspecialchars($sc['nombre_producto']) ?></td>
                                            <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($sc['categoria']) ?></span></td>
                                            <td class="text-center"><span class="badge bg-danger"><?= $sc['stock'] ?> u.</span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Especialización 1 a 0..1: Tabla CELULAR con IMEI -->
                <div class="col-lg-7">
                    <div class="card card-section h-100 border-top border-4 border-info">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-phone-fill text-info me-2"></i>Control de Celulares e IMEI (Especialización 1 a 0..1)</h6>
                            <span class="badge bg-info text-dark">Tabla: CELULAR (3FN)</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>IMEI Único (PK)</th>
                                            <th>Equipo Asociado (FK UNIQUE)</th>
                                            <th>Estado Físico</th>
                                            <th>Red</th>
                                            <th>Precio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($celulares as $cel): ?>
                                        <tr>
                                            <td><code class="fw-bold text-dark"><?= htmlspecialchars($cel['imei']) ?></code></td>
                                            <td>#<?= $cel['id_producto'] ?> - <?= htmlspecialchars($cel['nombre_producto']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($cel['estado_fisico']) ?></span></td>
                                            <td><span class="badge bg-success-subtle text-success"><?= htmlspecialchars($cel['estado_red']) ?></span></td>
                                            <td class="fw-bold">Bs. <?= number_format($cel['precio'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-3 bg-light border-top small text-secondary">
                                <i class="bi bi-info-circle me-1"></i><strong>Justificación 3FN:</strong> Los accesorios y cosméticos no tienen IMEI. Separar <code>CELULAR</code> evita valores <code>NULL</code> masivos en la tabla <code>PRODUCTO</code>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Catálogo General de Productos -->
            <div class="card card-section">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Inventario General de Vitrina (Tabla Maestra PRODUCTO)</h6>
                    <span class="badge bg-dark">CHECK (precio &gt;= 0 AND stock &gt;= 0)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID Producto</th>
                                    <th>Descripción Comercial</th>
                                    <th>Categoría</th>
                                    <th>Precio Unitario</th>
                                    <th class="text-center">Stock Actual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $pr): ?>
                                <tr>
                                    <td class="fw-bold">#<?= $pr['id_producto'] ?></td>
                                    <td><?= htmlspecialchars($pr['nombre_producto']) ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($pr['categoria']) ?></span></td>
                                    <td class="fw-semibold">Bs. <?= number_format($pr['precio'], 2) ?></td>
                                    <td class="text-center">
                                        <?php if ($pr['stock'] < 5): ?>
                                            <span class="badge bg-danger"><?= $pr['stock'] ?> u. (Crítico)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= $pr['stock'] ?> u.</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- =============================================================== -->
        <!-- PESTAÑA 2: PUNTO DE VENTA (POS) CON TRANSACCIONES ACID          -->
        <!-- =============================================================== -->
        <div class="tab-pane fade <?= $tab_activa === 'pos' ? 'show active' : '' ?>" id="tab-pos">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card card-section border-top border-4 border-primary">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-cash-coin me-2 text-primary"></i>Registrar Venta (Transacción ACID en Vivo)</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="index.php?tab=pos">
                                <input type="hidden" name="accion" value="registrar_venta">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Sucursal / Puesto de Atención</label>
                                    <select name="id_puesto" class="form-select" required>
                                        <?php foreach ($puestos as $pu): ?>
                                            <option value="<?= $pu['id_puesto'] ?>">
                                                Puesto #<?= $pu['id_puesto'] ?> - <?= htmlspecialchars($pu['nombre_puesto']) ?> (<?= htmlspecialchars($pu['sector']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Cliente (ID=0 para Venta Rápida de Mostrador)</label>
                                    <select name="id_cliente" class="form-select" required>
                                        <?php foreach ($clientes as $cl): ?>
                                            <option value="<?= $cl['id_cliente'] ?>">
                                                [ID: <?= $cl['id_cliente'] ?>] <?= htmlspecialchars($cl['nombre_completo']) ?> (Tel: <?= htmlspecialchars($cl['telefono']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Producto de Vitrina</label>
                                    <select name="id_producto" class="form-select" required>
                                        <?php foreach ($productos as $pr): ?>
                                            <option value="<?= $pr['id_producto'] ?>">
                                                #<?= $pr['id_producto'] ?> - <?= htmlspecialchars($pr['nombre_producto']) ?> | Bs. <?= number_format($pr['precio'], 2) ?> (Disp: <?= $pr['stock'] ?> u.)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small">Cantidad</label>
                                        <input type="number" name="cantidad" class="form-control" value="1" min="1" max="500" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small">Método de Pago</label>
                                        <select name="metodo_pago" class="form-select" required>
                                            <option value="EFECTIVO">EFECTIVO</option>
                                            <option value="QR">QR</option>
                                            <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                                    <i class="bi bi-check2-circle me-1"></i> Ejecutar Transacción (BEGIN &rarr; COMMIT)
                                </button>
                            </form>

                            <hr>
                            <div class="p-3 rounded bg-warning-subtle border border-warning small">
                                <strong><i class="bi bi-lightning-charge-fill me-1"></i>Tip para Demostración al Ing. Peinado:</strong>
                                Si intentas vender <strong>más unidades de las disponibles en stock</strong> (ej. vender 5 unidades del <em>iPhone 11</em> que tiene stock 1), verás cómo SQLite dispara el <code>CHECK (stock &gt;= 0)</code> y PHP ejecuta automáticamente <code>ROLLBACK</code> sin guardar recibos fantasma.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card card-section">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-receipt-cutoff me-2 text-success"></i>Historial de Ventas (INNER JOIN 5 Tablas)</h6>
                            <span class="badge bg-success">Actualización en Tiempo Real</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Recibo</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Producto</th>
                                            <th>Cant.</th>
                                            <th>Subtotal</th>
                                            <th>Pago</th>
                                            <th>Puesto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sol4_ventas_join as $v): ?>
                                        <tr>
                                            <td class="fw-bold">#<?= $v['id_venta'] ?></td>
                                            <td class="small"><?= htmlspecialchars($v['fecha']) ?></td>
                                            <td><?= htmlspecialchars($v['cliente']) ?></td>
                                            <td><?= htmlspecialchars($v['producto']) ?></td>
                                            <td class="text-center"><?= $v['cantidad'] ?></td>
                                            <td class="fw-bold">Bs. <?= number_format($v['subtotal'], 2) ?></td>
                                            <td><span class="badge bg-info-subtle text-info-emphasis"><?= htmlspecialchars($v['metodo_pago']) ?></span></td>
                                            <td class="small"><?= htmlspecialchars($v['puesto']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- PESTAÑA 3: TALLER DE SERVICIO TÉCNICO                           -->
        <!-- =============================================================== -->
        <div class="tab-pane fade <?= $tab_activa === 'taller' ? 'show active' : '' ?>" id="tab-taller">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card card-section border-top border-4 border-warning">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-wrench-adjustable-circle me-2 text-warning"></i>Nueva Orden de Reparación</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="index.php?tab=taller">
                                <input type="hidden" name="accion" value="registrar_servicio">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Cliente Propietario del Equipo</label>
                                    <select name="id_cliente" class="form-select" required>
                                        <?php foreach ($clientes as $cl): ?>
                                            <?php if ($cl['id_cliente'] != 0): ?>
                                                <option value="<?= $cl['id_cliente'] ?>">
                                                    <?= htmlspecialchars($cl['nombre_completo']) ?> (Tel: <?= htmlspecialchars($cl['telefono']) ?>)
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Puesto Receptor</label>
                                    <select name="id_puesto" class="form-select" required>
                                        <?php foreach ($puestos as $pu): ?>
                                            <option value="<?= $pu['id_puesto'] ?>"><?= htmlspecialchars($pu['nombre_puesto']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Diagnóstico / Falla Reportada</label>
                                    <textarea name="falla_reportada" class="form-control" rows="2" placeholder="Ej: Cambio de batería y pin de carga..." required></textarea>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small">Costo Mano de Obra (Bs.)</label>
                                        <input type="number" step="0.50" min="0" name="costo" class="form-control" value="100.00" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small">Estado del Equipo</label>
                                        <select name="estado" class="form-select" required>
                                            <option value="En revisión">En revisión</option>
                                            <option value="Listo">Listo</option>
                                            <option value="Completado">Completado</option>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-warning w-100 fw-bold">
                                    <i class="bi bi-plus-circle me-1"></i> Registrar Orden en Taller
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card card-section">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-clipboard2-pulse me-2 text-warning"></i>Historial de Servicios Técnicos</h6>
                            <span class="badge bg-warning text-dark">Vista: V_HISTORIAL_REPARACIONES</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Ingreso</th>
                                            <th>Cliente</th>
                                            <th>Sucursal</th>
                                            <th>Falla Reportada</th>
                                            <th>Costo</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reparaciones as $rep): ?>
                                        <tr>
                                            <td class="fw-bold">#<?= $rep['id_servicio'] ?></td>
                                            <td class="small"><?= htmlspecialchars($rep['fecha_ingreso']) ?></td>
                                            <td><?= htmlspecialchars($rep['cliente']) ?></td>
                                            <td class="small"><?= htmlspecialchars($rep['puesto']) ?></td>
                                            <td><?= htmlspecialchars($rep['falla_reportada']) ?></td>
                                            <td class="fw-bold">Bs. <?= number_format($rep['costo'], 2) ?></td>
                                            <td>
                                                <?php if ($rep['estado'] === 'Completado'): ?>
                                                    <span class="badge bg-success">Completado</span>
                                                <?php elseif ($rep['estado'] === 'Listo'): ?>
                                                    <span class="badge bg-primary">Listo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">En revisión</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- PESTAÑA 4: SOLUCIONARIO AVANZADO SQL DEL ING. PEINADO           -->
        <!-- =============================================================== -->
        <div class="tab-pane fade <?= $tab_activa === 'solucionario' ? 'show active' : '' ?>" id="tab-solucionario">
            <div class="row g-4">

                <!-- Solución 3: Estadísticas por Categoría -->
                <div class="col-md-6">
                    <div class="card card-section h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Solución 3: Estadísticas por Categoría (GROUP BY)</h6>
                            <span class="badge bg-secondary">COUNT / AVG / MIN / MAX</span>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-bordered mb-3">
                                <thead>
                                    <tr>
                                        <th>Categoría</th>
                                        <th>Total Prod.</th>
                                        <th>Promedio</th>
                                        <th>Mínimo</th>
                                        <th>Máximo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sol3_estadisticas as $s3): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($s3['categoria']) ?></td>
                                        <td><?= $s3['total_productos'] ?></td>
                                        <td>Bs. <?= number_format($s3['precio_promedio'], 2) ?></td>
                                        <td>Bs. <?= number_format($s3['precio_minimo'], 2) ?></td>
                                        <td>Bs. <?= number_format($s3['precio_maximo'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <pre class="sql-box">SELECT categoria, COUNT(*), ROUND(AVG(precio),2), MIN(precio), MAX(precio)
FROM PRODUCTO GROUP BY categoria;</pre>
                        </div>
                    </div>
                </div>

                <!-- Solución 6: Clientes Destacados con HAVING -->
                <div class="col-md-6">
                    <div class="card card-section h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Solución 6: Clientes Destacados &gt; 100 Bs (HAVING)</h6>
                            <span class="badge bg-secondary">GROUP BY + HAVING</span>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-bordered mb-3">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Compras</th>
                                        <th>Total Gastado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sol6_having as $s6): ?>
                                    <tr>
                                        <td>#<?= $s6['id_cliente'] ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($s6['cliente']) ?></td>
                                        <td><?= $s6['compras_realizadas'] ?></td>
                                        <td class="text-success fw-bold">Bs. <?= number_format($s6['total_gastado'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <pre class="sql-box">SELECT c.id_cliente, c.nombre_completo, COUNT(v.id_venta), SUM(v.total)
FROM CLIENTE c INNER JOIN VENTA v ON c.id_cliente = v.id_cliente
GROUP BY c.id_cliente HAVING SUM(v.total) &gt; 100.00;</pre>
                        </div>
                    </div>
                </div>

                <!-- Solución 8: Auditoría de Productos Sin Ventas -->
                <div class="col-md-6">
                    <div class="card card-section h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Solución 8: Productos Sin Ventas (Auditoría)</h6>
                            <span class="badge bg-secondary">LEFT JOIN / NOT EXISTS</span>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-bordered mb-3">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Producto</th>
                                        <th>Categoría</th>
                                        <th>Stock Inmovilizado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sol8_sin_ventas as $s8): ?>
                                    <tr>
                                        <td>#<?= $s8['id_producto'] ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($s8['nombre_producto']) ?></td>
                                        <td><?= htmlspecialchars($s8['categoria']) ?></td>
                                        <td><?= $s8['stock'] ?> u.</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <pre class="sql-box">SELECT p.id_producto, p.nombre_producto, p.stock
FROM PRODUCTO p LEFT JOIN DETALLE_VENTA dv ON p.id_producto = dv.id_producto
WHERE dv.id_detalle IS NULL;</pre>
                        </div>
                    </div>
                </div>

                <!-- Solución 9: Top Producto por Categoría (CTE + DENSE_RANK) -->
                <div class="col-md-6">
                    <div class="card card-section h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Solución 9: Top Más Vendido por Categoría</h6>
                            <span class="badge bg-info text-dark">CTE + DENSE_RANK() OVER</span>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-bordered mb-3">
                                <thead>
                                    <tr>
                                        <th>Categoría</th>
                                        <th>Producto Líder (Rank #1)</th>
                                        <th>Unidades Vendidas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sol9_top_categoria as $s9): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($s9['categoria']) ?></td>
                                        <td><?= htmlspecialchars($s9['producto']) ?></td>
                                        <td class="fw-bold text-primary"><?= $s9['total_unidades'] ?> u.</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <pre class="sql-box">WITH UnidadesPorProducto AS (
  SELECT p.categoria, p.nombre_producto, SUM(dv.cantidad) AS total_unidades,
         DENSE_RANK() OVER (PARTITION BY p.categoria ORDER BY SUM(dv.cantidad) DESC) AS rnk
  FROM DETALLE_VENTA dv JOIN PRODUCTO p ON dv.id_producto = p.id_producto
  GROUP BY p.categoria, p.id_producto
) SELECT * FROM UnidadesPorProducto WHERE rnk = 1;</pre>
                        </div>
                    </div>
                </div>

                <!-- Solución 10: Acumulado Histórico por Cliente (Running Total) -->
                <div class="col-12">
                    <div class="card card-section">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Solución 10: Acumulado Histórico por Cliente (Running Total - Función de Ventana)</h6>
                            <span class="badge bg-info text-dark">SUM(v.total) OVER (PARTITION BY v.id_cliente ORDER BY v.fecha)</span>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-lg-7">
                                    <table class="table table-sm table-striped table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Recibo</th>
                                                <th>Fecha</th>
                                                <th>Cliente</th>
                                                <th>Monto Venta</th>
                                                <th>Acumulado Progresivo (Window)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sol10_running_total as $s10): ?>
                                            <tr>
                                                <td>#<?= $s10['id_venta'] ?></td>
                                                <td><?= htmlspecialchars($s10['fecha']) ?></td>
                                                <td class="fw-semibold"><?= htmlspecialchars($s10['cliente']) ?></td>
                                                <td>Bs. <?= number_format($s10['monto_venta'], 2) ?></td>
                                                <td class="fw-bold text-primary">Bs. <?= number_format($s10['acumulado_cliente'], 2) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-lg-5 mt-3 mt-lg-0">
                                    <pre class="sql-box">SELECT v.id_venta, v.fecha, c.nombre_completo, v.total,
       SUM(v.total) OVER (
           PARTITION BY v.id_cliente
           ORDER BY v.fecha ASC, v.id_venta ASC
       ) AS acumulado_cliente
FROM VENTA v
INNER JOIN CLIENTE c ON v.id_cliente = c.id_cliente;</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>