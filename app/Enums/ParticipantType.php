<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ParticipantType: string implements HasColor, HasLabel
{
    case Enrolled = 'enrolled';
    case MakeUp = 'make_up';
    case WalkIn = 'walk_in';

    public function getLabel(): string
    {
        return match ($this) {
            self::Enrolled => 'Enrolled',
            self::MakeUp => 'Make-up',
            self::WalkIn => 'Walk-in',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Enrolled => 'success',
            self::MakeUp => 'info',
            self::WalkIn => 'warning',
        };
    }
}
