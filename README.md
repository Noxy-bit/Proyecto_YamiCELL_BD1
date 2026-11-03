# Sistema ERP Integral · YamiCELL (INF-312 · Base de Datos I)

| Datos Académicos | Detalle |
| :--- | :--- |
| **Universidad** | Universidad Autónoma Gabriel René Moreno (UAGRM) - FICCT |
| **Materia** | Base de Datos I (INF-312) |
| **Docente** | Ing. MSc Juan Carlos Peinado |
| **Estudiante** | Oscar Alejandro Nava Orihuela |
| **Caso de Estudio** | ERP de inventario multi-puesto, facturación POS y analítica BI para la tienda "YamiCELL" (Puesto 98 – Pasillo Tarija y Puesto 121 – Pasillo Las Américas, Santa Cruz) |

---

## 1. Descripción del Sistema

ERP web funcional que digitaliza por completo la operación de YamiCELL: control de inventario por puesto, facturación en punto de venta con transacciones ACID, auditoría kardex inmutable, traspasos logísticos entre puestos y un laboratorio SQL en vivo para la defensa académica.

| Módulo | Qué hace |
| :--- | :--- |
| **Dashboard BI** | KPIs en bolivianos (Bs.), gráficas Chart.js de ventas por día, categoría, método de pago y sucursal; conmutador Consolidado / Puesto 98 / Puesto 121. |
| **Punto de Venta (POS)** | Carrito multi-ítem, validación de stock por puesto, precio público/técnico, descuento en Bs., 4 métodos de pago, alta rápida de cliente. Transacción ACID con `ROLLBACK` automático y mensaje exacto de error. |
| **Matriz de Inventario** | Tabla consolidada por SKU con stock de cada puesto, filtros por categoría/marca/estado, buscador instantáneo, alta de producto, compra de reposición, exportación CSV e impresión. |
| **Kardex Valorado** | Historial inmutable de movimientos (compras, ventas, traspasos, ajustes) con saldo anterior/resultante por fila, protegido por trigger `BEFORE DELETE`. |
| **Traspasos** | Movimiento P98 ⇄ P121 en una sola transacción: `TRASPASO_ENVIO` + `TRASPASO_RECEPCION` como asiento doble en el kardex con código `TR-####`. |
| **Clientes & Proveedores** | Directorio con totales comprados y catálogo por proveedor. |
| **Defensa INF-312 (SQL Lab)** | 8 consultas académicas ejecutables en vivo con medición de ms, consola `SELECT` de solo lectura, DER en Mermaid, diccionario de datos vía `PRAGMA` y panel de objetos SQL (vistas/triggers/índices). |

---

## 2. Arquitectura de Datos — 11 Tablas en 3ra Forma Normal

```text
sucursales ──────────────┐   categorias ────┐   proveedores ───┐
  PUESTO-98 · Tarija     │                  │                │
  PUESTO-121 · Américas  ▼                  ▼                ▼
        usuarios   productos ─────────► inventario_sucursal (N:M stock por puesto)
            │          │                      │
            ▼          ▼                      │
clientes ─► ventas ─► detalle_venta           │
                 │                            │
traspasos_sucursal (origen/destino) ──► kardex_movimientos (auditoría inmutable)
```

| # | Tabla | Rol |
| :- | :--- | :--- |
| 1 | `sucursales` | PUESTO-98 (Tarija) y PUESTO-121 (Las Américas) |
| 2 | `categorias` | Clasificación del repuesto/accesorio |
| 3 | `proveedores` | Importadoras y distribuidoras |
| 4 | `clientes` | `TECNICO_AFILIADO` / `MAYORISTA` / `CLIENTE_FINAL` |
| 5 | `usuarios` | Cajeros/encargados por puesto |
| 6 | `productos` | Catálogo: SKU único, costo, precio técnico/público |
| 7 | `inventario_sucursal` | **Puente N:M** — stock por producto × puesto (2FN/3FN) |
| 8 | `ventas` | Cabecera del recibo `YC-P98-#####` / `YC-P121-#####` |
| 9 | `detalle_venta` | Renglones de la venta (1FN: valores atómicos) |
| 10 | `traspasos_sucursal` | Logística origen→destino con doble FK a `sucursales` |
| 11 | `kardex_movimientos` | Auditoría inmutable: saldo anterior → resultante |

### Normalización aplicada
* **1FN:** atributos atómicos; líneas de venta en `detalle_venta`, no en la cabecera.
* **2FN:** en `inventario_sucursal` cada columna depende del par `id_producto + id_sucursal`; el stock de un puesto no depende solo del producto.
* **3FN:** `categorias`, `proveedores`, `clientes` y `sucursales` son entidades independientes — ningún atributo no-clave depende de otro no-clave (ej. `tipo_cliente` no anida campos del tipo).

