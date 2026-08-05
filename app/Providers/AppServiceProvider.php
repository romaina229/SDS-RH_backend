<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            $frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

            return $frontend . '/reset-password?token=' . urlencode($token)
                . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
