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
            $query = Product::select('products.id', 'products.name', 'products.main_img', 'products.status', 'products.featured', 'product_status.status_name', 'products.slug', 'products.created_at')
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
                    'slug' => $product->slug,
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
                    'created_date' => $product->created_at,
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
                ->select('id', 'name', 'description', 'description_bold', 'description_italic', 'description_underline', 'main_img', 'main_video', 'file_data_sheet', 'status', 'featured')
                ->findOrFail($id);

            // Limpia los datos del pivot para cada relación
            $product->categories->each(function ($category) {
                unset($category->pivot);
            });

            $product->materials->each(function ($material) {
                $material->img_value = $material->pivot->img;
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
                foreach ($request->materials_values as $index => $material) {
                    // Verificar si existe una imagen para el material específico
                    $materialImgPath = isset($material['img']) && $request->hasFile("materials_values.$index.img")
                        ? $request->file("materials_values.$index.img")->store('products/materials', 'public')
                        : null;

                    ProductMaterial::create([
                        'id_product' => $product->id,
                        'id_material' => $material['id_material_value'],
                        'img' => $materialImgPath,
                    ]);
                }
            }

            // Guardar atributos asociados al producto
            if ($request->has('attributes_values')) {
                foreach ($request->attributes_values as $index => $attribute) {
                    // Verificar si existe una imagen para el atributo específico
                    $attributeImgPath = isset($attribute['img']) && $request->hasFile("attributes_values.$index.img")
                        ? $request->file("attributes_values.$index.img")->store('products/attributes', 'public')
                        : null;

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
                $material->img_value = $material->pivot->img;
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
                'description_bold' => 'nullable|required|in:1,0',
                'description_italic' => 'nullable|required|in:1,0',
                'description_underline' => 'nullable|required|in:1,0',
                'status' => 'required|integer|exists:product_status,id',
                'main_img' => 'nullable',
                'main_video' => 'nullable',
                'file_data_sheet' => 'nullable',
                'featured' => 'nullable|boolean',
                'categories' => 'array',
                'categories.*' => 'integer|exists:products_categories,id',
                'gallery' => 'array',
                'gallery.*.id' => 'sometimes|exists:product_galleries,id',
                'gallery.*.file' => 'nullable',
                'gallery.*' => 'nullable',
                'materials_values' => 'array',
                'materials_values.*.id_material_value' => 'required|integer|exists:material_values,id',
                'materials_values.*.img' => 'nullable',
                'attributes_values' => 'array',
                'attributes_values.*.id_attribute_value' => 'required|integer|exists:attribute_values,id',
                'attributes_values.*.img' => 'nullable',
                'components' => 'array',
                'components.*' => 'integer|exists:components,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $product = Product::findOrFail($id);

            // Manejo de main_img
            if ($request->has('main_img')) {
                if (is_null($request->main_img)) {
                    if ($product->main_img && Storage::disk('public')->exists($product->main_img)) {
                        Storage::disk('public')->delete($product->main_img);
                    }
                    $product->main_img = null;
                } elseif ($request->hasFile('main_img')) {
                    if ($product->main_img && Storage::disk('public')->exists($product->main_img)) {
                        Storage::disk('public')->delete($product->main_img);
                    }
                    $product->main_img = $request->file('main_img')->store('products/images', 'public');
                }
            }

            // Manejo de main_video
            if ($request->has('main_video')) {
                if (is_null($request->main_video)) {
                    if ($product->main_video && Storage::disk('public')->exists($product->main_video)) {
                        Storage::disk('public')->delete($product->main_video);
                    }
                    $product->main_video = null;
                } elseif ($request->hasFile('main_video')) {
                    if ($product->main_video && Storage::disk('public')->exists($product->main_video)) {
                        Storage::disk('public')->delete($product->main_video);
                    }
                    $product->main_video = $request->file('main_video')->store('products/videos', 'public');
                }
            }

            // Manejo de file_data_sheet
            if ($request->has('file_data_sheet')) {
                if (is_null($request->file_data_sheet)) {
                    if ($product->file_data_sheet && Storage::disk('public')->exists($product->file_data_sheet)) {
                        Storage::disk('public')->delete($product->file_data_sheet);
                    }
                    $product->file_data_sheet = null;
                } elseif ($request->hasFile('file_data_sheet')) {
                    if ($product->file_data_sheet && Storage::disk('public')->exists($product->file_data_sheet)) {
                        Storage::disk('public')->delete($product->file_data_sheet);
                    }
                    $product->file_data_sheet = $request->file('file_data_sheet')->store('products/data_sheets', 'public');
                }
            }

            $product->update([
                'name' => $request->name,
                'sku' => $request->sku,
                'slug' => $request->slug,
                'description' => $request->description,
                'description_bold' => $request->description_bold,
                'description_italic' => $request->description_italic,
                'description_underline' => $request->description_underline,
                'status' => $request->status,
                'featured' => $request->featured,
            ]);

            // Actualizar categorías asociadas
            $product->categories()->sync($request->categories ?? []);

            // Manejo de la galería de imágenes
            if ($request->has('gallery')) {
                // Obtener los IDs actuales de la galería
                $currentGalleryIds = $product->gallery->pluck('id')->toArray();
            
                // Crear un array de IDs que se enviaron en la solicitud
                $sentGalleryIds = array_filter(array_map(function ($galleryItem) {
                    return isset($galleryItem['id']) ? $galleryItem['id'] : null;
                }, $request->gallery));
            
                // Filtrar los elementos de la galería enviados
                foreach ($request->gallery as $galleryItem) {
                    // Si el galleryItem tiene 'id' y 'file', manejamos las actualizaciones
                    if (isset($galleryItem['id']) && isset($galleryItem['file'])) {
                        // Si el 'file' es un string (y no es un archivo), significa que se debe mantener la imagen
                        if (is_string($galleryItem['file'])) {
                            continue;  // No se hace nada si el archivo es el mismo
                        }
            
                        $gallery = ProductGallery::findOrFail($galleryItem['id']);
                        
                        // Si el 'file' ha cambiado (es un archivo), eliminamos la imagen antigua
                        if (Storage::disk('public')->exists($gallery->file)) {
                            Storage::disk('public')->delete($gallery->file);
                        }
            
                        // Guardamos la nueva imagen
                        $newFilePath = $galleryItem['file']->store('products/gallery', 'public');
                        $gallery->update(['file' => $newFilePath]);
                    }
            
                    // Si el 'id' está vacío, es una nueva imagen, se sube el archivo
                    if (empty($galleryItem['id']) && isset($galleryItem['file'])) {
                        $filePath = $galleryItem['file']->store('products/gallery', 'public');
                        $product->gallery()->create(['file' => $filePath]);
                    }
            
                    // Si el 'id' está presente y el 'file' es null, eliminamos la imagen correspondiente
                    if (isset($galleryItem['id']) && $galleryItem['file'] === null) {
                        $gallery = ProductGallery::findOrFail($galleryItem['id']);
            
                        // Eliminar la imagen
                        if (Storage::disk('public')->exists($gallery->file)) {
                            Storage::disk('public')->delete($gallery->file);
                        }
            
                        $gallery->delete();
                    }
                }
            
                // Eliminar las imágenes cuyo ID no está presente en la lista de IDs enviados
                $idsToDelete = array_diff($currentGalleryIds, $sentGalleryIds);
            
                // Eliminar las imágenes correspondientes
                foreach ($idsToDelete as $id) {
                    $gallery = ProductGallery::findOrFail($id);
            
                    // Eliminar la imagen de la galería
                    if (Storage::disk('public')->exists($gallery->file)) {
                        Storage::disk('public')->delete($gallery->file);
                    }
            
                    $gallery->delete();
                }
            }                      


            // Actualizar atributos asociados
            if ($request->has('attributes_values')) {
                foreach ($request->attributes_values as $index => $attribute) {
                    $attributeInstance = ProductAttribute::where('id_product', $product->id)
                        ->where('id_attribute_value', $attribute['id_attribute_value'])
                        ->first();

                    $attributeImgPath = null;

                    if (isset($attribute['img']) && is_string($attribute['img'])) {
                        // Conserva la imagen actual si es un string
                        $attributeImgPath = $attributeInstance->img;
                    } elseif (isset($attribute['img']) && $attribute['img'] === null) {
                        // Elimina la imagen actual si se pasa null
                        if ($attributeInstance && $attributeInstance->img && Storage::disk('public')->exists($attributeInstance->img)) {
                            Storage::disk('public')->delete($attributeInstance->img);
                        }
                        $attributeImgPath = null;
                    } elseif ($request->hasFile("attributes_values.$index.img")) {
                        // Actualiza con la nueva imagen si hay un archivo
                        if ($attributeInstance && $attributeInstance->img && Storage::disk('public')->exists($attributeInstance->img)) {
                            Storage::disk('public')->delete($attributeInstance->img);
                        }
                        $attributeImgPath = $request->file("attributes_values.$index.img")->store('products/attributes', 'public');
                    }

                    ProductAttribute::updateOrCreate(
                        ['id_product' => $product->id, 'id_attribute_value' => $attribute['id_attribute_value']],
                        ['img' => $attributeImgPath]
                    );
                }
            }

            // Actualizar componentes asociados
            $product->components()->sync($request->components ?? []);

            $product = Product::with([
                'categories',
                'materials.material',
                'attributes.attribute',
                'gallery',
                'components'
            ])->findOrFail($product->id);

            $product->categories->each(function ($category) {
                unset($category->pivot);
            });

            $product->materials->each(function ($material) {
                $material->img_value = $material->pivot->img;
                unset($material->pivot);
            });

            $product->attributes->each(function ($attribute) {
                $attribute->img = $attribute->pivot->img;
                unset($attribute->pivot);
            });

            $product->components->each(function ($component) {
                unset($component->pivot);
            });

            $status = ProductStatus::find($product->status);
            $product->status = $status ? ['id' => $status->id, 'status_name' => $status->status_name] : ['id' => $product->status, 'status_name' => 'Unknown'];

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
