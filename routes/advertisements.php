<?php

Route::prefix('api/v1')->name('api.')->group(function () {
    Route::prefix('advertisements')->name('second_screen.')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\v1\second_screen\AdvertisementController::class, 'index'])->name('advertisements.index');
    });

    // Media streaming endpoint with proper byte-range support for videos
    Route::get('/media/stream/{filename}', [\App\Http\Controllers\API\v1\second_screen\MediaStreamController::class, 'stream'])
        ->name('media.stream')
        ->where('filename', '.*');
});
