<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ServerInfo;

Route::get('/', [ServerInfo::class, 'check']);
