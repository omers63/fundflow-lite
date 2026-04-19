<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BankImportSampleController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = storage_path('app/examples/alrajhi-bank-import-sample-100-mixed.csv');

        abort_unless(is_file($path), 404, 'Bank import sample file not found.');

        return response()->download($path, 'alrajhi-bank-import-sample-100-mixed.csv', [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
