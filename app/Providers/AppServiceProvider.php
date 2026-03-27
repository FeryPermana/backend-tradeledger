<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verify Your Email - TradeLedger')
                ->greeting('Hello, ' . $notifiable->name . '!')
                ->line('Thank you for registering with TradeLedger.')
                ->line('Please verify your email address to activate your account and continue using your trading and investing dashboard.')
                ->action('Verify Email Address', $url)
                ->line('If you did not create this account, no further action is required.')
                ->salutation('Regards, TradeLedger');
        });
    }
}
