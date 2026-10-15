# Proyecto Final · INF-312 Base de Datos I

**Universidad Autónoma Gabriel René Moreno**
**Materia:** Base de Datos I (INF-312)
**Docente:** Ing. Juan Carlos Peinado
**Estudiante:** Oscar Alejandro Nava Orihuela
**Caso de Estudio:** Sistema de Gestión Comercial Mixto (Tienda "YamiCELL")

---

## Planteamiento del Problema
Este proyecto nace del levantamiento de requerimientos y entrevistas realizadas al local **YamiCELL**. Este negocio tiene la particularidad de manejar dos rubros totalmente distintos en un mismo espacio físico:
1. Un inventario masivo de productos variados (cables, accesorios, maquillaje, etc.).
2. Un taller de servicio técnico para reparación de celulares.

**El problema detectado:** 
Actualmente todo se registra de forma manual en cuadernos. Esto genera mucho desorden porque es difícil cruzar la información para saber cuánto dinero ingresó por vender accesorios y cuánto ingresó por mano de obra de reparaciones. Además, al vender equipos caros, el registro manual complica llevar un control formal de los IMEI para el tema de garantías.

## Solución y Estructura
Se diseñó una base de datos relacional para digitalizar el negocio. El proyecto se dividió en dos fases de desarrollo:

| Fase | Descripción de la entrega |
|---|---|
| **Fase 1** | Diseño conceptual (Draw.io) y modelado lógico. |
| **Fase 2** | Implementación del código SQL y consultas. |

---

##  Fase 1: Diseño y Reglas de Negocio
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

Aplicación de las 3 Formas Normales (Normalización)
Para garantizar que la base de datos sea eficiente, no tenga datos duplicados y evite anomalías al insertar o borrar información, el modelo del sistema fue sometido rigurosamente a las tres formas normales:

**1. Primera Forma Normal (1FN): Datos Atómicos**
Regla: Cada columna debe contener un valor atómico (indivisible) y no se permiten listas o grupos de valores repetidos. Cada fila debe ser única mediante una Llave Primaria (PK).

Aplicación:
En la tabla CLIENTE, el atributo telefono almacena un único número de contacto. No se permiten listas de teléfonos separadas por comas.

tabla:
| id_cliente (PK) | nombre_completo | telefono |
| :--- | :--- | :--- |
| 1 | Juan Pérez | 77123456 |
| 2 | María Gómez | 79876543 |

**2. Segunda Forma Normal (2FN): Dependencia Funcional Completa**
Regla: Debe cumplir con la 1FN y todos los atributos que no son clave deben depender completamente de la Llave Primaria completa (evitando dependencias parciales).

Aplicación:
Dado que utilizamos Llaves Primarias artificiales e independientes (id_producto, id_cliente), garantizamos la 2FN de forma directa. Por ejemplo, en la tabla PRODUCTO, el nombre_producto, el precio y el stock dependen única y exclusivamente del código del producto (id_producto), sin depender de otros campos.

tabla:
| id_producto (PK) | nombre_producto | categoria | precio | stock |
| :--- | :--- | :--- | :--- | :--- |
| 101 | Cable USB Tipo C | Accesorios | 15.00 | 20 |

**3. Tercera Forma Normal (3FN): Cero Dependencia Transitiva**
Regla: Debe cumplir con la 2FN y ningún atributo que no sea clave debe depender de otro atributo que tampoco sea clave (es decir, los datos no clave no deben transitar a través de otros campos).

Aplicación en YamiCELL: Este fue el paso más crítico del diseño para evitar redundancias y anomalías:

Separación de Ventas (Cabecera y Detalle): En lugar de repetir los datos del cliente, la fecha o el puesto cada vez que alguien compra varios artículos, dividimos la transacción en dos tablas: VENTA (Cabecera) y DETALLE_VENTA (Carrito).

Ejemplo de la separación en 3FN:

Tabla VENTA (Cabecera general):
| id_venta (PK) | fecha | total | id_cliente (FK) | id_puesto (FK) |
| :--- | :--- | :--- | :--- | :--- |
| 50 | 2026-10-15 | 45.00 | 0 (Ocasional) | 1 (Pasillo Tarija) |

Tabla DETALLE_VENTA (Renglones del carrito asociados a la venta anterior):
| id_detalle (PK) | cantidad | subtotal | id_venta (FK) | id_producto (FK) |
| :--- | :--- | :--- | :--- | :--- |
| 1 | 2 | 30.00 | 50 | 101 (Cable) |
| 2 | 1 | 15.00 | 50 | 105 (Funda) |

Separación del Inventario (PRODUCTO y CELULAR): Separamos los teléfonos de la tabla general para evitar campos vacíos (NULL) en los accesorios. Cada entidad maneja sus propios atributos específicos sin depender de datos ajenos.



## Fase 2: Implementación
**Archivo:** `script_yamicell.sql`

En la segunda fase pasamos todo a código para SQLite. En el script incluimos:
1. Comandos de limpieza (`DROP TABLE`) para que el código se pueda ejecutar varias veces sin lanzar errores.
2. Restricciones de seguridad (`CHECK`) para que nadie pueda registrar, por error, un producto con precio negativo.
3. El uso de `ON DELETE CASCADE` para que no queden datos basura si un cliente es eliminado.

###  avance (app.py)
Quería comprobar si mi diseño funcionaba en la práctica y no solo en papel. Por eso, armé un script rápido en Python que se conecta a SQLite, ejecuta mi archivo SQL desde cero, le mete unos datos de prueba y me tira un reporte real de ganancias en la terminal. Así compruebo que las consultas y las Vistas responden bien.

### Decisiones de Diseño en el Trabajo
Durante el análisis del negocio, noté un detalle operativo vital: **no se le pide nombre ni teléfono a un cliente que solo compra un accesorio barato**. 
Para que la base de datos sea rápida y no retrase las ventas de mostrador, implementé la figura del `id_cliente = 0` (Cliente Ocasional). De esta manera, el sistema permite registrar ventas menores al instante, pero mantiene la exigencia de registrar datos reales obligatorios cuando el cliente deja un equipo caro en el módulo de **Servicio Técnico**.
