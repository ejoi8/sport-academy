<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\GatewayPayment;
use Ejoi\PaymentGateway\Enums\PaymentStatus;
use Ejoi\PaymentGateway\Laravel\Payments;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('enrollment.student.parent', 'enrollment.offering.program'))
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable(),
                TextColumn::make('enrollment.student.parent.name')
                    ->label('Parent')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('enrollment.student.name')
                    ->label('Child')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('enrollment.offering.program.name')
                    ->label('Program')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->money('MYR', divideBy: 100),
                TextColumn::make('gateway')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::Paid => 'success',
                        PaymentStatus::Pending => 'warning',
                        PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired => 'danger',
                        PaymentStatus::Refunded => 'info',
                        PaymentStatus::Unknown => 'gray',
                    }),
                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—'),
                TextColumn::make('transaction_id')
                    ->label('Transaction')
                    ->searchable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(
                        fn (PaymentStatus $status) => [$status->value => ucfirst($status->value)]
                    )->all()),
                SelectFilter::make('gateway')
                    ->options([
                        'billplz' => 'Billplz',
                        'toyyibpay' => 'toyyibPay',
                        'chip' => 'CHIP',
                        'manual' => 'Manual',
                        'stripe' => 'Stripe',
                        'paypal' => 'PayPal',
                    ]),
            ])
            ->headerActions([
                Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(route('reports.payments.csv'))
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                Action::make('openEnrollment')
                    ->label('Enrolment')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->url(fn (GatewayPayment $record): ?string => $record->enrollment
                        ? EnrollmentResource::getUrl('edit', ['record' => $record->enrollment])
                        : null),
                Action::make('viewProof')
                    ->label('View proof')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->color('gray')
                    ->url(fn (GatewayPayment $record): ?string => ($proof = $record->proofs()->latest()->first())
                        ? route('payments.proofs.show', $proof)
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (GatewayPayment $record): bool => $record->gateway === 'manual' && $record->proofs()->exists()),
                Action::make('approvePayment')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve this payment?')
                    ->modalDescription('This marks the payment as paid and activates the matching enrolment. This cannot be undone from here.')
                    ->modalSubmitActionLabel('Approve')
                    ->visible(fn (GatewayPayment $record): bool => $record->gateway === 'manual'
                        && $record->status->isPending()
                        && $record->proofs()->exists())
                    ->schema([
                        Textarea::make('note')
                            ->label('Note')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->action(function (GatewayPayment $record, array $data, Payments $payments): void {
                        $payments->approve(
                            $record,
                            reviewedBy: auth()->user()?->name,
                            note: $data['note'] ?: null,
                        );

                        Notification::make()
                            ->title('Payment approved')
                            ->success()
                            ->send();
                    }),
                Action::make('rejectPayment')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject this payment?')
                    ->modalDescription('The payment stays pending so the parent can upload a new receipt. The reason is recorded on the payment.')
                    ->modalSubmitActionLabel('Reject')
                    ->visible(fn (GatewayPayment $record): bool => $record->gateway === 'manual'
                        && $record->status->isPending()
                        && $record->proofs()->exists())
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->action(function (GatewayPayment $record, array $data, Payments $payments): void {
                        $payments->reject(
                            $record,
                            reason: $data['reason'] ?: null,
                            reviewedBy: auth()->user()?->name,
                            reupload: true,
                        );

                        Notification::make()
                            ->title('Payment rejected')
                            ->warning()
                            ->send();
                    }),
            ]);
    }
}
