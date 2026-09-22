<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;

Route::middleware(['auth.jwt'])->group(function () {
    Route::get('/auth/me', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'data' => auth()->user()
        ]);
    });
    Route::get('/presensi/today', [AttendanceController::class, 'today']);
    Route::get('/presensi/history', [AttendanceController::class, 'history']);
    Route::post('/presensi', [AttendanceController::class, 'store']);
    Route::get('/admin/attendances', [\App\Http\Controllers\AdminAttendanceController::class, 'index']);
    Route::get('/admin/settings', [\App\Http\Controllers\SettingController::class, 'index']);
    Route::put('/admin/settings', [\App\Http\Controllers\SettingController::class, 'update']);
});
