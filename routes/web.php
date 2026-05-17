<?php

use App\Livewire\Settings\General;
use App\Http\Controllers\StudentMembershipPaymentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('dashboard', function () {
        if (Auth::user()->role === 'student') {
            return redirect()->route('student.dashboard');
        }

        if (Auth::user()->role === 'owner') {
            return redirect()->route('owner.dashboard');
        }

        return view('dashboard');
    })->name('dashboard');

    // owner routes
    Route::middleware(['auth', 'role:owner'])->group(function () {
        Route::livewire('owner/dashboard', 'pages::dashboard')->name('owner.dashboard');
        Route::livewire('library/create', 'pages::library.create')->name('library.create');
        Route::livewire('room/manage', 'library::room.manage')->name('room.manage');
        Route::livewire('student/manage', 'library::student.manage')->name('student.manage');
        Route::livewire('/membership/manage/{library}', 'library::membership.manage')
            ->name('membership.manage');
        Route::livewire('payment/manage', 'library::payment.manage')->name('payment.manage');
        Route::redirect('setup-configurations', 'setup-configurations/payment-gateway');
        Route::livewire('setup-configurations/payment-gateway', General::class)->name('setup.payment-gateway.edit');
    });

    // student routes
    Route::middleware(['auth', 'role:student'])->prefix("student")->name("student.")->group(function () {
        Route::livewire('dashboard', 'pages::student.dashboard')->name('dashboard');
        Route::livewire('payments', 'pages::student.payments')->name('payments');
        Route::post('memberships/{membership}/payments/razorpay/order', [StudentMembershipPaymentController::class, 'storeRazorpayOrder'])
            ->name('memberships.payments.razorpay.order');
    });
});

require __DIR__ . '/settings.php';
