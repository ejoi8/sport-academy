<?php

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Livewire\PublicSite\BookingWizard;
use App\Livewire\PublicSite\ProofUpload;
use App\Models\Enrollment;
use App\Models\GatewayPayment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Sport;
use App\Models\Student;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use Ejoi\PaymentGateway\Contracts\HttpClient;
use Ejoi\PaymentGateway\Contracts\PaymentGateway as PaymentGatewayContract;
use Ejoi\PaymentGateway\Data\CallbackPayload;
use Ejoi\PaymentGateway\Data\Customer;
use Ejoi\PaymentGateway\Data\Money;
use Ejoi\PaymentGateway\Data\PaymentRequest;
use Ejoi\PaymentGateway\Data\PaymentResponse;
use Ejoi\PaymentGateway\Data\PaymentStatusResult;
use Ejoi\PaymentGateway\Enums\PaymentStatus;
use Ejoi\PaymentGateway\Laravel\Events\PaymentStatusChanged;
use Ejoi\PaymentGateway\Laravel\Payments;
use Ejoi\PaymentGateway\PaymentGatewayManager;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

function integrationProgram(array $program = [], array $offering = []): array
{
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);

    $programModel = Program::create(array_merge([
        'sport_id' => $sport->id,
        'name' => 'Group Training',
        'description' => 'Saturday academy sessions.',
        'base_price_sen' => 12000,
        'walk_in_fee_sen' => 4000,
        'default_sessions' => 4,
        'is_active' => true,
    ], $program));

    $offeringModel = Offering::create(array_merge([
        'program_id' => $programModel->id,
        'period' => now()->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => 6,
        'start_time' => '09:00',
        'end_time' => '10:30',
        'capacity' => 5,
        'session_count' => 4,
        'price_sen' => 12000,
        'is_open' => true,
    ], $offering));

    return [$programModel, $offeringModel];
}

