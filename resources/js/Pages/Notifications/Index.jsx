import Pagination from '@/Components/UI/Pagination';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';

function formatDateTime(isoString) {
    return new Date(isoString).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function NotificationsIndex({ notifications }) {
    const unreadOnPage = notifications.data.filter((notification) => !notification.read_at).length;

    const markAllAsRead = () => {
        router.post(route('notifications.read-all'));
    };

    const openNotification = (id) => {
        router.post(route('notifications.read', id));
    };

    return (
        <AppLayout
            title="Notifications"
            actions={
                unreadOnPage > 0 && (
                    <button
                        type="button"
                        onClick={markAllAsRead}
                        className="inline-flex items-center rounded-lg border border-sterling-green/20 px-4 py-2 text-sm font-medium text-sterling-green hover:bg-sterling-green-50"
                    >
                        Mark all as read
                    </button>
                )
            }
        >
            <Head title="Notifications" />

            <div className="overflow-hidden rounded-xl border border-sterling-green/10 bg-white shadow-sm">
                {notifications.data.length === 0 ? (
                    <p className="px-6 py-12 text-center text-sm text-slate-500">You have no notifications yet.</p>
                ) : (
                    <ul className="divide-y divide-sterling-green/10">
                        {notifications.data.map((notification) => (
                            <li key={notification.id}>
                                <button
                                    type="button"
                                    onClick={() => openNotification(notification.id)}
                                    className={`block w-full px-6 py-4 text-start transition hover:bg-sterling-green-50 ${
                                        notification.read_at ? 'bg-white' : 'bg-sterling-green-50/40'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="text-sm font-semibold text-sterling-green">{notification.title}</p>
                                            <p className="mt-1 text-sm text-slate-600">{notification.message}</p>
                                            <p className="mt-2 text-xs text-slate-400">{formatDateTime(notification.created_at)}</p>
                                        </div>
                                        {!notification.read_at && (
                                            <span className="mt-1 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-sterling-gold" />
                                        )}
                                    </div>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {notifications.links?.length > 3 && (
                <div className="mt-6">
                    <Pagination links={notifications.links} />
                </div>
            )}

            <div className="mt-4">
                <Link href={route('dashboard')} className="text-sm text-sterling-green hover:underline">
                    Back to dashboard
                </Link>
            </div>
        </AppLayout>
    );
}
