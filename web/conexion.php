<?php
/**
 * YAMICELL - Módulo Central de Conexión PDO (SQLite3)
 * Puestos 98 (Pasillo Tarija) y 121 (Pasillo Las Américas)
 */

$db_path = __DIR__ . '/../yamicell_database.db';
$sql_script = __DIR__ . '/../fase2/script_yamicell.sql';

try {
    $ need_init = !file_exists($db_path) || filesize($db_path) === 0;

    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // REQUISITO ESTRICTO UNIDAD 4: Integridad referencial y ON DELETE CASCADE activos
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // Auto-inicialización si la base de datos no existe
    if ($need_init && file_exists($sql_script)) {
        $sql = file_get_contents($sql_script);
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    die("
    <div style='font-family: system-ui, sans-serif; max-width: 650px; margin: 50px auto; padding: 24px; background: #fff5f5; border-left: 6px solid #dc2626; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);'>
        <h2 style='color: #991b1b; margin-top: 0;'>Error Crítico de Base de Datos (PDO)</h2>
        <p style='color: #374151;'><strong>Detalle técnico:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p style='color: #4b5563; font-size: 0.9rem;'>Verifica que <code>yamicell_database.db</code> tenga permisos de lectura/escritura o ejecuta <code>python3 app.py</code> en la terminal.</p>
    </div>");
}
?>