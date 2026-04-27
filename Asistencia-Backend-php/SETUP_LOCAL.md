# 🛠️ Guía de Instalación Local — Sistema de Asistencia

> Stack: **PHP 8+ (servidor built-in)** · **MySQL** · **Vue 3 + Vite** · **Node.js 18+**

---

## 📋 Prerrequisitos

| Herramienta | Versión mínima | Descarga |
|-------------|---------------|----------|
| PHP | 8.0+ | https://windows.php.net/download *(o incluido en XAMPP)* |
| MySQL | 8.0+ | https://dev.mysql.com/downloads *(o incluido en XAMPP)* |
| Node.js | 18 LTS | https://nodejs.org |
| Composer | 2.x | https://getcomposer.org |

> 💡 Si ya tienes **XAMPP**, puedes usar su PHP y MySQL directamente — solo **no es necesario iniciar Apache**.

Verificar instalaciones en PowerShell:
```powershell
php -v          # PHP 8.0+
composer -V     # Composer 2.x
node -v         # v18+
npm -v          # 9+
mysql --version # MySQL 8.0+
```

---

## 📁 Estructura del proyecto

```
d:\Practicas\proyecto-web\
├── Asistencia-Backend-php/     ← Backend PHP MVC
│   ├── app/
│   ├── database/
│   │   └── setup.sql
│   ├── public/                 ← Raíz del servidor PHP
│   ├── routes/
│   ├── .env                    ← Crear desde .env.example
│   └── composer.json
└── asistencia-frontend/        ← Frontend Vue 3 + Vite
    ├── src/
    ├── .env                    ← Crear desde .env.example
    └── package.json
```

---

## 🗄️ PASO 1 — Base de Datos (MySQL)

### 1.1 Iniciar MySQL

**Si usas XAMPP:** abre el XAMPP Control Panel e inicia **solo MySQL** (Apache no es necesario).

**Si tienes MySQL instalado directamente:** el servicio ya debería estar corriendo. Verifica con:
```powershell
mysql -u root -p
```

### 1.2 Crear la base de datos

Desde la consola de MySQL (o phpMyAdmin si prefieres interfaz gráfica):
```sql
CREATE DATABASE sistemas_asistencia_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 1.3 Importar el esquema

```powershell
mysql -u root -p sistemas_asistencia_db < "d:\Practicas\proyecto-web\Asistencia-Backend-php\database\setup.sql"
```

O desde phpMyAdmin:
1. Selecciona la BD `sistemas_asistencia_db` → pestaña **Importar**
2. Selecciona el archivo `database/setup.sql` → **Continuar**

> ✅ El script crea las 17 tablas y sus datos semilla (roles, tipos de ausencia, etc.)

---

## ⚙️ PASO 2 — Backend PHP

### 2.1 Instalar dependencias

```powershell
cd d:\Practicas\proyecto-web\Asistencia-Backend-php
composer install
```

Esto instala `firebase/php-jwt` y genera `vendor/autoload.php`.

### 2.2 Configurar variables de entorno

```powershell
copy .env.example .env
```

Edita `.env` con los datos de tu MySQL:
```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=sistemas_asistencia_db
DB_USERNAME=root
DB_PASSWORD=              # Dejar vacío si no configuraste contraseña en MySQL

JWT_SECRET=cambia_esto_por_clave_segura
JWT_EXPIRATION=3600

APP_ENV=development
APP_DEBUG=true
```

> ⚠️ **Generar un JWT_SECRET seguro:**
> ```powershell
> php -r "echo base64_encode(random_bytes(32));"
> ```
> Copia el resultado y pégalo como valor de `JWT_SECRET`.

### 2.3 Iniciar el servidor PHP built-in

```powershell
cd d:\Practicas\proyecto-web\Asistencia-Backend-php
php -S localhost:8000 -t public
```

El backend quedará escuchando en:
```
http://localhost:8000
```

> 💡 **Mantén esta terminal abierta** mientras trabajas. El servidor se detiene al cerrarla.

### 2.4 Verificar el backend

Abre en el navegador o en PowerShell:
```powershell
# Con curl (PowerShell)
curl http://localhost:8000/v1/web/stats

# O simplemente abre en el navegador:
# http://localhost:8000/v1/web/stats
```

Respuesta esperada (`401` porque no hay token):
```json
{"success": false, "message": "Token no proporcionado"}
```

> ✅ Si ves ese JSON, el backend responde correctamente.

---

## 🎨 PASO 3 — Frontend Vue 3

### 3.1 Instalar dependencias

```powershell
cd d:\Practicas\proyecto-web\asistencia-frontend
npm install
```

### 3.2 Configurar la URL de la API

```powershell
copy .env.example .env
```

Edita `.env`:
```env
VITE_API_URL=http://localhost:8000
```

### 3.3 Iniciar el servidor de desarrollo

```powershell
npm run dev
```

La aplicación estará en:
```
http://localhost:5173
```

---

## 👤 PASO 4 — Crear primer usuario administrador

El `setup.sql` no incluye usuarios por defecto. Créalo ejecutando este SQL en MySQL:

```sql
-- 1. Crear el usuario
INSERT INTO usuarios (nombres, apellido_paterno, email, password_hash, codigo)
VALUES (
  'Admin',
  'Sistema',
  'admin@empresa.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uHV1i/oJ',
  'ADM001'
);

