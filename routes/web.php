<?php

use Illuminate\Support\Facades\Route;
use Pencilled\Statamic\Http\Controllers\WebhookController;

// The webhook is authenticated by its HMAC signature, so CSRF verification
// is excluded. Every class name the framework has used is listed so this
// works across the Laravel versions supported by Statamic 5 and 6.
Route::post('/pencilled/webhook', WebhookController::class)
    ->withoutMiddleware([
        'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
        'App\Http\Middleware\VerifyCsrfToken',
    ])
    ->name('pencilled.webhook');
