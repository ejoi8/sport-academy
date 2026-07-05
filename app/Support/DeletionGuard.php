<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Traversable;

class DeletionGuard
{
    public static function firstBlockedMessage(iterable $records): ?string
    {
        if ($records instanceof Traversable) {
            $records = iterator_to_array($records, false);
        }

        foreach ($records as $record) {
            if (! method_exists($record, 'deletionBlockedReason')) {
                continue;
            }

            $message = $record->deletionBlockedReason();

            if (filled($message)) {
                return $message;
            }
        }

        return null;
    }

    public static function halt(object $action, string $message): void
    {
        Notification::make()
            ->danger()
            ->title($message)
            ->send();

        if (method_exists($action, 'halt')) {
            $action->halt();
        }
    }
}
