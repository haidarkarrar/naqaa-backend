<?php

use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\AuthenticateDoctor;
use Illuminate\Support\Facades\Route;

Route::post('doctor/login', [AuthController::class, 'login']);

Route::middleware([AuthenticateDoctor::class])->group(function () {
    Route::post('doctor/logout', [AuthController::class, 'logout']);

    Route::get('doctor/admissions', [AdmissionController::class, 'index']);
    Route::get('doctor/admissions/{id}', [AdmissionController::class, 'show']);
    Route::post('doctor/admissions/{id}/form', [AdmissionController::class, 'saveForm']);
    Route::post('doctor/admissions/{id}/attachments', [AdmissionController::class, 'uploadAttachment']);
});
