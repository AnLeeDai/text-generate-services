<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn(Request $request) => $request->user())->middleware('auth:sanctum');

Route::group(['prefix' => 'bank-bill'], function () {
    Route::post('/generate', [App\Http\Controllers\Api\BankBillController::class, 'generateBankBillBTGPactualGenerate']);
});