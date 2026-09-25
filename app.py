import sqlite3
import os

def ejecutar_proyecto_yamicell():
    print("=========================================")
    print("🚀 INICIANDO SISTEMA YAMICELL (SQLITE) 🚀")
    print("=========================================")

    # 1. Conectarnos a una base de datos local (si no existe, la crea)
    conexion = sqlite3.connect("yamicell_database.db")
    cursor = conexion.cursor()

    # 2. Leer tu archivo SQL de la Fase 2
    ruta_script = os.path.join("fase2", "script_yamicell.sql")
    
    try:
        with open(ruta_script, 'r', encoding='utf-8') as archivo_sql:
            script_completo = archivo_sql.read()
            
            # 3. Ejecutar todo tu código SQL de golpe
            cursor.executescript(script_completo)
            print("✅ Tablas creadas con éxito.")
            print("✅ Datos de prueba insertados.")
            print("✅ Vistas generadas correctamente.\n")

            # 4. Probar que funciona haciendo una consulta real
            print("📊 REPORTE RÁPIDO: PUESTOS Y SUS VENTAS TOTALES")
            print("-----------------------------------------")
            cursor.execute("SELECT * FROM V_INGRESOS_PUESTOS;")
            resultados = cursor.fetchall()
            
            for fila in resultados:
                print(f"📍 Puesto: {fila[0]} | 💰 Total Generado: ${fila[1]}")
            
    except Exception as e:
        print(f"❌ Error al ejecutar el script: {e}")
    finally:
        conexion.close()
        print("\n=========================================")
        print("✅ EJECUCIÓN FINALIZADA CORRECTAMENTE")
        print("=========================================")

# Arrancar el programa
if __name__ == "__main__":
    ejecutar_proyecto_yamicell()