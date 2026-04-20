<?php

use App\Http\Controllers\Admin\AdminStatementPdfController;
use App\Http\Controllers\Admin\DatabaseBackupDownloadController;
use App\Http\Controllers\Admin\StoredDatabaseBackupDownloadController;
use App\Http\Controllers\BankImportSampleController;
use App\Http\Controllers\ContributionImportSampleController;
use App\Http\Controllers\ContributionReceiptController;
use App\Http\Controllers\DirectMessageAttachmentController;
use App\Http\Controllers\LoanImportSampleController;
use App\Http\Controllers\LoanSchedulePdfController;
use App\Http\Controllers\LocaleSwitchController;
use App\Http\Controllers\MemberImportSampleController;
use App\Http\Controllers\MembershipApplicationFormTemplateController;
use App\Http\Controllers\MembershipApplicationImportSampleController;
use App\Http\Controllers\MembershipCertificateController;
use App\Http\Controllers\StatementPdfController;
use App\Http\Controllers\TermsConditionsDownloadController;
use App\Http\Livewire\ApplicationStatusPage;
use App\Http\Livewire\LoginPage;
use App\Http\Livewire\MembershipApplicationForm;
use App\Http\Livewire\PublicHomePage;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomePage::class)->name('home');
Route::get('/login', LoginPage::class)->name('login');
Route::get('/apply', MembershipApplicationForm::class)->name('apply');
Route::get('/application-status', ApplicationStatusPage::class)->name('application.status');

Route::get('/downloads/membership-application-form-template', MembershipApplicationFormTemplateController::class)
    ->name('downloads.membership-application-form-template');
Route::get('/downloads/membership-application-import-sample', MembershipApplicationImportSampleController::class)
    ->name('downloads.membership-application-import-sample');
Route::get('/downloads/member-import-sample', MemberImportSampleController::class)
    ->name('downloads.member-import-sample');
Route::get('/downloads/contribution-import-sample', ContributionImportSampleController::class)
    ->name('downloads.contribution-import-sample');
Route::get('/downloads/bank-import-sample', BankImportSampleController::class)
    ->name('downloads.bank-import-sample');
Route::get('/downloads/loan-import-sample', LoanImportSampleController::class)
    ->name('downloads.loan-import-sample');
Route::get('/downloads/terms-and-conditions', TermsConditionsDownloadController::class)
    ->name('downloads.terms-and-conditions');
Route::get('/locale/{locale}', LocaleSwitchController::class)
    ->name('locale.switch');

Route::middleware(['auth'])->group(function () {
    Route::get('/direct-messages/{message}/attachment/{index}', [DirectMessageAttachmentController::class, 'show'])
        ->whereNumber('index')
        ->name('direct-messages.attachment');

    Route::get('/member/statements/{statement}/pdf', [StatementPdfController::class, 'download'])
        ->name('member.statement.pdf');

    Route::get('/member/contributions/{contribution}/receipt', [ContributionReceiptController::class, 'download'])
        ->name('member.contribution.receipt');

    Route::get('/member/certificate', [MembershipCertificateController::class, 'download'])
        ->name('member.certificate');

    Route::get('/member/loans/{loan}/schedule', [LoanSchedulePdfController::class, 'download'])
        ->name('member.loan.schedule');

    Route::get('/admin/statements/{statement}/pdf', AdminStatementPdfController::class)
        ->name('admin.statement.pdf');

    Route::get('/admin/system/backup-download', DatabaseBackupDownloadController::class)
        ->name('admin.system.backup-download');

    Route::get('/admin/system/backups/{databaseBackup}/download', StoredDatabaseBackupDownloadController::class)
        ->name('admin.system.backup-stored-download');
});

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');
