-- ====================================================
-- SISTEMA DE GESTION YAMICELL 
-- ====================================================

PRAGMA foreign_keys = ON; -- Forzar llaves foráneas en SQLite (Requerido)

DROP TABLE IF EXISTS CELULAR;
DROP TABLE IF EXISTS DETALLE_VENTA;
DROP TABLE IF EXISTS SERVICIO_TECNICO;
DROP TABLE IF EXISTS VENTA;
DROP TABLE IF EXISTS PRODUCTO;
DROP TABLE IF EXISTS PUESTO;
DROP TABLE IF EXISTS CLIENTE;

-- ====================================================
-- DDL: CREACIÓN DE TABLAS
-- ====================================================

-- 1. TABLA CLIENTE (El origen de los ingresos)
CREATE TABLE CLIENTE (
    id_cliente INT PRIMARY KEY,
    nombre_completo VARCHAR(100) NOT NULL,
    telefono VARCHAR(15)
);

-- 2. TABLA PUESTO (Las sucursales físicas)
CREATE TABLE PUESTO (
    id_puesto INT PRIMARY KEY,
    nombre_puesto VARCHAR(50) NOT NULL,
    sector VARCHAR(50) CHECK (sector IN ('Pasillo Tarija', 'Pasillo Américas', 'Otro')) -- Restricción de dominio
);

-- 3. TABLA PRODUCTO (El inventario general de la vitrina)
CREATE TABLE PRODUCTO (
    id_producto INT PRIMARY KEY,
    nombre_producto VARCHAR(100) NOT NULL,
    categoria VARCHAR(50),
    precio DECIMAL(10, 2) NOT NULL CHECK (precio >= 0), -- Seguridad: No precios negativos
    stock INT NOT NULL DEFAULT 0 CHECK (stock >= 0)     -- Seguridad: No stock negativo
);

-- 4. TABLA VENTA (El recibo físico)
CREATE TABLE VENTA (
    id_venta INT PRIMARY KEY,
    fecha DATE NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    id_cliente INT,
    id_puesto INT,
    FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id_cliente) ON DELETE CASCADE,
    FOREIGN KEY (id_puesto) REFERENCES PUESTO(id_puesto) ON DELETE CASCADE
);

-- 5. TABLA SERVICIO_TECNICO (Los arreglos del puesto 99)
CREATE TABLE SERVICIO_TECNICO (
    id_servicio INT PRIMARY KEY,
    falla_reportada VARCHAR(255) NOT NULL,
    costo DECIMAL(10, 2) NOT NULL,
    id_cliente INT,
    id_puesto INT,
    FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id_cliente) ON DELETE CASCADE,
    FOREIGN KEY (id_puesto) REFERENCES PUESTO(id_puesto) ON DELETE CASCADE
);

-- 6. TABLA DETALLE_VENTA (El carrito de compras)
CREATE TABLE DETALLE_VENTA (
    id_detalle INT PRIMARY KEY,
    cantidad INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    id_venta INT,
    id_producto INT,
    FOREIGN KEY (id_venta) REFERENCES VENTA(id_venta) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES PRODUCTO(id_producto) ON DELETE CASCADE
);

-- 7. TABLA CELULAR (Equipos de alto valor con IMEI)
CREATE TABLE CELULAR (
    imei VARCHAR(50) PRIMARY KEY,
    estado_fisico VARCHAR(50),
    estado_red VARCHAR(50),
    id_producto INT UNIQUE,
    FOREIGN KEY (id_producto) REFERENCES PRODUCTO(id_producto) ON DELETE CASCADE
);
-- ====================================================
-- DATOS DE PRUEBA (Para validar consultas y reportes)
-- ====================================================

-- Insertar los puestos físicos (Pasillo Tarija y Américas)
INSERT INTO PUESTO (id_puesto, nombre_puesto, sector) VALUES 
(98, 'Puesto Principal Tarija', 'Pasillo Tarija'),
(121, 'Sucursal Américas', 'Pasillo Américas');

-- Insertar clientes de prueba
INSERT INTO CLIENTE (id_cliente, nombre_completo, telefono) VALUES 
(1, 'Carlos Mendoza', '77889900'),
(2, 'Maria Fernanda Lopez', '71234567'),
(3, 'Juan Perez', '76543210');

-- Insertar el inventario (Productos generales y de vitrina)
INSERT INTO PRODUCTO (id_producto, nombre_producto, categoria, precio, stock) VALUES 
(101, 'Cable Tipo C Carga Rápida', 'Accesorios', 25.00, 50),
(102, 'Funda de Silicona iPhone 13', 'Accesorios', 35.00, 30),
(103, 'Rímel Maybelline Waterproof', 'Cosmética', 45.00, 20),
(104, 'Samsung Galaxy A54', 'Celulares', 2100.00, 2),
(105, 'iPhone 11 (Usado)', 'Celulares', 1800.00, 1);

