<?php

namespace App\Http\Controllers;

use App\Models\Component;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;

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
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Definir la ruta base dentro de public/storage/components
            $baseStoragePath = public_path('storage/components/images/');

            // Verificar si la carpeta existe, si no, crearla
            if (!file_exists($baseStoragePath)) {
                mkdir($baseStoragePath, 0777, true);
            }

            // Procesar la imagen y moverla a la carpeta adecuada
            $imgPath = null;
            if ($request->hasFile('img')) {
                $fileName = time() . '_' . $request->file('img')->getClientOriginalName();
                $request->file('img')->move($baseStoragePath, $fileName);
                $imgPath = 'storage/components/images/' . $fileName;
            }

            $component = Component::create([
                'id_component' => $request->id_component,
                'name' => $request->name,
                'description' => $request->description,
                'img' => $imgPath,
                'status' => $request->status,
                'id_category' => 1,
            ]);

            $component->load('status');

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
                'img' => 'sometimes|required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'status' => 'required|integer|exists:status,id',
                'id_category' => 'nullable|exists:categories,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $component = Component::findOrFail($id);

            // Definir la ruta base dentro de public/storage/components
            $baseStoragePath = public_path('storage/components/images/');

            // Verificar si la carpeta existe, si no, crearla
            if (!file_exists($baseStoragePath)) {
                mkdir($baseStoragePath, 0777, true);
            }

            if ($request->hasFile('img')) {
                // Elimina la imagen anterior si existe
                if ($component->img && file_exists(public_path($component->img))) {
                    unlink(public_path($component->img)); // Usamos unlink() para borrar la imagen
                }

                // Almacenar la nueva imagen
                $fileName = time() . '_' . $request->file('img')->getClientOriginalName();
                $request->file('img')->move($baseStoragePath, $fileName);
                $component->img = 'storage/components/images/' . $fileName;
            }

            if ($request->has('id_component')) {
                $component->id_component = $request->id_component;
            }

            // Actualiza el nombre si se ha enviado en la solicitud
            if ($request->has('name')) {
                $component->name = $request->name;
            }

            if ($request->has('description')) {
                $component->description = $request->description;
            }

            if ($request->has('status')) {
                $component->status = $request->status;
            }

            if ($request->has('id_category')) {
                $component->id_category = '1';
            }

            $component->save();
            $component->load('status');

            return ApiResponse::create('Componente actualizado correctamente', 200, $component);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el componente', 500, ['error' => $e->getMessage()]);
        }
    }

}
