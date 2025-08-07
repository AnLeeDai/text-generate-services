<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn(Request $request) => $request->user())->middleware('auth:sanctum');

Route::group(['prefix' => 'system'], function () {
    Route::get('/server-info', [App\Http\Controllers\Api\ServerInfo::class, 'check']);
    Route::get('/list', [\App\Http\Controllers\Api\FileCheckController::class, 'listFiles']);
    Route::delete('/delete-all', [\App\Http\Controllers\Api\FileCheckController::class, 'deleteAllFiles']);
    Route::delete('/delete/{fileName}', [\App\Http\Controllers\Api\FileCheckController::class, 'deleteFileByName']);
});

Route::group(['prefix' => 'bank-bill'], function () {
    Route::post('/generate', [App\Http\Controllers\Api\BankBillController::class, 'generateBankBillBTGPactualGenerate']);
});