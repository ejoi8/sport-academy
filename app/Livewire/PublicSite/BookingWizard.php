<?php

namespace App\Livewire\PublicSite;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Student;
use App\Models\User;
use App\Notifications\BookingReceived;
use App\Support\PaymentInstructions;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class BookingWizard extends Component
{
    use WithRateLimiting;

    #[Locked]
    public Offering $offering;

    public int $step = 1;

    public bool $useExistingStudent = true;

    public ?int $existingStudentId = null;

    public string $studentName = '';

    public string $guardianName = '';

    public string $guardianPhone = '';

    public string $icNumber = '';

    public string $dob = '';

    public string $gender = '';

    public string $accountName = '';

    public string $accountEmail = '';

    public string $accountPhone = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $agreedToPolicy = false;

    public ?string $completedReference = null;

    public ?int $completedEnrollmentId = null;

    public bool $gatewayEnabled = false;

    public ?string $selectedGateway = null;

    public function mount(Offering $offering): void
    {
        abort_unless(
            Offering::query()->publiclyBookable()->whereKey($offering->id)->exists(),
            404
        );

        $offering->load('program');
        $this->offering = $offering;
        $this->gatewayEnabled = PaymentInstructions::usesHostedGateway();
        $this->selectedGateway = PaymentInstructions::defaultHostedGateway();

        if (Auth::check()) {
            $this->accountName = (string) Auth::user()->name;
            $this->accountEmail = (string) Auth::user()->email;
            $this->accountPhone = (string) (Auth::user()->phone ?? '');
            $this->useExistingStudent = Auth::user()->students()->exists();
        }
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validateStepOne();
        }

        if ($this->step === 2 && ! Auth::check()) {
            $this->validateStepTwo();
        }

        $this->step = min(4, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function submit(): void
    {
        try {
            $this->rateLimit(5, 60, method: 'submit');
        } catch (TooManyRequestsException $exception) {
            $this->addError('submit', 'Please wait '.$exception->secondsUntilAvailable.' seconds before trying again.');

            return;
        }

        $this->validateStepOne();

        if (! Auth::check()) {
            $this->validateStepTwo();
        }

        $this->validate([
            'agreedToPolicy' => ['accepted'],
        ], [], [
            'agreedToPolicy' => 'policy acknowledgement',
        ]);

        $user = null;
        $enrollment = null;

        DB::transaction(function () use (&$user, &$enrollment): void {
            $freshOffering = Offering::query()
                ->whereKey($this->offering->id)
                ->with('program')
                ->lockForUpdate()
                ->withCount([
                    'enrollments as held_seats_count' => fn ($query) => $query->whereIn('status', ['active', 'pending']),
                ])
                ->firstOrFail();

            if (! $freshOffering->is_open || $freshOffering->capacity <= 0) {
                $this->addError('submit', 'This class is not open for online booking.');

                return;
            }

            if ($freshOffering->isFull()) {
                $this->addError('submit', 'Class full — contact us.');

                return;
            }

            $user = Auth::user() ?? User::create([
                'name' => $this->accountName,
                'email' => $this->accountEmail,
                'phone' => $this->accountPhone,
                'password' => $this->password,
            ]);

            if (! $user->hasRole('parent')) {
                $user->assignRole(Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']));
            }

            if (! Auth::check()) {
                Auth::login($user);

                if (request()->hasSession()) {
                    request()->session()->regenerate();
                }
            } elseif ($this->accountPhone !== (string) ($user->phone ?? '')) {
                $user->forceFill(['phone' => $this->accountPhone])->save();
            }

            $student = $this->resolveStudent($user);

            $existing = Enrollment::withTrashed()
                ->where('student_id', $student->id)
                ->where('offering_id', $freshOffering->id)
                ->first();

            if ($existing) {
                $this->addError('submit', 'This child is already booked into that class.');

                return;
            }

            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'offering_id' => $freshOffering->id,
                'status' => EnrollmentStatus::Pending->value,
                'price_sen' => $freshOffering->price_sen,
                'sessions_included' => $freshOffering->session_count,
                'source' => 'online',
            ]);

            $enrollment->forceFill([
                'booking_reference' => sprintf('BK-%s-%06d', now()->year, $enrollment->id),
            ])->saveQuietly();

            $enrollment->loadMissing('student.parent', 'offering.program');

            $user->notify(new BookingReceived($enrollment));
        });

        if (! $enrollment) {
            return;
        }

        $this->completedReference = $enrollment->booking_reference;
        $this->completedEnrollmentId = $enrollment->id;
        $this->step = 4;
    }

    protected function resolveStudent(User $user): Student
    {
        if ($this->useExistingStudent && filled($this->existingStudentId)) {
            return $user->students()
                ->whereKey($this->existingStudentId)
                ->firstOrFail();
        }

        return Student::create([
            'parent_id' => $user->id,
            'guardian_name' => $this->guardianName ?: $user->name,
            'guardian_phone' => $this->guardianPhone ?: ($user->phone ?? null),
            'name' => $this->studentName,
            'ic_number' => $this->icNumber ?: null,
            'dob' => $this->dob ?: null,
            'gender' => $this->gender ?: null,
            'is_active' => true,
        ]);
    }

    protected function validateStepOne(): void
    {
        if (Auth::check() && $this->useExistingStudent) {
            $this->validate([
                'existingStudentId' => [
                    'required',
                    'integer',
                    Rule::exists('students', 'id')->where(
                        fn ($query) => $query->where('parent_id', Auth::id())->where('is_active', true)
                    ),
                ],
            ]);

            return;
        }

        $this->validate([
            'studentName' => ['required', 'string', 'max:255'],
            'guardianName' => ['nullable', 'string', 'max:255'],
            'guardianPhone' => ['nullable', 'string', 'max:255'],
            'icNumber' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
        ]);
    }

    protected function validateStepTwo(): void
    {
        $this->validate([
            'accountName' => ['required', 'string', 'max:255'],
            'accountEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'accountPhone' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'same:passwordConfirmation'],
            'passwordConfirmation' => ['required', 'string', 'min:8'],
        ]);
    }

    public function paymentInstructions(): array
    {
        return PaymentInstructions::lines();
    }

    public function render()
    {
        $this->offering->loadMissing('program');

        return view('livewire.public-site.booking-wizard', [
            'paymentInstructions' => PaymentInstructions::lines(),
            'existingStudents' => Auth::user()?->students()->where('is_active', true)->orderBy('name')->get() ?? collect(),
            'gatewayEnabled' => $this->gatewayEnabled,
            'gatewayOptions' => PaymentInstructions::hostedGatewayOptions(),
        ])->layout('layouts.public', [
            'title' => 'Book '.$this->offering->program->name,
        ]);
    }
}