-- 2. Asignar rol administrador
INSERT INTO usuario_roles (usuario_id, rol_id)
SELECT LAST_INSERT_ID(), id FROM roles WHERE nombre = 'administrador';
```

Puedes ejecutarlo desde la consola de MySQL o desde phpMyAdmin (pestaña SQL).

### Credenciales iniciales
| Campo | Valor |
|-------|-------|
| **Email** | `admin@empresa.com` |
| **Contraseña** | `password` |

> ⚠️ Cambia la contraseña tras el primer login. Para generar un hash real:
> ```powershell
> php -r "echo password_hash('TuNuevaContraseña123', PASSWORD_BCRYPT);"
> ```
> Luego actualiza: `UPDATE usuarios SET password_hash = 'NUEVO_HASH' WHERE email = 'admin@empresa.com';`

---

## 🔄 Flujo de trabajo diario

Una vez instalado, para levantar el proyecto en cada sesión de trabajo:

```powershell
# Terminal 1 — Backend PHP
cd d:\Practicas\proyecto-web\Asistencia-Backend-php
php -S localhost:8000 -t public

# Terminal 2 — Frontend Vue
cd d:\Practicas\proyecto-web\asistencia-frontend
npm run dev
```

| Servicio | URL |
|----------|-----|
| **Panel Web** | http://localhost:5173 |
| **API Backend** | http://localhost:8000 |
| **phpMyAdmin** (si usas XAMPP MySQL) | http://localhost/phpmyadmin |

---

## 📡 Referencia rápida de la API

**Base URL:** `http://localhost:8000`

Todas las rutas protegidas requieren el header:
```
Authorization: Bearer <token_jwt>
```

### Autenticación
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/v1/web/login` | Login Panel Web (admin/supervisor) |
| POST | `/v1/app/login` | Login App Móvil (empleados) |
| POST | `/v1/web/logout` | Cerrar sesión |

### Panel Web (admin/supervisor)
| Recurso | Endpoints |
|---------|-----------|
| Dashboard | `GET /v1/web/stats` |
| Sedes | `GET · POST /v1/web/sedes` · `PUT · DELETE /v1/web/sedes/{id}` |
| Horarios | `GET · POST /v1/web/horarios` · `PUT /v1/web/horarios/{id}` · `PUT /v1/web/horarios/{id}/dias` |
| Departamentos | `GET · POST /v1/web/departamentos` · `PUT · DELETE /v1/web/departamentos/{id}` |
| Feriados | `GET · POST /v1/web/feriados` · `PUT · DELETE /v1/web/feriados/{id}` |
| Trabajadores | `GET · POST /v1/web/usuarios-app` · `PUT · DELETE /v1/web/usuarios-app/{id}` |
| Asistencias | `GET /v1/web/asistencias` · `POST /v1/web/asistencias/{id}/revisar` |
| Justificaciones | `GET /v1/web/justificaciones` · `POST /v1/web/justificaciones/{id}/aprobar\|rechazar` |
| Solicitudes ausencia | `GET /v1/web/solicitudes-ausencia` · `POST /v1/web/solicitudes-ausencia/{id}/aprobar\|rechazar` |

### App Móvil (empleados)
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/v1/app/asistencias/estado-dia` | Estado de asistencia del día |
| POST | `/v1/app/asistencias` | Registrar marcación |
| GET | `/v1/app/justificaciones` | Ver justificaciones propias |
| POST | `/v1/app/justificaciones` | Enviar justificación |
| GET | `/v1/app/solicitudes-ausencia` | Ver solicitudes propias |
| POST | `/v1/app/solicitudes-ausencia` | Pedir ausencia anticipada |

---

## 🐛 Errores comunes y soluciones

### ❌ CORS error en el navegador
**Causa:** El backend no acepta peticiones desde `http://localhost:5173`.

Agrega en `.env` del backend:
```env
ALLOWED_ORIGINS=http://localhost:5173
```
Reinicia el servidor PHP (`Ctrl+C` y vuelve a lanzar `php -S`).

---

### ❌ `PDOException: could not find driver`
**Causa:** La extensión `pdo_mysql` no está habilitada en tu PHP.

Busca tu `php.ini` con:
```powershell
php --ini
```
Abre ese archivo y descomenta:
```ini
extension=pdo_mysql
extension=mysqli
```
Reinicia el servidor PHP.

---

### ❌ `Class "Firebase\JWT\JWT" not found`
**Causa:** `composer install` no se ejecutó.
```powershell
cd d:\Practicas\proyecto-web\Asistencia-Backend-php
composer install
```

---

### ❌ `Address already in use` al lanzar `php -S`
**Causa:** El puerto 8000 está ocupado por otro proceso.

Usa otro puerto:
```powershell
php -S localhost:8080 -t public
```
Y actualiza `.env` del frontend:
```env
VITE_API_URL=http://localhost:8080
```

---

### ❌ Puerto 5173 ocupado (frontend)
```powershell
npm run dev -- --port 5174
```

---

### ❌ `401 Unauthorized` en todas las peticiones
Cierra sesión desde el panel y vuelve a iniciar sesión. Si persiste, verifica que `JWT_SECRET` en `.env` no haya cambiado desde la última vez que iniciaste sesión.

---

## 📂 Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `Asistencia-Backend-php/.env` | Conexión BD y JWT (¡no commitear!) |
| `Asistencia-Backend-php/database/setup.sql` | Esquema completo de la BD |
| `Asistencia-Backend-php/routes/api.php` | Definición de todas las rutas REST |
| `asistencia-frontend/.env` | URL del API para el frontend |
| `asistencia-frontend/src/api/axios.js` | Cliente HTTP + interceptores JWT |
| `asistencia-frontend/src/router/index.js` | Rutas de la SPA + guards de auth |
