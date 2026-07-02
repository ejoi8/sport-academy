<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScheduleType: string implements HasLabel
{
    case Recurring = 'recurring';
    case OneOff = 'one_off';

    public function getLabel(): string
    {
        return match ($this) {
            self::Recurring => 'Recurring (weekly)',
            self::OneOff => 'One-off (specific date)',
        };
    }
}
