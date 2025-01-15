<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Exception;
use App\Http\Token\TokenService;

class AuthController extends Controller
{
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return ApiResponse::create('Credenciales no válidas', 401);
            }

            // Generar el token usando el servicio TokenService
            $token = $this->tokenService->generateToken($user);

            return ApiResponse::create('Inicio de sesión exitoso', 200, $token);

        } catch (Exception $e) {
            return ApiResponse::create('Error al iniciar sesión', 500, ['error' => $e->getMessage()]);
        }
    }
    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    public function changePassword(Request $request)
    {
        try {
            // Validar la entrada
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            // Obtener el usuario autenticado
            $user = auth()->user();

            // Verificar si la contraseña actual coincide
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'La contraseña actual es incorrecta',
                ], 400);
            }

            // Asignar la nueva contraseña (se encripta automáticamente en el modelo)
            $user->password = $request->new_password;
            $user->save();

            return response()->json([
                'message' => 'Contraseña actualizada con éxito',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la contraseña',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
