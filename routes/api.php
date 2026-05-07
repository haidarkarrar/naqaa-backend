<?php

use App\Http\Controllers\Api\Admin\MetadataController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\AuthenticateApiUser;
use App\Http\Middleware\EnsureDoctorLinkedUser;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/refresh', [AuthController::class, 'refresh']);

// Legacy auth compatibility routes
Route::post('doctor/login', [AuthController::class, 'doctorLogin']);
Route::post('doctor/refresh', [AuthController::class, 'doctorRefresh']);

Route::middleware([AuthenticateApiUser::class])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::get('admissions', [AdmissionController::class, 'index']);
    Route::get('admissions/doctors/search', [AdmissionController::class, 'doctorsSearch']);
    Route::get('admissions/{id}', [AdmissionController::class, 'show']);
    Route::patch('admissions/{id}/patient', [AdmissionController::class, 'updatePatient']);
    Route::post('admissions/{id}/form', [AdmissionController::class, 'saveForm']);
    Route::post('admissions/{id}/attachments', [AdmissionController::class, 'uploadAttachment']);
    Route::delete('admissions/{id}/attachments/{attachmentId}', [AdmissionController::class, 'deleteAttachment']);
    Route::patch('admissions/{id}/status', [AdmissionController::class, 'updateStatus']);
    Route::post('admissions/status/batch/preview', [AdmissionController::class, 'previewBatchStatus']);
    Route::patch('admissions/status/batch', [AdmissionController::class, 'batchUpdateStatus']);

    Route::prefix('admin')->group(function () {
        Route::get('permissions', [MetadataController::class, 'permissions']);
        Route::get('doctors/search', [MetadataController::class, 'doctorsSearch']);

        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::patch('roles/{roleId}', [RoleController::class, 'update']);
        Route::delete('roles/{roleId}', [RoleController::class, 'destroy']);

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{userId}', [UserController::class, 'show']);
        Route::patch('users/{userId}', [UserController::class, 'update']);
        Route::patch('users/{userId}/password', [UserController::class, 'updatePassword']);
        Route::patch('users/{userId}/activation', [UserController::class, 'updateActivation']);
    });

    // Legacy admissions compatibility routes (doctor-linked users only)
    Route::middleware([EnsureDoctorLinkedUser::class])->group(function () {
        Route::post('doctor/logout', [AuthController::class, 'doctorLogout']);
        Route::get('doctor/admissions', [AdmissionController::class, 'index']);
        Route::get('doctor/admissions/{id}', [AdmissionController::class, 'show']);
        Route::post('doctor/admissions/{id}/form', [AdmissionController::class, 'saveForm']);
        Route::post('doctor/admissions/{id}/attachments', [AdmissionController::class, 'uploadAttachment']);
        Route::delete('doctor/admissions/{id}/attachments/{attachmentId}', [AdmissionController::class, 'deleteAttachment']);
    });
});
