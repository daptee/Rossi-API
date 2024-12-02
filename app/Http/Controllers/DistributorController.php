<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Distributor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class DistributorController extends Controller
{
    // GET ALL (para admin)
    public function index()
    {
        try {
            $distributors = Distributor::where('status', 2)
                ->with(['locality.province', 'status'])->get();

            return ApiResponse::create('Succeeded', 200, $distributors);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los distribuidores', 500, ['error' => $e->getMessage()]);
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
                'locality_id' => 'required|integer|exists:localities,id',
                'locality' => 'required|string|max:255',
                'position' => ['required', 'array'],
                'position.lat' => 'required|numeric|between:-90,90',
                'position.lng' => 'required|numeric|between:-180,180',
                'postal_code' => 'nullable|string|max:10', // Opcional
                'web_url' => 'nullable|url|max:255',
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
            $distributor->load('locality.province', 'status');

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
                'name' => 'required|string|max:150',
                'number' => 'required|string|max:10',
                'address' => 'required|string|max:255',
                'locality_id' => 'required|integer|exists:localities,id',
                'locality' => 'nullable|string|max:255',
                'position' => ['required', 'array'],
                'position.lat' => 'required|numeric|between:-90,90',
                'position.lng' => 'required|numeric|between:-180,180',
                'postal_code' => 'nullable|string|max:10',
                'web_url' => 'nullable|url|max:255',
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

}
