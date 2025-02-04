<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    protected $signature = 'database:backup';
    protected $description = 'Crea un respaldo de la base de datos y lo almacena en el servidor';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host = env('DB_HOST');
        $storagePath = storage_path('app/backups');
        
        // Asegurarse de que el directorio de backups exista
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        // Nombre del archivo .sql con marca de tiempo
        $fileName = "backup_" . Carbon::now()->format('Y_m_d_His') . ".sql";
        $filePath = $storagePath . '/' . $fileName;

        // Comando para realizar el backup
        $dumpCommand = "mysqldump -h {$host} -u {$username} --password={$password} {$database} > {$filePath}";

        // Ejecutar el comando
        $process = exec($dumpCommand);

        // Verificar si se creó correctamente el archivo
        if (file_exists($filePath)) {
            $this->info("Backup creado con éxito: {$fileName}");
        } else {
            $this->error("Hubo un error al crear el backup.");
        }
    }
}
