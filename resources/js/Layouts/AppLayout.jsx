import { usePageTransition } from '@/Contexts/PageTransitionContext';
import Sidebar from '@/Components/Layout/Sidebar';
import TopNav from '@/Components/Layout/TopNav';
import useDashboardEntrance from '@/hooks/useDashboardEntrance';
import { getMotionTiming } from '@/lib/motionTiming';
import { useEffect, useMemo, useState } from 'react';

function readDesktopSidebarPreference() {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(min-width: 1024px)').matches;
}

export default function AppLayout({ title, children, actions }) {
    const [sidebarOpen, setSidebarOpen] = useState(readDesktopSidebarPreference);
    const dashboardEntering = useDashboardEntrance();
    const { isLoggingOut } = usePageTransition() ?? {};

    useEffect(() => {
        const mediaQuery = window.matchMedia('(min-width: 1024px)');

        const handleChange = (event) => {
            setSidebarOpen(event.matches);
        };

        mediaQuery.addEventListener('change', handleChange);

        return () => mediaQuery.removeEventListener('change', handleChange);
    }, []);

    const motionStyle = useMemo(() => {
        const { scrollMs } = getMotionTiming();

        return { '--motion-scroll-duration': `${scrollMs}ms` };
    }, [dashboardEntering, isLoggingOut]);

    const shellClass = [
        'app-shell dashboard-shell-bg relative z-[70] min-h-[100dvh]',
        dashboardEntering ? 'dashboard-enter' : '',
        isLoggingOut ? 'dashboard-exit' : '',
    ]
        .filter(Boolean)
        .join(' ');

    const mainOffsetClass = sidebarOpen ? 'lg:pl-64' : 'lg:pl-0';

    return (
        <div className={shellClass} style={motionStyle}>
            <Sidebar
                open={sidebarOpen}
                onClose={() => setSidebarOpen(false)}
            />

            <div className={`transition-[padding] duration-300 ease-in-out print:pl-0 ${mainOffsetClass}`}>
                <TopNav
                    sidebarOpen={sidebarOpen}
                    onMenuClick={() => setSidebarOpen((open) => !open)}
                />
                <main className="p-4 sm:p-6 lg:p-8 print:p-0">
                    {(title || actions) && (
                        <div className="no-print mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            {title && (
                                <div>
                                    <h1 className="font-serif text-2xl font-bold text-sterling-green">{title}</h1>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Sterling Insurance Company, Inc.
                                    </p>
                                </div>
                            )}
                            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
