<?php

use App\Http\Controllers\IncidentReportReviewController;
use App\Http\Controllers\PublicIncidentReportController;
use Illuminate\Support\Facades\Route;

Route::get('/public-portal/report-incident', [PublicIncidentReportController::class, 'create'])
    ->middleware(['auth', 'resident', 'throttle:60,1,public-report-form'])->name('public.incident-reports.create');
Route::post('/public-portal/report-incident', [PublicIncidentReportController::class, 'store'])
    ->middleware(['auth', 'resident', 'throttle:5,1,public-report-submit'])->name('public.incident-reports.store');

Route::middleware(['auth', 'permission:public-submissions.view'])
    ->prefix('incident-reports')->name('public-submissions.')->group(function (): void {
        Route::get('/', [IncidentReportReviewController::class, 'index'])->name('index');
        Route::get('/{publicReport}', [IncidentReportReviewController::class, 'show'])->name('show');
        Route::get('/{publicReport}/photo', [IncidentReportReviewController::class, 'photo'])->name('photo');
        Route::post('/{publicReport}/validate', [IncidentReportReviewController::class, 'validateReport'])
            ->middleware('throttle:30,1,incident-report-review')->name('validate');
        Route::post('/{publicReport}/reject', [IncidentReportReviewController::class, 'reject'])
            ->middleware('throttle:30,1,incident-report-review')->name('reject');
        Route::post('/{publicReport}/publish', [IncidentReportReviewController::class, 'publish'])
            ->middleware('throttle:30,1,incident-report-review')->name('publish');
    });