function integrationParent(string $name = 'Parent User', string $email = 'parent@example.test'): User
{
    $user = User::factory()->create(['name' => $name, 'email' => $email]);
    $user->assignRole(Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']));

    return $user;
}

function integrationStaff(string $name = 'Staff Tester', string $email = 'staff@example.test'): User
{
    $user = User::factory()->create(['name' => $name, 'email' => $email]);
    $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

    return $user;
}

function integrationPendingEnrollment(?User $parent = null): array
{
    $parent ??= integrationParent();
    $student = Student::create([
        'parent_id' => $parent->id,
        'name' => 'Aiman',
        'is_active' => true,
    ]);

    [, $offering] = integrationProgram();

    $enrollment = Enrollment::create([
        'student_id' => $student->id,
        'offering_id' => $offering->id,
        'status' => EnrollmentStatus::Pending->value,
        'price_sen' => $offering->price_sen,
        'sessions_included' => $offering->session_count,
        'source' => 'online',
        'booking_reference' => 'BK-2026-000123',
    ]);

    return [$parent, $student, $offering, $enrollment];
}

function hostedGatewayConfig(array $overrides = []): array
{
    return array_merge([
        'payment-gateway.default' => 'billplz',
        'payment-gateway.gateways.billplz' => [
            'driver' => 'billplz',
            'api_key' => 'test-key',
            'collection_id' => 'collection-123',
            'x_signature_key' => 'signature-123',
            'sandbox' => true,
        ],
        'payment-gateway.notifications.enabled' => false,
    ], $overrides);
}

it('shows pay now on the booking confirmation screen when a hosted gateway is enabled', function () {
    config(hostedGatewayConfig());

    [, $offering] = integrationProgram();

    Livewire::test(BookingWizard::class, ['offering' => $offering])
        ->set('studentName', 'Adam Rahman')
        ->set('guardianName', 'Aida Rahman')
        ->set('guardianPhone', '0123456789')
        ->set('accountName', 'Aida Rahman')
        ->set('accountEmail', 'aida@example.test')
        ->set('accountPhone', '0123456789')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->set('agreedToPolicy', true)
        ->call('submit')
        ->assertSet('step', 4)
        ->assertSee('Pay now');
});

it('shows available payment providers on the booking confirmation screen', function () {
    config(hostedGatewayConfig([
        'payment-gateway.gateways.toyyibpay' => [
            'driver' => 'toyyibpay',
            'secret_key' => 'secret-123',
            'category_code' => 'category-123',
            'sandbox' => true,
        ],
    ]));

    [, $offering] = integrationProgram();

    Livewire::test(BookingWizard::class, ['offering' => $offering])
        ->set('studentName', 'Adam Rahman')
        ->set('guardianName', 'Aida Rahman')
        ->set('guardianPhone', '0123456789')
        ->set('accountName', 'Aida Rahman')
        ->set('accountEmail', 'aida@example.test')
        ->set('accountPhone', '0123456789')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->set('agreedToPolicy', true)
        ->call('submit')
        ->assertSet('step', 4)
        ->assertSee('Payment provider')
        ->assertSee('Billplz FPX')
        ->assertSee('toyyibPay FPX');
});

it('creates a hosted checkout with the chosen payment provider and redirects the parent there', function () {
    config(hostedGatewayConfig([
        'payment-gateway.default' => 'billplz',
        'payment-gateway.gateways.fakecheckout' => [
            'driver' => 'fakecheckout',
        ],
    ]));

    [$parent, , , $enrollment] = integrationPendingEnrollment();

    app(PaymentGatewayManager::class)->extend('fakecheckout', function ($config, HttpClient $http) use ($enrollment): PaymentGatewayContract {
        return new class($enrollment->booking_reference, $enrollment->price_sen) implements PaymentGatewayContract
        {
            public function __construct(
                private readonly string $expectedReference,
                private readonly int $expectedAmount,
            ) {}

            public function name(): string
            {
                return 'fakecheckout';
            }

            public function createPayment(PaymentRequest $request): PaymentResponse
            {
                expect($request->reference)->toBe($this->expectedReference)
                    ->and($request->amount->minor())->toBe($this->expectedAmount);

                return new PaymentResponse(
                    gatewayReference: 'fake-gateway-ref',
                    redirectUrl: 'https://billplz.test/pay/abc123',
                    status: PaymentStatus::Pending,
                    raw: ['fake' => true],
                );
            }

            public function verifyCallback(CallbackPayload $payload): PaymentStatusResult
            {
                return new PaymentStatusResult(
                    reference: $this->expectedReference,
                    gatewayReference: 'fake-gateway-ref',
                    status: PaymentStatus::Paid,
                    verified: true,
                    amount: Money::fromMinor($this->expectedAmount, 'MYR'),
                    raw: ['fake' => true],
                );
            }

            public function queryStatus(string $gatewayReference): PaymentStatusResult
            {
                return new PaymentStatusResult(
                    reference: $this->expectedReference,
                    gatewayReference: $gatewayReference,
                    status: PaymentStatus::Paid,
                    verified: true,
                    amount: Money::fromMinor($this->expectedAmount, 'MYR'),
                    raw: ['fake' => true],
                );
            }
        };
    });

    $this->actingAs($parent)
        ->post(route('payments.checkout', $enrollment), [
            'gateway' => 'fakecheckout',
        ])
        ->assertRedirect('https://billplz.test/pay/abc123');

    $payment = GatewayPayment::query()->where('reference', $enrollment->booking_reference)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->gateway)->toBe('fakecheckout');
});

it('activates the matching online enrolment when a manual package payment is approved', function () {
    config([
        'payment-gateway.default' => 'manual',
        'payment-gateway.notifications.enabled' => false,
        'payment-gateway.gateways.manual.bank_name' => 'Maybank',
        'payment-gateway.gateways.manual.account_name' => 'Football Academy',
        'payment-gateway.gateways.manual.account_number' => '1234567890',
    ]);

    [$parent, , , $enrollment] = integrationPendingEnrollment(integrationParent('Review Parent', 'review@example.test'));

    /** @var Payments $payments */
    $payments = app(Payments::class);

    $payment = $payments->create('manual', new PaymentRequest(
        reference: $enrollment->booking_reference,
        amount: Money::fromMinor($enrollment->price_sen, 'MYR'),
        description: 'Offline payment for '.$enrollment->booking_reference,
        customer: new Customer($parent->email, $parent->name, $parent->phone),
        redirectUrl: route('family.index'),
        metadata: ['enrollment_id' => $enrollment->id],
    ));

    expect($payment->status)->toBe(PaymentStatus::Pending);

    $payments->approve($payment, reviewedBy: 'Staff Tester', note: 'Bank transfer verified.');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($enrollment->fresh()->status)->toBe(EnrollmentStatus::Active);
});

