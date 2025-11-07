<?php

namespace App\Http\Controllers;

use App\Helpers\ImageHelper;
use App\Services\FileStorageService;
use App\Models\Component;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;
use Log;

class ComponentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $search = $request->query('search'); // Parámetro de búsqueda
            $perPage = $request->query('per_page'); // Número de elementos por página

            // Consulta inicial: solo componentes padre
            $query = Component::with([
                'status',
                'components' => function ($query) {
                    $query->with('status'); // Incluir el estado de los hijos
                }
            ])->whereNull('id_component'); // Filtrar para que solo sean padres

            // Filtrar por búsqueda si el parámetro está presente
            if ($search !== null) {
                $query->where('name', 'like', '%' . $search . '%'); // Buscar por nombre del componente
            }

            // Verificar si se debe paginar o traer todos
            if ($perPage !== null) {
                $components = $query->paginate((int) $perPage); // Paginar si se especifica el parámetro
                $metaData = [
                    'page' => $components->currentPage(),
                    'per_page' => $components->perPage(),
                    'total' => $components->total(),
                    'last_page' => $components->lastPage(),
                ];
                $data = $components->items();
            } else {
                $data = $query->get(); // Traer todos los registros si no hay paginación
                $metaData = [
                    'total' => $data->count(),
                    'per_page' => 'Todos',
                    'page' => 1,
                    'last_page' => 1,
                ];
            }

            return ApiResponse::create('Componentes obtenidos correctamente', 200, $data, $metaData);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer los componentes', 500, [], ['error' => $e->getMessage()]);
        }
    }


    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_component' => 'nullable|exists:components,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'img' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'status' => 'required|integer|exists:status,id',
                'id_category' => 'nullable|exists:categories,id',
                'meta_data' => 'nullable|json',
                'subComponents' => 'nullable|array',
                'subComponents.*.name' => 'required_with:subComponents|string|max:255',
                'subComponents.*.description' => 'nullable|string',
                'subComponents.*.img' => 'required_with:subComponents|image|mimes:jpeg,png,jpg,gif|max:2048',
                'subComponents.*.status' => 'required_with:subComponents|integer|exists:status,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            if (config('filesystems.default') === 'local' || config('filesystems.default') === 'public') {
                // Definir la ruta base dentro de public/storage/components
                $baseStoragePath = public_path('storage/components/images/');

                // Verificar si la carpeta existe, si no, crearla
                if (!file_exists($baseStoragePath)) {
                    mkdir($baseStoragePath, 0777, true);
                }
            }

            // Procesar la imagen del componente padre
            $imgPath = null;
            if ($request->hasFile('img')) {
                $imgPath = FileStorageService::storeFile($request->file('img'), 'storage/components/images');
                $imgPathThumbnail = ImageHelper::saveReducedImage(
                    $request->file('img'),
                    'storage/components/images/'
                );
            }

            $decodedMetaData = json_decode($request->meta_data, true);

            // Crear el componente padre
            $component = Component::create([
                'id_component' => $request->id_component,
                'name' => $request->name,
                'description' => $request->description,
                'img' => $imgPath,
                'thumbnail_img' => $imgPathThumbnail,
                'status' => $request->status,
                'id_category' => 1,
                'meta_data' => $decodedMetaData,
            ]);

            // Procesar subcomponentes
            if ($request->has('subComponents')) {
                foreach ($request->subComponents as $index => $subComponentData) {
                    $subImgPath = null;
                    $subImgPathThumb = null;
                    if ($request->hasFile("subComponents.$index.img")) {
                        $imgFile = $request->file("subComponents.$index.img");

                        $subImgPath = FileStorageService::storeFile($imgFile, 'storage/components/images');
                        $subImgPathThumb = ImageHelper::saveReducedImage(
                            $imgFile,
                            'storage/components/images/'
                        );
                    }

                    $component->children()->create([
                        'name' => $subComponentData['name'],
                        'description' => $subComponentData['description'] ?? null,
                        'img' => $subImgPath,
                        'thumbnail_img' => $subImgPathThumb,
                        'status' => $subComponentData['status'],
                        'id_category' => 1,
                    ]);
                }
            }

            $component->load('status', 'children');

            return ApiResponse::create('Componente creado correctamente', 200, $component);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear el componente', 500, ['error' => $e->getMessage()]);
        }
    }
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_component' => 'nullable|exists:components,id',
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'img' => 'sometimes', // Puede ser un string, null, o un archivo.
                'status' => 'required|integer|exists:status,id',
                'id_category' => 'nullable|exists:categories,id',
                'meta_data' => 'nullable|json',
                'subComponents' => 'nullable|array',
                'subComponents.*.id' => 'nullable|exists:components,id',
                'subComponents.*.name' => 'sometimes|required|string|max:255',
                'subComponents.*.description' => 'nullable|string',
                'subComponents.*.img' => 'nullable', // Puede ser string, null, o archivo.
                'subComponents.*.status' => 'sometimes|required|integer|exists:status,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $component = Component::findOrFail($id);

            if (config('filesystems.default') === 'local' || config('filesystems.default') === 'public') {
                $baseStoragePath = public_path('storage/components/images/');

                if (!file_exists($baseStoragePath)) {
                    mkdir($baseStoragePath, 0777, true);
                }
            }

            // Procesar imagen principal
            if ($request->has('img')) {
                if (is_string($request->img)) {
                    // Conservar imagen actual
                } elseif (is_null($request->img)) {
                    // Eliminar imagen actual
                    if ($component->img && FileStorageService::fileExists($component->img)) {
                        FileStorageService::deleteFile($component->img);
                    }
                    if ($component->thumbnail_img && FileStorageService::fileExists($component->thumbnail_img)) {
                        FileStorageService::deleteFile($component->thumbnail_img);
                    }
                    $component->img = null;
                    $component->thumbnail_img = null;
                } elseif ($request->hasFile('img')) {
                    // Reemplazar imagen actual
                    if ($component->img && FileStorageService::fileExists($component->img)) {
                        FileStorageService::deleteFile($component->img);
                    }

                    if ($component->thumbnail_img && FileStorageService::fileExists($component->thumbnail_img)) {
                        FileStorageService::deleteFile($component->thumbnail_img);
                    }

                    $component->img = FileStorageService::storeFile($request->file('img'), 'storage/components/images');
                    $component->thumbnail_img = ImageHelper::saveReducedImage(
                        $request->file('img'),
                        'storage/components/images/'
                    );
                }
            }

            $newMetaData = is_string($request->meta_data) ? json_decode($request->meta_data, true) : $request->meta_data;


            // Actualizar campos del componente principal
            $component->fill($request->only([
                'id_component',
                'name',
                'description',
                'status',
                'id_category' => 1,
            ]));

            $component->meta_data = $newMetaData;
            $component->save();

            // Procesar subcomponentes
            if ($request->has('subComponents')) {
                // Eliminar subcomponentes no incluidos en la solicitud
                $requestedIds = collect($request->subComponents)->pluck('id')->filter();
                $component->children()->whereNotIn('id', $requestedIds)->get()->each(function ($subComponent) {
                    if ($subComponent->img && FileStorageService::fileExists($subComponent->img)) {
                        FileStorageService::deleteFile($subComponent->img);
                    }
                    if ($subComponent->thumbnail_img && FileStorageService::fileExists($subComponent->thumbnail_img)) {
                        FileStorageService::deleteFile($subComponent->thumbnail_img);
                    }
                    $subComponent->delete();
                });


                $existingSubComponents = $component->children->keyBy('id');

                foreach ($request->subComponents as $index => $subComponentData) {
                    if (isset($subComponentData['id'])) {
                        $subComponent = $existingSubComponents->get($subComponentData['id']);

                        if ($subComponent) {
                            Log::info("aquiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii 1111111");
                            if ($request->hasFile("subComponents.{$index}.img")) {
                                Log::info("aquiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii 2222");
                                // Eliminar la imagen anterior si existe
                                if ($subComponent->img && FileStorageService::fileExists($subComponent->img)) {
                                    FileStorageService::deleteFile($subComponent->img);
                                }

                                if ($subComponent->thumbnail_img && FileStorageService::fileExists($subComponent->thumbnail_img)) {
                                    FileStorageService::deleteFile($subComponent->thumbnail_img);
                                }

                                // Procesar nueva imagen
                                $subComponent->img = FileStorageService::storeFile($request->file("subComponents.{$index}.img"), 'storage/components/images');
                                $subComponent->thumbnail_img = ImageHelper::saveReducedImage(
                                    $request->file("subComponents.{$index}.img"),
                                    'storage/components/images/'
                                );
                            } elseif ($request->input("subComponents.{$index}.img") === null) {
                                Log::info("Se recibió `null`, la imagen será eliminada.");

                                // Caso en el que se envíe `null` para eliminar la imagen
                                if ($subComponent->img && FileStorageService::fileExists($subComponent->img)) {
                                    FileStorageService::deleteFile($subComponent->img);
                                }
                                if ($subComponent->thumbnail_img && FileStorageService::fileExists($subComponent->thumbnail_img)) {
                                    FileStorageService::deleteFile($subComponent->thumbnail_img);
                                }
                                $subComponent->img = null;
                                $subComponent->thumbnail_img = null;
                            } elseif (is_string($request->input("subComponents.{$index}.img"))) {
                                Log::info("Se recibió un string, conservar la imagen actual.");
                                // Conservar la imagen actual (no realizar ninguna acción)
                            } else {
                                Log::info("No se recibió ningún valor para la imagen.");
                                // No realizar ninguna acción adicional
                            }

                            // Actualizar los datos del subcomponente
                            $subComponent->update([
                                'name' => $subComponentData['name'],
                                'description' => $subComponentData['description'] ?? null,
                                'img' => $subComponent->img,
                                'status' => $subComponentData['status'],
                            ]);
                        }
                    } else {
                        // Crear nuevos subcomponentes
                        $newImgPath = null;
                        $newImgPathThumb = null;

                        if ($request->hasFile("subComponents.{$index}.img")) {
                            $newImgPath = FileStorageService::storeFile($request->file("subComponents.{$index}.img"), 'storage/components/images');
                            $newImgPathThumb = ImageHelper::saveReducedImage(
                                $request->file("subComponents.{$index}.img"),
                                'storage/components/images/'
                            );
                        }

                        $component->children()->create([
                            'name' => $subComponentData['name'],
                            'description' => $subComponentData['description'] ?? '',
                            'img' => $newImgPath,
                            'thumbnail_img' => $newImgPathThumb,
                            'status' => $subComponentData['status'] ?? 2, // Asignar un estado por defecto si no se proporciona
                            'id_category' => 1,
                        ]);

                    }
                }
            }

            $component->load('status', 'children');

            return ApiResponse::create('Componente actualizado correctamente', 200, $component);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el componente', 500, ['error' => $e->getMessage()]);
        }
    }
}
