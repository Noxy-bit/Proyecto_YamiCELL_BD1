import sqlite3
import os

DB_NAME = "yamicell_database.db"
SQL_FILE = "fase2/script_yamicell.sql"

# Eliminar base de datos anterior para pruebas limpias en vivo
if os.path.exists(DB_NAME):
    os.remove(DB_NAME)

conexion = sqlite3.connect(DB_NAME)
cursor = conexion.cursor()
cursor.execute("PRAGMA foreign_keys = ON;")

# Leer y ejecutar el script SQL completo de YamiCELL
if os.path.exists(SQL_FILE):
    with open(SQL_FILE, "r", encoding="utf-8") as f:
        cursor.executescript(f.read())
    conexion.commit()
    print("====================================================")
    print(" SISTEMA YAMICELL - BASE DE DATOS INICIADA CON ÉXITO")
    print("====================================================")
else:
    print(f"[ERROR] No se encontró el archivo SQL en la ruta: {SQL_FILE}")

def mostrar_tabla_en_caja(titulo, query):
    print(f"\n📊 {titulo}")
    try:
        cursor.execute(query)
        filas = cursor.fetchall()
        columnas = [desc[0] for desc in cursor.description]
        
        if not filas:
            print("(La tabla no contiene registros actualmente)")
            return

        # Calcular anchos dinámicos de columnas para formatear la caja
        anchos = [max(len(str(col)), max((len(str(f[i])) for f in filas), default=0)) for i, col in enumerate(columnas)]
        borde = "+-" + "-+-".join("-" * a for a in anchos) + "-+"
        
        print(borde)
        print("| " + " | ".join(str(col).ljust(anchos[i]) for i, col in enumerate(columnas)) + " |")
        print(borde)
        for fila in filas:
            print("| " + " | ".join(str(val).ljust(anchos[i]) for i, val in enumerate(fila)) + " |")
        print(borde)
    except Exception as e:
        print(f"[Error al consultar]: {e}")

# Mostrar las tablas principales y las vistas solicitadas en la Unidad 4 frente al Ing. Peinado
mostrar_tabla_en_caja("TABLA MAESTRA: PUESTOS FÍSICOS", "SELECT * FROM PUESTO;")
mostrar_tabla_en_caja("TABLA MAESTRA: PRODUCTOS Y VITRINA", "SELECT * FROM PRODUCTO;")
mostrar_tabla_en_caja("TABLA DEPENDIENTE: CELULARES (Especialización 1 a 0..1)", "SELECT * FROM CELULAR;")
mostrar_tabla_en_caja("TRANSACCIONAL: VENTAS REGISTRADAS", "SELECT * FROM VENTA;")
mostrar_tabla_en_caja("REPORTE UNIDAD 4: VISTA DE INGRESOS POR PUESTO", "SELECT * FROM V_INGRESOS_PUESTOS;")
mostrar_tabla_en_caja("REPORTE UNIDAD 4: PRODUCTOS CON STOCK CRÍTICO (<5)", "SELECT * FROM V_STOCK_CRITICO;")

conexion.close()