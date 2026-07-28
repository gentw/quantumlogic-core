<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AlarmAlertController;
use App\Http\Controllers\Api\AuthenticationController;
use App\Http\Controllers\Api\BankTransferController;
use App\Http\Controllers\Api\BillingPayPalController;
use App\Http\Controllers\Api\ClientBillingController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientPaymentController;
use App\Http\Controllers\Api\FirebaseController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentReconciliationController;
use App\Http\Controllers\Api\PayPalController;
use App\Http\Controllers\Api\PayPalWebhookController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
// use \App\Http\Controllers\Api\CodeCheckController;
// use \App\Http\Controllers\Api\ResetPasswordController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DomainController;
use Illuminate\Support\Facades\Route;

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
Route::group(['namespace' => 'Api', 'prefix' => 'v1'], function () {
    Route::post('login', [AuthenticationController::class, 'store']);
    Route::post('verify-otp', [AuthenticationController::class, 'verifyOtp']);
    Route::post('logout', [AuthenticationController::class, 'destroy'])->middleware('auth:api');
    Route::post('token/refresh', [TokenController::class, 'refresh']);
    Route::post('register-client-email', [AuthenticationController::class, 'registerClientEmail']);
    Route::post('register-new-client', [AuthenticationController::class, 'registerNewClient']);
    Route::post('register_client', [AuthenticationController::class, 'registerNewClientFromWeb']);

    Route::post('register_client2', [AuthenticationController::class, 'registerClient']);

    // //forgot password
    Route::post('password/email', [ForgotPasswordController::class, 'forgotPassword'])->name('password.reset');
    Route::post('password/token/check', [ForgotPasswordController::class, 'checkToken']);
    Route::post('password/reset', [ForgotPasswordController::class, 'resetPassword']);
});

