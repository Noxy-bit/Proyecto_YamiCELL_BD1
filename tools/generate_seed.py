#!/usr/bin/env python3
"""Genera fase2/script_yamicell.sql: DDL 3FN + seed consistente (kardex == inventario)."""
import random, datetime, io

random.seed(20261015)
out = io.StringIO()
w = out.write

w("""-- ============================================================================
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
""")

# ---------- Catálogos ----------
w("""INSERT INTO sucursales (codigo_puesto, nombre_sucursal, pasillo_ubicacion, encargado, telefono) VALUES
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

""")

# ---------- Productos ----------
# sku, nombre, marca, modelo, cat_id, prov_id, costo, p_tec, p_pub, min, stock98, stock121
productos = [
    ('PAN-A54-OLED',   'Pantalla OLED Samsung A54 5G con marco', 'Samsung',  'Galaxy A54',    1, 2, 380.00, 480.00, 550.00, 4, 6, 8),
    ('PAN-A32-INC',    'Módulo Incell Samsung A32',              'Samsung',  'Galaxy A32',    1, 2, 210.00, 270.00, 320.00, 4, 10, 5),
    ('PAN-IP11-INC',   'Pantalla Incell iPhone 11',              'Apple',    'iPhone 11',     1, 2, 300.00, 390.00, 450.00, 3, 4, 2),
    ('PAN-RN13-AMO',   'Display AMOLED Redmi Note 13 Pro',       'Xiaomi',   'Note 13 Pro',   1, 2, 340.00, 430.00, 495.00, 3, 7, 9),
    ('PAN-A15-OLED',   'Pantalla OLED Samsung A15',              'Samsung',  'Galaxy A15',    1, 1, 260.00, 330.00, 380.00, 4, 12, 6),
    ('PAN-MG84-INC',   'Módulo Incell Moto G84',                 'Motorola', 'Moto G84',      1, 1, 240.00, 300.00, 350.00, 3, 6, 0),
    ('BAT-A54-5K',     'Batería interna Samsung A54 5000mAh',    'Samsung',  'Galaxy A54',    2, 3, 90.00,  130.00, 160.00, 5, 14, 11),
    ('BAT-IP11-3K',    'Batería iPhone 11 3110mAh',              'Apple',    'iPhone 11',     2, 3, 110.00, 150.00, 185.00, 4, 8, 4),
    ('BAT-RN13-5K',    'Batería Redmi Note 13 5000mAh',          'Xiaomi',   'Note 13',       2, 3, 85.00,  120.00, 150.00, 5, 16, 13),
    ('FLX-A54-CARGA',  'Flex de carga Samsung A54',              'Samsung',  'Galaxy A54',    3, 4, 45.00,  70.00,  90.00, 6, 18, 15),
    ('FLX-IP11-CARGA', 'Placa sub-board carga iPhone 11',        'Apple',    'iPhone 11',     3, 4, 65.00,  95.00,  120.00, 4, 6, 3),
    ('FLX-RN13-CARGA', 'Flex de carga Redmi Note 13',            'Xiaomi',   'Note 13',       3, 4, 40.00,  60.00,  80.00, 6, 20, 17),
    ('HER-FLUX-AMTECH','Flux AMTECH NC-559 micro-soldadura',     'AMTECH',   'Universal',     4, 2, 120.00, 160.00, 190.00, 2, 3, 1),
    ('HER-ESTANO-138', 'Estaño en pasta 138°C bajo punto',       'Mechanic', 'Universal',     4, 2, 95.00,  130.00, 155.00, 2, 4, 2),
    ('HER-MALLA-DESS', 'Malla desoldadora 2.0mm premium',        'Sunshine', 'Universal',     4, 4, 15.00,  25.00,  35.00, 8, 22, 19),
    ('CAR-SAM-45W',    'Cargador Samsung 45W Super Fast original','Samsung', 'Universal USB-C',5, 3, 140.00, 190.00, 230.00, 5, 9, 12),
    ('CAR-USB-C25W',   'Cable Tipo C carga rápida 25W certif.',  'Genérico', 'Universal',     5, 3, 18.00,  30.00,  40.00, 10, 40, 35),
    ('CAR-IP20W',      'Cargador iPhone 20W PD original',        'Apple',    'iPhone Lightning',5,3, 160.00, 210.00, 250.00, 4, 5, 7),
    ('PRO-TEMP-A54',   'Vidrio templado 9D Samsung A54',         'Genérico', 'Galaxy A54',    6, 4, 8.00,   15.00,  25.00, 12, 55, 48),
    ('PRO-HIDRO-UNI',  'Lámina hidrogel universal corte',        'Genérico', 'Universal',     6, 4, 12.00,  20.00,  30.00, 15, 60, 52),
    ('PRO-TEMP-IP11',  'Vidrio templado iPhone 11 cerámico',     'Genérico', 'iPhone 11',     6, 4, 10.00,  18.00,  28.00, 10, 45, 38),
    ('CAM-RN13-MAIN',  'Cámara principal Redmi Note 13',         'Xiaomi',   'Note 13',       3, 1, 150.00, 200.00, 240.00, 2, 3, 2),
]

