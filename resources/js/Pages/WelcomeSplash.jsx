import FluidBackground from '@/Components/Auth/FluidBackground';
import useMotionTiming from '@/hooks/useMotionTiming';
import { markDashboardEntrance } from '@/lib/dashboardEntrance';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function WelcomeSplash({ userName, redirectTo }) {
    const [exiting, setExiting] = useState(false);
    const { scrollMs, holdMs } = useMotionTiming();

    useEffect(() => {
        const exitTimer = setTimeout(() => setExiting(true), holdMs);
        const navigateTimer = setTimeout(() => {
            markDashboardEntrance();
            router.visit(redirectTo, { replace: true });
        }, holdMs + scrollMs);

        return () => {
            clearTimeout(exitTimer);
            clearTimeout(navigateTimer);
        };
    }, [redirectTo, holdMs, scrollMs]);

    return (
        <>
            <Head title="Welcome" />

            <div
                className={`welcome-splash auth-fluid-bg fixed inset-0 z-50 overflow-hidden ${exiting ? 'welcome-splash--exit' : ''}`}
                style={{
                    '--motion-scroll-duration': scrollMs ? `${scrollMs}ms` : '0ms',
                }}
            >
                <FluidBackground />

                <div className="welcome-splash__stage relative z-10 flex min-h-[100dvh] flex-col items-center justify-center px-4 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-[max(1.5rem,env(safe-area-inset-top))] sm:px-6">
                    <div className="welcome-splash__logo-wrap">
                        <img
                            src="/images/sterling-logo-mark.png"
                            alt="Sterling Insurance Company, Inc."
                            className="welcome-splash__logo mx-auto object-contain drop-shadow-2xl"
                        />
                    </div>

                    <p className="welcome-splash__greeting mt-6 max-w-[min(100%,20rem)] text-center font-serif font-bold text-white sm:mt-8">
                        <span className="block text-pretty">Welcome,</span>
                        <span className="welcome-splash__name mt-1 block text-pretty">{userName}</span>
                    </p>
                </div>
            </div>
        </>
    );
}
