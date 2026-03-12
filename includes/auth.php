<?php
// ============================================
// pnomina - Funciones de Autenticación
// ============================================

require_once __DIR__ . '/../config/database.php';

function iniciarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function estaAutenticado() {
    iniciarSesion();
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

function requiereLogin() {
    if (!estaAutenticado()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requiereRol($rolesPermitidos) {
    requiereLogin();
    if (!in_array($_SESSION['rol_nombre'], (array)$rolesPermitidos)) {
        header('Location: ' . APP_URL . '/index.php?error=sin_permiso');
        exit;
    }
}

function tienePermiso($permiso) {
    if (!estaAutenticado()) return false;
    $permisos = $_SESSION['permisos'] ?? [];
    return isset($permisos[$permiso]) && $permisos[$permiso] === true;
}

// Es usuario normal (Empleado): no es Administrador ni Supervisor
function esUsuarioNormal() {
    iniciarSesion();
    $rol = $_SESSION['rol_nombre'] ?? '';
    return ($rol !== 'Administrador' && $rol !== 'Supervisor');
}

function login($email, $password) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, r.nombre as rol_nombre, r.permisos 
        FROM usuarios u 
        JOIN roles_usuario r ON u.rol_id = r.id 
        WHERE u.email = ? AND u.activo = 1
    ");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($password, $usuario['password'])) {
        iniciarSesion();
        $_SESSION['usuario_id']     = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_email']  = $usuario['email'];
        $_SESSION['rol_id']         = $usuario['rol_id'];
        $_SESSION['rol_nombre']     = $usuario['rol_nombre'];
        $_SESSION['permisos']       = json_decode($usuario['permisos'], true);

        // Vincular empleado automáticamente por email
        $emp = $db->prepare("SELECT id FROM empleados WHERE email = ? AND activo = 1 LIMIT 1");
        $emp->execute([$usuario['email']]);
        $empleado = $emp->fetch();
        $_SESSION['empleado_id'] = $empleado ? $empleado['id'] : null;

        $db->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$usuario['id']]);
        registrarHistorial('login', 'auth', 'Inicio de sesión exitoso');
        return true;
    }
    return false;
}

function logout() {
    iniciarSesion();
    registrarHistorial('logout', 'auth', 'Cierre de sesión');
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

function registrarHistorial($accion, $modulo, $descripcion = '') {
    try {
        $db = getDB();
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $db->prepare("INSERT INTO historial (usuario_id, accion, modulo, descripcion, ip) VALUES (?,?,?,?,?)")
           ->execute([$usuario_id, $accion, $modulo, $descripcion, $ip]);
    } catch (Exception $e) { /* Silencioso */ }
}

function formatCOP($valor) {
    return '$ ' . number_format($valor, 0, ',', '.');
}

function getNombreMes($mes) {
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
              'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    return $meses[(int)$mes] ?? '';
}
