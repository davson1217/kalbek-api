<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', fn () => [
        'status' => 'ok',
        'service' => config('app.name'),
    ])->name('health');

    Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user())
        ->name('user');
});
