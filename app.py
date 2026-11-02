import sqlite3
import os

DB_NAME = "yamicell_database.db"
SQL_FILE = "fase2/script_yamicell.sql"

# Recrear base de datos limpia desde el script SQL actualizado
if os.path.exists(DB_NAME):
    os.remove(DB_NAME)

conexion = sqlite3.connect(DB_NAME)
cursor = conexion.cursor()
cursor.execute("PRAGMA foreign_keys = ON;")

if os.path.exists(SQL_FILE):
    with open(SQL_FILE, "r", encoding="utf-8") as f:
        cursor.executescript(f.read())
    conexion.commit()
    print("==================================================================")
    print(" SISTEMA CORPORATIVO YAMICELL - BASE DE DATOS INICIADA CON ÉXITO")
    print("==================================================================")
else:
    print(f"[ERROR] No se encontró el archivo SQL en la ruta: {SQL_FILE}")

def mostrar_tabla_en_caja(titulo, query):
    print(f"\n📊 {titulo}")
    try:
        cursor.execute(query)
        filas = cursor.fetchall()
        columnas = [desc[0] for desc in cursor.description]
        
        if not filas:
            print("(Sin registros para mostrar)")
            return

        anchos = [max(len(str(col)), max((len(str(f[i])) for f in filas), default=0)) for i, col in enumerate(columnas)]
        borde = "+-" + "-+-".join("-" * a for a in anchos) + "-+"
        
        print(borde)
        print("| " + " | ".join(str(col).ljust(anchos[i]) for i, col in enumerate(columnas)) + " |")
        print(borde)
        for fila in filas:
            print("| " + " | ".join(str(val).ljust(anchos[i]) for i, val in enumerate(fila)) + " |")
        print(borde)
    except Exception as e:
        print(f"[Error en consulta]: {e}")

# 1. TABLAS MAESTRAS Y ESPECIALIZACIÓN
mostrar_tabla_en_caja("TABLA MAESTRA: PUESTOS FÍSICOS (3FN)", "SELECT * FROM PUESTO;")
mostrar_tabla_en_caja("TABLA MAESTRA: CLIENTES REGISTRADOS", "SELECT * FROM CLIENTE;")
mostrar_tabla_en_caja("TABLA MAESTRA: PRODUCTOS Y VITRINA", "SELECT * FROM PRODUCTO;")
mostrar_tabla_en_caja("ESPECIALIZACIÓN (1 a 0..1): CELULARES CON IMEI", "SELECT * FROM CELULAR;")

# 2. VISTAS CORPORATIVAS (UNIDAD 4)
mostrar_tabla_en_caja("VISTA 1: ALERTA DE STOCK CRÍTICO (< 5 UNIDADES)", "SELECT * FROM V_STOCK_CRITICO;")
mostrar_tabla_en_caja("VISTA 2: RENDIMIENTO E INGRESOS POR PUESTO", "SELECT * FROM V_INGRESOS_PUESTOS;")
mostrar_tabla_en_caja("VISTA 3: HISTORIAL DE SERVICIOS TÉCNICOS", "SELECT * FROM V_HISTORIAL_REPARACIONES;")

# 3. CONSULTAS AVANZADAS DEL SOLUCIONARIO DEL ING. PEINADO
mostrar_tabla_en_caja("SOLUCIÓN 3: ESTADÍSTICAS DE PRECIOS POR CATEGORÍA (GROUP BY)", """
SELECT categoria, COUNT(*) AS total_productos, ROUND(AVG(precio), 2) AS precio_promedio,
       MIN(precio) AS precio_minimo, MAX(precio) AS precio_maximo
FROM PRODUCTO GROUP BY categoria;
""")

mostrar_tabla_en_caja("SOLUCIÓN 4: REPORTE DETALLADO DE VENTAS (INNER JOIN MÚLTIPLE)", """
SELECT v.id_venta, v.fecha, c.nombre_completo AS cliente, p.nombre_producto AS producto,
       dv.cantidad, dv.subtotal, v.metodo_pago, pu.nombre_puesto AS puesto
FROM VENTA v
INNER JOIN CLIENTE c ON v.id_cliente = c.id_cliente
INNER JOIN DETALLE_VENTA dv ON v.id_venta = dv.id_venta
INNER JOIN PRODUCTO p ON dv.id_producto = p.id_producto
INNER JOIN PUESTO pu ON v.id_puesto = pu.id_puesto
ORDER BY v.fecha DESC;
""")

mostrar_tabla_en_caja("SOLUCIÓN 6: CLIENTES DESTACADOS CON COMPRAS > 100 BS (HAVING)", """
SELECT c.id_cliente, c.nombre_completo AS cliente, COUNT(v.id_venta) AS compras, SUM(v.total) AS total_gastado
FROM CLIENTE c
INNER JOIN VENTA v ON c.id_cliente = v.id_cliente
GROUP BY c.id_cliente, c.nombre_completo
HAVING SUM(v.total) > 100.00
ORDER BY total_gastado DESC;
""")

mostrar_tabla_en_caja("SOLUCIÓN 8: AUDITORÍA DE PRODUCTOS SIN VENTAS (LEFT JOIN / IS NULL)", """
SELECT p.id_producto, p.nombre_producto, p.categoria, p.precio, p.stock
FROM PRODUCTO p
LEFT JOIN DETALLE_VENTA dv ON p.id_producto = dv.id_producto
WHERE dv.id_detalle IS NULL;
""")

mostrar_tabla_en_caja("SOLUCIÓN 9: TOP PRODUCTO MÁS VENDIDO POR CATEGORÍA (CTE + DENSE_RANK)", """
WITH UnidadesPorProducto AS (
    SELECT p.categoria, p.nombre_producto AS producto, SUM(dv.cantidad) AS total_unidades,
           DENSE_RANK() OVER (PARTITION BY p.categoria ORDER BY SUM(dv.cantidad) DESC) AS ranking
    FROM DETALLE_VENTA dv
    INNER JOIN PRODUCTO p ON dv.id_producto = p.id_producto
    GROUP BY p.categoria, p.id_producto, p.nombre_producto
)
SELECT categoria, producto, total_unidades FROM UnidadesPorProducto WHERE ranking = 1;
""")

mostrar_tabla_en_caja("SOLUCIÓN 10: ACUMULADO HISTÓRICO DE GASTO POR CLIENTE (RUNNING TOTAL)", """
SELECT v.id_venta, v.fecha, c.nombre_completo AS cliente, v.total AS monto_venta,
       SUM(v.total) OVER (PARTITION BY v.id_cliente ORDER BY v.fecha ASC, v.id_venta ASC) AS acumulado_cliente
FROM VENTA v
INNER JOIN CLIENTE c ON v.id_cliente = c.id_cliente
ORDER BY c.id_cliente, v.fecha ASC;
""")

conexion.close()