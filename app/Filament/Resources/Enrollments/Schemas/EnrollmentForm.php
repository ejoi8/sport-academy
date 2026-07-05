<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Enums\EnrollmentStatus;
use App\Models\Offering;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class EnrollmentForm
{
    /**
     * @param  bool  $includeStudent  Show the student picker (omit inside a student's relation manager).
     * @param  bool  $includeOffering  Show the timeslot picker (omit inside a timeslot's relation manager).
     */
    public static function configure(Schema $schema, bool $includeStudent = true, bool $includeOffering = true): Schema
    {
        return $schema
            ->components(array_values(array_filter([
                $includeStudent ? Select::make('student_id')
                    ->relationship('student', 'name')
                    ->required()
                    ->searchable()
                    ->preload() : null,
                $includeOffering ? Select::make('offering_id')
                    ->label('Timeslot')
                    ->relationship('offering', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Offering $record): string => $record->program?->name.' · '.$record->scheduleLabel().' · '.Carbon::parse($record->period.'-01')->format('M Y'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false) : null,
                Select::make('status')
                    ->options(EnrollmentStatus::class)
                    ->default(EnrollmentStatus::Active->value)
                    ->required()
                    ->native(false),
                TextInput::make('price_sen')
                    ->label('Price (RM)')
                    ->numeric()
                    ->prefix('RM')
                    ->minValue(0)
                    ->default(0)
                    ->disabled(fn ($record): bool => $record?->snapshotsLocked() ?? false)
                    ->helperText(fn ($record): ?string => $record?->snapshotsLocked()
                        ? 'Locked — sessions have been recorded against this enrolment. Adjust by cancelling and creating a new enrolment, or record the deal offline.'
                        : null)
                    ->required()
                    ->formatStateUsing(fn (?int $state): string => number_format(($state ?? 0) / 100, 2, '.', ''))
                    ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                TextInput::make('sessions_included')
                    ->label('Session credits')
                    ->numeric()
                    ->minValue(0)
                    ->default(4)
                    ->disabled(fn ($record): bool => $record?->snapshotsLocked() ?? false)
                    ->helperText(fn ($record): ?string => $record?->snapshotsLocked()
                        ? 'Locked — sessions have been recorded against this enrolment. Adjust by cancelling and creating a new enrolment, or record the deal offline.'
                        : null)
                    ->required(),
                DatePicker::make('credits_expire_at')
                    ->label('Credits expire on')
                    ->helperText('Leave blank to never expire.')
                    ->native(false),
            ])));
    }
}
