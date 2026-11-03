<?php
/**
 * YAMICELL — Panel ERP Web (INF-312 Base de Datos I)
 * Router principal: ?page=dashboard|pos|inventario|kardex|traspasos|directorio|defensa
 */
require_once __DIR__ . '/conexion.php';

$page = $_GET['page'] ?? 'dashboard';
$pages = [
    'dashboard'  => ['Dashboard BI',        'chart-line',   'pages/dashboard.php'],
    'pos'        => ['Punto de Venta (POS)','cash-register','pages/pos.php'],
    'inventario' => ['Matriz de Inventario','boxes',        'pages/inventario.php'],
    'kardex'     => ['Kardex Valorado',     'clipboard-list','pages/kardex.php'],
    'traspasos'  => ['Traspasos',           'exchange-alt', 'pages/traspasos.php'],
    'directorio' => ['Clientes & Proveedores','address-book','pages/directorio.php'],
    'defensa'    => ['Defensa INF-312 (SQL Lab)','flask',   'pages/defensa.php'],
];
if (!isset($pages[$page])) $page = 'dashboard';

$msg = $_GET['msg'] ?? ''; $err = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>YamiCELL — <?= $pages[$page][0] ?></title>
<link rel="stylesheet" href="assets/vendor/fa/all.min.css">
<link rel="stylesheet" href="assets/app.css">
<script src="assets/vendor/chart.umd.min.js"></script>
<script src="assets/vendor/mermaid.min.js"></script>
</head>
<body>
<aside class="sidebar">
    <div class="brand"><i class="fa-solid fa-mobile-screen-button"></i><div><strong>YamiCELL</strong><span>ERP · INF-312</span></div></div>
    <nav>
        <?php foreach ($pages as $key => [$label, $icon]): ?>
            <a class="<?= $page === $key ? 'active' : '' ?>" href="index.php?page=<?= $key ?>">
                <i class="fa-solid fa-<?= $icon ?>"></i><span><?= $label ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
        <div><i class="fa-solid fa-database"></i> SQLite 3 · PDO</div>
        <div><i class="fa-solid fa-location-dot"></i> Santa Cruz, Bolivia</div>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <h1><?= $pages[$page][0] ?></h1>
        <div class="topbar-right">
            <span class="badge badge-ok"><i class="fa-solid fa-circle-check"></i> SQLite · foreign_keys ON</span>
            <button id="btnReset" class="btn btn-warn" title="Regenera el stock y ventas históricas de demostración">
                <i class="fa-solid fa-rotate-left"></i> Reiniciar Datos Demo
            </button>
        </div>
    </header>

    <?php if ($msg): ?><div class="alert alert-ok"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-err"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <main class="content">
        <?php include __DIR__ . '/' . $pages[$page][2]; ?>
    </main>
</div>

<div id="toast" class="toast"></div>
<script src="assets/app.js"></script>
</body>
</html>
