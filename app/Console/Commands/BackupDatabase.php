<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDatabase extends Command
{
    protected $signature = 'database:backup';
    protected $description = 'Prueba de ejecución del comando para debug';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        Log::info("Comando database:backup ejecutado correctamente.");

        // // Variables de entorno
        // $database = env('DB_DATABASE');
        // $username = env('DB_USERNAME');
        // $password = env('DB_PASSWORD');
        // $host = env('DB_HOST');
        // $storagePath = public_path('storage/backups'); // Ruta en public/

        // // Asegurar que el directorio de backups en public/ exista
        // if (!file_exists($storagePath)) {
        //     mkdir($storagePath, 0755, true);
        // }

        // // Nombre del archivo con marca de tiempo
        // $fileName = "backup_" . Carbon::now()->format('Y_m_d_His') . ".sql";
        // $filePath = $storagePath . '/' . $fileName;

        // // Comando para hacer el backup
        // $dumpCommand = "mysqldump -h {$host} -u {$username} --password={$password} {$database} > {$filePath}";
        // exec($dumpCommand);

        // // Verificar si se creó correctamente el archivo
        // if (file_exists($filePath)) {
        //     Log::info("Backup creado con éxito: public/backups/{$fileName}");
        // } else {
        //     Log::error("Hubo un error al crear el backup.");
        // }
    }
}
