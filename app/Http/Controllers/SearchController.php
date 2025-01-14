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
                $results = [];

                // Buscar las categorías principales y sus subcategorías coincidentes
                $matchingCategories = Category::whereHas('categories', function ($query) use ($search) {
                    $query->where('category', 'like', '%' . $search . '%');
                })->with([
                            'categories' => function ($query) use ($search) {
                                $query->where('category', 'like', '%' . $search . '%'); // Subcategorías coincidentes
                            },
                            'status'
                        ])->get();

                $uniqueCategories = [];
                $processedCategoryIds = [];

                // Construir un array único para las categorías
                foreach ($matchingCategories as $category) {
                    $id = $category->id;
                    if (!isset($uniqueCategories[$id])) {
                        $uniqueCategories[$id] = [
                            'id' => $category->id,
                            'category' => $category->category,
                            'status' => $category->status,
                            'img' => $category->img,
                            'sub_img' => $category->sub_img,
                            'video' => $category->video,
                            'icon' => $category->icon,
                            'color' => $category->color,
                            'grid' => $category->grid,
                            'subcategories' => [], // Inicializamos el array de subcategorías
                        ];
                    }

                    // Agregar las subcategorías únicas
                    $uniqueCategories[$id]['subcategories'] = array_merge(
                        $uniqueCategories[$id]['subcategories'],
                        $category->categories->map(function ($subcategory) use (&$processedCategoryIds) {
                            if (!in_array($subcategory->id, $processedCategoryIds)) {
                                $processedCategoryIds[] = $subcategory->id;
                                return [
                                    'id' => $subcategory->id,
                                    'category' => $subcategory->category,
                                    'status' => $subcategory->status,
                                    'img'=> $subcategory->img,
                                    'sub_img'=> $subcategory->sub_img,
                                    'video'=> $subcategory->video,
                                    'icon'=> $subcategory->icon,
                                    'color'=> $subcategory->color,
                                    'grid'=> $subcategory->grid,
                                ];
                            }
                            return null;
                        })->filter()->toArray() // Filtrar valores nulos
                    );
                }

                // Agregar categorías independientes que coincidan con la búsqueda
                $independentCategories = Category::where('category', 'like', '%' . $search . '%')
                    ->whereNotIn('id', $processedCategoryIds) // Excluir subcategorías ya procesadas
                    ->with(['status'])
                    ->get()
                    ->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'category' => $category->category,
                            'status' => $category->status,
                            'img'=> $category->img,
                            'sub_img'=> $category->sub_img,
                            'video'=> $category->video,
                            'icon'=> $category->icon,
                            'color'=> $category->color,
                            'grid'=> $category->grid,
                            'subcategories' => $category->categories->map(function ($subcategory) {
                                return [
                                    'id' => $subcategory->id,
                                    'category' => $subcategory->category,
                                    'status' => $subcategory->status,
                                    'img'=> $subcategory->img,
                                    'sub_img'=> $subcategory->sub_img,
                                    'video'=> $subcategory->video,
                                    'icon'=> $subcategory->icon,
                                    'color'=> $subcategory->color,
                                    'grid'=> $subcategory->grid,
                                ];
                            })->toArray(), // Sin subcategorías porque son independientes
                        ];
                    });

                // Combinar los resultados
                foreach ($independentCategories as $independent) {
                    $uniqueCategories[$independent['id']] = $independent;
                }

                $results = array_values($uniqueCategories);

                // Asignar los resultados al array de categorías
                $result['categories'] = $results;
            }

            // Componentes con búsqueda en los hijos
            if ($filters['component'] == 1) {
                $results = [];

                // Buscar los componentes principales que coinciden o cuyos subcomponentes coincidan con el término
                $matchingComponents = Component::whereHas('components', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                })->with([
                            'components' => function ($query) use ($search) {
                                $query->where('name', 'like', '%' . $search . '%'); // Solo subcomponentes coincidentes
                            },
                            'status'
                        ])->get();

                // Construir un nuevo array desde cero para los subcomponentes
                $subcomponentIds = [];
                $uniqueComponents = [];

                foreach ($matchingComponents as $component) {
                    $id = $component->id;
                    if (!isset($uniqueComponents[$id])) {
                        $uniqueComponents[$id] = [
                            'id' => $component->id,
                            'name' => $component->name,
                            'status' => $component->status, // Información del componente principal
                            'components' => [],
                        ];
                    }

                    $uniqueComponents[$id]['components'] = array_merge(
                        $uniqueComponents[$id]['components'],
                        $component->components->map(function ($subcomponent) use (&$subcomponentIds) {
                            $subcomponentIds[] = $subcomponent->id;
                            return [
                                'id' => $subcomponent->id,
                                'name' => $subcomponent->name,
                                'status' => $subcomponent->status,
                            ];
                        })->toArray()
                    );
                }

                // Unificar subcomponentes dentro de cada componente principal
                foreach ($uniqueComponents as &$component) {
                    $component['components'] = array_values(
                        array_unique($component['components'], SORT_REGULAR)
                    );
                }

                // Incluir componentes independientes que coincidan con la búsqueda, pero no estén en subcomponentes
                $independentComponents = Component::where('name', 'like', '%' . $search . '%')
                    ->whereNotIn('id', $subcomponentIds) // Excluir los subcomponentes ya listados
                    ->with(['status'])
                    ->get()
                    ->map(function ($component) {
                        return [
                            'id' => $component->id,
                            'name' => $component->name,
                            'status' => $component->status,
                            'components' => $component->components->map(function ($subcomponent) {
                                return [
                                    'id' => $subcomponent->id,
                                    'name' => $subcomponent->name,
                                    'status' => $subcomponent->status, // Información del subcomponente
                                ];
                            })->toArray(),
                        ];
                    })->toArray();

                // Añadir los componentes independientes al resultado final
                foreach ($independentComponents as $independent) {
                    $uniqueComponents[$independent['id']] = $independent;
                }

                // Convertir a un arreglo final de resultados
                $results = array_values($uniqueComponents);

                $result['components'] = $results;
            }

            if ($filters['material'] == 1) {
                $results = [];

                // Buscar los materiales principales y sus submateriales coincidentes
                $matchingMaterials = Material::whereHas('submaterials', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                })->with([
                            'submaterials' => function ($query) use ($search) {
                                $query->where('name', 'like', '%' . $search . '%'); // Submateriales coincidentes
                            },
                            'status',
                            'values'
                        ])->get();

                $uniqueMaterials = [];
                $processedSubmaterialIds = [];

                // Construir un array único para los materiales
                foreach ($matchingMaterials as $material) {
                    $id = $material->id;
                    if (!isset($uniqueMaterials[$id])) {
                        $uniqueMaterials[$id] = [
                            'id' => $material->id,
                            'name' => $material->name,
                            'status' => $material->status,
                            'values' => $material->values,
                            'submaterials' => [],
                        ];
                    }

                    // Agregar los submateriales únicos
                    $uniqueMaterials[$id]['submaterials'] = array_merge(
                        $uniqueMaterials[$id]['submaterials'],
                        $material->submaterials->map(function ($submaterial) use (&$processedSubmaterialIds) {
                            if (!in_array($submaterial->id, $processedSubmaterialIds)) {
                                $processedSubmaterialIds[] = $submaterial->id;
                                return [
                                    'id' => $submaterial->id,
                                    'name' => $submaterial->name,
                                    'status' => $submaterial->status,
                                    'values' => $submaterial->values,
                                ];
                            }
                            return null;
                        })->filter()->toArray() // Filtrar nulls
                    );
                }

                // Agregar materiales independientes que coincidan con la búsqueda
                $independentMaterials = Material::where('name', 'like', '%' . $search . '%')
                    ->whereNotIn('id', $processedSubmaterialIds) // Excluir submateriales ya procesados
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
                            })->toArray(), // Sin submateriales porque son independientes
                        ];
                    });

                // Combinar los resultados
                foreach ($independentMaterials as $independent) {
                    $uniqueMaterials[$independent['id']] = $independent;
                }

                $results = array_values($uniqueMaterials);

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
