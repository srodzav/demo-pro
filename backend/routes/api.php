<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentPlanController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\SettingsController;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/appointments/public', [AppointmentController::class, 'storePublic']);
Route::get('/public/config', [SettingsController::class, 'publicConfig']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // Patients
    Route::middleware('permission:patients.manage')->group(function () {
        Route::get('/patients/search', [PatientController::class, 'search']);
        Route::get('/patients/find-by-email', [PatientController::class, 'findByEmail']);
        Route::post('/patients/find-or-create', [PatientController::class, 'findOrCreate']);
        Route::apiResource('patients', PatientController::class);
    });
    
    // Appointments CRUD
    Route::middleware('permission:appointments.manage')->group(function () {
        Route::apiResource('appointments', AppointmentController::class);
        
        // Additional appointment actions
        Route::get('/appointments-weekly-calendar', [AppointmentController::class, 'weeklyCalendar']);
        Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
        Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
    });

    // Medical Records
    Route::apiResource('medical-records', MedicalRecordController::class)
        ->middleware('permission:medical_records.manage');

    // Payments
    Route::apiResource('payments', PaymentController::class)
        ->middleware('permission:payments.manage');

    // Payment Plans
    Route::middleware('permission:payments.manage')->group(function () {
        Route::apiResource('payment-plans', PaymentPlanController::class);
        Route::post('/payment-plans/{id}/cancel', [PaymentPlanController::class, 'cancel']);
    });

    // Statistics and Reports
    Route::middleware('permission:statistics.view')->group(function () {
        Route::get('/statistics/dashboard', [StatisticsController::class, 'dashboard']);
        Route::get('/statistics/monthly-income', [StatisticsController::class, 'monthlyIncome']);
        Route::get('/statistics/daily-income', [StatisticsController::class, 'dailyIncome']);
        Route::get('/statistics/treatment-stats', [StatisticsController::class, 'treatmentStats']);
        Route::get('/statistics/appointment-stats', [StatisticsController::class, 'appointmentStats']);
        Route::get('/statistics/payment-method-stats', [StatisticsController::class, 'paymentMethodStats']);
        Route::get('/statistics/accounts-receivable', [StatisticsController::class, 'accountsReceivable']);
        Route::get('/statistics/top-patients', [StatisticsController::class, 'topPatientsBySpending']);
        Route::get('/statistics/peak-hours', [StatisticsController::class, 'peakHours']);
        Route::get('/statistics/peak-days', [StatisticsController::class, 'peakDays']);
        Route::get('/statistics/financial-report', [StatisticsController::class, 'financialReport']);
    });

    // Settings
    Route::prefix('settings')->middleware('permission:settings.manage')->group(function () {
        Route::get('/', [SettingsController::class, 'overview']);
        Route::put('/clinic', [SettingsController::class, 'updateClinic']);
        Route::post('/clinic/logo', [SettingsController::class, 'uploadClinicLogo']);
        Route::put('/app', [SettingsController::class, 'updateAppSettings']);
        Route::get('/users', [SettingsController::class, 'users']);
        Route::post('/users', [SettingsController::class, 'storeUser']);
        Route::put('/users/{user}', [SettingsController::class, 'updateUser']);
        Route::get('/roles', [SettingsController::class, 'roles']);
        Route::post('/roles', [SettingsController::class, 'storeRole']);
        Route::put('/roles/{role}', [SettingsController::class, 'updateRole']);
        Route::get('/catalogs', [SettingsController::class, 'catalogs']);
        Route::post('/catalogs', [SettingsController::class, 'storeCatalog']);
        Route::put('/catalogs/{catalogItem}', [SettingsController::class, 'updateCatalog']);
        Route::delete('/catalogs/{catalogItem}', [SettingsController::class, 'destroyCatalog']);
    });
});
