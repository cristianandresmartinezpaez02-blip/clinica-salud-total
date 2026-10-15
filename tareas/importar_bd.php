<?php
/**
 * Importa base_datos/campaez_proyecto.sql en la base de datos configurada
 * (útil para cargar la base MySQL en la nube que usa Render).
 *
 * Uso (PowerShell), con los datos de su proveedor MySQL:
 *   $env:DB_HOST="..."; $env:DB_PORT="..."; $env:DB_USER="..."; $env:DB_PASS="..."; $env:DB_NAME="..."; $env:DB_SSL="true"
 *   C:\xampp\php\php.exe tareas\importar_bd.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/bootstrap.php';

$archivo = $argv[1] ?? __DIR__ . '/../base_datos/campaez_proyecto.sql';
$sql = file_get_contents($archivo);
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer $archivo\n");
    exit(1);
}

echo 'Conectando a ' . config('db.host') . ':' . config('db.port', 3306) . ' / ' . config('db.name') . "...\n";
$conexion = db();

// Algunos proveedores (Aiven) exigen clave primaria al crear tablas; el volcado la agrega después
@$conexion->query('SET SESSION sql_require_primary_key = 0');

if (!$conexion->multi_query($sql)) {
    fwrite(STDERR, 'Error: ' . $conexion->error . "\n");
    exit(1);
}
$sentencias = 0;
do {
    $sentencias++;
    if ($resultado = $conexion->store_result()) {
        $resultado->free();
    }
} while ($conexion->more_results() && $conexion->next_result());

if ($conexion->errno) {
    fwrite(STDERR, "Error después de $sentencias sentencias: " . $conexion->error . "\n");
    exit(1);
}
echo "Listo: $sentencias sentencias ejecutadas.\n";