for (sku, nom, marca, modelo, cat, prov, costo, pte, ppu, smin, s98, s121) in productos:
    w(f"INSERT INTO productos (sku, nombre_producto, marca_compatible, modelo_compatible, id_categoria, id_proveedor, costo_compra, precio_tecnico, precio_publico, stock_minimo_alerta) VALUES\n('{sku}','{nom}','{marca}','{modelo}',{cat},{prov},{costo:.2f},{pte:.2f},{ppu:.2f},{smin});\n")

w("\n")
for i, p in enumerate(productos, start=1):
    w(f"INSERT INTO inventario_sucursal (id_producto, id_sucursal, stock_actual, stock_minimo_alerta) VALUES ({i},1,{p[10]},{p[9]}),\n({i},2,{p[11]},{p[9]});\n")

# ---------- Historial: ventas + traspasos + kardex consistente ----------
# Estado inicial: cada puesto arranca con entrada de compra = stock inicial,
# luego ventas y traspasos descuentan/suman. Registramos TODO el kardex.
# Para garantizar consistencia, computamos el stock final a partir de un
# stock de ingreso MAYOR que el stock actual (ingresos - ventas ± traspasos = actual).

w("\n-- ======== HISTORIAL OPERATIVO (kardex consistente con inventario) ========\n")

base_date = datetime.datetime(2026, 9, 20, 9, 0, 0)
kardex_rows = []        # (fecha, prod_idx, suc, tipo, doc, qty_signed, stock_ant, stock_res, usuario, detalle)
ventas_rows = []
detalle_rows = []
traspaso_rows = []
stock = {}              # (prod_idx, suc) -> actual

# Stock inicial objetivo = stock declarado arriba; reconstruimos el flujo:
# ingreso_inicial = actual + salidas - entradas_posteriores
salidas = {}   # (prod,suc) -> qty
entradas_extra = {}

# --- Ventas seed (fecha, sucursal, cliente_id, usuario_id, metodo, tipo_precio, [(prod_idx, qty)], descuento)
ventas_seed = [
    ('2026-09-25 10:15', 1, 5, 2, 'EFECTIVO',     'PUBLICO', [(7,1)]),
    ('2026-09-26 11:40', 2, 6, 3, 'QR_SIMPLE',    'PUBLICO', [(20,2),(19,1)]),
    ('2026-09-28 16:05', 1, 2, 2, 'TRANSFERENCIA','TECNICO', [(1,1),(15,1)]),
    ('2026-10-02 09:50', 2, 3, 3, 'QR_SIMPLE',    'PUBLICO', [(16,1)]),
    ('2026-10-03 12:20', 1, 4, 2, 'MIXTO',        'PUBLICO', [(4,2),(21,4)], 20.00),
    ('2026-10-05 15:30', 1, 7, 2, 'EFECTIVO',     'PUBLICO', [(17,2)]),
    ('2026-10-07 10:00', 2, 8, 3, 'QR_SIMPLE',    'PUBLICO', [(5,1)]),
    ('2026-10-09 17:45', 2, 6, 3, 'EFECTIVO',     'PUBLICO', [(9,1),(20,3)]),
    ('2026-10-11 11:10', 1, 2, 2, 'TRANSFERENCIA','TECNICO', [(13,1),(14,1)]),
    ('2026-10-13 09:25', 1, 6, 2, 'QR_SIMPLE',    'PUBLICO', [(10,2)]),
    ('2026-10-15 14:55', 2, 4, 3, 'MIXTO',        'PUBLICO', [(8,1),(11,2),(19,2)], 15.00),
    ('2026-10-17 16:30', 1, 3, 2, 'EFECTIVO',     'PUBLICO', [(18,1)]),
    ('2026-10-19 10:40', 2, 5, 3, 'QR_SIMPLE',    'PUBLICO', [(17,1),(19,1)]),
    ('2026-10-21 13:15', 1, 8, 2, 'EFECTIVO',     'PUBLICO', [(2,1)]),
    ('2026-10-23 18:00', 2, 6, 3, 'TRANSFERENCIA','PUBLICO', [(3,1)]),
    ('2026-10-25 11:35', 1, 7, 2, 'QR_SIMPLE',    'PUBLICO', [(12,2),(20,5)]),
    ('2026-10-27 09:05', 2, 5, 3, 'EFECTIVO',     'PUBLICO', [(9,1)]),
    ('2026-10-28 15:50', 1, 4, 2, 'MIXTO',        'TECNICO', [(1,1),(10,1)], 25.00),
    ('2026-10-29 12:10', 2, 7, 3, 'QR_SIMPLE',    'PUBLICO', [(15,3)]),
    ('2026-10-30 13:00', 2, 5, 3, 'EFECTIVO',     'PUBLICO', [(6,4)]),
    ('2026-10-30 17:20', 1, 6, 2, 'EFECTIVO',     'PUBLICO', [(21,2),(20,1)]),
    ('2026-10-31 10:30', 2, 2, 3, 'TRANSFERENCIA','TECNICO', [(22,1)]),
    ('2026-11-01 14:00', 1, 5, 2, 'QR_SIMPLE',    'PUBLICO', [(16,1),(19,2)]),
    ('2026-11-01 19:15', 2, 6, 3, 'EFECTIVO',     'PUBLICO', [(17,1)]),
    ('2026-11-02 11:45', 1, 3, 2, 'QR_SIMPLE',    'TECNICO', [(4,1)]),
]

