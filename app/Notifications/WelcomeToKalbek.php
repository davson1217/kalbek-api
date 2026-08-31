<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeToKalbek extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('services.kalbek.frontend_url', 'http://localhost:3000'), '/');

        return (new MailMessage)
            ->subject('Welcome to Kalbek')
            ->greeting('Sveiki atvykę, '.$notifiable->name.'!')
            ->line('Your Kalbek account is ready. You can now practise Lithuanian speaking through short real-life scenes.')
            ->line('We will track your progress over time and use your speaking attempts to help estimate your proficiency level.')
            ->action('Start practising', $frontendUrl)
            ->line('Ačiū for joining us.');
    }
}
