-- ============================================================================
-- SISTEMA WEB YAMICELL — INF-312 BASE DE DATOS I
-- Script maestro DDL + DML (3FN): 11 tablas, vistas, triggers, índices, seed
-- Puesto 98 - Pasillo Tarija  |  Puesto 121 - Pasillo Las Américas
-- Motor: SQLite 3 (compatible con PDO). Moneda: Bolivianos (Bs.)
-- ============================================================================

PRAGMA foreign_keys = ON;

DROP VIEW   IF EXISTS vw_inventario_consolidado_puestos;
DROP VIEW   IF EXISTS vw_rentabilidad_ventas_detalle;
DROP VIEW   IF EXISTS vw_alertas_reposicion;
DROP TRIGGER IF EXISTS trg_validar_stock_no_negativo;
DROP TRIGGER IF EXISTS trg_actualizar_timestamp_inventario;
DROP TRIGGER IF EXISTS trg_proteger_kardex_inmutable;
DROP TABLE  IF EXISTS kardex_movimientos;
DROP TABLE  IF EXISTS traspasos_sucursal;
DROP TABLE  IF EXISTS detalle_venta;
DROP TABLE  IF EXISTS ventas;
DROP TABLE  IF EXISTS inventario_sucursal;
DROP TABLE  IF EXISTS productos;
DROP TABLE  IF EXISTS usuarios;
DROP TABLE  IF EXISTS clientes;
DROP TABLE  IF EXISTS proveedores;
DROP TABLE  IF EXISTS categorias;
DROP TABLE  IF EXISTS sucursales;

-- ============================================================================
-- 1. DDL: 11 TABLAS NORMALIZADAS EN 3RA FORMA NORMAL (3FN)
-- ============================================================================

CREATE TABLE sucursales (
    id_sucursal       INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo_puesto     VARCHAR(20)  NOT NULL UNIQUE,
    nombre_sucursal   VARCHAR(100) NOT NULL,
    pasillo_ubicacion VARCHAR(100) NOT NULL,
    encargado         VARCHAR(100) NOT NULL,
    telefono          VARCHAR(20),
    estado            VARCHAR(10)  NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO','INACTIVO'))
);

CREATE TABLE categorias (
    id_categoria     INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_categoria VARCHAR(80) NOT NULL UNIQUE,
    descripcion      TEXT
);

CREATE TABLE proveedores (
    id_proveedor    INTEGER PRIMARY KEY AUTOINCREMENT,
    razon_social    VARCHAR(120) NOT NULL,
    nit_ci          VARCHAR(20)  UNIQUE,
    contacto_nombre VARCHAR(100),
    telefono        VARCHAR(20),
    ciudad_origen   VARCHAR(80)
);

