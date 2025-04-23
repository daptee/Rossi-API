<?php

namespace App\Http\Controllers;

use App\Helpers\ImageHelper;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;
use App\Models\Category;
use Intervention\Image\ImageManager;
use Log;

class CategoriesController extends Controller
{
    // GET ALL
    public function index(Request $request)
    {
        try {
            $search = $request->query('search'); // Parámetro de búsqueda
            $perPage = $request->query('per_page'); // Número de elementos por página o null si no está definido

            // Consulta inicial con relaciones necesarias
            $query = Category::with(['categories', 'status'])
                ->withCount('products')
                ->whereNull('id_category');

            if ($search !== null) {
                // Buscar en categorías principales o subcategorías
                $query->where(function ($q) use ($search) {
                    $q->where('category', 'like', '%' . $search . '%')
                        ->orWhereHas('categories', function ($subQuery) use ($search) {
                            $subQuery->where('category', 'like', '%' . $search . '%');
                        });
                });
            }

            // Obtener categorías con o sin paginación
            if ($perPage !== null) {
                $categories = $query->paginate((int) $perPage);
                $metaData = [
                    'page' => $categories->currentPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                    'last_page' => $categories->lastPage(),
                ];
                $collection = $categories->getCollection();
            } else {
                $collection = $query->get();
                $metaData = [
                    'total' => $collection->count(),
                    'per_page' => 'Todos',
                    'page' => 1,
                    'last_page' => 1,
                ];
            }

            // Procesar cada categoría y sus subcategorías
            $collection->transform(function ($category) {
                $category = $this->removeEmptyCategories($category);
                $category = $this->attachProductInfoToGrid($category);

                // Asigna el conteo de productos a cada subcategoría
                $category->categories = $category->categories->map(function ($subCategory) {
                    $subCategory->product_count = $subCategory->products->count();
                    return $subCategory;
                });

                return $category;
            });

            // Respuesta con ApiResponse
            return ApiResponse::create('Categorías obtenidas correctamente', 200, $collection, $metaData);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer todas las categorías', 500, [], ['error' => $e->getMessage()]);
        }
    }

    // POST
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'required|string|max:255',
                'img' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'sub_img' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'video' => 'nullable|file|mimes:mp4,mov,avi|max:10240',
                'icon' => 'nullable|file|mimes:svg,png|max:2048',
                'color' => 'nullable|string',
                'status' => 'required|integer|exists:status,id',
                'grid' => 'nullable|json',
                'meta_data' => 'nullable|json',
                'id_category' => 'nullable|exists:categories,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $imgPath = null;
            $subImgPath = null;
            $videoPath = null;
            $iconPath = null;

            $baseStoragePath = public_path('storage/categories/');
            $this->createDirectories($baseStoragePath);

            if ($request->hasFile('img')) {
                $fileName = time() . '_' . $request->file('img')->getClientOriginalName();
                $imgPathThumbnail = ImageHelper::saveReducedImage(
                    $request->file('img'),
                    'storage/categories/images/'
                );
                $request->file('img')->move($baseStoragePath . 'images/', $fileName);
                $imgPath = 'storage/categories/images/' . $fileName;
            }

            if ($request->hasFile('sub_img')) {
                $fileName = time() . '_' . $request->file('sub_img')->getClientOriginalName();
                $subImgPathThumbnail = ImageHelper::saveReducedImage(
                    $request->file('sub_img'),
                    'storage/categories/images/'
                );
                $request->file('sub_img')->move($baseStoragePath . 'images/', $fileName);
                $subImgPath = 'storage/categories/images/' . $fileName;
            }

            if ($request->hasFile('video')) {
                $fileName = time() . '_' . $request->file('video')->getClientOriginalName();
                $request->file('video')->move($baseStoragePath . 'videos/', $fileName);
                $videoPath = 'storage/categories/videos/' . $fileName;
            }

            if ($request->hasFile('icon')) {
                $fileName = time() . '_' . $request->file('icon')->getClientOriginalName();
                $request->file('icon')->move($baseStoragePath . 'icons/', $fileName);
                $iconPath = 'storage/categories/icons/' . $fileName;
            }

            $decodedGrid = !empty($request->grid) ? json_decode($request->grid, true) : null;
            $decodedMetaData = !empty($request->meta_data) ? json_decode($request->meta_data, true) : null;

