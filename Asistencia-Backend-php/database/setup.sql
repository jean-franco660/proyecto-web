-- ============================================================
-- SISTEMA DE ASISTENCIA — Base de datos final
-- Ejecutar: mysql -u root -p < database/database_setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS asistencia_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE asistencia_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. ADMINISTRADORES WEB (panel de gestión)
-- Roles: administrador (acceso total) | supervisor (solo sus sedes)
-- El administrador se crea directo en BD (seed)
-- Los supervisores los crea el administrador, inician INACTIVO
-- El administrador los activa manualmente desde el panel
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios_web (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150)    NOT NULL,
    email           VARCHAR(150)    NOT NULL UNIQUE,
    password        VARCHAR(255)    NOT NULL,
    rol             ENUM('administrador','supervisor') NOT NULL DEFAULT 'supervisor',
    -- administrador:      siempre ACTIVO
    -- supervisor: inicia INACTIVO, el administrador lo activa manualmente
    estado          ENUM('ACTIVO','INACTIVO')  NOT NULL DEFAULT 'INACTIVO',
    ultimo_login    DATETIME        NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP       NULL,
    INDEX idx_email  (email),
    INDEX idx_rol    (rol),
    INDEX idx_estado (estado)
);

-- ============================================================
-- 2. SEDES (sucursales / locales de la empresa)
-- Se eliminaron: rubro, distrito, provincia, region, logo
-- Una sede sin horarios activos no aparece en selectores
-- ============================================================
CREATE TABLE IF NOT EXISTS sedes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_sede     VARCHAR(50)     NOT NULL UNIQUE,
    nombre          VARCHAR(255)    NOT NULL,
    direccion       VARCHAR(500)    NULL,
    latitud         DECIMAL(10,8)   NOT NULL,
    longitud        DECIMAL(11,8)   NOT NULL,
    radio           INT             NOT NULL DEFAULT 100,
    activa          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP       NULL,
    INDEX idx_codigo (codigo_sede)
);

-- ============================================================
-- 3. RELACIÓN SUPERVISOR ↔ SEDES
-- Permite restringir qué sedes puede ver cada supervisor
-- El admin ve todas las sedes sin necesitar esta tabla
-- ============================================================
CREATE TABLE IF NOT EXISTS usuario_web_sede (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_web_id  INT UNSIGNED    NOT NULL,
    sede_id         INT UNSIGNED    NOT NULL,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    fecha_inicio    DATE            NULL,
    fecha_fin       DATE            NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_web_sede (usuario_web_id, sede_id),
    FOREIGN KEY (usuario_web_id) REFERENCES usuarios_web(id) ON DELETE CASCADE,
    FOREIGN KEY (sede_id)        REFERENCES sedes(id)        ON DELETE CASCADE
);

-- ============================================================
-- 4. HORARIOS DE SEDE (turnos)
-- Una sede puede tener múltiples turnos
-- El empleado se asigna a un turno específico (turno fijo)
-- ============================================================
CREATE TABLE IF NOT EXISTS horarios_sede (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sede_id                     INT UNSIGNED    NOT NULL,
    nombre_turno                VARCHAR(50)     NOT NULL,
    hora_entrada                TIME            NOT NULL,
    hora_salida                 TIME            NOT NULL,
    tolerancia_entrada_minutos  INT             NOT NULL DEFAULT 0,
    tolerancia_salida_minutos   INT             NOT NULL DEFAULT 0,
    dias_semana                 JSON            NOT NULL,  -- ["L","M","X","J","V"]
    activo                      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sede   (sede_id),
    INDEX idx_activo (activo),
    FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE
);

