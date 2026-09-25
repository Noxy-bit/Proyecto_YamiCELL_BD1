**Universidad Autónoma "Gabriel René Moreno"**
**Materia:** Base de Datos I (INF-312)
**Docente:** Ing. Juan Carlos Peinado
**Estudiante:** Oscar Alejandro Nava Orihuela
**Caso asignado:** Sistema de Gestión Comercial Mixto (YamiCELL)

## 📝 Descripción General
El proyecto consiste en diseñar e implementar una base de datos relacional para el negocio comercial **YamiCELL**. El sistema resuelve la problemática de gestionar un inventario mixto (accesorios) y separar lógicamente los servicios técnicos, unificando todo en un solo flujo de caja centralizado.

El proyecto se evalúa en **dos fases**:
| Fase | Descripción | % del Proyecto |
|---|---|---|
| **Fase 1** | Diseño conceptual + lógico | 50% |
| **Fase 2** | Implementación en SQL + consultas | 50% |

## ✅ Criterios de aceptación técnica mínimos
* [x] El script SQL se ejecuta de inicio a fin sin errores en una BD vacía.
* [x] Existen datos de prueba suficientes para demostrar cada consulta.
* [x] Las consultas entregadas están comentadas y validadas con JOIN y Subconsultas.
* [x] La normalización está justificada hasta 3FN como mínimo.
* [x] El modelo implementado refleja el diagrama conceptual aprobado.

---

## 📐 Fase 1 — Diseño Conceptual e Intermedio
**Entregable:** Diagrama Entidad-Relación (`Modelo_Conceptual_YamiCELL.drawio`)

### Descripción de Reglas de Negocio
* **Catálogo Mixto:** Los artículos se registran en una tabla general agrupados por categoría (ej. Tecnología, Cosmética).
* **Trazabilidad de Celulares:** Equipos de alto valor se anclan al catálogo general pero exigen un registro de IMEI único.
* **Servicio Técnico:** Arreglos independientes a las ventas físicas, exigiendo datos del cliente, falla reportada y costo.
* **Caja Centralizada:** Toda venta o servicio reporta ingresos a un puesto físico específico (ej. Pasillo Tarija).

### Requerimiento 2: Mapeo Objeto-Relacional
* `CLIENTE(id_cliente {PK}, nombre_completo, telefono)`
* `PUESTO(id_puesto {PK}, nombre_puesto, sector)`
* `PRODUCTO(id_producto {PK}, nombre_producto, categoria, precio, stock)`
* `CELULAR(imei {PK}, estado_fisico, estado_red, id_producto {FK})`
* `VENTA(id_venta {PK}, fecha, total, id_cliente {FK}, id_puesto {FK})`
* `DETALLE_VENTA(id_detalle {PK}, cantidad, subtotal, id_venta {FK}, id_producto {FK})`
* `SERVICIO_TECNICO(id_servicio {PK}, falla_reportada, costo, id_cliente {FK}, id_puesto {FK})`

### Requerimiento 3: Normalización (3FN)
El modelo cumple rigurosamente con la 3FN. Se evitó la anomalía de actualización separando el carrito de compras (`DETALLE_VENTA`) de la cabecera de la factura (`VENTA`). De esta manera, se eliminan las dependencias transitivas y se permite que una venta tenga múltiples productos sin repetir los datos del cliente o la fecha.

---

## 💻 Fase 2 — Implementación en SQL
**Entregable:** Archivo `script_yamicell.sql`

### Requerimiento 4 y 5: Script DDL y DML
El script adjunto en el repositorio incluye:
1. Creación de tablas con tipos de datos adecuados y llaves primarias (PK).
2. Llaves foráneas (FK) garantizando integridad referencial.
3. Datos de prueba coherentes (INSERT).
4. Consultas requeridas por la Unidad 4 (JOIN, LEFT JOIN, Subconsultas) y control de Transacciones (BEGIN/COMMIT).