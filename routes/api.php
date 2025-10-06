<?php

use App\Http\Controllers\Api\FailedJobController;
use App\Http\Controllers\Api\ShiftOverrideController;
use App\Http\Controllers\Api\V1\AttendanceCorrectionController;
use App\Http\Controllers\Api\V1\AttendanceSummaryController;
use App\Http\Controllers\Api\V1\ManagerCorrectionController;
use App\Http\Controllers\Api\V1\QueueMetricsController;
use App\Http\Controllers\Api\V1\ReportController;
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

    // Attendance Correction Routes (Employee)
    Route::apiResource('corrections', AttendanceCorrectionController::class);
    Route::get('corrections/{correction}/document', [AttendanceCorrectionController::class, 'downloadDocument']);

    // Manager Correction Routes
    Route::prefix('manager')->group(function () {
        Route::get('corrections', [ManagerCorrectionController::class, 'index']);
        Route::post('corrections/{correction}/approve', [ManagerCorrectionController::class, 'approve']);
        Route::post('corrections/{correction}/reject', [ManagerCorrectionController::class, 'reject']);
    });

    // Queue Metrics Routes (Admin only)
    Route::prefix('queue')->middleware(['auth:sanctum'])->group(function () {
        Route::get('metrics', [QueueMetricsController::class, 'index']);
        Route::get('metrics/{queue}', [QueueMetricsController::class, 'show']);
    });

    // Report Routes
    Route::prefix('reports')->name('api.v1.reports.')->group(function () {
        Route::post('attendance', [ReportController::class, 'attendanceReport'])->name('attendance');
        Route::post('violations', [ReportController::class, 'violationReport'])->name('violations');
        Route::get('download', [ReportController::class, 'downloadReport'])->name('download');
    });

    // Failed Job Management Routes (Admin only)
    Route::prefix('failed-jobs')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [FailedJobController::class, 'index']);
        Route::get('/{id}', [FailedJobController::class, 'show']);
        Route::post('/{id}/retry', [FailedJobController::class, 'retry']);
        Route::post('/retry-all', [FailedJobController::class, 'retryAll']);
        Route::delete('/{id}', [FailedJobController::class, 'destroy']);
        Route::post('/prune', [FailedJobController::class, 'prune']);
    });
});
