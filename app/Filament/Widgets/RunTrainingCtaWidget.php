<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\RunTraining;
use Filament\Widgets\Widget;

/**
 * A bold, full-width shortcut to the Run Training console, pinned to the top of
 * the dashboard so coaches can jump straight into taking a session. Only shown to
 * users who can actually reach the page.
 */
class RunTrainingCtaWidget extends Widget
{
    protected string $view = 'filament.widgets.run-training-cta';

    // Negative sort keeps it above every other dashboard widget.
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return RunTraining::canAccess();
    }

    public function getRunTrainingUrl(): string
    {
        return RunTraining::getUrl();
    }
}
