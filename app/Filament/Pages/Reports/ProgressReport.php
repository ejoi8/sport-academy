<?php

namespace App\Filament\Pages\Reports;

use App\Support\Reporting\ProgressSummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ProgressReport extends Page
{
    protected string $view = 'filament.pages.reports.progress';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $title = 'Program progress';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin', 'coach']) ?? false;
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
        return ProgressSummary::build();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(route('reports.progress'))
                ->openUrlInNewTab(),
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('reports.progress', ['format' => 'csv']))
                ->openUrlInNewTab(),
        ];
    }
}
