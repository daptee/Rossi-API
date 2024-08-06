<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Verificar si el usuario está autenticado
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return ApiResponse::create('Credenciales no válidas', 401);
            }
        } catch (TokenExpiredException $e) {
            return ApiResponse::create('El token a expirado', 401);
        } catch (JWTException $e) {
            return ApiResponse::create('Token no proporcionado', 401);
        }

        // Verificar si el usuario es administrador
        if ($user->id_user_type !== 1) {
            return ApiResponse::create('Prohibido', 403);
        }

        return $next($request);
    }
}
