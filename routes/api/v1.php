<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// guest
Route::prefix('auth')->group(function () {
    Route::post('signup', 'AuthController@signup');
    Route::post('login', 'AuthController@login');
});

Route::post('sociallogin/{provider}', 'AuthController@socialSignup');
Route::get('auth/{provider}/callback', 'OutController@index')->where('provider', '.*');
Route::post('process/{gateway}', 'PaymentController@process');
Route::get('users/guest/{username}', 'UserController@show');
Route::get('posts/guest/{user}', 'PostController@user');

// check online status
Route::get('userstatus', 'UserController@userStatus');
Route::get('status', 'UserController@status');
Route::get('live-status/{id}', 'UserController@liveStatus');

Broadcast::routes(['middleware' => ['auth:sanctum', 'abilities:user']]);

// dummy function
Route::post('log', 'UserController@dolog');

// user
Route::middleware(['auth:sanctum', 'abilities:user'])->group(function () {

    // auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', 'AuthController@logout');
        Route::get('me', 'AuthController@me');
    });

    // post
    Route::apiResource('posts', 'PostController');
    Route::post('posts/{post}/{poll}/vote', 'PostController@vote');
    Route::post('posts/{post}/like', 'PostController@like');
    Route::get('posts/user/{user}', 'PostController@user');
    // media
    Route::apiResource('media', 'MediaController')->only(['store', 'destroy']);

    // comments
    Route::get('comments/{post}', 'CommentController@index');
    Route::post('comments/{post}', 'CommentController@store');
    Route::delete('comments/{comment}', 'CommentController@destroy');
    Route::post('comments/{comment}/like', 'CommentController@like');

    // profile
    Route::post('profile/image/{type}', 'ProfileController@image');
    Route::post('profile', 'ProfileController@store');
    Route::post('profile/email', 'ProfileController@email');
    Route::post('profile/password', 'ProfileController@password');

    // payouts
    Route::post('payouts/verification', 'PayoutController@verificationStore');
    Route::get('payouts/verification', 'PayoutController@verificationShow');
    Route::get('payouts', 'PayoutController@index');
    Route::get('earnings', 'PayoutController@earningsIndex');
    Route::post('payouts', 'PayoutController@store');
    Route::get('payouts/info', 'PayoutController@info');
    Route::post('payouts/method', 'PayoutController@methodStore');
    Route::put('payouts/method/{payout_method}', 'PayoutController@methodUpdate');

    // bookmarks and lists
    Route::post('bookmarks/{post}', 'BookmarkController@add');
    Route::get('bookmarks', 'BookmarkController@index');
    Route::post('lists', 'ListController@store');
    Route::post('lists/{user}/{list_id}', 'ListController@add');
    Route::get('lists', 'ListController@index');
    Route::get('lists/user/{user}', 'ListController@indexUser');
    Route::get('lists/message', 'ListController@indexMessage');
    Route::get('lists/{id}', 'ListController@indexList');

    // users
    Route::get('users', 'UserController@suggestions');
    Route::get('users/{username}', 'UserController@show');

    // subscriptions
    Route::get('subscriptions', 'UserController@subscriptions');
    Route::delete('subscriptions/{user}', 'UserController@subscriptionDestroy');
    Route::post('subscriptions/{user}', 'UserController@resubscription');
    Route::post('subscribe/{user}', 'UserController@subscribe');

    // messages
    Route::post('messages', 'MessageController@storeMass');
    Route::post('messages/{user}', 'MessageController@store');
    Route::get('messages/{user}', 'MessageController@indexChat');
    Route::get('messages', 'MessageController@index');
    Route::delete('messages/{user}', 'MessageController@destroy');

    // payments
    Route::get('gateways', 'PaymentController@gateways');
    Route::post('price', 'PaymentController@price');
    Route::post('price/bundle', 'PaymentController@bundleStore');
    Route::put('price/bundle/{bundle}', 'PaymentController@bundleUpdate');
    Route::delete('price/bundle/{bundle}', 'PaymentController@bundleDestroy');
    Route::post('payments', 'PaymentController@store');
    Route::get('payments', 'PaymentController@index');
    Route::get('payments/method', 'PaymentController@methodIndex');
    Route::post('payments/method', 'PaymentController@methodStore');
    Route::put('payments/method/{payment_method}', 'PaymentController@methodMain');
    Route::delete('payments/method/{payment_method}', 'PaymentController@methodDestroy');

    // notifications
    Route::get('notifications', 'NotificationController@index');

    // stripe
    Route::post('stripe/intent', 'StripeController@intent');
});
