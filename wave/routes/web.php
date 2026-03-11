<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Wave\Actions\Reset;

Route::impersonate();

// Additional Auth Routes
Route::get('logout', '\Wave\Http\Controllers\LogoutController@logout')->name('wave.logout');
// Route::get('user/verify/{verification_code}', '\Wave\Http\Controllers\Auth\RegisterController@verify')->name('verify');
// Route::post('register/complete', '\Wave\Http\Controllers\Auth\RegisterController@complete')->name('wave.register-complete');

Route::get('organization/invite/{organization}/accept', '\Wave\Http\Controllers\OrganizationInviteController@accept')
    ->name('organization.invite.accept');

Route::view('install', 'wave::install')->name('wave.install');

/********** File Download Route ***********/
Route::get('files/{file}/download', \Wave\Http\Controllers\FileDownloadController::class)
    ->name('files.download')
    ->middleware(['auth', 'signed']);

Route::group(['middleware' => 'auth'], function () {
    Route::redirect('settings', 'settings/profile')->name('settings');

    Route::post('notification/read/{id}', '\Wave\Http\Controllers\NotificationController@delete')->name('wave.notification.read');
    Route::post('changelog/read', '\Wave\Http\Controllers\ChangelogController@read')->name('changelog.read');

    /********** Checkout/Billing Routes ***********/
    Route::post('cancel', '\Wave\Http\Controllers\SubscriptionController@cancel')
        ->middleware('can-manage-billing')
        ->name('wave.cancel');

    Route::post('subscribe', '\Wave\Http\Controllers\SubscriptionController@subscribe')
        ->middleware('can-manage-billing')
        ->name('wave.subscribe');
    Route::post('switch-plans', '\Wave\Http\Controllers\SubscriptionController@switchPlans')
        ->middleware('can-manage-billing')
        ->name('wave.switch-plans');
    Route::post('settings/billing-context', '\Wave\Http\Controllers\SubscriptionController@setBillingContext')
        ->name('settings.billing-context');
});

Route::redirect('admin/login', '/auth/login');

// Reset sqlite database - only in local environment
if (app()->environment('local')) {
    Route::get('reset', Reset::class)->middleware('auth');
}

/***** Billing Routes *****/
Route::post('stripe/webhook', [\Laravel\Cashier\Http\Controllers\WebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');
Route::get('stripe/portal', '\Wave\Http\Controllers\Billing\Stripe@redirect_to_customer_portal')
    ->middleware(['auth', 'can-manage-billing'])
    ->name('stripe.portal');
Route::redirect('billing', 'settings/subscription')->name('billing');

try {
    // If no users are found, redirect to the installer or dummy page
    if (! User::first()) {
        Route::view('/', 'wave::welcome');
    }
} catch (QueryException $e) {
    // Handle the exception or log it if needed
}
