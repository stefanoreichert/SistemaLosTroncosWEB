<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'los_troncos');
define('DB_USER', 'root');
define('DB_PASS', '');

// Rango de números de mesa reservados para Delivery
// Delivery 1 = Mesa 101, Delivery 2 = Mesa 102, etc.
define('DELIVERY_BASE', 100);

// Conexión a la base de datos
function getConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Iniciar sesión
session_start();

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['usuario']);
}

// Función para obtener el nivel del usuario actual
function getNivelUsuario() {
    return strtolower($_SESSION['nivel'] ?? 'invitado');
}

// Función para verificar si el usuario tiene un cierto nivel
function tieneNivel($nivel) {
    $nivelActual = getNivelUsuario();
    return $nivelActual === strtolower($nivel);
}

// Función para verificar si el usuario es admin
function esAdmin() {
    return tieneNivel('admin');
}

// Función para requerir autenticación
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

// Agregar columna 'notas' a mesa pedido si no existe (ejecución única silenciosa)
try {
    $tmpConn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $tmpConn->exec("ALTER TABLE `mesa pedido` ADD COLUMN `notas` TEXT NULL DEFAULT NULL");
} catch(Exception $e) { /* columna ya existe, ignorar */ }

// Crear tabla resumenes_diarios si no existe
try {
    $tmpConn2 = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $tmpConn2->exec("CREATE TABLE IF NOT EXISTS `resumenes_diarios` (
        `id`        INT AUTO_INCREMENT PRIMARY KEY,
        `fecha`     DATE        NOT NULL,
        `hora`      TIME        NOT NULL,
        `mesa`      INT         NOT NULL,
        `total`     DECIMAL(10,2) NOT NULL DEFAULT 0,
        `productos` TEXT        NULL,
        INDEX `idx_fecha` (`fecha`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) { /* tabla ya existe o error ignorado */ }

// Crear tabla delivery_ordenes si no existe
try {
    $tmpDelivery = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $tmpDelivery->exec("CREATE TABLE IF NOT EXISTS `delivery_ordenes` (
        `id`                INT AUTO_INCREMENT PRIMARY KEY,
        `id_mozo`           INT         NULL,
        `cliente_nombre`    VARCHAR(100) NULL DEFAULT NULL,
        `cliente_telefono`  VARCHAR(50)  NULL DEFAULT NULL,
        `cliente_direccion` VARCHAR(200) NULL DEFAULT NULL,
        `estado`            ENUM('recibido','preparando','listo') NOT NULL DEFAULT 'recibido',
        `hora_recibido`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `hora_listo`        DATETIME    NULL DEFAULT NULL,
        `notas`             TEXT        NULL DEFAULT NULL,
        `cerrado`           TINYINT(1)  NOT NULL DEFAULT 0,
        INDEX `idx_estado`  (`estado`),
        INDEX `idx_cerrado` (`cerrado`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $tmpDelivery->exec("CREATE TABLE IF NOT EXISTS `delivery_items` (
        `id`              INT AUTO_INCREMENT PRIMARY KEY,
        `id_orden`        INT          NOT NULL,
        `producto_id`     INT          NOT NULL,
        `cantidad`        INT          NOT NULL DEFAULT 1,
        `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `fecha_hora`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_orden` (`id_orden`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $tmpDelivery->exec("CREATE TABLE IF NOT EXISTS `delivery_resumen_diario` (
        `id`             INT AUTO_INCREMENT PRIMARY KEY,
        `fecha`          DATE         NOT NULL,
        `hora`           TIME         NOT NULL,
        `id_orden`       INT          NOT NULL,
        `total`          DECIMAL(10,2) NOT NULL DEFAULT 0,
        `productos`      TEXT         NULL,
        `cliente_nombre` VARCHAR(100) NULL DEFAULT NULL,
        INDEX `idx_fecha` (`fecha`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) { /* tablas delivery ya existen o error ignorado */ }

// Función para requerir nivel específico
function requireNivel($nivel) {
    requireAuth();
    $nivelActual = getNivelUsuario();
    $nivelRequerido = strtolower($nivel);
    
    if ($nivelActual !== $nivelRequerido && $nivelActual !== 'admin') {
        header('HTTP/1.1 403 Forbidden');
        die('Acceso denegado. Tu nivel es: ' . $nivelActual . '. Nivel requerido: ' . $nivelRequerido);
    }
}
?>
