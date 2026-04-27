-- ============================================================
-- SISTEMA DE ASISTENCIA — ESQUEMA COMPLETO Y DEFINITIVO
-- ============================================================

CREATE DATABASE IF NOT EXISTS sistemas_asistencia_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE sistemas_asistencia_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. USUARIOS
-- ============================================================
CREATE TABLE usuarios (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    codigo                  VARCHAR(50)  NULL UNIQUE,
    email                   VARCHAR(150) NULL UNIQUE,

    nombres                 VARCHAR(150) NOT NULL,
    apellido_paterno        VARCHAR(100) NULL,
    apellido_materno        VARCHAR(100) NULL,

    password                VARCHAR(255) NOT NULL,
    password_temporal       TINYINT(1)   NOT NULL DEFAULT 0,

    dni                     VARCHAR(20)  NULL UNIQUE,
    telefono                VARCHAR(20)  NULL,
    foto                    VARCHAR(500) NULL,

    estado                  ENUM('ACTIVO','INACTIVO','BLOQUEADO') NOT NULL DEFAULT 'ACTIVO',

    ultimo_login            DATETIME NULL,

    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP NULL,

    INDEX idx_usuarios_codigo  (codigo),
    INDEX idx_usuarios_email   (email),
    INDEX idx_usuarios_estado  (estado),
    INDEX idx_usuarios_dni     (dni)
);

-- ============================================================
-- 2. ROLES
-- ============================================================
CREATE TABLE roles (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO roles (nombre) VALUES
('ADMINISTRADOR'),
('SUPERVISOR'),
('EMPLEADO');

-- ============================================================
-- 3. USUARIO ↔ ROL
-- ============================================================
CREATE TABLE usuario_roles (
    usuario_id INT UNSIGNED NOT NULL,
    rol_id     INT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, rol_id),
    CONSTRAINT fk_ur_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_rol     FOREIGN KEY (rol_id)     REFERENCES roles(id)    ON DELETE CASCADE
);

-- ============================================================
-- 4. DEPARTAMENTOS / ÁREAS
-- ============================================================
CREATE TABLE departamentos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- 5. SEDES
-- ============================================================
CREATE TABLE sedes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_sede     VARCHAR(50)   NOT NULL UNIQUE,
    nombre          VARCHAR(255)  NOT NULL,
    direccion       VARCHAR(500)  NULL,
    latitud         DECIMAL(10,8) NOT NULL,
    longitud        DECIMAL(11,8) NOT NULL,
    radio           INT           NOT NULL DEFAULT 100,  -- metros de tolerancia GPS
    activa          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- 6. SUPERVISOR ↔ SEDE
-- Un supervisor puede gestionar una o varias sedes
-- ============================================================
CREATE TABLE usuario_sede (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    sede_id     INT UNSIGNED NOT NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,

    UNIQUE KEY uq_usuario_sede (usuario_id, sede_id),
    CONSTRAINT fk_us_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_us_sede    FOREIGN KEY (sede_id)    REFERENCES sedes(id)    ON DELETE CASCADE
);

-- ============================================================
-- 7. HORARIOS DE SEDE
-- ============================================================
CREATE TABLE horarios_sede (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sede_id                     INT UNSIGNED NOT NULL,
    nombre_turno                VARCHAR(50)  NOT NULL,

    hora_entrada                TIME NOT NULL,
    hora_salida                 TIME NOT NULL,

    tolerancia_entrada_minutos  INT NOT NULL DEFAULT 0,
    tolerancia_salida_minutos   INT NOT NULL DEFAULT 0,

    activo                      TINYINT(1) NOT NULL DEFAULT 1,

    CONSTRAINT fk_hs_sede FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,

    INDEX idx_hs_sede (sede_id)
);

-- ============================================================
-- 8. HORARIO ↔ DÍAS
-- ============================================================
CREATE TABLE horario_dias (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    horario_sede_id INT UNSIGNED NOT NULL,
    dia             ENUM('L','M','X','J','V','S','D') NOT NULL,

    UNIQUE KEY uq_horario_dia (horario_sede_id, dia),
    CONSTRAINT fk_hd_horario FOREIGN KEY (horario_sede_id) REFERENCES horarios_sede(id) ON DELETE CASCADE
);

