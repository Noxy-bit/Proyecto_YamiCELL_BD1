# Sistema de Gestión YamiCELL: Inventario Mixto y Servicio Técnico
**Materia:** Base de Datos I  
**Docente:** Ing. Peinado  
**Estudiante:** Oscar Alejandro Nava Orihuela 

## 1. Planteamiento del Problema
**YamiCELL** es un negocio familiar y de esfuerzo constante que ha evolucionado de reparar radios y vender cargadores antiguos, a un puesto comercial mixto. Actualmente, el negocio maneja dos mundos paralelos:
1. Un inventario masivo y variado ("Inventario Mixto") que incluye desde accesorios tecnológicos (cables, vidrios templados) hasta productos de belleza y cuidado personal (cosmética, bisutería, accesorios capilares).
2. Un taller de reparaciones (Servicio Técnico) construido a base de experiencia, y la venta de equipos tecnológicos de mayor valor.

## 1. Planteamiento del Problema
El registro manual actual dificulta controlar la reposición de productos de alta rotación (como maquillaje o cables), no permite llevar un historial formal de los arreglos técnicos realizados, y complica el seguimiento de las garantías de celulares mediante su IMEI.

## 2. Solución Propuesta (Minimundo)
Se diseñará una base de datos relacional a la medida de **YamiCELL** capaz de separar lógicamente los servicios de los productos físicos, unificando todo en un solo flujo de caja.

### Reglas de Negocio:
1. **Catálogo Mixto por Categorías:** Todo artículo general (desde un rímel, pinzas o vaselina, hasta un cargador tipo C o fundas) se registrará en una tabla de `PRODUCTO` agrupado por `categoría` (ej. Cosmética, Tecnología, Accesorios), controlándose únicamente por su cantidad en `stock`.
2. **Trazabilidad de Celulares:** Todo equipo de alto valor (celulares) se anclará al catálogo general, pero exigirá obligatoriamente el registro de su código `IMEI` para evitar duplicidad y proteger las garantías.
3. **Control del Servicio Técnico:** Los arreglos de hardware/software se registrarán de forma independiente a las ventas físicas, exigiendo el nombre del **CLIENTE** y documentando la falla diagnosticada y el costo del servicio.
4. **Caja Centralizada:** Toda Venta (ya sea de un arito, una funda o el cobro de una reparación) reportará sus ingresos al **PUESTO** principal, permitiendo a los dueños conocer la rentabilidad exacta del día.