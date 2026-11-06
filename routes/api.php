<?php

use App\Http\Controllers\Api\V1\AdminKioskDeviceController;
use App\Http\Controllers\Api\V1\AdminPrinterController;
use App\Http\Controllers\Api\V1\AdminReportController;
use App\Http\Controllers\Api\V1\AdminSectionController;
use App\Http\Controllers\Api\V1\AdminSettingsController;
use App\Http\Controllers\Api\V1\AdminStationController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConsoleController;
use App\Http\Controllers\Api\V1\DisplayController;
use App\Http\Controllers\Api\V1\OperatorSessionController;
use App\Http\Controllers\Api\V1\PrinterConfigController;
use App\Http\Controllers\Api\V1\PriorityProfileController;
use App\Http\Controllers\Api\V1\PublicKioskController;
use App\Http\Controllers\Api\V1\QueueSimulatorController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('sections', [SectionController::class, 'index'])->name('sections.index');
    Route::get('sections/{section:slug}', [SectionController::class, 'show'])->name('sections.show');
    Route::get('kiosks/{kioskDevice:token}', [PublicKioskController::class, 'show'])->name('kiosks.show');

    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');

    Route::get('display/snapshot', [DisplayController::class, 'snapshot'])->name('display.snapshot');

    Route::middleware(['auth', 'active'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::middleware('role:admin,supervisor,operator')->group(function (): void {
            Route::get('console/queue', [ConsoleController::class, 'queue'])->name('console.queue');
            Route::get('console/operators', [OperatorSessionController::class, 'operators'])->name('console.operators');
            Route::get('console/stations', [OperatorSessionController::class, 'stations'])->name('console.stations');
            Route::post('console/session', [OperatorSessionController::class, 'store'])->name('console.session.store');
            Route::get('console/session/{session}', [OperatorSessionController::class, 'show'])->name('console.session.show');
            Route::delete('console/session/{session}', [OperatorSessionController::class, 'close'])->name('console.session.close');
            Route::patch('console/session/{session}/station', [OperatorSessionController::class, 'changeStation'])->name('console.session.station');
            Route::post('console/tickets/next', [ConsoleController::class, 'next'])->name('console.next');
            Route::post('console/tickets/{ticket}/recall', [ConsoleController::class, 'recall'])->name('console.recall');
            Route::post('console/tickets/{ticket}/start', [ConsoleController::class, 'start'])->name('console.start');
            Route::post('console/tickets/{ticket}/finish', [ConsoleController::class, 'finish'])->name('console.finish');
            Route::post('console/tickets/{ticket}/transfer', [ConsoleController::class, 'transfer'])->name('console.transfer');
            Route::post('console/tickets/{ticket}/reprint', [ConsoleController::class, 'reprint'])->name('console.reprint');
            Route::post('console/tickets/{ticket}/print-failed', [ConsoleController::class, 'printFailed'])->name('console.print-failed');
            Route::get('console/printers', [PrinterConfigController::class, 'index'])->name('console.printers');
        });

        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
            Route::get('settings', [AdminSettingsController::class, 'show'])->name('settings.show');
            Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');

            Route::get('operators', [AdminUserController::class, 'operators'])->name('operators.index');
            Route::apiResource('users', AdminUserController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::get('sections', [AdminSectionController::class, 'index'])->name('sections.index');
            Route::put('sections/{section:slug}', [AdminSectionController::class, 'update'])->name('sections.update');
            Route::put('sections/{section:slug}/matrix', [AdminSectionController::class, 'updateMatrix'])->name('sections.matrix');
            Route::apiResource('stations', AdminStationController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::apiResource('kiosks', AdminKioskDeviceController::class)
                ->parameters(['kiosks' => 'kioskDevice'])
                ->only(['index', 'store', 'update', 'destroy']);
            Route::apiResource('printers', AdminPrinterController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

            Route::get('reports/summary', [AdminReportController::class, 'summary'])->name('reports.summary');
            Route::get('reports/tickets', [AdminReportController::class, 'tickets'])->name('reports.tickets');
            Route::get('reports/exports', [AdminReportController::class, 'exports'])->name('reports.exports.index');
            Route::post('reports/exports', [AdminReportController::class, 'storeExport'])->name('reports.exports.store');
            Route::get('reports/exports/{export}/download', [AdminReportController::class, 'download'])->name('reports.exports.download');
            Route::delete('reports/exports/{export}', [AdminReportController::class, 'destroyExport'])->name('reports.exports.destroy');

            Route::apiResource('priority-profiles', PriorityProfileController::class)
                ->parameters(['priority-profiles' => 'priorityProfile']);

            Route::apiResource('services', ServiceController::class);

            Route::post('queue/simulate', [QueueSimulatorController::class, 'simulate'])->name('queue.simulate');
        });
    });
});
