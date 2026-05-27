-- AdminNeo 5.0.0 MySQL 8.0.35 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `abonos_combustible`;
CREATE TABLE `abonos_combustible` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `monto` int NOT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `app_conciliaciones`;
CREATE TABLE `app_conciliaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_movimiento` int NOT NULL,
  `id_pedido_interno` int NOT NULL COMMENT 'Se enlaza con pedidos_activos.id_interno',
  `monto_aplicado` decimal(12,2) NOT NULL,
  `fecha_conciliacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `conciliado_por` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_movimiento` (`id_movimiento`),
  CONSTRAINT `app_conciliaciones_ibfk_1` FOREIGN KEY (`id_movimiento`) REFERENCES `app_movimientos_bancarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `app_fcm_tokens`;
CREATE TABLE `app_fcm_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `token` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `dispositivo_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notify_pedido_creado` tinyint(1) DEFAULT '1',
  `notify_cambio_estado` tinyint(1) DEFAULT '1',
  `notify_pedido_entregado` tinyint(1) DEFAULT '1',
  `notify_pedido_editado` tinyint(1) DEFAULT '1',
  `notify_doc_por_vencer` tinyint(1) DEFAULT '1',
  `notify_doc_vencido` tinyint(1) DEFAULT '1',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `app_movimientos_bancarios`;
