<?php

namespace App\Http\Livewire;

use App\Models\Member;
use App\Models\Contribution;
use App\Models\Loan;
use Livewire\Component;

class PublicHomePage extends Component
{
    public function render()
    {
        $stats = [
            'members' => Member::where('status', 'active')->count(),
            'total_contributions' => Contribution::sum('amount'),
            'active_loans' => Loan::where('status', 'active')->count(),
        ];

        return view('livewire.public-home-page', compact('stats'))
            ->layout('layouts.public', ['title' => __('Welcome')]);
    }
}
