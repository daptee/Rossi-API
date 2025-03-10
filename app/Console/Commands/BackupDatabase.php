<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackupController extends Controller
{
    public function createBackup()
    {
        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host = env('DB_HOST');
        $storagePath = public_path('storage/backups'); // Ruta en public/

        // Asegurar que el directorio de backups en public/ exista
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        // Nombre del archivo con marca de tiempo
        $fileName = "backup_" . Carbon::now()->format('Y_m_d_His') . ".sql";
        $filePath = $storagePath . '/' . $fileName;

        // Comando para hacer el backup
        $dumpCommand = "mysqldump -h {$host} -u {$username} --password={$password} {$database} > {$filePath}";

        exec($dumpCommand);

        // Verificar si se creó correctamente el archivo
        if (file_exists($filePath)) {
            Log::info("✅ Backup creado con éxito: public/backups/{$fileName}");
            return response()->json([
                'success' => true,
                'message' => 'Backup creado con éxito.',
                'file' => url("storage/backups/{$fileName}") // URL de descarga
            ]);
        } else {
            Log::error("❌ Error al crear el backup.");
            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al crear el backup.'
            ], 500);
        }
    }
}
