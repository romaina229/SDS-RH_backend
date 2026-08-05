<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name', 'SDS-RH'),
        'status' => 'ok',
        'version' => app()->version(),
    ]);
});