-- ============================================================
-- 5. TRABAJADORES (usuarios de la app móvil)
-- Login con: codigo_empleado + password
-- Reset con: codigo_empleado + dni → admin aprueba
-- estado: ACTIVO | INACTIVO | BLOQUEADO
--   ACTIVO:    trabaja normalmente
--   INACTIVO:  ya no trabaja en la empresa
--   BLOQUEADO: suspendido temporalmente
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios_app (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_empleado         VARCHAR(50)     NOT NULL UNIQUE,
    apellido_paterno        VARCHAR(100)    NOT NULL,
    apellido_materno        VARCHAR(100)    NOT NULL,
    nombres                 VARCHAR(150)    NOT NULL,
    sexo                    ENUM('M','F')   NOT NULL DEFAULT 'M',
    dni                     VARCHAR(20)     NULL UNIQUE,
    fecha_nacimiento        DATE            NULL,
    telefono                VARCHAR(20)     NULL,
    password                VARCHAR(255)    NOT NULL,
    debe_cambiar_password   TINYINT(1)      NOT NULL DEFAULT 0,
    cargo                   VARCHAR(100)    NULL,
    estado                  ENUM('ACTIVO','INACTIVO','BLOQUEADO') NOT NULL DEFAULT 'ACTIVO',
    ultimo_login            DATETIME        NULL,
    foto                    VARCHAR(500)    NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP       NULL,
    INDEX idx_codigo (codigo_empleado),
    INDEX idx_estado (estado)
);

-- ============================================================
-- 6. RELACIÓN TRABAJADOR ↔ SEDE ↔ TURNO
-- horario_sede_id NOT NULL: todo empleado debe tener turno asignado
-- Un empleado puede estar en varias sedes con distintos turnos
-- ============================================================
CREATE TABLE IF NOT EXISTS usuario_app_sede (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id      INT UNSIGNED    NOT NULL,
    sede_id             INT UNSIGNED    NOT NULL,
    horario_sede_id     INT UNSIGNED    NOT NULL,
    cargo               VARCHAR(100)    NULL,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    fecha_inicio        DATE            NULL,
    fecha_fin           DATE            NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_trabajador_sede_horario (usuario_app_id, sede_id, horario_sede_id),
    INDEX idx_estado (estado),
    FOREIGN KEY (usuario_app_id)  REFERENCES usuarios_app(id)  ON DELETE CASCADE,
    FOREIGN KEY (sede_id)         REFERENCES sedes(id)          ON DELETE CASCADE,
    FOREIGN KEY (horario_sede_id) REFERENCES horarios_sede(id)  ON DELETE RESTRICT
);

-- ============================================================
-- 7. ASISTENCIAS (cabecera diaria por trabajador)
-- Se crea un registro por empleado por día
-- horario_sede_id NOT NULL para que el UNIQUE KEY funcione
-- ============================================================
CREATE TABLE IF NOT EXISTS asistencias (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id      INT UNSIGNED    NOT NULL,
    sede_id             INT UNSIGNED    NOT NULL,
    horario_sede_id     INT UNSIGNED    NOT NULL,
    fecha               DATE            NOT NULL,
    hora_entrada        TIME            NULL,
    hora_salida         TIME            NULL,
    minutos_tarde       INT             NOT NULL DEFAULT 0,
    estado_diario       ENUM('FALTA','PRESENTE','TARDANZA','JUSTIFICADO','PENDIENTE')
                        NOT NULL DEFAULT 'PENDIENTE',
    observacion         TEXT            NULL,
    revisado_por        INT UNSIGNED    NULL,
    revisado_en         DATETIME        NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_asistencia_dia (usuario_app_id, sede_id, fecha, horario_sede_id),
    INDEX idx_fecha         (fecha),
    INDEX idx_estado        (estado_diario),
    INDEX idx_usuario_fecha (usuario_app_id, fecha),
    INDEX idx_sede_fecha    (sede_id, fecha),
    FOREIGN KEY (usuario_app_id)  REFERENCES usuarios_app(id)  ON DELETE CASCADE,
    FOREIGN KEY (sede_id)         REFERENCES sedes(id)          ON DELETE CASCADE,
    FOREIGN KEY (horario_sede_id) REFERENCES horarios_sede(id)  ON DELETE RESTRICT,
    FOREIGN KEY (revisado_por)    REFERENCES usuarios_web(id)   ON DELETE SET NULL
);

