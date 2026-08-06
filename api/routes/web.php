<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\AccountDetail;

use App\Http\Controllers\Api\PayPalController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return "HELLO FROM API SERVER";
});

Route::get('/login', function() { 
    return null;
})->name('login');

Route::get('/401', function() {
    return response()->json(['error' => "You're not authenticated!"], 401);
})->name('401error');


Route::controller(PayPalController::class)->prefix('paypal')->group(function () {
   //Route::get('payment', 'createPayment')->name('payment');
  // Route::get('success',  'success')->name('paypal.success');
 //  Route::get('cancel',  'cancel')->name('paypal.cancel');
});