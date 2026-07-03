<?php

namespace App\Filament\Resources\Offerings\Schemas;

use App\Enums\ScheduleType;
use App\Filament\Resources\Offerings\OfferingResource;
use App\Models\Program;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OfferingForm
{
    /**
     * @param  bool  $includeProgram  Show the program picker. Omit it inside a program's relation
     *                                manager, where the owning program is already implied.
     */
    public static function configure(Schema $schema, bool $includeProgram = true): Schema
    {
        return $schema
            ->components(array_values(array_filter([
                $includeProgram ? Select::make('program_id')
                    ->relationship('program', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->live()
                    // Prefill this month's session count from the chosen program's default.
                    ->afterStateUpdated(fn (Set $set, $state) => $set('session_count', Program::find($state)?->default_sessions ?? 4)) : null,
                Select::make('period')
                    ->label('Month')
                    ->options(OfferingResource::monthOptions())
                    ->default(now()->format('Y-m'))
                    ->required()
                    ->native(false),
                Select::make('schedule_type')
                    ->label('Type')
                    ->options(ScheduleType::class)
                    ->default(ScheduleType::Recurring->value)
                    ->required()
                    ->live()
                    ->native(false),

                // Recurring weekly timeslot.
                Select::make('weekday')
                    ->label('Day of week')
                    ->options([
                        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
                        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
                    ])
                    ->native(false)
                    ->visible(fn (Get $get): bool => self::isRecurring($get))
                    ->required(fn (Get $get): bool => self::isRecurring($get)),
                TimePicker::make('start_time')
                    ->seconds(false)
                    ->visible(fn (Get $get): bool => self::isRecurring($get))
                    ->required(fn (Get $get): bool => self::isRecurring($get)),
                TimePicker::make('end_time')
                    ->seconds(false)
                    ->visible(fn (Get $get): bool => self::isRecurring($get)),

                // One-off clinic.
                DatePicker::make('specific_date')
                    ->native(false)
                    ->visible(fn (Get $get): bool => ! self::isRecurring($get))
                    ->required(fn (Get $get): bool => ! self::isRecurring($get)),

                TextInput::make('capacity')
                    ->numeric()
                    ->minValue(0)
                    ->default(12)
                    ->required(),
                TextInput::make('session_count')
                    ->label('Sessions this month')
                    ->helperText('Usually 4; bump to 5 for a five-week month.')
                    ->numeric()
                    ->minValue(1)
                    ->default(4)
                    ->required(),
                TextInput::make('price_sen')
                    ->label('Price (RM)')
                    ->numeric()
                    ->prefix('RM')
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->formatStateUsing(fn (?int $state): string => number_format(($state ?? 0) / 100, 2, '.', ''))
                    ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                Select::make('default_coach_id')
                    ->label('Head coach')
                    ->relationship('defaultCoach', 'name', fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'coach')))
                    ->searchable()
                    ->preload()
                    ->native(false),
                Toggle::make('is_open')
                    ->label('Open for registration')
                    ->default(true),
            ])));
    }

    /**
     * Whether the chosen schedule type is the recurring weekly one. The form state can be
     * either the enum or its string value depending on context, so normalise both.
     */
    private static function isRecurring(Get $get): bool
    {
        $value = $get('schedule_type');
        $type = $value instanceof ScheduleType ? $value : ScheduleType::tryFrom((string) ($value ?? ''));

        return $type === ScheduleType::Recurring;
    }
}
