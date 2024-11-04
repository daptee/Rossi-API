<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;
use App\Models\ProductsCategories;

class ProductsCategoriesController extends Controller
{
    // GET ALL
    public function index()
    {
        try {
            $categories = ProductsCategories::with('categories', 'status')
                ->whereNull('id_category')
                ->get()
                ->map(function ($category) {
                    return $this->removeEmptyCategories($category);
                });

            return ApiResponse::create('Succeeded', 200, $categories);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer todas las categorías', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST
    public function store(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'category' => 'required|string|max:255',
                'img' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'video' => 'nullable|file|mimes:mp4,mov,avi|max:10240',
                'icon' => 'nullable|file|mimes:svg,png|max:2048',
                'color' => 'nullable|string',
                'status' => 'required|integer|exists:status,id',
                'grid' => 'nullable|json',
                'id_category' => 'nullable|exists:products_categories,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create(message: 'Validation failed', code: 422, result: $validator->errors());
            }

            $imgPath = null;
            $videoPath = null;
            $iconPath = null;

            // Definir la ruta base dentro de public/storage/categories
            $baseStoragePath = public_path('storage/categories/');
            $this->createDirectories($baseStoragePath);

            // Guardar la imagen
            if ($request->hasFile('img')) {
                $fileName = time() . '_' . $request->file('img')->getClientOriginalName();
                $request->file('img')->move($baseStoragePath . 'images/', $fileName);
                $imgPath = 'storage/categories/images/' . $fileName;
            }

            // Guardar el video
            if ($request->hasFile('video')) {
                $fileName = time() . '_' . $request->file('video')->getClientOriginalName();
                $request->file('video')->move($baseStoragePath . 'videos/', $fileName);
                $videoPath = 'storage/categories/videos/' . $fileName;
            }

            // Guardar el icono
            if ($request->hasFile('icon')) {
                $fileName = time() . '_' . $request->file('icon')->getClientOriginalName();
                $request->file('icon')->move($baseStoragePath . 'icons/', $fileName);
                $iconPath = 'storage/categories/icons/' . $fileName;
            }

            // Guardar la categoría en la base de datos
            $category = new ProductsCategories([
                'id_category' => $request->input('id_category'),
                'category' => $request->input('category'),
                'img' => $imgPath,
                'video' => $videoPath,
                'icon' => $iconPath,
                'color' => $request->input('color'),
                'status' => $request->input('status'),
                'grid' => $request->input('grid'),
            ]);

            $category->save();
            $category->load('status');

            return ApiResponse::create('Categoría creada correctamente', 200, $category);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear una categoría', 500, ['error' => $e->getMessage()]);
        }
    }


    // PUT
    public function update(Request $request, $id)
    {
        try {
            // Validar solo la existencia y tipo básico de datos
            $validator = Validator::make($request->all(), [
                'category' => 'required|string|max:255',
                'img' => 'nullable',
                'video' => 'nullable',
                'icon' => 'nullable',
                'color' => 'nullable|string',
                'status' => 'required|integer|exists:status,id',
                'grid' => 'required|json',
                'id_category' => 'nullable|exists:products_categories,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $category = ProductsCategories::findOrFail($id);

            // Definir rutas de archivos actuales
            $imgPath = $category->img;
            $videoPath = $category->video;
            $iconPath = $category->icon;

            // Definir la ruta base para almacenamiento
            $baseStoragePath = public_path('storage/categories/');
            $this->createDirectories($baseStoragePath);

            // Procesar la imagen
            $imgPath = $this->processField($request, 'img', $category->img, $baseStoragePath . 'images/');

            // Procesar el video
            $videoPath = $this->processField($request, 'video', $category->video, $baseStoragePath . 'videos/');

            // Procesar el icono
            $iconPath = $this->processField($request, 'icon', $category->icon, $baseStoragePath . 'icons/');

            // Actualizar la categoría
            $category->update([
                'id_category' => $request->input('id_category'),
                'category' => $request->input('category'),
                'img' => $imgPath,
                'video' => $videoPath,
                'icon' => $iconPath,
                'color' => $request->input('color'),
                'status' => $request->input('status'),
                'grid' => $request->input('grid'),
            ]);

            $category->load('status');

            return ApiResponse::create('Categoría actualizada correctamente', 200, $category);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar la categoría', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Crear directorios si no existen.
     */
    private function createDirectories($basePath)
    {
        if (!file_exists($basePath . 'images')) {
            mkdir($basePath . 'images', 0777, true);
        }

        if (!file_exists($basePath . 'videos')) {
            mkdir($basePath . 'videos', 0777, true);
        }

        if (!file_exists($basePath . 'icons')) {
            mkdir($basePath . 'icons', 0777, true);
        }
    }

    /**
     * Procesar el campo que puede ser un archivo o un string.
     */
    private function processField($request, $fieldName, $oldPath, $destination)
    {
        // Verificar si es un archivo cargado
        if ($request->hasFile($fieldName)) {
            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            $fileName = time() . '_' . $request->file($fieldName)->getClientOriginalName();
            $request->file($fieldName)->move($destination, $fileName);
            return 'storage/categories/' . basename($destination) . '/' . $fileName;
        }

        // Verificar si es un string (URL)
        if (is_string($request->input($fieldName))) {
            return $request->input($fieldName);
        }

        // Eliminar archivo si el valor es null y el archivo existía antes
        if (is_null($request->input($fieldName)) && $oldPath) {
            $this->deleteFile($oldPath);
            return null;
        }

        // Retornar la ruta antigua si no hubo cambios
        return $oldPath;
    }

    /**
     * Eliminar un archivo de la ruta dada.
     */
    private function deleteFile($filePath)
    {
        if (file_exists(public_path($filePath))) {
            unlink(public_path($filePath));
        }
    }

    private function removeEmptyCategories($category)
    {
        if ($category->categories->isEmpty()) {
            $category->unsetRelation('categories');
        } else {
            $category->categories = $category->categories->map(function ($childCategory) {
                return $this->removeEmptyCategories($childCategory);
            });
        }

        return $category;
    }
}
