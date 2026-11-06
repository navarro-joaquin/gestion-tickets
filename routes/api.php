<?php

use App\Http\Controllers\Api\V1\AdminSettingsController;
use App\Http\Controllers\Api\V1\ConsoleController;
use App\Http\Controllers\Api\V1\DisplayController;
use App\Http\Controllers\Api\V1\OperatorSessionController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('sections', [SectionController::class, 'index'])->name('sections.index');
    Route::get('sections/{section:slug}', [SectionController::class, 'show'])->name('sections.show');

    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');

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

    Route::get('display/snapshot', [DisplayController::class, 'snapshot'])->name('display.snapshot');

    Route::get('admin/settings', [AdminSettingsController::class, 'show'])->name('admin.settings.show');
    Route::put('admin/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');
});