# --- Traspasos seed (fecha, prod_idx, origen, destino, qty, usuario)
traspasos_seed = [
    ('2026-10-06 10:30', 19, 1, 2, 10, 4, 'Reposición vitrina P121'),
    ('2026-10-14 15:00', 1,  2, 1, 2,  4, 'Pantalla OLED pedida por técnico en P98'),
    ('2026-10-22 09:45', 20, 1, 2, 8,  4, 'Hidrogel para campaña fin de semana'),
    ('2026-10-28 16:20', 12, 1, 2, 3,  4, 'Flex de carga bajo stock en P121'),
]

# Calcular ingreso inicial necesario por (prod, suc)
for p in productos:
    idx = productos.index(p) + 1
    for suc, actual in ((1, p[10]), (2, p[11])):
        s = sum(q for (_,vs,vu,vm,vt,items,*_d) in [] for q in [])  # placeholder
        stock[(idx, suc)] = actual

# ventas descuentan; traspasos mueven
for v in ventas_seed:
    suc = v[1]
    for (pi, q) in v[6]:
        salidas[(pi, suc)] = salidas.get((pi, suc), 0) + q
for (f, pi, o, d, q, u, obs) in traspasos_seed:
    salidas[(pi, o)] = salidas.get((pi, o), 0) + q
    entradas_extra[(pi, d)] = entradas_extra.get((pi, d), 0) + q

ingreso_inicial = {}
for (pi, suc), actual in stock.items():
    ingreso_inicial[(pi, suc)] = actual + salidas.get((pi, suc), 0) - entradas_extra.get((pi, suc), 0)
    if ingreso_inicial[(pi, suc)] < 0:
        raise SystemExit(f"negativo {pi} {suc}")

# Emitir ENTRADA_COMPRA inicial por cada (prod, suc) con stock>0 o movimientos
doc_compra = 0
for pi in range(1, len(productos) + 1):
    for suc in (1, 2):
        ini = ingreso_inicial[(pi, suc)]
        if ini <= 0:
            continue
        doc_compra += 1
        doc = f"COM-{doc_compra:04d}"
        fecha = (base_date + datetime.timedelta(hours=doc_compra)).strftime('%Y-%m-%d %H:%M:%S')
        kardex_rows.append((fecha, pi, suc, 'ENTRADA_COMPRA', doc, ini, 0, ini, 4, 'Ingreso inicial de mercadería'))
        stock[(pi, suc)] = ini

# Recibo counters por sucursal
recibo_n = {1: 0, 2: 0}
def recibo(suc):
    recibo_n[suc] += 1
    pref = 'YC-P98' if suc == 1 else 'YC-P121'
    return f"{pref}-{recibo_n[suc]:05d}"

tr_n = 0
vid = 0
did = 0
eventos = []  # (fecha, kind, payload)
for v in ventas_seed:
    eventos.append((v[0], 'venta', v))
for t in traspasos_seed:
    eventos.append((t[0], 'traspaso', t))
eventos.sort(key=lambda e: e[0])

