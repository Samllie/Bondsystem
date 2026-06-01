import FluidBackground from '@/Components/Auth/FluidBackground';
import { getMotionTiming } from '@/lib/motionTiming';
import { createContext, useCallback, useContext, useMemo, useState } from 'react';

const PageTransitionContext = createContext(null);

async function submitLogout() {
    await window.axios.post(route('logout'));
}

export function PageTransitionProvider({ children }) {
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    const logout = useCallback(() => {
        const { scrollMs } = getMotionTiming();

        const finishLogout = async () => {
            try {
                await submitLogout();
            } catch {
                // Still send the user to login if the request fails.
            }

            document.documentElement.classList.remove('overflow-hidden');
            window.location.assign(route('login'));
        };

        if (scrollMs === 0) {
            finishLogout();

            return;
        }

        setIsLoggingOut(true);
        document.documentElement.classList.add('overflow-hidden');

        window.setTimeout(() => {
            finishLogout();
        }, scrollMs);
    }, []);

    const value = useMemo(
        () => ({
            logout,
            isLoggingOut,
        }),
        [logout, isLoggingOut],
    );

    return (
        <PageTransitionContext.Provider value={value}>
            {isLoggingOut && (
                <div
                    className="auth-fluid-bg pointer-events-none fixed inset-0 z-[60] min-h-[100dvh]"
                    aria-hidden="true"
                >
                    <FluidBackground />
                </div>
            )}
            {children}
        </PageTransitionContext.Provider>
    );
}

export function usePageTransition() {
    return useContext(PageTransitionContext);
}

export function useLogout() {
    const context = usePageTransition();

    return useCallback(() => {
        if (context?.logout) {
            context.logout();

            return;
        }

        window.location.assign(route('login'));
    }, [context]);
}
