<?php

use App\Http\Controllers\Api\ObligeeController as ApiObligeeController;
use App\Http\Controllers\Api\PrincipalController as ApiPrincipalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BondRequestController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\CertificateVersionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\Maintenance\BondTypeMasterController;
use App\Http\Controllers\Maintenance\BranchController;
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

Route::get('/verify-certificate', [CertificateVerificationController::class, 'search'])
    ->name('certificate-verification.search');
Route::get('/verify-certificate/{verification_token}', [CertificateVerificationController::class, 'show'])
    ->name('certificate-verification.show');
Route::post('/verify-certificate/search', [CertificateVerificationController::class, 'lookup'])
    ->name('certificate-verification.lookup');

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $user = auth()->user();

    if ($user->hasPermission('dashboard.view')) {
        return redirect()->route('dashboard');
    }

    if ($user->hasPermission('certifications.view-assigned')) {
        return redirect()->route('certifications.index');
    }

    return redirect()->route('bond-requests.index');
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
    Route::get('bond-requests/{bond_request}/certificate-versions', [CertificateVersionController::class, 'index'])->name('bond-requests.certificate-versions.index');

    Route::get('certificate-versions/{certificateVersion}/view', [CertificateVersionController::class, 'view'])->name('certificate-versions.view');
    Route::get('certificate-versions/{certificateVersion}/download', [CertificateVersionController::class, 'download'])->name('certificate-versions.download');
    Route::get('certificate-versions/{certificateVersion}/download-docx', [CertificateVersionController::class, 'downloadDocx'])->name('certificate-versions.download-docx');
    Route::patch('certificate-versions/{certificateVersion}/make-current', [CertificateVersionController::class, 'makeCurrent'])->name('certificate-versions.make-current');
    Route::delete('certificate-versions/{certificateVersion}', [CertificateVersionController::class, 'destroy'])->name('certificate-versions.destroy');

    // Branch-scoped certificates for requesters and approvers
    Route::get('/certifications', [CertificateController::class, 'index'])->name('certifications.index');

    // Certificate Templates (admin)
    Route::get('/certificate-templates', [CertificateTemplateController::class, 'index'])->name('certificate-templates.index');
    Route::post('/certificate-templates', [CertificateTemplateController::class, 'store'])->name('certificate-templates.store');
    Route::patch('/certificate-templates/{certificate_template}/activate', [CertificateTemplateController::class, 'activate'])->name('certificate-templates.activate');
    Route::patch('/certificate-templates/{certificate_template}/archive', [CertificateTemplateController::class, 'archive'])->name('certificate-templates.archive');
    Route::get('/certificate-templates/{certificate_template}/download', [CertificateTemplateController::class, 'download'])->name('certificate-templates.download');
    Route::get('/certificate-templates/fallback/{type}/download', [CertificateTemplateController::class, 'downloadFallback'])->name('certificate-templates.download-fallback');

    // Obligees & Principals
    Route::resource('obligees', ObligeeController::class);
    Route::resource('principals', PrincipalController::class);

    // Payments
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/deposits', [DepositController::class, 'index'])->name('deposits.index');
        Route::get('/deposits/create', [DepositController::class, 'create'])->name('deposits.create');
        Route::post('/deposits', [DepositController::class, 'store'])->name('deposits.store');
        Route::get('/deposits/{deposit}', [DepositController::class, 'show'])->name('deposits.show');
        Route::get('/deposits/{deposit}/download-receipt', [DepositController::class, 'downloadReceipt'])->name('deposits.download-receipt');
        Route::post('/deposits/{deposit}/approve', [DepositController::class, 'approve'])->name('deposits.approve');
        Route::post('/deposits/{deposit}/reject', [DepositController::class, 'reject'])->name('deposits.reject');
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/histories', [PaymentHistoryController::class, 'index'])->name('histories.index');
    });

    // Users (admin)
    Route::resource('users', UserController::class)->only(['index', 'create', 'store']);

    // Audit Logs (admin)
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit-logs.view')
        ->name('audit-logs.index');

    // Maintenance
    Route::prefix('maintenance')->name('maintenance.')->group(function () {
        Route::resource('bond-types', BondTypeMasterController::class)->except('show');
        Route::resource('signatories', SignatoryController::class)->except('show');
        Route::resource('notaries', NotaryController::class)->except('show');
        Route::get('certifications', [CertificateController::class, 'maintenanceIndex'])->name('certifications.index');
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
