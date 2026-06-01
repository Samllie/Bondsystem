/**
 * Viewport-aware motion timings for welcome / dashboard transitions.
 */
export function getMotionTiming() {
    if (typeof window === 'undefined') {
        return { scrollMs: 750, holdMs: 3200 };
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reducedMotion) {
        return { scrollMs: 0, holdMs: 600 };
    }

    const height = window.innerHeight;
    const width = window.innerWidth;
    const isShortViewport = height < 520;
    const isCompact = height < 700 || width < 380;

    if (isShortViewport) {
        return { scrollMs: 550, holdMs: 2200 };
    }

    if (isCompact) {
        return { scrollMs: 650, holdMs: 2800 };
    }

    return { scrollMs: 750, holdMs: 3200 };
}