-- Registrar los IMEI de los celulares (Solo los id_producto 104 y 105)
INSERT INTO CELULAR (imei, estado_fisico, estado_red, id_producto) VALUES 
('354123098765432', 'Nuevo en caja', 'Liberado', 104),
('358765432109876', 'Usado - Pantalla rayada', 'Entel', 105);

-- Registrar una venta en el Puesto 98 (El cliente 1 compra un cable)
INSERT INTO VENTA (id_venta, fecha, total, id_cliente, id_puesto) VALUES 
(1001, '2026-09-25', 25.00, 1, 98);

-- Detalle de esa venta (1 cable)
INSERT INTO DETALLE_VENTA (id_detalle, cantidad, subtotal, id_venta, id_producto) VALUES 
(1, 1, 25.00, 1001, 101);

-- Registrar un servicio técnico en el Puesto 98 (El cliente 2 cambia pin de carga)
INSERT INTO SERVICIO_TECNICO (id_servicio, falla_reportada, costo, id_cliente, id_puesto) VALUES 
(501, 'Pin de carga roto - Cambio de placa inferior', 80.00, 2, 98);
-- ====================================================
-- CONSULTAS AVANZADAS Y TRANSACCIONES (UNIDAD 4)
-- ====================================================

-- 1. JOIN con alias claros (Requisito Unidad 4)
-- Muestra el detalle de qué cliente compró qué producto y en qué puesto.
SELECT 
    v.fecha,
    c.nombre_completo AS Cliente,
    p.nombre_producto AS Producto,
    dv.cantidad,
    dv.subtotal,
    pu.nombre_puesto AS Puesto
FROM VENTA v
JOIN CLIENTE c ON v.id_cliente = c.id_cliente
JOIN DETALLE_VENTA dv ON v.id_venta = dv.id_venta
JOIN PRODUCTO p ON dv.id_producto = p.id_producto
JOIN PUESTO pu ON v.id_puesto = pu.id_puesto;

-- 2. LEFT JOIN (Requisito Unidad 4)
-- Encuentra productos del inventario que NUNCA se han vendido (útil para auditoría).
SELECT 
    p.id_producto,
    p.nombre_producto,
    p.stock
FROM PRODUCTO p
LEFT JOIN DETALLE_VENTA dv ON p.id_producto = dv.id_producto
WHERE dv.id_detalle IS NULL;

-- 3. Subconsulta (Requisito Unidad 4)
-- Muestra los productos cuyo precio es mayor al precio promedio de toda la tienda.
SELECT 
    nombre_producto, 
    categoria, 
    precio 
FROM PRODUCTO
WHERE precio > (SELECT AVG(precio) FROM PRODUCTO);

-- 4. Transacción Segura (Requisito Unidad 4)
-- Simula una venta y actualiza el stock al mismo tiempo. Si algo falla, se hace ROLLBACK.
BEGIN; -- Inicia la transacción

    -- A. Registramos una nueva venta rápida
    INSERT INTO VENTA (id_venta, fecha, total, id_cliente, id_puesto) 
    VALUES (1002, '2026-09-25', 35.00, 3, 121);

    -- B. Registramos el detalle (Funda de silicona)
    INSERT INTO DETALLE_VENTA (id_detalle, cantidad, subtotal, id_venta, id_producto) 
    VALUES (2, 1, 35.00, 1002, 102);

    -- C. Descontamos el producto del inventario
    UPDATE PRODUCTO 
    SET stock = stock - 1 
    WHERE id_producto = 102;

COMMIT; -- Si todo sale bien, guardamos los cambios permanentemente.
-- ====================================================
-- REQUERIMIENTO 7: VISTAS ÚTILES PARA EL SISTEMA
-- ====================================================

-- Vista 1: Inventario crítico (Productos con menos de 5 en stock)
CREATE VIEW V_STOCK_CRITICO AS
SELECT id_producto, nombre_producto, stock 
FROM PRODUCTO WHERE stock < 5;

-- Vista 2: Rendimiento de Puestos (Cuánto dinero ha generado cada puesto)
CREATE VIEW V_INGRESOS_PUESTOS AS
SELECT p.nombre_puesto, SUM(v.total) as total_ventas
FROM PUESTO p
JOIN VENTA v ON p.id_puesto = v.id_puesto
GROUP BY p.nombre_puesto;

-- Vista 3: Historial de Servicios Técnicos de Clientes
CREATE VIEW V_HISTORIAL_REPARACIONES AS
SELECT c.nombre_completo, s.falla_reportada, s.costo
FROM CLIENTE c
JOIN SERVICIO_TECNICO s ON c.id_cliente = s.id_cliente;