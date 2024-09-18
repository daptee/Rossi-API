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
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $imgPath = $request->hasFile('img') 
                ? $request->file('img')->store('categories/images', 'public') 
                : null;

            $videoPath = $request->hasFile('video') 
                ? $request->file('video')->store('categories/videos', 'public') 
                : null;

            $iconPath = $request->hasFile('icon') 
                ? $request->file('icon')->store('categories/icons', 'public') 
                : null;

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

            if ($request->hasFile('img')) {
                // Eliminar la imagen antigua si existe
                if ($category->img) {
                    Storage::disk('public')->delete($category->img);
                }
                $imgPath = $request->file('img')->store('categories/images', 'public');
            } else {
                $imgPath = $category->img;
            }

            if ($request->hasFile('video')) {
                // Eliminar el video antiguo si existe
                if ($category->video) {
                    Storage::disk('public')->delete($category->video);
                }
                $videoPath = $request->file('video')->store('categories/videos', 'public');
            } else {
                $videoPath = $category->video;
            }

            if ($request->hasFile('icon')) {
                // Eliminar la icono antigua si existe
                if ($category->icon) {
                    Storage::disk('public')->delete($category->icon);
                }
                $iconPath = $request->file('icon')->store('categories/icons', 'public');
            } else {
                $iconPath = $category->icon;
            }

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
