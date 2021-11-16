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
    Route::post('lists', 'ListController@store');
    Route::post('lists/{user}/{list_id}', 'ListController@add');
    Route::get('lists', 'ListController@index');

    Route::get('users/{username}', 'UserController@show');
});
