<?php

namespace App\Http\Controllers\Payments;

use Ejoi\PaymentGateway\Laravel\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProofDownloadController
{
    /**
     * Stream an uploaded payment-proof file to a staff member (admin/coach) so
     * they can review it before approving/rejecting a manual payment. Never
     * exposed to parents — the disk is private by default (see
     * config('payment-gateway.proofs.disk')).
     */
    public function __invoke(Request $request, PaymentProof $proof): StreamedResponse
    {
        abort_unless((bool) $request->user()?->hasAnyRole(['admin', 'coach', 'super_admin']), 403);

        abort_unless(Storage::disk($proof->disk)->exists($proof->path), 404);

        return Storage::disk($proof->disk)->download($proof->path, $proof->original_name ?: 'receipt');
    }
}
