<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SessionsController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'Homepage';
});

Route::middleware('auth')->group(function () {
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/archived', [TaskController::class, 'archived']);
    Route::get('/tasks/create', [TaskController::class, 'create']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::patch('/tasks/{taskId}/restore', [TaskController::class, 'restore']);
    Route::delete('/tasks/{taskId}/force-delete', [TaskController::class, 'forceDelete']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->middleware('can:update,task');
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
    Route::patch('/tasks/{task}/move-left', [TaskController::class, 'moveLeft'])->middleware('can:update,task');
    Route::patch('/tasks/{task}/move-right', [TaskController::class, 'moveRight'])->middleware('can:update,task');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    Route::delete('/logout', [SessionsController::class, 'destroy']);
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create']);
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [SessionsController::class, 'create'])->name('login');
    Route::post('/login', [SessionsController::class, 'store']);
});

