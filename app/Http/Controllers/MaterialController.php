<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\MaterialValue;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Log;

class MaterialController extends Controller
{
    public function index(Request $request)
{
    try {
        $search = $request->query('search'); // Parámetro de búsqueda
        $perPage = $request->query('per_page', 10); // Número de elementos por página, por defecto 10

        // Consulta inicial para materiales padre
        $query = Material::whereNull('id_material')->with('values', 'status');

        if ($search) {
            // Filtrar por nombre en materiales padre e hijos
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%') // Buscar en el nombre del padre
                    ->orWhereHas('children', function ($childQuery) use ($search) {
                        $childQuery->where('name', 'like', '%' . $search . '%'); // Buscar en el nombre de los hijos
                    });
            });
        }

        // Paginación
        $materials = $query->paginate($perPage);

        // Construcción del árbol jerárquico
        $materials->getCollection()->transform(function ($material) {
            return $this->buildTree([$material])->first(); // Aplicar buildTree
        });

        // Metadata de paginación
        $metaData = [
            'page' => $materials->currentPage(),
            'per_page' => $materials->perPage(),
            'total' => $materials->total(),
            'last_page' => $materials->lastPage(),
        ];

        return ApiResponse::create('Materiales obtenidos correctamente', 200, $materials->items(), $metaData);
    } catch (Exception $e) {
        return ApiResponse::create('Error al traer todos los materiales', 500, [], ['error' => $e->getMessage()]);
    }
}

private function buildTree($materials)
{
    foreach ($materials as $material) {
        $children = Material::where('id_material', $material->id)->with('values')->get();
        if ($children->isNotEmpty()) {
            $material->materials = $this->buildTree($children); // Construir el árbol recursivamente
        }
    }

    return collect($materials); // Devolver la colección
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

            $baseStoragePath = public_path('storage/materials/images/');
            if (!file_exists($baseStoragePath)) {
                mkdir($baseStoragePath, 0777, true);
            }

            $receivedValueIds = $request->has('values') ? array_column($request->values, 'id') : [];
            $existingValues = $material->values()->pluck('id')->toArray();
            $valuesToDelete = array_diff($existingValues, $receivedValueIds);

            foreach ($valuesToDelete as $valueId) {
                $valueToDelete = MaterialValue::findOrFail($valueId);
                if ($valueToDelete->img) {
                    $this->deleteFile($valueToDelete->img);
                }
                $valueToDelete->delete();
            }

            if ($request->has_submaterials === "false" && $request->has('values')) {
                foreach ($request->values as $valueData) {
                    $this->saveOrUpdateMaterialValue($valueData, $material, $baseStoragePath);
                }
            }

            // Actualizar o eliminar submateriales
            if ($request->has_submaterials === "true" && $request->has('submaterials')) {
                $receivedSubmaterialIds = array_column($request->submaterials, 'id');

                // Obtener los submateriales existentes en la base de datos
                $existingSubmaterials = $material->submaterials()->pluck('id')->toArray();

                // Identificar submateriales a eliminar
                $submaterialsToDelete = array_diff($existingSubmaterials, $receivedSubmaterialIds);

                foreach ($submaterialsToDelete as $submaterialId) {
                    $submaterialToDelete = Material::findOrFail($submaterialId);

                    // Eliminar values asociados
                    foreach ($submaterialToDelete->values as $subValue) {
                        if ($subValue->img) {
                            $this->deleteFile($subValue->img);
                        }
                        $subValue->delete();
                    }
                    $submaterialToDelete->delete();
                }

                // Actualizar o crear submateriales proporcionados
                foreach ($request->submaterials as $submaterialData) {
                    if (isset($submaterialData['id'])) {
                        $submaterial = Material::findOrFail($submaterialData['id']);
                        $submaterial->update($submaterialData);
                    } else {
                        $submaterialData['id_material'] = $material->id;
                        $submaterial = Material::create($submaterialData);
                    }

                    $receivedSubValueIds = isset($submaterialData['values']) ? array_column($submaterialData['values'], 'id') : [];
                    $existingSubValues = $submaterial->values()->pluck('id')->toArray();
                    $subValuesToDelete = array_diff($existingSubValues, $receivedSubValueIds);

                    foreach ($subValuesToDelete as $subValueId) {
                        $subValueToDelete = MaterialValue::findOrFail($subValueId);
                        if ($subValueToDelete->img) {
                            $this->deleteFile($subValueToDelete->img);
                        }
                        $subValueToDelete->delete();
                    }

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
                try {
                    $this->deleteFile($value->img); // Eliminar imagen física
                    $value->delete(); // Marca el value como eliminado (soft delete)
                } catch (Exception $e) {
                    Log::error("Error al eliminar el value del material con ID {$value->id}: " . $e->getMessage());
                }
            }

            // Eliminar físicamente las imágenes de los submateriales y sus values
            foreach ($material->submaterials as $submaterial) {
                foreach ($submaterial->values as $subValue) {
                    try {
                        $this->deleteFile($subValue->img); // Eliminar imagen física
                        $subValue->delete(); // Marca el value del submaterial como eliminado (soft delete)
                    } catch (Exception $e) {
                        Log::error("Error al eliminar el value del submaterial con ID {$subValue->id}: " . $e->getMessage());
                    }
                }

                try {
                    $submaterial->delete(); // Marca el submaterial como eliminado (soft delete)
                } catch (Exception $e) {
                    Log::error("Error al eliminar el submaterial con ID {$submaterial->id}: " . $e->getMessage());
                }
            }

            // Eliminar el material principal (soft delete)
            $material->delete();

            return ApiResponse::create('Material y submateriales eliminados correctamente.', 200);
        } catch (Exception $e) {
            Log::error("Error al eliminar el material principal con ID {$id}: " . $e->getMessage());
            return ApiResponse::create('Error al eliminar el material principal', 500, ['error' => $e->getMessage()]);
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

    
}
