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
            $components = Component::all();
            return ApiResponse::create('Succeeded', 200, $components);
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
            }

            // Definir la ruta base dentro de public/storage/components
            $baseStoragePath = public_path('storage/components/images/');

            // Verificar si la carpeta existe, si no, crearla
            if (!file_exists($baseStoragePath)) {
                mkdir($baseStoragePath, 0777, true);
            }

            // Procesar la imagen y moverla a la carpeta adecuada
            $imgPath = null;
            if ($request->hasFile('img')) {
                $fileName = time() . '_' . $request->file('img')->getClientOriginalName();
                $request->file('img')->move($baseStoragePath, $fileName);
                $imgPath = 'storage/components/images/' . $fileName;
            }

            $component = Component::create([
                'name' => $request->name,
                'img' => $imgPath,
            ]);

            return ApiResponse::create('Componente creado correctamente', 200, $component);
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
            }

            $component = Component::findOrFail($id);

            // Definir la ruta base dentro de public/storage/components
            $baseStoragePath = public_path('storage/components/images/');

            // Verificar si la carpeta existe, si no, crearla
            if (!file_exists($baseStoragePath)) {
                mkdir($baseStoragePath, 0777, true);
            }

            if ($request->hasFile('img')) {
                // Elimina la imagen anterior si existe
                if ($component->img && file_exists(public_path($component->img))) {
                    unlink(public_path($component->img)); // Usamos unlink() para borrar la imagen
                }

                // Almacenar la nueva imagen
                $fileName = time() . '_' . $request->file('img')->getClientOriginalName();
                $request->file('img')->move($baseStoragePath, $fileName);
                $component->img = 'storage/components/images/' . $fileName;
            }

            // Actualiza el nombre si se ha enviado en la solicitud
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