// -----------------------------------------------------------------------
// FIX 1 — the return page must never 500, whatever state the payment is in.
// -----------------------------------------------------------------------

it('shows the paid state and booking reference when returning to a paid payment', function () {
    [$parent, , , $enrollment] = integrationPendingEnrollment();

    GatewayPayment::create([
        'gateway' => 'billplz',
        'reference' => $enrollment->booking_reference,
        'gateway_reference' => 'gw-ref-paid',
        'status' => PaymentStatus::Paid->value,
        'amount_minor' => $enrollment->price_sen,
        'currency' => 'MYR',
        'paid_at' => now(),
    ]);

    $this->actingAs($parent)
        ->get(route('payments.return', $enrollment))
        ->assertOk()
        ->assertSee($enrollment->booking_reference)
        ->assertSee('Payment received');
});

it('renders 200 for a pending payment without a gateway reference', function () {
    [$parent, , , $enrollment] = integrationPendingEnrollment();

    GatewayPayment::create([
        'gateway' => 'billplz',
        'reference' => $enrollment->booking_reference,
        'gateway_reference' => null,
        'status' => PaymentStatus::Pending->value,
        'amount_minor' => $enrollment->price_sen,
        'currency' => 'MYR',
    ]);

    $this->actingAs($parent)
        ->get(route('payments.return', $enrollment))
        ->assertOk()
        ->assertSee('still waiting for the gateway confirmation')
        ->assertSee('Check payment status again');
});

it('forbids returning to another parent\'s booking', function () {
    [, , , $enrollmentA] = integrationPendingEnrollment(integrationParent('Parent A', 'parent-a@example.test'));
    $parentB = integrationParent('Parent B', 'parent-b@example.test');

    $this->actingAs($parentB)
        ->get(route('payments.return', $enrollmentA))
        ->assertForbidden();
});

it('renders 200 when no payment attempt exists yet for the booking', function () {
    [$parent, , , $enrollment] = integrationPendingEnrollment();

    $this->actingAs($parent)
        ->get(route('payments.return', $enrollment))
        ->assertOk()
        ->assertSee('No payment attempt has been started');
});

// -----------------------------------------------------------------------
// FIX 3 — checkout reuse ladder: never mint a duplicate live bill.
// -----------------------------------------------------------------------

function extendFakeCheckoutGateway(Enrollment $enrollment, PaymentStatus $queryStatus, ?callable $onCreate = null): void
{
    app(PaymentGatewayManager::class)->extend('fakecheckout', function ($config, HttpClient $http) use ($enrollment, $queryStatus, $onCreate): PaymentGatewayContract {
        return new class($enrollment->booking_reference, $enrollment->price_sen, $queryStatus, $onCreate) implements PaymentGatewayContract
        {
            public function __construct(
                private readonly string $expectedReference,
                private readonly int $expectedAmount,
                private readonly PaymentStatus $queryStatus,
                private $onCreate,
            ) {}

            public function name(): string
            {
                return 'fakecheckout';
            }

            public function createPayment(PaymentRequest $request): PaymentResponse
            {
                if ($this->onCreate) {
                    ($this->onCreate)();
                }

                return new PaymentResponse(
                    gatewayReference: 'fake-gateway-ref-reuse',
                    redirectUrl: 'https://billplz.test/pay/reuse-test',
                    status: PaymentStatus::Pending,
                    raw: [],
                );
            }

            public function verifyCallback(CallbackPayload $payload): PaymentStatusResult
            {
                return new PaymentStatusResult(
                    reference: $this->expectedReference,
                    gatewayReference: 'fake-gateway-ref-reuse',
                    status: $this->queryStatus,
                    verified: true,
                    amount: Money::fromMinor($this->expectedAmount, 'MYR'),
                );
            }

            public function queryStatus(string $gatewayReference): PaymentStatusResult
            {
                return new PaymentStatusResult(
                    reference: $this->expectedReference,
                    gatewayReference: $gatewayReference,
                    status: $this->queryStatus,
                    verified: true,
                    amount: Money::fromMinor($this->expectedAmount, 'MYR'),
                );
            }
        };
    });
}

