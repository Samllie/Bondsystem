<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public string $url,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
    ) {}

    public static function make(
        string $type,
        string $title,
        string $message,
        string $url,
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): self {
        return new self($type, $title, $message, $url, $subjectType, $subjectId);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
        ];
    }
}
