<?php

namespace App\Filament\Resources\Enrollments\RelationManagers;

use App\Models\GatewayPayment;
use Ejoi\PaymentGateway\Enums\PaymentStatus;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only payment trail for this enrolment (only online bookings have any — an admin-created
 * enrolment has no booking reference and so no payments). Approving / rejecting / recording
 * payments lives on the Payments resource under Finance; this is just a window onto the history.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedBanknotes;

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Attempted')
                    ->dateTime('d M Y H:i'),
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
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('viewProof')
                    ->label('View proof')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->color('gray')
                    ->url(fn (GatewayPayment $record): ?string => ($proof = $record->proofs()->latest()->first())
                        ? route('payments.proofs.show', $proof)
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (GatewayPayment $record): bool => $record->gateway === 'manual' && $record->proofs()->exists()),
            ])
            ->toolbarActions([]);
    }
}
