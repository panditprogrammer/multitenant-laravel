<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('dashboard', function () {
        if (Auth::user()->role === 'student') {
            return redirect()->route('student.dashboard');
        }

        return view('dashboard');
    })->name('dashboard');

    // owner routes
    Route::middleware(['auth', 'role:owner'])->group(function () {
        Route::livewire('library/create', 'pages::library.create')->name('library.create');
        Route::livewire('room/manage', 'library::room.manage')->name('room.manage');
        Route::livewire('student/manage', 'library::student.manage')->name('student.manage');
        Route::livewire('student/create', 'library::student.create')->name('student.create');
        Route::livewire('/membership/manage/{library}', 'library::membership.manage')
            ->name('membership.manage');
    });

    // student routes
    Route::middleware(['auth', 'role:student'])->prefix("student")->name("student.")->group(function () {
        Route::livewire('dashboard', 'pages::student.dashboard')->name('dashboard');
    });
});

require __DIR__ . '/settings.php';
