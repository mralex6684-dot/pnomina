-- ============================================================
-- pnomina - SOLUCIÓN COMPLETA
-- Ejecuta TODO este script en phpMyAdmin de una vez
-- Pestaña SQL → pegar → Ejecutar
-- ============================================================

USE pnomina;

-- PASO 1: Borrar usuarios duplicados o de prueba que no sirven
-- (solo borra los que no sean admin ni supervisor)
DELETE FROM usuarios WHERE email NOT IN (
    'admin@pnomina.com', 
    'supervisor@pnomina.com'
) AND rol_id NOT IN (1, 2);

-- PASO 2: Insertar usuarios para cada empleado
-- El email DEBE coincidir exactamente con el de la tabla empleados
-- Contraseña de todos: password

INSERT INTO usuarios (nombre, email, password, rol_id) VALUES
('Juan Pérez',       'juan.perez@email.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
('María García',     'maria.garcia@email.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
('Carlos López',     'carlos.lopez@email.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
('Ana Martínez',     'ana.martinez@email.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
('Pedro Rodríguez',  'pedro.rodriguez@email.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
('Laura Sánchez',    'laura.sanchez@email.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
('Diego Torres',     'diego.torres@email.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
('Valentina Vargas', 'valentina.vargas@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3);

-- PASO 3: Verificar que quedó bien
SELECT 
    u.nombre as usuario,
    u.email,
    r.nombre as rol,
    IFNULL(CONCAT(e.nombres,' ',e.apellidos), '⚠️ SIN EMPLEADO VINCULADO') as empleado_vinculado
FROM usuarios u
JOIN roles_usuario r ON u.rol_id = r.id
LEFT JOIN empleados e ON e.email = u.email AND e.activo = 1
ORDER BY r.id, u.nombre;

