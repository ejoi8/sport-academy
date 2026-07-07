<?php

namespace App\Filament\Resources\Enrollments\Tables;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Filament\Resources\Offerings\OfferingResource;
use App\Models\Enrollment;
use App\Support\DeletionGuard;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        // The attendance statuses that consume a credit, as a SQL list for the "remaining" filter.
        $consuming = "'".implode("','", Enrollment::CREDIT_CONSUMING_STATUSES)."'";
        // Statuses that count as actually turning up, for the "sessions attended" filter.
        $attended = "'".implode("','", [AttendanceStatus::Present->value, AttendanceStatus::Late->value])."'";

        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('offering.program')
                ->withCount(['attendances as used_credits' => fn (Builder $q) => $q->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)]))
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('offering')
                    ->label('Timeslot')
                    ->state(fn (Enrollment $record): string => $record->offering
                        ? $record->offering->program?->name.' · '.$record->offering->scheduleLabel().' · '.Carbon::parse($record->offering->period.'-01')->format('M Y')
                        : '—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'online' ? 'info' : 'gray'),
                TextColumn::make('booking_reference')
                    ->label('Reference')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('price_sen')
                    ->label('Price')
                    ->money('MYR', divideBy: 100),
                TextColumn::make('credits')
                    ->label('Credits used')
                    ->badge()
                    ->color(function (Enrollment $record): string {
                        $used = (int) $record->used_credits;

                        return $used > $record->sessions_included
                            ? 'danger'
                            : ($record->sessions_included > 0 && $used >= $record->sessions_included ? 'warning' : 'gray');
                    })
                    ->state(function (Enrollment $record): string {
                        $used = (int) $record->used_credits;
                        $state = $used.' / '.$record->sessions_included;

                        return $used > $record->sessions_included
                            ? $state.' (+'.($used - $record->sessions_included).' over)'
                            : $state;
                    })
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('used_credits', $direction)),
                TextColumn::make('created_at')
                    ->label('Enrolled')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('unfinished')
                    ->label('Has credits remaining')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', ['active', 'pending', 'overdue'])
                        ->whereRaw("sessions_included > (select count(*) from attendances where attendances.enrollment_id = enrollments.id and attendances.status in ({$consuming}))")),
                Filter::make('over_delivered')
                    ->label('Over-delivered (past paid sessions)')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereRaw("(select count(*) from attendances where attendances.enrollment_id = enrollments.id and attendances.status in ({$consuming})) > sessions_included")),
                Filter::make('pending_stale')
                    ->label('Pending > 7 days')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', EnrollmentStatus::Pending->value)
                        ->whereDate('created_at', '<=', now()->subDays(7)->toDateString())),
                Filter::make('attended')
                    ->label('Sessions attended')
                    ->schema([
                        Select::make('operator')
                            ->label('Comparison')
                            ->options(['gte' => 'at least', 'lte' => 'at most', 'eq' => 'exactly'])
                            ->default('gte')
                            ->native(false),
                        TextInput::make('count')
                            ->label('Sessions')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function (Builder $query, array $data) use ($attended): Builder {
                        if (blank($data['count'] ?? null)) {
                            return $query;
                        }

                        $operator = ['gte' => '>=', 'lte' => '<=', 'eq' => '='][$data['operator'] ?? 'gte'] ?? '>=';

                        return $query->whereRaw(
                            "(select count(*) from attendances where attendances.enrollment_id = enrollments.id and attendances.status in ({$attended})) {$operator} ?",
                            [(int) $data['count']],
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (blank($data['count'] ?? null)) {
                            return null;
                        }

                        $label = ['gte' => 'at least', 'lte' => 'at most', 'eq' => 'exactly'][$data['operator'] ?? 'gte'] ?? 'at least';

                        return 'Attended '.$label.' '.$data['count'];
                    }),
                SelectFilter::make('status')
                    ->options(EnrollmentStatus::class),
                SelectFilter::make('period')
                    ->label('Month')
                    ->options(OfferingResource::monthOptions())
                    ->default(now()->format('Y-m'))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('offering', fn (Builder $offering): Builder => $offering->where('period', $data['value']))
                        : $query),
                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(route('reports.enrollments.csv'))
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, Collection $records): void {
                            if ($message = DeletionGuard::firstBlockedMessage($records)) {
                                DeletionGuard::halt($action, $message);
                            }
                        }),
                    ForceDeleteBulkAction::make()
                        ->before(function (ForceDeleteBulkAction $action, Collection $records): void {
                            if ($message = DeletionGuard::firstBlockedMessage($records)) {
                                DeletionGuard::halt($action, $message);
                            }
                        }),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
