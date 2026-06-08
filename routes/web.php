<?php

use App\Http\Controllers\Api\ObligeeController as ApiObligeeController;
use App\Http\Controllers\Api\PrincipalController as ApiPrincipalController;
use App\Http\Controllers\BondRequestController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\Maintenance\BondTypeMasterController;
use App\Http\Controllers\Maintenance\BranchController;
use App\Http\Controllers\Maintenance\CertificationController;
use App\Http\Controllers\Maintenance\CtcController;
use App\Http\Controllers\Maintenance\NotaryController;
use App\Http\Controllers\Maintenance\SignatoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ObligeeController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\PrincipalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/welcome', WelcomeController::class)->name('welcome');

    // Dashboard
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    // API (session-authenticated)
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/obligees', [ApiObligeeController::class, 'index'])->name('obligees.index');
        Route::get('/obligees/{id}', [ApiObligeeController::class, 'show'])->name('obligees.show');
        Route::get('/principals', [ApiPrincipalController::class, 'index'])->name('principals.index');
    });

    // Bond Requests
    Route::resource('bond-requests', BondRequestController::class);
    Route::post('bond-requests/{bond_request}/approve', [BondRequestController::class, 'approve'])->name('bond-requests.approve');
    Route::post('bond-requests/{bond_request}/reject', [BondRequestController::class, 'reject'])->name('bond-requests.reject');
    Route::post('bond-requests/{bond_request}/notarize', [BondRequestController::class, 'notarize'])->name('bond-requests.notarize');
    Route::post('bond-requests/{bond_request}/generate-certificate', [BondRequestController::class, 'generateCertificate'])->name('bond-requests.generate-certificate');
    Route::get('bond-requests/{bond_request}/view-certificate', [BondRequestController::class, 'viewCertificate'])->name('bond-requests.view-certificate');
    Route::get('bond-requests/{bond_request}/download-certificate', [BondRequestController::class, 'downloadCertificate'])->name('bond-requests.download-certificate');
    Route::get('bond-requests/{bond_request}/download-docx', [BondRequestController::class, 'downloadDocx'])->name('bond-requests.download-docx');

    // Certifications (branch-scoped certificate registry)
    Route::get('/certifications', [CertificateController::class, 'index'])->name('certifications.index');

    // Obligees & Principals
    Route::resource('obligees', ObligeeController::class);
    Route::resource('principals', PrincipalController::class);

    // Payments
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/deposits', [DepositController::class, 'index'])->name('deposits.index');
        Route::get('/deposits/create', [DepositController::class, 'create'])->name('deposits.create');
        Route::post('/deposits', [DepositController::class, 'store'])->name('deposits.store');
        Route::get('/deposits/{deposit}', [DepositController::class, 'show'])->name('deposits.show');
        Route::post('/deposits/{deposit}/approve', [DepositController::class, 'approve'])->name('deposits.approve');
        Route::post('/deposits/{deposit}/reject', [DepositController::class, 'reject'])->name('deposits.reject');
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/histories', [PaymentHistoryController::class, 'index'])->name('histories.index');
    });

    // Users (admin)
    Route::resource('users', UserController::class)->only(['index', 'create', 'store']);

    // Maintenance
    Route::prefix('maintenance')->name('maintenance.')->group(function () {
        Route::resource('bond-types', BondTypeMasterController::class)->except('show');
        Route::resource('signatories', SignatoryController::class)->except('show');
        Route::resource('notaries', NotaryController::class)->except('show');
        Route::resource('certifications', CertificationController::class)->except('show');
        Route::resource('ctcs', CtcController::class)->except('show');
        Route::resource('branches', BranchController::class)->except('show');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

require __DIR__.'/auth.php';
