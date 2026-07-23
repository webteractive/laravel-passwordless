<?php

namespace Webteractive\Passwordless\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly string $code,
        public readonly int $ttl,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $app = config('passwordless.branding.app_name') ?: config('app.name', 'Application');
        $minutes = (int) ceil($this->ttl / 60);

        return (new MailMessage)
            ->subject(__('passwordless::passwordless.magic_code.subject', ['app' => $app]))
            ->greeting(__('passwordless::passwordless.magic_code.greeting'))
            ->line(__('passwordless::passwordless.magic_code.intro', ['app' => $app]))
            ->action(__('passwordless::passwordless.magic_code.action'), $this->url)
            ->line(__('passwordless::passwordless.magic_code.code_intro'))
            ->line('**'.$this->code.'**')
            ->line(__('passwordless::passwordless.magic_code.outro', ['minutes' => $minutes]));
    }
}
