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
Route::middleware(['auth:api'])->group(function () {

    // auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', 'AuthController@logout');
        Route::get('me', 'AuthController@me');
    });
});
