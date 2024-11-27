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
            $distributors = Distributor::with(['locality.province'])->get();

            return ApiResponse::create('Succeeded', 200, $distributors);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los distribuidores', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST - Crear un nuevo producto
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:150',
                'address' => 'required|string|max:255',
                'number' => 'required|string|max:10',
                'locality_id' => 'required|integer|exists:localities,id',
                'locality' => 'required|string|max:255',
                'postal_code' => 'nullable|string|max:10',  // Si postal_code es opcional
                'web_url' => 'nullable|url|max:255',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
                'instagram' => 'nullable|string|max:100',
                'facebook' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $distributor = Distributor::create($request->all());
            $distributor->load('locality.province');

            return ApiResponse::create('Distribuitor creado con exito', 200, $distributor);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un distribuidor', 500, ['error' => $e->getMessage()]);
        }
    }


    // PUT - Editar un producto
    public function update(Request $request, $id)
    {
        try {

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:150',
                'number' => 'required|string|max:10',
                'address' => 'required|string|max:255',
                'locality_id' => 'required|integer|exists:localities,id',
                'locality' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:10',
                'web_url' => 'nullable|url|max:255',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
                'instagram' => 'nullable|string|max:100',
                'facebook' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $distributor = Distributor::findOrFail($id);
            $distributor->update($request->all());

            $distributor->load('locality.province');

            return ApiResponse::create('Distribuidor actualizado con exito', 200, $distributor);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar un distribuidor', 500, ['error' => $e->getMessage()]);
        }
    }

}