### Objetos SQL activos
* **3 vistas:** `vw_inventario_consolidado_puestos` (matriz pivot multi-puesto), `vw_rentabilidad_ventas_detalle` (ganancia por línea), `vw_alertas_reposicion` (stock ≤ mínimo).
* **3 triggers:** `trg_validar_stock_no_negativo` (aborta stock < 0), `trg_actualizar_timestamp_inventario`, `trg_proteger_kardex_inmutable` (kardex solo-lectura).
* **10 índices** sobre SKU, fecha+puesto, kardex, CI/NIT, FKs y pivote inventario.

### Seguridad SQL
Todo el acceso a datos usa **PDO con sentencias preparadas** (`prepare()` + `execute()`), `PDO::ERRMODE_EXCEPTION` y `PRAGMA foreign_keys = ON`. Está prohibido concatenar variables en consultas. La consola del SQL Lab rechaza cualquier sentencia que no comience por `SELECT` y bloquea palabras de escritura.

---

## 3. Stack Técnico

| Capa | Tecnología |
| :--- | :--- |
| Motor de datos | SQLite 3 (archivo `yamicell_database.db`, auto-inicializado desde el script) |
| Backend | PHP 8.1 + PDO (`ERRMODE_EXCEPTION`, `foreign_keys ON`) |
| Frontend | HTML/CSS/JS vanilla + Chart.js 4.4.7 + Mermaid 11.4.1 + FontAwesome 6.5.2 (todos vendored en `web/assets/vendor/` — **funciona 100% offline**) |
| Semilla | `tools/generate_seed.py` genera `fase2/script_yamicell.sql` con kardex matemáticamente consistente (89 movimientos reconciliados contra stock) |

---

## 4. Despliegue Local (producción local)

```bash
# Requisitos: PHP 8.x con extensiones pdo_sqlite y sqlite3
sudo apt install php-cli php-sqlite3 php-mbstring

# Desde la raíz del repositorio:
cd web
php -S 0.0.0.0:8000 -t .

# Abrir: http://localhost:8000/index.php?page=dashboard
```

La base `yamicell_database.db` se autoconstruye la primera vez ejecutando `fase2/script_yamicell.sql` (DDL + triggers + vistas + índices + datos semilla consistentes). El botón **"Reiniciar Datos Demo"** de la barra superior regenera la semilla completa en una transacción.

Para regenerar el script SQL desde el generador determinista:

```bash
python3 tools/generate_seed.py   # reescribe fase2/script_yamicell.sql
```

---

## 5. Las 8 Consultas de la Defensa (SQL Lab)

El módulo *Defensa INF-312* ejecuta en vivo, con temporizador en ms:

1. **Reporte maestro-detalle** — `JOIN` de 5 tablas (ventas, detalle, productos, sucursales, clientes).
2. **Rentabilidad por sucursal y categoría** — ingreso bruto, costo total y ganancia neta.
3. **Matriz pivot de stock** — `CASE WHEN` + `COALESCE` detectando desbalances P98/P121.
4. **Clientes VIP** — `GROUP BY ... HAVING` sobre transacciones y acumulado.
5. **Capital inmovilizado** — `NOT EXISTS` + subconsulta correlacionada (baja rotación).
6. **Auditoría de integridad** — último `stock_resultante` del kardex vs `stock_actual` (CONSISTENTE/ERROR).
7. **Métodos de pago por sucursal** — cobros y ticket promedio por EFECTIVO/QR_SIMPLE/TRANSFERENCIA/MIXTO.
8. **Trazabilidad de traspasos** — doble `JOIN` a `sucursales` como origen y destino.

Además: consola `SELECT` libre de solo lectura, DER renderizado con Mermaid (zoom/pan), diccionario de datos extraído de `PRAGMA table_info()`/`foreign_key_list()` y listado de vistas/triggers/índices desde `sqlite_master`.

---

## 6. Estructura del Repositorio

```text
├── fase1/                     # Entrevista y modelo conceptual
├── fase2/script_yamicell.sql  # DDL + triggers + vistas + índices + seed (generado)
├── tools/generate_seed.py     # Generador determinista del script SQL
├── yamicell_database.db       # SQLite (se autoconstruye; en .gitignore idealmente)
└── web/
    ├── conexion.php           # PDO + helpers bs() y q()
    ├── api.php                # API JSON: dashboard, venta ACID, compras, traspasos, export CSV, exec_sql
    ├── index.php              # Layout + router ?page=
    ├── assets/                # app.css, app.js, vendor/ (Chart.js, Mermaid, FontAwesome)
    └── pages/                 # dashboard, pos, inventario, kardex, traspasos, directorio, defensa
```
