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

use App\Actions\Reset;
use App\Http\Controllers\AccountRestrictedController;
use App\Http\Controllers\Billing\Stripe;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\FileDownloadController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationInviteController;
use App\Http\Controllers\SubscriptionController;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\WebhookController;

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

Route::get('account/restricted', [AccountRestrictedController::class, 'show'])->name('account.restricted');
Route::get('data-export', function () {
    return redirect()->route('settings.export');
})->name('account.data-export');

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
*/

Route::impersonate();

// Auth
Route::post('logout', [LogoutController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Organization invites
Route::get('organization/invite/{organization}/accept', [OrganizationInviteController::class, 'accept'])
    ->name('organization.invite.accept');

// Installer
Route::view('install', 'wave.install')->name('install');

// File downloads
Route::get('files/{file}/download', FileDownloadController::class)
    ->name('files.download')
    ->middleware(['auth', 'signed']);

// Authenticated routes
Route::group(['middleware' => 'auth'], function () {
    Route::redirect('settings', 'settings/profile')->name('settings');

    Route::post('notification/read/{id}', [NotificationController::class, 'delete'])->name('notification.read');
    Route::post('changelog/read', [ChangelogController::class, 'read'])->name('changelog.read');

    // Subscription management
    Route::post('cancel', [SubscriptionController::class, 'cancel'])
        ->middleware('can-manage-billing')
        ->name('subscription.cancel');

    Route::post('subscribe', [SubscriptionController::class, 'subscribe'])
        ->middleware('can-manage-billing')
        ->name('subscription.subscribe');

    Route::post('switch-plans', [SubscriptionController::class, 'switchPlans'])
        ->middleware('can-manage-billing')
        ->name('subscription.switch-plans');

    Route::post('settings/billing-context', [SubscriptionController::class, 'setBillingContext'])
        ->name('settings.billing-context');
});

// Admin login redirect
Route::redirect('admin/login', '/auth/login');

// Reset sqlite database - only in local environment
if (app()->environment('local')) {
    Route::get('reset', Reset::class)->middleware('auth');
}

// Billing / Stripe
Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');
Route::get('stripe/portal', [Stripe::class, 'redirect_to_customer_portal'])
    ->middleware(['auth', 'can-manage-billing'])
    ->name('stripe.portal');
Route::redirect('billing', 'settings/subscription')->name('billing');

// Welcome page fallback when no users exist
try {
    if (! User::first()) {
        Route::view('/', 'wave.welcome');
    }
} catch (QueryException $e) {
    // Handle the exception or log it if needed
}
