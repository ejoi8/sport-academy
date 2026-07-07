<?php

namespace App\Filament\Pages\Reports;

use App\Support\Reporting\CreditLiabilitySummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CreditLiabilityReport extends Page
{
    protected string $view = 'filament.pages.reports.credit-liability';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $title = 'Credit liability';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return CreditLiabilitySummary::build();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('reports.credit-liability'))
                ->openUrlInNewTab(),
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (): string => route('reports.credit-liability', ['format' => 'csv']))
                ->openUrlInNewTab(),
        ];
    }
}
