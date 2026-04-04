<?php

use App\Http\Controllers\Admin\DatabaseBackupDownloadController;
use App\Http\Controllers\StatementPdfController;
use App\Http\Livewire\ApplicationStatusPage;
use App\Http\Livewire\LoginPage;
use App\Http\Livewire\MembershipApplicationForm;
use App\Http\Livewire\PublicHomePage;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomePage::class)->name('home');
Route::get('/login', LoginPage::class)->name('login');
Route::get('/apply', MembershipApplicationForm::class)->name('apply');
Route::get('/application-status', ApplicationStatusPage::class)->name('application.status');

Route::middleware(['auth'])->group(function () {
    Route::get('/member/statements/{statement}/pdf', [StatementPdfController::class, 'download'])
        ->name('member.statement.pdf');

    Route::get('/admin/system/backup-download', DatabaseBackupDownloadController::class)
        ->name('admin.system.backup-download');
});

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');
