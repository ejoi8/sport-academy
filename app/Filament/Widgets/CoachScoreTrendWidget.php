<?php

namespace App\Filament\Widgets;

use App\Models\AssessmentScore;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class CoachScoreTrendWidget extends ChartWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected ?string $heading = 'My average score (1–5) — last 6 months';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['coach', 'super_admin']) ?? false;
    }

    protected function getData(): array
    {
        $coachId = auth()->id();

        $data = [];
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);

            $avg = AssessmentScore::whereHas('attendance', fn (Builder $q) => $q
                ->where('coach_id', $coachId)
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]))
                ->avg('score');

            $data[] = $avg ? round($avg, 2) : 0;
            $labels[] = $month->format('M Y');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Avg score',
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['min' => 0, 'max' => 5]]];
    }
}