Route::group([
    'namespace' => 'Api',
    'prefix' => 'v1',
    'middleware' => 'auth:api',
], function () {
    //incoming alarms — dormant security module, see docs/modules/security/README.md
    if (config('features.security')) {
        Route::get('fetchAlarms', [AlarmAlertController::class, 'fetchAlarms']);
        Route::get('fetchAlarmsForAgents', [AlarmAlertController::class, 'fetchAlarmsForAgents']);
        Route::post('/alarm/{alarm}/respondToAlarm', [AlarmAlertController::class, 'respondToAlarm']);
        Route::post('/client/alarm/{alarm}/logs', [AlarmAlertController::class, 'fetchAlarmLogsByAlarm']);

        Route::post('/alarm/{alarm}/changeStatusByAgent', [AlarmAlertController::class, 'changeStatusByAgent'])->middleware('agent');
        Route::post('/alarm/{alarm}/logs', [AlarmAlertController::class, 'fetchAlarmLogsForAgents'])->middleware('agent');
    }

    // #Chat
    Route::post('/chat/checkAgentStatus', [ChatController::class, 'checkAgentStatus']);
    Route::post('/chat/sendMessage', [ChatController::class, 'sendMessage']);
    Route::post('/chat/assignAgentToClient', [ChatController::class, 'assignAgentToClient'])->middleware('agent');
    Route::post('/chat/fetchMessagesByClient', [ChatController::class, 'fetchMessagesByClient']);
    Route::post('/chat/fetchMessagesByChatIdAndClient', [ChatController::class, 'fetchMessagesByChatIdAndClient']);
    Route::post('/chat/clientSwitchLiveOff', [ChatController::class, 'clientSwitchLiveOff']);

    // #Notifications
    Route::post('/notifications/fetch', [NotificationController::class, 'fetchNotifications']);
    Route::post('/notifications/readUnread', [NotificationController::class, 'readNotification']);

    // #User
    Route::post('/user/preferences/store', [UserController::class, 'storeUserPreferences']);
    Route::get('/user/preferences/fetch', [UserController::class, 'fetchUserPreferences']);

    Route::post('/user/profile/updateUserRequest', [UserController::class, 'updateUserRequest']);

    // //add this later
    // // Route::post('/user/profile/{user}/approveUpdateUserRequest', [UserController::class, 'approveUpdateUserRequest']);

    Route::get('/user/profile/{user}/showUserRequestDataByUserId', [UserController::class, 'showUserRequestDataByUserId']);
    Route::get('/user/profile/{user}/showUserRequestData', [UserController::class, 'showUserRequestDataByUserId']);
    Route::post('/user/profile/updatePassword', [UserController::class, 'updatePassword']);

    Route::post('/user/profile/{user}/approveUpdateUserRequest', [UserController::class, 'approveUpdateUserRequest']);
    Route::post('/user/profile/{user}/declineUpdateUserRequest', [UserController::class, 'declineUpdateUserRequest']);

    Route::post('/user/fetchAuthUserData', [UserController::class, 'fetchAuthUserData']);

    Route::post('/firebase/registerToken', [FirebaseController::class, 'registerToken']);
    Route::post('/firebase/unRegisterToken', [FirebaseController::class, 'unRegisterToken']);
    Route::post('/firebase/notification', [FirebaseController::class, 'notification']);
    Route::get('/client/fetchClients', [ClientController::class, 'fetchClients']);

    Route::post('/client/profile', [UserController::class, 'clientProfile'])->middleware('check-subscription');

    Route::post('/admin/blockUnblockUser', [UserController::class, 'blockUnblockUser'])->middleware('admin');
    Route::post('/admin/deactivateUser', [UserController::class, 'deactivateUser'])->middleware('admin');

    Route::post('/admin/registerNewAgent', [AgentController::class, 'store'])->middleware('admin');
    Route::post('/admin/updateAgent/{id}', [AgentController::class, 'updateAgent'])->middleware('admin');
    Route::post('/admin/updateAgentPassword/{id}', [AgentController::class, 'updateAgentPassword'])->middleware('admin');
    Route::get('/admin/fetchAgents', [AgentController::class, 'fetchAgents'])->middleware('admin');
    Route::get('/admin/deleteAgent/{id}', [AgentController::class, 'deleteAgent'])->middleware('admin');

    Route::post('/admin/registerNewAdmin', [AdminController::class, 'store'])->middleware('admin');
    Route::post('/admin/updateAdmin/{id}', [AdminController::class, 'updateAdmin'])->middleware('admin');
    Route::post('/admin/updateAdminPassword/{id}', [AdminController::class, 'updateAdminPassword'])->middleware('admin');
    Route::get('/admin/fetchAdmins', [AdminController::class, 'fetchAdmins'])->middleware('admin');
    Route::get('/admin/deleteAdmin/{id}', [AdminController::class, 'deleteAdmin'])->middleware('admin');

    Route::post('/admin/registerNewClient', [ClientController::class, 'store'])->middleware('admin');
    Route::post('/admin/updateClient/{id}', [ClientController::class, 'updateClient'])->middleware('admin');
    Route::post('/admin/updateClientPassword/{id}', [ClientController::class, 'updateClientPassword'])->middleware('admin');
    Route::get('/admin/deleteClient/{id}', [ClientController::class, 'deleteClient'])->middleware('admin');

    Route::post('/user/profile/{user}/showUserDataById', [UserController::class, 'showUserDataById']);

    Route::post('/user/features', [UserController::class, 'features']);

    Route::post('/admin/notifReminders/findUserByName', [UserController::class, 'findUserByName']);
    Route::post('/admin/notifReminders/create/{id?}', [NotificationController::class, 'createOrEditReminder']);
    Route::get('/admin/notifReminders/fetchReminder/{id}', [NotificationController::class, 'fetchReminder'])->middleware(['admin', 'agent']);
    Route::get('/admin/notifReminders/deleteReminder/{id}', [NotificationController::class, 'deleteReminder'])->middleware(['admin', 'agent']);

    // Plan-tier subscriptions — retired module, see docs/modules/subscriptions/README.md
    if (config('features.subscription_plans')) {
        Route::post('/client/sub/upgradeDowngrade', [SubscriptionController::class, 'upgradeDowngrade']);
        Route::post('/client/sub/startTrial', [SubscriptionController::class, 'startTrial'])->middleware('trial-guard');
        Route::post('/client/sub/subscribe', [SubscriptionController::class, 'subscribe']);

        Route::post('/client/sub/generateTrialInvoice', [SubscriptionController::class, 'generateTrialInvoice'])->middleware('trial-guard');
        Route::post('/client/sub/generateInvoice', [SubscriptionController::class, 'generateInvoice']);
        Route::post('/client/sub/changePlanInvoice/{id}', [SubscriptionController::class, 'changePlanInvoice']);
    }

    Route::get('/client/invoice/{id}', [InvoiceController::class, 'show']);

    // Billing & Payments — client reads
    Route::get('/client/billing/summary', [ClientBillingController::class, 'summary'])->middleware('client');
    Route::get('/client/billing/invoices', [ClientBillingController::class, 'index'])->middleware('client');
    Route::get('/client/billing/invoices/{invoice}', [ClientBillingController::class, 'show'])->middleware('client');

    // Billing & Payments — Stripe rail
    Route::post('/client/invoices/{invoice}/stripe/intent', [StripeController::class, 'createIntent'])->middleware('client');
    Route::post('/client/stripe/setup-intent', [StripeController::class, 'createSetupIntent'])->middleware('client');
    Route::get('/client/payments/{payment}/status', [ClientPaymentController::class, 'status'])->middleware('client');

    // Billing & Payments — PayPal rail (invoice-based; legacy subscription
    // PayPal flow lives in PayPalController behind the module flag)
    Route::post('/client/invoices/{invoice}/paypal/create', [BillingPayPalController::class, 'createOrder'])->middleware('client');

    // Billing & Payments — SEPA bank transfer rail
    Route::get('/client/invoices/{invoice}/bank-details', [BankTransferController::class, 'bankDetails'])->middleware('client');
    Route::post('/client/invoices/{invoice}/payment-proof', [BankTransferController::class, 'uploadProof'])->middleware('client');
    Route::get('/admin/payment-proofs', [PaymentReconciliationController::class, 'index'])->middleware('admin');
    Route::post('/admin/payment-proofs/{proof}/accept', [PaymentReconciliationController::class, 'accept'])->middleware('admin');
    Route::post('/admin/payment-proofs/{proof}/reject', [PaymentReconciliationController::class, 'reject'])->middleware('admin');
    Route::get('/admin/payment-proofs/{proof}/file', [PaymentReconciliationController::class, 'file'])->middleware('admin');

    // ma vone duhna mi qit posht
    Route::post('/paypal/payment', [PayPalController::class, 'createPayment'])
        ->name('payment');

});

