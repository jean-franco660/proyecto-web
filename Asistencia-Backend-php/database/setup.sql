
CREATE DATABASE IF NOT EXISTS asistencia_pro
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE asistencia_pro;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. CATALOGOS
-- ============================================================

CREATE TABLE estados_usuario (
    id TINYINT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE
);

INSERT INTO estados_usuario VALUES
(1,'ACTIVO'), (2,'INACTIVO'), (3,'BLOQUEADO');

CREATE TABLE roles (
    id TINYINT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE
);

INSERT INTO roles VALUES
(1,'ADMIN'), (2,'SUPERVISOR'), (3,'TRABAJADOR');

-- ============================================================
-- MEJORA 1: estados_justificacion SEPARADO
-- Antes: justificaciones.estado_id → estados_asistencia
--        lo que permitía estados como PRESENTE o TARDANZA
--        en una justificación, lo cual no tiene sentido.
-- Ahora: tabla propia con el ciclo de vida correcto.
-- ============================================================

CREATE TABLE estados_justificacion (
    id TINYINT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE
);

INSERT INTO estados_justificacion VALUES
(1,'PENDIENTE'),
(2,'APROBADA'),
(3,'RECHAZADA');

-- ============================================================
-- 2. USUARIOS (BASE)
-- ============================================================

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) UNIQUE NULL,
    codigo_empleado VARCHAR(50) UNIQUE NULL,
    password VARCHAR(255) NOT NULL,
    debe_cambiar_password BOOLEAN DEFAULT FALSE,
    verification_code VARCHAR(10) NULL,
    verification_expires_at DATETIME NULL,
    estado_id TINYINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (estado_id) REFERENCES estados_usuario(id),

    INDEX (email),
    INDEX (codigo_empleado),
    INDEX (estado_id)
);

-- ============================================================
-- 3. RBAC (ROLES Y PERMISOS)
-- ============================================================

CREATE TABLE usuario_roles (
    usuario_id INT UNSIGNED,
    rol_id TINYINT,

    PRIMARY KEY (usuario_id, rol_id),

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- ============================================================
-- 4. PERFILES
-- ============================================================

CREATE TABLE usuarios_trabajador (
    usuario_id INT UNSIGNED PRIMARY KEY,
    nombres VARCHAR(150) NOT NULL,
    apellidos VARCHAR(150) NOT NULL,
    dni VARCHAR(20) UNIQUE,
    telefono VARCHAR(20),
    foto VARCHAR(255),
    fecha_nacimiento DATE NULL,

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE usuarios_staff (
    usuario_id INT UNSIGNED PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- ============================================================
-- 5. SEDES
-- ============================================================

CREATE TABLE sedes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE,
    nombre VARCHAR(255) NOT NULL,
    direccion VARCHAR(500),
    latitud DECIMAL(10,8) NOT NULL,
    longitud DECIMAL(11,8) NOT NULL,
    radio_metros INT NOT NULL DEFAULT 100,
    activo BOOLEAN DEFAULT TRUE,

    INDEX (codigo)
);

-- ============================================================
-- 6. HORARIOS
-- ============================================================

CREATE TABLE horarios_sede (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sede_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(50),
    hora_entrada TIME NOT NULL,
    hora_salida TIME NOT NULL,
    tolerancia_entrada INT DEFAULT 0,
    tolerancia_salida INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,

    FOREIGN KEY (sede_id) REFERENCES sedes(id)
);

-- ============================================================
-- MEJORA 5: horario_id como INT UNSIGNED
-- Antes: horario_dias.horario_id era INT (sin UNSIGNED)
--        pero horarios_sede.id es INT UNSIGNED.
--        Tipos distintos impiden usar índices eficientemente.
-- Ahora: ambos son INT UNSIGNED, tipos consistentes.
-- ============================================================

CREATE TABLE horario_dias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    horario_id INT UNSIGNED NOT NULL,
    dia TINYINT NOT NULL, -- 1=Lunes ... 7=Domingo

    FOREIGN KEY (horario_id) REFERENCES horarios_sede(id),
    INDEX (horario_id, dia)
);

-- ============================================================
-- 7. ASIGNACION USUARIO-SEDE-HORARIO
-- MEJORA 2: fecha_inicio / fecha_fin
-- Antes: solo había estado BOOLEAN, sin historial de cuándo
--        estuvo el trabajador en cada sede. Un traslado borraba
--        el dato histórico rompiendo reportes del pasado.
-- Ahora: fecha_inicio obligatorio, fecha_fin NULL = vigente.
-- ============================================================

CREATE TABLE usuario_sede (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    sede_id INT UNSIGNED NOT NULL,
    horario_id INT UNSIGNED NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL, -- NULL = asignación actualmente vigente
    estado BOOLEAN DEFAULT TRUE,

    UNIQUE(usuario_id, sede_id, horario_id, fecha_inicio),

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE RESTRICT,
    FOREIGN KEY (horario_id) REFERENCES horarios_sede(id) ON DELETE RESTRICT,

    INDEX (usuario_id, fecha_inicio, fecha_fin)
);


CREATE TABLE estados_asistencia (
    id TINYINT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE
);

INSERT INTO estados_asistencia VALUES
(1,'PENDIENTE'), (2,'PRESENTE'), (3,'TARDANZA'),
(4,'FALTA'), (5,'JUSTIFICADO');

CREATE TABLE asistencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_sede_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    estado_id TINYINT NOT NULL,
    minutos_tarde INT DEFAULT 0,
    modified_by INT UNSIGNED NULL,
    modified_at TIMESTAMP NULL,

    UNIQUE(usuario_sede_id, fecha),

    FOREIGN KEY (usuario_sede_id) REFERENCES usuario_sede(id) ON DELETE CASCADE,
    FOREIGN KEY (estado_id) REFERENCES estados_asistencia(id),
    FOREIGN KEY (modified_by) REFERENCES usuarios(id) ON DELETE SET NULL,

    INDEX (fecha),
    INDEX (usuario_sede_id, fecha),
    INDEX (estado_id, fecha)
);

