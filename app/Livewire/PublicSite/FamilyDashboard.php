<?php

namespace App\Livewire\PublicSite;

use App\Models\Enrollment;
use App\Support\PaymentInstructions;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FamilyDashboard extends Component
{
    public function render()
    {
        $user = Auth::user()?->load([
            'students' => fn ($students) => $students
                ->where('is_active', true)
                ->with([
                    'enrollments' => fn ($enrollments) => $enrollments
                        ->with(['offering.program', 'latestPayment'])
                        ->withCount([
                            'attendances as used_credits' => fn ($query) => $query->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES),
                        ])
                        ->latest(),
                ])
                ->orderBy('name'),
        ]);

        return view('livewire.public-site.family-dashboard', [
            'user' => $user,
            'gatewayEnabled' => PaymentInstructions::usesHostedGateway(),
            'gatewayOptions' => PaymentInstructions::hostedGatewayOptions(),
            'defaultGateway' => PaymentInstructions::defaultHostedGateway(),
        ])->layout('layouts.public', [
            'title' => 'My Family',
            'noindex' => true, // private, per-parent — keep it out of search results
        ]);
    }
}
