<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Material;
use Illuminate\Support\Facades\Validator;
use Exception;

class MaterialController extends Controller
{
    public function index()
    {
        try {
            $materials = Material::whereNull('id_material')->with('values')->get();
            $materials = $this->buildTree($materials);

            return ApiResponse::create('Succeeded', 200, $materials);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer todos los materiales', 500, ['error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_material' => 'nullable|exists:materials,id',
                'name' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $material = Material::create($request->only('id_material', 'name'));
                
            return ApiResponse::create('Material creado correctamente', 200, $material);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un material', 500, ['error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_material' => 'nullable|exists:materials,id',
                'name' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $material = Material::findOrFail($id);
            $material->update($request->only('id_material', 'name'));
                
            return ApiResponse::create('Material actualizado correctamente', 200, $material);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar un material', 500, ['error' => $e->getMessage()]);
        }
    }

    private function buildTree($materials)
    {
        foreach ($materials as $material) {
            $children = Material::where('id_material', $material->id)->with('values')->get();
            if ($children->isNotEmpty()) {
                $material->materials = $this->buildTree($children);
            }
        }

        return $materials;
    }
}