it('reuses the existing checkout url instead of minting a duplicate bill', function () {
    config(hostedGatewayConfig([
        'payment-gateway.gateways.fakecheckout' => ['driver' => 'fakecheckout'],
    ]));

    [$parent, , , $enrollment] = integrationPendingEnrollment();

    $createCalls = 0;
    extendFakeCheckoutGateway($enrollment, PaymentStatus::Pending, function () use (&$createCalls): void {
        $createCalls++;
    });

    $this->actingAs($parent)
        ->post(route('payments.checkout', $enrollment), ['gateway' => 'fakecheckout'])
        ->assertRedirect('https://billplz.test/pay/reuse-test');

    $this->actingAs($parent)
        ->post(route('payments.checkout', $enrollment), ['gateway' => 'fakecheckout'])
        ->assertRedirect('https://billplz.test/pay/reuse-test');

    expect($createCalls)->toBe(1)
        ->and(GatewayPayment::query()->where('reference', $enrollment->booking_reference)->count())->toBe(1);
});

it('creates a new payment row when a different gateway is chosen the second time', function () {
    config(hostedGatewayConfig([
        'payment-gateway.gateways.fakecheckout' => ['driver' => 'fakecheckout'],
        'payment-gateway.gateways.toyyibpay' => [
            'driver' => 'toyyibpay',
            'secret_key' => 'secret-123',
            'category_code' => 'category-123',
            'sandbox' => true,
        ],
    ]));

    [$parent, , , $enrollment] = integrationPendingEnrollment();

    extendFakeCheckoutGateway($enrollment, PaymentStatus::Pending);

    app(PaymentGatewayManager::class)->extend('toyyibpay', function ($config, HttpClient $http) use ($enrollment): PaymentGatewayContract {
        return new class($enrollment->booking_reference, $enrollment->price_sen) implements PaymentGatewayContract
        {
            public function __construct(private readonly string $expectedReference, private readonly int $expectedAmount) {}

            public function name(): string
            {
                return 'toyyibpay';
            }

            public function createPayment(PaymentRequest $request): PaymentResponse
            {
                return new PaymentResponse(
                    gatewayReference: 'fake-toyyibpay-ref',
                    redirectUrl: 'https://toyyibpay.test/pay/xyz',
                    status: PaymentStatus::Pending,
                    raw: [],
                );
            }

            public function verifyCallback(CallbackPayload $payload): PaymentStatusResult
            {
                return new PaymentStatusResult(reference: $this->expectedReference, gatewayReference: 'fake-toyyibpay-ref', status: PaymentStatus::Pending, verified: true);
            }

            public function queryStatus(string $gatewayReference): PaymentStatusResult
            {
                return new PaymentStatusResult(reference: $this->expectedReference, gatewayReference: $gatewayReference, status: PaymentStatus::Pending, verified: true);
            }
        };
    });

    $this->actingAs($parent)
        ->post(route('payments.checkout', $enrollment), ['gateway' => 'fakecheckout'])
        ->assertRedirect('https://billplz.test/pay/reuse-test');

    $this->actingAs($parent)
        ->post(route('payments.checkout', $enrollment), ['gateway' => 'toyyibpay'])
        ->assertRedirect('https://toyyibpay.test/pay/xyz');

    expect(GatewayPayment::query()->where('reference', $enrollment->booking_reference)->count())->toBe(2);
});

it('redirects to the return page when the existing payment reconciles as already paid', function () {
    config(hostedGatewayConfig([
        'payment-gateway.gateways.fakecheckout' => ['driver' => 'fakecheckout'],
    ]));

    [$parent, , , $enrollment] = integrationPendingEnrollment();

    extendFakeCheckoutGateway($enrollment, PaymentStatus::Paid);

    GatewayPayment::create([
        'gateway' => 'fakecheckout',
        'reference' => $enrollment->booking_reference,
        'gateway_reference' => 'fake-gateway-ref-reuse',
        'status' => PaymentStatus::Pending->value,
        'amount_minor' => $enrollment->price_sen,
        'currency' => 'MYR',
    ]);

    $this->actingAs($parent)
        ->post(route('payments.checkout', $enrollment), ['gateway' => 'fakecheckout'])
        ->assertRedirect(route('payments.return', $enrollment));

    expect(GatewayPayment::query()->where('reference', $enrollment->booking_reference)->count())->toBe(1);
});