            // Procesar archivos adicionales y asignarlos a los elementos de grid
            if (is_array($decodedGrid)) {
                for ($i = 1; $i <= 3; $i++) {
                    $fileKey = 'file_' . $i;
                    if ($request->hasFile($fileKey)) {
                        $fileName = time() . '_' . $request->file($fileKey)->getClientOriginalName();
                        
                        $thumbnailFile = null;
                        $thumbnailFile = ImageHelper::saveReducedImage(
                            $request->file($fileKey),
                            'storage/categories/grid/'
                        );
                        $request->file($fileKey)->move($baseStoragePath . 'grid/', $fileName);
                        $fileUrl = 'storage/categories/grid/' . $fileName;

                        // Asignar la URL del archivo al elemento correspondiente en grid
                        $gridItemId = (string) $i;
                        foreach ($decodedGrid as &$gridItem) {
                            if ($gridItem['id'] === $gridItemId) {
                                $gridItem['props']['file']['url'] = $fileUrl;
                                $gridItem['props']['file']['thumbnail_url'] = $thumbnailFile;
                                break;
                            }
                        }
                    }
                }
            }

            // Guardar la categoría en la base de datos
            $category = new Category([
                'id_category' => $request->input('id_category'),
                'category' => $request->input('category'),
                'img' => $imgPath,
                'sub_img' => $subImgPath,
                'thumbnail_img' => $imgPathThumbnail,
                'thumbnail_sub_img' => $subImgPathThumbnail,
                'video' => $videoPath,
                'icon' => $iconPath,
                'color' => $request->input('color'),
                'status' => $request->input('status'),
                'grid' => $decodedGrid,
                'meta_data' => $decodedMetaData,
            ]);

            $category->save();
            $category->load('status');

            if ($category->grid != null) {

                $category->grid = array_map(function ($item) {
                    if ($item['props']['type'] === 'Producto' && isset($item['props']['id'])) {
                        $product = Product::find($item['props']['id']);
                        if ($product) {
                            $item['props']['product_info'] = [
                                'id' => $product->id,
                                'name' => $product->name,
                                'sku' => $product->sku,
                                'description' => $product->description,
                                'main_img' => $product->main_img,
                                'thumbnail_main_img' => $product->thumbnail_main_img,
                                'featured' => $product->featured
                                // Agrega otros campos que deseas mostrar
                            ];
                        }
                    }
                    return $item;
                }, $category->grid);
            }

