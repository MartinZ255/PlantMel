<?php

use App\Http\Controllers\Settings\AiImportController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::get('settings', function () {
        return Inertia::render('settings/Index');
    })->name('settings.index');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    // E18: eigener Gemini-API-Schlüssel für den KI-Import
    Route::get('settings/ki-import', [AiImportController::class, 'edit'])->name('settings.ai.edit');

    Route::put('settings/ki-import', [AiImportController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('settings.ai.update');

    Route::delete('settings/ki-import', [AiImportController::class, 'destroy'])->name('settings.ai.destroy');

    Route::post('settings/ki-import/pruefen', [AiImportController::class, 'check'])
        ->middleware('throttle:6,1')
        ->name('settings.ai.check');

    // F-20 (Slice 3): `settings/appearance` (Hell/Dunkel) und
    // `settings/two-factor` (2FA) sind bewusst entfallen.
});
