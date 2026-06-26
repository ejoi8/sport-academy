<?php

namespace App\Settings;

use App\Enums\PaymentMode;
use Spatie\LaravelSettings\Settings;

class AcademySettings extends Settings
{
    public ?int $head_coach_user_id;

    public bool $parent_top_performer_visible;

    public PaymentMode $payment_mode;

    public string $default_gateway;

    public bool $advanced_reports_enabled;

    public bool $free_first_month;

    public static function group(): string
    {
        return 'academy';
    }
}
