<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class AttributeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $search = $request->query('search'); // Parámetro de búsqueda
            $perPage = $request->query('per_page', 30); // Número de elementos por página, por defecto 30

            // Consulta inicial
            $query = Attribute::whereNull('id_attribute')->with('values', 'status');

            if ($search !== null) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%') // Buscar en el nombre de los padres
                    ->orWhereHas('children', function ($subQuery) use ($search) {
                        $subQuery->where('name', 'like', '%' . $search . '%'); // Buscar en los hijos
                    });
                });
            }

            // Paginación
            $attributes = $query->paginate($perPage);

            // Procesar la estructura jerárquica
            $attributes->getCollection()->transform(function ($attribute) use ($search) {
                if ($search) {
                    // Si se está buscando, incluir solo los hijos que coinciden
                    $attribute->attributes = $this->filterChildren($attribute, $search);
                } else {
                    // Construir toda la jerarquía si no hay búsqueda
                    $attribute->attributes = $this->buildTree(Attribute::where('id_attribute', $attribute->id)->with('values')->get());
                }
                return $attribute;
            });

            // Metadatos para la paginación
            $metaData = [
                'page' => $attributes->currentPage(),
                'per_page' => $attributes->perPage(),
                'total' => $attributes->total(),
                'last_page' => $attributes->lastPage(),
            ];

            return ApiResponse::create('Atributos obtenidos correctamente', 200, $attributes->items(), $metaData);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer todos los atributos', 500, [], ['error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_attribute' => 'nullable|exists:attributes,id',
                'name' => 'required|string|max:255',
                'status' => 'required|integer|exists:status,id',
                'values' => 'nullable|array',
                'values.*.value' => 'required_with:values|string|max:255',
                'values.*.color' => 'nullable|string|max:7'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $attribute = Attribute::create($request->only('id_attribute', 'name', 'status'));

            if ($request->has('values')) {
                foreach ($request->values as $valueData) {
                    $valueData['id_attribute'] = $attribute->id;
                    AttributeValue::create($valueData);
                }
            }

            $attribute->load('values', 'status');
            return ApiResponse::create('Succeeded', 200, $attribute);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un atributo', 500, ['error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_attribute' => 'nullable|exists:attributes,id',
                'name' => 'required|string|max:255',
                'status' => 'required|integer|exists:status,id',
                'values' => 'nullable|array',
                'values.*.id' => 'sometimes|exists:attribute_values,id',
                'values.*.value' => 'required_with:values|string|max:255',
                'values.*.color' => 'nullable|string|max:7'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $attribute = Attribute::findOrFail($id);
            $attribute->update($request->only('id_attribute', 'name', 'status'));

            $receivedValueIds = $request->has('values') ? array_column($request->values, 'id') : [];

            $existingValueIds = $attribute->values()->pluck('id')->toArray();

            $valuesToDelete = array_diff($existingValueIds, $receivedValueIds);

            AttributeValue::destroy($valuesToDelete);

            if ($request->has('values')) {
                foreach ($request->values as $valueData) {
                    if (isset($valueData['id'])) {
                        $value = AttributeValue::findOrFail($valueData['id']);
                        $value->update($valueData);
                    } else {
                        $valueData['id_attribute'] = $attribute->id;
                        AttributeValue::create($valueData);
                    }
                }
            }

            $attribute->load('values', 'status');
            return ApiResponse::create('Succeeded', 200, $attribute);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar un atributo', 500, ['error' => $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        try {
            // Encontrar el atributo a eliminar
            $attribute = Attribute::findOrFail($id);

            // Eliminar los valores asociados al atributo
            $valuesToDelete = $attribute->values()->pluck('id')->toArray();
            if (!empty($valuesToDelete)) {
                AttributeValue::destroy($valuesToDelete);
            }

            // Eliminar el atributo
            $attribute->delete();

            return ApiResponse::create('Atributo eliminado correctamente', 200);
        } catch (Exception $e) {
            return ApiResponse::create('Error al eliminar el atributo', 500, ['error' => $e->getMessage()]);
        }
    }


    private function filterChildren($parent, $search)
    {
        $children = Attribute::where('id_attribute', $parent->id)->with('values')->get();

        // Filtrar solo los hijos que coinciden con la búsqueda
        $filteredChildren = $children->filter(function ($child) use ($search) {
            return str_contains(strtolower($child->name), strtolower($search));
        });

        // Procesar recursivamente los hijos encontrados
        $filteredChildren->transform(function ($child) use ($search) {
            $child->attributes = $this->filterChildren($child, $search);
            return $child;
        });

        return $filteredChildren;
    }

    private function buildTree($attributes)
    {
        foreach ($attributes as $attribute) {
            $children = Attribute::where('id_attribute', $attribute->id)->with('values')->get();
            if ($children->isNotEmpty()) {
                $attribute->attributes = $this->buildTree($children);
            }
        }

        return $attributes;
    }
}
