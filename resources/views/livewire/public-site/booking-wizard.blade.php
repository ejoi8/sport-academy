<div class="space-y-6">
    <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ route('programs.show', $offering->program) }}" class="text-sm text-zinc-500 hover:text-zinc-800">{{ $offering->program->name }}</a>
                <h1 class="mt-3 text-3xl font-semibold text-zinc-950">Book {{ $offering->program->name }}</h1>
                <p class="mt-2 text-sm text-zinc-500">{{ $offering->scheduleLabel() }} · {{ $offering->monthLabel() }}</p>
            </div>
            <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-5 py-4">
                <p class="text-xs uppercase tracking-wide text-emerald-700">Seats left</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ $offering->seatsLeft() }}</p>
            </div>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="mb-6 flex items-center gap-2 text-sm text-zinc-500">
                @foreach ([1 => 'Child', 2 => 'Account', 3 => 'Review', 4 => 'Done'] as $number => $label)
                    <span class="inline-flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full {{ $step >= $number ? 'bg-zinc-950 text-white' : 'bg-zinc-100 text-zinc-500' }}">{{ $number }}</span>
                        <span>{{ $label }}</span>
                    </span>
                @endforeach
            </div>

            @if ($step === 1)
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold text-zinc-950">Step 1 · Your child</h2>

                    @auth
                        @if ($existingStudents->isNotEmpty())
                            <div class="flex flex-wrap gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-zinc-700">
                                    <input type="radio" wire:model.live="useExistingStudent" value="1">
                                    Choose existing child
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-zinc-700">
                                    <input type="radio" wire:model.live="useExistingStudent" value="0">
                                    Add new child
                                </label>
                            </div>
                        @endif
                    @endauth

                    @if (auth()->check() && $useExistingStudent)
                        <div>
                            <label class="block text-sm font-medium text-zinc-700">Child</label>
                            <select wire:model="existingStudentId" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                                <option value="">Choose a child</option>
                                @foreach ($existingStudents as $student)
                                    <option value="{{ $student->id }}">{{ $student->name }}</option>
                                @endforeach
                            </select>
                            @error('existingStudentId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-zinc-700">Child name</label>
                                <input type="text" wire:model="studentName" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                                @error('studentName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700">Guardian name</label>
                                <input type="text" wire:model="guardianName" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700">Guardian phone</label>
                                <input type="text" wire:model="guardianPhone" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700">IC / passport</label>
                                <input type="text" wire:model="icNumber" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700">Date of birth</label>
                                <input type="date" wire:model="dob" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700">Gender</label>
                                <select wire:model="gender" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                                    <option value="">Choose</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif ($step === 2)
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold text-zinc-950">Step 2 · Your account</h2>
                    @auth
                        <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700">
                            <p class="font-medium text-zinc-950">{{ auth()->user()->name }}</p>
                            <p class="mt-1">{{ auth()->user()->email }}</p>
                            @if (auth()->user()->phone)
                                <p class="mt-1">{{ auth()->user()->phone }}</p>
                            @endif
                        </div>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-zinc-700">Your name</label>
                                <input type="text" wire:model="accountName" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                                @error('accountName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-zinc-700">Email</label>
                                <input type="email" wire:model="accountEmail" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                                @error('accountEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-zinc-700">Phone</label>
                                <input type="text" wire:model="accountPhone" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700">Password</label>
                                <input type="password" wire:model="password" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700">Confirm password</label>
                                <input type="password" wire:model="passwordConfirmation" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                            </div>
                        </div>
                        <p class="text-sm text-zinc-500">Already have an account? <a href="{{ route('login', ['redirect' => request()->fullUrl()]) }}" class="text-emerald-700 hover:text-emerald-800">Log in first</a>.</p>
                    @endauth
                </div>
            @elseif ($step === 3)
                <div class="space-y-5">
                    <h2 class="text-xl font-semibold text-zinc-950">Step 3 · Review and agree</h2>
                    <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700">
                        <p class="font-medium text-zinc-950">{{ $offering->program->name }}</p>
                        <p class="mt-1">{{ $offering->scheduleLabel() }} · {{ $offering->monthLabel() }}</p>
                        <p class="mt-1">RM{{ number_format($offering->price_sen / 100, 2) }} · {{ $offering->session_count }} session credits</p>
                    </div>

                    <div class="space-y-2 rounded-md border border-zinc-200 bg-white p-4">
                        <p class="text-sm font-medium text-zinc-950">Session-credit rules</p>
                        <ul class="space-y-2 text-sm leading-6 text-zinc-600">
                            <li>Each monthly registration buys a set number of session credits.</li>
                            <li>Present, late, and absent all use a credit. Excused does not.</li>
                            <li>Unused credits carry over.</li>
                            <li>Carry-over make-ups are same-program only.</li>
                        </ul>
                    </div>

                    <label class="flex items-start gap-3 rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700">
                        <input type="checkbox" wire:model="agreedToPolicy" class="mt-0.5">
                        <span>I understand how session credits, absences, carry-over, and same-program make-ups work.</span>
                    </label>
                    @error('agreedToPolicy') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('submit') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @else
                <div class="space-y-4">
                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">4</div>
                    <h2 class="text-2xl font-semibold text-zinc-950">Booking received</h2>
                    <p class="text-sm text-zinc-600">
                        Your booking is now pending payment confirmation.
                        @if ($gatewayEnabled)
                            You can pay online right away.
                        @endif
                    </p>
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs uppercase tracking-wide text-emerald-700">Booking reference</p>
                        <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ $completedReference }}</p>
                    </div>
                    @if ($gatewayEnabled && $completedEnrollmentId)
                        <form method="POST" action="{{ route('payments.checkout', $completedEnrollmentId) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label for="booking-gateway" class="block text-sm font-medium text-zinc-700">Payment provider</label>
                                <select id="booking-gateway" wire:model="selectedGateway" name="gateway" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2">
                                    @foreach ($gatewayOptions as $gateway => $label)
                                        <option value="{{ $gateway }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="inline-flex rounded-md bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">
                                Pay now
                            </button>
                        </form>
                    @endif
                    <div class="space-y-2 rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700">
                        @foreach ($paymentInstructions as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    </div>
                    <a href="{{ route('family.index') }}" class="inline-flex rounded-md bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800">Go to My Family</a>
                </div>
            @endif

            @if ($step < 4)
                <div class="mt-8 flex items-center justify-between">
                    <button type="button" wire:click="previousStep" class="rounded-md px-4 py-2 text-sm text-zinc-600 hover:bg-zinc-100" @disabled($step === 1)>Back</button>
                    @if ($step < 3)
                        <button type="button" wire:click="nextStep" class="rounded-md bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800">Continue</button>
                    @else
                        <button type="button" wire:click="submit" class="rounded-md bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Send booking</button>
                    @endif
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-zinc-950">Class summary</h2>
                <dl class="mt-4 space-y-3 text-sm text-zinc-700">
                    <div class="flex items-center justify-between">
                        <dt>Program</dt>
                        <dd class="font-medium text-zinc-950">{{ $offering->program->name }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt>Schedule</dt>
                        <dd class="font-medium text-zinc-950">{{ $offering->scheduleLabel() }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt>Month</dt>
                        <dd class="font-medium text-zinc-950">{{ $offering->monthLabel() }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt>Monthly fee</dt>
                        <dd class="font-medium text-zinc-950">RM{{ number_format($offering->price_sen / 100, 2) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-zinc-950">Payment</h2>
                <p class="mt-3 text-sm leading-6 text-zinc-600">{{ \App\Support\PaymentInstructions::summary($selectedGateway ?? null) }}</p>
            </div>
        </aside>
    </section>
</div>
