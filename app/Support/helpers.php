<?php

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('system_currency_info')) {
    function system_currency_info(): array
    {
        $code = strtoupper(trim((string) SystemSetting::query()
            ->where('setting_key', 'currency')
            ->value('setting_value')));

        if ($code === '') {
            $code = 'INR';
        }

        $currencies = [
            'INR' => ['symbol' => '₹', 'label' => 'INR (₹)'],
            'USD' => ['symbol' => '$', 'label' => 'USD ($)'],
            'EUR' => ['symbol' => '€', 'label' => 'EUR (€)'],
            'GBP' => ['symbol' => '£', 'label' => 'GBP (£)'],
        ];

        return array_merge(
            ['code' => $code, 'symbol' => $code . ' ', 'label' => $code],
            $currencies[$code] ?? []
        );
    }
}

if (! function_exists('system_currency_code')) {
    function system_currency_code(): string
    {
        return system_currency_info()['code'];
    }
}

if (! function_exists('system_currency_symbol')) {
    function system_currency_symbol(): string
    {
        return system_currency_info()['symbol'];
    }
}

if (! function_exists('system_currency_label')) {
    function system_currency_label(): string
    {
        return system_currency_info()['label'];
    }
}

if (! function_exists('system_currency_format')) {
    function system_currency_format(float|int|string|null $amount, int $decimals = 2): string
    {
        return system_currency_symbol() . number_format((float) $amount, $decimals);
    }
}

if (! function_exists('system_timezone_info')) {
    function system_timezone_info(): array
    {
        return Cache::remember('spedfly.system.timezone', now()->addMinutes(30), function (): array {
            $timezone = trim((string) SystemSetting::query()
                ->where('setting_key', 'timezone')
                ->value('setting_value'));

            if ($timezone === '') {
                $timezone = 'Asia/Kolkata';
            }

            $options = [
                'Asia/Kolkata' => 'Asia/Kolkata (UTC+5:30)',
                'Asia/Dubai' => 'Asia/Dubai (UTC+4:00)',
                'Europe/London' => 'Europe/London (UTC+0:00)',
                'America/New_York' => 'America/New_York (UTC-5:00)',
            ];

            return [
                'code' => $timezone,
                'label' => $options[$timezone] ?? $timezone,
            ];
        });
    }
}

if (! function_exists('system_timezone_name')) {
    function system_timezone_name(): string
    {
        return system_timezone_info()['code'];
    }
}

if (! function_exists('system_timezone_label')) {
    function system_timezone_label(): string
    {
        return system_timezone_info()['label'];
    }
}
