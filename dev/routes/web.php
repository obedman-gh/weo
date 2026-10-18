<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:20,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy']);
    Route::get('/dashboard', [WorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::get('/admin', [AdminController::class, 'index']);
    Route::post('/admin/users', [AdminController::class, 'store']);
    Route::put('/admin/users/{user}', [AdminController::class, 'update']);
    Route::get('/notifications', [WorkspaceController::class, 'notifications']);
    Route::post('/notifications/{notification}/read', [WorkspaceController::class, 'readNotification']);
    Route::get('/documents/versions/{version}/download', [FileController::class, 'document']);
    Route::get('/proofs/{type}/{id}', [FileController::class, 'proof'])->whereNumber('id');
    Route::get('/projects/{project}/report.pdf', [ReportController::class, 'report']);
    Route::get('/rundowns/{rundown}/pdf', [ReportController::class, 'rundown']);
    Route::post('/projects/{project}/actions/{operation}/{id?}', [WorkspaceController::class, 'mutate'])->whereNumber('id');
    Route::get('/projects/{project}/{module}', [WorkspaceController::class, 'show']);
});
