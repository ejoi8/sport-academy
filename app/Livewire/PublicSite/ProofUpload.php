<?php

namespace App\Livewire\PublicSite;

use App\Models\Enrollment;
use Ejoi\PaymentGateway\Data\Customer;
use Ejoi\PaymentGateway\Data\Money;
use Ejoi\PaymentGateway\Data\PaymentRequest;
use Ejoi\PaymentGateway\Laravel\Payments;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Paid by bank transfer? Upload your receipt" — the manual-proof half of the
 * payment flow. One instance per pending online enrolment on the family
 * dashboard. Creates the manual payment row (if one doesn't already exist)
 * and attaches the uploaded receipt to it via the package's own API
 * (Payments::attachProof) — the payment stays pending until an admin reviews
 * it (see PaymentsTable's Approve/Reject actions).
 */
class ProofUpload extends Component
{
    use WithFileUploads;

    #[Locked]
    public Enrollment $enrollment;

    public $receipt = null;

    public string $note = '';

    public bool $uploaded = false;

    /**
     * Set when a previous proof on the current pending payment was reviewed
     * (rejected, kept pending for resubmission) — shown above the form so the
     * parent knows to try again.
     */
    public ?string $rejectionNote = null;

    public function mount(Enrollment $enrollment): void
    {
        abort_unless($enrollment->student?->parent_id === Auth::id(), 403);

        $this->enrollment = $enrollment;

        $this->syncStateFrom($enrollment->latestPayment);
    }

    public function submit(Payments $payments): void
    {
        abort_unless($this->enrollment->student?->parent_id === Auth::id(), 403);

        $this->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = $this->enrollment->latestPayment;

        // Only a manual, still-pending payment can receive a proof. Any other
        // state (no payment yet, or a hosted-gateway attempt on file) needs a
        // fresh manual payment row created first — mirrors how the package's
        // manual driver works: no API call, just a pending ledger row that the
        // proof attaches to.
        if (! $payment || $payment->gateway !== 'manual' || ! $payment->status->isPending()) {
            $payment = $payments->create('manual', new PaymentRequest(
                reference: $this->enrollment->booking_reference,
                amount: Money::fromMinor($this->enrollment->price_sen, 'MYR'),
                description: 'Bank transfer for '.$this->enrollment->booking_reference,
                customer: new Customer(
                    $this->enrollment->student?->parent?->email ?? Auth::user()->email,
                    $this->enrollment->student?->parent?->name ?? Auth::user()->name,
                    $this->enrollment->student?->parent?->phone,
                ),
                redirectUrl: route('family.index'),
                metadata: ['enrollment_id' => $this->enrollment->id],
            ));
        } elseif (is_array($payment->metadata) && array_key_exists('review', $payment->metadata)) {
            // Resubmitting on a payment an admin already reviewed (rejected) —
            // clear the stale review so it shows as freshly awaiting review.
            $metadata = $payment->metadata;
            unset($metadata['review']);
            $payment->metadata = $metadata;
            $payment->save();
        }

        $payments->attachProof($payment, $this->receipt, $this->note ?: null);

        $this->reset('receipt', 'note');
        $this->syncStateFrom($payment->fresh());
    }

    private function syncStateFrom(?object $payment): void
    {
        $metadata = is_array($payment?->metadata ?? null) ? $payment->metadata : [];
        $reviewed = array_key_exists('review', $metadata);

        $this->uploaded = (bool) ($payment
            && $payment->gateway === 'manual'
            && $payment->status->isPending()
            && ! $reviewed
            && $payment->proofs()->exists());

        $this->rejectionNote = $reviewed ? ($metadata['review']['note'] ?? 'Please upload a new receipt.') : null;
    }

    public function render()
    {
        return view('livewire.public-site.proof-upload');
    }
}