CREATE TABLE `app_movimientos_bancarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fintoc_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `fecha_movimiento` date NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `rut_remitente` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `nombre_remitente` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `tipo` enum('ingreso','egreso') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'ingreso',
  `estado` enum('pendiente','parcial','conciliado') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'pendiente',
  `saldo_disponible` decimal(12,2) NOT NULL COMMENT 'Permite pagar múltiples facturas con un solo abono',
  `banco` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_fintoc_id` (`fintoc_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha` (`fecha_movimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `app_roles`;
CREATE TABLE `app_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `app_usuario_roles`;
CREATE TABLE `app_usuario_roles` (
  `usuario_email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `rol_id` int NOT NULL,
  PRIMARY KEY (`usuario_email`,`rol_id`),
  KEY `rol_id` (`rol_id`),
  CONSTRAINT `app_usuario_roles_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `app_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `app_usuarios`;
CREATE TABLE `app_usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_login` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `apellido` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `foto_url` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `telefono` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cargo` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT 'Usuario',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_nacimiento` date DEFAULT NULL,
  `fcm_token` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_login` (`user_login`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


SET NAMES utf8mb4;

DROP TABLE IF EXISTS `categorias_clientes`;
CREATE TABLE `categorias_clientes` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `nombre_categoria` varchar(50) NOT NULL,
  `descripcion` text,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id_interno` int NOT NULL AUTO_INCREMENT,
  `es_global` tinyint(1) DEFAULT '0',
  `id_cliente` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cliente` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `razon_social` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `rut_cliente` varchar(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `giro` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `tipo_cliente` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `direccion` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `direccion_factura` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `comuna_factura` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ciudad_factura` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `comuna` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ciudad` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `contacto` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `apellido` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `responsable` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `telefono_factura` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `email_factura` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `lat_despacho` decimal(10,8) DEFAULT NULL,
  `lng_despacho` decimal(11,8) DEFAULT NULL,
  `dias_credito` int DEFAULT '0',
  `logo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_interno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

INSERT INTO `clientes` (`id_interno`, `es_global`, `id_cliente`, `cliente`, `razon_social`, `rut_cliente`, `giro`, `tipo_cliente`, `direccion`, `direccion_factura`, `comuna_factura`, `ciudad_factura`, `comuna`, `ciudad`, `contacto`, `nombre`, `apellido`, `responsable`, `telefono`, `telefono_factura`, `email`, `email_factura`, `activo`, `lat_despacho`, `lng_despacho`, `dias_credito`, `logo`) VALUES
(1,	0,	'CLI-001',	'Maifud',	NULL,	'76.840.054-7',	'Venta al por menor por correo',	'1',	'Dr. José Tobias 2790, Quinta Normal, Chile',	NULL,	NULL,	NULL,	'Quinta Normal',	NULL,	'Dario contreras',	'Dario',	'contreras',	'jandres@tabolango.cl',	'+56998741639',	'+56998741639',	'Dario@maifud.cl',	'Dario@maifud.cl',	0,	-33.41271920,	-70.69965670,	0,	'https://tabolango.cl/logos/logo_1_1767737130.png'),
(2,	0,	'CL-002',	'Dominó',	'Carreño y Garcia Limitada',	'77.593.150-7',	'Venta al por menor',	'1',	'Fariña 405, Recoleta, Chile',	'Fariña 405',	'Recoleta',	'Santiago',	'Recoleta',	NULL,	'Luis Rojas',	'Luis',	'Rojas',	'jandres@tabolango.cl',	'84392831',	'84392831',	'Televegaventas@gmail.com',	'Televegaventas@gmail.com',	1,	-33.42740790,	-70.65018820,	0,	'https://tabolango.cl/logos/logo_2_1767724745.png'),
(3,	0,	'CLI-003',	'Montemarket',	'',	'77.742.976-0',	'Otras actividades de Venta al por menor',	'5',	'Río Aconcagua 410, Concón, Chile',	NULL,	NULL,	NULL,	'Concón',	NULL,	'Alvaro Duque',	'Alvaro',	'Duque',	'sofia@tabolango.cl',	'+56953972602',	'+56953972602',	'',	'',	1,	-32.93556700,	-71.52826660,	0,	'https://tabolango.cl/logos/logo_3_1767899460.png'),
(5,	0,	'alemana',	'Fuente Alemana',	NULL,	'78.711.810-0',	'Venta al por menor',	'Prospecto',	'Avenida Pedro de Valdivia 210, Providencia, Chile',	NULL,	NULL,	NULL,	'Providencia',	NULL,	'Freddy',	'Freddy',	'',	'jandres@tabolango.cl',	'',	'',	'fuentealemana54@gmail.com',	'fuentealemana54@gmail.com',	0,	-33.42549050,	-70.61115310,	0,	'https://tabolango.cl/logos/logo_5_1767805036.png'),
(13,	1,	'Migue',	'Miguelayo',	NULL,	'12-3',	'Venta al por menor',	NULL,	'Exequiel Fernández 2693, Macul, Chile',	NULL,	NULL,	NULL,	'Macul',	NULL,	'',	'',	'',	'jandres@tabolango.cl',	'',	'',	'',	'',	1,	-33.47868630,	-70.60169020,	0,	'https://tabolango.cl/logos/logo_13_1767986519.png'),
(14,	0,	'Migue',	'Miguelayo - Vitacura',	NULL,	'',	'Venta al por menor',	NULL,	'Av Vitacura 7450, Vitacura, Chile',	NULL,	NULL,	NULL,	'Vitacura',	NULL,	'',	'',	'',	'jandres@tabolango.cl',	'',	'',	'',	'',	1,	-33.38578230,	-70.56189080,	0,	'https://tabolango.cl/logos/logo_13_1767986519.png'),
(15,	0,	'Migue',	'Miguelayo - Macul',	NULL,	'',	'Venta al por menor',	NULL,	'Av. Macul 3353, Macul, Chile',	NULL,	NULL,	NULL,	'Macul',	NULL,	'',	'',	'',	'jandres@tabolango.cl',	'',	'',	'',	'',	1,	-33.48499630,	-70.59962740,	0,	'https://tabolango.cl/logos/logo_13_1767986519.png'),
(16,	0,	'Migue',	'Miguelayo - Cantagallo',	NULL,	'',	'Venta al por menor',	NULL,	'Nueva Las Condes 12290, Las Condes, Chile',	NULL,	NULL,	NULL,	'Las Condes',	NULL,	'---',	'---',	'',	'jandres@tabolango.cl',	'',	'',	'',	'',	1,	-33.37340390,	-70.51825930,	0,	'https://tabolango.cl/logos/logo_13_1767986519.png'),
(17,	0,	'Migue',	'Miguelayo - Apoquindo',	NULL,	'',	'Venta al por menor',	NULL,	'Avenida Apoquindo 5601, Las Condes, Chile',	NULL,	NULL,	NULL,	'Las Condes',	NULL,	'',	'',	'',	'jandres@tabolango.cl',	'',	'',	'',	'',	1,	-33.40996520,	-70.57068550,	0,	'https://tabolango.cl/logos/logo_13_1767986519.png'),
(18,	0,	'Migue',	'Miguelayo - Puente Alto',	NULL,	'',	'Venta al por menor',	NULL,	'Avenida Concha y Toro 3338, Puente Alto, Chile',	NULL,	NULL,	NULL,	'Puente Alto',	NULL,	'---',	'---',	'',	'jandres@tabolango.cl',	'',	'',	'',	'',	1,	-33.57887980,	-70.58229920,	0,	'https://tabolango.cl/logos/logo_13_1767986519.png'),
(19,	0,	'Migue',	'Miguelayo - Providencia',	NULL,	'',	'Venta al por menor',	NULL,	'Avenida Providencia 1095, Providencia, Chile',	NULL,	NULL,	NULL,	'Providencia',	NULL,	'---',	'---',	'',	'jandres@tabolango.cl',	'',	'',	'',	'',	1,	-33.43019480,	-70.62216610,	0,	'https://tabolango.cl/logos/logo_13_1767986519.png'),
(20,	0,	'Migue',	'Miguelayo - Egaña',	NULL,	'',	'Venta al por menor',	NULL,	'Diag. Ote. 5693, Ñuñoa, Chile',	NULL,	NULL,	NULL,	'Ñuñoa',	NULL,	'---',	'---',	'',	'',	'+56956956925',	'+56956956925',	'',	'',	1,	-33.45900450,	-70.57285040,	0,	'https://tabolango.cl/logos/logo_13_1767986519.png'),
(21,	0,	'el templo',	'Pizzería El Templo - Concon',	'JAIME ALBERTO MIRANDA BRAVO HOSPEDAJE Y HOTELERIA EMPRESA INDIVIDUAL DE RESPONSABILIDAD LIMITADA',	'76.406.086-5',	'Actividades de restaurante',	'5',	'Santa Macarena 280, Concón, Chile',	'Los Almendros 751',	'Casablanca',	'Quintay',	'Concón',	NULL,	'Erick Soto',	'Carlos',	'Miranda',	'jandres@tabolango.cl',	'67022155',	'+56967022155',	'cmiranda95@gmail.com',	'cmiranda95@gmail.com',	1,	-32.94193630,	-71.52124060,	0,	'https://tabolango.cl/logos/logo_21_1768239707.png'),
(22,	0,	'antigua',	'Antigua Fuente',	NULL,	'',	'Venta al por menor',	NULL,	'MUT - Mercado Urbano Tobalaba - Avenida Apoquindo, Las Condes, Chile',	NULL,	NULL,	NULL,	'Las Condes',	NULL,	'José Manuel',	'José',	'Manuel',	'jandres@tabolango.cl',	'',	'',	'antiguafuentemut@gmail.com',	'antiguafuentemut@gmail.com',	0,	-33.41758840,	-70.60130540,	0,	'https://tabolango.cl/logos/logo_22_1768240002.png'),
(23,	0,	'Pecado',	'Pecado del Inka - 12 Norte',	'Ortíz Gamarra y compañia Limitada',	'76.411.867-7',	'Restaurante',	'5',	'4 - 1/2 Oriente 1168, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'Jhon Ortíz',	'Jhon',	'Ortíz',	'sofia@tabolango.cl',	'+56984605662',	'+56984605662',	'jhonortizgamarra@gmail.com',	'jhonortizgamarra@gmail.com',	1,	-33.01139380,	-71.54335800,	0,	'https://tabolango.cl/logos/logo_30_1768857726.png'),
(24,	0,	NULL,	'Prueba',	'Neighbox spa',	'77.731.867-5',	'Venta al por mayor',	'5',	'Matilde Salamanca 950, Providencia, Chile',	'v5ddd',	'Providencia',	'Santiago',	'Providencia',	'Santiago',	'Sofía Andonaegui j',	'Sofía',	'Andonaegui j',	'jandres@tabolango.cl',	'979694520',	'979694520',	'jandres@tabolango.cl',	'jaespinosaa@gmail.com',	1,	-33.43449570,	-70.61235780,	2,	NULL),
(25,	0,	NULL,	'La Flor de Chile',	'Víctor Vera e Hijos y Cía Ltda',	'76.101.048-4',	'Venta al por menor',	'5',	'8 Norte 601, Valparaíso, Viña del Mar, Chile',	'',	'',	'',	'Viña del Mar',	NULL,	'Cristian Vera',	'Cristian',	'Vera',	'jandres@tabolango.cl',	'81892534',	'+5698189 2534',	'cvera@laflordechile.cl',	'cvera@laflordechile.cl',	1,	-33.01498290,	-71.55113680,	0,	'https://tabolango.cl/logos/logo_25_1770121950.png'),
(26,	0,	NULL,	'La Chacra Verdulería',	'Raúl Matamala Peralta',	'7.826.636-8',	'Alimentos, Frutas y Verduras',	'5',	'6 Norte 880, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'Raul Matamala',	'Raul',	'Matamala',	'sofia@tabolango.cl',	'+56993349353',	'+56993349353',	'',	'',	1,	-33.01785700,	-71.54826210,	0,	'https://tabolango.cl/logos/logo_26_1768856804.png'),
(27,	0,	NULL,	'Restaurant OH Margot',	'Zuri Martinez Farah',	'8.681.309-2',	'Venta al por menor',	'5',	'Edificio Euromarina I - Lapislázuli, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'Zuri Martinez',	'Zuri',	'Martinez',	'sofia@tabolango.cl',	'+56989928803',	'+56989928803',	'',	'',	1,	-32.95663580,	-71.54680800,	0,	'https://tabolango.cl/logos/logo_27_1768857157.png'),
(28,	0,	NULL,	'Restaurante aquí y en la quebrada del ají',	'Marianela Flores Salinas',	'8.477.980-6',	'Venta al por menor',	'5',	'San Antonio 1318, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'Marianela Flores',	'Marianela',	'Flores',	'sofia@tabolango.cl',	'+56983203654',	'+56983203654',	'',	'',	1,	-33.00975750,	-71.54238360,	0,	NULL),
(29,	0,	NULL,	'Restaurant Txipiron Ltda',	'Restaurant Txipiron Limitada',	'76.213.382-2',	'RESTAURANTES COMIDA ESPAÑOLA.',	'5',	'6 Norte 96, Viña del Mar, Chile',	'',	'',	'',	'Viña del Mar',	NULL,	'Gonzalo Navarro',	'Gonzalo',	'Navarro',	'jandres@tabolango.cl',	'79776341',	'+56979776341',	'gerencia@txipiron.cl',	'gerencia@txipiron.cl',	1,	-33.01646220,	-71.55757010,	0,	'https://tabolango.cl/logos/logo_29_1768857921.png'),
(30,	0,	'Pecado',	'Pecado del Inka - Ecuador',	'Pecado del Inka Spa',	'76.733.231-9',	'Servicios de Restaurantes, Banquetes, Bodas, Pub, Minimarket',	'5',	'Ecuador 280, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'Jhon Ortiz',	'Jhon',	'Ortiz',	'sofia@tabolango.cl',	'+56984605662',	'+56984605662',	'jhonortizgamarra@gmail.com',	'jhonortizgamarra@gmail.com',	1,	-33.02482570,	-71.56018940,	0,	'https://tabolango.cl/logos/logo_30_1768857726.png'),
(31,	0,	'',	'Central Inka',	'Inversiones Coricancha Limitada',	'77.646.336-1',	'Servicios de restaurant y delivery',	'5',	'1 Oriente 1150, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'Jhon Ortiz',	'Jhon',	'Ortiz',	'sofia@tabolango.cl',	'+56984605662',	'+56984605662',	'jhonortizgamarra@gmail.com',	'jhonortizgamarra@gmail.com',	1,	-33.01090130,	-71.54753640,	0,	'https://tabolango.cl/logos/logo_31_1768926872.png'),
(32,	0,	'',	'Kosta Brava',	'Inversiones Mil Mares Limitada',	'78.019.371-9',	'Restaurant',	'5',	'Central 153, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'Jhon Ortiz',	'Jhon',	'Ortiz',	'sofia@tabolango.cl',	'+56984605662',	'+56984605662',	'jhonortizgamarra@gmail.com',	'jhonortizgamarra@gmail.com',	1,	-32.97048420,	-71.54436120,	0,	'https://tabolango.cl/logos/logo_23_1768856337.png'),
(33,	0,	NULL,	'Nitan Gourmet',	'Venta de productos veganos Maria Macarena Saavedra Zaror e.i.r.l',	'77.015.783-8',	'Venta al por menor',	'5',	'6 Norte 880, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'Macarena Saavedra',	'Macarena',	'Saavedra',	'sofia@tabolango.cl',	'+56994435868',	'+56994435868',	'',	'',	1,	-33.01785700,	-71.54826210,	0,	'https://tabolango.cl/logos/logo_33_1769100107.png'),
(34,	0,	NULL,	'yo pruebo esta función',	'',	'12.345.678-9',	'Venta al por menor',	'5',	'Trancura, Pucon, Pucón, Chile',	NULL,	NULL,	NULL,	'Pucón',	NULL,	'changos',	'changos',	'',	'jandres@tabolango.cl',	'+56997969342',	'+56997969342',	'lala@lala.cl',	'lala@lala.cl',	0,	-39.27704230,	-71.97674860,	0,	NULL),
(35,	0,	NULL,	'tabolango go',	'Tabolango sp',	'12.345.678-0',	'Venta al por menor',	'5',	'Felipe II, Las Condes, Chile',	NULL,	NULL,	NULL,	'Las Condes',	NULL,	'hardo',	'hardo',	'',	'jandres@tabolango.cl',	'+56997969452',	'+56997969452',	'oo@oo.cll',	'oo@oo.cll',	0,	-33.42880550,	-70.57889140,	0,	NULL),
(36,	1,	'Pecado',	'Pecado del Inka',	'',	'76.411.867-7',	'Venta al por menor',	'5',	'12 Norte 1114, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'Jhon Ortíz',	'Jhon',	'Ortíz',	'sofia@tabolango.cl',	'+56984605662',	'+56984605662',	'jhonortizgamarra@gmail.com',	'jhonortizgamarra@gmail.com',	1,	-33.01214080,	-71.54398470,	0,	'https://tabolango.cl/logos/logo_30_1768857726.png'),
(37,	0,	NULL,	'Restaurant Kai Tiki',	'Kai Spa.',	'77.662.554-K',	'Venta al por menor de alimentos',	'4',	'Avenida del Mar 2500, Puchuncaví, Chile',	NULL,	NULL,	NULL,	'Puchuncaví',	NULL,	'Karyn',	'Karyn',	'',	'sofia@tabolango.cl',	'+56996576191',	'+56996576191',	'',	'',	1,	-32.64965900,	-71.44110350,	0,	'https://tabolango.cl/logos/logo_37_1769000699.png'),
(38,	0,	NULL,	'Wok AND roll',	'Servicios Arba Chile Ltda',	'76.078.780-9',	'Venta al por menor',	'5',	'5 Norte 476, Valparaíso, Viña del Mar, Chile',	'',	'',	'',	'Viña del Mar',	NULL,	'José Figueroa',	'José',	'Figueroa',	'jandres@tabolango.cl',	'84994094',	'56984994094',	'jfigueroa@wokandroll.cl',	'jfigueroa@wokandroll.cl',	1,	-33.01817870,	-71.55365350,	0,	'https://tabolango.cl/logos/logo_38_1769184673.png'),
(39,	0,	NULL,	'Pits Burger',	'Comercial er Ltda',	'78.964.090-4',	'Venta al por menor',	'5',	'Avenida Barros 792, Concón, Chile',	NULL,	NULL,	NULL,	'Concón',	NULL,	'',	'',	'',	'sofia@tabolango.cl',	'+56942694231',	'+56942694231',	'',	'',	1,	-32.91995980,	-71.51998280,	0,	'https://tabolango.cl/logos/logo_39_1770123055.png'),
(40,	0,	'Migue',	'Miguelayo - Tobalaba',	'',	'',	'Venta al por menor',	'2',	'Avenida Hernando de Aguirre 35, Providencia, Chile',	NULL,	NULL,	NULL,	'Providencia',	NULL,	'---',	'---',	'',	'',	'+56956956925',	'+56956956925',	'',	'',	1,	-33.41865840,	-70.60150600,	0,	'https://tabolango.cl/logos/logo_13_1767986519.png'),
(41,	0,	NULL,	'Fogón Carmencha',	'RESTAURANTE CARMEN GLORIA ADRIAZOLA VALENZUELA E.I.R.L.',	'77.979.406-7',	'Actividades de Restaurnte y Servicios',	'5',	'Las Rosas Oriente 810, Concón, Chile',	'',	'',	'',	'Concón',	NULL,	'',	'Jaime',	'',	'jandres@tabolango.cl',	'32652105',	'56932652105',	'',	'',	1,	-32.93594700,	-71.52389490,	0,	'https://tabolango.cl/logos/logo_41_1770123307.png'),
(42,	0,	NULL,	'Swiss Café',	'SWISSCAFE SPA',	'77.211.506-7',	'Venta al por menor',	'5',	'Angamos 640, Viña del Mar, Chile',	'',	'',	'',	'Viña del Mar',	NULL,	'',	'',	'',	'jandres@tabolango.cl',	'98439252',	'56998439252',	'',	'',	1,	-32.97151300,	-71.53769360,	0,	'https://tabolango.cl/logos/logo_42_1770123519.png'),
(43,	0,	NULL,	'Cholos Criollos',	'INVERSIONES Y GASTRONOMIA CELINDA SPA',	'77.044.924-3',	'Venta al por menor',	'5',	'Avenida Borgoño 15280, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'',	'',	'',	'sofia@tabolango.cl',	'+56939491543',	'+56939491543',	'',	'',	1,	-32.96993370,	-71.54355090,	0,	'https://tabolango.cl/logos/logo_43_1770122878.png'),
(44,	0,	NULL,	'Cevichotes Restaurant',	'LACREME SpA',	'77.921.695-0',	'Venta al por menor',	'5',	'Angamos 242, Viña del Mar, Chile',	'',	'',	'',	'Viña del Mar',	NULL,	'Matías Campusano',	'Matías',	'Campusano',	'jandres@tabolango.cl',	'90785602',	'+56990785602',	'Mati.campusano.599@gmail.com',	'Mati.campusano.599@gmail.com',	1,	-32.97176740,	-71.54200410,	0,	'https://tabolango.cl/logos/logo_44_1770123589.png'),
(45,	0,	NULL,	'JAC',	'Comercial JAC Spa',	'77.995.120-0',	'Venta al por menor',	'4',	'Las Fresas 4646, Vitacura, Chile',	NULL,	NULL,	NULL,	'Vitacura',	NULL,	'Jorge carvallo',	'Jorge',	'carvallo',	'sofia@tabolango.cl',	'+56997778838',	'+56997778838',	'',	'',	1,	-33.39992000,	-70.58530830,	0,	NULL),
(46,	0,	NULL,	'Emanuel',	'Emanuel Spa',	'78.295.823-2',	'Venta al por menor',	'4',	'Av. del Mar 211, Maitencillo, Zapallar, Chile',	NULL,	NULL,	NULL,	'Zapallar',	NULL,	'Karina Herrera',	'Karina',	'Herrera',	'sofia@tabolango.cl',	'',	'',	'',	'',	1,	-32.63429470,	-71.42922120,	0,	NULL),
(47,	0,	NULL,	'Apianados',	'PIA CAROLINA LOPEZ VENEGAS',	'15.777.341-0',	'Venta al por menor',	'4',	'APIANADOS MAITENCILLO Marisquería apanada - Avenida del Mar 101, Puchuncaví, Chile',	NULL,	NULL,	NULL,	'Puchuncaví',	NULL,	'',	'',	'',	'sofia@tabolango.cl',	'',	'',	'',	'',	1,	-32.63345180,	-71.42898450,	0,	NULL),
(48,	0,	NULL,	'Castillo Del Mar',	'Servicios Gastronómicos del mar Limitada',	'76.251.579-2',	'Venta al por menor',	'5',	'Av. Marina 50, Viña del Mar, Chile',	NULL,	NULL,	NULL,	'Viña del Mar',	NULL,	'Ronaldo Muñoz',	'Ronaldo',	'Muñoz',	'sofia@tabolango.cl',	'+56989980753',	'+56989980753',	'',	'',	1,	-33.02064270,	-71.56522580,	0,	'https://tabolango.cl/logos/logo_48_1770122448.png'),
(49,	0,	NULL,	'Bar Tees Costa Cachagua',	'Pachamama eventos SpA',	'76.682.208-8',	'Venta al por menor',	'4',	'Bar tees Costa Cachagua, Zapallar, Chile',	NULL,	NULL,	NULL,	'Zapallar',	NULL,	'Patricio Chacana',	'Patricio',	'Chacana',	'sofia@tabolango.cl',	'+56956987690',	'+56956987690',	'pato.chacanafarfan@gmail.com',	'pato.chacanafarfan@gmail.com',	1,	-32.62495470,	-71.42499640,	0,	NULL),
(50,	0,	NULL,	'Casa Tabolango',	'Sofia Andonaegui',	'9.386.848-K',	NULL,	NULL,	'Camino Diego Portales 151, Tabolango, Limache, Chile',	NULL,	NULL,	NULL,	'Limache',	NULL,	'Sofia',	'Sofia',	'',	'sofia@tabolango.cl',	'+56956961131',	'+56956961131',	'sofia.ando@gmail.com',	'sofia.ando@gmail.com',	0,	-32.92583600,	-71.37452600,	0,	NULL),
(51,	0,	NULL,	'Burgazo',	'Comercial deportiva Playa el Abanico Ltda',	'76.058.219-0',	'Venta al por menor',	'5',	'Burgazo Hamburgueserías, Puchuncaví, Chile',	'',	'',	'',	'Puchuncaví',	NULL,	'Ignacio',	'Ignacio',	'',	'jandres@tabolango.cl',	'+56 9 93437188',	'+56923809409',	'',	'',	1,	-32.68341000,	-71.41314320,	0,	NULL),
(52,	0,	NULL,	'La Ruka',	'Victoria SpA',	'78.085.799-4',	'Venta al por menor',	'5',	'Camino Internacional, Viña del Mar, Chile',	'Camino Internacional, Viña del Mar, Chile',	'Limache',	'Limache',	'Viña del Mar',	NULL,	'Claudia',	'Claudia',	'',	'jandres@tabolango.cl',	'23809409',	'56923809409',	'',	'',	1,	-32.92807289,	-71.42610466,	0,	NULL),
(53,	0,	NULL,	'La Caleta',	'La Caleta Spa Restaurant',	'77.902.269-2',	'Venta al por menor',	'4',	'Avenida del Mar 2360, Puchuncaví, Chile',	NULL,	NULL,	NULL,	'Puchuncaví',	'',	'',	'',	'',	'sofia@tabolango.cl',	'+56996654318',	'+56996654318',	'',	'',	1,	-32.64931120,	-71.44018680,	0,	NULL),
(55,	0,	NULL,	'probando quinta',	'',	'',	'',	'4',	'El Mar 201, La Laguna, Zapallar, Chile',	NULL,	NULL,	NULL,	'Zapallar',	'Petorca',	'',	'',	'',	'jandres@tabolango.cl',	'',	'',	'',	'',	0,	-32.62306710,	-71.40232210,	0,	NULL),
(56,	0,	NULL,	'Pizzería Mónaco',	'Sociedad Comercial y de Inversiones Monaco Limitada',	'77.209.884-7',	'Arriendo de Bs Inmuebles amoblados, Heladeria, Restaurant, cafeteria',	'4',	'Mónaco Sports Bar - Avenida del Mar, Puchuncaví, Chile',	NULL,	NULL,	NULL,	'Puchuncaví',	'Petorca',	'Nicolas Aguirre',	'Nicolas',	'Aguirre',	'sofia@tabolango.cl',	'+56994409188',	'+56994409188',	'monacomaitencillo@gmail.com',	'monacomaitencillo@gmail.com',	1,	-32.64467760,	-71.43237150,	0,	'https://tabolango.cl/logos/logo_56_1770478147.png'),
(57,	0,	NULL,	'Empanadas Tio Mario',	'Sociedad comercial San Vicente limitada',	'78.111.582-7',	'Venta al por menor',	'4',	'Tio Mario, Restaurant, Puchuncaví, Chile',	'',	'',	'',	'Puchuncaví',	'',	'Paola',	'Paola',	'',	'jandres@tabolango.cl',	'48735810',	'+56948735810',	'Mariomeva@gmail.com',	'Mariomeva@gmail.com',	1,	-32.68672900,	-71.40892600,	0,	NULL),
(58,	0,	'el templo',	'Pizzería El Templo - Quintay',	'JAIME ALBERTO MIRANDA BRAVO HOSPEDAJE Y HOTELERIA EMPRESA INDIVIDUAL DE RESPONSABILIDAD LIMITADA',	'76.406.086-5',	'Actividades de restaurante',	'5',	'Los Plátanos 2662, Viña del Mar, Chile',	'Los Almendros 751',	'Casablanca',	'Quintay',	'Viña del Mar',	'Concón',	'Maria Urrutia',	'Maria',	'Urrutia',	'jandres@tabolango.cl',	'67022155',	'67022155',	'cmiranda95@gmail.com',	'cmiranda95@gmail.com',	1,	-33.03355150,	-71.52632570,	0,	'https://tabolango.cl/logos/logo_21_1768239707.png'),
(59,	1,	'el templo',	'Pizzería El Templo',	'JAIME ALBERTO MIRANDA BRAVO HOSPEDAJE Y HOTELERIA EMPRESA INDIVIDUAL DE RESPONSABILIDAD LIMITADA',	'76.406.086-5',	'Actividades de restaurante',	'5',	'Santa Macarena 280, Concón, Chile',	'Los Almendros 751',	'Casablanca',	'Quintay',	'Concón',	'Concón',	'Carlos Miranda',	'Carlos',	'Miranda',	'jandres@tabolango.cl',	'67022155',	'+56967022155',	'cmiranda95@gmail.com',	'cmiranda95@gmail.com',	1,	-32.94193630,	-71.52124060,	0,	'https://tabolango.cl/logos/logo_21_1768239707.png'),
(60,	0,	NULL,	'Valle Verde',	'Jerónimo Eduardo Velásquez Urrutia',	'5.130.561-2',	'Hostería y Restaurant Valle Verde',	'5',	'Camino Troncal 0388, Villa Alemana, Valparaíso, Chile',	'Camino Troncal 0388',	'Villa Alemana',	'Villa Alemana',	'Villa Alemana',	'',	'Victoria Aros',	'Victoria',	'Aros',	'jandres@tabolango.cl',	'95695695',	'+56995695695',	'eventos@valleverde.cl',	'eventos@valleverde.cl',	1,	-33.03576700,	-71.29507400,	0,	NULL),
(61,	0,	NULL,	'Fiero',	'FIERO FOGONERO SPA',	'77.938.236-2',	'Restaurant',	'5',	'Candelaria Goyenechea 3820, local 192, Vitacura, Chile',	NULL,	NULL,	NULL,	'Vitacura',	'Vitacura',	'Felipe Carey',	NULL,	NULL,	'jandres@tabolango.cl',	'+56989687503',	'+56989687503',	'Facturacion@fiero.cl',	'Facturacion@fiero.cl',	1,	-33.40069080,	-70.59343000,	0,	'https://tabolango.cl/logos/logo_61_1770848963.png'),
(62,	0,	'el templo',	'Pizzería El Templo - Viña del Mar',	'JAIME ALBERTO MIRANDA BRAVO HOSPEDAJE Y HOTELERIA EMPRESA INDIVIDUAL DE RESPONSABILIDAD LIMITADA',	'76.406.086-5',	'Actividades de restaurante',	'5',	'5 Norte 434, Viña del Mar, Chile',	'Los Almendros 751',	'Casablanca',	'Quintay',	'Viña del Mar',	'Viña del Mar',	'',	'Carlos',	'Miranda',	'jandres@tabolango.cl',	'+56967022155',	'+56967022155',	'cmiranda95@gmail.com',	'cmiranda95@gmail.com',	1,	-33.01808520,	-71.55414330,	0,	'https://tabolango.cl/logos/logo_21_1768239707.png'),
(63,	0,	NULL,	'TeleVega',	'Carreño y Garcia Limitada',	'77.593.150-7',	'Venta al por menor',	'1',	'Fariña 411, Recoleta, Chile',	'Fariña 405, Recoleta, Chile',	'Recoleta',	'Recoleta',	'Recoleta',	'Recoleta',	'Luis Sepulveda',	'',	'',	'sofia@tabolango.cl',	'+56971096160',	'+56971096160',	'ventastelevega@gmail.com',	'ventastelevega@gmail.com',	1,	-33.42717420,	-70.65008480,	0,	NULL),
(64,	0,	NULL,	'Carnivorus',	'Sociedad gastronómica Comercial L&M SPA',	'77.726.793-0',	'Restaurante ',	'5',	'Santa Macarena 280, local 2, Concón, Chile',	'Santa Macarena 280, local 2, Concón, Chile',	'Concón',	'Concón',	'Concón',	'Concón',	'',	'',	'',	'sofia@tabolango.cl',	'+56999440786',	'+56999440786',	'carnivorussh@gmail.com',	'carnivorussh@gmail.com',	1,	-32.94193630,	-71.52124060,	0,	NULL),
(65,	0,	NULL,	'INKA MAR',	'Vilchez y Rojas limitada',	'78.319.042-7',	'Restaurant',	'5',	'Inka Mar Cocina Peruana - Viana, Viña del Mar, Chile',	'Viana 419, local 2, Viña del Mar',	'',	'',	'Viña del Mar',	'Viña del Mar',	'Juan Carlos Vilchez',	'Juan Carlos',	'Vilchez',	'sofia@tabolango.cl',	'+56956822371',	'+56956822371',	'inkamarcocinaperuana@gmail.com',	'inkamarcocinaperuana@gmail.com',	1,	-33.02536310,	-71.55691980,	0,	NULL),
(66,	0,	NULL,	'Tomodachi House',	'Soc. Gastronómica, eventos e importaciones Tomodachi House Ltda.',	'76.174.871-8',	'Restaurant',	'3',	'4 Poniente 630, Viña del Mar, Chile',	'4 Poniente 630, Viña del Mar, Chile',	'Viña del Mar',	'Viña del Mar',	'Viña del Mar',	'Viña del Mar',	'Enji',	'Enji',	'',	'sofia@tabolango.cl',	'+56956990905',	'+56956990905',	'info@tomodachihouse.cl',	'info@tomodachihouse.cl',	1,	-33.01535630,	-71.55488840,	0,	NULL);

DROP TABLE IF EXISTS `consumo_combustible`;
CREATE TABLE `consumo_combustible` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folio` int NOT NULL,
  `fecha_emision` date NOT NULL,
  `rut_proveedor` varchar(15) DEFAULT '99520000-7',
  `patente` varchar(10) DEFAULT NULL,
  `tipo_combustible` varchar(50) DEFAULT NULL,
  `litros` decimal(10,3) DEFAULT NULL,
  `precio_litro` decimal(10,2) DEFAULT NULL,
  `monto_total` int DEFAULT NULL,
  `url_xml` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_guia` (`folio`,`rut_proveedor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `dte_emitidos`;
CREATE TABLE `dte_emitidos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pedido` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo_documento` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `folio` int NOT NULL,
  `url_xml` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `url_pdf` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `estado_envio` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'PENDIENTE_SII' COMMENT 'PENDIENTE_SII, ENVIADO, ERROR',
  `track_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `respuesta_api` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Para guardar el log de errores si algo falla',
  `fecha_emision` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `facturas_historial`;
CREATE TABLE `facturas_historial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pedido_sistema` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `folio_sii` int DEFAULT NULL,
  `tipo_documento` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'factura',
  `total_facturado` int DEFAULT '0',
  `url_simpleapi` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ruta_archivo_local` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ruta_xml_local` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `estado_api` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `json_respuesta` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `fecha_emision` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_pedido_sistema` (`id_pedido_sistema`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `facturas_recibidas`;
CREATE TABLE `facturas_recibidas` (
  `id_acuse` int NOT NULL AUTO_INCREMENT,
  `folio` int NOT NULL,
  `fecha_emision` date NOT NULL,
  `proveedor` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rut_proveedor` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_bruto` int NOT NULL,
  `url_pdf` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_xml` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_acuse` enum('PENDIENTE','ACEPTADA','RECHAZADA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'PENDIENTE',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_acuse`),
  UNIQUE KEY `unique_factura` (`rut_proveedor`,`folio`),
  KEY `idx_proveedor` (`proveedor`),
  KEY `idx_rut` (`rut_proveedor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `pedidos_activos`;
CREATE TABLE `pedidos_activos` (
  `id_interno` int NOT NULL AUTO_INCREMENT,
  `id_pedido` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cliente` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `id_interno_cliente` int DEFAULT NULL,
  `producto` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `precio_original` decimal(10,2) DEFAULT NULL,
  `porcentaje_descuento` decimal(5,2) DEFAULT '0.00',
  `monto_descuento` decimal(10,2) DEFAULT '0.00',
  `costo_unitario` decimal(10,2) DEFAULT NULL,
  `total_venta` decimal(10,2) DEFAULT NULL,
  `total_costo` decimal(10,2) DEFAULT NULL,
  `margen` decimal(10,2) DEFAULT NULL,
  `estado` enum('Confirmado','En preparación','En despacho','Entregado') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'Confirmado',
  `fecha_despacho` date DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `qr_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `fecha_ultima_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `precio_por_kilo` decimal(10,2) DEFAULT NULL,
  `numero_factura` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `numero_guia` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `costo_por_kilo` decimal(10,2) DEFAULT NULL,
  `url_factura` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `url_factura_firmada` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `url_guia` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `evidencia_entrega` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `lat_entrega` decimal(10,8) DEFAULT NULL,
  `lng_entrega` decimal(11,8) DEFAULT NULL,
  `lat_preparacion` decimal(10,8) DEFAULT NULL,
  `lng_preparacion` decimal(11,8) DEFAULT NULL,
  `id_sede_interna` int DEFAULT NULL,
  `id_producto` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ultima_edicion` datetime DEFAULT NULL,
  `editado_por` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `whatsapp_enviado` datetime DEFAULT NULL,
  `observacion_entrega` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `estado_nota_credito` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `numero_nc` int DEFAULT '0',
  `url_nc` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `estado_pago` enum('Pendiente','Parcial','Pagado') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'Pendiente',
  `monto_pagado` decimal(12,2) DEFAULT '0.00',
  PRIMARY KEY (`id_interno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
  `id_producto` int NOT NULL AUTO_INCREMENT,
  `producto` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `variedad` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidad` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precio_actual` decimal(10,2) DEFAULT NULL,
  `costo_actual` decimal(10,2) DEFAULT NULL,
  `precio_por_kilo` decimal(10,2) DEFAULT NULL,
  `costo_por_kilo` decimal(10,2) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `color_diferenciador` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#0F4B29',
  `kg_por_unidad` decimal(10,3) DEFAULT '0.000',
  `calibre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formato` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aplica_descuentos` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `productos_precios_categorias`;
CREATE TABLE `productos_precios_categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_producto` int NOT NULL,
  `id_categoria_cliente` int NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `producto_categoria_idx` (`id_producto`,`id_categoria_cliente`),
  CONSTRAINT `productos_precios_categorias_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `productos_tramos_descuento`;
CREATE TABLE `productos_tramos_descuento` (
  `id_producto` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `cantidad_minima` decimal(10,2) NOT NULL,
  `porcentaje_descuento` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id_producto`,`cantidad_minima`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `vehiculo_usuarios`;
CREATE TABLE `vehiculo_usuarios` (
  `id_vinculo` int NOT NULL AUTO_INCREMENT,
  `patente_vehiculo` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `user_email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_vinculo`),
  KEY `patente_vehiculo` (`patente_vehiculo`),
  CONSTRAINT `vehiculo_usuarios_ibfk_1` FOREIGN KEY (`patente_vehiculo`) REFERENCES `vehiculos` (`patente`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `vehiculos`;
CREATE TABLE `vehiculos` (
  `patente` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `marca` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `modelo` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `clase_licencia` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'B',
  `tipo_vehiculo` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `pdf_permiso` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `pdf_soap` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `pdf_revision` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `estado_permiso` tinyint(1) DEFAULT '1',
  `estado_soap` tinyint(1) DEFAULT '1',
  `estado_revision` tinyint(1) DEFAULT '1',
  `venc_permiso` date DEFAULT NULL,
  `venc_soap` date DEFAULT NULL,
  `venc_revision` date DEFAULT NULL,
  `foto` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  PRIMARY KEY (`patente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `wp_commentmeta`;
CREATE TABLE `wp_commentmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `comment_id` (`comment_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_comments`;
CREATE TABLE `wp_comments` (
  `comment_ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_post_ID` bigint unsigned NOT NULL DEFAULT '0',
  `comment_author` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `comment_author_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_author_url` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_author_IP` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `comment_karma` int NOT NULL DEFAULT '0',
  `comment_approved` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '1',
  `comment_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'comment',
  `comment_parent` bigint unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`comment_ID`),
  KEY `comment_post_ID` (`comment_post_ID`),
  KEY `comment_approved_date_gmt` (`comment_approved`,`comment_date_gmt`),
  KEY `comment_date_gmt` (`comment_date_gmt`),
  KEY `comment_parent` (`comment_parent`),
  KEY `comment_author_email` (`comment_author_email`(10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_links`;
CREATE TABLE `wp_links` (
  `link_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `link_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_target` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_visible` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'Y',
  `link_owner` bigint unsigned NOT NULL DEFAULT '1',
  `link_rating` int NOT NULL DEFAULT '0',
  `link_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `link_rel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `link_rss` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`link_id`),
  KEY `link_visible` (`link_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_options`;
CREATE TABLE `wp_options` (
  `option_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `option_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `autoload` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name` (`option_name`),
  KEY `autoload` (`autoload`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_postmeta`;
CREATE TABLE `wp_postmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `post_id` (`post_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_posts`;
CREATE TABLE `wp_posts` (
  `ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_author` bigint unsigned NOT NULL DEFAULT '0',
  `post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_excerpt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'publish',
  `comment_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'open',
  `ping_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'open',
  `post_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `post_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `to_ping` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `pinged` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content_filtered` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_parent` bigint unsigned NOT NULL DEFAULT '0',
  `guid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `menu_order` int NOT NULL DEFAULT '0',
  `post_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'post',
  `post_mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_count` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `post_name` (`post_name`(191)),
  KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
  KEY `post_parent` (`post_parent`),
  KEY `post_author` (`post_author`),
  KEY `type_status_author` (`post_type`,`post_status`,`post_author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_social_users`;
CREATE TABLE `wp_social_users` (
  `social_users_id` int NOT NULL AUTO_INCREMENT,
  `ID` int NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `identifier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `register_date` datetime DEFAULT NULL,
  `login_date` datetime DEFAULT NULL,
  `link_date` datetime DEFAULT NULL,
  PRIMARY KEY (`social_users_id`),
  KEY `ID` (`ID`,`type`),
  KEY `identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_term_relationships`;
CREATE TABLE `wp_term_relationships` (
  `object_id` bigint unsigned NOT NULL DEFAULT '0',
  `term_taxonomy_id` bigint unsigned NOT NULL DEFAULT '0',
  `term_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`object_id`,`term_taxonomy_id`),
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_term_taxonomy`;
CREATE TABLE `wp_term_taxonomy` (
  `term_taxonomy_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint unsigned NOT NULL DEFAULT '0',
  `taxonomy` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `parent` bigint unsigned NOT NULL DEFAULT '0',
  `count` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
  KEY `taxonomy` (`taxonomy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_termmeta`;
CREATE TABLE `wp_termmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `term_id` (`term_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_terms`;
CREATE TABLE `wp_terms` (
  `term_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `slug` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `term_group` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_id`),
  KEY `slug` (`slug`(191)),
  KEY `name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_usermeta`;
CREATE TABLE `wp_usermeta` (
  `umeta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`umeta_id`),
  KEY `user_id` (`user_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_users`;
CREATE TABLE `wp_users` (
  `ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_login` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_pass` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_nicename` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_url` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_status` int NOT NULL DEFAULT '0',
  `display_name` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename` (`user_nicename`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- 2026-05-27 16:48:16 UTC
