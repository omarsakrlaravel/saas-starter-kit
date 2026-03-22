<?php

use App\Http\Middleware\AccountStatusMiddleware;
use Illuminate\Http\Request;

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

Route::middleware(['auth:sanctum', AccountStatusMiddleware::class])->get('/user', function (Request $request) {
    return auth()->user();
});

Wave::api();