CREATE TABLE clientes (
    id_cliente     INTEGER PRIMARY KEY AUTOINCREMENT,
    ci_nit         VARCHAR(20) UNIQUE,
    nombre_completo VARCHAR(120) NOT NULL,
    tipo_cliente   VARCHAR(20) NOT NULL DEFAULT 'CLIENTE_FINAL'
                   CHECK (tipo_cliente IN ('TECNICO_AFILIADO','MAYORISTA','CLIENTE_FINAL')),
    telefono       VARCHAR(20),
    fecha_registro TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE usuarios (
    id_usuario    INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_usuario VARCHAR(60) NOT NULL UNIQUE,
    nombre_completo VARCHAR(120) NOT NULL,
    rol           VARCHAR(20) NOT NULL CHECK (rol IN ('ADMIN','VENDEDOR','ALMACENERO')),
    id_sucursal   INTEGER REFERENCES sucursales(id_sucursal),
    estado        VARCHAR(10) NOT NULL DEFAULT 'ACTIVO'
);

CREATE TABLE productos (
    id_producto        INTEGER PRIMARY KEY AUTOINCREMENT,
    sku                VARCHAR(30) NOT NULL UNIQUE,
    nombre_producto    VARCHAR(150) NOT NULL,
    marca_compatible   VARCHAR(60),
    modelo_compatible  VARCHAR(60),
    id_categoria       INTEGER NOT NULL REFERENCES categorias(id_categoria),
    id_proveedor       INTEGER REFERENCES proveedores(id_proveedor),
    costo_compra       NUMERIC(10,2) NOT NULL CHECK (costo_compra >= 0),
    precio_tecnico     NUMERIC(10,2) NOT NULL CHECK (precio_tecnico >= 0),
    precio_publico     NUMERIC(10,2) NOT NULL CHECK (precio_publico >= 0),
    stock_minimo_alerta INTEGER NOT NULL DEFAULT 5 CHECK (stock_minimo_alerta >= 0),
    estado             VARCHAR(10) NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO','DESCONTINUADO'))
);

CREATE TABLE inventario_sucursal (
    id_inventario        INTEGER PRIMARY KEY AUTOINCREMENT,
    id_producto          INTEGER NOT NULL REFERENCES productos(id_producto),
    id_sucursal          INTEGER NOT NULL REFERENCES sucursales(id_sucursal),
    stock_actual         INTEGER NOT NULL DEFAULT 0 CHECK (stock_actual >= 0),
    stock_minimo_alerta  INTEGER NOT NULL DEFAULT 5 CHECK (stock_minimo_alerta >= 0),
    ultima_actualizacion TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    UNIQUE (id_producto, id_sucursal)
);

CREATE TABLE ventas (
    id_venta       INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo_recibo  VARCHAR(20) NOT NULL UNIQUE,
    fecha_hora     TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    id_sucursal    INTEGER NOT NULL REFERENCES sucursales(id_sucursal),
    id_cliente     INTEGER NOT NULL REFERENCES clientes(id_cliente),
    id_usuario     INTEGER REFERENCES usuarios(id_usuario),
    metodo_pago    VARCHAR(20) NOT NULL CHECK (metodo_pago IN ('EFECTIVO','QR_SIMPLE','TRANSFERENCIA','MIXTO')),
    tipo_precio    VARCHAR(10) NOT NULL DEFAULT 'PUBLICO' CHECK (tipo_precio IN ('PUBLICO','TECNICO')),
    descuento_bs   NUMERIC(10,2) NOT NULL DEFAULT 0 CHECK (descuento_bs >= 0),
    subtotal_bruto NUMERIC(10,2) NOT NULL CHECK (subtotal_bruto >= 0),
    total_neto     NUMERIC(10,2) NOT NULL CHECK (total_neto >= 0)
);

CREATE TABLE detalle_venta (
    id_detalle     INTEGER PRIMARY KEY AUTOINCREMENT,
    id_venta       INTEGER NOT NULL REFERENCES ventas(id_venta) ON DELETE CASCADE,
    id_producto    INTEGER NOT NULL REFERENCES productos(id_producto),
    cantidad       INTEGER NOT NULL CHECK (cantidad > 0),
    precio_unitario NUMERIC(10,2) NOT NULL CHECK (precio_unitario >= 0),
    costo_unitario  NUMERIC(10,2) NOT NULL DEFAULT 0 CHECK (costo_unitario >= 0),
    subtotal       NUMERIC(10,2) NOT NULL CHECK (subtotal >= 0)
);

CREATE TABLE traspasos_sucursal (
    id_traspaso        INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo_traspaso    VARCHAR(20) NOT NULL UNIQUE,
    fecha_hora         TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    id_producto        INTEGER NOT NULL REFERENCES productos(id_producto),
    id_sucursal_origen INTEGER NOT NULL REFERENCES sucursales(id_sucursal),
    id_sucursal_destino INTEGER NOT NULL REFERENCES sucursales(id_sucursal),
    cantidad           INTEGER NOT NULL CHECK (cantidad > 0),
    id_usuario         INTEGER REFERENCES usuarios(id_usuario),
    estado             VARCHAR(15) NOT NULL DEFAULT 'COMPLETADO' CHECK (estado IN ('COMPLETADO','ANULADO')),
    observacion        VARCHAR(200),
    CHECK (id_sucursal_origen <> id_sucursal_destino)
);

CREATE TABLE kardex_movimientos (
    id_movimiento       INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha_hora          TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    id_producto         INTEGER NOT NULL REFERENCES productos(id_producto),
    id_sucursal         INTEGER NOT NULL REFERENCES sucursales(id_sucursal),
    tipo_movimiento     VARCHAR(20) NOT NULL CHECK (tipo_movimiento IN
                        ('ENTRADA_COMPRA','SALIDA_VENTA','TRASPASO_ENVIO','TRASPASO_RECEPCION','AJUSTE_INVENTARIO')),
    documento_referencia VARCHAR(20),
    cantidad            INTEGER NOT NULL,
    stock_anterior      INTEGER NOT NULL,
    stock_resultante    INTEGER NOT NULL,
    id_usuario          INTEGER REFERENCES usuarios(id_usuario),
    detalle             VARCHAR(200)
);

-- ============================================================================
-- 2. ÍNDICES DE OPTIMIZACIÓN (EXPLAIN QUERY PLAN en la defensa)
-- ============================================================================
CREATE INDEX idx_productos_sku        ON productos(sku);
CREATE INDEX idx_productos_cat        ON productos(id_categoria);
CREATE INDEX idx_ventas_fecha_sucursal ON ventas(fecha_hora, id_sucursal);
CREATE INDEX idx_ventas_cliente       ON ventas(id_cliente);
CREATE INDEX idx_detalle_venta_venta  ON detalle_venta(id_venta);
CREATE INDEX idx_detalle_venta_prod   ON detalle_venta(id_producto);
CREATE INDEX idx_inventario_prod_suc  ON inventario_sucursal(id_producto, id_sucursal);
CREATE INDEX idx_kardex_fecha_puesto  ON kardex_movimientos(fecha_hora, id_sucursal);
CREATE INDEX idx_kardex_prod          ON kardex_movimientos(id_producto);
CREATE INDEX idx_clientes_ci_nit      ON clientes(ci_nit);

-- ============================================================================
-- 3. VISTAS SQL NATIVAS
-- ============================================================================
CREATE VIEW vw_inventario_consolidado_puestos AS
SELECT
    p.id_producto,
    p.sku,
    p.nombre_producto,
    p.marca_compatible,
    p.modelo_compatible,
    c.nombre_categoria,
    p.id_categoria,
    p.id_proveedor,
    p.stock_minimo_alerta,
    p.costo_compra,
    p.precio_tecnico,
    p.precio_publico,
    COALESCE(MAX(CASE WHEN s.codigo_puesto = 'PUESTO-98'  THEN i.stock_actual END), 0)  AS stock_puesto_98,
    COALESCE(MAX(CASE WHEN s.codigo_puesto = 'PUESTO-121' THEN i.stock_actual END), 0)  AS stock_puesto_121,
    COALESCE(SUM(i.stock_actual), 0) AS stock_total,
    ROUND(COALESCE(SUM(i.stock_actual), 0) * p.costo_compra, 2)   AS valor_total_costo_bs,
    ROUND(COALESCE(SUM(i.stock_actual), 0) * p.precio_publico, 2) AS valor_total_publico_bs
FROM productos p
INNER JOIN categorias c         ON c.id_categoria = p.id_categoria
LEFT  JOIN inventario_sucursal i ON i.id_producto = p.id_producto
LEFT  JOIN sucursales s          ON s.id_sucursal = i.id_sucursal
GROUP BY p.id_producto;

CREATE VIEW vw_rentabilidad_ventas_detalle AS
SELECT
    v.id_venta,
    v.codigo_recibo,
    v.fecha_hora,
    s.nombre_sucursal,
    s.codigo_puesto,
    cl.nombre_completo AS cliente,
    cl.tipo_cliente,
    v.metodo_pago,
    p.sku,
    p.nombre_producto,
    dv.cantidad,
    dv.precio_unitario,
    dv.costo_unitario,
    ROUND(dv.cantidad * dv.costo_unitario, 2)  AS costo_total_bs,
    ROUND(dv.cantidad * dv.precio_unitario, 2) AS ingreso_bruto_bs,
    ROUND(dv.cantidad * (dv.precio_unitario - dv.costo_unitario), 2) AS ganancia_bruta_bs
FROM detalle_venta dv
INNER JOIN ventas     v  ON v.id_venta     = dv.id_venta
INNER JOIN sucursales s  ON s.id_sucursal  = v.id_sucursal
INNER JOIN clientes   cl ON cl.id_cliente  = v.id_cliente
INNER JOIN productos  p  ON p.id_producto  = dv.id_producto;

CREATE VIEW vw_alertas_reposicion AS
SELECT
    i.id_inventario,
    s.nombre_sucursal,
    s.codigo_puesto,
    p.sku,
    p.nombre_producto,
    p.marca_compatible,
    i.stock_actual,
    i.stock_minimo_alerta,
    i.ultima_actualizacion
FROM inventario_sucursal i
INNER JOIN productos  p ON p.id_producto = i.id_producto
INNER JOIN sucursales s ON s.id_sucursal = i.id_sucursal
WHERE i.stock_actual <= i.stock_minimo_alerta;

-- ============================================================================
-- 4. TRIGGERS NATIVOS DEL MOTOR
-- ============================================================================
CREATE TRIGGER trg_validar_stock_no_negativo
BEFORE UPDATE ON inventario_sucursal
FOR EACH ROW
WHEN NEW.stock_actual < 0
BEGIN
    SELECT RAISE(ABORT, 'Integridad violada: stock_actual no puede ser negativo (trigger trg_validar_stock_no_negativo)');
END;

CREATE TRIGGER trg_actualizar_timestamp_inventario
AFTER UPDATE ON inventario_sucursal
FOR EACH ROW
WHEN NEW.stock_actual <> OLD.stock_actual
BEGIN
    UPDATE inventario_sucursal
    SET ultima_actualizacion = datetime('now','localtime')
    WHERE id_inventario = NEW.id_inventario;
END;

CREATE TRIGGER trg_proteger_kardex_inmutable
BEFORE DELETE ON kardex_movimientos
FOR EACH ROW
BEGIN
    SELECT RAISE(ABORT, 'Kardex inmutable: prohibido borrar movimientos históricos (trg_proteger_kardex_inmutable)');
END;

-- ============================================================================
-- 5. DML: POBLADO DE DEMOSTRACIÓN (datos reales de operación YamiCELL)
-- ============================================================================
INSERT INTO sucursales (codigo_puesto, nombre_sucursal, pasillo_ubicacion, encargado, telefono) VALUES
('PUESTO-98',  'Puesto 98 - Pasillo Tarija',      'Pasillo Tarija #98',       'Yami Flores',   '700-98981'),
('PUESTO-121', 'Puesto 121 - Pasillo Las Américas','Pasillo Las Américas #121','Carlos Rivera','700-12121');

INSERT INTO categorias (nombre_categoria, descripcion) VALUES
('Pantallas y Módulos Display',    'Pantallas OLED, AMOLED e Incell para telefonía móvil'),
('Baterías Internas',              'Baterías de repuesto originales y compatibles'),
('Flex y Placas de Carga',         'Flex de carga, sub-boards y placas auxiliares'),
('Herramientas y Micro-Soldadura', 'Insumos de laboratorio: flux, estaño, estaciones'),
('Cargadores y Cables Originales', 'Cargadores de carga rápida y cables certificados'),
('Protección e Hidrogel',          'Vidrios templados, hidrogel y estuches');

INSERT INTO proveedores (razon_social, nit_ci, contacto_nombre, telefono, ciudad_origen) VALUES
('Importadora TecnoZona SRL',  '3884012018', 'Luis Andrade',  '703-11223', 'Iquique / Importación'),
('Shenzhen Mobile Parts Co.',  'EXT-88001',  'Wang Li',       '+86-755-8899', 'Shenzhen / Importadora'),
('Distribuidora Paceña GSM',   '1029384021', 'Rosa Quispe',   '2-2445566', 'La Paz'),
('Repuestos SCZ Mayorista',    '4550123017', 'Marco Soliz',   '3-3447788', 'Santa Cruz');

INSERT INTO clientes (ci_nit, nombre_completo, tipo_cliente, telefono) VALUES
('0000000',  'Cliente Ocasional (Venta Rápida)', 'CLIENTE_FINAL',    'S/N'),
('5233418',  'Taller TecnoFix - Jhonny Aguilar', 'TECNICO_AFILIADO', '770-11234'),
('6885210',  'Lab MicroCell - Dra. Paola Roca',  'TECNICO_AFILIADO', '776-55678'),
('9012345',  'Comercial Aduana Norte SRL',       'MAYORISTA',        '3-3556677'),
('4567890',  'María Fernanda López',             'CLIENTE_FINAL',    '712-34567'),
('7654321',  'Juan Pérez Salvatierra',           'CLIENTE_FINAL',    '765-43210'),
('8112233',  'TecnoServicios Ríos',              'TECNICO_AFILIADO', '760-99887'),
('3344556',  'Ana Gabriela Suárez',              'CLIENTE_FINAL',    '700-11223');

INSERT INTO usuarios (nombre_usuario, nombre_completo, rol, id_sucursal) VALUES
('admin',    'Yami Flores (Dueña)',  'ADMIN',    NULL),
('vendedor98', 'Ruth Cabrera',       'VENDEDOR', 1),
('vendedor121','Diego Mercado',      'VENDEDOR', 2),
('almacen',  'Carlos Rivera',        'ALMACENERO', 2);

INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('PAN-A54-OLED','Pantalla OLED Samsung A54 5G con marco','Samsung','Galaxy A54',1,2,380.00,480.00,550.00,4);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('PAN-A32-INC','Módulo Incell Samsung A32','Samsung','Galaxy A32',1,2,210.00,270.00,320.00,4);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('PAN-IP11-INC','Pantalla Incell iPhone 11','Apple','iPhone 11',1,2,300.00,390.00,450.00,3);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('PAN-RN13-AMO','Display AMOLED Redmi Note 13 Pro','Xiaomi','Note 13 Pro',1,2,340.00,430.00,495.00,3);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('PAN-A15-OLED','Pantalla OLED Samsung A15','Samsung','Galaxy A15',1,1,260.00,330.00,380.00,4);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('PAN-MG84-INC','Módulo Incell Moto G84','Motorola','Moto G84',1,1,240.00,300.00,350.00,3);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('BAT-A54-5K','Batería interna Samsung A54 5000mAh','Samsung','Galaxy A54',2,3,90.00,130.00,160.00,5);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('BAT-IP11-3K','Batería iPhone 11 3110mAh','Apple','iPhone 11',2,3,110.00,150.00,185.00,4);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('BAT-RN13-5K','Batería Redmi Note 13 5000mAh','Xiaomi','Note 13',2,3,85.00,120.00,150.00,5);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('FLX-A54-CARGA','Flex de carga Samsung A54','Samsung','Galaxy A54',3,4,45.00,70.00,90.00,6);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('FLX-IP11-CARGA','Placa sub-board carga iPhone 11','Apple','iPhone 11',3,4,65.00,95.00,120.00,4);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('FLX-RN13-CARGA','Flex de carga Redmi Note 13','Xiaomi','Note 13',3,4,40.00,60.00,80.00,6);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('HER-FLUX-AMTECH','Flux AMTECH NC-559 micro-soldadura','AMTECH','Universal',4,2,120.00,160.00,190.00,2);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('HER-ESTANO-138','Estaño en pasta 138°C bajo punto','Mechanic','Universal',4,2,95.00,130.00,155.00,2);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('HER-MALLA-DESS','Malla desoldadora 2.0mm premium','Sunshine','Universal',4,4,15.00,25.00,35.00,8);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('CAR-SAM-45W','Cargador Samsung 45W Super Fast original','Samsung','Universal USB-C',5,3,140.00,190.00,230.00,5);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('CAR-USB-C25W','Cable Tipo C carga rápida 25W certif.','Genérico','Universal',5,3,18.00,30.00,40.00,10);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('CAR-IP20W','Cargador iPhone 20W PD original','Apple','iPhone Lightning',5,3,160.00,210.00,250.00,4);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('PRO-TEMP-A54','Vidrio templado 9D Samsung A54','Genérico','Galaxy A54',6,4,8.00,15.00,25.00,12);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('PRO-HIDRO-UNI','Lámina hidrogel universal corte','Genérico','Universal',6,4,12.00,20.00,30.00,15);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('PRO-TEMP-IP11','Vidrio templado iPhone 11 cerámico','Genérico','iPhone 11',6,4,10.00,18.00,28.00,10);
INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES
('CAM-RN13-MAIN','Cámara principal Redmi Note 13','Xiaomi','Note 13',3,1,150.00,200.00,240.00,2);

INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (1,1,6,4),
(1,2,8,4);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (2,1,10,4),
(2,2,5,4);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (3,1,4,3),
(3,2,2,3);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (4,1,7,3),
(4,2,9,3);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (5,1,12,4),
(5,2,6,4);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (6,1,6,3),
(6,2,0,3);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (7,1,14,5),
(7,2,11,5);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (8,1,8,4),
(8,2,4,4);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (9,1,16,5),
(9,2,13,5);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (10,1,18,6),
(10,2,15,6);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (11,1,6,4),
(11,2,3,4);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (12,1,20,6),
(12,2,17,6);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (13,1,3,2),
(13,2,1,2);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (14,1,4,2),
(14,2,2,2);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (15,1,22,8),
(15,2,19,8);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (16,1,9,5),
(16,2,12,5);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (17,1,40,10),
(17,2,35,10);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (18,1,5,4),
(18,2,7,4);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (19,1,55,12),
(19,2,48,12);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (20,1,60,15),
(20,2,52,15);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (21,1,45,10),
(21,2,38,10);
INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES (22,1,3,2),
(22,2,2,2);

-- ======== HISTORIAL OPERATIVO (kardex consistente con inventario) ========
-- Ventas históricas (maestro-detalle)
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(1,'YC-P98-00001','2026-09-25 10:15:00',1,5,2,'EFECTIVO','PUBLICO',0.00,160.00,160.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(2,'YC-P121-00001','2026-09-26 11:40:00',2,6,3,'QR_SIMPLE','PUBLICO',0.00,85.00,85.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(3,'YC-P98-00002','2026-09-28 16:05:00',1,2,2,'TRANSFERENCIA','TECNICO',0.00,505.00,505.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(4,'YC-P121-00002','2026-10-02 09:50:00',2,3,3,'QR_SIMPLE','PUBLICO',0.00,230.00,230.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(5,'YC-P98-00003','2026-10-03 12:20:00',1,4,2,'MIXTO','PUBLICO',20.00,1102.00,1082.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(6,'YC-P98-00004','2026-10-05 15:30:00',1,7,2,'EFECTIVO','PUBLICO',0.00,80.00,80.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(7,'YC-P121-00003','2026-10-07 10:00:00',2,8,3,'QR_SIMPLE','PUBLICO',0.00,380.00,380.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(8,'YC-P121-00004','2026-10-09 17:45:00',2,6,3,'EFECTIVO','PUBLICO',0.00,240.00,240.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(9,'YC-P98-00005','2026-10-11 11:10:00',1,2,2,'TRANSFERENCIA','TECNICO',0.00,290.00,290.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(10,'YC-P98-00006','2026-10-13 09:25:00',1,6,2,'QR_SIMPLE','PUBLICO',0.00,180.00,180.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(11,'YC-P121-00005','2026-10-15 14:55:00',2,4,3,'MIXTO','PUBLICO',15.00,475.00,460.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(12,'YC-P98-00007','2026-10-17 16:30:00',1,3,2,'EFECTIVO','PUBLICO',0.00,250.00,250.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(13,'YC-P121-00006','2026-10-19 10:40:00',2,5,3,'QR_SIMPLE','PUBLICO',0.00,65.00,65.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(14,'YC-P98-00008','2026-10-21 13:15:00',1,8,2,'EFECTIVO','PUBLICO',0.00,320.00,320.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(15,'YC-P121-00007','2026-10-23 18:00:00',2,6,3,'TRANSFERENCIA','PUBLICO',0.00,450.00,450.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(16,'YC-P98-00009','2026-10-25 11:35:00',1,7,2,'QR_SIMPLE','PUBLICO',0.00,310.00,310.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(17,'YC-P121-00008','2026-10-27 09:05:00',2,5,3,'EFECTIVO','PUBLICO',0.00,150.00,150.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(18,'YC-P98-00010','2026-10-28 15:50:00',1,4,2,'MIXTO','TECNICO',25.00,550.00,525.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(19,'YC-P121-00009','2026-10-29 12:10:00',2,7,3,'QR_SIMPLE','PUBLICO',0.00,105.00,105.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(20,'YC-P121-00010','2026-10-30 13:00:00',2,5,3,'EFECTIVO','PUBLICO',0.00,1400.00,1400.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(21,'YC-P98-00011','2026-10-30 17:20:00',1,6,2,'EFECTIVO','PUBLICO',0.00,86.00,86.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(22,'YC-P121-00011','2026-10-31 10:30:00',2,2,3,'TRANSFERENCIA','TECNICO',0.00,200.00,200.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(23,'YC-P98-00012','2026-11-01 14:00:00',1,5,2,'QR_SIMPLE','PUBLICO',0.00,280.00,280.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(24,'YC-P121-00012','2026-11-01 19:15:00',2,6,3,'EFECTIVO','PUBLICO',0.00,40.00,40.00);
INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES
(25,'YC-P98-00013','2026-11-02 11:45:00',1,3,2,'QR_SIMPLE','TECNICO',0.00,430.00,430.00);

INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(1,1,7,1,160.00,90.00,160.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(2,2,20,2,30.00,12.00,60.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(3,2,19,1,25.00,8.00,25.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(4,3,1,1,480.00,380.00,480.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(5,3,15,1,25.00,15.00,25.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(6,4,16,1,230.00,140.00,230.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(7,5,4,2,495.00,340.00,990.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(8,5,21,4,28.00,10.00,112.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(9,6,17,2,40.00,18.00,80.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(10,7,5,1,380.00,260.00,380.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(11,8,9,1,150.00,85.00,150.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(12,8,20,3,30.00,12.00,90.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(13,9,13,1,160.00,120.00,160.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(14,9,14,1,130.00,95.00,130.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(15,10,10,2,90.00,45.00,180.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(16,11,8,1,185.00,110.00,185.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(17,11,11,2,120.00,65.00,240.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(18,11,19,2,25.00,8.00,50.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(19,12,18,1,250.00,160.00,250.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(20,13,17,1,40.00,18.00,40.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(21,13,19,1,25.00,8.00,25.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(22,14,2,1,320.00,210.00,320.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(23,15,3,1,450.00,300.00,450.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(24,16,12,2,80.00,40.00,160.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(25,16,20,5,30.00,12.00,150.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(26,17,9,1,150.00,85.00,150.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(27,18,1,1,480.00,380.00,480.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(28,18,10,1,70.00,45.00,70.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(29,19,15,3,35.00,15.00,105.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(30,20,6,4,350.00,240.00,1400.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(31,21,21,2,28.00,10.00,56.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(32,21,20,1,30.00,12.00,30.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(33,22,22,1,200.00,150.00,200.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(34,23,16,1,230.00,140.00,230.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(35,23,19,2,25.00,8.00,50.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(36,24,17,1,40.00,18.00,40.00);
INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(37,25,4,1,430.00,340.00,430.00);

INSERT INTO traspasos_sucursal (id_traspaso, codigo_traspaso, fecha_hora, id_producto, id_sucursal_origen, id_sucursal_destino, cantidad, id_usuario, estado, observacion) VALUES
(1,'TR-0001','2026-10-06 10:30:00',19,1,2,10,4,'COMPLETADO','Reposición vitrina P121');
INSERT INTO traspasos_sucursal (id_traspaso, codigo_traspaso, fecha_hora, id_producto, id_sucursal_origen, id_sucursal_destino, cantidad, id_usuario, estado, observacion) VALUES
(2,'TR-0002','2026-10-14 15:00:00',1,2,1,2,4,'COMPLETADO','Pantalla OLED pedida por técnico en P98');
INSERT INTO traspasos_sucursal (id_traspaso, codigo_traspaso, fecha_hora, id_producto, id_sucursal_origen, id_sucursal_destino, cantidad, id_usuario, estado, observacion) VALUES
(3,'TR-0003','2026-10-22 09:45:00',20,1,2,8,4,'COMPLETADO','Hidrogel para campaña fin de semana');
INSERT INTO traspasos_sucursal (id_traspaso, codigo_traspaso, fecha_hora, id_producto, id_sucursal_origen, id_sucursal_destino, cantidad, id_usuario, estado, observacion) VALUES
(4,'TR-0004','2026-10-28 16:20:00',12,1,2,3,4,'COMPLETADO','Flex de carga bajo stock en P121');

-- Kardex histórico valorado (ordenado; stock_resultante coincide con inventario_sucursal)
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 10:00:00',1,1,'ENTRADA_COMPRA','COM-0001',6,0,6,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 11:00:00',1,2,'ENTRADA_COMPRA','COM-0002',10,0,10,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 12:00:00',2,1,'ENTRADA_COMPRA','COM-0003',11,0,11,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 13:00:00',2,2,'ENTRADA_COMPRA','COM-0004',5,0,5,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 14:00:00',3,1,'ENTRADA_COMPRA','COM-0005',4,0,4,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 15:00:00',3,2,'ENTRADA_COMPRA','COM-0006',3,0,3,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 16:00:00',4,1,'ENTRADA_COMPRA','COM-0007',10,0,10,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 17:00:00',4,2,'ENTRADA_COMPRA','COM-0008',9,0,9,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 18:00:00',5,1,'ENTRADA_COMPRA','COM-0009',12,0,12,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 19:00:00',5,2,'ENTRADA_COMPRA','COM-0010',7,0,7,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 20:00:00',6,1,'ENTRADA_COMPRA','COM-0011',6,0,6,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 21:00:00',6,2,'ENTRADA_COMPRA','COM-0012',4,0,4,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 22:00:00',7,1,'ENTRADA_COMPRA','COM-0013',15,0,15,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-20 23:00:00',7,2,'ENTRADA_COMPRA','COM-0014',11,0,11,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 00:00:00',8,1,'ENTRADA_COMPRA','COM-0015',8,0,8,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 01:00:00',8,2,'ENTRADA_COMPRA','COM-0016',5,0,5,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 02:00:00',9,1,'ENTRADA_COMPRA','COM-0017',16,0,16,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 03:00:00',9,2,'ENTRADA_COMPRA','COM-0018',15,0,15,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 04:00:00',10,1,'ENTRADA_COMPRA','COM-0019',21,0,21,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 05:00:00',10,2,'ENTRADA_COMPRA','COM-0020',15,0,15,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 06:00:00',11,1,'ENTRADA_COMPRA','COM-0021',6,0,6,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 07:00:00',11,2,'ENTRADA_COMPRA','COM-0022',5,0,5,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 08:00:00',12,1,'ENTRADA_COMPRA','COM-0023',25,0,25,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 09:00:00',12,2,'ENTRADA_COMPRA','COM-0024',14,0,14,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 10:00:00',13,1,'ENTRADA_COMPRA','COM-0025',4,0,4,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 11:00:00',13,2,'ENTRADA_COMPRA','COM-0026',1,0,1,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 12:00:00',14,1,'ENTRADA_COMPRA','COM-0027',5,0,5,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 13:00:00',14,2,'ENTRADA_COMPRA','COM-0028',2,0,2,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 14:00:00',15,1,'ENTRADA_COMPRA','COM-0029',23,0,23,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 15:00:00',15,2,'ENTRADA_COMPRA','COM-0030',22,0,22,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 16:00:00',16,1,'ENTRADA_COMPRA','COM-0031',10,0,10,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 17:00:00',16,2,'ENTRADA_COMPRA','COM-0032',13,0,13,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 18:00:00',17,1,'ENTRADA_COMPRA','COM-0033',42,0,42,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 19:00:00',17,2,'ENTRADA_COMPRA','COM-0034',37,0,37,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 20:00:00',18,1,'ENTRADA_COMPRA','COM-0035',6,0,6,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 21:00:00',18,2,'ENTRADA_COMPRA','COM-0036',7,0,7,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 22:00:00',19,1,'ENTRADA_COMPRA','COM-0037',67,0,67,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-21 23:00:00',19,2,'ENTRADA_COMPRA','COM-0038',42,0,42,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-22 00:00:00',20,1,'ENTRADA_COMPRA','COM-0039',74,0,74,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-22 01:00:00',20,2,'ENTRADA_COMPRA','COM-0040',49,0,49,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-22 02:00:00',21,1,'ENTRADA_COMPRA','COM-0041',51,0,51,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-22 03:00:00',21,2,'ENTRADA_COMPRA','COM-0042',38,0,38,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-22 04:00:00',22,1,'ENTRADA_COMPRA','COM-0043',3,0,3,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-22 05:00:00',22,2,'ENTRADA_COMPRA','COM-0044',3,0,3,4,'Ingreso inicial de mercadería');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-25 10:15:00',7,1,'SALIDA_VENTA','YC-P98-00001',-1,15,14,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-26 11:40:00',20,2,'SALIDA_VENTA','YC-P121-00001',-2,49,47,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-26 11:40:00',19,2,'SALIDA_VENTA','YC-P121-00001',-1,42,41,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-28 16:05:00',1,1,'SALIDA_VENTA','YC-P98-00002',-1,6,5,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-09-28 16:05:00',15,1,'SALIDA_VENTA','YC-P98-00002',-1,23,22,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-02 09:50:00',16,2,'SALIDA_VENTA','YC-P121-00002',-1,13,12,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-03 12:20:00',4,1,'SALIDA_VENTA','YC-P98-00003',-2,10,8,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-03 12:20:00',21,1,'SALIDA_VENTA','YC-P98-00003',-4,51,47,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-05 15:30:00',17,1,'SALIDA_VENTA','YC-P98-00004',-2,42,40,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-06 10:30:00',19,1,'TRASPASO_ENVIO','TR-0001',-10,67,57,4,'Traspaso Reposición vitrina P121');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-06 10:30:00',19,2,'TRASPASO_RECEPCION','TR-0001',10,41,51,4,'Traspaso Reposición vitrina P121');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-07 10:00:00',5,2,'SALIDA_VENTA','YC-P121-00003',-1,7,6,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-09 17:45:00',9,2,'SALIDA_VENTA','YC-P121-00004',-1,15,14,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-09 17:45:00',20,2,'SALIDA_VENTA','YC-P121-00004',-3,47,44,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-11 11:10:00',13,1,'SALIDA_VENTA','YC-P98-00005',-1,4,3,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-11 11:10:00',14,1,'SALIDA_VENTA','YC-P98-00005',-1,5,4,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-13 09:25:00',10,1,'SALIDA_VENTA','YC-P98-00006',-2,21,19,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-14 15:00:00',1,2,'TRASPASO_ENVIO','TR-0002',-2,10,8,4,'Traspaso Pantalla OLED pedida por técnico en P98');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-14 15:00:00',1,1,'TRASPASO_RECEPCION','TR-0002',2,5,7,4,'Traspaso Pantalla OLED pedida por técnico en P98');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-15 14:55:00',8,2,'SALIDA_VENTA','YC-P121-00005',-1,5,4,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-15 14:55:00',11,2,'SALIDA_VENTA','YC-P121-00005',-2,5,3,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-15 14:55:00',19,2,'SALIDA_VENTA','YC-P121-00005',-2,51,49,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-17 16:30:00',18,1,'SALIDA_VENTA','YC-P98-00007',-1,6,5,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-19 10:40:00',17,2,'SALIDA_VENTA','YC-P121-00006',-1,37,36,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-19 10:40:00',19,2,'SALIDA_VENTA','YC-P121-00006',-1,49,48,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-21 13:15:00',2,1,'SALIDA_VENTA','YC-P98-00008',-1,11,10,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-22 09:45:00',20,1,'TRASPASO_ENVIO','TR-0003',-8,74,66,4,'Traspaso Hidrogel para campaña fin de semana');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-22 09:45:00',20,2,'TRASPASO_RECEPCION','TR-0003',8,44,52,4,'Traspaso Hidrogel para campaña fin de semana');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-23 18:00:00',3,2,'SALIDA_VENTA','YC-P121-00007',-1,3,2,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-25 11:35:00',12,1,'SALIDA_VENTA','YC-P98-00009',-2,25,23,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-25 11:35:00',20,1,'SALIDA_VENTA','YC-P98-00009',-5,66,61,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-27 09:05:00',9,2,'SALIDA_VENTA','YC-P121-00008',-1,14,13,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-28 15:50:00',1,1,'SALIDA_VENTA','YC-P98-00010',-1,7,6,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-28 15:50:00',10,1,'SALIDA_VENTA','YC-P98-00010',-1,19,18,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-28 16:20:00',12,1,'TRASPASO_ENVIO','TR-0004',-3,23,20,4,'Traspaso Flex de carga bajo stock en P121');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-28 16:20:00',12,2,'TRASPASO_RECEPCION','TR-0004',3,14,17,4,'Traspaso Flex de carga bajo stock en P121');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-29 12:10:00',15,2,'SALIDA_VENTA','YC-P121-00009',-3,22,19,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-30 13:00:00',6,2,'SALIDA_VENTA','YC-P121-00010',-4,4,0,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-30 17:20:00',21,1,'SALIDA_VENTA','YC-P98-00011',-2,47,45,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-30 17:20:00',20,1,'SALIDA_VENTA','YC-P98-00011',-1,61,60,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-10-31 10:30:00',22,2,'SALIDA_VENTA','YC-P121-00011',-1,3,2,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-11-01 14:00:00',16,1,'SALIDA_VENTA','YC-P98-00012',-1,10,9,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-11-01 14:00:00',19,1,'SALIDA_VENTA','YC-P98-00012',-2,57,55,2,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-11-01 19:15:00',17,2,'SALIDA_VENTA','YC-P121-00012',-1,36,35,3,'Venta POS');
INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES
('2026-11-02 11:45:00',4,1,'SALIDA_VENTA','YC-P98-00013',-1,8,7,2,'Venta POS');

-- ============================================================================
-- 6. FIN DEL SCRIPT — La app PHP (web/) consume esta base vía PDO + prepare()
-- ============================================================================
