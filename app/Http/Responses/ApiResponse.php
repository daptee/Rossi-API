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
            'data' => $result,
        ];

        if ($code !== 200) {
            $response['code'] = $code;
        }

        return response()->json($response, $code);
    }
}
