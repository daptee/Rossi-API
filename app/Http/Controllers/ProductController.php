<?php

namespace App\Http\Controllers;

use App\Helpers\ImageHelper;
use App\Http\Responses\ApiResponse;
use App\Models\Product3DModel;
use App\Models\ProductComponent;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\ProductsRelated;
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
            $perPage = $request->query('per_page', 300000000);

            // Consulta inicial
            $query = Product::select('products.id', 'products.name', 'products.main_img', 'products.thumbnail_main_img', 'products.sub_img', 'products.thumbnail_main_img', 'products.status', 'products.featured', 'product_status.status_name', 'products.sku', 'products.slug', 'products.meta_data', 'products.created_at')
                ->join('product_status', 'products.status', '=', 'product_status.id')
                ->with(['categories.parent', 'materials', 'attributes', 'gallery', 'components', 'relatedProducts'])
                ->withCount(['categories', 'materials', 'attributes', 'gallery', 'components', 'relatedProducts']);

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
                    'thumbnail_main_img' => $product->thumbnail_main_img,
                    'sub_img' => $product->sub_img,
                    'thumbnail_sub_img' => $product->thumbnail_sub_img,
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
                    'meta_data' => $product->meta_data,
                    'related_products' => $product->relatedProducts->map(function ($related) {
                        return [
                            'id' => $related->related->id,
                            'name' => $related->related->name,
                            'sku' => $related->related->sku,
                            'main_img' => $related->related->main_img,
                            'thumbnail_main_img' => $related->related->thumbnail_main_img,
                        ];
                    }),
                    'created_date' => $product->created_at,
                ];
            });

            if ($perPage === 300000000) {
                $metaData = [
                    'page' => $products->currentPage(),
                    'per_page' => null,
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ];
            } else {
                $metaData = [
                    'page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ];
            }



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
            $perPage = $request->query('per_page', 30000000000); // Número de elementos por página, por defecto 30

            // Consulta inicial con relaciones necesarias
            $query = Product::with([
                'categories',
                'materials.material',
                'attributes.attribute',
                'gallery',
                'components',
                'relatedProducts.related'
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
                    'thumbnail_main_img',
                    'sub_img',
                    'thumbnail_sub_img',
                    'main_video',
                    'file_data_sheet',
                    'status',
                    'featured',
                    'meta_data'
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
            if ($request->query('per_page') === null) {
                $metaData = [
                    'page' => $products->currentPage(),
                    'per_page' => null,
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ];
            } else {
                $metaData = [
                    'page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ];
            }

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
                'components',
                'product3DModels',
                'relatedProducts.related'
            ])
                ->select('id', 'name', 'slug', 'sku', 'description', 'description_bold', 'description_italic', 'description_underline', 'main_img', 'thumbnail_main_img', 'sub_img', 'thumbnail_sub_img', 'customizable', 'main_video', 'file_data_sheet', 'status', 'featured', 'meta_data')
                ->findOrFail($id);

            // Limpia los datos del pivot para cada relación
            $product->categories->each(function ($category) {
                unset($category->pivot);
            });

            $product->materials->each(function ($material) {
                $material->img_value = $material->pivot->img;
                $material->thumbnail_img_value = $material->pivot->thumbnail_img;
                unset($material->pivot);
            });

            $product->attributes->each(function ($attribute) {
                $attribute->img = $attribute->pivot->img;
                $attribute->thumbnail_img = $attribute->pivot->thumbnail_img;
                unset($attribute->pivot);
            });

            $product->components->each(function ($component) {
                unset($component->pivot);
            });

            // Reorganiza los parent_attributes3d por id_attribute para fácil acceso
            $attributes3D = [];



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

            // Eliminar relaciones que ya fueron procesadas
            unset($product->materialFiles);
            unset($product->attributeFiles);
            unset($product->parentAttributes3d);

            return ApiResponse::create('Succeeded', 200, $product);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener el producto', 500, ['error' => $e->getMessage()]);
        }
    }

    public function skuProduct($sku)
    {
        try {

            // Consulta el producto con todas las relaciones necesarias
            $product = Product::with([
                'categories',
                'materials.material',
                'attributes.attribute',
                'gallery',
                'components',
                'product3DModels',
                'relatedProducts.related'
            ])
                ->select('id', 'name', 'slug', 'sku', 'description', 'description_bold', 'description_italic', 'description_underline', 'main_img', 'thumbnail_main_img', 'sub_img', 'thumbnail_sub_img', 'main_video', 'file_data_sheet', 'customizable', 'status', 'featured', 'meta_data')
                ->where('sku', $sku)
                ->first();

            if (!$product) {
                return ApiResponse::create('Producto no encontrado', 404);
            }

            // Limpia los datos del pivot para cada relación
            $product->categories->each(function ($category) {
                unset($category->pivot);
            });

            $product->materials->each(function ($material) {
                $material->img_value = $material->pivot->img;
                $material->thumbnail_img_value = $material->pivot->thumbnail_img;
                unset($material->pivot);
            });

            $product->attributes->each(function ($attribute) {
                $attribute->img = $attribute->pivot->img;
                $attribute->thumbnail_img = $attribute->pivot->thumbnail_img;
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

            // Palabras a ignorar
            $ignoreWords = collect([
                'silla',
                'sillas',
                'mesa',
                'mesas',
                'escritorio',
                'escritorios',
                'taburete',
                'taburetes',
                'wood',
                'woods',
                'tapizada',
                'tapizadas',
                'neumática',
                'neumáticas',
                'sillon',
                'sillones',
                'tándem',
                'tndem',
                'tándems',
                'operativa',
                'operativas',
                'ejecutiva',
                'ejecutivas',
                'gerencial',
                'gerenciales',
                'componente',
                'componentes',
                'escolares',
                'tapizado',
                'tapizados'
            ])->map(fn($word) => $this->normalizeString($word));

            // Normalizar el nombre del producto y separarlo en palabras
            $productNameWords = collect(explode(' ', $this->normalizeString($product->name)))
                ->reject(fn($word) => $ignoreWords->contains($word))
                ->values();

            Log::info('Palabras filtradas:', $productNameWords->toArray());

            if ($productNameWords->isEmpty()) {
                // Si no quedan palabras útiles, evitar consultas vacías
                $product->products = [];
            } else {
                // Buscar productos relacionados que contengan alguna de las palabras filtradas
                $relatedProducts = Product::select('id', 'name', 'slug', 'sku', 'main_img', 'thumbnail_main_img')
                    ->where(function ($query) use ($productNameWords) {
                        foreach ($productNameWords as $word) {
                            $query->orWhereRaw('LOWER(name) REGEXP ?', ["\\b" . preg_quote($word) . "\\b"]);
                        }
                    })
                    ->where('id', '!=', $product->id) // Excluir el producto principal
                    ->orderBy('name', 'asc') // Ordenar alfabéticamente
                    ->get();

                // Agregar los productos relacionados al producto principal
                $product->products = $relatedProducts;
            }

            return ApiResponse::create('Succeeded', 200, $product);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener el producto', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Función para normalizar cadenas eliminando acentos y caracteres especiales.
     */
    private function normalizeString($string)
    {
        $normalized = strtolower($string);
        $normalized = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $normalized
        );

        // Mantener solo caracteres alfanuméricos y espacios
        return preg_replace('/[^a-z0-9\s]/', '', $normalized);
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
                'sub_img' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
                'main_video' => 'nullable|file|mimes:mp4,mov,avi|max:10240',
                'file_data_sheet' => 'nullable|file|mimes:pdf|max:5120',
                'featured' => 'nullable|boolean',
                'meta_data' => 'nullable|json',
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
                'customizable' => 'required|boolean',
                '3d_files' => 'array',
                '3d_files.*.name_file' => 'required|string|max:100',
                '3d_files.*.glb_file' => 'required|file',
                '3d_files.*.data' => 'required|json',
                'components' => 'array',
                'components.*' => 'integer|exists:components,id',
                'products_related' => 'nullable|array',
                'products_related.*' => 'integer|exists:products,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Rutas base en la carpeta public
            $baseStoragePath = public_path('storage/products');

            // Asegurar que la carpeta base existe
            if (!file_exists($baseStoragePath)) {
                mkdir($baseStoragePath, 0755, true);
            }

            // Luego puedes crear subcarpetas sin error
            if (!file_exists("$baseStoragePath/images")) {
                mkdir("$baseStoragePath/images", 0755, true);
            }

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
            if (!file_exists("$baseStoragePath/3d/attributes"))
                mkdir("$baseStoragePath/3d/attributes", 0755, true);

            // Almacenar archivos principales en la carpeta 'public/storage/products'
            $mainImgThumbnailPath = null;
            if ($request->hasFile('main_img')) {
                $mainImgThumbnailPath = ImageHelper::saveReducedImage(
                    $request->file('main_img'),
                    'storage/products/images/',
                );
            }
            $mainImgPath = $request->hasFile('main_img') ? $request->file('main_img')->move("$baseStoragePath/images", uniqid() . '_' . $request->file('main_img')->getClientOriginalName()) : null;

            $subImgThumbnailPath = null;
            if ($request->hasFile('sub_img')) {
                $subImgThumbnailPath = ImageHelper::saveReducedImage(
                    $request->file('sub_img'),
                    "storage/products/images/",
                );
            }
            $subImgPath = $request->hasFile('sub_img') ? $request->file('sub_img')->move("$baseStoragePath/images", uniqid() . '_' . $request->file('sub_img')->getClientOriginalName()) : null;
            $mainVideoPath = $request->hasFile('main_video') ? $request->file('main_video')->move("$baseStoragePath/videos", uniqid() . '_' . $request->file('main_video')->getClientOriginalName()) : null;
            $fileDataSheetPath = $request->hasFile('file_data_sheet') ? $request->file('file_data_sheet')->move("$baseStoragePath/data_sheets", uniqid() . '_' . $request->file('file_data_sheet')->getClientOriginalName()) : null;

            $decodedMetaData = json_decode($request->meta_data, true);

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
                'thumbnail_main_img' => $mainImgThumbnailPath ? $mainImgThumbnailPath : null,
                'sub_img' => $subImgPath ? "storage/products/images/" . basename($subImgPath) : null,
                'thumbnail_sub_img' => $subImgThumbnailPath ? $subImgThumbnailPath : null,
                'customizable' => $request->customizable,
                '3d_file' => null,
                'main_video' => $mainVideoPath ? "storage/products/videos/" . basename($mainVideoPath) : null,
                'file_data_sheet' => $fileDataSheetPath ? "storage/products/data_sheets/" . basename($fileDataSheetPath) : null,
                'featured' => $request->featured,
                'meta_data' => $decodedMetaData,
            ]);


            // Guardar archivos en la galería
            if ($request->has('gallery')) {
                foreach ($request->gallery as $file) {
                    $uniqueName = uniqid() . '_' . $file->getClientOriginalName();
                    $fileThumbnailPath = ImageHelper::saveReducedImage(
                        $file,
                        "storage/products/gallery/",
                    );
                    $filePath = $file->move("$baseStoragePath/gallery", $uniqueName);
                    ProductGallery::create(
                        [
                            'id_product' => $product->id,
                            'file' => "storage/products/gallery/" . $uniqueName,
                            'thumbnail_file' => $fileThumbnailPath,
                        ]
                    );
                }
            }

            // Asociar categorías al producto
            if ($request->has('categories')) {
                foreach ($request->categories as $categoryId) {
                    ProductCategory::create(['id_product' => $product->id, 'id_categorie' => $categoryId]);
                }
            }

            // Guardar materiales asociados
            $productMaterialMap = [];
            if ($request->has('materials_values')) {
                foreach ($request->materials_values as $index => $material) {
                    $materialImgThumbnailPath = null;
                    if (isset($material['img']) && $request->hasFile("materials_values.$index.img")) {
                        $materialImgThumbnailPath = ImageHelper::saveReducedImage(
                            $request->file("materials_values.$index.img"),
                            "storage/products/materials/",
                        );
                    }
                    ;
                    $materialImgPath = isset($material['img']) && $request->hasFile("materials_values.$index.img")
                        ? $request->file("materials_values.$index.img")->move("$baseStoragePath/materials", uniqid() . '_' . $request->file("materials_values.$index.img")->getClientOriginalName())
                        : null;

                    $productMaterial = ProductMaterial::create([
                        'id_product' => $product->id,
                        'id_material' => $material['id_material_value'],
                        'img' => $materialImgPath ? "storage/products/materials/" . basename($materialImgPath) : null,
                        'thumbnail_img' => $materialImgThumbnailPath ? $materialImgThumbnailPath : null,
                    ]);

                    $productMaterialMap[$material['id_material_value']] = $productMaterial->id;
                }
            }

            // Guardar atributos asociados
            $productAttributeMap = [];
            if ($request->has('attributes_values')) {
                foreach ($request->attributes_values as $index => $attribute) {
                    $attributeImgThumbnailPath = null;
                    if (isset($attribute['img']) && $request->hasFile("attributes_values.$index.img")) {
                        $attributeImgThumbnailPath = ImageHelper::saveReducedImage(
                            $request->file("attributes_values.$index.img"),
                            "storage/products/attributes/",
                        );
                    }
                    ;
                    $attributeImgPath = isset($attribute['img']) && $request->hasFile("attributes_values.$index.img")
                        ? $request->file("attributes_values.$index.img")->move("$baseStoragePath/attributes", uniqid() . '_' . $request->file("attributes_values.$index.img")->getClientOriginalName())
                        : null;

                    $productAttribute = ProductAttribute::create([
                        'id_product' => $product->id,
                        'id_attribute_value' => $attribute['id_attribute_value'],
                        'img' => $attributeImgPath ? "storage/products/attributes/" . basename($attributeImgPath) : null,
                        'thumbnail_img' => $attributeImgThumbnailPath ? $attributeImgThumbnailPath : null,
                    ]);

                    $productAttributeMap[$attribute['id_attribute_value']] = $productAttribute->id;
                }
            }

            // verificar si es customizable, si es 1 o true, entonces guardar los modelos 3D


            if ($request->has('3d_files') && is_array($request->input('3d_files')) && $request->has('customizable') && $request->customizable) {
                foreach ($request->input('3d_files') as $index => $fileData) {
                    // Obtener archivo glb y estructura
                    $glbFile = $request->file("3d_files.$index.glb_file");
                    $structureJson = $fileData['data'];
                    $structure = json_decode($structureJson, true);

                    // Guardar archivo .glb
                    $glbPath = $glbFile->move("$baseStoragePath/3d", uniqid() . '_' . $glbFile->getClientOriginalName());
                    $glbPath = "storage/products/3d/" . basename($glbPath);

                    // Obtener todos los archivos cargados
                    $allFiles = $request->file('3d_files');

                    // Procesar atributos
                    if (isset($structure['attributes'])) {
                        foreach ($structure['attributes'] as &$attribute) {
                            foreach ($attribute['values'] as &$value) {
                                Log::info('Processing attribute value', ['attribute' => $attribute, 'value' => $value]);

                                $imageKey = "attribute_{$attribute['id']}_value_{$value['id']}";
                                $images = $allFiles[$index]['images'][$imageKey] ?? null;

                                Log::info('Image key:', ['key' => $imageKey]);
                                Log::info('Images:', ['images' => $images]);

                                $paths = [];

                                if (is_array($images)) {
                                    foreach ($images as $image) {
                                        $moved = $image->move("$baseStoragePath/3d/attributes", uniqid() . '_' . $image->getClientOriginalName());
                                        $paths[] = "storage/products/3d/attributes/" . basename($moved->getPathname());
                                    }
                                } elseif ($images instanceof \Illuminate\Http\UploadedFile) {
                                    $moved = $images->move("$baseStoragePath/3d/attributes", uniqid() . '_' . $images->getClientOriginalName());
                                    $paths[] = "storage/products/3d/attributes/" . basename($moved->getPathname());
                                }

                                Log::info('Paths:', ['paths' => $paths]);

                                $value['images'] = $paths;

                                Log::info('Updated value with images', ['value' => $value]);
                            }
                        }
                    }

                    // Procesar materiales
                    if (isset($structure['materials'])) {
                        foreach ($structure['materials'] as &$material) {
                            foreach ($material['values'] as &$value) {
                                $imageKey = "material_{$material['id']}_value_{$value['id']}";
                                $images = $allFiles[$index]['images'][$imageKey] ?? null;

                                Log::info('Material image key:', ['key' => $imageKey]);
                                Log::info('Material images:', ['images' => $images]);

                                $paths = [];

                                if (is_array($images)) {
                                    foreach ($images as $image) {
                                        $moved = $image->move("$baseStoragePath/3d/materials", uniqid() . '_' . $image->getClientOriginalName());
                                        $paths[] = "storage/products/3d/materials/" . basename($moved->getPathname());
                                    }
                                } elseif ($images instanceof \Illuminate\Http\UploadedFile) {
                                    $moved = $images->move("$baseStoragePath/3d/materials", uniqid() . '_' . $images->getClientOriginalName());
                                    $paths[] = "storage/products/3d/materials/" . basename($moved->getPathname());
                                }

                                $value['images'] = $paths;
                                Log::info('Updated material value with images', ['value' => $value]);
                            }
                        }
                    }

                    // Guardar modelo 3D
                    $model = Product3DModel::create([
                        'id_product' => $product->id,
                        'name' => $fileData['name_file'],
                        'glb_file_path' => $glbPath,
                        'data' => $structure
                    ]);

                    $models[] = $model;
                }
            }

            // Asociar componentes al producto
            if ($request->has('components')) {
                foreach ($request->components as $componentId) {
                    ProductComponent::create(['id_product' => $product->id, 'id_component' => $componentId]);
                }
            }

            // Asociar productos relacionados
            if ($request->has('products_related')) {
                foreach ($request->products_related as $relatedProductId) {
                    ProductsRelated::create([
                        'id_product' => $product->id,
                        'id_product_related' => $relatedProductId
                    ]);
                }
            }

            $product = Product::with([
                'categories',
                'materials.material',
                'attributes.attribute',
                'gallery',
                'components',
                'relatedProducts.related'
            ])->findOrFail($product->id);

            // Limpiar datos del pivot para cada relación
            $product->categories->each(function ($category) {
                unset($category->pivot);
            });
            $product->materials->each(function ($material) {
                $material->img_value = $material->pivot->img;
                $material->thumbnail_img_value = $material->pivot->thumbnail_img;
                unset($material->pivot);
            });
            $product->attributes->each(function ($attribute) {
                $attribute->img = $attribute->pivot->img;
                $attribute->thumbnail_img = $attribute->pivot->thumbnail_img;
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
                'sub_img' => 'nullable',
                'main_video' => 'nullable',
                'file_data_sheet' => 'nullable',
                'featured' => 'nullable|boolean',
                'meta_data' => 'nullable|json',
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
                'customizable' => 'required|boolean',
                '3d_files' => 'array',
                '3d_files.*.name_file' => 'required|string|max:100',
                '3d_files.*.glb_file' => 'nullable|file',
                '3d_files.*.data' => 'required|json',
                '3d_files.*.id' => 'sometimes|integer|exists:product_3d_models,id',
                'deleted_3d_files' => 'nullable|array',
                'components' => 'array',
                'components.*' => 'integer|exists:components,id',
                'products_related' => 'nullable|array',
                'products_related.*' => 'integer|exists:products,id',
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
            if (!file_exists("$baseStoragePath/3d/attributes"))
                mkdir("$baseStoragePath/3d/attributes", 0755, true);

            // Manejo de main_img
            if ($request->has('main_img')) {
                if (is_null($request->main_img)) {
                    if ($product->main_img && file_exists(public_path($product->main_img))) {
                        unlink(public_path($product->main_img));
                    }
                    if ($product->main_img && file_exists(public_path($product->thumbnail_main_img)) && $product->thumbnail_main_img != null) {
                        unlink(public_path($product->thumbnail_main_img));
                    }
                    $product->main_img = null;
                    $product->thumbnail_main_img = null;
                } elseif ($request->hasFile('main_img')) {
                    if ($product->main_img && file_exists(public_path($product->main_img))) {
                        unlink(public_path($product->main_img));
                    }
                    if ($product->main_img && file_exists(public_path($product->thumbnail_main_img)) && $product->thumbnail_main_img != null) {
                        unlink(public_path($product->thumbnail_main_img));
                    }

                    // Generar nombre aleatorio
                    $randomName = uniqid() . '_' . $request->file('main_img')->getClientOriginalName();
                    $mainImgThumbnailPath = ImageHelper::saveReducedImage(
                        $request->file('main_img'),
                        "storage/products/images/",
                    );
                    $destinationPath = public_path('storage/products/images/');
                    $request->file('main_img')->move($destinationPath, $randomName);
                    $product->main_img = 'storage/products/images/' . $randomName;
                    $product->thumbnail_main_img = $mainImgThumbnailPath;
                }
            }

            if ($request->has('sub_img')) {
                if (is_null($request->sub_img)) {
                    if ($product->sub_img && file_exists(public_path($product->sub_img))) {
                        unlink(public_path($product->sub_img));
                    }
                    if ($product->sub_img && file_exists(public_path($product->thumbnail_sub_img)) && $product->thumbnail_sub_img != null) {
                        unlink(public_path($product->thumbnail_sub_img));
                    }
                    $product->sub_img = null;
                    $product->thumbnail_sub_img = null;
                } elseif ($request->hasFile('sub_img')) {
                    if ($product->sub_img && file_exists(public_path($product->sub_img))) {
                        unlink(public_path($product->sub_img));
                    }
                    if ($product->sub_img && file_exists(public_path($product->thumbnail_sub_img)) && $product->thumbnail_sub_img != null) {
                        unlink(public_path($product->thumbnail_sub_img));
                    }

                    // Generar nombre aleatorio
                    $randomName = uniqid() . '_' . $request->file('sub_img')->getClientOriginalName();
                    $subImgThumbnailPath = ImageHelper::saveReducedImage(
                        $request->file('sub_img'),
                        "storage/products/images/",
                    );
                    $destinationPath = public_path('storage/products/images/');
                    $request->file('sub_img')->move($destinationPath, $randomName);
                    $product->sub_img = 'storage/products/images/' . $randomName;
                    $product->thumbnail_main_img = $subImgThumbnailPath;
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

            $newMetaData = is_string($request->meta_data) ? json_decode($request->meta_data, true) : $request->meta_data;

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
                'meta_data' => $newMetaData,
                'customizable' => $request->customizable,
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

                        if (file_exists(public_path($gallery->thumbnail_file)) && $gallery->thumbnail_file != null) {
                            unlink(public_path($gallery->thumbnail_file));
                        }

                        // Guardamos la nueva imagen
                        $randomName = uniqid() . '_' . $galleryItem['file']->getClientOriginalName();
                        $fileThumbnailPath = ImageHelper::saveReducedImage(
                            $galleryItem['file'],
                            "storage/products/gallery/",
                        );
                        $destinationPath = public_path('storage/products/gallery/');
                        $galleryItem['file']->move($destinationPath, $randomName);
                        $gallery->update(
                            [
                                'file' => 'storage/products/gallery/' . $randomName,
                                'thumbnail_file' => $fileThumbnailPath
                            ]
                        );
                    }

                    // Si el 'id' está vacío, es una nueva imagen, se sube el archivo
                    if (empty($galleryItem['id']) && isset($galleryItem['file'])) {
                        $randomName = uniqid() . '_' . $galleryItem['file']->getClientOriginalName();
                        $newFileThumbnailPath = ImageHelper::saveReducedImage(
                            $galleryItem['file'],
                            "storage/products/gallery/",
                        );
                        $destinationPath = public_path('storage/products/gallery/');
                        $galleryItem['file']->move($destinationPath, $randomName);
                        $product->gallery()->create(
                            [
                                'file' => 'storage/products/gallery/' . $randomName,
                                'thumbnail_file' => $newFileThumbnailPath
                            ]
                        );
                    }

                    // Si el 'id' está presente y el 'file' es null, eliminamos la imagen correspondiente
                    if (isset($galleryItem['id']) && $galleryItem['file'] === null) {
                        $gallery = ProductGallery::findOrFail($galleryItem['id']);

                        // Eliminar la imagen
                        if (file_exists(public_path($gallery->file))) {
                            unlink(public_path($gallery->file));
                        }
                        if (file_exists(public_path($gallery->thumbnail_file)) && $gallery->thumbnail_file !== null) {
                            unlink(public_path($gallery->thumbnail_file));
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
                    if (file_exists(public_path($gallery->thumbnail_file)) && $gallery->thumbnail_file !== null) {
                        unlink(public_path($gallery->thumbnail_file));
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

                    if ($material->img && file_exists(public_path($material->thumbnail_img)) && $material->thumbnail_img !== null) {
                        unlink(public_path($material->thumbnail_img));
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

                        if ($existingMaterial->img && file_exists(public_path($existingMaterial->thumbnail_img)) && $existingMaterial->thumbnail_img !== null) {
                            unlink(public_path($existingMaterial->thumbnail_img));
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
                    $materialThumbnailImgPath = $existingMaterial ? $existingMaterial->thumbnail_img : null;

                    // Verificar si el material ya existe
                    if ($existingMaterial) {
                        if ($request->hasFile("materials_values.$index.img")) {
                            // Si se envía una nueva imagen, eliminar la anterior si es diferente
                            if ($existingMaterial->img && file_exists(public_path($existingMaterial->img))) {
                                unlink(public_path($existingMaterial->img));
                            }

                            if ($existingMaterial->img && file_exists(public_path($existingMaterial->thumbnail_img)) && $existingMaterial->thumbnail_img !== null) {
                                unlink(public_path($existingMaterial->thumbnail_img));
                            }

                            $materialThumbnailPath = ImageHelper::saveReducedImage(
                                $request->file("materials_values.$index.img"),
                                "storage/products/materials/",
                            );
                            // Guardar la nueva imagen
                            $randomName = uniqid() . '_' . $request->file("materials_values.$index.img")->getClientOriginalName();
                            $destinationPath = public_path('storage/products/materials/');
                            $request->file("materials_values.$index.img")->move($destinationPath, $randomName);
                            $materialImgPath = 'storage/products/materials/' . $randomName;
                            $materialThumbnailImgPath = $materialThumbnailPath;

                        } elseif (!isset($material['img'])) {
                            // Si 'img' no está definido, eliminar la imagen existente
                            if ($existingMaterial->img && file_exists(public_path($existingMaterial->img))) {
                                unlink(public_path($existingMaterial->img));
                            }

                            if ($existingMaterial->img && file_exists(public_path($existingMaterial->thumbnail_img)) && $existingMaterial->thumbnail_img !== null) {
                                unlink(public_path($existingMaterial->thumbnail_img));
                            }
                            $materialImgPath = null; // Se elimina la referencia a la imagen
                            $materialThumbnailImgPath = null;
                        }
                    } else {
                        // Crear material nuevo con imagen solo si se ha enviado una
                        if ($request->hasFile("materials_values.$index.img")) {
                            $newMaterialThumbnailPath = ImageHelper::saveReducedImage(
                                $request->file("materials_values.$index.img"),
                                "storage/products/materials/",
                            );
                            $randomName = uniqid() . '_' . $request->file("materials_values.$index.img")->getClientOriginalName();
                            $destinationPath = public_path('storage/products/materials/');
                            $request->file("materials_values.$index.img")->move($destinationPath, $randomName);
                            $materialImgPath = 'storage/products/materials/' . $randomName;
                            $materialThumbnailImgPath = $newMaterialThumbnailPath;
                        }
                    }

                    // Crear o actualizar la relación con la imagen actualizada o eliminada
                    ProductMaterial::updateOrCreate(
                        ['id_product' => $product->id, 'id_material' => $material['id_material_value']],
                        ['img' => $materialImgPath, 'thumbnail_img' => $materialThumbnailImgPath]
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

                        if ($attributeInstance->img && file_exists(public_path($attributeInstance->thumbnail_img)) && $attributeInstance->thumbnail_img != null) {
                            unlink(public_path($attributeInstance->thumbnail_img));
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
                    $attributeThumbnailImgPath = null;

                    if (isset($attribute['img']) && is_string($attribute['img'])) {
                        // Conserva la imagen actual si es un string
                        $attributeImgPath = $attributeInstance ? $attributeInstance->img : null;
                    } elseif (isset($attribute['img']) && $attribute['img'] === null) {
                        // Elimina la imagen actual si se pasa null
                        if ($attributeInstance && $attributeInstance->img && file_exists(public_path($attributeInstance->img))) {
                            unlink(public_path($attributeInstance->img));
                        }

                        if ($attributeInstance && $attributeInstance->img && file_exists(public_path($attributeInstance->thumbnail_img)) && $attributeInstance->thumbnail_img != null) {
                            unlink(public_path($attributeInstance->thumbnail_img));
                        }
                        $attributeImgPath = null;
                        $attributeThumbnailImgPath = null;
                    } elseif ($request->hasFile("attributes_values.$index.img")) {
                        // Actualiza con la nueva imagen si hay un archivo
                        if ($attributeInstance && $attributeInstance->img && file_exists(public_path($attributeInstance->img))) {
                            unlink(public_path($attributeInstance->img));
                        }
                        if ($attributeInstance && $attributeInstance->img && file_exists(public_path($attributeInstance->thumbnail_img)) && $attributeInstance->thumbnail_img != null) {
                            unlink(public_path($attributeInstance->thumbnail_img));
                        }
                        $attributeThumbnailPath = ImageHelper::saveReducedImage(
                            $request->file("attributes_values.$index.img"),
                            "storage/products/attributes/",
                        );
                        $randomName = uniqid() . '_' . $request->file("attributes_values.$index.img")->getClientOriginalName();
                        $destinationPath = public_path('storage/products/attributes/');
                        $request->file("attributes_values.$index.img")->move($destinationPath, $randomName);
                        $attributeImgPath = 'storage/products/attributes/' . $randomName;
                        $attributeThumbnailImgPath = $attributeThumbnailPath;
                    }

                    // Actualiza o crea la relación con la nueva imagen
                    ProductAttribute::updateOrCreate(
                        ['id_product' => $product->id, 'id_attribute_value' => $attribute['id_attribute_value']],
                        [
                            'img' => $attributeImgPath,
                            'thumbnail_img' => $attributeThumbnailImgPath
                        ]
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

                    if ($attributeInstance->img && file_exists(public_path($attributeInstance->thumbnail_img)) && $attributeInstance->thumbnail_img != null) {
                        unlink(public_path($attributeInstance->thumbnail_img));
                    }

                    // Eliminar el atributo
                    $attributeInstance->delete();
                }
            }

            if ($request->has('3d_files') && is_array($request->input('3d_files')) && $request->has('customizable') && $request->customizable) {
                foreach ($request->input('3d_files') as $index => $fileData) {
                    $glbFile = $request->file("3d_files.$index.glb_file");
                    $structureJson = $fileData['data'];
                    $structure = json_decode($structureJson, true);
                    $modelId = $fileData['id'] ?? null;

                    // Buscar modelo existente o crear uno nuevo
                    $model = $modelId ? Product3DModel::find($modelId) : new Product3DModel();

                    // Actualizar nombre del archivo
                    $model->name = $fileData['name_file'];
                    $model->id_product = $product->id;

                    // Si hay archivo glb nuevo, lo actualizamos
                    if ($glbFile) {
                        $glbPath = $glbFile->move("$baseStoragePath/3d", uniqid() . '_' . $glbFile->getClientOriginalName());
                        $model->glb_file_path = "storage/products/3d/" . basename($glbPath);
                    }

                    $allFiles = $request->file('3d_files');

                    // Procesar imágenes de atributos
                    if (isset($structure['attributes'])) {
                        foreach ($structure['attributes'] as &$attribute) {
                            foreach ($attribute['values'] as &$value) {
                                $imageKey = "attribute_{$attribute['id']}_value_{$value['id']}";
                                $images = $allFiles[$index]['images'][$imageKey] ?? null;

                                $paths = [];

                                if (is_array($images)) {
                                    foreach ($images as $image) {
                                        $moved = $image->move("$baseStoragePath/3d/attributes", uniqid() . '_' . $image->getClientOriginalName());
                                        $paths[] = "storage/products/3d/attributes/" . basename($moved->getPathname());
                                    }
                                } elseif ($images instanceof \Illuminate\Http\UploadedFile) {
                                    $moved = $images->move("$baseStoragePath/3d/attributes", uniqid() . '_' . $images->getClientOriginalName());
                                    $paths[] = "storage/products/3d/attributes/" . basename($moved->getPathname());
                                }

                                // Fusionar imágenes nuevas con las existentes si hay
                                if (!empty($paths)) {
                                    $existingImages = $value['images'] ?? [];
                                    $value['images'] = array_merge($existingImages, $paths);
                                }
                            }
                        }
                    }

                    // Procesar imágenes de materiales
                    if (isset($structure['materials'])) {
                        foreach ($structure['materials'] as &$material) {
                            foreach ($material['values'] as &$value) {
                                $imageKey = "material_{$material['id']}_value_{$value['id']}";
                                $images = $allFiles[$index]['images'][$imageKey] ?? null;

                                $paths = [];

                                if (is_array($images)) {
                                    foreach ($images as $image) {
                                        $moved = $image->move("$baseStoragePath/3d/materials", uniqid() . '_' . $image->getClientOriginalName());
                                        $paths[] = "storage/products/3d/materials/" . basename($moved->getPathname());
                                    }
                                } elseif ($images instanceof \Illuminate\Http\UploadedFile) {
                                    $moved = $images->move("$baseStoragePath/3d/materials", uniqid() . '_' . $image->getClientOriginalName());
                                    $paths[] = "storage/products/3d/materials/" . basename($moved->getPathname());
                                }

                                // Fusionar imágenes nuevas con las existentes si hay
                                if (!empty($paths)) {
                                    $existingImages = $value['images'] ?? [];
                                    $value['images'] = array_merge($existingImages, $paths);
                                }
                            }
                        }
                    }


                    // Guardar estructura actualizada
                    $model->data = $structure;
                    $model->save();

                    $models[] = $model;
                }
            }

            if ($request->has('deleted_3d_files') && is_array($request->deleted_3d_files)) {
                foreach ($request->deleted_3d_files as $idToDelete) {
                    $model = Product3DModel::find($idToDelete);

                    if ($model) {
                        // Eliminar archivo .glb
                        if ($model->glb_file_path) {
                            $glbPath = public_path($model->glb_file_path);
                            if (file_exists($glbPath)) {
                                unlink($glbPath);
                            }
                        }

                        $structure = $model->data;

                        // Eliminar imágenes de atributos
                        if (isset($structure['attributes'])) {
                            foreach ($structure['attributes'] as $attribute) {
                                foreach ($attribute['values'] as $value) {
                                    if (isset($value['images']) && is_array($value['images'])) {
                                        foreach ($value['images'] as $imagePath) {
                                            $fullPath = public_path($imagePath);
                                            if (file_exists($fullPath)) {
                                                unlink($fullPath);
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Eliminar imágenes de materiales
                        if (isset($structure['materials'])) {
                            foreach ($structure['materials'] as $material) {
                                foreach ($material['values'] as $value) {
                                    if (isset($value['images']) && is_array($value['images'])) {
                                        foreach ($value['images'] as $imagePath) {
                                            $fullPath = public_path($imagePath);
                                            if (file_exists($fullPath)) {
                                                unlink($fullPath);
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Eliminar el modelo de la base de datos
                        $model->delete();
                    }
                }
            }

            // Primero eliminamos los existentes
            ProductsRelated::where('id_product', $product->id)->delete();

            // Luego agregamos los nuevos, igual que en el store
            if ($request->has('products_related')) {
                foreach ($request->products_related as $relatedProductId) {
                    ProductsRelated::create([
                        'id_product' => $product->id,
                        'id_product_related' => $relatedProductId
                    ]);
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
                $material->thumbnail_img_value = $material->pivot->thumbnail_img;
                unset($material->pivot);
            });

            $product->attributes->each(function ($attribute) {
                $attribute->img = $attribute->pivot->img;
                $attribute->thumbnail_img = $attribute->pivot->thumbnail_img;
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
                if ($product->thumbnail_main_img && file_exists(public_path($product->thumbnail_main_img)) && $product->thumbnail_main_img != null) {
                    unlink(public_path($product->thumbnail_main_img));
                }
            } catch (Exception $e) {
                Log::error("Error al eliminar la imagen principal: " . $e->getMessage());
            }

            try {
                if ($product->sub_img && file_exists(public_path($product->sub_img))) {
                    unlink(public_path($product->sub_img));
                }
                if ($product->thumbnail_sub_img && file_exists(public_path($product->thumbnail_sub_img)) && $product->thumbnail_sub_img != null) {
                    unlink(public_path($product->thumbnail_sub_img));
                }
            } catch (Exception $e) {
                Log::error("Error al eliminar la imagen secundaria: " . $e->getMessage());
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
                    if (file_exists(public_path($galleryItem->thumbnail_file)) && $galleryItem->thumbnail_file != null) {
                        unlink(public_path($galleryItem->thumbnail_file));
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
                    if ($attribute->pivot->thumbnail_img && file_exists(public_path($attribute->pivot->thumbnail_img)) && $attribute->pivot->thumbnail_img != null) {
                        unlink(public_path($attribute->pivot->thumbnail_img));
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
                    if ($material->pivot->thumbnail_img && file_exists(public_path($material->pivot->thumbnail_img)) && $material->pivot->thumbnail_img != null) {
                        unlink(public_path($material->pivot->thumbnail_img));
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

    public function generarFichaProducto(Request $request)
    {
        try {
            // Validar los datos del form
            $validated = $request->validate([
                'product_title' => 'required|string|max:255',
                'data' => 'required|string', // viene como string JSON
                'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            // Parsear el JSON del campo "data"
            $sections = json_decode($validated['data'], true);
            if (!is_array($sections)) {
                return response()->json(['error' => 'El campo data no contiene un JSON válido.'], 400);
            }

            // Si viene imagen, la guardamos en public/pdf-ficha/
            $imagePath = null;
            $basePublicPath = public_path('pdf-ficha');
            if ($request->hasFile('image')) {
                if (!file_exists($basePublicPath)) {
                    mkdir($basePublicPath, 0777, true);
                }

                $filename = time() . '_' . $request->file('image')->getClientOriginalName();
                $request->file('image')->move($basePublicPath, $filename);
                $imagePath = $basePublicPath . '/' . $filename;
            }

            // Datos para la vista Blade
            $data = [
                'product_title' => $validated['product_title'],
                'model' => $sections,
                'image' => $imagePath,
            ];

            // Generar PDF
            $pdf = Pdf::loadView('pdf.ficha_producto', compact('data'))
                ->setPaper('a4', 'portrait');

            $pdfOutput = $pdf->output();

            // Eliminar la imagen temporal después de generar el PDF
            if ($imagePath && file_exists($imagePath)) {
                unlink($imagePath);
            }

            // Retornar el PDF generado para descarga
            return response($pdfOutput)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $data['product_title'] . '.pdf"');
        } catch (\Throwable $e) {
            // Log para ver el error completo en storage/logs/laravel.log
            Log::error('Error generando ficha de producto: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // Mostrar error al cliente (sin romper JSON)
            return response()->json([
                'error' => 'Ocurrió un error al generar el PDF.',
                'exception' => $e->getMessage(), // 🔍 Mostrar mensaje real
                // 'trace' => $e->getTrace() // <-- podés descomentar para debug
            ], 500);
        }
    }
}
