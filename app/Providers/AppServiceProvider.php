<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $helpers = app_path('Support/helpers.php');

        if (is_file($helpers)) {
            require_once $helpers;
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $timezone = system_timezone_name();
        date_default_timezone_set($timezone);
        config(['app.timezone' => $timezone]);

        AppNotification::created(function (AppNotification $notification): void {
            ActivityLog::query()->create([
                'user_id' => $notification->user_id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => strtolower((string) $notification->type),
                'module' => match (strtolower((string) $notification->type)) {
                    'csv_import' => 'imports',
                    'order_status' => 'orders',
                    'call_center' => 'calls',
                    'return_status' => 'returns',
                    'payment_failed' => 'payments',
                    default => 'system',
                },
                'severity' => match (strtolower((string) $notification->type)) {
                    'call_center', 'return_status' => 'warning',
                    'payment_failed' => 'danger',
                    default => 'success',
                },
                'data' => array_merge(
                    is_array($notification->data) ? $notification->data : [],
                    [
                        'ip_address' => request()?->ip(),
                    ]
                ),
            ]);
        });
    }
}
