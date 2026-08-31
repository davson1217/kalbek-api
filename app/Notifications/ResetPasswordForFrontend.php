<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordForFrontend extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your Kalbek password')
            ->greeting('Labas!')
            ->line('We received a request to reset your Kalbek password.')
            ->action('Reset password', $this->resetUrl($notifiable->email))
            ->line('This link expires soon. If you did not request it, you can ignore this email.');
    }

    private function resetUrl(string $email): string
    {
        $baseUrl = rtrim(config('services.kalbek.frontend_url', 'http://localhost:3000'), '/');

        return $baseUrl.'/auth?'.http_build_query([
            'mode' => 'reset',
            'email' => $email,
            'token' => $this->token,
        ]);
    }
}