-- ============================================================
-- 8. MARCACIONES (ENTRADA / SALIDA con GPS y foto)
-- Cada asistencia puede tener 2 marcaciones: ENTRADA y SALIDA
-- Fuera del rango GPS → API retorna 403, NO se guarda registro
-- ============================================================
CREATE TABLE IF NOT EXISTS asistencias_diarias (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asistencia_id       INT UNSIGNED    NOT NULL,
    tipo                ENUM('ENTRADA','SALIDA') NOT NULL,
    marcada_en          DATETIME        NOT NULL,
    latitud             DECIMAL(10,8)   NOT NULL,
    longitud            DECIMAL(11,8)   NOT NULL,
    distancia_metros    INT             NULL,
    estado_marcacion    ENUM('VALIDA','OBSERVADA') NOT NULL DEFAULT 'VALIDA',
    -- VALIDA:    marcó dentro del horario permitido (con tolerancia)
    -- OBSERVADA: marcó fuera de la ventana de horario
    motivo_observacion  VARCHAR(255)    NULL,
    estado_revision     ENUM('PENDIENTE','APROBADA','MANTENER_OBSERVADA') NOT NULL DEFAULT 'APROBADA',
    offline_uuid        VARCHAR(100)    NULL UNIQUE,
    registrado_en       ENUM('APP_ONLINE','APP_OFFLINE') NOT NULL DEFAULT 'APP_ONLINE',
    foto                VARCHAR(500)    NULL,
    revisado_por        INT UNSIGNED    NULL,
    revisado_en         DATETIME        NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_asistencia   (asistencia_id),
    INDEX idx_tipo         (tipo),
    INDEX idx_marcada_en   (marcada_en),
    INDEX idx_estado_rev   (estado_revision),
    INDEX idx_offline_uuid (offline_uuid),
    FOREIGN KEY (asistencia_id) REFERENCES asistencias(id) ON DELETE CASCADE,
    FOREIGN KEY (revisado_por)  REFERENCES usuarios_web(id) ON DELETE SET NULL
);

-- ============================================================
-- 9. JUSTIFICACIONES
-- El empleado la envía desde la app
-- El admin/supervisor aprueba o rechaza desde el panel web
-- dias se calcula en PHP con: date_diff(fecha_inicio, fecha_fin)
-- ============================================================
CREATE TABLE IF NOT EXISTS justificaciones (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id      INT UNSIGNED    NOT NULL,
    asistencia_id       INT UNSIGNED    NULL,
    tipo                ENUM(
                            'ENFERMEDAD','PERMISO_PERSONAL','LICENCIA',
                            'COMISION_SERVICIO','CAPACITACION','DUELO',
                            'MATERNIDAD','PATERNIDAD','OLVIDO_MARCACION','OTRO'
                        ) NOT NULL,
    fecha_inicio        DATE            NOT NULL,
    fecha_fin           DATE            NOT NULL,
    motivo              TEXT            NOT NULL,
    estado              ENUM('PENDIENTE','APROBADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
    usuario_web_id      INT UNSIGNED    NULL,
    fecha_revision      DATETIME        NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_app_id),
    INDEX idx_estado  (estado),
    INDEX idx_fechas  (fecha_inicio, fecha_fin),
    FOREIGN KEY (usuario_app_id) REFERENCES usuarios_app(id)  ON DELETE CASCADE,
    FOREIGN KEY (asistencia_id)  REFERENCES asistencias(id)   ON DELETE SET NULL,
    FOREIGN KEY (usuario_web_id) REFERENCES usuarios_web(id)  ON DELETE SET NULL
);

-- ============================================================
-- 10. FERIADOS
-- NULL en sede_id = aplica a todas las sedes
-- ============================================================
CREATE TABLE IF NOT EXISTS feriados (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha       DATE            NOT NULL UNIQUE,
    nombre      VARCHAR(200)    NOT NULL,
    tipo        ENUM('NACIONAL','LOCAL','EMPRESA') NOT NULL DEFAULT 'NACIONAL',
    sede_id     INT UNSIGNED    NULL,
    activo      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fecha  (fecha),
    INDEX idx_activo (activo),
    FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE SET NULL
);

