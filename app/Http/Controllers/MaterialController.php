<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\MaterialValue;
use Illuminate\Support\Facades\Validator;
use Exception;

class MaterialController extends Controller
{
    public function index()
    {
        try {
            $materials = Material::whereNull('id_material')->with('values', 'status')->get();
            $materials = $this->buildTree($materials);

            return ApiResponse::create('Succeeded', 200, $materials);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer todos los materiales', 500, ['error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        try {
            // Definir las reglas de validación para el material y submateriales
            $validator = Validator::make($request->all(), [
                'id_material' => 'nullable|exists:materials,id',
                'name' => 'required|string|max:255',
                'status' => 'required|integer|exists:status,id',
                'has_submaterials' => 'required|in:true,false',
                'values' => 'nullable|array',
                'values.*.value' => 'required_with:values|string|max:255',
                'values.*.img' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
                'values.*.color' => 'nullable|string|max:7',
                'submaterials' => 'nullable|array',
                'submaterials.*.name' => 'required_with:submaterials|string|max:255',
                'submaterials.*.status' => 'required_with:submaterials|integer|exists:status,id',
                'submaterials.*.values' => 'nullable|array',
                'submaterials.*.values.*.value' => 'required_with:submaterials.*.values|string|max:255',
                'submaterials.*.values.*.img' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
                'submaterials.*.values.*.color' => 'nullable|string|max:7'
            ]);

            // Verificar si la validación falla
            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Crear el material principal
            $material = Material::create($request->only('id_material', 'name', 'status'));

            // Definir la ruta base para guardar las imágenes
            $baseStoragePath = public_path('storage/materials/images/');

            // Verificar si la carpeta existe, si no, crearla
            if (!file_exists($baseStoragePath)) {
                mkdir($baseStoragePath, 0777, true);
            }

            // Procesar los values si el material NO tiene submateriales
            if ($request->has_submaterials === "false" && $request->has('values')) {
                foreach ($request->values as $valueData) {
                    $valueData = $this->processMaterialValue($valueData, $baseStoragePath, $material->id);
                    MaterialValue::create($valueData);
                }
            }

            // Procesar los submateriales y sus respectivos values si tiene submateriales
            if ($request->has_submaterials === "true" && $request->has('submaterials')) {
                foreach ($request->submaterials as $submaterialData) {
                    // Crear cada submaterial
                    $submaterial = Material::create([
                        'id_material' => $material->id,
                        'name' => $submaterialData['name'],
                        'status' => $submaterialData['status'],
                    ]);

                    // Procesar y guardar cada value asociado al submaterial
                    if (isset($submaterialData['values'])) {
                        foreach ($submaterialData['values'] as $subValueData) {
                            $subValueData = $this->processMaterialValue($subValueData, $baseStoragePath, $submaterial->id);
                            MaterialValue::create($subValueData);
                        }
                    }
                }
            }

            // Cargar las relaciones y retornar la respuesta
            $material->load('values', 'status', 'submaterials.values');
            return ApiResponse::create('Material creado correctamente', 200, $material);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un material', 500, ['error' => $e->getMessage()]);
        }
    }

    private function processMaterialValue($valueData, $baseStoragePath, $materialId)
    {
        // Procesar la imagen si existe
        if (isset($valueData['img']) && $valueData['img'] instanceof \Illuminate\Http\UploadedFile) {
            $fileName = time() . '_' . $valueData['img']->getClientOriginalName();
            $valueData['img']->move($baseStoragePath, $fileName);
            $valueData['img'] = 'storage/materials/images/' . $fileName;
        }

        $valueData['id_material'] = $materialId;
        return $valueData;
    }

    public function update(Request $request, $id)
    {
        try {
            // Modificar la validación para img y hacerla opcional
            $validator = Validator::make($request->all(), [
                'id_material' => 'nullable|exists:materials,id',
                'name' => 'required|string|max:255',
                'status' => 'required|integer|exists:status,id',
                'has_submaterials' => 'required|in:true,false',
                'values' => 'nullable|array',
                'values.*.id' => 'sometimes|exists:material_values,id',
                'values.*.value' => 'required_with:values|string|max:255',
                'values.*.img' => 'nullable',
                'values.*.color' => 'nullable|string|max:7',
                'submaterials' => 'nullable|array',
                'submaterials.*.id' => 'sometimes|exists:materials,id',
                'submaterials.*.name' => 'required_with:submaterials|string|max:255',
                'submaterials.*.status' => 'required_with:submaterials|integer|exists:status,id',
                'submaterials.*.values' => 'nullable|array',
                'submaterials.*.values.*.id' => 'sometimes|exists:material_values,id',
                'submaterials.*.values.*.value' => 'required_with:submaterials.*.values|string|max:255',
                'submaterials.*.values.*.img' => 'nullable',
                'submaterials.*.values.*.color' => 'nullable|string|max:7'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $material = Material::findOrFail($id);
            $material->update($request->only('id_material', 'name', 'status'));

            // Definir la ruta base dentro de public/storage/materials/images
            $baseStoragePath = public_path('storage/materials/images/');

            // Verificar si la carpeta existe, si no, crearla
            if (!file_exists($baseStoragePath)) {
                mkdir($baseStoragePath, 0777, true);
            }

            // Obtener todos los ids de `values` enviados en la solicitud
            $receivedValueIds = $request->has('values') ? array_column($request->values, 'id') : [];

            // Obtener los ids de los `values` existentes en la base de datos
            $existingValues = $material->values()->pluck('id')->toArray();

            // Identificar los ids de `values` que deben ser eliminados (no presentes en la solicitud)
            $valuesToDelete = array_diff($existingValues, $receivedValueIds);

            // Eliminar los `values` que no están presentes en la solicitud
            foreach ($valuesToDelete as $valueId) {
                $valueToDelete = MaterialValue::findOrFail($valueId);
                if ($valueToDelete->img) {
                    // Eliminar la imagen asociada si existe
                    $this->deleteFile($valueToDelete->img);
                }
                $valueToDelete->delete();
            }

            // Manejo de valores (values) cuando no hay submateriales
            if ($request->has_submaterials === "false" && $request->has('values')) {
                foreach ($request->values as $valueData) {
                    $this->saveOrUpdateMaterialValue($valueData, $material, $baseStoragePath);
                }
            }

            // Manejo de submateriales si hay submateriales presentes en la solicitud
            if ($request->has_submaterials === "true" && $request->has('submaterials')) {
                foreach ($request->submaterials as $submaterialData) {
                    // Verificar si se está actualizando un submaterial existente o creando uno nuevo
                    if (isset($submaterialData['id'])) {
                        $submaterial = Material::findOrFail($submaterialData['id']);
                        $submaterial->update($submaterialData);
                    } else {
                        $submaterialData['id_material'] = $material->id;
                        $submaterial = Material::create($submaterialData);
                    }

                    // Procesar los valores asociados al submaterial
                    if (isset($submaterialData['values'])) {
                        foreach ($submaterialData['values'] as $subValueData) {
                            $this->saveOrUpdateMaterialValue($subValueData, $submaterial, $baseStoragePath);
                        }
                    }
                }
            }

            $material->load('values', 'status', 'submaterials.values');
            return ApiResponse::create('Material actualizado correctamente', 200, $material);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar un material', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Guardar o actualizar un valor de material.
     *
     * @param array $valueData
     * @param Material $material
     * @param string $baseStoragePath
     */
    private function saveOrUpdateMaterialValue(array $valueData, Material $material, string $baseStoragePath)
    {
        // Si se proporciona un ID, se está actualizando un valor existente
        if (isset($valueData['id'])) {
            $value = MaterialValue::findOrFail($valueData['id']);

            // Verificar si se proporcionó una nueva imagen
            if (array_key_exists('img', $valueData) && $valueData['img'] instanceof \Illuminate\Http\UploadedFile) {
                // Eliminar la imagen anterior si existe
                if ($value->img) {
                    $this->deleteFile($value->img);
                }

                // Guardar la nueva imagen
                $fileName = time() . '_' . $valueData['img']->getClientOriginalName();
                $valueData['img']->move($baseStoragePath, $fileName);
                $valueData['img'] = 'storage/materials/images/' . $fileName;
            }

            // Actualizar el valor con la información proporcionada
            $value->update($valueData);
        } else {
            // Si es un nuevo valor, procesar la imagen si se proporciona
            if (isset($valueData['img']) && $valueData['img'] instanceof \Illuminate\Http\UploadedFile) {
                $fileName = time() . '_' . $valueData['img']->getClientOriginalName();
                $valueData['img']->move($baseStoragePath, $fileName);
                $valueData['img'] = 'storage/materials/images/' . $fileName;
            }

            // Si no se proporciona imagen en la creación, establecer `img` como `null`
            $valueData['id_material'] = $material->id;
            $valueData['img'] = $valueData['img'] ?? null;
            MaterialValue::create($valueData);
        }
    }

    public function delete($id)
    {
        try {
            // Buscar el material principal por su ID con relaciones
            $material = Material::with('values', 'submaterials.values')->findOrFail($id);

            // Eliminar físicamente las imágenes asociadas a los values del material principal
            foreach ($material->values as $value) {
                $this->deleteFile($value->img); // Eliminar imagen física
                $value->delete(); // Marca el value como eliminado (soft delete)
            }

            // Eliminar físicamente las imágenes de los submateriales y sus values
            foreach ($material->submaterials as $submaterial) {
                // Eliminar cada value del submaterial
                foreach ($submaterial->values as $subValue) {
                    $this->deleteFile($subValue->img); // Eliminar imagen física
                    $subValue->delete(); // Marca el value del submaterial como eliminado (soft delete)
                }
                $submaterial->delete(); // Marca el submaterial como eliminado (soft delete)
            }

            // Eliminar el material principal (soft delete)
            $material->delete();

            return ApiResponse::create('Material eliminados correctamente.', 200);
        } catch (Exception $e) {
            return ApiResponse::create('Error al eliminar el material', 500, ['error' => $e->getMessage()]);
        }
    }


    /**
     * Procesar la imagen que puede ser un archivo o un string.
     *
     * @param mixed $imageData
     * @param string|null $oldPath
     * @param string $destination
     * @return string|null
     */
    private function processImage($imageData, $oldPath, $destination)
    {
        // Verificar si es un archivo cargado
        if ($imageData instanceof \Illuminate\Http\UploadedFile) {
            // Si hay una imagen anterior, eliminarla
            $this->deleteFile($oldPath);

            $fileName = time() . '_' . $imageData->getClientOriginalName();
            $imageData->move($destination, $fileName);
            return 'storage/materials/images/' . $fileName; // Ruta relativa para almacenar en la base de datos
        }

        // Verificar si es un string (URL) o un valor no nulo
        if (is_string($imageData)) {
            return $imageData;
        }

        // Eliminar archivo si el valor es null y el archivo existía antes
        if (is_null($imageData) && $oldPath) {
            $this->deleteFile($oldPath);
            return null;
        }

        // Retornar la ruta antigua si no hubo cambios
        return $oldPath;
    }


    /**
     * Eliminar un archivo de la ruta dada.
     *
     * @param string $filePath
     */
    private function deleteFile($filePath)
    {
        if (file_exists(public_path($filePath))) {
            unlink(public_path($filePath));
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
