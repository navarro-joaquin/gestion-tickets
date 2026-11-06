<?php

use App\Http\Controllers\Api\V1\SectionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('sections', [SectionController::class, 'index'])->name('sections.index');
    Route::get('sections/{section:slug}', [SectionController::class, 'show'])->name('sections.show');
});
