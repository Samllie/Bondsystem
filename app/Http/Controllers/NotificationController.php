<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications->through(fn (DatabaseNotification $notification) => $this->formatNotification($notification)),
        ]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('dashboard');

        return redirect($url);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNotification(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->data['type'] ?? '',
            'title' => $notification->data['title'] ?? '',
            'message' => $notification->data['message'] ?? '',
            'url' => $notification->data['url'] ?? route('dashboard'),
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}
