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

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return ApiResponse::create('Invalid credentials', 401);
            }

            // Generar el token usando el servicio TokenService
            $token = $this->tokenService->generateToken($user);

            return ApiResponse::create('Login successful', 200, $token);

        } catch (Exception $e) {
            return ApiResponse::create('Error logging in', 500);
        }
    }
}