// -----------------------------------------------------------------------
// FIX 4 — amount mismatch and duplicate payment must never activate silently.
// -----------------------------------------------------------------------

it('flags a payment amount mismatch instead of activating the enrolment', function () {
    [, , , $enrollment] = integrationPendingEnrollment();

    $payment = GatewayPayment::create([
        'gateway' => 'manual',
        'reference' => $enrollment->booking_reference,
        'gateway_reference' => $enrollment->booking_reference,
        'status' => PaymentStatus::Paid->value,
        'amount_minor' => $enrollment->price_sen - 100,
        'currency' => 'MYR',
        'paid_at' => now(),
    ]);

    event(new PaymentStatusChanged($payment, PaymentStatus::Pending));

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Pending);

    $activity = Activity::query()->where('log_name', 'enrolments')->where('subject_id', $enrollment->id)->orderByDesc('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('payment amount mismatch')
        ->and($activity->properties->get('expected_amount_minor'))->toBe($enrollment->price_sen)
        ->and($activity->properties->get('received_amount_minor'))->toBe($enrollment->price_sen - 100);
});

it('flags a duplicate payment received for an already-active enrolment', function () {
    Notification::fake();

    [, , , $enrollment] = integrationPendingEnrollment();
    $enrollment->update(['status' => EnrollmentStatus::Active]);

    $duplicatePayment = GatewayPayment::create([
        'gateway' => 'billplz',
        'reference' => $enrollment->booking_reference,
        'gateway_reference' => 'gw-ref-duplicate',
        'status' => PaymentStatus::Paid->value,
        'amount_minor' => $enrollment->price_sen,
        'currency' => 'MYR',
        'paid_at' => now(),
    ]);

    event(new PaymentStatusChanged($duplicatePayment, PaymentStatus::Pending));

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Active);

    $activity = Activity::query()->where('log_name', 'enrolments')->where('subject_id', $enrollment->id)->orderByDesc('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('duplicate payment received');
});

// -----------------------------------------------------------------------
// FIX 5 — the manual proof loop, end to end.
// -----------------------------------------------------------------------

it('lets a parent upload a payment proof for a pending online enrolment', function () {
    Storage::fake('local');

    [$parent, , , $enrollment] = integrationPendingEnrollment();

    $this->actingAs($parent);

    Livewire::test(ProofUpload::class, ['enrollment' => $enrollment])
        ->set('receipt', UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('uploaded', true)
        ->assertSee('Receipt uploaded');

    $payment = GatewayPayment::query()->where('reference', $enrollment->booking_reference)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->gateway)->toBe('manual')
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->proofs()->count())->toBe(1)
        ->and($enrollment->fresh()->status)->toBe(EnrollmentStatus::Pending);
});

it('forbids uploading a proof for another parent\'s enrolment', function () {
    Storage::fake('local');

    [, , , $enrollment] = integrationPendingEnrollment(integrationParent('Owner Parent', 'owner@example.test'));
    $otherParent = integrationParent('Other Parent', 'other@example.test');

    $this->actingAs($otherParent);

    Livewire::test(ProofUpload::class, ['enrollment' => $enrollment])
        ->assertForbidden();
});

