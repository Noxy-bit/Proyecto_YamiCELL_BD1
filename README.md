# Sistema de Gestión Comercial y Servicio Técnico · YamiCELL

| Datos Académicos | Detalle |
| :--- | :--- |
| **Universidad** | Universidad Autónoma Gabriel René Moreno (UAGRM) - FICCT |
| **Materia** | Base de Datos I (INF-312) |
| **Docente** | Ing. MSc Juan Carlos Peinado |
| **Estudiante** | Oscar Alejandro Nava Orihuela |
| **Caso de Estudio** | Tienda Comercial Mixta y Taller Técnico "YamiCELL" (Puestos 98 y 121) |

---

## 1. Planteamiento del Problema
Este proyecto nace del levantamiento de requerimientos y entrevistas realizadas al negocio **YamiCELL**, el cual opera en dos sucursales físicas (**Puesto 98 en Pasillo Tarija** y **Puesto 121 en Pasillo Las Américas**) combinando dos rubros distintos en un mismo espacio:
1. **Venta minorista de alta rotación:** Cables, cargadores, fundas, artículos electrónicos, cosmética (maquillaje) y teléfonos celulares (nuevos y semi-nuevos).
2. **Taller de servicio técnico:** Diagnóstico y reparación especializada de equipos móviles.

**Problema detectado:**  
El registro diario se llevaba de forma manual en cuadernos y mensajes de WhatsApp. Esto impedía cruzar información con exactitud para diferenciar los ingresos por venta de accesorios frente a los ingresos por mano de obra técnica, generaba descuadres de caja al reponer mercadería y dificultaba el rastreo formal del código **IMEI** en equipos con garantía.

---

## 2. Arquitectura y Fases del Proyecto

| Fase | Entregable Principal | Descripción |
| :--- | :--- | :--- |
| **Fase 1** | `fase1/Entrevista.md` y `Modelo_Conceptual_YamiCELL.drawio` | Levantamiento de requerimientos, reglas de negocio, diseño conceptual y normalización (1FN, 2FN, 3FN). |
| **Fase 2** | `fase2/script_yamicell.sql` y `app.py` | Implementación física en SQL (DDL, DML, restricciones `CHECK`, transacciones ACID, Vistas y Consultas Avanzadas) junto con el auditor en consola y panel web en PHP. |

---

## 3. Fase 1: Diseño Relacional y Reglas de Negocio

* **Catálogo General (`PRODUCTO`):** Centraliza todos los artículos comerciales validando mediante restricciones `CHECK` que ni el precio ni el stock puedan ser negativos.
* **Especialización de Celulares (`1 a 0..1` en `CELULAR`):** Debido a que los accesorios y cosméticos no poseen IMEI, los teléfonos se separan en la tabla `CELULAR` vinculada mediante `id_producto {FK, UNIQUE}`, registrando `imei`, `estado_fisico` y `estado_red` sin generar valores `NULL` en el resto del inventario.
* **Modalidad de Venta Rápida (`id_cliente = 0`):** En ventas de mostrador de bajo costo no se exige nombre ni teléfono al comprador para no demorar la atención; se asigna el registro maestro `id_cliente = 0` (*Cliente Ocasional*), exigiendo datos nominales completos para ventas de celulares o recepciones en **Servicio Técnico**.

### Mapeo Relacional Definitivo
* `CLIENTE(id_cliente {PK}, nombre_completo, telefono)`
* `PUESTO(id_puesto {PK}, nombre_puesto, sector)`
* `PRODUCTO(id_producto {PK}, nombre_producto, categoria, precio, stock)`
* `CELULAR(imei {PK}, estado_fisico, estado_red, id_producto {FK, UNIQUE})`
* `VENTA(id_venta {PK}, fecha, metodo_pago, total, id_cliente {FK}, id_puesto {FK})`
* `DETALLE_VENTA(id_detalle {PK}, cantidad, subtotal, id_venta {FK}, id_producto {FK})`
* `SERVICIO_TECNICO(id_servicio {PK}, fecha_ingreso, falla_reportada, costo, estado, id_cliente {FK}, id_puesto {FK})`

---

## 4. Aplicación de las 3 Formas Normales (Normalización)

### 4.1 Primera Forma Normal (1FN): Datos Atómicos
* **Regla:** Cada atributo debe contener un valor atómico (indivisible), sin grupos repetidos, identificando cada tupla mediante una Llave Primaria (`PK`).
* **Aplicación:** En `CLIENTE`, el campo `telefono` almacena un único número de contacto principal por registro.

