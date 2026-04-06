<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class MembershipApplicationFormTemplateController extends Controller
{
    public function __invoke(): Response
    {
        $pdf = Pdf::loadView('pdf.membership-application-form-template');

        return $pdf->download('fundflow-membership-application-form-template.pdf');
    }
}
