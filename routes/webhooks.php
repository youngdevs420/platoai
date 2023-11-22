<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Carbon\Carbon;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Gateways\StripeController;
use App\Http\Controllers\Gateways\PaypalController;
use App\Http\Controllers\Gateways\YokassaController;
use App\Http\Controllers\Gateways\TwoCheckoutController;
use App\Http\Controllers\Gateways\IyzicoController;
use App\Http\Controllers\Gateways\PaystackController;

Route::group(['prefix' => LaravelLocalization::setLocale(), 'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]], function() {

    Route::prefix('webhooks')->name('webhooks.')->group(function () {
        Route::post('/paypal', [PaypalController::class, 'handleWebhook']);
        Route::post('/stripe', [StripeController::class, 'handleWebhook']);
        Route::post('/yokassa', [YokassaController::class, 'handleWebhook']);
        Route::match(['get', 'post'], '/twocheckout', [TwoCheckoutController::class, 'handleWebhook']);
        Route::post('/iyzico', [IyzicoController::class, 'handleWebhook']);
        Route::post('/paystack', [PaystackController::class, 'handleWebhook']);

        Route::get('/simulate', [PaypalController::class, 'simulateWebhookEvent']); // This is specific to Paypal
    });

});

