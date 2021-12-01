<?php

use Illuminate\Http\Request;
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
    Route::get('refresh', 'AuthController@refresh');
});

Route::get('/test', 'PaymentController@test');
Route::post('process/{gateway}', 'PaymentController@paymentProcess');

// dummy function
// Route::post('log', 'UserController@dolog');

// user
Route::middleware(['auth:sanctum'])->group(function () {

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
    Route::apiResource('media', 'MediaController')->only(['store', 'destroy']);

    Route::get('comments/{post}', 'CommentController@index');
    Route::post('comments/{post}', 'CommentController@store');
    Route::delete('comments/{comment}', 'CommentController@destroy');
    Route::post('comments/{comment}/like', 'CommentController@like');

    Route::post('profile/image/{type}', 'ProfileController@image');
    Route::post('profile', 'ProfileController@store');
    Route::post('profile/email', 'ProfileController@email');
    Route::post('profile/password', 'ProfileController@password');

    Route::apiResource('notifications', 'NotificationController')->only(['index']);
    Route::post('bookmarks/{post}', 'BookmarkController@add');
    Route::get('bookmarks', 'BookmarkController@index');
    Route::post('lists', 'ListController@store');
    Route::post('lists/{user}/{list_id}', 'ListController@add');
    Route::get('lists', 'ListController@index');
    Route::get('lists/user/{user}', 'ListController@indexUser');
    Route::get('lists/{id}', 'ListController@indexList');

    Route::get('users/{username}', 'UserController@show');

    Route::post('messages/{user}', 'MessageController@store');
    Route::get('messages/{user}', 'MessageController@indexChat');
    Route::get('messages', 'MessageController@index');
    Route::delete('messages/{user}', 'MessageController@destroy');

    Route::post('price', 'PaymentController@price');
    Route::post('price/bundle', 'PaymentController@bundleStore');
    Route::put('price/bundle/{bundle}', 'PaymentController@bundleUpdate');
    Route::delete('price/bundle/{bundle}', 'PaymentController@bundleDestroy');

    Route::post('payment', 'PaymentController@paymentStore');
});
