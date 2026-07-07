<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\Offerings\OfferingResource;
use App\Support\Reporting\RevenueSummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RevenueReport extends Page
{
    protected string $view = 'filament.pages.reports.revenue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $title = 'Revenue & outstanding';

    protected static ?int $navigationSort = 1;

    public string $period = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
    }

    /**
     * @return array<string, string>
     */
    public function periodOptions(): array
    {
        return OfferingResource::monthOptions();
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return RevenueSummary::for($this->period);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('reports.revenue', ['period' => $this->period]))
                ->openUrlInNewTab(),
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (): string => route('reports.revenue', ['period' => $this->period, 'format' => 'csv']))
                ->openUrlInNewTab(),
        ];
    }
}
