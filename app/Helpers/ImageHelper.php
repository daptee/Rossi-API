<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Log;

class ImageHelper
{
    public static function saveReducedImage($imageFile, $path): string
    {
        if (!$imageFile || !$imageFile->isValid()) {
            throw new \Exception("Archivo de imagen no válido");
        }

        Log::info("imagennn thu...". $imageFile);

        $originalName = $imageFile->getClientOriginalName();
        $extension = strtolower($imageFile->getClientOriginalExtension());
        $fileName = time() . '_' . Str::random(10) . '_thumbnail.' . $extension;
        $fullPath = public_path($path . $fileName);

        if (!file_exists(public_path($path))) {
            mkdir(public_path($path), 0777, true);
        }

        $sourcePath = $imageFile->getPathname();
        [$width, $height] = getimagesize($sourcePath);

        // Nuevo ancho deseado
        $newWidth = 800;
        // Escalar proporcionalmente
        $scale = $newWidth / $width;
        $newHeight = intval($height * $scale);

        Log::info("extension...". $extension);

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
                return null;
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

        // Guardar imagen reducida
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($resizedImage, $fullPath, 75);
                break;
            case 'png':
                imagepng($resizedImage, $fullPath, 6); // compresión PNG: 0-9
                break;
            case 'gif':
                imagegif($resizedImage, $fullPath);
                break;
        }

        imagedestroy($srcImage);
        imagedestroy($resizedImage);

        return $path . $fileName;
    }
}