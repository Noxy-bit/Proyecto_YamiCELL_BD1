-- ====================================================
-- SISTEMA DE GESTIÓN COMERCIAL Y TÉCNICA: YAMICELL
-- Puestos 98 (Pasillo Tarija) y 121 (Pasillo Las Américas)
-- ====================================================

PRAGMA foreign_keys = ON; -- Forzar integridad referencial en SQLite

DROP VIEW IF EXISTS V_STOCK_CRITICO;
DROP VIEW IF EXISTS V_INGRESOS_PUESTOS;
DROP VIEW IF EXISTS V_HISTORIAL_REPARACIONES;

DROP TABLE IF EXISTS CELULAR;
DROP TABLE IF EXISTS DETALLE_VENTA;
DROP TABLE IF EXISTS SERVICIO_TECNICO;
DROP TABLE IF EXISTS VENTA;
DROP TABLE IF EXISTS PRODUCTO;
DROP TABLE IF EXISTS PUESTO;
DROP TABLE IF EXISTS CLIENTE;

-- ====================================================
-- 1. DDL: CREACIÓN DE TABLAS NORMALIZADAS (3FN)
-- ====================================================

-- 1.1 TABLA CLIENTE (Registro de compradores y cliente genérico id = 0)
CREATE TABLE CLIENTE (
    id_cliente INT PRIMARY KEY,
    nombre_completo VARCHAR(100) NOT NULL,
    telefono VARCHAR(15) NOT NULL DEFAULT 'S/N'
);

-- 1.2 TABLA PUESTO (Sucursales físicas de YamiCELL)
CREATE TABLE PUESTO (
    id_puesto INT PRIMARY KEY,
    nombre_puesto VARCHAR(50) NOT NULL,
    sector VARCHAR(50) NOT NULL CHECK (sector IN ('Pasillo Tarija', 'Pasillo Américas', 'Otro'))
);

-- 1.3 TABLA PRODUCTO (Inventario general de vitrina)
CREATE TABLE PRODUCTO (
    id_producto INT PRIMARY KEY,
    nombre_producto VARCHAR(100) NOT NULL,
    categoria VARCHAR(50) NOT NULL CHECK (categoria IN ('Accesorios', 'Cosmética', 'Celulares')),
    precio DECIMAL(10, 2) NOT NULL CHECK (precio >= 0),
    stock INT NOT NULL DEFAULT 0 CHECK (stock >= 0)
);

-- 1.4 TABLA VENTA (Cabecera del recibo con método de pago)
CREATE TABLE VENTA (
    id_venta INT PRIMARY KEY,
    fecha DATE NOT NULL,
    metodo_pago VARCHAR(20) NOT NULL DEFAULT 'EFECTIVO' CHECK (metodo_pago IN ('EFECTIVO', 'QR', 'TRANSFERENCIA')),
    total DECIMAL(10, 2) NOT NULL CHECK (total >= 0),
    id_cliente INT NOT NULL,
    id_puesto INT NOT NULL,
    FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id_cliente) ON DELETE CASCADE,
    FOREIGN KEY (id_puesto) REFERENCES PUESTO(id_puesto) ON DELETE CASCADE
);

-- 1.5 TABLA SERVICIO_TECNICO (Recepción y reparaciones de equipos)
CREATE TABLE SERVICIO_TECNICO (
    id_servicio INT PRIMARY KEY,
    fecha_ingreso DATE NOT NULL DEFAULT CURRENT_DATE,
    falla_reportada VARCHAR(255) NOT NULL,
    costo DECIMAL(10, 2) NOT NULL CHECK (costo >= 0),
    estado VARCHAR(30) NOT NULL DEFAULT 'Completado' CHECK (estado IN ('En revisión', 'Listo', 'Completado')),
    id_cliente INT NOT NULL,
    id_puesto INT NOT NULL,
    FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id_cliente) ON DELETE CASCADE,
    FOREIGN KEY (id_puesto) REFERENCES PUESTO(id_puesto) ON DELETE CASCADE
);

-- 1.6 TABLA DETALLE_VENTA (Desglose de productos por recibo)
CREATE TABLE DETALLE_VENTA (
    id_detalle INT PRIMARY KEY,
    cantidad INT NOT NULL CHECK (cantidad > 0),
    subtotal DECIMAL(10, 2) NOT NULL CHECK (subtotal >= 0),
    id_venta INT NOT NULL,
    id_producto INT NOT NULL,
    FOREIGN KEY (id_venta) REFERENCES VENTA(id_venta) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES PRODUCTO(id_producto) ON DELETE CASCADE
);

-- 1.7 TABLA CELULAR (Especialización 1 a 0..1 para equipos con IMEI único)
CREATE TABLE CELULAR (
    imei VARCHAR(50) PRIMARY KEY,
    estado_fisico VARCHAR(50) NOT NULL,
    estado_red VARCHAR(50) NOT NULL,
    id_producto INT NOT NULL UNIQUE,
    FOREIGN KEY (id_producto) REFERENCES PRODUCTO(id_producto) ON DELETE CASCADE
);

