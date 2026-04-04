<?php

namespace App\Http\Controllers\Admin;

use App\Services\DatabaseMaintenanceService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupDownloadController
{
    public function __invoke(Request $request, DatabaseMaintenanceService $service): BinaryFileResponse|StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessPanel(Filament::getPanel('admin')), 403);

        return $service->downloadBackupResponse();
    }
}