-- ============================================================
-- MEJORA 4 (continuación): historial completo de cambios
-- ============================================================

CREATE TABLE asistencias_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asistencia_id INT UNSIGNED NOT NULL,
    estado_anterior_id TINYINT NOT NULL,
    estado_nuevo_id TINYINT NOT NULL,
    modified_by INT UNSIGNED NOT NULL,
    modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observacion VARCHAR(500) NULL,

    FOREIGN KEY (asistencia_id) REFERENCES asistencias(id) ON DELETE CASCADE,
    FOREIGN KEY (estado_anterior_id) REFERENCES estados_asistencia(id),
    FOREIGN KEY (estado_nuevo_id) REFERENCES estados_asistencia(id),
    FOREIGN KEY (modified_by) REFERENCES usuarios(id),

    INDEX (asistencia_id),
    INDEX (modified_by),
    INDEX (modified_at)
);


CREATE TABLE tipos_marcacion (
    id TINYINT PRIMARY KEY,
    nombre VARCHAR(20) UNIQUE
);

INSERT INTO tipos_marcacion VALUES
(1,'ENTRADA'), (2,'SALIDA');

CREATE TABLE marcaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asistencia_id INT UNSIGNED NOT NULL,
    tipo_id TINYINT NOT NULL,
    fecha_hora DATETIME NOT NULL,
    latitud DECIMAL(10,8),
    longitud DECIMAL(11,8),
    distancia INT,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    observacion VARCHAR(255),

    -- Solo puede haber una marcación por tipo en cada segundo de la asistencia
    UNIQUE(asistencia_id, tipo_id, fecha_hora),

    FOREIGN KEY (asistencia_id) REFERENCES asistencias(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_id) REFERENCES tipos_marcacion(id),

    INDEX (asistencia_id, tipo_id),
    INDEX (fecha_hora)
);

-- ============================================================
-- 10. JUSTIFICACIONES
-- MEJORA 1 (aplicada): estado_id → estados_justificacion
-- ============================================================

CREATE TABLE justificaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    asistencia_id INT UNSIGNED NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    motivo TEXT,
    estado_id TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (asistencia_id) REFERENCES asistencias(id),
    FOREIGN KEY (estado_id) REFERENCES estados_justificacion(id),

    INDEX (usuario_id),
    INDEX (estado_id),
    INDEX (fecha_inicio, fecha_fin)
);

-- ============================================================
-- 11. TOKENS
-- ============================================================

CREATE TABLE tokens_web (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token VARCHAR(500) UNIQUE,
    expires_at DATETIME,

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX (usuario_id)
);

CREATE TABLE tokens_app (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token VARCHAR(500) UNIQUE,
    expires_at DATETIME,

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX (usuario_id)
);

-- ============================================================
-- 12. RATE LIMITING
-- ============================================================

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45),
    endpoint VARCHAR(50),
    intentos INT DEFAULT 1,
    ultimo_intento DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    bloqueado_hasta DATETIME,

    INDEX (ip, endpoint)
);

CREATE TABLE password_resets_app (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    estado ENUM('PENDIENTE', 'APROBADA', 'RECHAZADA') DEFAULT 'PENDIENTE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX (usuario_id),
    INDEX (estado)
);

CREATE TABLE departamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) UNIQUE NOT NULL,
    descripcion VARCHAR(500) NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO departamentos (nombre, descripcion) VALUES
('Recursos Humanos', 'Área de gestión de personal'),
('Tecnología de Información', 'Soporte y desarrollo de sistemas'),
('Operaciones', 'Área operativa y logística');

CREATE TABLE feriados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    tipo ENUM('NACIONAL', 'LOCAL', 'EMPRESA') NOT NULL DEFAULT 'NACIONAL',
    sede_id INT UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (fecha),
    INDEX (activo),
    FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE SET NULL
);

SET FOREIGN_KEY_CHECKS = 1;


