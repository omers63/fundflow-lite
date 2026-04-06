<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\DatabaseBackup;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoredDatabaseBackupDownloadController
{
    public function __invoke(Request $request, DatabaseBackup $databaseBackup): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessPanel(Filament::getPanel('admin')), 403);

        $root = realpath(storage_path('app/backups'));
        abort_unless($root !== false && is_dir($root), 404);

        $full = realpath(storage_path('app/' . $databaseBackup->path));
        abort_unless($full !== false && is_file($full), 404);
        abort_unless(str_starts_with($full, $root), 403);

        return response()->download($full, $databaseBackup->filename, [
            'Content-Type' => str_ends_with($databaseBackup->filename, '.sql')
                ? 'application/sql'
                : 'application/octet-stream',
        ]);
    }
}