-- ====================================================
-- 2. DML: POBLADO DE DATOS DE PRUEBA (SIN NULOS INNECESARIOS)
-- ====================================================

INSERT INTO PUESTO (id_puesto, nombre_puesto, sector) VALUES 
(98, 'Puesto 98 - Tarija', 'Pasillo Tarija'),
(121, 'Puesto 121 - Américas', 'Pasillo Américas');

INSERT INTO PRODUCTO (id_producto, nombre_producto, categoria, precio, stock) VALUES 
(101, 'Cable Tipo C Carga Rápida 25W', 'Accesorios', 25.00, 48),
(102, 'Funda Silicona iPhone 13', 'Accesorios', 35.00, 29),
(103, 'Rímel Maybelline Waterproof', 'Cosmética', 45.00, 18),
(104, 'Samsung Galaxy A54 5G', 'Celulares', 2100.00, 2),
(105, 'iPhone 11 128GB (Semi-nuevo)', 'Celulares', 1800.00, 1),
(106, 'Cargador Original Samsung 45W', 'Accesorios', 120.00, 4),
(107, 'Set Labiales Matte Bissú', 'Cosmética', 65.00, 3),
(108, 'Xiaomi Redmi Note 13 Pro', 'Celulares', 1950.00, 2);

INSERT INTO CLIENTE (id_cliente, nombre_completo, telefono) VALUES 
(0, 'Cliente Ocasional (Venta Rápida)', 'S/N'),
(1, 'Carlos Mendoza', '77889900'),
(2, 'María Fernanda López', '71234567'),
(3, 'Juan Pérez', '76543210'),
(4, 'Ana Gabriela Suárez', '70011223');

INSERT INTO CELULAR (imei, estado_fisico, estado_red, id_producto) VALUES 
('354123098765432', 'Nuevo en caja sellada', 'Liberado', 104),
('358765432109876', 'Usado - Batería 89%', 'Homologado Entel', 105),
('869456123078951', 'Nuevo en caja sellada', 'Liberado Dual SIM', 108);

-- Ventas iniciales para alimentar reportes y funciones de ventana
INSERT INTO VENTA (id_venta, fecha, metodo_pago, total, id_cliente, id_puesto) VALUES 
(1001, '2026-09-25', 'EFECTIVO', 25.00, 0, 98),
(1002, '2026-09-26', 'QR', 90.00, 2, 121),
(1003, '2026-09-28', 'TRANSFERENCIA', 2125.00, 1, 98),
(1004, '2026-10-02', 'QR', 120.00, 1, 121),
(1005, '2026-10-05', 'EFECTIVO', 65.00, 2, 98);

INSERT INTO DETALLE_VENTA (id_detalle, cantidad, subtotal, id_venta, id_producto) VALUES 
(1, 1, 25.00, 1001, 101),
(2, 2, 90.00, 1002, 103),
(3, 1, 2100.00, 1003, 104),
(4, 1, 25.00, 1003, 101),
(5, 1, 120.00, 1004, 106),
(6, 1, 65.00, 1005, 107);

INSERT INTO SERVICIO_TECNICO (id_servicio, fecha_ingreso, falla_reportada, costo, estado, id_cliente, id_puesto) VALUES 
(501, '2026-09-25', 'Pin de carga roto - Cambio de placa inferior', 80.00, 'Completado', 2, 98),
(502, '2026-10-01', 'Cambio de módulo de pantalla OLED Samsung', 450.00, 'Completado', 1, 98),
(503, '2026-10-06', 'Limpieza ultrasónica por humedad y flasheo', 150.00, 'En revisión', 4, 121);

-- ====================================================
-- 3. TRANSACCIÓN ATÓMICA (PROPIEDADES ACID - UNIDAD 4)
-- ====================================================
BEGIN;

    INSERT INTO VENTA (id_venta, fecha, metodo_pago, total, id_cliente, id_puesto) 
    VALUES (1006, '2026-10-10', 'QR', 35.00, 3, 121);

    INSERT INTO DETALLE_VENTA (id_detalle, cantidad, subtotal, id_venta, id_producto) 
    VALUES (7, 1, 35.00, 1006, 102);

    UPDATE PRODUCTO 
    SET stock = stock - 1 
    WHERE id_producto = 102;

COMMIT;
-- Nota para defensa oral: Si el stock fuese insuficiente (violando CHECK stock >= 0),
-- se ejecuta ROLLBACK; para deshacer el INSERT en VENTA y DETALLE_VENTA.

-- ====================================================
-- 4. VISTAS CORPORATIVAS DEL SISTEMA (UNIDAD 4)
-- ====================================================

-- Vista 1: Alerta de Inventario Crítico (Stock menor a 5 unidades)
CREATE VIEW V_STOCK_CRITICO AS
SELECT id_producto, nombre_producto, categoria, precio, stock 
FROM PRODUCTO 
WHERE stock < 5;

-- Vista 2: Rendimiento Financiero por Puesto
CREATE VIEW V_INGRESOS_PUESTOS AS
SELECT 
    p.id_puesto,
    p.nombre_puesto, 
    p.sector,
    COUNT(v.id_venta) AS cantidad_ventas,
    SUM(v.total) AS total_ventas
