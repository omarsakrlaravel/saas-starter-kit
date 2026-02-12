<?php

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

use Illuminate\Http\Request;
use Wave\Facades\Wave;

// Email verification notice (overrides the vendor Folio page)
Route::get('auth/verify', function (Request $request) {
    if ($request->user()?->hasVerifiedEmail()) {
        return redirect('/');
    }

    return view('auth.verify');
})->middleware('auth')->name('verification.notice');

Route::post('auth/verify/resend', function (Request $request) {
    if ($request->user()->hasVerifiedEmail()) {
        return redirect('/');
    }

    $request->user()->sendEmailVerificationNotification();
    session()->flash('resent');

    return back();
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// Wave routes
Wave::routes();