| id_cliente (PK) | nombre_completo | telefono |
| :--- | :--- | :--- |
| 0 | Cliente Ocasional (Venta Rápida) | S/N |
| 1 | Carlos Mendoza | 77889900 |
| 2 | María Fernanda López | 71234567 |

### 4.2 Segunda Forma Normal (2FN): Dependencia Funcional Completa
* **Regla:** Cumplir con 1FN y garantizar que todo atributo no clave dependa íntegramente de la Llave Primaria, sin dependencias parciales.
* **Aplicación:** En `PRODUCTO`, tanto `nombre_producto`, `categoria`, `precio` como `stock` dependen de forma exclusiva de `id_producto`.

| id_producto (PK) | nombre_producto | categoria | precio | stock |
| :--- | :--- | :--- | :--- | :--- |
| 101 | Cable Tipo C Carga Rápida 25W | Accesorios | 25.00 | 48 |
| 104 | Samsung Galaxy A54 5G | Celulares | 2100.00 | 2 |

### 4.3 Tercera Forma Normal (3FN): Cero Dependencia Transitiva
* **Regla:** Cumplir con 2FN y asegurar que ningún atributo no clave dependa transitivamente de otro atributo no clave.
* **Aplicación:** Se desacopló la facturación en cabecera (`VENTA`) y renglones (`DETALLE_VENTA`), y se aisló la ficha técnica del teléfono en `CELULAR`.

**Tabla `VENTA` (Cabecera del recibo):**

| id_venta (PK) | fecha | metodo_pago | total | id_cliente (FK) | id_puesto (FK) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1003 | 2026-09-28 | TRANSFERENCIA | 2125.00 | 1 (Carlos Mendoza) | 98 (Puesto 98 - Tarija) |

**Tabla `DETALLE_VENTA` (Renglones asociados al recibo 1003):**

| id_detalle (PK) | cantidad | subtotal | id_venta (FK) | id_producto (FK) |
| :--- | :--- | :--- | :--- | :--- |
| 3 | 1 | 2100.00 | 1003 | 104 (Samsung Galaxy A54 5G) |
| 4 | 1 | 25.00 | 1003 | 101 (Cable Tipo C Carga Rápida 25W) |

---

## 5. Fase 2: Implementación SQL, Transacciones y Consultas Avanzadas

El archivo `fase2/script_yamicell.sql` implementa la base de datos completa e incluye:
1. **Integridad Referencial y de Dominio:** Uso de `PRAGMA foreign_keys = ON`, `ON DELETE CASCADE` y validaciones `CHECK` para sectores permitidos (`Pasillo Tarija`, `Pasillo Américas`), categorías (`Accesorios`, `Cosmética`, `Celulares`), métodos de pago (`EFECTIVO`, `QR`, `TRANSFERENCIA`) y control de valores no negativos (`precio >= 0`, `stock >= 0`).
2. **Transacciones Atómicas (ACID):** Bloque `BEGIN ... COMMIT` que registra la venta, inserta el detalle y descuenta el inventario simultáneamente, permitiendo `ROLLBACK` ante cualquier violación de stock.
3. **Vistas Corporativas:**
   * `V_STOCK_CRITICO`: Alerta automática de productos con menos de 5 unidades.
   * `V_INGRESOS_PUESTOS`: Consolidado financiero y conteo de ventas por sucursal.
   * `V_HISTORIAL_REPARACIONES`: Reporte cruzado de clientes, puestos, fallas y estados del taller.
4. **Consultas Avanzadas de Negocio:**
   * Estadísticas por categoría con `COUNT`, `ROUND(AVG(), 2)`, `MIN`, `MAX` y `GROUP BY`.
   * Reporte detallado multi-tabla con `INNER JOIN`.
   * Filtrado de clientes frecuentes con `GROUP BY` y `HAVING`.
   * Auditoría de mercadería sin rotación mediante `LEFT JOIN ... IS NULL` y subconsulta correlacionada `NOT EXISTS`.
   * Funciones de ventana: Ranking del producto más vendido por categoría (`CTE` + `DENSE_RANK() OVER (PARTITION BY ...)`) y acumulado histórico de compras por cliente (`SUM() OVER (PARTITION BY ... ORDER BY ...)`).

### Ejecución Rápida del Auditor en Consola
Para reconstruir la base de datos `yamicell_database.db` desde cero y visualizar todas las tablas, vistas y consultas avanzadas en cajas formateadas en la terminal:

`python3 app.py`
