<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductCategoryController;

Route::get('/', function () {
    return 'welcome';
});

Route::post('/users', [UserController::class, 'store']);

Route::post('/login', [AuthController::class, 'login']);

Route::get('/categories', [ProductCategoryController::class, 'index']);
Route::post('/categories', [ProductCategoryController::class, 'store']);
Route::put('/categories/{id}', [ProductCategoryController::class, 'update']);