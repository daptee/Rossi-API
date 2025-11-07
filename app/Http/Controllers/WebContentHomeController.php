<?php

namespace App\Http\Controllers;

use App\Helpers\ImageHelper;
use App\Services\FileStorageService;
use App\Models\Product;
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
            // Obtener todos los contenidos de la web
            $webContents = WebContentHome::all();

            // Recorrer cada contenido y procesar los productos asociados
            foreach ($webContents as $webContent) {
                $data = $webContent->data; // Decodificar los datos JSON

                // Procesar imágenes y hotspots
                if (isset($data['images']) && is_array($data['images'])) {
                    foreach ($data['images'] as &$image) {
                        if (isset($image['hotspots']) && is_array($image['hotspots'])) {
                            foreach ($image['hotspots'] as &$hotspot) {
                                if (isset($hotspot['product'])) {
                                    Log::info($hotspot['product']);
                                    // Obtener la información del producto
                                    $productInfo = Product::find($hotspot['product']);

                                    // Agregar la información del producto
                                    $hotspot['product_info'] = $productInfo ?: null;
                                    $hotspot['product'] = $productInfo ? $hotspot['product'] : null;
                                }
                            }
                        }
                    }
                }

                // Asignar de nuevo los datos procesados
                $webContent->data = $data;
            }

            return ApiResponse::create('Succeeded', 200, $webContents);
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
                'imgSlider.*' => 'file|mimes:jpeg,png,jpg',
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

            // Procesar y guardar los archivos básicos
            $files = [
                ['name' => 'imagen1', 'type' => 'image'],
                ['name' => 'imagen2', 'type' => 'image'],
                ['name' => 'video1', 'type' => 'video'],
                ['name' => 'video2', 'type' => 'video'],
            ];

            foreach ($files as $file) {
                if ($request->hasFile($file['name'])) {
                    $thumbnailImagenPath = null;
                    $uploadedFile = $request->file($file['name']);
                    if ($file['name'] == 'imagen1' || $file['name'] == 'imagen2') {
                        $thumbnailImagenPath = ImageHelper::saveReducedImage(
                            $uploadedFile,
                            "storage/web_content_home/",
                        );
                    }
                    $path = $this->storeFile($uploadedFile, 'storage/web_content_home');
                    $this->updateDecodedData($decodedData, $file, $thumbnailImagenPath, $path, $webContent->id);
                }
            }

            // Procesar imágenes del slider

            Log::info($request->all()); // Inspecciona todos los datos enviados
            Log::info($request->files->all());
            if (isset($decodedData['images']) && is_array($decodedData['images'])) {
                foreach ($decodedData['images'] as $index => &$imageData) {
                    Log::info("Procesando imagen para índice: $index");

                    // Intentar obtener el archivo del array 'imgSlider'
                    $uploadedSliderImage = $request->file('imgSlider')[$index] ?? null;

                    if ($uploadedSliderImage && $uploadedSliderImage->isValid()) {
                        Log::info("Archivo encontrado: " . $uploadedSliderImage->getClientOriginalName());

                        $thumbnailSliderImage = ImageHelper::saveReducedImage(
                            $uploadedSliderImage,
                            "storage/web_content_home/",
                        );

                        // Guardar el archivo y actualizar la URL
                        $path = $this->storeFile($uploadedSliderImage, 'storage/web_content_home');
                        $imageData['img']['url'] = $path;
                        $imageData['img']['thumbnail_url'] = $thumbnailSliderImage;
                    } else {
                        Log::warning("No se encontró archivo para imgSlider[$index]");
                        $imageData['img']['url'] = null; // En caso de que no se encuentre el archivo
                        $imageData['img']['thumbnail_url'] = null;
                    }
                }
            }

            // Actualizar el campo `data` con los datos procesados
            $webContent->update(['data' => $decodedData]);

            return ApiResponse::create('Contenido de la web creado correctamente', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear el contenido de la web', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Almacena un archivo en una ruta especificada.
     */
    private function storeFile($file, $directory)
    {
        // Use FileStorageService to store the file
        return FileStorageService::storeFile($file, $directory);
    }

    /**
     * Actualiza los datos decodificados para incluir las rutas de los archivos.
     */
    private function updateDecodedData(&$decodedData, $file, $thumbnailFile, $path, $webContentId)
    {
        WebContentHomeFile::create([
            'id_web_content_home' => $webContentId,
            'name' => $file['name'],
            'type' => $file['type'],
            'path' => $path,
            'thumbnail_path' => $thumbnailFile,
        ]);

        foreach ($decodedData['files'] as &$fileData) {
            if ($fileData['name'] === $file['name']) {
                $fileData['file'] = $path;
                $fileData['file_path'] = $thumbnailFile;
                break;
            }
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
                'imgSlider.*' => 'nullable|file|mimes:jpeg,png,jpg', // Validar múltiples imágenes para el slider
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

            // Procesar archivos estándar (imagen1, imagen2, video1, video2)
            $files = [
                ['name' => 'imagen1', 'type' => 'image'],
                ['name' => 'imagen2', 'type' => 'image'],
                ['name' => 'video1', 'type' => 'video'],
                ['name' => 'video2', 'type' => 'video'],
            ];

            foreach ($files as $file) {
                if ($request->hasFile($file['name'])) {
                    $thumbnailImagenPath = null;
                    $uploadedFile = $request->file($file['name']);
                    if ($file['name'] == 'imagen1' || $file['name'] == 'imagen2') {
                        $thumbnailImagenPath = ImageHelper::saveReducedImage(
                            $uploadedFile,
                            "storage/web_content_home/",
                        );
                    }
                    $this->processFile($webContent, $decodedData, $uploadedFile, $thumbnailImagenPath, $file['name'], $file['type']);
                }
            }

            // Procesar imágenes del slider
            if ($request->has('imgSlider')) {
                foreach ($request->file('imgSlider') as $index => $uploadedFile) {
                    if (isset($decodedData['images'][$index])) {
                        // Eliminar archivo existente si aplica usando FileStorageService
                        $existingImage = $decodedData['images'][$index]['img']['url'] ?? null;
                        $existingThumbnailImage = $decodedData['images'][$index]['img']['thumbnail_url'] ?? null;
                        if ($existingImage && FileStorageService::fileExists($existingImage)) {
                            FileStorageService::deleteFile($existingImage);
                        }

                        if ($existingThumbnailImage && FileStorageService::fileExists($existingThumbnailImage)) {
                            FileStorageService::deleteFile($existingThumbnailImage);
                        }

                        // Guardar nueva imagen usando FileStorageService
                        $newImagePath = FileStorageService::storeFile($uploadedFile, 'storage/web_content_home');

                        $newThumbnailImagenPath = ImageHelper::saveReducedImage(
                            $uploadedFile,
                            "storage/web_content_home/",
                        );

                        // Actualizar URL en `images`
                        $decodedData['images'][$index]['img']['url'] = $newImagePath;
                        $decodedData['images'][$index]['img']['thumbnail_url'] = $newThumbnailImagenPath;
                    }
                }
            }

            // Actualizar el contenido con los datos nuevos
            $webContent->update(['data' => $decodedData]);

            return ApiResponse::create('Contenido de la web creado correctamente', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el contenido de la web', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Procesa un archivo estándar (imagen o video).
     */
    private function processFile($webContent, &$decodedData, $uploadedFile, $thumbnailImagenPath, $fileName, $fileType)
    {
        // Eliminar archivo existente (si lo hay)
        $existingFile = WebContentHomeFile::where('id_web_content_home', $webContent->id)
            ->where('name', $fileName)
            ->first();

        if ($existingFile) {
            if ($existingFile->path && FileStorageService::fileExists($existingFile->path)) {
                FileStorageService::deleteFile($existingFile->path);
            }
            if ($existingFile->thumbnail_path && FileStorageService::fileExists($existingFile->thumbnail_path)) {
                FileStorageService::deleteFile($existingFile->thumbnail_path);
            }
            $existingFile->delete();
        }

        // Guardar nuevo archivo usando FileStorageService
        $newFilePath = FileStorageService::storeFile($uploadedFile, 'storage/web_content_home');

        // Guardar en base de datos
        WebContentHomeFile::create([
            'id_web_content_home' => $webContent->id,
            'name' => $fileName,
            'type' => $fileType,
            'path' => $newFilePath,
            'thumbnail_path' => $thumbnailImagenPath,
        ]);

        // Actualizar el JSON
        foreach ($decodedData['files'] as &$fileData) {
            if ($fileData['name'] === $fileName) {
                $fileData['file'] = $newFilePath;
                $fileData['thumbnail_file'] = $thumbnailImagenPath;
                break;
            }
        }
    }


}

