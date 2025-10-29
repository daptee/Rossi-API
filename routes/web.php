<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

Route::get('/ready', function () {
    try {
        DB::select('SELECT 1');
        return response()->json(['status' => 'ready'], 200);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'degraded', 'error' => $e->getMessage()], 503);
    }
});
