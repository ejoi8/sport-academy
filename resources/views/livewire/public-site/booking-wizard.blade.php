<div class="mx-auto max-w-2xl space-y-5">
    {{-- Always-visible booking context: what you're booking and what it costs. --}}
    @php($theme = $offering->program->theme())
    <section class="fa-card flex items-center gap-4 p-4 sm:p-5">
        <span class="fa-grain relative grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-2xl text-3xl" style="background:linear-gradient(150deg,{{ $theme['from'] }},{{ $theme['to'] }})" aria-hidden="true"><span class="relative drop-shadow">{{ $offering->program->emoji() }}</span></span>
        <div class="min-w-0 flex-1">
            <a href="{{ route('programs.show', $offering->program) }}" class="text-xs font-bold text-slate-400 hover:text-blue-700">← {{ $offering->program->name }}</a>
            <h1 class="truncate text-lg font-extrabold tracking-tight text-slate-900">{{ $offering->scheduleLabel() }}</h1>
            <p class="text-xs font-semibold text-slate-400">{{ $offering->monthLabel() }} · {{ $offering->session_count }} session credits · {{ $offering->seatsLeft() }} seats left</p>
        </div>
        <p class="whitespace-nowrap text-xl font-extrabold tracking-tight text-slate-900"><span class="align-top text-xs font-bold text-slate-400">RM</span>{{ number_format($offering->price_sen / 100, 0) }}</p>
    </section>

    <section class="fa-card p-6 sm:p-7">
        {{-- Stepper: labelled progress segments. --}}
        <div class="mb-7">
            <div class="flex gap-1.5">
                @foreach ([1, 2, 3] as $number)
                    <span class="h-1.5 flex-1 rounded-full {{ $step > $number ? 'bg-emerald-500' : ($step === $number ? 'bg-[linear-gradient(90deg,#2563eb,#1d4ed8)]' : 'bg-slate-200') }}"></span>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[11px] font-bold uppercase tracking-wide">
                @foreach ([1 => 'Child', 2 => 'Your details', 3 => 'Confirm'] as $number => $label)
                    <span class="{{ $step >= $number ? 'text-slate-900' : 'text-slate-300' }}">{{ $label }}</span>
                @endforeach
            </div>
        </div>

        @if ($step === 1)
            <div class="space-y-4">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Who's joining?</h2>
                    <p class="mt-1 text-sm text-slate-500">Pick a child or add a new one.</p>
                </div>

                @guest
                    <div class="flex items-center gap-3 rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm">
                        <span class="text-lg" aria-hidden="true">👋</span>
                        <p class="text-slate-600">Booked with us before? <a href="{{ route('login', ['redirect' => request()->fullUrl()]) }}" class="font-bold text-blue-700 hover:text-blue-800">Log in</a> to pick your child in one tap.</p>
                    </div>
                @endguest

                @auth
                    @if ($existingStudents->isNotEmpty())
                        <div class="flex gap-2 rounded-2xl bg-slate-100 p-1.5 text-sm font-bold">
                            <label class="flex-1 cursor-pointer rounded-xl px-3 py-2 text-center transition {{ $useExistingStudent ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-400' }}">
                                <input type="radio" wire:model.live="useExistingStudent" value="1" class="sr-only">
                                My children
                            </label>
                            <label class="flex-1 cursor-pointer rounded-xl px-3 py-2 text-center transition {{ ! $useExistingStudent ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-400' }}">
                                <input type="radio" wire:model.live="useExistingStudent" value="0" class="sr-only">
                                Add a new child
                            </label>
                        </div>
                    @endif
                @endauth

                @if (auth()->check() && $useExistingStudent)
                    <div class="space-y-2.5">
                        @foreach ($existingStudents as $student)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 p-3.5 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 {{ (int) $existingStudentId === $student->id ? 'border-blue-500 bg-blue-50' : 'border-slate-200' }}">
                                <input type="radio" wire:model="existingStudentId" value="{{ $student->id }}" class="h-4 w-4 accent-blue-600">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white text-sm font-extrabold text-slate-500 shadow-sm">{{ mb_substr($student->name, 0, 1) }}</span>
                                <span class="flex-1">
                                    <span class="block text-sm font-extrabold text-slate-900">{{ $student->name }}</span>
                                    @if ($student->age)
                                        <span class="block text-xs font-semibold text-slate-400">{{ $student->age }} yrs</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                        @error('existingStudentId') <p class="text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="fa-label">Child name</label>
                            <input type="text" wire:model="studentName" class="fa-input" placeholder="e.g. Adam Rahman">
                            @error('studentName') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
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
                        <div>
                            <label class="fa-label">IC / passport <span class="font-medium text-slate-400">(optional)</span></label>
                            <input type="text" wire:model="icNumber" class="fa-input">
                        </div>
                        <div>
                            <label class="fa-label">Guardian phone <span class="font-medium text-slate-400">(optional)</span></label>
                            <input type="text" wire:model="guardianPhone" class="fa-input">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="fa-label">Guardian name <span class="font-medium text-slate-400">(optional)</span></label>
                            <input type="text" wire:model="guardianName" class="fa-input" placeholder="Defaults to your account name">
                        </div>
                    </div>
                @endif
            </div>
        @elseif ($step === 2)
            <div class="space-y-4">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Your details</h2>
                    <p class="mt-1 text-sm text-slate-500">We'll send the booking confirmation here.</p>
                </div>

                @auth
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-white text-sm font-extrabold text-blue-700 shadow-sm">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                        <div>
                            <p class="font-extrabold text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="text-slate-500">{{ auth()->user()->email }}@if (auth()->user()->phone) · {{ auth()->user()->phone }}@endif</p>
                        </div>
                    </div>

                    @if (blank(auth()->user()->phone))
                        <div>
                            <label class="fa-label">Contact phone</label>
                            <input type="text" wire:model="accountPhone" class="fa-input" placeholder="e.g. 012-345 6789">
                            <p class="mt-1 text-xs text-slate-400">Needed for the payment provider and class updates — we'll save it to your account.</p>
                            @error('accountPhone') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                @else
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="fa-label">Your name</label>
                            <input type="text" wire:model="accountName" class="fa-input">
                            @error('accountName') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="fa-label">Email</label>
                            <input type="email" wire:model="accountEmail" class="fa-input">
                            @error('accountEmail') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
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
                    <p class="text-sm text-slate-500">Already have an account? <a href="{{ route('login', ['redirect' => request()->fullUrl()]) }}" class="font-bold text-blue-700 hover:text-blue-800">Log in first</a>.</p>
                @endauth
            </div>
        @elseif ($step === 3)
            <div class="space-y-5">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Confirm your booking</h2>
                    <p class="mt-1 text-sm text-slate-500">One last look before we reserve the spot.</p>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <div class="flex items-center justify-between px-4 py-3 text-sm"><span class="text-slate-500">Class</span><span class="font-extrabold text-slate-900">{{ $offering->program->name }}</span></div>
                    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"><span class="text-slate-500">Schedule</span><span class="font-bold text-slate-900">{{ $offering->scheduleLabel() }}</span></div>
                    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"><span class="text-slate-500">Month</span><span class="font-bold text-slate-900">{{ $offering->monthLabel() }}</span></div>
                    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"><span class="text-slate-500">Includes</span><span class="font-bold text-slate-900">{{ $offering->session_count }} session credits</span></div>
                    <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3.5"><span class="text-sm font-bold text-slate-600">Total</span><span class="text-lg font-extrabold tracking-tight text-slate-900">RM{{ number_format($offering->price_sen / 100, 2) }}</span></div>
                </div>

                <div class="rounded-2xl bg-[#f6f8fb] p-4 text-sm leading-6 text-slate-500">
                    <p class="mb-1 font-extrabold text-slate-900">🎟️ How your credits work</p>
                    Each month buys {{ $offering->session_count }} session credits. Present, late and absent each use one — excused doesn't. Unused credits carry over as free make-ups in the same program.
                </div>

                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border-2 border-slate-200 p-4 text-sm text-slate-600 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                    <input type="checkbox" wire:model="agreedToPolicy" class="mt-0.5 h-4 w-4 accent-blue-600">
                    <span>I understand how session credits, absences, carry-over, and same-program make-ups work.</span>
                </label>
                @error('agreedToPolicy') <p class="text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                @error('submit') <p class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $message }}</p> @enderror

                <p class="text-xs leading-5 text-slate-400">{{ \App\Support\PaymentInstructions::summary($selectedGateway ?? null) }}</p>
            </div>
        @else
            <div class="space-y-5 text-center">
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-50">
                    <svg class="h-8 w-8 stroke-emerald-600" viewBox="0 0 24 24" fill="none" stroke-width="2.6"><path d="M20 6L9 17l-5-5"/></svg>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Booking received 🎉</h2>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                        The spot is reserved and pending payment confirmation.
                        @if ($gatewayEnabled) Pay now to lock it in. @endif
                    </p>
                </div>

                {{-- The reference, styled as the ticket it really is. --}}
                <div class="fa-ticket mx-auto max-w-sm border border-slate-200 px-6 py-4">
                    <div class="flex items-center justify-center gap-2 text-xs font-bold text-slate-400">
                        <span aria-hidden="true">{{ $offering->program->emoji() }}</span>
                        {{ $offering->program->name }} · {{ $offering->monthLabel() }}
                    </div>
                    <div class="my-3 border-t-2 border-dashed border-slate-200"></div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Booking reference</p>
                    <p class="mt-1 text-2xl font-extrabold tracking-wide text-slate-900">{{ $completedReference }}</p>
                </div>

                @if ($gatewayEnabled && $completedEnrollmentId)
                    <form method="POST" action="{{ route('payments.checkout', $completedEnrollmentId) }}" class="space-y-3 text-left">
                        @csrf
                        <div>
                            <label for="booking-gateway" class="fa-label">Payment provider</label>
                            <select id="booking-gateway" wire:model="selectedGateway" name="gateway" class="fa-input">
                                @foreach ($gatewayOptions as $gateway => $label)
                                    <option value="{{ $gateway }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="fa-btn-primary w-full py-3 text-base">Pay now · RM{{ number_format($offering->price_sen / 100, 2) }}</button>
                    </form>
                @endif

                <div class="space-y-1.5 rounded-2xl bg-[#f6f8fb] p-4 text-left text-sm leading-6 text-slate-500">
                    @foreach ($paymentInstructions as $line)
                        <p>{{ $line }}</p>
                    @endforeach
                </div>

                <a href="{{ route('family.index') }}" class="inline-flex text-sm font-bold text-slate-500 hover:text-blue-700">Go to My Family →</a>
            </div>
        @endif

        @if ($step < 4)
            <div class="mt-8 flex items-center gap-3">
                @if ($step > 1)
                    <button type="button" wire:click="previousStep" class="fa-btn-ghost px-5">Back</button>
                @endif
                @if ($step < 3)
                    <button type="button" wire:click="nextStep" class="fa-btn-primary flex-1 py-3 text-base">Continue</button>
                @else
                    <button type="button" wire:click="submit" wire:loading.attr="disabled" class="fa-btn-primary flex-1 py-3 text-base disabled:opacity-60">
                        <span wire:loading.remove wire:target="submit">Confirm booking</span>
                        <span wire:loading wire:target="submit">Reserving…</span>
                    </button>
                @endif
            </div>
        @endif
    </section>
</div>
