<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BoardController::class, 'index'])->name('board');
Route::get('/runs/{run}', [BoardController::class, 'show'])->name('runs.show');
Route::get('/runs/{run}/availability', [BoardController::class, 'availability'])->name('runs.availability');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', fn () => redirect()->route(match (auth()->user()->role) {
        'admin' => 'admin.index', 'driver' => 'driver.index', default => 'board'
    }))->name('dashboard');
    Route::get('/wallet', [StudentController::class, 'wallet'])->middleware('role:student,driver')->name('wallet');
    Route::middleware('role:student')->group(function () {
        Route::get('/bookings', [StudentController::class, 'bookings'])->name('bookings');
        Route::post('/runs/{run}/book', [StudentController::class, 'book'])->name('book');
        Route::get('/tickets/{booking}', [StudentController::class, 'ticket'])->name('tickets.show');
        Route::post('/tickets/{booking}/cancel', [StudentController::class, 'cancel'])->name('tickets.cancel');
    });
    Route::prefix('driver')->name('driver.')->middleware('role:driver')->group(function () {
        Route::get('/', [DriverController::class, 'index'])->name('index');
        Route::post('/buses', [DriverController::class, 'registerBus'])->name('buses.store');
        Route::post('/runs', [DriverController::class, 'openRun'])->name('runs.store');
        Route::get('/runs/{run}', [DriverController::class, 'show'])->name('runs.show');
        Route::patch('/runs/{run}', [DriverController::class, 'update'])->name('runs.update');
        Route::post('/runs/{run}/status', [DriverController::class, 'transition'])->name('runs.status');
        Route::post('/bookings/{booking}/board', [DriverController::class, 'board'])->name('bookings.board');
    });
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/buses', [AdminController::class, 'buses'])->name('buses');
        Route::patch('/buses/{bus}', [AdminController::class, 'approve'])->name('buses.approve');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::post('/terminals/{terminal?}', [AdminController::class, 'terminal'])->name('terminals.save');
        Route::post('/fares', [AdminController::class, 'fare'])->name('fares.save');
        Route::get('/students', [AdminController::class, 'students'])->name('students');
        Route::post('/students/{student}/credit', [AdminController::class, 'credit'])->name('students.credit');
        Route::post('/runs/{run}/cancel', [AdminController::class, 'cancelRun'])->name('runs.cancel');
        Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    });
});
