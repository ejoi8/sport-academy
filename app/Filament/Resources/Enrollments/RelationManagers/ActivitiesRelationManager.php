<?php

namespace App\Filament\Resources\Enrollments\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Activity';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->tooltip(fn (Activity $record): ?string => $record->created_at?->toDateTimeString()),
                TextColumn::make('causer.name')
                    ->label('By')
                    ->placeholder('System'),
                TextColumn::make('description')
                    ->label('Event')
                    ->badge(),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->state(function (Activity $record): string {
                        $attributes = data_get($record->properties, 'attributes', []);
                        $old = data_get($record->properties, 'old', []);

                        if (blank($attributes) && blank($old)) {
                            return '—';
                        }

                        $keys = collect(array_unique([...array_keys($attributes), ...array_keys($old)]));

                        return $keys
                            ->map(function (string $key) use ($attributes, $old): string {
                                $before = array_key_exists($key, $old) ? $this->stringify($old[$key]) : '—';
                                $after = array_key_exists($key, $attributes) ? $this->stringify($attributes[$key]) : '—';

                                return "{$key}: {$before} -> {$after}";
                            })
                            ->implode("\n");
                    })
                    ->lineClamp(3)
                    ->wrap(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null || $value === '') {
            return '—';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        return (string) $value;
    }
}
