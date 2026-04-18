<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class TermsConditionsDownloadController extends Controller
{
    /**
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function __invoke()
    {
        $relativePath = 'downloads/fund-terms-and-conditions.pdf';

        if (!Storage::disk('public')->exists($relativePath)) {
            abort(404, 'Terms & Conditions document is not available.');
        }

        $absolutePath = Storage::disk('public')->path($relativePath);

        return response()->download(
            $absolutePath,
            'fund-terms-and-conditions.pdf',
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}