Route::group([
    'prefix' => 'v1',
], function () {
    Route::controller(PayPalController::class)->prefix('paypal')->group(function () {
        //Route::get('payment', 'createPayment')->name('payment');
        Route::get('success', 'success')->name('paypal.success');
        Route::get('cancel', 'cancel')->name('paypal.cancel');
    });

    // Billing & Payments — PayPal browser returns (UX only; the capture is
    // idempotent with the webhook)
    Route::get('/paypal/billing/success', [BillingPayPalController::class, 'success'])->name('paypal.billing.success');
    Route::get('/paypal/billing/cancel', [BillingPayPalController::class, 'cancel'])->name('paypal.billing.cancel');

    // Billing & Payments webhooks — public by design; the provider
    // signature is the authentication, never auth:api.
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
    Route::post('/webhooks/paypal', [PayPalWebhookController::class, 'handle']);
});

// Domain protection — dormant security module, see docs/modules/security/README.md
if (config('features.security')) {
    Route::group([
        'namespace' => 'Api',
        'prefix' => 'v1',
        'middleware' => ['auth:api', 'check.subscription'],
    ], function () {
        Route::get('/client/fetchDomains', [DomainController::class, 'fetchDomains']);
        Route::post('/client/createDomain', [DomainController::class, 'store']);
        Route::get('/client/showDomain/{domain}', [DomainController::class, 'show']);
        Route::post('/client/domains/{domain}/verify', [DomainController::class, 'verify']);
    });
}

// Route::middleware(['auth:api'])->group(function () {
//     Route::get('test', [AuthenticationController::class, 'test']);
// });
