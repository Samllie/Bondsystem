import Dropdown from '@/Components/Dropdown';
import { Link, router, usePage } from '@inertiajs/react';

function formatRelativeTime(isoString) {
    const date = new Date(isoString);
    const diffMs = Date.now() - date.getTime();
    const diffMinutes = Math.floor(diffMs / 60000);

    if (diffMinutes < 1) {
        return 'Just now';
    }

    if (diffMinutes < 60) {
        return `${diffMinutes}m ago`;
    }

    const diffHours = Math.floor(diffMinutes / 60);

    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }

    const diffDays = Math.floor(diffHours / 24);

    if (diffDays < 7) {
        return `${diffDays}d ago`;
    }

    return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

export default function NotificationBell() {
    const { notifications } = usePage().props;
    const unreadCount = notifications?.unread_count ?? 0;
    const recent = notifications?.recent ?? [];

    const openNotification = (id) => {
        router.post(route('notifications.read', id));
    };

    const markAllAsRead = () => {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true });
    };

    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button
                    type="button"
                    className="relative inline-flex rounded-lg p-2 text-sterling-green hover:bg-sterling-green-50"
                    aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
                >
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                        />
                    </svg>
                    {unreadCount > 0 && (
                        <span className="absolute -right-0.5 -top-0.5 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    )}
                </button>
            </Dropdown.Trigger>
            <Dropdown.Content align="right" width="80" contentClasses="py-0 bg-white">
                <div className="flex items-center justify-between border-b border-sterling-green/10 px-4 py-3">
                    <p className="text-sm font-semibold text-sterling-green">Notifications</p>
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            onClick={markAllAsRead}
                            className="text-xs font-medium text-sterling-green/70 hover:text-sterling-green"
                        >
                            Mark all read
                        </button>
                    )}
                </div>

                {recent.length === 0 ? (
                    <p className="px-4 py-6 text-center text-sm text-slate-500">No new notifications.</p>
                ) : (
                    <div className="max-h-80 overflow-y-auto">
                        {recent.map((notification) => (
                            <button
                                key={notification.id}
                                type="button"
                                onClick={() => openNotification(notification.id)}
                                className="block w-full border-b border-sterling-green/5 px-4 py-3 text-start transition hover:bg-sterling-green-50 last:border-b-0"
                            >
                                <p className="text-sm font-medium text-sterling-green">{notification.title}</p>
                                <p className="mt-0.5 line-clamp-2 text-xs text-slate-600">{notification.message}</p>
                                <p className="mt-1 text-[11px] text-slate-400">
                                    {formatRelativeTime(notification.created_at)}
                                </p>
                            </button>
                        ))}
                    </div>
                )}

                <div className="border-t border-sterling-green/10 px-4 py-2">
                    <Link
                        href={route('notifications.index')}
                        className="block text-center text-xs font-medium text-sterling-green hover:underline"
                    >
                        View all notifications
                    </Link>
                </div>
            </Dropdown.Content>
        </Dropdown>
    );
}
