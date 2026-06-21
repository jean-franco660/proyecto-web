<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=asistencia_pro;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$check = $pdo->query("SHOW COLUMNS FROM usuarios_trabajador LIKE 'departamento_id'")->fetch();
if ($check) {
    echo "La columna departamento_id ya existe.\n";
} else {
    // INT UNSIGNED para coincidir con departamentos.id
    $pdo->exec("
        ALTER TABLE usuarios_trabajador 
        ADD COLUMN departamento_id INT(10) UNSIGNED NULL DEFAULT NULL,
        ADD CONSTRAINT fk_trabajador_depto 
            FOREIGN KEY (departamento_id) 
            REFERENCES departamentos(id) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE
    ");
    echo "OK: columna departamento_id (UNSIGNED) agregada con FK a departamentos.\n";
}