FROM PUESTO p
LEFT JOIN VENTA v ON p.id_puesto = v.id_puesto
GROUP BY p.id_puesto, p.nombre_puesto, p.sector;

-- Vista 3: Historial Detallado de Servicios Técnicos
CREATE VIEW V_HISTORIAL_REPARACIONES AS
SELECT 
    s.id_servicio,
    s.fecha_ingreso,
    c.nombre_completo AS cliente, 
    p.nombre_puesto AS puesto,
    s.falla_reportada, 
    s.costo,
    s.estado
FROM SERVICIO_TECNICO s
INNER JOIN CLIENTE c ON c.id_cliente = s.id_cliente
INNER JOIN PUESTO p ON p.id_puesto = s.id_puesto;

-- ====================================================
-- 5. CONSULTAS AVANZADAS (SOLUCIONARIO ING. PEINADO)
-- ====================================================

-- Consulta 1 (Estilo Solución 3 del Ing.): Estadísticas de inventario por categoría
SELECT 
    categoria,
    COUNT(*) AS total_productos,
    ROUND(AVG(precio), 2) AS precio_promedio,
    MIN(precio) AS precio_minimo,
    MAX(precio) AS precio_maximo
FROM PRODUCTO
GROUP BY categoria;

-- Consulta 2 (Estilo Solución 4 del Ing.): Reporte completo de ventas con INNER JOIN
SELECT 
    v.id_venta,
    v.fecha,
    c.nombre_completo AS cliente,
    p.nombre_producto AS producto,
    dv.cantidad,
    dv.subtotal,
    v.metodo_pago,
    pu.nombre_puesto AS puesto
FROM VENTA v
INNER JOIN CLIENTE c ON v.id_cliente = c.id_cliente
INNER JOIN DETALLE_VENTA dv ON v.id_venta = dv.id_venta
INNER JOIN PRODUCTO p ON dv.id_producto = p.id_producto
INNER JOIN PUESTO pu ON v.id_puesto = pu.id_puesto
ORDER BY v.fecha DESC;

-- Consulta 3 (Estilo Solución 6 del Ing.): Clientes destacados con GROUP BY y HAVING
SELECT 
    c.id_cliente,
    c.nombre_completo AS cliente,
    COUNT(v.id_venta) AS compras_realizadas,
    SUM(v.total) AS total_gastado
FROM CLIENTE c
INNER JOIN VENTA v ON c.id_cliente = v.id_cliente
GROUP BY c.id_cliente, c.nombre_completo
HAVING SUM(v.total) > 100.00
ORDER BY total_gastado DESC;

-- Consulta 4 (Estilo Solución 7 del Ing.): Subconsulta de productos sobre el precio promedio
SELECT 
    id_producto,
    nombre_producto, 
    categoria, 
    precio 
FROM PRODUCTO
WHERE precio > (SELECT AVG(precio) FROM PRODUCTO)
ORDER BY precio DESC;

-- Consulta 5 (Estilo Solución 8 del Ing.): Productos sin ventas (LEFT JOIN y NOT EXISTS)
-- Opción A: Usando LEFT JOIN
SELECT 
    p.id_producto,
    p.nombre_producto,
    p.categoria,
    p.stock
FROM PRODUCTO p
LEFT JOIN DETALLE_VENTA dv ON p.id_producto = dv.id_producto
WHERE dv.id_detalle IS NULL;

-- Opción B: Usando subconsulta correlacionada NOT EXISTS
SELECT 
    p.id_producto,
    p.nombre_producto,
    p.categoria,
    p.stock
FROM PRODUCTO p
WHERE NOT EXISTS (
    SELECT 1 
    FROM DETALLE_VENTA dv 
    WHERE dv.id_producto = p.id_producto
);

-- Consulta 6 (Estilo Solución 9 del Ing.): Top Producto más vendido por categoría (CTE + DENSE_RANK)
WITH UnidadesPorProducto AS (
    SELECT 
        p.categoria,
        p.nombre_producto AS producto,
        SUM(dv.cantidad) AS total_unidades,
        DENSE_RANK() OVER (
            PARTITION BY p.categoria 
            ORDER BY SUM(dv.cantidad) DESC
        ) AS ranking
    FROM DETALLE_VENTA dv
    INNER JOIN PRODUCTO p ON dv.id_producto = p.id_producto
    GROUP BY p.categoria, p.id_producto, p.nombre_producto
)
SELECT categoria, producto, total_unidades
FROM UnidadesPorProducto
WHERE ranking = 1;

-- Consulta 7 (Estilo Solución 10 del Ing.): Acumulado histórico (Running Total) por cliente
SELECT 
    v.id_venta,
    v.fecha,
    c.nombre_completo AS cliente,
    v.total AS monto_venta,
    SUM(v.total) OVER (
        PARTITION BY v.id_cliente 
        ORDER BY v.fecha ASC, v.id_venta ASC
    ) AS acumulado_cliente
FROM VENTA v
INNER JOIN CLIENTE c ON v.id_cliente = c.id_cliente
ORDER BY c.id_cliente, v.fecha ASC;