<?php

namespace App\Http\Responses;

class ApiResponse
{
    /**
     * Generate a standard API response.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  mixed  $data
     * @param  array|null  $meta
     * @return \Illuminate\Http\JsonResponse
     */
    public static function create($message, $code, $data = [], $meta = null)
    {
        $response = [
            'message' => $message,
            'data' => empty($data) ? null : $data, // Establecer data como null si no hay datos
        ];

        // Añadir metadata solo si no es nula
        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }
}
