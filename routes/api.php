<?php

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\RecruitmentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TrainingController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::middleware('tenant')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::middleware('permission:view_employees')->group(function () {
            Route::get('/employees', [EmployeeController::class, 'index']);
            Route::get('/employees/stats', [EmployeeController::class, 'stats']);
            Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
        });
        Route::post('/employees', [EmployeeController::class, 'store'])->middleware('permission:create_employees');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:edit_employees');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:delete_employees');

        Route::middleware('permission:view_departments')->group(function () {
            Route::get('/departments/tree', [DepartmentController::class, 'tree']);
            Route::get('/departments', [DepartmentController::class, 'index']);
            Route::get('/departments/{department}', [DepartmentController::class, 'show']);
        });
        Route::post('/departments', [DepartmentController::class, 'store'])->middleware('permission:create_departments');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:edit_departments');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:delete_departments');

        Route::middleware('permission:view_positions')->group(function () {
            Route::get('/positions', [PositionController::class, 'index']);
            Route::get('/positions/{position}', [PositionController::class, 'show']);
        });
        Route::post('/positions', [PositionController::class, 'store'])->middleware('permission:create_positions');
        Route::put('/positions/{position}', [PositionController::class, 'update'])->middleware('permission:edit_positions');
        Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->middleware('permission:delete_positions');

        Route::middleware('permission:view_contracts')->group(function () {
            Route::get('/contracts', [ContractController::class, 'index']);
            Route::get('/contracts/{contract}', [ContractController::class, 'show']);
        });
        Route::post('/contracts', [ContractController::class, 'store'])->middleware('permission:create_contracts');
        Route::put('/contracts/{contract}', [ContractController::class, 'update'])->middleware('permission:edit_contracts');
        Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->middleware('permission:delete_contracts');

        Route::middleware('permission:view_leaves')->group(function () {
            Route::get('/leaves/pending', [LeaveController::class, 'pending'])->middleware('permission:approve_leaves');
            Route::get('/leaves/balance/{employee}', [LeaveController::class, 'balance']);
            Route::get('/leaves', [LeaveController::class, 'index']);
            Route::get('/leaves/{leave}', [LeaveController::class, 'show']);
        });
        Route::post('/leaves', [LeaveController::class, 'store'])->middleware('permission:create_leaves');
        Route::put('/leaves/{leave}', [LeaveController::class, 'update'])->middleware('permission:edit_leaves');
        Route::delete('/leaves/{leave}', [LeaveController::class, 'destroy'])->middleware('permission:delete_leaves');
        Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->middleware('permission:approve_leaves');
        Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])->middleware('permission:approve_leaves');

        Route::middleware('permission:view_attendance')->prefix('attendances')->group(function () {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::get('/today', [AttendanceController::class, 'today']);
            Route::get('/stats', [AttendanceController::class, 'stats']);
            Route::get('/{employee}/history', [AttendanceController::class, 'history']);
            Route::get('/generate-qr', [AttendanceController::class, 'generateQR']);
            Route::post('/clock-in', [AttendanceController::class, 'clockIn']);
            Route::post('/clock-out', [AttendanceController::class, 'clockOut']);
            Route::post('/scan/{qrCode}', [AttendanceController::class, 'scanQR']);
        });

        Route::middleware('permission:view_documents')->group(function () {
            Route::get('/documents', [DocumentController::class, 'index']);
            Route::get('/documents/{document}', [DocumentController::class, 'show']);
            Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
        });
        Route::post('/documents', [DocumentController::class, 'store'])->middleware('permission:upload_documents');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->middleware('permission:delete_documents');

        Route::middleware('permission:view_payrolls')->prefix('payrolls')->group(function () {
            Route::get('/', [PayrollController::class, 'index']);
            Route::get('/stats', [PayrollController::class, 'stats']);
            Route::get('/{payroll}/download', [PayrollController::class, 'download']);
            Route::get('/{payroll}', [PayrollController::class, 'show']);
        });
        Route::post('/payrolls/process', [PayrollController::class, 'process'])->middleware('permission:process_payrolls');
        Route::post('/payrolls/{payroll}/pay', [PayrollController::class, 'pay'])->middleware('permission:pay_payrolls');

        Route::middleware('permission:view_recruitments')->prefix('recruitments')->group(function () {
            Route::get('/', [RecruitmentController::class, 'index']);
            Route::get('/stats', [RecruitmentController::class, 'stats']);
            Route::get('/{recruitment}', [RecruitmentController::class, 'show']);
        });
        Route::post('/recruitments', [RecruitmentController::class, 'store'])->middleware('permission:create_recruitments');
        Route::put('/recruitments/{recruitment}', [RecruitmentController::class, 'update'])->middleware('permission:edit_recruitments');
        Route::delete('/recruitments/{recruitment}', [RecruitmentController::class, 'destroy'])->middleware('permission:delete_recruitments');
        Route::post('/recruitments/{recruitment}/publish', [RecruitmentController::class, 'publish'])->middleware('permission:edit_recruitments');
        Route::post('/recruitments/{recruitment}/candidates', [RecruitmentController::class, 'addCandidate'])->middleware('permission:edit_recruitments');
        Route::put('/recruitments/{recruitment}/candidates/{candidate}', [RecruitmentController::class, 'updateCandidate'])->middleware('permission:edit_recruitments');

        Route::middleware('permission:view_trainings')->prefix('trainings')->group(function () {
            Route::get('/', [TrainingController::class, 'index']);
            Route::get('/stats', [TrainingController::class, 'stats']);
            Route::get('/{training}', [TrainingController::class, 'show']);
        });
        Route::post('/trainings', [TrainingController::class, 'store'])->middleware('permission:create_trainings');
        Route::put('/trainings/{training}', [TrainingController::class, 'update'])->middleware('permission:edit_trainings');
        Route::delete('/trainings/{training}', [TrainingController::class, 'destroy'])->middleware('permission:delete_trainings');
        Route::post('/trainings/{training}/enroll', [TrainingController::class, 'enroll'])->middleware('permission:view_trainings');
        Route::post('/trainings/{training}/complete', [TrainingController::class, 'complete'])->middleware('permission:edit_trainings');

        Route::middleware('permission:view_reports')->prefix('reports')->group(function () {
            Route::get('/employees', [ReportController::class, 'employees']);
            Route::get('/attendance', [ReportController::class, 'attendance']);
            Route::get('/payroll', [ReportController::class, 'payroll']);
            Route::get('/leaves', [ReportController::class, 'leaves']);
        });

        Route::middleware('permission:view_settings')->group(function () {
            Route::get('/settings', [SettingsController::class, 'index']);
            Route::put('/settings', [SettingsController::class, 'update']);
        });
        Route::put('/profile', [SettingsController::class, 'updateProfile']);
    });

    Route::prefix('admin')->middleware('role:super_admin')->group(function () {
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::get('/tenants/search', [TenantController::class, 'search']);
        Route::get('/tenants/export', [TenantController::class, 'export']);
        Route::post('/tenants/{tenant}/activate', [TenantController::class, 'activate']);
        Route::post('/tenants/{tenant}/deactivate', [TenantController::class, 'deactivate']);
        Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
        Route::put('/tenants/{tenant}', [TenantController::class, 'update']);
        Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy']);
    });
});
