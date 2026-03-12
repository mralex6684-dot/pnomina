<?php
// ============================================
// pnomina - Configuración de Base de Datos
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pnomina');
define('DB_CHARSET', 'utf8mb4');

// Configuración general del sistema
define('APP_NAME', 'NominaRest');
define('APP_URL', 'http://localhost/pnomina');
define('APP_VERSION', '1.0.0');

// Zona horaria Colombia
date_default_timezone_set('America/Bogota');

// Conexión PDO
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
