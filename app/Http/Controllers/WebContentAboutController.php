<?php

namespace App\Http\Controllers;

use App\Helpers\ImageHelper;
use App\Services\FileStorageService;
use App\Models\GalleryWebContentAbout;
use App\Models\WebContentAbout;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class WebContentAboutController extends Controller
{
    public function index()
    {
        try {
            $webContent = WebContentAbout::all();
            return ApiResponse::create('Succeeded', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer el contenido sobre nosotros de la web', 500, ['error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Validar la solicitud
            $validator = Validator::make($request->all(), [
                'data' => 'required|json',
                'videoHero' => 'nullable|file|mimes:mp4,mov,avi',
                'videoShowroom' => 'nullable|file|mimes:mp4,mov,avi',
                'gallery.*.file' => 'nullable|file|mimes:jpeg,png,jpg',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Decodificar el JSON
            $decodedData = json_decode($request->data, true);

            // Procesar los videos
            $videoPaths = [];
            $videos = ['videoHero' => 'video_giro', 'videoShowroom' => 'video_showroom'];

            foreach ($videos as $inputName => $dbColumn) {
                if ($request->hasFile($inputName)) {
                    $uploadedVideo = $request->file($inputName);

                    // Guardar la ruta del video usando FileStorageService
                    $videoPaths[$dbColumn] = FileStorageService::storeFile($uploadedVideo, 'storage/web_content_about');
                }
            }

            // Crear el registro principal
            $webContent = WebContentAbout::create([
                'id_user' => $user->id,
                'data' => $decodedData,
                'video_giro' => $videoPaths['video_giro'] ?? null,
                'video_showroom' => $videoPaths['video_showroom'] ?? null,
            ]);

            // Procesar las imágenes de la galería
            if ($request->has('gallery')) {
                foreach ($request->gallery as $index => $galleryItem) {
                    if (isset($galleryItem['file']) && $galleryItem['file']->isValid()) {
                        $uploadedFile = $galleryItem['file'];

                        // Guardar la imagen usando FileStorageService
                        $filePath = FileStorageService::storeFile($uploadedFile, 'storage/web_content_about/gallery');

                        $newFileThumbnailPath = ImageHelper::saveReducedImage(
                            $galleryItem['file'],
                            "storage/web_content_about/gallery/",
                        );

                        // Guardar el registro en la tabla de galería
                        $gallery = GalleryWebContentAbout::create([
                            'id_web_content_about' => $webContent->id,
                            'file' => $filePath,
                            'thumbnail_file' => $newFileThumbnailPath,
                        ]);

                        // Actualizar el JSON `data` con la información de la imagen
                        if (isset($decodedData['gallery'][$index])) {
                            $decodedData['gallery'][$index]['file'] = $gallery->file;
                            $decodedData['gallery'][$index]['thumbnail_file'] = $gallery->thumbnail_file;
                            $decodedData['gallery'][$index]['id'] = $gallery->id;
                        }
                    }
                }
            }

            // Actualizar el campo `data` en el registro principal
            $webContent->update(['data' => $decodedData]);

            return ApiResponse::create('Contenido guardado correctamente', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al guardar el contenido', 500, ['error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Validar la solicitud
            $validator = Validator::make($request->all(), [
                'data' => 'required|json',
                'videoHero' => 'nullable|file|mimes:mp4,mov,avi',
                'videoShowroom' => 'nullable|file|mimes:mp4,mov,avi',
                'gallery.*.file' => 'nullable|file|mimes:jpeg,png,jpg',
                'gallery.*.id' => 'nullable|integer|exists:gallery_web_content_about,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Buscar el contenido principal
            $webContent = WebContentAbout::findOrFail($id);

            // Decodificar el JSON
            $decodedData = json_decode($request->data, true);

            // Procesar los videos
            $videoPaths = [];
            $videos = ['videoHero' => 'video_giro', 'videoShowroom' => 'video_showroom'];

            foreach ($videos as $inputName => $dbColumn) {
                if ($request->hasFile($inputName)) {
                    $uploadedVideo = $request->file($inputName);

                    // Eliminar el video anterior si existe
                    if ($webContent->$dbColumn && FileStorageService::fileExists($webContent->$dbColumn)) {
                        FileStorageService::deleteFile($webContent->$dbColumn);
                    }

                    // Subir el nuevo video usando FileStorageService
                    $videoPaths[$dbColumn] = FileStorageService::storeFile($uploadedVideo, 'storage/web_content_about');
                }
            }

            // Actualizar el registro principal
            $webContent->update([
                'data' => $decodedData,
                'video_giro' => $videoPaths['video_giro'] ?? $webContent->video_giro,
                'video_showroom' => $videoPaths['video_showroom'] ?? $webContent->video_showroom,
            ]);

            // Procesar la galería
            if ($request->has('gallery')) {
                foreach ($request->gallery as $galleryItem) {
                    // Reemplazar una imagen existente en el JSON
                    if (isset($galleryItem['id']) && isset($galleryItem['file']) && $galleryItem['file']->isValid()) {
                        $existingImage = GalleryWebContentAbout::find($galleryItem['id']);
                        if ($existingImage) {
                            // Eliminar la imagen anterior del servidor usando FileStorageService
                            if ($existingImage->file && FileStorageService::fileExists($existingImage->file)) {
                                FileStorageService::deleteFile($existingImage->file);
                            }

                            if ($existingImage->thumbnail_file && FileStorageService::fileExists($existingImage->thumbnail_file)) {
                                FileStorageService::deleteFile($existingImage->thumbnail_file);
                            }

                            // Subir la nueva imagen usando FileStorageService
                            $uploadedFile = $galleryItem['file'];
                            $newFilePath = FileStorageService::storeFile($uploadedFile, 'storage/web_content_about/gallery');

                            $thumbnailFilePath = ImageHelper::saveReducedImage(
                                $galleryItem['file'],
                                "storage/web_content_about/gallery/",
                            );

                            // Actualizar la base de datos
                            $existingImage->update(
                                [
                                    'file' => $newFilePath,
                                    'thumbnail_file'=> $thumbnailFilePath,
                                ]
                            );

                            // Actualizar el JSON
                            foreach ($decodedData['gallery'] as &$file) {
                                if ($file['id'] == $galleryItem['id']) {
                                    $file['file'] = $newFilePath;
                                    $file['thumbnail_file'] = $thumbnailFilePath;
                                    break;
                                }
                            }
                        }
                    }

                    Log::info($galleryItem);

                    // Agregar una nueva imagen donde id es null
                    if (
                        (!array_key_exists('id', $galleryItem) || is_null($galleryItem['id']))
                        && isset($galleryItem['file'])
                        && $galleryItem['file']->isValid()
                    ) {

                        Log::info("holaaaaa");
                        $uploadedFile = $galleryItem['file'];

                        // Subir la nueva imagen usando FileStorageService
                        $newFilePath = FileStorageService::storeFile($uploadedFile, 'storage/web_content_about/gallery');

                        $newThumbnailFilePath = ImageHelper::saveReducedImage(
                            $galleryItem['file'],
                            "storage/web_content_about/gallery/",
                        );

                        // Guardar la imagen en la base de datos
                        $newGalleryImage = GalleryWebContentAbout::create([
                            'file' => $newFilePath,
                            'thumbnail_file'=> $newThumbnailFilePath,
                            'id_web_content_about' => $webContent->id // Asegúrate de que este campo se asocia correctamente
                        ]);

                        // Asignar el id de la base de datos al JSON
                        foreach ($decodedData['gallery'] as &$file) {
                            if (is_null($file['id']) && empty($file['file'])) {
                                $file['file'] = $newFilePath;
                                $file['thumbnail_file'] = $newThumbnailFilePath;
                                $file['id'] = $newGalleryImage->id; // Usar el id generado en la base de datos
                                break;
                            }
                        }
                    }


                }

                // Actualizar el JSON con la nueva galería
                $webContent->update(['data' => $decodedData]);
            }

            return ApiResponse::create('Contenido actualizado correctamente', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el contenido', 500, ['error' => $e->getMessage()]);
        }
    }

}
