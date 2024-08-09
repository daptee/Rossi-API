<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Models\ProductsCategories;

class ProductsCategoriesController extends Controller
{
    // GET ALL
    public function index()
    {
        try {
            $categories = ProductsCategories::all();
            return ApiResponse::create('Succeeded', 200, $categories);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer todas las caterogias', 500);
        }    
    }

    // POST
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'required|string|max:255',
                'img' => 'nullable|string',
                'video' => 'nullable|string',
                'icon' => 'nullable|string',
                'color' => 'nullable|string',
                'status' => 'required|integer|exists:categories_status,id',
                'id_category' => 'nullable|exists:products_categories,id',
            ]);
    
            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }
    
            $category = new ProductsCategories([
                'id_category' => $request->input('id_category'),
                'category' => $request->input('category'),
                'img' => $request->input('img'),
                'video' => $request->input('video'),
                'icon' => $request->input('icon'),
                'color' => $request->input('color'),
                'status' => $request->input('status'),
            ]);
    
            $category->save();
            
            return ApiResponse::create('Categoria creada correctamente', 200, $category);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear una categoria', 500);
        } 
    }

    // PUT
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|required|string|max:255',
                'img' => 'nullable|string',
                'video' => 'nullable|string',
                'icon' => 'nullable|string',
                'color' => 'nullable|string',
                'status' => 'sometimes|required|integer|exists:categories_status,id',
                'id_category' => 'nullable|exists:products_categories,id',
            ]);
    
            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            };
            

            $category = ProductsCategories::findOrFail($id);

            $category->update($request->only([
                'id_category',
                'category',
                'img',
                'video',
                'icon',
                'color',
                'status'
            ]));

            return ApiResponse::create('Categoria actualizada correctamente', 200, $category);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar una categoria', 500);
        }
    }
}