-- ============================================================
-- 9. EMPLEADO ↔ SEDE ↔ TURNO ↔ DEPARTAMENTO
-- ============================================================
CREATE TABLE usuario_app_sede (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NOT NULL,
    sede_id         INT UNSIGNED NOT NULL,
    horario_sede_id INT UNSIGNED NOT NULL,
    departamento_id INT UNSIGNED NULL,
    estado          ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',

    -- un empleado solo puede tener UN turno por sede
    UNIQUE KEY uq_usuario_sede (usuario_id, sede_id),

    CONSTRAINT fk_uas_usuario      FOREIGN KEY (usuario_id)      REFERENCES usuarios(id)      ON DELETE CASCADE,
    CONSTRAINT fk_uas_sede         FOREIGN KEY (sede_id)         REFERENCES sedes(id)         ON DELETE CASCADE,
    CONSTRAINT fk_uas_horario      FOREIGN KEY (horario_sede_id) REFERENCES horarios_sede(id) ON DELETE RESTRICT,
    CONSTRAINT fk_uas_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE SET NULL
);

-- ============================================================
-- 10. FERIADOS
-- sede_id NULL = feriado nacional (aplica a todas las sedes)
-- sede_id con valor = feriado solo para esa sede
-- ============================================================
CREATE TABLE feriados (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha    DATE         NOT NULL,
    nombre   VARCHAR(200) NOT NULL,
    tipo     ENUM('NACIONAL','LOCAL','EMPRESA') NOT NULL DEFAULT 'NACIONAL',
    -- NACIONAL: aplica a todas las sedes (sede_id NULL)
    -- LOCAL / EMPRESA: solo para la sede indicada
    sede_id  INT UNSIGNED NULL,
    activo   TINYINT(1)   NOT NULL DEFAULT 1,

    UNIQUE KEY uq_feriado (fecha, sede_id),
    CONSTRAINT fk_fer_sede FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,

    INDEX idx_feriados_sede_fecha (sede_id, fecha),
    INDEX idx_feriados_tipo       (tipo)
);

