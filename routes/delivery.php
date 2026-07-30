<?php

use App\Http\Controllers\SteadfastWebhookController;
use Illuminate\Support\Facades\Route;

// Server-to-server: no session, and exempt from CSRF via bootstrap/app.php.
Route::post('webhooks/steadfast', SteadfastWebhookController::class)
    ->name('webhooks.steadfast');
