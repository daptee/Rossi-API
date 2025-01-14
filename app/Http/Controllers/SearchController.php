<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Component;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Http\Request;
use Exception;
use Log;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Parámetros del request
            $search = $request->query('search');

            // Filtros habilitados (1 para incluir, 0 para excluir)
            $filters = [
                'category' => $request->query('category', 0),
                'component' => $request->query('component', 0),
                'material' => $request->query('material', 0),
                'product' => $request->query('product', 0),
                'attribute' => $request->query('attribute', 0),
            ];

            // Resultado combinado
            $result = [];

            // Categorías con búsqueda en los hijos
            if ($filters['category'] == 1) {
                $query = Category::with([
                    'categories' => function ($query) use ($search) {
                        if ($search) {
                            // Subcategorías coincidentes con la búsqueda
                            $query->where('category', 'like', '%' . $search . '%');
                        }
                    },
                    'status'
                ])
                    ->withCount('products');

                if ($search) {
                    // Padres que coincidan directamente o cuyos hijos coincidan
                    $query->where('category', 'like', '%' . $search . '%')
                        ->orWhereHas('categories', function ($childQuery) use ($search) {
                            $childQuery->where('category', 'like', '%' . $search . '%');
                        });
                }

                $result['categories'] = $query->get();
            }

            // Componentes con búsqueda en los hijos
            if ($filters['component'] == 1) {
                $query = Component::with([
                    'components' => function ($query) use ($search) {
                        if ($search) {
                            // Subcomponentes coincidentes con la búsqueda
                            $query->where('name', 'like', '%' . $search . '%');
                        }
                    },
                    'status'
                ]);

                if ($search) {
                    // Padres que coincidan directamente o cuyos hijos coincidan
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhereHas('components', function ($childQuery) use ($search) {
                            $childQuery->where('name', 'like', '%' . $search . '%');
                        });
                }

                $result['components'] = $query->get();
            }

            if ($filters['material'] == 1) {
                $results = [];

                // Buscar el submaterial que coincide con el término
                $matchingSubmaterials = Material::whereHas('submaterials', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                })->with([
                            'submaterials' => function ($query) use ($search) {
                                $query->where('name', 'like', '%' . $search . '%'); // Solo submateriales coincidentes
                            },
                            'status',
                            'values'
                        ])->get();

                // Construir un nuevo array desde cero para submateriales
                $submaterialIds = []; // Array para almacenar los IDs de los submateriales procesados

                foreach ($matchingSubmaterials as $material) {
                    $results[] = [
                        'id' => $material->id,
                        'name' => $material->name,
                        'status' => $material->status, // Información del material padre
                        'values' => $material->values,
                        'submaterials' => $material->submaterials->map(function ($submaterial) use (&$submaterialIds) {
                            $submaterialIds[] = $submaterial->id; // Registrar el ID del submaterial
                            return [
                                'id' => $submaterial->id,
                                'name' => $submaterial->name,
                                'status' => $submaterial->status, // Información del submaterial
                                'values' => $submaterial->values,
                            ];
                        })->toArray(),
                    ];
                }

                // Incluir materiales independientes que coincidan con la búsqueda, pero no estén en submateriales
                $independentMaterials = Material::where('name', 'like', '%' . $search . '%')
                    ->whereNotIn('id', $submaterialIds) // Excluir los submateriales ya listados
                    ->with(['status', 'values'])
                    ->get()
                    ->map(function ($material) {
                        return [
                            'id' => $material->id,
                            'name' => $material->name,
                            'status' => $material->status,
                            'values' => $material->values,
                            'submaterials' => $material->submaterials->map(function ($submaterial) {
                                return [
                                    'id' => $submaterial->id,
                                    'name' => $submaterial->name,
                                    'status' => $submaterial->status, // Información del submaterial
                                    'values' => $submaterial->values,
                                ];
                            })->toArray(),// Vacío porque es independiente
                        ];
                    });

                $results = array_merge($results, $independentMaterials->toArray());

                $result['materials'] = $results;
            }




            if ($filters['attribute'] == 1) {
                $query = Attribute::with([
                    'values',
                    'status',
                    'attributes' => function ($query) {
                        $query->with('status'); // Hijos de atributos
                    }
                ])->whereNull('id_attribute');

                if ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhereHas('attributes', function ($childQuery) use ($search) {
                            $childQuery->where('name', 'like', '%' . $search . '%');
                        });
                }

                $result['attributes'] = $query->get();
            }

            // Productos sin cambios en la lógica
            if ($filters['product'] == 1) {
                $query = Product::select(
                    'products.id',
                    'products.name',
                    'products.main_img',
                    'products.sub_img',
                    'products.status',
                    'products.featured',
                    'product_status.status_name',
                    'products.sku',
                    'products.slug',
                    'products.created_at'
                )
                    ->join('product_status', 'products.status', '=', 'product_status.id')
                    ->with(['categories.parent', 'materials', 'attributes', 'gallery', 'components'])
                    ->withCount(['categories', 'materials', 'attributes', 'gallery', 'components']);

                if ($search) {
                    $query->where('products.name', 'like', '%' . $search . '%');
                }

                $result['products'] = $query->get();
            }

            // Respuesta
            return ApiResponse::create('Búsqueda combinada exitosa', 200, $result);
        } catch (Exception $e) {
            return ApiResponse::create('Error en la búsqueda combinada', 500, [], ['error' => $e->getMessage()]);
        }
    }

}
