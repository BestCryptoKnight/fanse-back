<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/ 

Route::get('/', function () {
    return config('app.name') . ' API version ' . config('app.version');
    // return 'hello world 112233  abc';   
});

Route::get('/password-reset', function (Request $request) {
    return redirect()->away(config('app.app_url') . $request->getPathInfo() . '?' . $request->getQueryString());
    // return 'hello world 445566000';
})->name('password.reset');
