<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Enrollment;
use Ejoi\PaymentGateway\Data\Customer;
use Ejoi\PaymentGateway\Data\Money;
use Ejoi\PaymentGateway\Data\PaymentRequest;
use Ejoi\PaymentGateway\Laravel\Payments;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordOfflinePayment')
                ->label('Record offline payment')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Select::make('enrollment_id')
                        ->label('Enrolment')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            return Enrollment::query()
                                ->with('student.parent', 'offering.program')
                                ->where('status', EnrollmentStatus::Pending)
                                ->where(function ($query) use ($search): void {
                                    $query->where('booking_reference', 'like', "%{$search}%")
                                        ->orWhereHas('student', fn ($student) => $student->where('name', 'like', "%{$search}%"))
                                        ->orWhereHas('offering.program', fn ($program) => $program->where('name', 'like', "%{$search}%"));
                                })
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Enrollment $enrollment) => [$enrollment->id => $this->enrollmentLabel($enrollment)])
                                ->all();
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => ($enrollment = Enrollment::query()->with('student.parent', 'offering.program')->find($value))
                            ? $this->enrollmentLabel($enrollment)
                            : null),
                    Textarea::make('note')
                        ->label('Note')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (array $data, Payments $payments): void {
                    $enrollment = Enrollment::query()
                        ->with('student.parent', 'offering.program')
                        ->findOrFail($data['enrollment_id']);

                    $payment = $payments->create('manual', new PaymentRequest(
                        reference: $enrollment->booking_reference,
                        amount: Money::fromMinor($enrollment->price_sen, 'MYR'),
                        description: sprintf(
                            '%s · %s',
                            $enrollment->offering?->program?->name ?? 'Football Academy booking',
                            $enrollment->booking_reference,
                        ),
                        customer: new Customer(
                            $enrollment->student?->parent?->email,
                            $enrollment->student?->parent?->name,
                            $enrollment->student?->parent?->phone,
                        ),
                        redirectUrl: route('family.index'),
                        metadata: [
                            'enrollment_id' => $enrollment->id,
                            'recorded_offline' => true,
                        ],
                    ));

                    $payments->approve(
                        $payment,
                        reviewedBy: auth()->user()?->name,
                        note: $data['note'] ?: 'Recorded from the staff payments screen.',
                    );

                    Notification::make()
                        ->title('Offline payment recorded')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function enrollmentLabel(Enrollment $enrollment): string
    {
        $reference = $enrollment->booking_reference ?: 'Enrolment #'.$enrollment->id;
        $program = $enrollment->offering?->program?->name ?? 'Program';
        $student = $enrollment->student?->name ?? 'Student';

        return "{$reference} · {$student} · {$program}";
    }
}
