<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\Offerings\OfferingResource;
use App\Models\User;
use App\Support\Reporting\AttendanceSummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AttendanceReport extends Page
{
    protected string $view = 'filament.pages.reports.attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $title = 'Attendance & delivery';

    protected static ?int $navigationSort = 3;

    public string $period = '';

    public ?int $coachFilter = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin', 'coach']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
    }

    /** Admins see everyone (with an optional coach filter); a plain coach only ever sees their own. */
    public function isAdmin(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    /**
     * @return array<int, string>
     */
    public function coachOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'coach'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function periodOptions(): array
    {
        return OfferingResource::monthOptions();
    }

    protected function resolveCoachId(): ?int
    {
        return $this->isAdmin() ? ($this->coachFilter ?: null) : (int) auth()->id();
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return AttendanceSummary::for($this->period, $this->resolveCoachId());
    }

    /**
     * @return array<string, int|string>
     */
    protected function reportParams(?string $extra = null): array
    {
        return array_filter([
            'period' => $this->period,
            'coach' => $this->isAdmin() ? $this->coachFilter : null,
            'format' => $extra,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('reports.attendance', $this->reportParams()))
                ->openUrlInNewTab(),
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (): string => route('reports.attendance', $this->reportParams('csv')))
                ->openUrlInNewTab(),
        ];
    }
}
