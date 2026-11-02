<?php

namespace App\Notifications;

use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base notification (docs/07). Channels come from the user's preferences; database + Pusher
 * broadcast are delivered synchronously (instant bell/toast), mail goes through the database queue.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Catalogue key, e.g. "payment_recorded". */
    public const TYPE = 'generic';

    public function __construct(
        public string $title,
        public string $message,
        public string $level = 'info',
        public ?string $url = null,
        public array $meta = [],
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationService::class)->channelsFor($notifiable, static::TYPE);
    }

    public function viaConnections(): array
    {
        return ['database' => 'sync', 'broadcast' => 'sync', 'mail' => config('queue.default')];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => static::TYPE, 'title' => $this->title, 'message' => $this->message, 'level' => $this->level, 'url' => $this->url, 'meta' => $this->meta];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title.' · '.config('app.name'))->greeting('Hello '.$notifiable->name.',')->line($this->message);
        foreach ($this->mailLines() as $line) {
            $mail->line($line);
        }
        if ($this->url) {
            $mail->action('Open in Auxtech Ops', url($this->url));
        }

        return $mail->salutation('— Auxtech Ops');
    }

    /** @return list<string> extra lines for email (digests). */
    protected function mailLines(): array
    {
        return array_map('strval', $this->meta['lines'] ?? []);
    }
}
