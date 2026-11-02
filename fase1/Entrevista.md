# Entrevista de Levantamiento de Información · Tienda "YamiCELL"

## 1. Rubro y modelo del negocio

**Pregunta 1. ¿A qué se dedica principalmente el negocio YamiCELL?**

Respuesta: El negocio es una tienda comercial mixta que se divide en dos áreas totalmente diferentes dentro del mismo espacio:
- Venta minorista de un inventario variado de accesorios para celulares, artículos electrónicos, controles, productos para el hogar y cosmética (maquillaje).
- Servicio técnico especializado en la reparación de teléfonos celulares.

## 2. Ubicación y sucursales

**Pregunta 2. ¿Cuántos puestos tiene el negocio actualmente?**

Respuesta: Actualmente el negocio opera en dos puestos físicos:
- Puesto 98, ubicado en el Pasillo Tarija.
- Puesto 121, ubicado en el Pasillo Las Américas.

## 3. Identificación de locales

**Pregunta 3. ¿Cómo se identifican y organizan estos puestos?**

Respuesta: Cada puesto se identifica por su número de local comercial (`98` y `121`), un nombre de referencia y el sector o pasillo específico en el que se encuentra ubicado dentro del centro comercial.

## 4. Registro actual de la información y problemas detectados

**Pregunta 4. ¿Cómo se registran actualmente las ventas diarias y los trabajos del taller?**

Respuesta: Todo se registra de manera manual utilizando cuadernos de apuntes o mensajes de WhatsApp desde el celular.

**Pregunta 5. ¿Utilizan actualmente algún sistema informático o base de datos para controlar el negocio?**

Respuesta: No. No se cuenta con ningún sistema digital para controlar el inventario, las ventas ni las reparaciones.

**Pregunta 6. ¿Qué problemas les ocasiona llevar el registro en cuadernos y WhatsApp?**

Respuesta: Genera desorden al momento de hacer el cierre total de la semana. Entre los problemas principales están:
- Es difícil cruzar la información para saber cuánto dinero ingresó exactamente por vender accesorios y cuánto ingresó por mano de obra de reparaciones.
- Al sacar cuentas para pagar a proveedores o reponer mercadería, a veces no cuadran los montos debido a apuntes incompletos.
- Se pierden u olvidan los datos de respaldo cuando se vende un teléfono de alto valor que cuenta con garantía.

## 5. Productos e inventario general

**Pregunta 7. ¿Qué tipos de productos se comercializan en la vitrina general?**

Respuesta: Se comercializan artículos variados de alta rotación divididos en tres categorías principales:
- **Accesorios:** Cables de carga, cargadores de carga rápida, fundas y protectores.
- **Cosmética:** Productos de maquillaje de alta demanda (como rímel y sets de labiales).
- **Celulares:** Teléfonos móviles nuevos en caja sellada y equipos semi-nuevos.

**Pregunta 8. ¿Qué datos se necesitan registrar de cada producto del catálogo?**

Respuesta: No manejamos códigos de barras externos; los diferenciamos por su nombre descriptivo, su categoría, su precio de venta y la cantidad disponible en stock. El identificador numérico (`id_producto`) lo asigna el sistema internamente para evitar errores al registrar una venta.

**Pregunta 9. ¿Cómo se evitan errores humanos al registrar precios o cantidades de mercadería?**

Respuesta: En el sistema no debe permitirse bajo ninguna circunstancia registrar productos con precios negativos ni cantidades de stock menores a cero. Cada vez que se concreta una venta, el stock debe descontarse de forma atómica.

**Pregunta 10. ¿Cuándo consideran que un producto se está agotando y necesita reposición?**

Respuesta: Cuando un producto tiene menos de 5 unidades disponibles en vitrina. Se necesita un reporte directo (`V_STOCK_CRITICO`) que muestre únicamente esos artículos en estado crítico para realizar el pedido a los proveedores.

## 6. Control de celulares e IMEI

**Pregunta 11. ¿Los teléfonos celulares se registran igual que un accesorio común como un cable o una funda?**

Respuesta: No. Aunque forman parte del catálogo de productos, los celulares son equipos de alto valor económico y requieren un control individualizado por motivos de garantía y seguridad.

