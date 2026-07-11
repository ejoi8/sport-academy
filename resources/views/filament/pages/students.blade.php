<x-filament::page>
    @php($enrollment = $this->enrollment)
    @php($report = $enrollment ? $this->enrollmentReport : null)
    @php($student = $this->student)
    @php($onReport = (bool) $enrollment)
    @php($onDetail = ! $onReport && (bool) $student)
    @php($onList = ! $onReport && ! $onDetail)
    @php($students = $onList ? $this->results : [])
    @php($profile = $onDetail ? $this->profile : null)
    @php($genderOptions = $this->genderOptions)

    <x-coach-shell active="students" :tabs="$onList">

        {{-- ============================ ENROLMENT REPORT SCREEN ============================ --}}
        @if($onReport)
            <div class="rt-bar">
                <button type="button" class="rt-iconbtn" wire:click="backToStudent" aria-label="Back to student">
                    <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
                <div style="flex:1; min-width:0;">
                    <div class="rt-crumb">{{ $student?->name }} · enrolment report</div>
                    <h1 style="font-size:1.05rem;">{{ $enrollment->offering?->program?->name ?? 'Programme' }} · {{ $enrollment->offering?->period ?? '—' }}</h1>
                </div>
            </div>

            @include('filament.pages.partials.students-enrollment', ['enrollment' => $enrollment, 'report' => $report])

        {{-- ============================ PROFILE SCREEN ============================ --}}
        @elseif($onDetail)
            <div class="rt-bar">
                <button type="button" class="rt-iconbtn" wire:click="back" aria-label="Back to students">
                    <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
                <div style="flex:1; min-width:0;">
                    <div class="rt-crumb">Student</div>
                    <h1 style="font-size:1.05rem;">{{ $student->name }}</h1>
                </div>
                {{-- The same printable report parents/admins get — opens in a new tab (route allows coaches). --}}
                <a href="{{ route('students.report', $student) }}" target="_blank" rel="noopener" class="rt-iconbtn" title="View / print report" aria-label="View or print report">
                    <svg viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V9z"/><path d="M14 3v6h6M9 13h6M9 17h4"/></svg>
                </a>
                @if($student->is_active)
                    <span class="rt-status saved"><span class="led"></span>Active</span>
                @else
                    <span class="rt-status off"><span class="led"></span>Inactive</span>
                @endif
            </div>

            @include('filament.pages.partials.students-detail', ['student' => $student, 'profile' => $profile])

        {{-- ============================ LIST SCREEN ============================ --}}
        @else
            <div class="rt-bar">
                <h1>Students</h1>
                {{-- Focus mode hides the panel nav; this leaves the coach console for the full panel. --}}
                <a href="{{ \Filament\Facades\Filament::getUrl() }}" wire:navigate class="rt-iconbtn" title="Exit to dashboard" aria-label="Exit">
                    <svg viewBox="0 0 24 24"><path d="M14 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                </a>
            </div>

            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, IC or guardian…">

            @php($matching = $this->matchingCount)
            <div class="rt-list">
                <div class="rt-listlabel">{{ count($students) < $matching ? 'Showing '.count($students).' of '.$matching : $matching.' student'.($matching === 1 ? '' : 's') }}</div>

                @forelse($students as $s)
                    <button type="button" class="rt-scard" wire:click="openStudent({{ $s['id'] }})">
                        <span class="rt-pav" aria-hidden="true">{{ mb_substr($s['name'], 0, 1) }}</span>
                        <span class="rt-scard-body">
                            <span class="rt-scard-title" style="display:block;">{{ $s['name'] }}</span>
                            <span class="rt-scard-meta">
                                {{ $s['age'] !== null ? $s['age'].' yrs' : 'age —' }} · IC {{ $s['ic'] ?? '—' }}
                            </span>
                        </span>
                        @unless($s['active'])<span class="rt-status off"><span class="led"></span>Inactive</span>@endunless
                    </button>
                @empty
                    <div class="rt-callout">
                        <span class="ball" aria-hidden="true">🧒</span>
                        {{ trim($search) !== '' ? 'No students match "'.trim($search).'".' : 'No students yet — add the first below.' }}
                    </div>
                @endforelse

                @if(count($students) < $matching)
                    <button type="button" class="rt-addbtn" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                        <span wire:loading.remove wire:target="loadMore">Load more · {{ $matching - count($students) }} more</span>
                        <span wire:loading wire:target="loadMore">Loading…</span>
                    </button>
                @endif

                <button type="button" class="rt-scard new" wire:click="startCreate">
                    <span class="rt-plus" aria-hidden="true">＋</span>
                    <span class="rt-scard-body">
                        <span class="rt-scard-title" style="display:block;">Add student</span>
                        <span class="rt-scard-meta">New player record</span>
                    </span>
                </button>
            </div>
        @endif

        {{-- ============================ ADD / EDIT SHEET ============================ --}}
        @if($editing)
            @include('filament.pages.partials.students-form', ['genderOptions' => $genderOptions])
        @endif

    </x-coach-shell>
</x-filament::page>