-- ============================================================
-- 11. RESET DE CONTRASEÑA — SUPERVISORES (flujo por email)
-- 1. Supervisor pone su email
-- 2. API genera token y lo guarda aquí
-- 3. Se envía link al email con el token
-- 4. Supervisor hace clic → cambia su password
-- 5. Se marca usado_en = NOW()
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets_web (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_web_id  INT UNSIGNED    NOT NULL,
    email           VARCHAR(150)    NOT NULL,
    token           VARCHAR(255)    NOT NULL UNIQUE,
    expires_at      DATETIME        NOT NULL,
    usado_en        DATETIME        NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email),
    FOREIGN KEY (usuario_web_id) REFERENCES usuarios_web(id) ON DELETE CASCADE
);

-- ============================================================
-- 12. RESET DE CONTRASEÑA — EMPLEADOS (flujo con aprobación)
-- 1. Empleado pone DNI + código de empleado
-- 2. API verifica que coinciden → crea solicitud PENDIENTE
-- 3. Admin ve la solicitud en el panel web
-- 4. Admin aprueba → genera password temporal
-- 5. Empleado entra con password temporal
-- 6. App obliga a cambiar la password (debe_cambiar_password = 1)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets_app (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id      INT UNSIGNED    NOT NULL,
    dni                 VARCHAR(20)     NOT NULL,
    codigo_empleado     VARCHAR(50)     NOT NULL,
    estado              ENUM('PENDIENTE','APROBADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
    password_temporal   VARCHAR(255)    NULL,
    motivo_rechazo      VARCHAR(255)    NULL,
    aprobado_por        INT UNSIGNED    NULL,
    aprobado_en         DATETIME        NULL,
    usado_en            DATETIME        NULL,
    expires_at          DATETIME        NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_app_id),
    INDEX idx_estado  (estado),
    FOREIGN KEY (usuario_app_id) REFERENCES usuarios_app(id) ON DELETE CASCADE,
    FOREIGN KEY (aprobado_por)   REFERENCES usuarios_web(id) ON DELETE SET NULL
);

