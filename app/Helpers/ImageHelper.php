<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImageHelper
{
    public static function saveReducedImage($imageFile, $path): string
    {
        if (!$imageFile || !$imageFile->isValid()) {
            throw new \Exception("Archivo de imagen no válido");
        }

        Log::info("Processing thumbnail image: " . $imageFile->getClientOriginalName());

        $originalName = $imageFile->getClientOriginalName();
        $extension = strtolower($imageFile->getClientOriginalExtension());
        $fileName = time() . '_' . Str::random(10) . '_thumbnail.' . $extension;

        // Remove 'storage/' prefix if present to get the actual storage path
        $storagePath = ltrim($path, 'storage/');
        $fullStoragePath = $storagePath . $fileName;

        $sourcePath = $imageFile->getPathname();
        [$width, $height] = getimagesize($sourcePath);

        // Nuevo ancho deseado
        $newWidth = 100;
        // Escalar proporcionalmente
        $scale = $newWidth / $width;
        $newHeight = intval($height * $scale);

        Log::info("Processing extension: " . $extension);

        // Crear imagen desde el archivo original
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                $srcImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $srcImage = imagecreatefrompng($sourcePath);
                break;
            case 'gif':
                $srcImage = imagecreatefromgif($sourcePath);
                break;
            default:
                $srcImage = imagecreatefromjpeg($sourcePath);
                break;
        }

        // Crear imagen nueva con tamaño reducido
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preservar transparencia para PNG y GIF
        if (in_array($extension, ['png', 'gif'])) {
            imagecolortransparent($resizedImage, imagecolorallocatealpha($resizedImage, 0, 0, 0, 127));
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
        }

        imagecopyresampled(
            $resizedImage,
            $srcImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        // Create a temporary file to save the resized image
        $tempPath = tempnam(sys_get_temp_dir(), 'thumbnail_');

        // Guardar imagen reducida en archivo temporal
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($resizedImage, $tempPath, 75);
                break;
            case 'png':
                imagepng($resizedImage, $tempPath, 6); // compresión PNG: 0-9
                break;
            case 'gif':
                imagegif($resizedImage, $tempPath);
                break;
        }

        imagedestroy($srcImage);
        imagedestroy($resizedImage);

        // Store the thumbnail using Laravel's Storage facade
        $disk = Storage::disk(config('filesystems.default'));
        $disk->put($fullStoragePath, file_get_contents($tempPath));

        // Clean up temporary file
        unlink($tempPath);

        // Return the path with 'storage/' prefix for consistency with existing code
        return 'storage/' . $fullStoragePath;
    }
}