            return ApiResponse::create('Categoría creada correctamente', 200, $category);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear una categoría', 500, ['error' => $e->getMessage()]);
        }
    }

    // PUT
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'required|string|max:255',
                'img' => 'nullable',
                'sub_img' => 'nullable',
                'video' => 'nullable',
                'icon' => 'nullable',
                'color' => 'nullable|string',
                'status' => 'required|integer|exists:status,id',
                'grid' => 'nullable|json',
                'meta_data' => 'nullable|json',
                'id_category' => 'nullable|exists:categories,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $category = Category::findOrFail($id);

            if ($request->hasFile('img')) {
                // Asegurarse de que la imagen existe ahora
                $thumbnailImgPath = ImageHelper::saveReducedImage(
                    $request->file('img'),
                    'storage/categories/images/'
                );
                // Eliminar el thumbnail anterior si corresponde
                if ($category->thumbnail_img && file_exists(public_path($category->thumbnail_img))) {
                    Log::info('tummmmmmm'. $category->thumbnail_img);
                    unlink(public_path($category->thumbnail_img));
                }
            } else {
                $thumbnailImgPath = $category->thumbnail_img;
            }
            $imgPath = $this->processField($request, 'img', $category->img, public_path('storage/categories/images/'));

            if ($request->hasFile('sub_img')) {
                // Asegurarse de que la imagen existe ahora
                $thumbnailSubImgPath = ImageHelper::saveReducedImage(
                    $request->file('sub_img'),
                    'storage/categories/images/'
                );
                // Eliminar el thumbnail anterior si corresponde
                if ($category->thumbnail_sub_img && file_exists(public_path($category->thumbnail_sub_img))) {
                    unlink(public_path($category->thumbnail_sub_img));
                }
            } else {
                $thumbnailSubImgPath = $category->thumbnail_sub_img;
            }
            $subImgPath = $this->processField($request, 'sub_img', $category->sub_img, public_path('storage/categories/images/'));
            
            $videoPath = $this->processField($request, 'video', $category->video, public_path('storage/categories/videos/'));
            $iconPath = $this->processField($request, 'icon', $category->icon, public_path('storage/categories/icons/'));

            // Obtener la `grid` guardada y la `grid` nueva
            $existingGridData = is_string($category->grid) ? json_decode($category->grid, true) : $category->grid;
            $newGridData = is_string($request->grid) ? json_decode($request->grid, true) : $request->grid;

            $newMetaData = is_string($request->meta_data) ? json_decode($request->meta_data, true) : $request->meta_data;

            // Procesar archivos en la nueva `grid`
            if ($newGridData != null) {
                foreach ($newGridData as $key => &$newGridItem) {
                    $fileField = 'file_' . ($key + 1);

                    // Obtener el archivo actual de la `grid` guardada si existe
                    $existingGridItem = $existingGridData[$key] ?? null;
                    $existingFileUrl = $existingGridItem['props']['file']['url'] ?? null;

                    if ($request->hasFile($fileField)) {
                        // Si hay un archivo nuevo, elimina el archivo existente si es diferente
                        /*  if ($existingFileUrl && $existingFileUrl !== $newGridItem['props']['file']['url']) {
                             $this->deleteFile($existingFileUrl);
                         } */

                        // Guardar el nuevo archivo
                        $fileName = time() . '_' . $request->file($fileField)->getClientOriginalName();
                        $newThumbnailFile = null;
                        if ($newGridItem['props']['type'] == "Imagen") {
                            $newThumbnailFile = ImageHelper::saveReducedImage(
                                $request->file($fileField),
                                'storage/categories/grid/'
                            );
                        }
                        $request->file($fileField)->move(public_path('storage/categories/grid/'), $fileName);

                        // Actualizar la URL del archivo en la nueva `grid`
                        $newGridItem['props']['file']['url'] = 'storage/categories/grid/' . $fileName;
                        $newGridItem['props']['file']['thumbnail_url'] = $newThumbnailFile;
                    } /* elseif ($existingFileUrl && !isset($newGridItem['props']['file']['url'])) {
                     // Si no se envía un archivo nuevo pero había uno antiguo, eliminar el archivo antiguo
                     $this->deleteFile($existingFileUrl);
                     $newGridItem['props']['file']['url'] = null;
                 } */
                }
            }

            // Actualizar la categoría con la nueva información
            $category->update([
                'id_category' => $request->input('id_category'),
                'category' => $request->input('category'),
                'img' => $imgPath,
                'thumbnail_img' => $thumbnailImgPath,
                'sub_img' => $subImgPath,
                'thumbnail_sub_img' => $thumbnailSubImgPath,
                'video' => $videoPath,
                'icon' => $iconPath,
                'color' => $request->input('color'),
                'status' => $request->input('status'),
                'grid' => $newGridData,
                'meta_data' => $newMetaData,
            ]);

            $category->load('status');

            return ApiResponse::create('Categoría actualizada correctamente', 200, $category);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar la categoría', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Crear directorios si no existen.
     */
    private function createDirectories($basePath)
    {
        if (!file_exists($basePath . 'images')) {
            mkdir($basePath . 'images', 0777, true);
        }

        if (!file_exists($basePath . 'videos')) {
            mkdir($basePath . 'videos', 0777, true);
        }

        if (!file_exists($basePath . 'icons')) {
            mkdir($basePath . 'icons', 0777, true);
        }
    }

    /**
     * Procesar el campo que puede ser un archivo o un string.
     */
    private function processField($request, $fieldName, $oldPath, $destination)
    {
        // Verificar si es un archivo cargado
        if ($request->hasFile($fieldName)) {
            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            $fileName = time() . '_' . $request->file($fieldName)->getClientOriginalName();
            $request->file($fieldName)->move($destination, $fileName);
            return 'storage/categories/' . basename($destination) . '/' . $fileName;
        }

        // Verificar si es un string (URL)
        if (is_string($request->input($fieldName))) {
            return $request->input($fieldName);
        }

        // Eliminar archivo si el valor es null y el archivo existía antes
        if (is_null($request->input($fieldName)) && $oldPath) {
            $this->deleteFile($oldPath);
            return null;
        }

        // Retornar la ruta antigua si no hubo cambios
        return $oldPath;
    }

    public function destroy($id)
    {
        try {
            // Buscar la categoría
            $category = Category::findOrFail($id);

            // Eliminar categorías hijas recursivamente
            $this->deleteChildren($category);

            // Eliminar los archivos relacionados de la categoría principal
            if ($category->img) {
                $this->deleteFile(public_path($category->img));
            }
            if ($category->sub_img) {
                $this->deleteFile(public_path($category->sub_img));
            }
            if ($category->thumbnail_img) {
                $this->deleteFile(public_path($category->thumbnail_img));
            }
            if ($category->thumbnail_sub_img) {
                $this->deleteFile(public_path($category->thumbnail_sub_img));
            }
            if ($category->video) {
                $this->deleteFile(public_path($category->video));
            }
            if ($category->icon) {
                $this->deleteFile(public_path($category->icon));
            }

            if ($category->grid) {
                $gridData = is_string($category->grid) ? json_decode($category->grid, true) : $category->grid;
                foreach ($gridData as $gridItem) {
                    $fileUrl = $gridItem['props']['file']['url'] ?? null;
                    if ($fileUrl) {
                        $this->deleteFile(public_path($fileUrl));
                    }
                }
            }

            // Eliminar la categoría principal
            $category->delete();

            return ApiResponse::create('Categoría y sus categorías hijas eliminadas correctamente', 200);
        } catch (Exception $e) {
            return ApiResponse::create('Error al eliminar la categoría', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Eliminar categorías hijas de manera recursiva.
     */
    private function deleteChildren($category)
    {
        // Obtener las categorías hijas
        $children = Category::where('id_category', $category->id)->get();

        foreach ($children as $child) {
            // Llamar recursivamente a este método para manejar los descendientes
            $this->deleteChildren($child);

            // Eliminar archivos relacionados con la categoría hija
            if ($child->img) {
                $this->deleteFile(public_path($child->img));
            }
            if ($child->sub_img) {
                $this->deleteFile(public_path($child->sub_img));
            }
            if ($child->video) {
                $this->deleteFile(public_path($child->video));
            }
            if ($child->icon) {
                $this->deleteFile(public_path($child->icon));
            }

            if ($child->grid) {
                $gridData = is_string($child->grid) ? json_decode($child->grid, true) : $child->grid;
                foreach ($gridData as $gridItem) {
                    $fileUrl = $gridItem['props']['file']['url'] ?? null;
                    if ($fileUrl) {
                        $this->deleteFile(public_path($fileUrl));
                    }
                }
            }

            // Eliminar la categoría hija
            $child->delete();
        }
    }

    /**
     * Eliminar un archivo de la ruta dada.
     */
    private function deleteFile($filePath)
    {
        if (file_exists(public_path($filePath))) {
            unlink(public_path($filePath));
        }
    }

    private function removeEmptyCategories($category)
    {
        if ($category->categories->isEmpty()) {
            $category->unsetRelation('categories');
        } else {
            $category->categories = $category->categories->map(function ($childCategory) {
                return $this->removeEmptyCategories($childCategory);
            });
        }

        return $category;
    }

    private function attachProductInfoToGrid($category)
    {
        // Procesar el grid de la categoría principal
        $category->grid = $this->processGrid($category->grid);

        // Recursivamente procesar el grid de las subcategorías
        if ($category->categories) {
            foreach ($category->categories as $subCategory) {
                $this->attachProductInfoToGrid($subCategory);
            }
        }

        return $category;
    }

    private function processGrid($grid)
    {
        // Decodificar el grid en un array si está en formato JSON
        $gridArray = is_string($grid) ? json_decode($grid, true) : $grid;

        if (is_array($gridArray)) {
            foreach ($gridArray as &$gridItem) {
                if (isset($gridItem['props']['type']) && $gridItem['props']['type'] === 'Producto') {
                    $productId = $gridItem['props']['id'];
                    $product = Product::find($productId); // Obtener la información del producto

                    if ($product) {
                        $gridItem['props']['product_info'] = $product; // Agregar información del producto
                    } else {
                        $gridItem['props']['type'] = 'null';
                        $gridItem['props']['id'] = null;
                    }
                }
            }
        }

        return $gridArray;
    }

}
