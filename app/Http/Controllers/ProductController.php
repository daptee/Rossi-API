<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
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
    public function indexAdmin()
    {
        try {
            $products = Product::select('id', 'name', 'main_img', 'status', 'featured')
            ->withCount(['categories', 'materials', 'attributes', 'gallery'])
            ->get();

            // Mapea cada producto para devolver solo los conteos y la información básica
            $products = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'main_img' => $product->main_img,
                    'status' => $product->status,
                    'featured' => $product->featured,
                    'categories_count' => $product->categories_count,
                    'materials_count' => $product->materials_count,
                    'attributes_count' => $product->attributes_count,
                    'gallery_count' => $product->gallery_count,
                ];
            });

            return ApiResponse::create('Succeeded', 200, $products);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los productos', 500, ['error' => $e->getMessage()]);
        }
    }

    // GET ALL (para web)
    public function indexWeb()
    {
        try {
            $products = Product::where('status', 2) // Assuming 2 is the ID for "Activo"
                ->select('id', 'name', 'slug', 'main_img', 'featured')
                ->get();

            return ApiResponse::create('Succeeded', 200, $products);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los productos', 500, ['error' => $e->getMessage()]);
        }
    }

    // GET PRODUCT
    public function indexProduct($id)
    {
        try {
            $products = Product::with(['categories', 'materials', 'attributes', 'gallery'])
                ->select('id', 'name', 'description', 'main_img', 'main_video', 'file_data_sheet', 'status', 'featured')
                ->findOrFail($id);

            return ApiResponse::create('Succeeded', 200, $products);
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
                'materials_values.*' => 'integer|exists:material_values,id',
                'attributes_values' => 'array',
                'attributes_values.*.id_attribute_value' => 'required|integer|exists:attribute_values,id',
                'attributes_values.*.img' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Guardar archivos de imágenes y videos
            $mainImgPath = $request->hasFile('main_img') ? $request->file('main_img')->store('products/images') : null;
            $mainVideoPath = $request->hasFile('main_video') ? $request->file('main_video')->store('products/videos') : null;
            $fileDataSheetPath = $request->hasFile('file_data_sheet') ? $request->file('file_data_sheet')->store('products/data_sheets') : null;

            $product = Product::create([
                'name' => $request->name,
                'sku' => $request->sku,
                'slug' => $request->slug,
                'description' => $request->description,
                'status' => $request->status,
                'main_img' => $mainImgPath,
                'main_video' => $mainVideoPath,
                'file_data_sheet' => $fileDataSheetPath,
                'featured' => $request->featured,
            ]);

            // Guardar imágenes en la galería
            if ($request->has('gallery')) {
                foreach ($request->gallery as $file) {
                    $filePath = $file->store('products/gallery');
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
                foreach ($request->materials_values as $materialId) {
                    ProductMaterial::create([
                        'id_product' => $product->id,
                        'id_material' => $materialId,
                    ]);
                }
            }

            // Guardar atributos asociados al producto
            if ($request->has('attributes_values')) {
                foreach ($request->attributes_values as $attribute) {
                    $attributeImgPath = $request->hasFile('attributes_values.*.img') ? $attribute['img']->store('products/attributes') : null;
                    ProductAttribute::create([
                        'id_product' => $product->id,
                        'id_attribute_value' => $attribute['id_attribute_value'],
                        'img' => $attributeImgPath,
                    ]);
                }
            }

            return ApiResponse::create('Producto creado correctamente', 200, $product);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear producto', 500, ['error' => $e->getMessage()]);
        }
    }


    // PUT - Editar un producto
    public function update(Request $request, $id)
    {
        try {
            Log::info('Request Data:', $request->all());
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:100|unique:products,sku,' . $id,
                'slug' => 'required|string|max:255|unique:products,slug,' . $id,
                'description' => 'nullable|string',
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
                'materials_values.*' => 'integer|exists:material_values,id',
                'attributes_values' => 'array',
                'attributes_values.*.id_attribute_value' => 'required|integer|exists:attribute_values,id',
                'attributes_values.*.img' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $product = Product::with('attributes')->findOrFail($id);

            // Actualizar y eliminar imágenes si es necesario
            if ($request->hasFile('main_img')) {
                Storage::delete($product->main_img);
                $product->main_img = $request->file('main_img')->store('products/images');
            }

            if ($request->hasFile('main_video')) {
                Storage::delete($product->main_video);
                $product->main_video = $request->file('main_video')->store('products/videos');
            }

            if ($request->hasFile('file_data_sheet')) {
                Storage::delete($product->file_data_sheet);
                $product->file_data_sheet = $request->file('file_data_sheet')->store('products/data_sheets');
            }

            $product->update($request->only('name', 'sku', 'slug', 'description', 'status', 'featured'));

            // Actualizar las categorías asociadas al producto
            if ($request->has('categories')) {
                $product->categories()->sync($request->categories);
            }

            // Actualizar la galería
            if ($request->has('gallery')) {
                foreach ($product->gallery as $galleryItem) {
                    Storage::delete($galleryItem->file);
                    $galleryItem->delete();
                }

                foreach ($request->gallery as $file) {
                    $filePath = $file->store('products/gallery');
                    ProductGallery::create(['id_product' => $product->id, 'file' => $filePath]);
                }
            }

            // Actualizar los materiales asociados al producto
            if ($request->has('materials_values')) {
                $product->materials()->sync($request->materials_values);
            }

            // Actualizar los atributos asociados al producto
            if ($request->has('attributes_values')) {
                // Eliminar todos los atributos existentes del producto
                foreach ($product->attributes as $attribute) {
                    if ($attribute->pivot->img) {
                        Storage::delete($attribute->pivot->img);
                    }
                }
                $product->attributes()->detach();
    
                // Crear nuevos atributos
                foreach ($request->attributes_values as $index => $attribute) {
                    $attributeImgPath = $request->hasFile("attributes_values.$index.img")
                        ? $request->file("attributes_values.$index.img")->store('products/attributes')
                        : null;
    
                    $product->attributes()->attach($attribute['id_attribute_value'], ['img' => $attributeImgPath]);
                }
            }
    
            return ApiResponse::create('Product actualizado successfully', 200, $product);
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
