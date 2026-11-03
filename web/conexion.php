<?php
/**
 * YAMICELL — Módulo Central de Conexión PDO (SQLite3)
 * Puestos 98 (Pasillo Tarija) y 121 (Pasillo Las Américas)
 *
 * Reglas estrictas INF-312:
 *  - PDO con sentencias preparadas en TODO acceso a datos (cero SQL injection).
 *  - PDO::ERRMODE_EXCEPTION + PRAGMA foreign_keys = ON en cada conexión.
 *  - La base se auto-inicializa desde fase2/script_yamicell.sql si está vacía.
 */

$db_path    = __DIR__ . '/../yamicell_database.db';
$sql_script = __DIR__ . '/../fase2/script_yamicell.sql';

try {
    $need_init = !file_exists($db_path) || filesize($db_path) === 0;

    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");

    if ($need_init && file_exists($sql_script)) {
        $pdo->exec(file_get_contents($sql_script));
    }
} catch (PDOException $e) {
    http_response_code(500);
    die("<div style='font-family:system-ui,sans-serif;max-width:650px;margin:50px auto;padding:24px;background:#fff5f5;border-left:6px solid #dc2626;border-radius:8px;'>
        <h2 style='color:#991b1b;margin-top:0'>Error Crítico de Base de Datos (PDO)</h2>
        <p><strong>Detalle:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Verifica permisos sobre <code>yamicell_database.db</code> o regenera con el botón «Reiniciar Datos Demo».</p></div>");
}

/** Formatea un monto en Bolivianos con 2 decimales. */
function bs(float $m): string {
    return 'Bs. ' . number_format($m, 2, '.', ',');
}

/** Fetch helper con prepare() — jamás concatenar valores en SQL. */
function q(PDO $pdo, string $sql, array $params = []): PDOStatement {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
