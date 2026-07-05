<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Support\PaymentInstructions;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReceived extends Notification
{
    use Queueable;

    public function __construct(public Enrollment $enrollment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $program = $this->enrollment->offering?->program?->name ?? 'your class';

        $mail = (new MailMessage)
            ->subject('Booking received: '.$this->enrollment->booking_reference)
            ->greeting('Hi '.$notifiable->name.',')
            ->line('We have received your booking for '.$program.'.')
            ->line('Booking reference: '.$this->enrollment->booking_reference);

        foreach (PaymentInstructions::lines() as $line) {
            $mail->line($line);
        }

        return $mail->action('View my family', route('family.index'));
    }
}
