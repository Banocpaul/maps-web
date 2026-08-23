<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FireHydrantController;
use App\Http\Controllers\FireIncidentController;
use App\Http\Controllers\FloodDatasetController;
use App\Http\Controllers\FloodOperationController;
use App\Http\Controllers\GisMapController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\OperationalRecordController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
*/

Route::get('/public-portal', function () {
    return view('public.index');
})->name('public.portal');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLoginForm'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Flood Prediction
    |--------------------------------------------------------------------------
    */

    Route::get('/prediction', [PredictionController::class, 'index'])
        ->middleware('permission:prediction.view')
        ->name('prediction.index');

    Route::post('/prediction/run', [PredictionController::class, 'run'])
        ->middleware([
            'permission:prediction.run',
            'throttle:30,1',
        ])
        ->name('prediction.run');

    Route::post(
        '/prediction/citywide',
        [PredictionController::class, 'citywide']
    )
        ->middleware([
            'permission:prediction.run',
            'throttle:10,1',
        ])
        ->name('prediction.citywide');

    /*
    |--------------------------------------------------------------------------
    | Flood Operations Center
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/flood-operation',
        [FloodOperationController::class, 'index']
    )
        ->middleware('permission:prediction.view')
        ->name('flood-operation.index');

    /*
    |--------------------------------------------------------------------------
    | Flood Dataset Management
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/flood-operation/dataset',
        [FloodDatasetController::class, 'index']
    )
        ->middleware('permission:prediction.view')
        ->name('flood-dataset.index');

    Route::post(
        '/flood-operation/dataset',
        [FloodDatasetController::class, 'store']
    )
        ->middleware([
            'permission:flood.create',
            'throttle:30,1',
        ])
        ->name('flood-dataset.store');

    Route::get(
        '/flood-operation/dataset/{floodTrainingRecord}',
        [FloodDatasetController::class, 'show']
    )
        ->middleware('permission:prediction.view')
        ->name('flood-dataset.show');

    Route::put(
        '/flood-operation/dataset/{floodTrainingRecord}',
        [FloodDatasetController::class, 'update']
    )
        ->middleware([
            'permission:flood.edit',
            'throttle:30,1',
        ])
        ->name('flood-dataset.update');

    Route::patch(
        '/flood-operation/dataset/{floodTrainingRecord}/training-status',
        [FloodDatasetController::class, 'toggleTraining']
    )
        ->middleware([
            'permission:prediction.data.manage',
            'throttle:30,1',
        ])
        ->name('flood-dataset.training-status');

    Route::delete(
        '/flood-operation/dataset/{floodTrainingRecord}',
        [FloodDatasetController::class, 'destroy']
    )
        ->middleware([
            'permission:flood.delete',
            'throttle:20,1',
        ])
        ->name('flood-dataset.destroy');

    /*
    |--------------------------------------------------------------------------
    | Live Weather
    |--------------------------------------------------------------------------
    */

    Route::get('/weather/live', [WeatherController::class, 'live'])
        ->middleware([
            'permission:prediction.view',
            'throttle:30,1',
        ])
        ->name('weather.live');

    /*
    |--------------------------------------------------------------------------
    | Fire Incidents
    |--------------------------------------------------------------------------
    */

    Route::resource('fire-incidents', FireIncidentController::class)
        ->only(['index', 'show'])
        ->middleware('permission:fire.view');
    Route::resource('fire-incidents', FireIncidentController::class)
        ->only(['create', 'store'])
        ->middleware('permission:fire.create');
    Route::resource('fire-incidents', FireIncidentController::class)
        ->only(['edit', 'update'])
        ->middleware('permission:fire.edit');
    Route::delete('/fire-incidents/{fire_incident}', [FireIncidentController::class, 'destroy'])
        ->middleware('permission:fire.delete')
        ->name('fire-incidents.destroy');

    /*
    |--------------------------------------------------------------------------
    | Fire Hydrants
    |--------------------------------------------------------------------------
    */

    Route::resource('fire-hydrants', FireHydrantController::class)
        ->only(['index', 'show'])
        ->middleware('permission:fire.view');
    Route::resource('fire-hydrants', FireHydrantController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('permission:hydrants.manage');

    /* Operational database browser and controlled CSV exports. */
    Route::get('/operational-records', [OperationalRecordController::class, 'index'])
        ->middleware('permission:records.view')
        ->name('operational-records.index');
    Route::get('/operational-records/export', [OperationalRecordController::class, 'export'])
        ->middleware(['permission:records.export', 'throttle:10,1'])
        ->name('operational-records.export');
    Route::get('/operational-records/flood/create', [OperationalRecordController::class, 'createFlood'])
        ->middleware('permission:flood.create')->name('operational-records.flood.create');
    Route::post('/operational-records/flood', [OperationalRecordController::class, 'storeFlood'])
        ->middleware(['permission:flood.create', 'throttle:30,1'])->name('operational-records.flood.store');
    Route::get('/operational-records/flood/{id}/edit', [OperationalRecordController::class, 'editFlood'])
        ->whereNumber('id')->middleware('permission:flood.edit')->name('operational-records.flood.edit');
    Route::put('/operational-records/flood/{id}', [OperationalRecordController::class, 'updateFlood'])
        ->whereNumber('id')->middleware(['permission:flood.edit', 'throttle:30,1'])->name('operational-records.flood.update');
    Route::delete('/operational-records/flood/{id}', [OperationalRecordController::class, 'destroyFlood'])
        ->whereNumber('id')->middleware(['permission:flood.delete', 'throttle:20,1'])->name('operational-records.flood.destroy');

    /*
    |--------------------------------------------------------------------------
    | GIS Mapping
    |--------------------------------------------------------------------------
    */

    Route::get('/gis-mapping', [GisMapController::class, 'index'])
        ->middleware('permission:gis.view')
        ->name('gis.index');

    Route::get('/gis-mapping/data', [GisMapController::class, 'data'])
        ->middleware('permission:gis.view')
        ->name('gis.data');

    Route::get(
        '/gis-mapping/nearest-hydrants',
        [GisMapController::class, 'nearestHydrants']
    )
        ->middleware('permission:gis.view')
        ->name('gis.nearest-hydrants');

    /*
    |--------------------------------------------------------------------------
    | SMS Center
    |--------------------------------------------------------------------------
    */

    Route::get('/sms-center', [SmsController::class, 'index'])
        ->middleware('permission:sms.view')
        ->name('sms.index');

    /*
    |--------------------------------------------------------------------------
    | SMS Recipient Management
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/sms-center/recipients',
        [SmsController::class, 'storeRecipient']
    )
        ->middleware('permission:sms.recipients.manage')
        ->name('sms.recipients.store');

    Route::put(
        '/sms-center/recipients/{recipient}',
        [SmsController::class, 'updateRecipient']
    )
        ->middleware('permission:sms.recipients.manage')
        ->name('sms.recipients.update');

    Route::patch(
        '/sms-center/recipients/{recipient}/status',
        [SmsController::class, 'toggleRecipient']
    )
        ->middleware('permission:sms.recipients.manage')
        ->name('sms.recipients.status');

    Route::delete(
        '/sms-center/recipients/{recipient}',
        [SmsController::class, 'destroyRecipient']
    )
        ->middleware('permission:sms.recipients.manage')
        ->name('sms.recipients.destroy');

    /*
    |--------------------------------------------------------------------------
    | SMS Automation Rules
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/sms-center/automation-rules',
        [SmsController::class, 'storeAutomationRule']
    )
        ->middleware('permission:sms.automation.manage')
        ->name('sms.automation.store');

    Route::put(
        '/sms-center/automation-rules/{rule}',
        [SmsController::class, 'updateAutomationRule']
    )
        ->middleware('permission:sms.automation.manage')
        ->name('sms.automation.update');

    Route::patch(
        '/sms-center/automation-rules/{rule}/status',
        [SmsController::class, 'toggleAutomationRule']
    )
        ->middleware('permission:sms.automation.manage')
        ->name('sms.automation.status');

    Route::delete(
        '/sms-center/automation-rules/{rule}',
        [SmsController::class, 'destroyAutomationRule']
    )
        ->middleware('permission:sms.automation.manage')
        ->name('sms.automation.destroy');

    /*
    |--------------------------------------------------------------------------
    | Manual and Test SMS
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/sms-center/send',
        [SmsController::class, 'sendManual']
    )
        ->middleware([
            'permission:sms.send',
            'throttle:10,1',
        ])
        ->name('sms.send');

    Route::post(
        '/sms-center/test',
        [SmsController::class, 'sendTest']
    )
        ->middleware([
            'permission:sms.send',
            'throttle:5,1',
        ])
        ->name('sms.test');

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::get(
            '/users',
            [UserManagementController::class, 'index']
        )->name('users.index');

        Route::get(
            '/users/create',
            [UserManagementController::class, 'create']
        )->name('users.create');

        Route::post(
            '/users',
            [UserManagementController::class, 'store']
        )->name('users.store');

        Route::get(
            '/users/{user}/edit',
            [UserManagementController::class, 'edit']
        )->name('users.edit');

        Route::put(
            '/users/{user}',
            [UserManagementController::class, 'update']
        )->name('users.update');

        Route::patch(
            '/users/{user}/status',
            [UserManagementController::class, 'updateStatus']
        )->name('users.status');

        Route::patch(
            '/users/{user}/reset-password',
            [UserManagementController::class, 'resetPassword']
        )->name('users.reset-password');

        Route::delete(
            '/users/{user}',
            [UserManagementController::class, 'destroy']
        )->name('users.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Administrator-only Activity Logs
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/activity-logs',
        [ActivityLogController::class, 'index']
    )
        ->middleware([
            'admin',
            'permission:activity-logs.view',
        ])
        ->name('activity-logs.index');

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');
});
