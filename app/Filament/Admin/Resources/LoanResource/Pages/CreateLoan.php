<?php

namespace App\Filament\Admin\Resources\LoanResource\Pages;

use App\Filament\Admin\Resources\LoanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    public function mount(): void
    {
        parent::mount();

        $memberId = request()->integer('member_id');
        if ($memberId > 0) {
            $this->form->fill(['member_id' => $memberId]);
        }
    }
}