it('lets an admin approve an uploaded proof, activating the enrolment and notifying the parent', function () {
    Storage::fake('local');
    Notification::fake();

    [$parent, , , $enrollment] = integrationPendingEnrollment();

    $this->actingAs($parent);

    Livewire::test(ProofUpload::class, ['enrollment' => $enrollment])
        ->set('receipt', UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'))
        ->call('submit');

    $payment = GatewayPayment::query()->where('reference', $enrollment->booking_reference)->firstOrFail();

    $staff = integrationStaff();
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(ListPayments::class)
        ->callAction(TestAction::make('approvePayment')->table($payment), ['note' => 'Bank transfer verified.'])
        ->assertHasNoTableActionErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($enrollment->fresh()->status)->toBe(EnrollmentStatus::Active);

    Notification::assertSentTo($parent, BookingConfirmed::class);
});

it('lets an admin reject an uploaded proof, keeping the enrolment pending for resubmission', function () {
    Storage::fake('local');
    Notification::fake();

    [$parent, , , $enrollment] = integrationPendingEnrollment();

    $this->actingAs($parent);

    Livewire::test(ProofUpload::class, ['enrollment' => $enrollment])
        ->set('receipt', UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'))
        ->call('submit');

    $payment = GatewayPayment::query()->where('reference', $enrollment->booking_reference)->firstOrFail();

    $staff = integrationStaff();
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(ListPayments::class)
        ->callAction(TestAction::make('rejectPayment')->table($payment), ['reason' => 'Amount does not match.'])
        ->assertHasNoTableActionErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($enrollment->fresh()->status)->toBe(EnrollmentStatus::Pending);

    $this->actingAs($parent);

    // Fetch a fresh model instance: the one above already cached a (now stale)
    // `latestPayment` relation from the first ProofUpload mount, before any
    // payment/proof existed.
    Livewire::test(ProofUpload::class, ['enrollment' => Enrollment::find($enrollment->id)])
        ->assertSet('uploaded', false)
        ->assertSee('Your previous receipt could not be confirmed');
});

it('redirects back with a friendly error when the gateway opens no checkout page', function () {
    config(hostedGatewayConfig([
        'payment-gateway.gateways.nourl' => ['driver' => 'nourl'],
    ]));

    [$parent, , , $enrollment] = integrationPendingEnrollment();

    app(PaymentGatewayManager::class)->extend('nourl', function ($config, HttpClient $http): PaymentGatewayContract {
        return new class implements PaymentGatewayContract
        {
            public function name(): string
            {
                return 'nourl';
            }

            public function createPayment(PaymentRequest $request): PaymentResponse
            {
                // Mirrors toyyibPay refusing a bill: an error reply and no redirect URL.
                return new PaymentResponse(
                    gatewayReference: '',
                    redirectUrl: '',
                    status: PaymentStatus::Pending,
                    raw: ['status' => 'error', 'msg' => 'billPhone parameter is empty'],
                );
            }

            public function verifyCallback(CallbackPayload $payload): PaymentStatusResult
            {
                throw new RuntimeException('not used');
            }

            public function queryStatus(string $gatewayReference): PaymentStatusResult
            {
                throw new RuntimeException('not used');
            }
        };
    });

    $this->actingAs($parent)
        ->post(route('payments.checkout', $enrollment), ['gateway' => 'nourl'])
        ->assertRedirect(route('family.index'))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'billPhone parameter is empty'));

    // No exception page, and the enrolment simply stays pending.
    expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Pending);
});

it('falls back to the guardian phone when the parent account has none', function () {
    config(hostedGatewayConfig([
        'payment-gateway.gateways.phonecheck' => ['driver' => 'phonecheck'],
    ]));

    [$parent, $student, , $enrollment] = integrationPendingEnrollment();
    expect($parent->phone)->toBeNull(); // factory accounts carry no phone
    $student->update(['guardian_phone' => '0111222333']);

    app(PaymentGatewayManager::class)->extend('phonecheck', function ($config, HttpClient $http): PaymentGatewayContract {
        return new class implements PaymentGatewayContract
        {
            public function name(): string
            {
                return 'phonecheck';
            }

            public function createPayment(PaymentRequest $request): PaymentResponse
            {
                expect($request->customer?->phone)->toBe('0111222333');

                return new PaymentResponse(
                    gatewayReference: 'phone-ref',
                    redirectUrl: 'https://gateway.test/pay/phone',
                    status: PaymentStatus::Pending,
                    raw: [],
                );
            }

            public function verifyCallback(CallbackPayload $payload): PaymentStatusResult
            {
                throw new RuntimeException('not used');
            }

            public function queryStatus(string $gatewayReference): PaymentStatusResult
            {
                throw new RuntimeException('not used');
            }
        };
    });

    $this->actingAs($parent)
        ->post(route('payments.checkout', $enrollment), ['gateway' => 'phonecheck'])
        ->assertRedirect('https://gateway.test/pay/phone');
});
