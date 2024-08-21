<?php

namespace App\Http\Controllers;

use App\Models\Component;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;

class ComponentController extends Controller
{
    public function index()
    {
        try {
            $webContent = Component::all();
            return ApiResponse::create('Succeeded', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer los componentes', 500, ['error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'img' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);
    
            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            };

            $imgPath = $request->hasFile('img') ? $request->file('img')->store('componet/images') : null;
    
            $webContent = Component::create([
                'name' => $request->name,
                'img' => $imgPath,
            ]);
            
            return ApiResponse::create('Componente creado correctamente', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear el componente', 500, ['error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'img' => 'sometimes|required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            };

            $component = Component::find($id);

            if ($request->hasFile('img')) {
                Storage::delete($component->img);
                $component->img = $request->file('img')->store('componet/images');
            }
            
            if ($request->has('name')) {
                $component->name = $request->name;
            }
        
            $component->save();

            return ApiResponse::create('Componente actualizado correctamente', 200, $component);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el componente', 500, ['error' => $e->getMessage()]);
        }
    }
}
