<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\ProductComponent;
use App\Models\ProductStatus;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMaterial;
use App\Models\ProductAttribute;
use App\Models\ProductGallery;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProductController extends Controller
{
    // GET ALL (para admin)
    public function indexAdmin(Request $request)
    {
        try {
            $category_id = $request->query('category_id');  // Obtén el category_id de la solicitud

            // Consulta inicial
            $query = Product::select('products.id', 'products.name', 'products.main_img', 'products.status', 'products.featured', 'product_status.status_name')
                ->join('product_status', 'products.status', '=', 'product_status.id')
                ->with([
                    'categories' => function ($query) {
                        $query->with('parent'); // Incluimos la relación 'parent' en categorías
                    },
                    'materials',
                    'attributes',
                    'gallery',
                    'components'
                ])
                ->withCount(['categories', 'materials', 'attributes', 'gallery', 'components']);

            // Si se recibe un category_id, filtramos los productos por la categoría
            if ($category_id) {
                $query->whereHas('categories', function ($query) use ($category_id) {
                    // Filtramos por el id de categoría
                    $query->where('products_categories.id', $category_id)
                        ->orWhere('products_categories.id_category', $category_id);  // También incluye las subcategorías (padres)
                });
            }

            // Ejecutamos la consulta
            $products = $query->get();

            // Mapea cada producto para devolver solo los conteos, la información básica y las categorías
            $products = $products->map(function ($product) {
                $categories = collect();

                foreach ($product->categories as $category) {
                    if ($category->id_category) { // Si es una categoría hija
                        // Agregamos la categoría padre si aún no está en la colección
                        $parentCategory = $category->parent;
                        if ($parentCategory && !$categories->contains('id', $parentCategory->id)) {
                            $categories->push([
                                'id' => $parentCategory->id,
                                'category' => $parentCategory->category,
                            ]);
                        }
                    }

                    // Agregamos la categoría hija
                    $categories->push([
                        'id' => $category->id,
                        'category' => $category->category,
                    ]);
                }

                // Filtramos duplicados en caso de que se repita alguna categoría
                $uniqueCategories = $categories->unique('id')->values();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'main_img' => $product->main_img,
                    'status' => [
                        'id' => $product->status,
                        'status_name' => $product->status_name,
                    ],
                    'featured' => $product->featured,
                    'categories' => $uniqueCategories,
                    'categories_count' => $product->categories_count,
                    'materials_count' => $product->materials_count,
                    'attributes_count' => $product->attributes_count,
                    'components_count' => $product->components_count,
                    'gallery_count' => $product->gallery_count,
                ];
            });

            return ApiResponse::create('Succeeded', 200, $products);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los productos', 500, ['error' => $e->getMessage()]);
        }
    }

    // GET ALL (para web)
    public function indexWeb(Request $request)
    {
        try {
            $featured = $request->query('featured');

            $query = Product::where('status', 2)
                ->select('id', 'name', 'slug', 'main_img', 'featured');

            if ($featured !== null) {
                $query->where('featured', $featured);
            }

            $products = $query->get();

            return ApiResponse::create('Succeeded', 200, $products);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los productos', 500, ['error' => $e->getMessage()]);
        }
    }


    // GET PRODUCT
    public function indexProduct($id)
    {
        try {
            // Consulta el producto con todas las relaciones necesarias
            $product = Product::with([
                'categories',
                'materials.material',
                'attributes.attribute',
                'gallery',
                'components'
            ])
                ->select('id', 'name', 'description', 'main_img', 'main_video', 'file_data_sheet', 'status', 'featured')
                ->findOrFail($id);

            // Limpia los datos del pivot para cada relación
            $product->categories->each(function ($category) {
                unset($category->pivot);
            });

            $product->materials->each(function ($material) {
                unset($material->pivot);
            });

            $product->attributes->each(function ($attribute) {
                $attribute->img = $attribute->pivot->img;
                unset($attribute->pivot);
            });

            $product->components->each(function ($component) {
                unset($component->pivot);
            });

            // Obtener el nombre del estado del producto desde la tabla product_status
            $status = ProductStatus::find($product->status);

            // Si se encuentra el estado, agrega su nombre al producto
            if ($status) {
                $product->status = [
                    'id' => $status->id,
                    'status_name' => $status->status_name
                ];
            } else {
                // Si no se encuentra el estado, puedes definir un valor por defecto o manejar el error
                $product->status = [
                    'id' => $product->status,
                    'status_name' => 'Unknown' // O cualquier valor por defecto
                ];
            }

            return ApiResponse::create('Succeeded', 200, $product);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener el producto', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST - Crear un nuevo producto
    public function store(Request $request)
    {
        try {
            Log::info('Request Data:', $request->all());
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:100|unique:products,sku',
                'slug' => 'required|string|max:255|unique:products,slug',
                'description' => 'nullable|string',
                'description_bold' => 'nullable|required|in:1,0',
                'description_italic' => 'nullable|required|in:1,0',
                'description_underline' => 'nullable|required|in:1,0',
                'status' => 'required|integer|exists:product_status,id',
                'main_img' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
                'main_video' => 'nullable|file|mimes:mp4,mov,avi|max:10240',
                'file_data_sheet' => 'nullable|file|mimes:pdf|max:5120',
                'featured' => 'nullable|boolean',
                'categories' => 'array',
                'categories.*' => 'integer|exists:products_categories,id',
                'gallery' => 'array',
                'gallery.*' => 'file|mimes:jpg,jpeg,png,mp4,mov,avi|max:10240',
                'materials_values' => 'array',
                'materials_values.*.id_material_value' => 'required|integer|exists:material_values,id',
                'materials_values.*.img' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
                'attributes_values' => 'array',
                'attributes_values.*.id_attribute_value' => 'required|integer|exists:attribute_values,id',
                'attributes_values.*.img' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
                'components' => 'array',
                'components.*' => 'integer|exists:components,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Guardar archivos de imágenes y videos
            $mainImgPath = $request->hasFile('main_img') ? $request->file('main_img')->store('products/images', 'public') : null;
            $mainVideoPath = $request->hasFile('main_video') ? $request->file('main_video')->store('products/videos', 'public') : null;
            $fileDataSheetPath = $request->hasFile('file_data_sheet') ? $request->file('file_data_sheet')->store('products/data_sheets', 'public') : null;

            $product = Product::create([
                'name' => $request->name,
                'sku' => $request->sku,
                'slug' => $request->slug,
                'description' => $request->description,
                'description_bold' => $request->description_bold,
                'description_italic' => $request->description_italic,
                'description_underline' => $request->description_underline,
                'status' => $request->status,
                'main_img' => $mainImgPath,
                'main_video' => $mainVideoPath,
                'file_data_sheet' => $fileDataSheetPath,
                'featured' => $request->featured,
            ]);

            // Guardar imágenes en la galería
            if ($request->has('gallery')) {
                foreach ($request->gallery as $file) {
                    $filePath = $file->store('products/gallery', 'public');
                    ProductGallery::create(['id_product' => $product->id, 'file' => $filePath]);
                }
            }

            // Guardar categorías asociadas al producto
            if ($request->has('categories')) {
                foreach ($request->categories as $categoryId) {
                    ProductCategory::create(['id_product' => $product->id, 'id_categorie' => $categoryId]);
                }
            }

            // Guardar materiales asociados al producto
            if ($request->has('materials_values')) {
                foreach ($request->materials_values as $material) {
                    $materialImgPath = $request->hasFile('materials_values.*.img') ? $material['img']->store('products/materials', 'public') : null;
                    ProductMaterial::create([
                        'id_product' => $product->id,
                        'id_material' => $material['id_material_value'],
                        'img' => $materialImgPath,
                    ]);
                }
            }

            // Guardar atributos asociados al producto
            if ($request->has('attributes_values')) {
                foreach ($request->attributes_values as $attribute) {
                    $attributeImgPath = $request->hasFile('attributes_values.*.img') ? $attribute['img']->store('products/attributes', 'public') : null;
                    ProductAttribute::create([
                        'id_product' => $product->id,
                        'id_attribute_value' => $attribute['id_attribute_value'],
                        'img' => $attributeImgPath,
                    ]);
                }
            }

            // Guardar componentes asociados al producto
            if ($request->has('components')) {
                foreach ($request->components as $componentId) {
                    ProductComponent::create(['id_product' => $product->id, 'id_component' => $componentId]);
                }
            }

            $product = Product::with([
                'categories',
                'materials.material',
                'attributes.attribute',
                'gallery',
                'components'
            ])
                ->findOrFail($product->id);

            // Limpiar datos del pivot para cada relación
            $product->categories->each(function ($category) {
                unset($category->pivot);
            });

            $product->materials->each(function ($material) {
                unset($material->pivot);
            });

            $product->attributes->each(function ($attribute) {
                $attribute->img = $attribute->pivot->img;
                unset($attribute->pivot);
            });

            $product->components->each(function ($component) {
                unset($component->pivot);
            });

            // Obtener el nombre del estado del producto desde la tabla product_status
            $status = ProductStatus::find($product->status);

            // Si se encuentra el estado, agrega su nombre al producto
            if ($status) {
                $product->status = [
                    'id' => $status->id,
                    'status_name' => $status->status_name
                ];
            } else {
                // Si no se encuentra el estado, puedes definir un valor por defecto o manejar el error
                $product->status = [
                    'id' => $product->status,
                    'status_name' => 'Unknown' // O cualquier valor por defecto
                ];
            }

            return ApiResponse::create('Producto creado correctamente', 200, $product);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear producto', 500, ['error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            Log::info('Request Data:', $request->all());

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:100|unique:products,sku,' . $id,
                'slug' => 'required|string|max:255|unique:products,slug,' . $id,
                'description' => 'nullable|string',
                'description_bold' => 'required|in:true,false',
                'description_italic' => 'required|in:true,false',
                'description_underline' => 'required|in:true,false',
                'status' => 'required|integer|exists:status,id',
                'main_img' => 'nullable',
                'main_video' => 'nullable',
                'file_data_sheet' => 'nullable|file|mimes:pdf|max:5120',
                'featured' => 'nullable|boolean',
                'categories' => 'array',
                'categories.*' => 'integer|exists:products_categories,id',
                'gallery' => 'array',
                'gallery.*' => 'file|mimes:jpg,jpeg,png,mp4,mov,avi|max:10240',
                'materials_values' => 'array',
                'materials_values.*.id_material_value' => 'required|integer|exists:material_values,id',
                'materials_values.*.img' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
                'attributes_values' => 'array',
                'attributes_values.*.id_attribute_value' => 'required|integer|exists:attribute_values,id',
                'attributes_values.*.img' => 'nullable',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $product = Product::with('attributes')->findOrFail($id);

            // Actualizar y eliminar imágenes si es necesario
            if ($request->hasFile('main_img')) {
                Storage::delete($product->main_img);
                $product->main_img = $request->file('main_img')->store('products/images', 'public');
            }

            if ($request->hasFile('main_video')) {
                Storage::delete($product->main_video);
                $product->main_video = $request->file('main_video')->store('products/videos', 'public');
            }

            if ($request->hasFile('file_data_sheet')) {
                Storage::delete($product->file_data_sheet);
                $product->file_data_sheet = $request->file('file_data_sheet')->store('products/data_sheets', 'public');
            }

            $product->update([
                'name' => $request->name,
                'sku' => $request->sku,
                'slug' => $request->slug,
                'description' => $request->description,
                'status' => $request->status,
                'featured' => $request->featured,
            ]);

            // Eliminar imágenes de la galería
            if ($request->has('gallery')) {
                foreach ($product->gallery as $gallery) {
                    Storage::delete($gallery->file);
                    $gallery->delete();
                }

                foreach ($request->gallery as $file) {
                    $filePath = $file->store('products/gallery', 'public');
                    ProductGallery::create(['id_product' => $product->id, 'file' => $filePath]);
                }
            }

            // Eliminar relaciones de categorías y volver a crearlas
            ProductCategory::where('id_product', $product->id)->delete();
            if ($request->has('categories')) {
                foreach ($request->categories as $categoryId) {
                    ProductCategory::create(['id_product' => $product->id, 'id_categorie' => $categoryId]);
                }
            }

            // Eliminar materiales y volver a crearlos
            ProductMaterial::where('id_product', $product->id)->delete();
            if ($request->has('materials_values')) {
                foreach ($request->materials_values as $material) {
                    $materialImgPath = $request->hasFile('materials_values.*.img') ? $material['img']->store('products/materials', 'public') : null;
                    ProductMaterial::create([
                        'id_product' => $product->id,
                        'id_material' => $material['id_material_value'],
                        'img' => $materialImgPath,
                    ]);
                }
            }

            // Eliminar atributos y volver a crearlos
            ProductAttribute::where('id_product', $product->id)->delete();
            if ($request->has('attributes_values')) {
                foreach ($request->attributes_values as $attribute) {
                    $attributeImgPath = $request->hasFile('attributes_values.*.img') ? $attribute['img']->store('products/attributes', 'public') : null;
                    ProductAttribute::create([
                        'id_product' => $product->id,
                        'id_attribute_value' => $attribute['id_attribute_value'],
                        'img' => $attributeImgPath,
                    ]);
                }
            }

            // Eliminar componentes y volver a crearlos
            ProductComponent::where('id_product', $product->id)->delete();
            if ($request->has('components')) {
                foreach ($request->components as $componentId) {
                    ProductComponent::create(['id_product' => $product->id, 'id_component' => $componentId]);
                }
            }

            $product = Product::with([
                'categories',
                'materials.material',
                'attributes.attribute',
                'gallery',
                'components'
            ])
                ->findOrFail($product->id);

            // Limpiar datos del pivot para cada relación
            $product->categories->each(function ($category) {
                unset($category->pivot);
            });

            $product->materials->each(function ($material) {
                unset($material->pivot);
            });

            $product->attributes->each(function ($attribute) {
                $attribute->img = $attribute->pivot->img;
                unset($attribute->pivot);
            });

            $product->components->each(function ($component) {
                unset($component->pivot);
            });

            // Obtener el nombre del estado del producto desde la tabla product_status
            $status = ProductStatus::find($product->status);

            // Si se encuentra el estado, agrega su nombre al producto
            if ($status) {
                $product->status = [
                    'id' => $status->id,
                    'status_name' => $status->status_name
                ];
            } else {
                // Si no se encuentra el estado, puedes definir un valor por defecto o manejar el error
                $product->status = [
                    'id' => $product->status,
                    'status_name' => 'Unknown' // O cualquier valor por defecto
                ];
            }

            return ApiResponse::create('Producto actualizado correctamente', 200, $product);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar producto', 500, ['error' => $e->getMessage()]);
        }
    }

    private function buildTree($items)
    {
        foreach ($items as $item) {
            $children = $item->children()->with('values')->get();
            if ($children->isNotEmpty()) {
                $item->materials = $this->buildTree($children);
            }
        }

        return $items;
    }
}
