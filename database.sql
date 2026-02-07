-- Base de datos para Sistema de Restaurante Los Troncos
-- Ejecutar este script en phpMyAdmin o MySQL Workbench

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS `los_troncos` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `los_troncos`;

-- Tabla de productos
CREATE TABLE IF NOT EXISTS `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de pedidos por mesa
CREATE TABLE IF NOT EXISTS `mesa pedido` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mesa` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) DEFAULT 0,
  `id_mozo` int(11),
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mesa` (`mesa`),
  KEY `idx_producto` (`producto_id`),
  KEY `idx_fecha` (`fecha_hora`),
  CONSTRAINT `fk_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar productos de ejemplo
INSERT INTO `productos` (`nombre`, `tipo`, `categoria`, `precio`, `stock`) VALUES
-- Bebidas
('Coca Cola 500ml', 'Bebida', 'Gaseosa', 2.50, 100),
('Pepsi 500ml', 'Bebida', 'Gaseosa', 2.50, 100),
('Sprite 500ml', 'Bebida', 'Gaseosa', 2.50, 100),
('Fanta 500ml', 'Bebida', 'Gaseosa', 2.50, 100),
('Agua Mineral', 'Bebida', 'Agua', 1.50, 150),
('Agua con Gas', 'Bebida', 'Agua', 1.50, 150),
('Cerveza Corona', 'Bebida', 'Cerveza', 4.50, 80),
('Cerveza Heineken', 'Bebida', 'Cerveza', 4.50, 80),
('Jugo de Naranja', 'Bebida', 'Jugo Natural', 3.50, 50),
('Jugo de Piña', 'Bebida', 'Jugo Natural', 3.50, 50),

-- Comidas - Entradas
('Papas Fritas', 'Comida', 'Entrada', 5.00, 80),
('Aros de Cebolla', 'Comida', 'Entrada', 5.50, 60),
('Alitas de Pollo', 'Comida', 'Entrada', 8.50, 70),
('Nachos con Queso', 'Comida', 'Entrada', 7.00, 50),
('Ensalada César', 'Comida', 'Ensalada', 6.50, 40),
('Ensalada Mixta', 'Comida', 'Ensalada', 5.50, 40),

-- Comidas - Platos Principales
('Hamburguesa Clásica', 'Comida', 'Hamburguesa', 12.00, 60),
('Hamburguesa con Queso', 'Comida', 'Hamburguesa', 13.50, 60),
('Hamburguesa BBQ', 'Comida', 'Hamburguesa', 14.00, 50),
('Pizza Margarita', 'Comida', 'Pizza', 15.00, 40),
('Pizza Pepperoni', 'Comida', 'Pizza', 16.50, 40),
('Pizza Hawaiana', 'Comida', 'Pizza', 16.00, 40),
('Pasta Carbonara', 'Comida', 'Pasta', 13.00, 35),
('Pasta Boloñesa', 'Comida', 'Pasta', 13.00, 35),
('Milanesa con Papas', 'Comida', 'Carne', 14.50, 30),
('Bife de Chorizo', 'Comida', 'Carne', 18.00, 25),
('Pollo a la Parrilla', 'Comida', 'Pollo', 13.50, 30),
('Suprema Napolitana', 'Comida', 'Pollo', 15.00, 30),

-- Postres
('Helado 3 Sabores', 'Postre', 'Helado', 6.00, 50),
('Flan con Crema', 'Postre', 'Casero', 5.50, 30),
('Torta Chocolate', 'Postre', 'Torta', 7.00, 20),
('Panqueques con Dulce', 'Postre', 'Casero', 6.50, 25),
('Brownie con Helado', 'Postre', 'Chocolate', 8.00, 20),

-- Cafetería
('Café Expreso', 'Bebida', 'Café', 2.50, 100),
('Café con Leche', 'Bebida', 'Café', 3.00, 100),
('Capuccino', 'Bebida', 'Café', 3.50, 80),
('Té Verde', 'Bebida', 'Té', 2.50, 80),
('Té Negro', 'Bebida', 'Té', 2.50, 80),
('Chocolate Caliente', 'Bebida', 'Caliente', 4.00, 60);

-- Tabla de notificaciones para mozo
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) NOT NULL,
  `mensaje` varchar(255) NOT NULL,
  `mesa` int(11),
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_leido` (`leido`),
  KEY `idx_fecha` (`fecha_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mensaje de confirmación
SELECT 'Base de datos creada exitosamente!' as Status;
SELECT COUNT(*) as 'Total de Productos Insertados' FROM productos;
