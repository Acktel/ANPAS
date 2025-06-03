<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutomezzoController;

Route::resource('automezzi', AutomezzoController::class);