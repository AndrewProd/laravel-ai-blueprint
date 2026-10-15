<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('customers', CustomerController::class)->only(['index', 'store', 'show']);

Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('admin.customers.show');
});

foreach (['a', 'b'] as $page) {
    Route::view("/pages/{$page}", 'page');
}
