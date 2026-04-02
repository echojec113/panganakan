<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PrenatalVisitController;
use App\Http\Controllers\MedicalHistoryController;
use App\Http\Controllers\BirthPlanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UltrasoundController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\RiskMonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});


Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/patients/trashed', [PatientController::class, 'trashed'])
    ->name('patients.trashed');

    Route::post('/patients/{id}/restore', [PatientController::class, 'restore'])
    ->name('patients.restore');
    Route::get('/ultrasound/{id}/edit', [UltrasoundController::class, 'edit'])
    ->name('ultrasound.edit');
    Route::post('/patients/{id}/deliver', [PatientController::class, 'markDelivered'])
    ->name('patients.deliver');
    Route::get('/patients/delivered', [PatientController::class, 'delivered'])
    ->name('patients.delivered');
    Route::resource('staff', StaffController::class)->except(['show']);
     Route::get('/risk-monitoring', [App\Http\Controllers\RiskMonitoringController::class, 'index'])
    ->middleware('auth')
    ->name('risk.monitoring');
    Route::middleware(['auth'])->group(function () {

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
    ->middleware(['auth'])
    ->name('audit-logs.index');     
});
   
    
    





    Route::resource('patients', PatientController::class);
    Route::resource('prenatal-visits', PrenatalVisitController::class);
    Route::resource('medical-histories', MedicalHistoryController::class);
    Route::resource('birth-plans', BirthPlanController::class);
    Route::post('/ultrasound/store', [UltrasoundController::class, 'store'])
    ->name('ultrasound.store');
    Route::get('/ultrasound/create/{patient}', [UltrasoundController::class, 'create'])
    ->name('ultrasound.create');
    Route::put('/ultrasound/{id}', [UltrasoundController::class, 'update'])
    ->name('ultrasound.update');
    
    



});

require __DIR__.'/auth.php';