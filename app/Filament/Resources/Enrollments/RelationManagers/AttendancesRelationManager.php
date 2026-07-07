<?php

namespace App\Filament\Resources\Enrollments\RelationManagers;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only list of the sessions that consumed THIS enrolment's credits — including any make-ups
 * from other timeslots that drew on this pool. It explains the credit badge (e.g. why it reads
 * "5/4 · +1 over"). Attendance and scores are written only by RecordTrainingSession, so there is
 * no create/edit/delete here.
 */
class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Sessions (credits used)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('training_session_id', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['trainingSession.offering.program', 'coach', 'scores.skill']))
            ->columns([
                TextColumn::make('trainingSession.session_date')
                    ->label('Date')
                    ->date(),
                TextColumn::make('timeslot')
                    ->label('Timeslot')
                    ->state(fn (Attendance $record): string => $record->trainingSession?->offering?->label() ?? '—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('participant_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('coach.name')
                    ->label('Coach')
                    ->placeholder('—'),
                TextColumn::make('scores_count')
                    ->counts('scores')
                    ->label('Skills scored')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceStatus::class),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->infolist([
                        TextEntry::make('trainingSession.session_date')
                            ->label('Date')
                            ->date(),
                        TextEntry::make('timeslot')
                            ->state(fn (Attendance $record): string => $record->trainingSession?->offering?->label() ?? '—'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('participant_type')
                            ->label('Type')
                            ->badge(),
                        TextEntry::make('coach.name')
                            ->label('Coach')
                            ->placeholder('—'),
                        TextEntry::make('note')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        RepeatableEntry::make('scores')
                            ->label('Assessment')
                            ->schema([
                                TextEntry::make('skill.name')
                                    ->label('Skill'),
                                TextEntry::make('score')
                                    ->badge(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ])
            ->toolbarActions([]);
    }
}
