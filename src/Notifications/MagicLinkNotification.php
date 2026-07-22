<?php

namespace Webteractive\Passwordless\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $url) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $app = config('passwordless.branding.app_name') ?: config('app.name', 'Application');

        return (new MailMessage)
            ->subject(__('passwordless::passwordless.magic_link.subject', ['app' => $app]))
            ->greeting(__('passwordless::passwordless.magic_link.greeting'))
            ->line(__('passwordless::passwordless.magic_link.intro', ['app' => $app]))
            ->action(__('passwordless::passwordless.magic_link.action'), $this->url)
            ->line(__('passwordless::passwordless.magic_link.outro'));
    }
}