-- ============================================================
-- 11. ASISTENCIAS (RESUMEN DIARIO)
-- Un registro por empleado por día por turno
-- ============================================================
CREATE TABLE asistencias (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NOT NULL,
    sede_id         INT UNSIGNED NOT NULL,
    horario_sede_id INT UNSIGNED NOT NULL,
    fecha           DATE         NOT NULL,

    hora_entrada    TIME NULL,
    hora_salida     TIME NULL,
    minutos_tarde   INT  NOT NULL DEFAULT 0,

    minutos_trabajados INT NULL,

    estado_diario   ENUM('PENDIENTE','PRESENTE','TARDANZA','FALTA','JUSTIFICADO','FERIADO')
                    NOT NULL DEFAULT 'PENDIENTE',

    observacion     TEXT NULL,

    revisado_por    INT UNSIGNED NULL,
    revisado_en     DATETIME NULL,

    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_asistencia (usuario_id, sede_id, fecha, horario_sede_id),

    CONSTRAINT fk_asis_usuario  FOREIGN KEY (usuario_id)      REFERENCES usuarios(id)      ON DELETE CASCADE,
    CONSTRAINT fk_asis_sede     FOREIGN KEY (sede_id)         REFERENCES sedes(id)         ON DELETE CASCADE,
    CONSTRAINT fk_asis_horario  FOREIGN KEY (horario_sede_id) REFERENCES horarios_sede(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asis_revisor  FOREIGN KEY (revisado_por)    REFERENCES usuarios(id)      ON DELETE SET NULL,

    INDEX idx_asis_usuario_fecha  (usuario_id, fecha),
    INDEX idx_asis_sede_fecha     (sede_id, fecha),
    INDEX idx_asis_estado         (estado_diario)
);

-- ============================================================
-- 12. MARCACIONES (DETALLE DE CADA FICHAJE)
-- ============================================================
CREATE TABLE asistencias_diarias (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asistencia_id       INT UNSIGNED NOT NULL,

    tipo                ENUM('ENTRADA','SALIDA','SALIDA_ALMUERZO','RETORNO_ALMUERZO') NOT NULL,
    marcada_en          DATETIME     NOT NULL,

    latitud             DECIMAL(10,8) NULL,
    longitud            DECIMAL(11,8) NULL,
    distancia_metros    INT           NULL,

    -- VALIDA: dentro del radio | OBSERVADA: fuera del radio pero aceptada
    estado_marcacion    ENUM('VALIDA','OBSERVADA') NOT NULL DEFAULT 'VALIDA',
    motivo_observacion  VARCHAR(200) NULL,   -- razón cuando estado_marcacion = 'OBSERVADA'

    -- para marcaciones que requieren revisión manual
    estado_revision     ENUM('PENDIENTE','APROBADA','RECHAZADA') NOT NULL DEFAULT 'APROBADA',

    offline_uuid        VARCHAR(100) NULL UNIQUE,
    registrado_en       ENUM('APP_ONLINE','APP_OFFLINE') NOT NULL DEFAULT 'APP_ONLINE',

    revisado_por        INT UNSIGNED NULL,

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ad_asistencia FOREIGN KEY (asistencia_id) REFERENCES asistencias(id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_revisor    FOREIGN KEY (revisado_por)  REFERENCES usuarios(id)    ON DELETE SET NULL,

    INDEX idx_ad_asistencia_fecha (asistencia_id, marcada_en),
    INDEX idx_ad_estado_revision  (estado_revision)
);

-- ============================================================
-- 13. TIPOS DE AUSENCIA (CATÁLOGO)
-- ============================================================
CREATE TABLE tipos_ausencia (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(50)  NOT NULL UNIQUE,
    nombre          VARCHAR(150) NOT NULL,
    requiere_doc    TINYINT(1)   NOT NULL DEFAULT 0,  -- ¿exige documento adjunto?
    descuenta_dias  TINYINT(1)   NOT NULL DEFAULT 1,  -- ¿afecta bolsa de días?
    activo          TINYINT(1)   NOT NULL DEFAULT 1
);

INSERT INTO tipos_ausencia (codigo, nombre, requiere_doc, descuenta_dias) VALUES
('VACACIONES',       'Vacaciones',              0, 1),
('ENFERMEDAD',       'Descanso médico',         1, 0),
('PERMISO_PERSONAL', 'Permiso personal',        0, 1),
('COMISION',         'Comisión de servicio',    1, 0),
('LICENCIA',         'Licencia sin goce',       1, 1),
('CAPACITACION',     'Capacitación',            0, 0),
('DUELO',            'Licencia por duelo',      1, 0),
('MATERNIDAD',       'Licencia por maternidad', 1, 0),
('PATERNIDAD',       'Licencia por paternidad', 1, 0);

-- ============================================================
-- 14. SOLICITUDES DE AUSENCIA (PROACTIVO — ANTES DEL HECHO)
-- El empleado pide permiso/vacaciones con anticipación
-- ============================================================
CREATE TABLE solicitudes_ausencia (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    tipo_ausencia_id    INT UNSIGNED NOT NULL,

    fecha_inicio        DATE NOT NULL,
    fecha_fin           DATE NOT NULL,

    motivo              TEXT         NULL,
    archivo_adjunto     VARCHAR(500) NULL,

    estado              ENUM('PENDIENTE','APROBADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',

    revisado_por        INT UNSIGNED NULL,
    revisado_en         DATETIME     NULL,
    comentario_revision TEXT         NULL,

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_sa_usuario  FOREIGN KEY (usuario_id)       REFERENCES usuarios(id)      ON DELETE CASCADE,
    CONSTRAINT fk_sa_tipo     FOREIGN KEY (tipo_ausencia_id) REFERENCES tipos_ausencia(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sa_revisor  FOREIGN KEY (revisado_por)     REFERENCES usuarios(id)      ON DELETE SET NULL,

    INDEX idx_sa_usuario_fecha (usuario_id, fecha_inicio, fecha_fin),
    INDEX idx_sa_estado        (estado)
);

-- ============================================================
-- 15. JUSTIFICACIONES (REACTIVO — DESPUÉS DEL HECHO)
-- El empleado explica una falta ya ocurrida
-- ============================================================
CREATE TABLE justificaciones (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    asistencia_id       INT UNSIGNED NOT NULL,
    tipo_ausencia_id    INT UNSIGNED NOT NULL,

    fecha_inicio        DATE NOT NULL,
    fecha_fin           DATE NOT NULL,

    motivo              TEXT         NULL,
    archivo_adjunto     VARCHAR(500) NULL,

    estado              ENUM('PENDIENTE','APROBADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',

    revisado_por        INT UNSIGNED NULL,
    revisado_en         DATETIME     NULL,
    comentario_revision TEXT         NULL,

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_just_usuario     FOREIGN KEY (usuario_id)       REFERENCES usuarios(id)      ON DELETE CASCADE,
    CONSTRAINT fk_just_asistencia  FOREIGN KEY (asistencia_id)    REFERENCES asistencias(id)   ON DELETE CASCADE,
    CONSTRAINT fk_just_tipo        FOREIGN KEY (tipo_ausencia_id) REFERENCES tipos_ausencia(id) ON DELETE RESTRICT,
    CONSTRAINT fk_just_revisor     FOREIGN KEY (revisado_por)     REFERENCES usuarios(id)      ON DELETE SET NULL,

    INDEX idx_just_usuario (usuario_id),
    INDEX idx_just_estado  (estado)
);

-- ============================================================
-- 16. TOKENS DE SESIÓN
-- ============================================================
CREATE TABLE tokens_sesion (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    token       VARCHAR(500) NOT NULL UNIQUE,
    expires_at  DATETIME     NOT NULL,
    revocado    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_tok_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,

    INDEX idx_token_activo (usuario_id, revocado, expires_at)
);

-- ============================================================
-- 17. AUDITORÍA
-- Registra cualquier cambio sensible: quién, qué, cuándo, desde dónde
-- ============================================================
CREATE TABLE auditoria (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,  -- BIGINT: crece rápido
    usuario_id      INT UNSIGNED NULL,     -- NULL si fue el sistema (cron, trigger)
    tabla           VARCHAR(100) NOT NULL,
    registro_id     INT UNSIGNED NOT NULL,
    accion          ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    campo           VARCHAR(100) NULL,     -- columna modificada (solo en UPDATE)
    valor_anterior  TEXT NULL,
    valor_nuevo     TEXT NULL,
    ip              VARCHAR(45)  NULL,     -- soporta IPv4 e IPv6
    user_agent      VARCHAR(500) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,

    INDEX idx_audit_tabla_registro (tabla, registro_id),
    INDEX idx_audit_usuario        (usuario_id)
);

-- ============================================================
-- 18. INTENTOS DE LOGIN (RATE LIMITING)
-- ============================================================
CREATE TABLE login_attempts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip              VARCHAR(45)  NOT NULL,  -- soporta IPv4 e IPv6
    endpoint        VARCHAR(100) NOT NULL,
    intentos        INT          NOT NULL DEFAULT 1,
    ultimo_intento  DATETIME     NOT NULL,
    bloqueado_hasta DATETIME     NULL,

    INDEX idx_login_ip_endpoint (ip, endpoint),
    INDEX idx_login_bloqueado   (bloqueado_hasta)
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- RESUMEN DE TABLAS
-- ============================================================
-- 01. usuarios              → todos los usuarios del sistema
-- 02. roles                 → ADMIN, SUPERVISOR, EMPLEADO
-- 03. usuario_roles         → relación usuario ↔ rol (N:M)
-- 04. departamentos         → áreas de la empresa
-- 05. sedes                 → ubicaciones físicas con GPS
-- 06. usuario_sede          → supervisor ↔ sede (N:M)
-- 07. horarios_sede         → turnos con entrada/salida/almuerzo
-- 08. horario_dias          → días laborables por turno
-- 09. usuario_app_sede      → empleado ↔ sede ↔ turno ↔ departamento
-- 10. feriados              → nacionales (sede NULL) o por sede
-- 11. asistencias           → resumen diario por empleado
-- 12. asistencias_diarias   → marcaciones individuales (entrada/salida/almuerzo)
-- 13. tipos_ausencia        → catálogo: vacaciones, enfermedad, permiso, etc.
-- 14. solicitudes_ausencia  → permisos pedidos ANTES (proactivo)
-- 15. justificaciones       → explicaciones de faltas DESPUÉS (reactivo)
-- 16. tokens_sesion         → manejo de sesiones JWT
-- 17. auditoria             → trazabilidad completa de cambios