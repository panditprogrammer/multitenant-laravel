<?php

use App\Livewire\Settings\General;
use App\Http\Controllers\StudentMembershipPaymentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('dashboard', function () {
        if (Auth::user()->isStudent()) {
            return redirect()->route('student.dashboard');
        }

        if (Auth::user()->canAccessOwnerPanel()) {
            return redirect()->route('owner.dashboard');
        }

        return view('dashboard');
    })->name('dashboard');

    Route::middleware(['owner_panel'])->group(function () {
        Route::livewire('owner/dashboard', 'pages::dashboard')->name('owner.dashboard');
        Route::livewire('library/create', 'pages::library.create')->middleware('permission:view_library')->name('library.create');
        Route::livewire('room/manage', 'library::room.manage')->middleware('permission:view_room')->name('room.manage');
        Route::livewire('student/manage', 'library::student.manage')->middleware('permission:view_student')->name('student.manage');
        Route::livewire('/membership/manage/{library}', 'library::membership.manage')
            ->middleware('permission:view_membership')
            ->name('membership.manage');
        Route::livewire('payment/manage', 'library::payment.manage')->middleware('permission:view_payment')->name('payment.manage');
        Route::livewire('library/attendance', 'library::attendance.manage')->middleware('permission:view_attendance')->name('owner.attendance');
        Route::livewire('role/manage', 'library::role.manage')->middleware('primary_owner')->name('role.manage');
        Route::livewire('user/manage', 'library::user.manage')->middleware('primary_owner')->name('user.manage');
        Route::redirect('setup-configurations', 'setup-configurations/payment-gateway');
        Route::livewire('setup-configurations/payment-gateway', General::class)->middleware('primary_owner')->name('setup.payment-gateway.edit');
    });

    Route::middleware(['role:student'])->prefix("student")->name("student.")->group(function () {
        Route::livewire('dashboard', 'pages::student.dashboard')->middleware('permission:view_student_dashboard')->name('dashboard');
        Route::livewire('payments', 'pages::student.payments')->middleware('permission:view_own_payments')->name('payments');
        Route::livewire('attendance', 'pages::student.attendance')->middleware('permission:view_own_attendance')->name('attendance');
        Route::post('memberships/{membership}/payments/razorpay/order', [StudentMembershipPaymentController::class, 'storeRazorpayOrder'])
            ->middleware('permission:create_membership_payment')
            ->name('memberships.payments.razorpay.order');
    });
});

require __DIR__ . '/settings.php';
