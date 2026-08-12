<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectClarificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectGenerationController;
use App\Http\Controllers\ProjectWizardController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas públicas
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/dashboard')->name('home');

/*
|--------------------------------------------------------------------------
| Autenticación — pantalla 01
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Aplicación — pantallas 02 a 06
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    // Pantalla 02 — dashboard de proyectos y completaciones
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Pantallas 03 y 04 — asistente de creación
    Route::get('/projects/new/tipo', [ProjectWizardController::class, 'type'])->name('projects.create.type');
    Route::post('/projects/new/tipo', [ProjectWizardController::class, 'storeType'])->name('projects.store.type');
    Route::get('/projects/new/descripcion', [ProjectWizardController::class, 'prompt'])->name('projects.create.prompt');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');

    // Pantalla 05 — espera con polling
    Route::get('/projects/{project}/generando', [ProjectGenerationController::class, 'show'])->name('projects.generating');
    Route::get('/projects/{project}/estado', [ProjectGenerationController::class, 'status'])->name('projects.status');
    Route::post('/projects/{project}/clarifications', [ProjectClarificationController::class, 'store'])
        ->name('projects.clarifications.store');
    Route::post('/projects/{project}/regenerar', [ProjectController::class, 'regenerate'])->name('projects.regenerate');

    // Pantalla 06 — malla CPM del proyecto
    Route::get('/projects/{project}/malla', [ProjectController::class, 'show'])->name('projects.show');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Panel lateral de la pantalla 06
    Route::patch('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::post('/activities/{activity}/completar', [ActivityController::class, 'toggleCompletion'])->name('activities.toggle');

    // Plan de suscripción (contratación simulada, sin pasarela de pago)
    Route::get('/cuenta/plan', [SubscriptionController::class, 'show'])->name('account.plan');
    Route::post('/cuenta/plan', [SubscriptionController::class, 'store'])->name('account.plan.store');
    Route::delete('/cuenta/plan', [SubscriptionController::class, 'destroy'])->name('account.plan.destroy');
});
