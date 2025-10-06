<?php

use App\Http\Controllers\Api\ShiftOverrideController;
use App\Http\Controllers\Api\V1\AttendanceSummaryController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\ViolationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API V1 Routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // Shift Resource Routes
    Route::apiResource('shifts', ShiftController::class);

    // Shift Override Resource Routes
    Route::apiResource('shift-overrides', ShiftOverrideController::class);

    // Attendance Summary Routes
    Route::get('attendance-summaries', [AttendanceSummaryController::class, 'index']);
    Route::get('attendance-summaries/{id}', [AttendanceSummaryController::class, 'show']);
    Route::post('attendance-summaries/recalculate', [AttendanceSummaryController::class, 'recalculate']);

    // Violation Routes
    Route::get('violations', [ViolationController::class, 'index']);
    Route::get('violations/{violation}', [ViolationController::class, 'show']);
    Route::get('employees/{employee}/violations', [ViolationController::class, 'employeeViolations']);
    Route::post('violations/{violation}/acknowledge', [ViolationController::class, 'acknowledge']);
    Route::post('violations/{violation}/dispute', [ViolationController::class, 'dispute']);
});
