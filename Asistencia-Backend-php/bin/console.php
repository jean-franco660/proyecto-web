<?php
declare(strict_types=1);

/**
 * CLI Console Script
 * Herramienta de consola para tareas administrativas del Sistema de Asistencia
 */

define('BASE_PATH', dirname(__DIR__));

// Cargar autoloader de Composer
require_once BASE_PATH . '/vendor/autoload.php';

// Cargar variables de entorno desde .env
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Obtener argumentos
$command = $argv[1] ?? null;

if (!$command) {
    echo "Uso: php bin/console.php [comando]\n";
    echo "Comandos disponibles:\n";
    echo "  db:migrate      - Aplicar migraciones pendientes a la base de datos\n";
    echo "  db:seed-admin   - Crear o sembrar una cuenta de administrador de forma segura\n";
    echo "  tokens:clean    - Limpiar tokens expirados de la base de datos (blacklist)\n";
    echo "  auth:reset-2fa  - Limpiar el código 2FA de un administrador (Uso: auth:reset-2fa <email>)\n";
    exit(1);
}

try {
    $db = App\Core\Database::getInstance();
} catch (Exception $e) {
    echo "Error de conexión a la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}

switch ($command) {
    case 'db:seed-admin':
        echo "=== CREADOR DE ADMINISTRADOR SEGURO ===\n";
        
        // Solicitar Email
        echo "Email: ";
        $email = trim(fgets(STDIN));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "Error: Correo electrónico inválido.\n";
            exit(1);
        }

        // Solicitar Contraseña
        echo "Contraseña: ";
        // Ocultar caracteres de contraseña en Unix/Linux/cPanel si es posible (opcional)
        $password = trim(fgets(STDIN));

        // Validar complejidad
        if (!App\Core\Validator::isSecurePassword($password)) {
            echo "Error: La contraseña debe tener al menos 8 caracteres, e incluir letras y números.\n";
            exit(1);
        }

        // Solicitar Nombre Completo
        echo "Nombre Completo: ";
        $nombre = trim(fgets(STDIN));
        if (empty($nombre)) {
            echo "Error: El nombre es obligatorio.\n";
            exit(1);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $db->beginTransaction();

            // Verificar si ya existe el usuario
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo "Error: Ya existe un usuario con este correo electrónico.\n";
                $db->rollBack();
                exit(1);
            }

            // Insertar en la tabla usuarios (rol base, estado_id = 1 (ACTIVO), debe_cambiar_password = 1)
            $stmt = $db->prepare("INSERT INTO usuarios (email, password, debe_cambiar_password, estado_id) VALUES (?, ?, 1, 1)");
            $stmt->execute([$email, $hash]);
            $usuarioId = $db->lastInsertId();

            // Insertar rol 1 (ADMIN) en usuario_roles
            $stmt = $db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, 1)");
            $stmt->execute([$usuarioId]);

            // Insertar perfil en usuarios_staff
            $stmt = $db->prepare("INSERT INTO usuarios_staff (usuario_id, nombre) VALUES (?, ?)");
            $stmt->execute([$usuarioId, $nombre]);

            $db->commit();
            echo "¡Usuario administrador creado exitosamente!\n";
            echo "ID: $usuarioId\n";
            echo "Email: $email\n";
            echo "Nota: Se ha configurado 'debe_cambiar_password = true' para forzar su cambio en el primer inicio de sesión.\n";
        } catch (Exception $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            echo "Error al registrar en BD: " . $ex->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'db:migrate':
        echo "=== EJECUTANDO MIGRACIONES DE BASE DE DATOS ===\n";
        try {
            // Crear tabla de control si no existe
            $db->exec("CREATE TABLE IF NOT EXISTS sistema_migraciones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(255) UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $migrationsDir = BASE_PATH . '/database/migrations';
            if (!is_dir($migrationsDir)) {
                echo "Error: El directorio de migraciones no existe en $migrationsDir\n";
                exit(1);
            }

            $files = glob($migrationsDir . '/*.sql');
            sort($files);

            $executedCount = 0;
            foreach ($files as $file) {
                $version = basename($file);

                // Verificar si ya se ejecutó
                $stmt = $db->prepare("SELECT id FROM sistema_migraciones WHERE version = ?");
                $stmt->execute([$version]);
                if ($stmt->fetch()) {
                    continue;
                }

                echo "Aplicando migración: $version ... ";
                $sql = file_get_contents($file);
                
                $db->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        $db->exec($statement);
                    }
                }
                
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");

                // Registrar migración ejecutada
                $stmt = $db->prepare("INSERT INTO sistema_migraciones (version) VALUES (?)");
                $stmt->execute([$version]);
                echo "OK\n";
                $executedCount++;
            }

            echo "Proceso finalizado. Migraciones aplicadas: $executedCount\n";
        } catch (Exception $ex) {
            echo "Error al aplicar migraciones: " . $ex->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'tokens:clean':
        echo "=== LIMPIEZA DE BLACKLIST DE TOKENS ===\n";
        try {
            $stmtWeb = $db->exec("DELETE FROM tokens_web WHERE expires_at < NOW()");
            $stmtApp = $db->exec("DELETE FROM tokens_app WHERE expires_at < NOW()");
            echo "Registros eliminados de tokens_web: $stmtWeb\n";
            echo "Registros eliminados de tokens_app: $stmtApp\n";
            echo "¡Limpieza completada con éxito!\n";
        } catch (Exception $ex) {
            echo "Error al limpiar tokens: " . $ex->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'auth:reset-2fa':
        echo "=== RESTABLECER AUTENTICACIÓN 2FA ===\n";
        $email = $argv[2] ?? null;
        if (!$email) {
            echo "Error: Debes proporcionar el correo electrónico del usuario.\n";
            echo "Uso: php bin/console.php auth:reset-2fa <email>\n";
            exit(1);
        }

        try {
            // Verificar si el usuario existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) {
                echo "Error: No existe ningún usuario registrado con el correo '$email'.\n";
                exit(1);
            }

            // Limpiar código 2FA
            $stmt = $db->prepare("UPDATE usuarios SET verification_code = NULL, verification_expires_at = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);

            echo "¡Código 2FA para el usuario '$email' restablecido con éxito!\n";
        } catch (Exception $ex) {
            echo "Error al restablecer 2FA: " . $ex->getMessage() . "\n";
            exit(1);
        }
        break;

    default:
        echo "Comando no reconocido: $command\n";
        exit(1);
}