**Pregunta 12. ¿Qué datos adicionales son obligatorios al registrar un celular para la venta?**

Respuesta: Además de su nombre, precio y stock en el catálogo general, de cada celular se debe registrar obligatoriamente:
- El código **IMEI** único del aparato (15 dígitos).
- El **estado físico** del equipo (por ejemplo, si está nuevo en caja sellada o semi-nuevo con algún detalle).
- El **estado de red** (si el equipo está liberado para cualquier operadora o restringido/homologado para una empresa como Entel).

**Pregunta 13. ¿Por qué no se anota el IMEI en la misma lista de los demás productos?**

Respuesta: Porque los cables, fundas y maquillajes no tienen código IMEI ni estado de red. Si se pusieran en la misma tabla, casi todo el inventario tendría esas columnas vacías (`NULL`) innecesariamente.

## 7. Clientes y modalidad de venta rápida

**Pregunta 14. ¿Qué información se registra de los clientes?**

Respuesta: Cuando se registra a un cliente, se solicita su nombre completo y un único número de teléfono celular principal para poder comunicarse con él.

**Pregunta 15. ¿Se le pide nombre y teléfono a todas las personas que compran en el puesto?**

Respuesta: No. En el trabajo diario de mostrador no se le pide nombre ni teléfono a una persona que solo entra a comprar un cable o un accesorio económico, porque eso demora la atención. Para esos casos se maneja la figura del **"Cliente Ocasional (Venta Rápida)"** (`id_cliente = 0`) que permite registrar el ingreso al instante. En cambio, sí es obligatorio registrar el nombre y teléfono real cuando el cliente compra un equipo costoso o deja un celular en el servicio técnico.

## 8. Proceso de ventas en mostrador

**Pregunta 16. ¿Cómo se realiza una venta cuando el cliente lleva varios artículos?**

Respuesta: Si el cliente lleva varios artículos en una misma compra, no repetimos los datos del comprador ni la fecha en cada renglón. Se divide en dos partes:
- **La cabecera (`VENTA`):** Es el recibo general que registra la fecha, el método de pago, el total a pagar, quién compró y en qué puesto se realizó.
- **El detalle (`DETALLE_VENTA`):** Es el desglose de los productos vendidos con su cantidad y su subtotal.

**Pregunta 17. ¿Qué control necesitan tener sobre el dinero que genera cada puesto?**

Respuesta: Se necesita consultar de forma directa cuánto dinero en total y cuántas ventas ha generado el **Puesto 98 (Pasillo Tarija)** frente al **Puesto 121 (Pasillo Las Américas)** mediante la vista de rendimiento por sucursal (`V_INGRESOS_PUESTOS`).

## 9. Taller de servicio técnico y reparaciones

**Pregunta 18. ¿Por qué los trabajos de servicio técnico se registran por separado de las ventas normales?**

Respuesta: Porque una reparación es un cobro por mano de obra y revisión técnica, no la salida de un artículo de vitrina. Tiene su propio flujo de trabajo y requiere registrar el diagnóstico y el estado del arreglo.

**Pregunta 19. ¿Qué datos se anotan cuando un cliente deja un teléfono para reparar?**

Respuesta: Se registra un número único de servicio, la fecha de ingreso, qué cliente dejó el equipo, en cuál de los puestos fue atendido, la descripción específica de la falla reportada (por ejemplo, cambio de pin de carga o módulo de pantalla), el costo acordado y el estado actual (`En revisión`, `Listo` o `Completado`).

## 10. Depuración de registros y medios de pago

**Pregunta 20. ¿Qué hacen si se equivocan al anotar una venta o si el cliente pide una devolución?**

Respuesta: Si ocurre un error o una anulación, el sistema debe deshacer la operación sin dejar registros huérfanos ni descuadrar el inventario, respaldándose en transacciones seguras y reglas de borrado en cascada.

**Pregunta 21. ¿Qué formas de pago aceptan en los puestos?**

Respuesta: Aceptamos pagos en **Efectivo**, cobros rápidos mediante código **QR** y **Transferencia** bancaria según la comodidad del cliente.