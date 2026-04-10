<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::view('dashboard', 'dashboard')->name('dashboard');

    // owner routes
    Route::middleware(['auth', 'role:owner'])->group(function () {
        Route::livewire('/library/create', 'pages::library.create')->name('library.create');
        Route::livewire('/room/manage', 'library::room.manage')->name('room.manage');
    });

    // student routes
    Route::middleware(['auth', 'role:student'])->group(function () {
        Route::get('student', function () {
            return 'student';
        })->name('student');
    });
});

require __DIR__ . '/settings.php';
