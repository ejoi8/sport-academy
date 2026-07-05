<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmed extends Notification
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

        return (new MailMessage)
            ->subject('Booking confirmed: '.$this->enrollment->booking_reference)
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your booking for '.$program.' has been confirmed.')
            ->line('Booking reference: '.$this->enrollment->booking_reference)
            ->action('View my family', route('family.index'));
    }
}
