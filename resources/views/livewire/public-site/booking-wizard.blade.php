<div class="space-y-6">
    <section class="fa-card p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ route('programs.show', $offering->program) }}" class="text-sm font-semibold text-slate-500 hover:text-blue-700">← {{ $offering->program->name }}</a>
                <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">Book {{ $offering->program->name }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $offering->scheduleLabel() }} · {{ $offering->monthLabel() }}</p>
            </div>
            <div class="rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Seats left</p>
                <p class="mt-2 text-2xl font-extrabold text-blue-900">{{ $offering->seatsLeft() }}</p>
            </div>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">
        <div class="fa-card p-6">
            <div class="mb-6 flex items-center gap-2 text-sm font-semibold">
                @foreach ([1 => 'Child', 2 => 'Account', 3 => 'Review', 4 => 'Done'] as $number => $label)
                    @if (! $loop->first)
                        <span class="h-px flex-1 {{ $step > $number - 1 ? 'bg-blue-500' : 'bg-slate-200' }}"></span>
                    @endif
                    <span class="inline-flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold {{ $step > $number ? 'bg-emerald-600 text-white' : ($step === $number ? 'bg-[linear-gradient(150deg,#2563eb,#1d4ed8)] text-white' : 'bg-slate-100 text-slate-400') }}">
                            @if ($step > $number)&checkmark;@else{{ $number }}@endif
                        </span>
                        <span class="{{ $step >= $number ? 'text-slate-900' : 'text-slate-400' }}">{{ $label }}</span>
                    </span>
                @endforeach
            </div>

            @if ($step === 1)
                <div class="space-y-4">
                    <h2 class="text-xl font-extrabold text-slate-900">Who's joining?</h2>

                    @auth
                        @if ($existingStudents->isNotEmpty())
                            <div class="flex flex-wrap gap-3">
                                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                    <input type="radio" wire:model.live="useExistingStudent" value="1" class="accent-blue-600">
                                    Choose existing child
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                    <input type="radio" wire:model.live="useExistingStudent" value="0" class="accent-blue-600">
                                    Add new child
                                </label>
                            </div>
                        @endif
                    @endauth

                    @if (auth()->check() && $useExistingStudent)
                        <div>
                            <label class="fa-label">Child</label>
                            <select wire:model="existingStudentId" class="fa-input">
                                <option value="">Choose a child</option>
                                @foreach ($existingStudents as $student)
                                    <option value="{{ $student->id }}">{{ $student->name }}</option>
                                @endforeach
                            </select>
                            @error('existingStudentId') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="fa-label">Child name</label>
                                <input type="text" wire:model="studentName" class="fa-input">
                                @error('studentName') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fa-label">Guardian name</label>
                                <input type="text" wire:model="guardianName" class="fa-input">
                            </div>
                            <div>
                                <label class="fa-label">Guardian phone</label>
                                <input type="text" wire:model="guardianPhone" class="fa-input">
                            </div>
                            <div>
                                <label class="fa-label">IC / passport</label>
                                <input type="text" wire:model="icNumber" class="fa-input">
                            </div>
                            <div>
                                <label class="fa-label">Date of birth</label>
                                <input type="date" wire:model="dob" class="fa-input">
                            </div>
                            <div>
                                <label class="fa-label">Gender</label>
                                <select wire:model="gender" class="fa-input">
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
                    <h2 class="text-xl font-extrabold text-slate-900">Your details</h2>
                    @auth
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                            <p class="font-bold text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="mt-1">{{ auth()->user()->email }}</p>
                            @if (auth()->user()->phone)
                                <p class="mt-1">{{ auth()->user()->phone }}</p>
                            @endif
                        </div>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="fa-label">Your name</label>
                                <input type="text" wire:model="accountName" class="fa-input">
                                @error('accountName') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="fa-label">Email</label>
                                <input type="email" wire:model="accountEmail" class="fa-input">
                                @error('accountEmail') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="fa-label">Phone</label>
                                <input type="text" wire:model="accountPhone" class="fa-input">
                            </div>
                            <div>
                                <label class="fa-label">Password</label>
                                <input type="password" wire:model="password" class="fa-input">
                                @error('password') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fa-label">Confirm password</label>
                                <input type="password" wire:model="passwordConfirmation" class="fa-input">
                            </div>
                        </div>
                        <p class="text-sm text-slate-500">Already have an account? <a href="{{ route('login', ['redirect' => request()->fullUrl()]) }}" class="font-semibold text-blue-700 hover:text-blue-800">Log in first</a>.</p>
                    @endauth
                </div>
            @elseif ($step === 3)
                <div class="space-y-5">
                    <h2 class="text-xl font-extrabold text-slate-900">Review &amp; agree</h2>
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <div class="flex items-center justify-between px-4 py-3 text-sm"><span class="text-slate-500">Program</span><span class="font-bold text-slate-900">{{ $offering->program->name }}</span></div>
                        <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"><span class="text-slate-500">Class</span><span class="font-bold text-slate-900">{{ $offering->scheduleLabel() }}</span></div>
                        <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"><span class="text-slate-500">Month</span><span class="font-bold text-slate-900">{{ $offering->monthLabel() }}</span></div>
                        <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-4 py-3 text-sm"><span class="text-slate-500">Total · {{ $offering->session_count }} credits</span><span class="text-base font-extrabold text-slate-900">RM{{ number_format($offering->price_sen / 100, 2) }}</span></div>
                    </div>

                    <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-sm font-bold text-slate-900">Session-credit rules</p>
                        <ul class="space-y-2 text-sm leading-6 text-slate-500">
                            <li>Each monthly registration buys a set number of session credits.</li>
                            <li>Present, late, and absent all use a credit. Excused does not.</li>
                            <li>Unused credits carry over.</li>
                            <li>Carry-over make-ups are same-program only.</li>
                        </ul>
                    </div>

                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        <input type="checkbox" wire:model="agreedToPolicy" class="mt-0.5 accent-blue-600">
                        <span>I understand how session credits, absences, carry-over, and same-program make-ups work.</span>
                    </label>
                    @error('agreedToPolicy') <p class="text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                    @error('submit') <p class="text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
            @else
                <div class="space-y-4">
                    <div class="grid h-14 w-14 place-items-center rounded-full bg-emerald-50">
                        <svg class="h-7 w-7 stroke-emerald-600" viewBox="0 0 24 24" fill="none" stroke-width="2.6"><path d="M20 6L9 17l-5-5"/></svg>
                    </div>
                    <h2 class="text-2xl font-extrabold text-slate-900">Booking received</h2>
                    <p class="text-sm text-slate-500">
                        Your booking is now pending payment confirmation.
                        @if ($gatewayEnabled)
                            You can pay online right away.
                        @endif
                    </p>
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-center">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Booking reference</p>
                        <p class="mt-1 text-2xl font-extrabold tracking-wide text-slate-900">{{ $completedReference }}</p>
                    </div>
                    @if ($gatewayEnabled && $completedEnrollmentId)
                        <form method="POST" action="{{ route('payments.checkout', $completedEnrollmentId) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label for="booking-gateway" class="fa-label">Payment provider</label>
                                <select id="booking-gateway" wire:model="selectedGateway" name="gateway" class="fa-input">
                                    @foreach ($gatewayOptions as $gateway => $label)
                                        <option value="{{ $gateway }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="fa-btn-primary">Pay now</button>
                        </form>
                    @endif
                    <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        @foreach ($paymentInstructions as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    </div>
                    <a href="{{ route('family.index') }}" class="fa-btn-ghost">Go to My Family</a>
                </div>
            @endif

            @if ($step < 4)
                <div class="mt-8 flex items-center justify-between">
                    <button type="button" wire:click="previousStep" class="rounded-xl px-4 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 disabled:opacity-40" @disabled($step === 1)>Back</button>
                    @if ($step < 3)
                        <button type="button" wire:click="nextStep" class="fa-btn-primary">Continue</button>
                    @else
                        <button type="button" wire:click="submit" class="fa-btn-primary">Send booking</button>
                    @endif
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="fa-card p-5">
                <h2 class="text-base font-extrabold text-slate-900">Class summary</h2>
                <dl class="mt-4 space-y-3 text-sm text-slate-600">
                    <div class="flex items-center justify-between"><dt>Program</dt><dd class="font-bold text-slate-900">{{ $offering->program->name }}</dd></div>
                    <div class="flex items-center justify-between"><dt>Schedule</dt><dd class="font-bold text-slate-900">{{ $offering->scheduleLabel() }}</dd></div>
                    <div class="flex items-center justify-between"><dt>Month</dt><dd class="font-bold text-slate-900">{{ $offering->monthLabel() }}</dd></div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-3"><dt>Monthly fee</dt><dd class="text-base font-extrabold text-slate-900">RM{{ number_format($offering->price_sen / 100, 2) }}</dd></div>
                </dl>
            </div>

            <div class="fa-card p-5">
                <h2 class="text-base font-extrabold text-slate-900">Payment</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">{{ \App\Support\PaymentInstructions::summary($selectedGateway ?? null) }}</p>
            </div>
        </aside>
    </section>
</div>
