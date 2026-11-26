<?php

use App\Http\Controllers\Api\EmployeeApiController;
use Illuminate\Support\Facades\Route;

/*
 * API untuk aplikasi Inventaris Umum & IT (prefix /api/v1, token Bearer).
 * Lihat App\Http\Middleware\ApiToken.
 */
Route::get('/ping', [EmployeeApiController::class, 'ping'])->name('ping');
Route::get('/employees', [EmployeeApiController::class, 'index'])->name('employees');
Route::get('/organization', [EmployeeApiController::class, 'organization'])->name('organization');
