<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver; // o Intervention\Image\Drivers\Imagick\Driver

class ImageHelper
{
    public static function saveReducedImage($imageFile, $path): string
    {
        if (!$imageFile || !$imageFile->isValid()) {
            throw new \Exception("Archivo de imagen no válido");
        }    
        // Crear instancia de ImageManager con driver GD
        $imageManager = new ImageManager(new Driver());

        $fileName = time() . '_' . Str::random(10) . '_thumbnail' . '.' . $imageFile->getClientOriginalExtension();
        $fullPath = public_path($path . $fileName);

        if (!file_exists(public_path($path))) {
            mkdir(public_path($path), 0777, true);
        }

        // Crear y guardar imagen reducida
        $imageManager->read($imageFile)
            ->scaleDown(width: 1280) // ajustá el tamaño
            ->toJpeg(quality: 75)
            ->save($fullPath);

        return $path . $fileName;
    }
}
