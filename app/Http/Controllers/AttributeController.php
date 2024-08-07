<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\Validator;
use Exception;

class AttributeController extends Controller
{
    public function index()
    {
        try {
            $attributes = Attribute::whereNull('id_attribute')->with('values')->get();
            $attributes = $this->buildTree($attributes);

            return ApiResponse::create('Succeeded', 200, $attributes);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer todos los atributos', 500, ['error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_attribute' => 'nullable|exists:attributes,id',
                'name' => 'required|string|max:255',
                'values' => 'nullable|array',
                'values.*.value' => 'required_with:values|string|max:255'
            ]);
    
            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }
    
            $attribute = Attribute::create($request->only('id_attribute', 'name'));
    
            if ($request->has('values')) {
                foreach ($request->values as $valueData) {
                    $valueData['id_attribute'] = $attribute->id;
                    AttributeValue::create($valueData);
                }
            }
    
            $attribute->load('values');
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
                'values' => 'nullable|array',
                'values.*.id' => 'sometimes|exists:attribute_values,id',
                'values.*.value' => 'required_with:values|string|max:255'
            ]);
    
            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }
    
            $attribute = Attribute::findOrFail($id);
            $attribute->update($request->only('id_attribute', 'name'));
    
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
    
            $attribute->load('values');
            return ApiResponse::create('Succeeded', 200, $attribute);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar un atributo', 500, ['error' => $e->getMessage()]);
        }
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
