# Proyecto Final · INF-312 Base de Datos I

**Universidad Autónoma "Gabriel René Moreno"**
**Materia:** Base de Datos I (INF-312)
**Docente:** Ing. Juan Carlos Peinado
**Estudiante:** Oscar Alejandro Nava Orihuela
**Caso de Estudio:** Sistema de Gestión Comercial Mixto (Comercial "YamiCELL")

---

## 📖 Planteamiento del Problema
Este proyecto nace del levantamiento de requerimientos y entrevistas realizadas al local comercial **YamiCELL**. Este negocio tiene la particularidad de manejar dos rubros totalmente distintos en un mismo espacio físico:
1. Un inventario masivo de productos variados (cables, accesorios, maquillaje, etc.).
2. Un taller de servicio técnico para reparación de celulares.

**El problema detectado:** 
Actualmente todo se registra de forma manual en cuadernos. Esto genera mucho desorden porque es difícil cruzar la información para saber cuánto dinero ingresó por vender accesorios y cuánto ingresó por mano de obra de reparaciones. Además, al vender equipos caros, el registro manual complica llevar un control formal de los IMEI para el tema de garantías.

## 🎯 Solución y Estructura
Se diseñó una base de datos relacional para digitalizar el negocio. El proyecto se dividió en dos fases de desarrollo:

| Fase | Descripción de la entrega |
|---|---|
| **Fase 1** | Diseño conceptual (Draw.io) y modelado lógico. |
| **Fase 2** | Implementación del código SQL y consultas. |

---

## 📐 Fase 1 — Diseño y Reglas de Negocio
**Archivo:** `Modelo_Conceptual_YamiCELL.drawio`

Durante el análisis del negocio, definimos cómo se iban a organizar los datos:
* **Catálogo General:** Todo lo que se vende (fundas, rímel, cargadores) va a una sola gran tabla de `PRODUCTO`. 
* **Control de Celulares:** Como los celulares son equipos caros que necesitan garantía, creamos una tabla especial `CELULAR` que se conecta al catálogo. Esto obliga al sistema a registrar el código IMEI único de cada teléfono.
* **Servicio Técnico:** Las reparaciones se manejan en su propia tabla porque requieren datos diferentes a una venta normal (qué falla reportó el cliente, costo del arreglo, etc.).

### Mapeo Relacional (Las Tablas)
Para pasar el dibujo a una base de datos real, usamos Llaves Primarias (PK) para darle un ID único a cada registro, y Llaves Foráneas (FK) para conectar las tablas. Por ejemplo, en vez de escribir todo el nombre del cliente cada vez que compra, solo guardamos su `id_cliente` en la tabla de ventas.

* `CLIENTE(id_cliente {PK}, nombre_completo, telefono)`
* `PUESTO(id_puesto {PK}, nombre_puesto, sector)`
* `PRODUCTO(id_producto {PK}, nombre_producto, categoria, precio, stock)`
* `CELULAR(imei {PK}, estado_fisico, estado_red, id_producto {FK})`
* `VENTA(id_venta {PK}, fecha, total, id_cliente {FK}, id_puesto {FK})`
* `DETALLE_VENTA(id_detalle {PK}, cantidad, subtotal, id_venta {FK}, id_producto {FK})`
* `SERVICIO_TECNICO(id_servicio {PK}, falla_reportada, costo, id_cliente {FK}, id_puesto {FK})`

### ¿Por qué aplicamos Normalización (3FN)?
Al principio parecía buena idea registrar todo lo que un cliente compraba en la misma tabla de `VENTA`. El problema es que si alguien compraba 5 accesorios diferentes, teníamos que repetir 5 veces la fecha, el cajero y los datos del cliente. 
Para evitar esa redundancia, aplicamos la **Tercera Forma Normal (3FN)**: separamos la cabecera de la factura (`VENTA`) del carrito de compras (`DETALLE_VENTA`). Así la base de datos queda ligera y organizada.

---

## 💻 Fase 2 — Implementación
**Archivo:** `script_yamicell.sql`

En la segunda fase pasamos todo a código para SQLite. En el script incluimos:
1. Comandos de limpieza (`DROP TABLE`) para que el código se pueda ejecutar varias veces sin lanzar errores.
2. Restricciones de seguridad (`CHECK`) para que nadie pueda registrar, por error, un producto con precio negativo.
3. El uso de `ON DELETE CASCADE` para que no queden datos basura si un cliente es eliminado.

**El plus del proyecto (app.py):**
Para no solo entregar un archivo de texto con código SQL, desarrollamos un pequeño script en Python. Este archivo se conecta a SQLite, ejecuta la base de datos desde cero, inserta datos de prueba y nos imprime en la terminal un reporte real de los ingresos del negocio usando Vistas.