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
                'status' => 'required|integer|exists:categories_status,id',
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

            // Verificar si las carpetas existen, si no, crearlas
            if (!file_exists($baseStoragePath . 'images')) {
                mkdir($baseStoragePath . 'images', 0777, true);
            }

            if (!file_exists($baseStoragePath . 'videos')) {
                mkdir($baseStoragePath . 'videos', 0777, true);
            }

            if (!file_exists($baseStoragePath . 'icons')) {
                mkdir($baseStoragePath . 'icons', 0777, true);
            }

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
            $validator = Validator::make($request->all(), [
                'category' => 'required|string|max:255',
                'img' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
                'video' => 'nullable|file|mimes:mp4,mov,avi|max:10240',
                'icon' => 'nullable|file|mimes:svg,png|max:2048',
                'color' => 'nullable|string',
                'status' => 'required|integer|exists:categories_status,id',
                'id_category' => 'nullable|exists:products_categories,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $category = ProductsCategories::findOrFail($id);

            $imgPath = $category->img;
            $videoPath = $category->video;
            $iconPath = $category->icon;

            // Definir la ruta base dentro de public/storage/categories
            $baseStoragePath = public_path('storage/categories/');

            // Verificar si las carpetas existen, si no, crearlas
            if (!file_exists($baseStoragePath . 'images')) {
                mkdir($baseStoragePath . 'images', 0777, true);
            }

            if (!file_exists($baseStoragePath . 'videos')) {
                mkdir($baseStoragePath . 'videos', 0777, true);
            }

            if (!file_exists($baseStoragePath . 'icons')) {
                mkdir($baseStoragePath . 'icons', 0777, true);
            }

            // Procesar la imagen
            if ($request->hasFile('img')) {
                // Eliminar la imagen antigua si existe
                if ($category->img && file_exists(public_path($category->img))) {
                    unlink(public_path($category->img));
                }

                $fileName = time() . '_' . $request->file('img')->getClientOriginalName();
                $request->file('img')->move($baseStoragePath . 'images/', $fileName);
                $imgPath = 'storage/categories/images/' . $fileName;
            }

            // Procesar el video
            if ($request->hasFile('video')) {
                // Eliminar el video antiguo si existe
                if ($category->video && file_exists(public_path($category->video))) {
                    unlink(public_path($category->video));
                }

                $fileName = time() . '_' . $request->file('video')->getClientOriginalName();
                $request->file('video')->move($baseStoragePath . 'videos/', $fileName);
                $videoPath = 'storage/categories/videos/' . $fileName;
            }

            // Procesar el icono
            if ($request->hasFile('icon')) {
                // Eliminar el icono antiguo si existe
                if ($category->icon && file_exists(public_path($category->icon))) {
                    unlink(public_path($category->icon));
                }

                $fileName = time() . '_' . $request->file('icon')->getClientOriginalName();
                $request->file('icon')->move($baseStoragePath . 'icons/', $fileName);
                $iconPath = 'storage/categories/icons/' . $fileName;
            }

            // Actualizar la categoría
            $category->update([
                'id_category' => $request->input('id_category'),
                'category' => $request->input('category'),
                'img' => $imgPath,
                'video' => $videoPath,
                'icon' => $iconPath,
                'color' => $request->input('color'),
                'status' => $request->input('status'),
            ]);

            $category->load('status');

            return ApiResponse::create('Categoría actualizada correctamente', 200, $category);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar la categoría', 500, ['error' => $e->getMessage()]);
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
