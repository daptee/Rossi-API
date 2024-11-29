<?php

namespace App\Http\Controllers;

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

                    // Generar un nombre único para el video
                    $uniqueFileName = uniqid() . '_' . time() . '.' . $uploadedVideo->getClientOriginalExtension();

                    // Definir la ruta de almacenamiento
                    $directory = public_path('storage/web_content_about');
                    $path = $directory . '/' . $uniqueFileName;

                    // Crear el directorio si no existe
                    if (!file_exists($directory)) {
                        mkdir($directory, 0755, true);
                    }

                    // Mover el video al almacenamiento
                    $uploadedVideo->move($directory, $uniqueFileName);

                    // Guardar la ruta del video
                    $videoPaths[$dbColumn] = "storage/web_content_about/" . $uniqueFileName;
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

                        // Generar un nombre único para la imagen
                        $uniqueFileName = uniqid() . '_' . time() . '.' . $uploadedFile->getClientOriginalExtension();

                        // Definir la ruta de almacenamiento
                        $galleryDirectory = public_path('storage/web_content_about/gallery');
                        $galleryPath = $galleryDirectory . '/' . $uniqueFileName;

                        // Crear el directorio si no existe
                        if (!file_exists($galleryDirectory)) {
                            mkdir($galleryDirectory, 0755, true);
                        }

                        // Mover la imagen al almacenamiento
                        $uploadedFile->move($galleryDirectory, $uniqueFileName);

                        // Guardar el registro en la tabla de galería
                        $gallery = GalleryWebContentAbout::create([
                            'id_web_content_about' => $webContent->id,
                            'file' => "storage/web_content_about/gallery/" . $uniqueFileName,
                        ]);

                        // Actualizar el JSON `data` con la información de la imagen
                        if (isset($decodedData['gallery'][$index])) {
                            $decodedData['gallery'][$index]['file'] = $gallery->file;
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
                if ($webContent->$dbColumn) {
                    $oldVideoPath = public_path($webContent->$dbColumn);
                    if (file_exists($oldVideoPath)) {
                        unlink($oldVideoPath);
                    }
                }

                // Subir el nuevo video
                $uniqueFileName = uniqid() . '_' . time() . '.' . $uploadedVideo->getClientOriginalExtension();
                $directory = public_path('storage/web_content_about');
                $uploadedVideo->move($directory, $uniqueFileName);

                $videoPaths[$dbColumn] = "storage/web_content_about/" . $uniqueFileName;
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
                // Crear una nueva imagen si el ID es null y el archivo está presente
                if (!isset($galleryItem['id']) && isset($galleryItem['file']) && $galleryItem['file']->isValid()) {
    $uploadedFile = $galleryItem['file'];
    $uniqueFileName = uniqid() . '_' . time() . '.' . $uploadedFile->getClientOriginalExtension();
    $galleryDirectory = public_path('storage/web_content_about/gallery');
    $uploadedFile->move($galleryDirectory, $uniqueFileName);

    $newFilePath = "storage/web_content_about/gallery/" . $uniqueFileName;

    // Crear el nuevo registro en la base de datos
    $newImage = GalleryWebContentAbout::create([
        'id_web_content_about' => $webContent->id, // Asegúrate de que este campo sea el correcto
        'file' => $newFilePath,
    ]);

    // Actualizar el JSON
    $decodedData['gallery'][] = [
        'id' => $newImage->id,
        'file' => $newFilePath,
    ];
}

                // Reemplazar una imagen existente si el ID está presente y el archivo es válido
                if (isset($galleryItem['id']) && isset($galleryItem['file']) && $galleryItem['file']->isValid()) {
                    $existingImage = GalleryWebContentAbout::find($galleryItem['id']);
                    if ($existingImage) {
                        // Eliminar la imagen anterior del servidor
                        $oldImagePath = public_path($existingImage->file);
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }

                        // Subir la nueva imagen
                        $uploadedFile = $galleryItem['file'];
                        $uniqueFileName = uniqid() . '_' . time() . '.' . $uploadedFile->getClientOriginalExtension();
                        $galleryDirectory = public_path('storage/web_content_about/gallery');
                        $uploadedFile->move($galleryDirectory, $uniqueFileName);

                        // Actualizar la base de datos
                        $newFilePath = "storage/web_content_about/gallery/" . $uniqueFileName;
                        $existingImage->update(['file' => $newFilePath]);

                        // Actualizar el JSON
                        foreach ($decodedData['gallery'] as &$file) {
                            if ($file['id'] == $galleryItem['id']) {
                                $file['file'] = $newFilePath;
                                break;
                            }
                        }
                    }
                }
            }

            // Actualizar el JSON con la galería procesada
            $webContent->update(['data' => $decodedData]);
        }

        return ApiResponse::create('Contenido actualizado correctamente', 200, $webContent);
    } catch (Exception $e) {
        return ApiResponse::create('Error al actualizar el contenido', 500, ['error' => $e->getMessage()]);
    }
}

}
