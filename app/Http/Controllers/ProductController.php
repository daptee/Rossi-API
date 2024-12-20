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
            $category_id = $request->query('category_id');
            $search = $request->query('search');
            $perPage = $request->query('per_page', 30);

            // Consulta inicial
            $query = Product::select('products.id', 'products.name', 'products.main_img', 'products.status', 'products.featured', 'product_status.status_name', 'products.sku', 'products.slug', 'products.created_at')
                ->join('product_status', 'products.status', '=', 'product_status.id')
                ->with(['categories.parent', 'materials', 'attributes', 'gallery', 'components'])
                ->withCount(['categories', 'materials', 'attributes', 'gallery', 'components']);

            if ($category_id) {
                $query->whereHas('categories', function ($query) use ($category_id) {
                    $query->where('categories.id', $category_id)
                        ->orWhere('categories.id_category', $category_id);
                });
            }

            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('products.name', 'like', "%$search%")
                        ->orWhere('products.sku', 'like', "%$search%");
                });
            }

            $products = $query->paginate($perPage);

            $products->getCollection()->transform(function ($product) {
                $categories = collect();

                foreach ($product->categories as $category) {
                    if ($category->id_category) {
                        $parentCategory = $category->parent;
                        if ($parentCategory && !$categories->contains('id', $parentCategory->id)) {
                            $categories->push([
                                'id' => $parentCategory->id,
                                'category' => $parentCategory->category,
                            ]);
                        }
                    }

                    $categories->push([
                        'id' => $category->id,
                        'category' => $category->category,
                    ]);
                }

                $uniqueCategories = $categories->unique('id')->values();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
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

            $metaData = [
                'page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ];

            return ApiResponse::create('Productos obtenidos correctamente', 200, $products->items(), $metaData);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los productos', 500, [], ['error' => $e->getMessage()]);
        }
    }

    // GET ALL (para web)
    public function indexWeb(Request $request)
    {
        try {
            $featured = $request->query('featured');
            $search = $request->query('search'); // Parámetro de búsqueda
            $perPage = $request->query('per_page', 30); // Número de elementos por página, por defecto 30

            // Consulta inicial con relaciones necesarias
            $query = Product::with([
                'categories',
                'materials.material',
                'attributes.attribute',
                'gallery',
                'components'
            ])
                ->select(
                    'id', 
                    'name', 
                    'slug', 
                    'sku', 
                    'description', 
                    'description_bold', 
                    'description_italic', 
                    'description_underline', 
                    'main_img', 
                    'main_video', 
                    'file_data_sheet', 
                    'status', 
                    'featured'
                )
                ->where('status', 2); // Solo productos con estado 2

            // Filtrar por featured si el parámetro está presente
            if ($featured !== null) {
                $query->where('featured', $featured);
            }

            // Filtrar por búsqueda si el parámetro está presente
            if ($search !== null) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%') // Buscar en el nombre
                    ->orWhere('slug', 'like', '%' . $search . '%') // Buscar en el slug
                    ->orWhere('description', 'like', '%' . $search . '%'); // Buscar en la descripción
                });
            }

            // Obtener los productos paginados
            $products = $query->paginate($perPage);

            // Limpiar datos del pivot y ajustar relaciones
            $products->getCollection()->each(function ($product) {
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

                // Agregar nombre del estado o establecer valor por defecto
                if ($status) {
                    $product->status = [
                        'id' => $status->id,
                        'status_name' => $status->status_name
                    ];
                } else {
                    $product->status = [
                        'id' => $product->status,
                        'status_name' => 'Unknown'
                    ];
                }
            });

            // Metadata para paginación
            $metaData = [
                'page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ];

            // Respuesta con ApiResponse
            return ApiResponse::create('Productos obtenidos correctamente', 200, $products->items(), $metaData);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los productos', 500, [], ['error' => $e->getMessage()]);
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
                ->select('id', 'name', 'slug', 'sku', 'description', 'description_bold', 'description_italic', 'description_underline', 'main_img', 'main_video', 'file_data_sheet', 'status', 'featured')
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

    public function skuProduct($sku)
    {
        try {
            Log::info($sku);

            // Consulta el producto con todas las relaciones necesarias
            $product = Product::with([
                'categories',
                'materials.material',
                'attributes.attribute',
                'gallery',
                'components'
            ])
                ->select('id', 'name', 'slug', 'sku', 'description', 'description_bold', 'description_italic', 'description_underline', 'main_img', 'main_video', 'file_data_sheet', 'status', 'featured')
                ->where('sku', $sku)
                ->firstOrFail();

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

            if ($status) {
                $product->status = [
                    'id' => $status->id,
                    'status_name' => $status->status_name
                ];
            } else {
                $product->status = [
                    'id' => $product->status,
                    'status_name' => 'Unknown'
                ];
            }

            // Palabras a ignorar en la búsqueda
            $ignoreWords = ['silla', 'Silla', 'sillas', 'Sillas', 'mesa', 'Mesa', 'mesas', 'Mesas', 'escritorio', 'Escritorio', 'escritorios', 'Escritorios'];

            // Separar el nombre del producto principal en palabras y filtrar las ignoradas
            $productNameWords = collect(explode(' ', $product->name))
                ->reject(fn($word) => in_array(strtolower($word), $ignoreWords))
                ->values();

            // Buscar productos relacionados que contengan al menos una de las palabras clave
            $relatedProducts = Product::select('id', 'name', 'slug', 'sku', 'main_img')
                ->where(function ($query) use ($productNameWords) {
                    foreach ($productNameWords as $word) {
                        $query->orWhere('name', 'LIKE', '%' . $word . '%');
                    }
                })
                ->where('id', '!=', $product->id) // Excluir el producto principal
                ->get();

            // Agregar los productos relacionados al array principal
            $product->products = $relatedProducts;

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
                'categories.*' => 'integer|exists:categories,id',
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

            // Rutas base en la carpeta public
            $baseStoragePath = public_path('storage/products');

            // Crear directorios si no existen
            if (!file_exists("$baseStoragePath/images"))
                mkdir("$baseStoragePath/images", 0755, true);
            if (!file_exists("$baseStoragePath/videos"))
                mkdir("$baseStoragePath/videos", 0755, true);
            if (!file_exists("$baseStoragePath/data_sheets"))
                mkdir("$baseStoragePath/data_sheets", 0755, true);
            if (!file_exists("$baseStoragePath/gallery"))
                mkdir("$baseStoragePath/gallery", 0755, true);
            if (!file_exists("$baseStoragePath/materials"))
                mkdir("$baseStoragePath/materials", 0755, true);
            if (!file_exists("$baseStoragePath/attributes"))
                mkdir("$baseStoragePath/attributes", 0755, true);

            // Almacenar archivos principales en la carpeta 'public/storage/products'
            // Almacenar archivos principales en la carpeta 'public/storage/products'
            $mainImgPath = $request->hasFile('main_img') ? $request->file('main_img')->move("$baseStoragePath/images", uniqid() . '_' . $request->file('main_img')->getClientOriginalName()) : null;
            $mainVideoPath = $request->hasFile('main_video') ? $request->file('main_video')->move("$baseStoragePath/videos", uniqid() . '_' . $request->file('main_video')->getClientOriginalName()) : null;
            $fileDataSheetPath = $request->hasFile('file_data_sheet') ? $request->file('file_data_sheet')->move("$baseStoragePath/data_sheets", uniqid() . '_' . $request->file('file_data_sheet')->getClientOriginalName()) : null;

            $product = Product::create([
                'name' => $request->name,
                'sku' => $request->sku,
                'slug' => $request->slug,
                'description' => $request->description,
                'description_bold' => $request->description_bold,
                'description_italic' => $request->description_italic,
                'description_underline' => $request->description_underline,
                'status' => $request->status,
                'main_img' => $mainImgPath ? "storage/products/images/" . basename($mainImgPath) : null,
                'main_video' => $mainVideoPath ? "storage/products/videos/" . basename($mainVideoPath) : null,
                'file_data_sheet' => $fileDataSheetPath ? "storage/products/data_sheets/" . basename($fileDataSheetPath) : null,
                'featured' => $request->featured,
            ]);


            // Guardar archivos en la galería
            if ($request->has('gallery')) {
                foreach ($request->gallery as $file) {
                    $uniqueName = uniqid() . '_' . $file->getClientOriginalName();
                    $filePath = $file->move("$baseStoragePath/gallery", $uniqueName);
                    ProductGallery::create(['id_product' => $product->id, 'file' => "storage/products/gallery/" . $uniqueName]);
                }
            }

            // Asociar categorías al producto
            if ($request->has('categories')) {
                foreach ($request->categories as $categoryId) {
                    ProductCategory::create(['id_product' => $product->id, 'id_categorie' => $categoryId]);
                }
            }

            // Guardar materiales asociados
            if ($request->has('materials_values')) {
                foreach ($request->materials_values as $index => $material) {
                    $materialImgPath = isset($material['img']) && $request->hasFile("materials_values.$index.img")
                        ? $request->file("materials_values.$index.img")->move("$baseStoragePath/materials", uniqid() . '_' . $request->file("materials_values.$index.img")->getClientOriginalName())
                        : null;

                    ProductMaterial::create([
                        'id_product' => $product->id,
                        'id_material' => $material['id_material_value'],
                        'img' => $materialImgPath ? "storage/products/materials/" . basename($materialImgPath) : null,
                    ]);
                }
            }

            // Guardar atributos asociados
            if ($request->has('attributes_values')) {
                foreach ($request->attributes_values as $index => $attribute) {
                    $attributeImgPath = isset($attribute['img']) && $request->hasFile("attributes_values.$index.img")
                        ? $request->file("attributes_values.$index.img")->move("$baseStoragePath/attributes", uniqid() . '_' . $request->file("attributes_values.$index.img")->getClientOriginalName())
                        : null;

                    ProductAttribute::create([
                        'id_product' => $product->id,
                        'id_attribute_value' => $attribute['id_attribute_value'],
                        'img' => $attributeImgPath ? "storage/products/attributes/" . basename($attributeImgPath) : null,
                    ]);
                }
            }

            // Asociar componentes al producto
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
            ])->findOrFail($product->id);

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

            $status = ProductStatus::find($product->status);
            $product->status = $status ? ['id' => $status->id, 'status_name' => $status->status_name] : ['id' => $product->status, 'status_name' => 'Unknown'];

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
                'categories.*' => 'integer|exists:categories,id',
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

            $baseStoragePath = public_path('storage/products');

            // Crear directorios si no existen
            if (!file_exists("$baseStoragePath/images"))
                mkdir("$baseStoragePath/images", 0755, true);
            if (!file_exists("$baseStoragePath/videos"))
                mkdir("$baseStoragePath/videos", 0755, true);
            if (!file_exists("$baseStoragePath/data_sheets"))
                mkdir("$baseStoragePath/data_sheets", 0755, true);
            if (!file_exists("$baseStoragePath/gallery"))
                mkdir("$baseStoragePath/gallery", 0755, true);
            if (!file_exists("$baseStoragePath/materials"))
                mkdir("$baseStoragePath/materials", 0755, true);
            if (!file_exists("$baseStoragePath/attributes"))
                mkdir("$baseStoragePath/attributes", 0755, true);

            // Manejo de main_img
            if ($request->has('main_img')) {
                if (is_null($request->main_img)) {
                    if ($product->main_img && file_exists(public_path($product->main_img))) {
                        unlink(public_path($product->main_img));
                    }
                    $product->main_img = null;
                } elseif ($request->hasFile('main_img')) {
                    if ($product->main_img && file_exists(public_path($product->main_img))) {
                        unlink(public_path($product->main_img));
                    }

                    // Generar nombre aleatorio
                    $randomName = uniqid() . '_' . $request->file('main_img')->getClientOriginalName();
                    $destinationPath = public_path('storage/products/images/');
                    $request->file('main_img')->move($destinationPath, $randomName);
                    $product->main_img = 'storage/products/images/' . $randomName;
                }
            }

            // Manejo de main_video
            if ($request->has('main_video')) {
                if (is_null($request->main_video)) {
                    if ($product->main_video && file_exists(public_path($product->main_video))) {
                        unlink(public_path($product->main_video));
                    }
                    $product->main_video = null;
                } elseif ($request->hasFile('main_video')) {
                    if ($product->main_video && file_exists(public_path($product->main_video))) {
                        unlink(public_path($product->main_video));
                    }

                    // Generar nombre aleatorio
                    $randomName = uniqid() . '_' . $request->file('main_video')->getClientOriginalName();
                    $destinationPath = public_path('storage/products/videos/');
                    $request->file('main_video')->move($destinationPath, $randomName);
                    $product->main_video = 'storage/products/videos/' . $randomName;
                }
            }

            // Manejo de file_data_sheet
            if ($request->has('file_data_sheet')) {
                if (is_null($request->file_data_sheet)) {
                    if ($product->file_data_sheet && file_exists(public_path($product->file_data_sheet))) {
                        unlink(public_path($product->file_data_sheet));
                    }
                    $product->file_data_sheet = null;
                } elseif ($request->hasFile('file_data_sheet')) {
                    if ($product->file_data_sheet && file_exists(public_path($product->file_data_sheet))) {
                        unlink(public_path($product->file_data_sheet));
                    }

                    // Generar nombre aleatorio
                    $randomName = uniqid() . '_' . $request->file('file_data_sheet')->getClientOriginalName();
                    $destinationPath = public_path('storage/products/data_sheets/');
                    $request->file('file_data_sheet')->move($destinationPath, $randomName);
                    $product->file_data_sheet = 'storage/products/data_sheets/' . $randomName;
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
                        if (file_exists(public_path($gallery->file))) {
                            unlink(public_path($gallery->file));
                        }

                        // Guardamos la nueva imagen
                        $randomName = uniqid() . '_' . $galleryItem['file']->getClientOriginalName();
                        $destinationPath = public_path('storage/products/gallery/');
                        $galleryItem['file']->move($destinationPath, $randomName);
                        $gallery->update(['file' => 'storage/products/gallery/' . $randomName]);
                    }

                    // Si el 'id' está vacío, es una nueva imagen, se sube el archivo
                    if (empty($galleryItem['id']) && isset($galleryItem['file'])) {
                        $randomName = uniqid() . '_' . $galleryItem['file']->getClientOriginalName();
                        $destinationPath = public_path('storage/products/gallery/');
                        $galleryItem['file']->move($destinationPath, $randomName);
                        $product->gallery()->create(['file' => 'storage/products/gallery/' . $randomName]);
                    }

                    // Si el 'id' está presente y el 'file' es null, eliminamos la imagen correspondiente
                    if (isset($galleryItem['id']) && $galleryItem['file'] === null) {
                        $gallery = ProductGallery::findOrFail($galleryItem['id']);

                        // Eliminar la imagen
                        if (file_exists(public_path($gallery->file))) {
                            unlink(public_path($gallery->file));
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
                    if (file_exists(public_path($gallery->file))) {
                        unlink(public_path($gallery->file));
                    }

                    $gallery->delete();
                }
            }

            // Actualizar materiales asociados
            if (!$request->has('materials_values') || empty($request->materials_values)) {
                // Si no se envían materiales, eliminar todos los materiales asociados al producto
                $productMaterials = ProductMaterial::where('id_product', $product->id)->get();

                foreach ($productMaterials as $material) {
                    // Eliminar la imagen si existe
                    if ($material->img && file_exists(public_path($material->img))) {
                        unlink(public_path($material->img));
                    }

                    // Eliminar el registro de la base de datos
                    $material->delete();
                }
            } else {
                // Crear un array de IDs de materiales enviados
                $sentMaterialIds = collect($request->materials_values)->pluck('id_material_value')->all();

                // Eliminar materiales que ya no están en la solicitud
                $existingMaterials = ProductMaterial::where('id_product', $product->id)->get();
                foreach ($existingMaterials as $existingMaterial) {
                    if (!in_array($existingMaterial->id_material, $sentMaterialIds)) {
                        // Eliminar la imagen si existe
                        if ($existingMaterial->img && file_exists(public_path($existingMaterial->img))) {
                            unlink(public_path($existingMaterial->img));
                        }
                        // Eliminar el material del producto
                        $existingMaterial->delete();
                    }
                }

                // Procesar materiales enviados
                foreach ($request->materials_values as $index => $material) {
                    $existingMaterial = ProductMaterial::where('id_product', $product->id)
                        ->where('id_material', $material['id_material_value'])
                        ->first();

                    $materialImgPath = $existingMaterial ? $existingMaterial->img : null;

                    // Verificar si el material ya existe
                    if ($existingMaterial) {
                        if ($request->hasFile("materials_values.$index.img")) {
                            // Si se envía una nueva imagen, eliminar la anterior si es diferente
                            if ($existingMaterial->img && file_exists(public_path($existingMaterial->img))) {
                                unlink(public_path($existingMaterial->img));
                            }

                            // Guardar la nueva imagen
                            $randomName = uniqid() . '_' . $request->file("materials_values.$index.img")->getClientOriginalName();
                            $destinationPath = public_path('storage/products/materials/');
                            $request->file("materials_values.$index.img")->move($destinationPath, $randomName);
                            $materialImgPath = 'storage/products/materials/' . $randomName;

                        } elseif (!isset($material['img'])) {
                            // Si 'img' no está definido, eliminar la imagen existente
                            if ($existingMaterial->img && file_exists(public_path($existingMaterial->img))) {
                                unlink(public_path($existingMaterial->img));
                            }
                            $materialImgPath = null; // Se elimina la referencia a la imagen
                        }
                    } else {
                        // Crear material nuevo con imagen solo si se ha enviado una
                        if ($request->hasFile("materials_values.$index.img")) {
                            $randomName = uniqid() . '_' . $request->file("materials_values.$index.img")->getClientOriginalName();
                            $destinationPath = public_path('storage/products/materials/');
                            $request->file("materials_values.$index.img")->move($destinationPath, $randomName);
                            $materialImgPath = 'storage/products/materials/' . $randomName;
                        }
                    }

                    // Crear o actualizar la relación con la imagen actualizada o eliminada
                    ProductMaterial::updateOrCreate(
                        ['id_product' => $product->id, 'id_material' => $material['id_material_value']],
                        ['img' => $materialImgPath]
                    );
                }
            }

            // Actualizar atributos asociados
            if ($request->has('attributes_values')) {
                // Obtener los ids de los atributos existentes en el producto
                $existingAttributeValues = ProductAttribute::where('id_product', $product->id)
                    ->pluck('id_attribute_value')->toArray();

                // Obtener los ids de los atributos que vienen en la solicitud
                $sentAttributeValues = array_column($request->attributes_values, 'id_attribute_value');

                // Eliminar atributos cuyo id no está presente en la lista enviada
                $idsToDelete = array_diff($existingAttributeValues, $sentAttributeValues);

                foreach ($idsToDelete as $id) {
                    // Buscar la relación y eliminarla
                    $attributeInstance = ProductAttribute::where('id_product', $product->id)
                        ->where('id_attribute_value', $id)
                        ->first();

                    if ($attributeInstance) {
                        // Eliminar la imagen si existe
                        if ($attributeInstance->img && file_exists(public_path($attributeInstance->img))) {
                            unlink(public_path($attributeInstance->img));
                        }

                        // Eliminar el registro de la base de datos
                        $attributeInstance->delete();
                    }
                }

                // Ahora, procesamos los atributos enviados en la solicitud
                foreach ($request->attributes_values as $index => $attribute) {
                    $attributeInstance = ProductAttribute::where('id_product', $product->id)
                        ->where('id_attribute_value', $attribute['id_attribute_value'])
                        ->first();

                    $attributeImgPath = null;

                    if (isset($attribute['img']) && is_string($attribute['img'])) {
                        // Conserva la imagen actual si es un string
                        $attributeImgPath = $attributeInstance ? $attributeInstance->img : null;
                    } elseif (isset($attribute['img']) && $attribute['img'] === null) {
                        // Elimina la imagen actual si se pasa null
                        if ($attributeInstance && $attributeInstance->img && file_exists(public_path($attributeInstance->img))) {
                            unlink(public_path($attributeInstance->img));
                        }
                        $attributeImgPath = null;
                    } elseif ($request->hasFile("attributes_values.$index.img")) {
                        // Actualiza con la nueva imagen si hay un archivo
                        if ($attributeInstance && $attributeInstance->img && file_exists(public_path($attributeInstance->img))) {
                            unlink(public_path($attributeInstance->img));
                        }
                        $randomName = uniqid() . '_' . $request->file("attributes_values.$index.img")->getClientOriginalName();
                        $destinationPath = public_path('storage/products/attributes/');
                        $request->file("attributes_values.$index.img")->move($destinationPath, $randomName);
                        $attributeImgPath = 'storage/products/attributes/' . $randomName;
                    }

                    // Actualiza o crea la relación con la nueva imagen
                    ProductAttribute::updateOrCreate(
                        ['id_product' => $product->id, 'id_attribute_value' => $attribute['id_attribute_value']],
                        ['img' => $attributeImgPath]
                    );
                }
            } else {
                // Si no se envían attributes_values, eliminamos todos los atributos relacionados al producto
                $existingAttributes = ProductAttribute::where('id_product', $product->id)->get();

                foreach ($existingAttributes as $attributeInstance) {
                    // Eliminar la imagen si existe
                    if ($attributeInstance->img && file_exists(public_path($attributeInstance->img))) {
                        unlink(public_path($attributeInstance->img));
                    }

                    // Eliminar el atributo
                    $attributeInstance->delete();
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

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // Intentar eliminar la imagen principal si existe
            try {
                if ($product->main_img && file_exists(public_path($product->main_img))) {
                    unlink(public_path($product->main_img));
                }
            } catch (Exception $e) {
                Log::error("Error al eliminar la imagen principal: " . $e->getMessage());
            }

            // Intentar eliminar el video principal si existe
            try {
                if ($product->main_video && file_exists(public_path($product->main_video))) {
                    unlink(public_path($product->main_video));
                }
            } catch (Exception $e) {
                Log::error("Error al eliminar el video principal: " . $e->getMessage());
            }

            // Intentar eliminar el archivo de la ficha técnica si existe
            try {
                if ($product->file_data_sheet && file_exists(public_path($product->file_data_sheet))) {
                    unlink(public_path($product->file_data_sheet));
                }
            } catch (Exception $e) {
                Log::error("Error al eliminar la ficha técnica: " . $e->getMessage());
            }

            // Intentar eliminar las imágenes de la galería
            foreach ($product->gallery as $galleryItem) {
                try {
                    if (file_exists(public_path($galleryItem->file))) {
                        unlink(public_path($galleryItem->file));
                    }
                    $galleryItem->delete(); // Eliminar el registro en la base de datos
                } catch (Exception $e) {
                    Log::error("Error al eliminar imagen de galería: " . $e->getMessage());
                }
            }

            // Intentar eliminar imágenes de los atributos
            $product->attributes->each(function ($attribute) {
                try {
                    if ($attribute->pivot->img && file_exists(public_path($attribute->pivot->img))) {
                        unlink(public_path($attribute->pivot->img));
                    }
                } catch (Exception $e) {
                    Log::error("Error al eliminar imagen de atributo: " . $e->getMessage());
                }
            });

            // Intentar eliminar imágenes de los materiales
            $product->materials->each(function ($material) {
                try {
                    if ($material->pivot->img && file_exists(public_path($material->pivot->img))) {
                        unlink(public_path($material->pivot->img));
                    }
                } catch (Exception $e) {
                    Log::error("Error al eliminar imagen de material: " . $e->getMessage());
                }
            });

            // Intentar eliminar las relaciones de categorías
            try {
                $product->categories()->detach();
            } catch (Exception $e) {
                Log::error("Error al eliminar relaciones de categorías: " . $e->getMessage());
            }

            // Intentar eliminar las relaciones de materiales
            try {
                $product->materials()->detach();
            } catch (Exception $e) {
                Log::error("Error al eliminar relaciones de materiales: " . $e->getMessage());
            }

            // Intentar eliminar las relaciones de componentes
            try {
                $product->components()->detach();
            } catch (Exception $e) {
                Log::error("Error al eliminar relaciones de componentes: " . $e->getMessage());
            }

            // Intentar eliminar las relaciones de atributos
            try {
                $product->attributes()->detach();
            } catch (Exception $e) {
                Log::error("Error al eliminar relaciones de atributos: " . $e->getMessage());
            }

            // Intentar eliminar el producto de la base de datos
            try {
                $product->delete();
            } catch (Exception $e) {
                Log::error("Error al eliminar el producto: " . $e->getMessage());
                return ApiResponse::create('An error occurred while deleting the product.', 500);
            }

            return ApiResponse::create('Producto eliminado correctamente.', 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ApiResponse::create('An error occurred while deleting the product.', 500);
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
