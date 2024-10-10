<?php

namespace App\Http\Responses;

class ApiResponse
{
    /**
     * Generate a standard API response.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  mixed  $result
     * @return \Illuminate\Http\JsonResponse
     */
    public static function create($message, $code, $result = [])
    {
        $response = [
            'message' => $message,
            'data' => empty($result) ? null : $result, // Establecer data como null si result está vacío
        ];

        return response()->json($response, $code);
    }
}
