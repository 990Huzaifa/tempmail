<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TempMailController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');
    return 'Optimization cache cleared!';
});

Route::get('/run-job', function () {
    Artisan::call('app:rotate-mail-domain');
    return 'Optimization cache cleared!';
});



Route::post('signin-as-guest', [AuthController::class, 'guestLogin']);

Route::post('signup', [AuthController::class, 'signup']);
Route::post('signin', [AuthController::class, 'signin']);
Route::post('social',[AuthController::class,'socialLoginSignup']);
Route::post('account-check',[AuthController::class,'accountCheck']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::post('resend-code', [AuthController::class, 'resendCode']);

Route::post('/webhook/apple', [WebhookController::class, 'handleApple']);
Route::post('/webhook/google', [WebhookController::class, 'handleGoogle']);

Route::post('buy',[TestController::class, 'purchaseDomain']);
Route::post('search',[TestController::class, 'searchCheapDomain']);
Route::get('find/{id}',[TestController::class, 'getDomainInfo']);
Route::post('add',[TestController::class, 'addDomain']);
Route::post('add-account',[TestController::class, 'creatAccount']);


Route::get('list',[TestController::class, 'getList']);

Route::get('mailboxes',[WebhookController::class, 'mailboxes']);
Route::post('mail/webhook',[WebhookController::class, 'webhook']);
Route::post('attachment/{id}',[WebhookController::class, 'attachmentWebhook']);



Route::get('domain-list',[TempMailController::class, 'domainlist']); 
Route::middleware(['auth:sanctum'])->group(function () {

    Route::put('generate-mail',[TempMailController::class, 'generateMail']);
    Route::post('setup-forwarding',[TempMailController::class, 'setupForwarding']);
    Route::get('forwarding-list',[TempMailController::class, 'forwardingList']);
    Route::post('compose-mail',[TempMailController::class, 'composeMail']);

    Route::get('mailbox',[TempMailController::class, 'mailBox']);
    Route::get('sentbox',[TempMailController::class, 'sentBox']);
    Route::get('email-list',[TempMailController::class, 'emailList']);
    Route::get('activate-mailbox/{id}',[TempMailController::class, 'activateMailboxes']);
    Route::get('view-mail/{id}',[TempMailController::class, 'show']);
    Route::get('read-mail/{id}',[TempMailController::class, 'readMail']);
    Route::delete('delete-mailbox/{id}',[TempMailController::class, 'deleteMailbox']);
    Route::delete('delete-mail/{id}',[TempMailController::class, 'deleteMail']);

    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'profile');
        Route::post('/profile', 'updateProfile');
        Route::get('/check-plan', 'checkPlan');

        route::delete('/delete-account', 'deleteAccount');
    });

    Route::post('feedback', [FeedbackController::class, 'store']);

});
