<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Models\Component;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Http\Request;
use Exception;

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
            ];

            // Resultado combinado
            $result = [];

            // Categorías
            if ($filters['category'] == 1) {
                $query = Category::with(['categories', 'status'])
                    ->withCount('products')
                    ->whereNull('id_category');

                if ($search) {
                    $query->where('category', 'like', '%' . $search . '%');
                }

                $result['categories'] = $query->get();
            }

            // Componentes
            if ($filters['component'] == 1) {
                $query = Component::with([
                    'status',
                    'components' => function ($query) {
                        $query->with('status');
                    }
                ])->whereNull('id_component');

                if ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                }

                $result['components'] = $query->get();
            }

            // Materiales
            if ($filters['material'] == 1) {
                $query = Material::with('values', 'status')
                    ->whereNull('id_material');

                if ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                }

                $result['materials'] = $query->get();
            }

            // Productos
            if ($filters['product'] == 1) {
                $query = Product::select('products.id', 'products.name', 'products.main_img', 'products.sub_img', 'products.status', 'products.featured', 'product_status.status_name', 'products.sku', 'products.slug', 'products.created_at')
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
