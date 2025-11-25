<?php

use App\Http\Controllers\TicketController;
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

// Ticket Classification Routes
Route::post('/classify', [TicketController::class, 'classify'])
    ->name('tickets.classify');

Route::get('/tickets/{id}', [TicketController::class, 'show'])
    ->name('tickets.show');

Route::get('/tickets', [TicketController::class, 'index'])
    ->name('tickets.index');

Route::get('/statistics', [TicketController::class, 'statistics'])
    ->name('tickets.statistics');