-- ============================================================
-- 13. TOKENS DE SESIÓN (JWT)
-- usuario_id puede ser de usuarios_web o usuarios_app
-- se diferencia por tipo_usuario — por eso no tiene FK directa
-- ============================================================
CREATE TABLE IF NOT EXISTS tokens_sesion (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED     NOT NULL,
    tipo_usuario    ENUM('APP','WEB') NOT NULL,
    token           VARCHAR(500)     NOT NULL UNIQUE,
    dispositivo     VARCHAR(255)     NULL,
    ip              VARCHAR(45)      NULL,
    expires_at      DATETIME         NOT NULL,
    revocado        TINYINT(1)       NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token   (token(191)),
    INDEX idx_usuario (usuario_id, tipo_usuario),
    INDEX idx_expires (expires_at)
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DATOS DE PRUEBA (seed)
-- Password de todos: "password"
-- Hash bcrypt: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================================

-- Admin inicia ACTIVO (creado directo en BD)
-- Supervisor inicia INACTIVO (el admin lo activa desde el panel)
INSERT INTO usuarios_web (nombre, email, password, rol, estado) VALUES
('Admin Principal', 'admin@empresa.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador', 'ACTIVO'),
('Supervisor Lima', 'supervisor@empresa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supervisor', 'INACTIVO');

-- Sedes (sin rubro, distrito, provincia, region, logo)
INSERT INTO sedes (codigo_sede, nombre, direccion, latitud, longitud, radio) VALUES
('SEDE-001', 'Sede Central Lima',   'Av. Larco 123',        -12.1219, -77.0282, 100),
('SEDE-002', 'Sucursal San Isidro', 'Calle Las Flores 45',  -12.0978, -77.0353, 150),
('SEDE-003', 'Sucursal Surco',      'Av. Primavera 890',    -12.1367, -76.9924, 100);

-- Horarios de sedes
INSERT INTO horarios_sede (sede_id, nombre_turno, hora_entrada, hora_salida, tolerancia_entrada_minutos, tolerancia_salida_minutos, dias_semana) VALUES
(1, 'Turno Mañana',   '08:00', '13:00', 10, 10, '["L","M","X","J","V"]'),
(1, 'Turno Tarde',    '13:00', '18:00', 10, 10, '["L","M","X","J","V"]'),
(1, 'Turno Noche',    '18:00', '23:00', 15, 15, '["L","M","X","J","V"]'),
(2, 'Turno Completo', '08:00', '17:00', 10, 10, '["L","M","X","J","V"]'),
(3, 'Turno Mañana',   '07:00', '14:00', 10, 10, '["L","M","X","J","V","S"]');

-- Trabajadores
INSERT INTO usuarios_app (codigo_empleado, apellido_paterno, apellido_materno, nombres, sexo, dni, password, cargo) VALUES
('EMP-001', 'García',   'López',   'Carlos Alberto', 'M', '12345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Desarrollador'),
('EMP-002', 'Martínez', 'Rojas',   'Ana Sofía',      'F', '23456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Diseñadora'),
('EMP-003', 'Quispe',   'Flores',  'Pedro José',     'M', '34567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'QA Tester'),
('EMP-004', 'Torres',   'Vega',    'Lucía Carmen',   'F', '45678901', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Project Manager'),
('EMP-005', 'Mendoza',  'Chávez',  'Roberto Luis',   'M', '56789012', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Analista'),
('EMP-006', 'Sánchez',  'Paredes', 'María Elena',    'F', '67890123', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'RRHH');

-- Asignaciones trabajador → sede → turno
INSERT INTO usuario_app_sede (usuario_app_id, sede_id, horario_sede_id, cargo, estado, fecha_inicio) VALUES
(1, 1, 1, 'Desarrollador',   'ACTIVO', '2025-01-01'),
(2, 1, 2, 'Diseñadora',      'ACTIVO', '2025-01-01'),
(3, 2, 4, 'QA Tester',       'ACTIVO', '2025-01-01'),
(4, 1, 1, 'Project Manager', 'ACTIVO', '2025-01-01'),
(5, 3, 5, 'Analista',        'ACTIVO', '2025-01-01'),
(6, 1, 2, 'RRHH',            'ACTIVO', '2025-01-01');

-- Supervisor → sus sedes asignadas
INSERT INTO usuario_web_sede (usuario_web_id, sede_id, activo, fecha_inicio) VALUES
(2, 1, 1, '2025-01-01'),
(2, 2, 1, '2025-01-01');

-- Feriados nacionales 2025
INSERT INTO feriados (fecha, nombre, tipo) VALUES
('2025-01-01', 'Año Nuevo',               'NACIONAL'),
('2025-04-17', 'Jueves Santo',            'NACIONAL'),
('2025-04-18', 'Viernes Santo',           'NACIONAL'),
('2025-05-01', 'Día del Trabajo',         'NACIONAL'),
('2025-06-07', 'Batalla de Arica',        'NACIONAL'),
('2025-06-29', 'San Pedro y San Pablo',   'NACIONAL'),
('2025-07-28', 'Fiestas Patrias',         'NACIONAL'),
('2025-07-29', 'Fiestas Patrias',         'NACIONAL'),
('2025-08-30', 'Santa Rosa de Lima',      'NACIONAL'),
('2025-10-08', 'Combate de Angamos',      'NACIONAL'),
('2025-11-01', 'Día de Todos los Santos', 'NACIONAL'),
('2025-12-08', 'Inmaculada Concepción',   'NACIONAL'),
('2025-12-25', 'Navidad',                 'NACIONAL');

-- Asistencias de ejemplo para hoy
INSERT INTO asistencias (usuario_app_id, sede_id, horario_sede_id, fecha, estado_diario) VALUES
(1, 1, 1, CURDATE(), 'PENDIENTE'),
(2, 1, 2, CURDATE(), 'PENDIENTE'),
(3, 2, 4, CURDATE(), 'PENDIENTE');