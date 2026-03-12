-- ============================================
-- pnomina - Base de datos del sistema de nómina
-- Compatible con MySQL / XAMPP
-- ============================================

CREATE DATABASE IF NOT EXISTS pnomina CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pnomina;

-- Tabla de roles de usuario
CREATE TABLE roles_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    permisos JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de usuarios del sistema
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    ultimo_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles_usuario(id)
);

-- Tabla de cargos/posiciones
CREATE TABLE cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    salario_base DECIMAL(15,2) NOT NULL DEFAULT 0,
    departamento VARCHAR(100),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de empleados
CREATE TABLE empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) NOT NULL UNIQUE,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    email VARCHAR(150),
    telefono VARCHAR(20),
    cargo_id INT NOT NULL,
    fecha_ingreso DATE NOT NULL,
    salario DECIMAL(15,2) NOT NULL,
    tipo_contrato ENUM('indefinido','fijo','temporal','obra') DEFAULT 'indefinido',
    cuenta_bancaria VARCHAR(50),
    banco VARCHAR(100),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id)
);

-- Tabla de nóminas generadas
CREATE TABLE nominas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    periodo_mes INT NOT NULL,
    periodo_anio INT NOT NULL,
    salario_base DECIMAL(15,2) NOT NULL,
    horas_extra INT DEFAULT 0,
    valor_horas_extra DECIMAL(15,2) DEFAULT 0,
    bonificaciones DECIMAL(15,2) DEFAULT 0,
    deducciones_salud DECIMAL(15,2) DEFAULT 0,
    deducciones_pension DECIMAL(15,2) DEFAULT 0,
    otras_deducciones DECIMAL(15,2) DEFAULT 0,
    total_devengado DECIMAL(15,2) NOT NULL,
    total_deducciones DECIMAL(15,2) NOT NULL,
    total_neto DECIMAL(15,2) NOT NULL,
    estado ENUM('pendiente','aprobado','pagado','rechazado') DEFAULT 'pendiente',
    aprobado_por INT NULL,
    fecha_pago DATE NULL,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id),
    FOREIGN KEY (aprobado_por) REFERENCES usuarios(id)
);

-- Tabla de pagos
CREATE TABLE pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomina_id INT NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    metodo_pago ENUM('transferencia','efectivo','cheque') DEFAULT 'transferencia',
    referencia VARCHAR(100),
    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    registrado_por INT,
    FOREIGN KEY (nomina_id) REFERENCES nominas(id),
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
);

-- Tabla de historial/auditoría
CREATE TABLE historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(100) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    descripcion TEXT,
    ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- ============================================
-- DATOS INICIALES
-- ============================================

INSERT INTO roles_usuario (nombre, descripcion, permisos) VALUES
('Administrador', 'Acceso total al sistema', '{"dashboard":true,"empleados":true,"roles":true,"nomina":true,"pagos":true,"historial":true,"reportes":true}'),
('Supervisor', 'Puede ver y aprobar nóminas', '{"dashboard":true,"empleados":true,"roles":false,"nomina":true,"pagos":false,"historial":false,"reportes":true}'),
('Empleado', 'Solo puede ver su propia nómina', '{"dashboard":false,"empleados":false,"roles":false,"nomina":false,"pagos":false,"historial":false,"reportes":false}');

-- Contraseña: Admin123! (bcrypt)
INSERT INTO usuarios (nombre, email, password, rol_id) VALUES
('Administrador NominaRest', 'admin@pnomina.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('Supervisor General', 'supervisor@pnomina.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2);

INSERT INTO cargos (nombre, descripcion, salario_base, departamento) VALUES
('Cocinero', 'Chef y asistente de cocina', 2200000, 'Cocina'),
('Mesero', 'Atención al cliente en sala', 1500000, 'Servicio'),
('Cajero', 'Manejo de caja y facturación', 1800000, 'Administrativo'),
('Administrador', 'Gestión general del restaurante', 4500000, 'Gerencia'),
('Bartender', 'Preparación de bebidas', 2000000, 'Bar'),
('Auxiliar Cocina', 'Apoyo en cocina', 1160000, 'Cocina'),
('Supervisor', 'Supervisión de personal', 3200000, 'Gerencia'),
('Domiciliario', 'Entregas a domicilio', 1200000, 'Logística');

INSERT INTO empleados (cedula, nombres, apellidos, email, telefono, cargo_id, fecha_ingreso, salario, tipo_contrato) VALUES
('1000111222', 'Juan', 'Pérez', 'juan.perez@email.com', '3001234567', 1, '2023-01-15', 2200000, 'indefinido'),
('1000222333', 'María', 'García', 'maria.garcia@email.com', '3012345678', 2, '2023-03-01', 1500000, 'indefinido'),
('1000333444', 'Carlos', 'López', 'carlos.lopez@email.com', '3023456789', 3, '2023-06-10', 1800000, 'fijo'),
('1000444555', 'Ana', 'Martínez', 'ana.martinez@email.com', '3034567890', 2, '2024-01-20', 1500000, 'indefinido'),
('1000555666', 'Pedro', 'Rodríguez', 'pedro.rodriguez@email.com', '3045678901', 1, '2022-11-05', 2400000, 'indefinido'),
('1000666777', 'Laura', 'Sánchez', 'laura.sanchez@email.com', '3056789012', 5, '2023-08-15', 2000000, 'indefinido'),
('1000777888', 'Diego', 'Torres', 'diego.torres@email.com', '3067890123', 6, '2024-02-01', 1160000, 'temporal'),
('1000888999', 'Valentina', 'Vargas', 'valentina.vargas@email.com', '3078901234', 2, '2023-09-12', 1550000, 'indefinido');

INSERT INTO nominas (empleado_id, periodo_mes, periodo_anio, salario_base, horas_extra, valor_horas_extra, deducciones_salud, deducciones_pension, total_devengado, total_deducciones, total_neto, estado, fecha_pago) VALUES
(1, 1, 2026, 2200000, 8, 110000, 88000, 88000, 2310000, 176000, 2134000, 'pagado', '2026-01-31'),
(2, 1, 2026, 1500000, 0, 0, 60000, 60000, 1500000, 120000, 1380000, 'pagado', '2026-01-31'),
(3, 1, 2026, 1800000, 4, 45000, 72000, 72000, 1845000, 144000, 1701000, 'aprobado', NULL),
(4, 1, 2026, 1500000, 0, 0, 60000, 60000, 1500000, 120000, 1380000, 'pendiente', NULL),
(5, 1, 2026, 2400000, 12, 150000, 96000, 96000, 2550000, 192000, 2358000, 'pagado', '2026-01-31');
