<?php
/**
 * Copia de seguridad diaria: base de datos (mysqldump comprimido) + archivos subidos (fotos,
 * documentos y resultados de laboratorio). Conserva los últimos N respaldos.
 *
 * Ejecutar una vez al día desde la línea de comandos, por ejemplo a las 11:00 p.m.:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\proyecto_productivo\tareas\respaldo.php
 * En Windows: Programador de tareas → Crear tarea básica → Diaria → Iniciar un programa.
 *
 * Restaurar: descomprima el .sql.gz e impórtelo en phpMyAdmin; descomprima el .zip en log_in/uploads.
 * Guarde también una copia fuera del equipo (disco externo o nube): un respaldo en el mismo disco
 * no protege contra daños del disco.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/bootstrap.php';

$carpeta = rtrim((string) config('respaldo.carpeta', __DIR__ . '/../respaldos'), '/\\');
$mysqldump = (string) config('respaldo.mysqldump', 'C:/xampp/mysql/bin/mysqldump.exe');
$conservar = max(1, (int) config('respaldo.conservar', 14));
if (!is_dir($carpeta) && !mkdir($carpeta, 0750, true)) {
    fwrite(STDERR, "No se pudo crear la carpeta de respaldos: {$carpeta}\n");
    exit(1);
}
// La carpeta nunca debe ser accesible desde la web
if (!is_file("{$carpeta}/.htaccess")) {
    file_put_contents("{$carpeta}/.htaccess", "Require all denied\n");
}

$sello = date('Y-m-d_His');
$base = (string) config('db.name');
$archivoSql = "{$carpeta}/bd_{$base}_{$sello}.sql";

// 1. Base de datos (la contraseña va por variable de entorno, no en la línea de comandos)
$cmd = escapeshellarg($mysqldump) . ' --single-transaction --routines --default-character-set=utf8mb4'
    . ' -h ' . escapeshellarg((string) config('db.host', 'localhost'))
    . ' -u ' . escapeshellarg((string) config('db.user', 'root'))
    . ' ' . escapeshellarg($base) . ' > ' . escapeshellarg($archivoSql);
if ((string) config('db.pass', '') !== '') {
    putenv('MYSQL_PWD=' . config('db.pass'));
}
exec($cmd, $salida, $codigo);
putenv('MYSQL_PWD');
if ($codigo !== 0 || !is_file($archivoSql) || filesize($archivoSql) < 100) {
    @unlink($archivoSql);
    fwrite(STDERR, "Falló mysqldump (código {$codigo}). Revise respaldo.mysqldump en config.local.php.\n");
    exit(1);
}
$gz = gzopen("{$archivoSql}.gz", 'wb9');
$in = fopen($archivoSql, 'rb');
while (!feof($in)) {
    gzwrite($gz, fread($in, 1 << 20));
}
fclose($in);
gzclose($gz);
unlink($archivoSql);
echo 'Base de datos: ' . basename($archivoSql) . ".gz\n";

// 2. Archivos subidos
$uploads = realpath(__DIR__ . '/../log_in/uploads');
if ($uploads && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    $archivoZip = "{$carpeta}/archivos_{$sello}.zip";
    if ($zip->open($archivoZip, ZipArchive::CREATE) === true) {
        $n = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $zip->addFile($f->getPathname(), substr($f->getPathname(), strlen($uploads) + 1));
                $n++;
            }
        }
        $zip->close();
        echo "Archivos subidos: {$n} en " . basename($archivoZip) . "\n";
    }
} else {
    echo "Archivos subidos: omitido (active la extensión zip de PHP para incluirlos).\n";
}

// 3. Rotación: conserva los más recientes
foreach (['bd_*.sql.gz', 'archivos_*.zip'] as $patron) {
    $lista = glob("{$carpeta}/{$patron}") ?: [];
    rsort($lista);
    foreach (array_slice($lista, $conservar) as $viejo) {
        unlink($viejo);
    }
}
file_put_contents("{$carpeta}/ultimo_respaldo.txt", date('Y-m-d H:i:s'));
echo "Respaldo completo en {$carpeta}\n";
