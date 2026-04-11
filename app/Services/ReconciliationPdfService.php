<?php

namespace App\Services;

use App\Models\ReconciliationSnapshot;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReconciliationPdfService
{
    public function download(ReconciliationSnapshot $snapshot): Response
    {
        $filename = 'reconciliation-snapshot-' . $snapshot->id . '-' . $snapshot->as_of->format('Y-m-d-His') . '.pdf';

        $pdf = Pdf::loadView('pdf.reconciliation-snapshot', [
            'snapshot' => $snapshot,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
