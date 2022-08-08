<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', 'AuthController@login');
});

Route::middleware(['auth:sanctum', 'abilities:admin'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', 'AuthController@logout');
        Route::get('me', 'AuthController@me');
    });  
    
    Route::get('users/list/{type?}', 'UserController@index');
    Route::post('users/verification/approve/{user}', 'UserController@verificationApprove');
    Route::post('users/verification/decline/{user}', 'UserController@verificationDecline');
    Route::resource('users', 'UserController')->except(['index']);

    Route::get('subscriptions/list/{type?}', 'SubscriptionController@index');
    Route::post('subscriptions/{subscription}', 'SubscriptionController@resume');
    Route::put('subscriptions/{subscription}', 'SubscriptionController@cancel');
    Route::delete('subscriptions/{subscription}', 'SubscriptionController@destroy');

    Route::get('payments/list/{type?}', 'PaymentController@index');
    Route::put('payments/{payment}', 'PaymentController@update');
    Route::delete('payments/{payment}', 'PaymentController@destroy');

    Route::get('stats', 'StatsController@index');

    Route::get('payouts/batches/file/{payout_batch}', 'PayoutController@batchFile');
    Route::get('payouts/list/batches', 'PayoutController@batchIndex');
    Route::post('payouts/batches', 'PayoutController@batchStore');
    Route::put('payouts/batches/{payout_batch}', 'PayoutController@batchMark');
    Route::delete('payouts/batches/{payout_batch}', 'PayoutController@batchDestroy');

    Route::get('payouts/list/{type?}', 'PayoutController@index');
    Route::put('payouts/{payout}', 'PayoutController@mark');
    Route::delete('payouts/{payout}', 'PayoutController@destroy');
});
