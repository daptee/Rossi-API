<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Distributor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\Notification;
use Exception;

class DistributorController extends Controller
{
    // GET ALL (para admin)
    public function index(Request $request)
    {
        try {
            $search = $request->query('search'); // Parámetro de búsqueda
            $perPage = $request->query('per_page'); // Número de elementos por página, por defecto es nulo
            $status = $request->query('status');

            // Consulta inicial
            $query = Distributor::query()
                ->with(['province', 'locality', 'status'])
                ->orderBy('name', 'asc'); // Ordenar alfabéticamente

            // Filtrar por estado si el parámetro está presente
            if ($status !== null) {
                $query->where('status', $status);
            }

            // Filtrar por búsqueda si el parámetro está presente
            if ($search !== null) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%') // Buscar por nombre del distribuidor
                        ->orWhereHas('locality', function ($subQuery) use ($search) {
                            $subQuery->where('name', 'like', '%' . $search . '%') // Buscar por nombre de la localidad
                                ->orWhereHas('province', function ($subSubQuery) use ($search) {
                                    $subSubQuery->where('name', 'like', '%' . $search . '%'); // Buscar por nombre de la provincia
                                });
                        });
                });
            }

            // Verificar si el parámetro per_page está presente
            if ($perPage !== null) {
                $distributors = $query->paginate((int) $perPage); // Paginación si se especifica per_page
                $metaData = [
                    'page' => $distributors->currentPage(),
                    'per_page' => $distributors->perPage(),
                    'total' => $distributors->total(),
                    'last_page' => $distributors->lastPage(),
                ];
                $data = $distributors->items();
            } else {
                $data = $query->get(); // Traer todos los registros si no se especifica per_page
                $metaData = [
                    'total' => $data->count(),
                    'per_page' => 'Todos',
                    'page' => 1,
                    'last_page' => 1,
                ];
            }

            return ApiResponse::create('Distribuidores obtenidos correctamente', 200, $data, $metaData);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los distribuidores', 500, [], ['error' => $e->getMessage()]);
        }
    }


    // POST - Crear un nuevo producto
    public function store(Request $request)
    {
        try {
            // Validación de los datos recibidos
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:150',
                'address' => 'required|string|max:255',
                'number' => 'required|string|max:10',
                'province_id' => 'required|integer|exists:provinces,id',
                'locality_id' => 'required|integer|exists:localities,id',
                'locality' => 'required|string|max:255',
                'position' => ['required', 'array'],
                'position.lat' => 'required|numeric|between:-90,90',
                'position.lng' => 'required|numeric|between:-180,180',
                'postal_code' => 'nullable|string|max:10', // Opcional
                'web_url' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
                'instagram' => 'nullable|string|max:100',
                'facebook' => 'nullable|string|max:100',
                'status' => 'required|integer|exists:status,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Procesar los datos para almacenar
            $data = $request->all();

            // Asegurarse de que 'position' se guarde como un array
            $data['position'] = $request->input('position');

            // Crear el distribuidor
            $distributor = Distributor::create($data);

            // Cargar relaciones necesarias
            $distributor->load('province', 'locality', 'status');

            return ApiResponse::create('Distribuidor creado con éxito', 200, $distributor);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un distribuidor', 500, ['error' => $e->getMessage()]);
        }
    }


    // PUT - Editar un producto
    public function update(Request $request, $id)
    {
        try {
            // Validación de los datos recibidos
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:150',
                'number' => 'nullable|string|max:10',
                'address' => 'nullable|string|max:255',
                'province_id' => 'nullable|integer|exists:provinces,id',
                'locality_id' => 'nullable|integer|exists:localities,id',
                'locality' => 'nullable|string|max:255',
                'position' => ['nullable', 'array'],
                'position.lat' => 'nullable|numeric|between:-90,90',
                'position.lng' => 'nullable|numeric|between:-180,180',
                'postal_code' => 'nullable|string|max:10',
                'web_url' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
                'instagram' => 'nullable|string|max:100',
                'facebook' => 'nullable|string|max:100',
                'status' => 'nullable|integer|exists:status,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Buscar el distribuidor
            $distributor = Distributor::findOrFail($id);

            // Preparar los datos antes de actualizar
            $data = $request->all();

            // Asegurarse de que 'position' se guarde como un array
            $data['position'] = $request->input('position');

            // Actualizar los datos del distribuidor
            $distributor->update($data);

            // Cargar relaciones necesarias
            $distributor->load('locality.province', 'status');

            return ApiResponse::create('Distribuidor actualizado con éxito', 200, $distributor);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar un distribuidor', 500, ['error' => $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        try {
            // Buscar el distribuidor por ID
            $distributor = Distributor::find($id);

            if (!$distributor) {
                return ApiResponse::create('Distribuidor no encontrado', 404);
            }

            // Eliminar el distribuidor
            $distributor->delete();

            return ApiResponse::create('Distribuidor eliminado con éxito', 200);
        } catch (Exception $e) {
            return ApiResponse::create('Error al eliminar el distribuidor', 500, ['error' => $e->getMessage()]);
        }
    }

    public function send(Request $request)
    {
        try {
            // Validación de los datos recibidos
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:150',
                'company' => 'required|string|max:255',
                'province' => 'required|string|max:255',
                'locality' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'required|email|max:100',
                'message' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            // Procesar los datos para almacenar
            $data = $request->all();

            $recipientEmail = env('MAIL_NOTIFICATION_TO');

            // Enviar el correo
            Mail::to($recipientEmail)->send(new Notification($data));

            return ApiResponse::create('Solicitud de distribuidor enviada correctamente', 200, []);
        } catch (Exception $e) {
            return ApiResponse::create('Error al enviar la solicitud para un nuevo distribuidor', 500, ['error' => $e->getMessage()]);
        }
    }
}
