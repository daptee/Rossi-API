<?php

namespace App\Http\Controllers;

use App\Models\WebContentHome;
use App\Http\Responses\ApiResponse;
use App\Models\WebContentHomeFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class WebContentHomeController extends Controller
{
    public function index()
    {
        try {
            $webContent = WebContentHome::all();
            return ApiResponse::create('Succeeded', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer el contenido de la web', 500, ['error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Validar la solicitud
            $validator = Validator::make($request->all(), [
                'data' => 'required|json',
                'imagen1' => 'required|file|mimes:jpeg,png,jpg',
                'imagen2' => 'required|file|mimes:jpeg,png,jpg',
                'video1' => 'required|file|mimes:mp4,mov,avi',
                'video2' => 'required|file|mimes:mp4,mov,avi',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Decodificar el JSON
            $decodedData = json_decode($request->data, true);

            // Verificar si el campo 'files' existe y es un array
            if (!isset($decodedData['files']) || !is_array($decodedData['files'])) {
                return ApiResponse::create('El campo files es obligatorio y debe ser un array.', 422);
            }

            // Crear el registro principal
            $webContent = WebContentHome::create([
                'id_user' => $user->id,
                'data' => $decodedData,
            ]);

            // Procesar y guardar los archivos
            $files = [
                ['name' => 'imagen1', 'type' => 'image'],
                ['name' => 'imagen2', 'type' => 'image'],
                ['name' => 'video1', 'type' => 'video'],
                ['name' => 'video2', 'type' => 'video'],
            ];

            foreach ($files as $file) {
                if ($request->hasFile($file['name'])) {
                    $uploadedFile = $request->file($file['name']);

                    // Generar un nombre único para el archivo
                    $uniqueFileName = uniqid() . '_' . time() . '.' . $uploadedFile->getClientOriginalExtension();

                    // Definir la ruta de almacenamiento
                    $directory = public_path('storage/web_content_home');
                    $path = $directory . '/' . $uniqueFileName;

                    // Crear el directorio si no existe
                    if (!file_exists($directory)) {
                        mkdir($directory, 0755, true);
                    }

                    // Mover el archivo a la carpeta de almacenamiento
                    $uploadedFile->move($directory, $uniqueFileName);

                    // Guardar la información en la tabla de archivos
                    WebContentHomeFile::create([
                        'id_web_content_home' => $webContent->id,
                        'name' => $file['name'],
                        'type' => $file['type'],
                        'path' => "storage/web_content_home/" . $uniqueFileName,
                    ]);

                    // Actualizar el JSON con la ruta del archivo
                    foreach ($decodedData['files'] as &$fileData) {
                        if ($fileData['name'] === $file['name']) {
                            $fileData['file'] = "storage/web_content_home/" . $uniqueFileName;
                            break;
                        }
                    }
                }
            }

            // Actualizar el campo `data` con las rutas de los archivos
            $webContent->update(['data' => $decodedData]);

            return ApiResponse::create('Contenido de la web creado correctamente', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear el contenido de la web', 500, ['error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Validar la solicitud
            $validator = Validator::make($request->all(), [
                'data' => 'required|json',
                'imagen1' => 'nullable|file|mimes:jpeg,png,jpg',
                'imagen2' => 'nullable|file|mimes:jpeg,png,jpg',
                'video1' => 'nullable|file|mimes:mp4,mov,avi',
                'video2' => 'nullable|file|mimes:mp4,mov,avi',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Encontrar el contenido existente
            $webContent = WebContentHome::findOrFail($id);

            // Decodificar el JSON
            $decodedData = json_decode($request->data, true);

            if (!isset($decodedData['files']) || !is_array($decodedData['files'])) {
                return ApiResponse::create('El campo files es obligatorio y debe ser un array.', 422);
            }

            // Procesar archivos recibidos
            $files = [
                ['name' => 'imagen1', 'type' => 'image'],
                ['name' => 'imagen2', 'type' => 'image'],
                ['name' => 'video1', 'type' => 'video'],
                ['name' => 'video2', 'type' => 'video'],
            ];

            foreach ($files as $file) {
                if ($request->hasFile($file['name'])) {
                    $uploadedFile = $request->file($file['name']);

                    // Eliminar archivo existente (si lo hay)
                    $existingFile = WebContentHomeFile::where('id_web_content_home', $webContent->id)
                        ->where('name', $file['name'])
                        ->first();

                    if ($existingFile) {
                        $existingFilePath = public_path($existingFile->path);
                        if (file_exists($existingFilePath)) {
                            unlink($existingFilePath); // Eliminar el archivo del servidor
                        }
                        $existingFile->delete(); // Eliminar registro de la base de datos
                    }

                    // Generar un nombre único para el nuevo archivo
                    $uniqueFileName = uniqid() . '_' . time() . '.' . $uploadedFile->getClientOriginalExtension();

                    // Definir la ruta de almacenamiento
                    $directory = public_path('storage/web_content_home');
                    $path = $directory . '/' . $uniqueFileName;

                    // Crear el directorio si no existe
                    if (!file_exists($directory)) {
                        mkdir($directory, 0755, true);
                    }

                    // Mover el archivo a la carpeta de almacenamiento
                    $uploadedFile->move($directory, $uniqueFileName);

                    // Guardar la información en la tabla de archivos
                    WebContentHomeFile::create([
                        'id_web_content_home' => $webContent->id,
                        'name' => $file['name'],
                        'type' => $file['type'],
                        'path' => "storage/web_content_home/" . $uniqueFileName,
                    ]);

                    // Actualizar el JSON con la ruta del archivo
                    foreach ($decodedData['files'] as &$fileData) {
                        if ($fileData['name'] === $file['name']) {
                            $fileData['file'] = "storage/web_content_home/" . $uniqueFileName;
                            break;
                        }
                    }
                }
            }

            // Actualizar el contenido con los datos nuevos
            $webContent->update(['data' => $decodedData]);

            return ApiResponse::create('Contenido de la web actualizado correctamente', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el contenido de la web', 500, ['error' => $e->getMessage()]);
        }
    }

}