for fecha, kind, payload in eventos:
    fkey = fecha.replace(' ', 'T') + ':00' if len(fecha) == 16 else fecha
    fecha_fmt = fecha if len(fecha) > 16 else fecha + ':00'
    if kind == 'venta':
        (_, suc, cli, usu, metodo, tpre, items, *rest) = payload
        desc = rest[0] if rest else 0.0
        cod = recibo(suc)
        vid += 1
        subtotal = 0.0
        lineas = []
        for (pi, q) in items:
            p = productos[pi - 1]
            precio = p[7] if tpre == 'TECNICO' else p[8]
            st = round(q * precio, 2)
            subtotal += st
            lineas.append((pi, q, precio, p[6], st))
        total = round(subtotal - desc, 2)
        ventas_rows.append((vid, cod, fecha_fmt, suc, cli, usu, metodo, tpre, desc, round(subtotal, 2), total))
        for (pi, q, precio, costo, st) in lineas:
            did += 1
            detalle_rows.append((did, vid, pi, q, precio, costo, st))
            ant = stock[(pi, suc)]
            res = ant - q
            if res < 0:
                raise SystemExit(f"stock negativo venta {cod} prod {pi}")
            stock[(pi, suc)] = res
            kardex_rows.append((fecha_fmt, pi, suc, 'SALIDA_VENTA', cod, -q, ant, res, usu, 'Venta POS'))
    else:
        (_, pi, o, d, q, u, obs) = payload
        tr_n += 1
        codt = f"TR-{tr_n:04d}"
        traspaso_rows.append((tr_n, codt, fecha_fmt, pi, o, d, q, u, 'COMPLETADO', obs))
        for suc, tipo, delta in ((o, 'TRASPASO_ENVIO', -q), (d, 'TRASPASO_RECEPCION', q)):
            ant = stock[(pi, suc)]
            res = ant + delta
            if res < 0:
                raise SystemExit(f"stock negativo traspaso {codt}")
            stock[(pi, suc)] = res
            kardex_rows.append((fecha_fmt, pi, suc, tipo, codt, delta, ant, res, u, f'Traspaso {obs}'))

# Sanity: stock final == declarado
for pi in range(1, len(productos) + 1):
    for suc, esperado in ((1, productos[pi-1][10]), (2, productos[pi-1][11])):
        assert stock[(pi, suc)] == esperado, f"prod {pi} suc {suc}: {stock[(pi,suc)]} != {esperado}"

kardex_rows.sort(key=lambda r: (r[0], r[4] or ''))

w("-- Ventas históricas (maestro-detalle)\n")
for r in ventas_rows:
    w(f"INSERT INTO ventas (id_venta, codigo_recibo, fecha_hora, id_sucursal, id_cliente, id_usuario, metodo_pago, tipo_precio, descuento_bs, subtotal_bruto, total_neto) VALUES\n({r[0]},'{r[1]}','{r[2]}',{r[3]},{r[4]},{r[5]},'{r[6]}','{r[7]}',{r[8]:.2f},{r[9]:.2f},{r[10]:.2f});\n")
w("\n")
for r in detalle_rows:
    w(f"INSERT INTO detalle_venta (id_detalle, id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES\n({r[0]},{r[1]},{r[2]},{r[3]},{r[4]:.2f},{r[5]:.2f},{r[6]:.2f});\n")
w("\n")
for r in traspaso_rows:
    w(f"INSERT INTO traspasos_sucursal (id_traspaso, codigo_traspaso, fecha_hora, id_producto, id_sucursal_origen, id_sucursal_destino, cantidad, id_usuario, estado, observacion) VALUES\n({r[0]},'{r[1]}','{r[2]}',{r[3]},{r[4]},{r[5]},{r[6]},{r[7]},'{r[8]}','{r[9]}');\n")
w("\n-- Kardex histórico valorado (ordenado; stock_resultante coincide con inventario_sucursal)\n")
for r in kardex_rows:
    det = r[9].replace("'", "''")
    w(f"INSERT INTO kardex_movimientos (fecha_hora, id_producto, id_sucursal, tipo_movimiento, documento_referencia, cantidad, stock_anterior, stock_resultante, id_usuario, detalle) VALUES\n('{r[0]}',{r[1]},{r[2]},'{r[3]}','{r[4]}',{r[5]},{r[6]},{r[7]},{r[8]},'{det}');\n")

w("""
-- ============================================================================
-- 6. FIN DEL SCRIPT — La app PHP (web/) consume esta base vía PDO + prepare()
-- ============================================================================
""")

with open('fase2/script_yamicell.sql', 'w', encoding='utf-8') as f:
    f.write(out.getvalue())

print(f"OK: {len(productos)} productos, {len(ventas_rows)} ventas, {len(detalle_rows)} líneas, {len(traspaso_rows)} traspasos, {len(kardex_rows)} movimientos kardex")
