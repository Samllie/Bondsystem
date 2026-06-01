import { getMotionTiming } from '@/lib/motionTiming';
import { useEffect, useState } from 'react';

/**
 * Triggers login intro classes after mount so animations run on both
 * full page loads and Inertia client visits.
 */
export default function useLoginIntro() {
    const [playIntro, setPlayIntro] = useState(false);

    useEffect(() => {
        const { scrollMs } = getMotionTiming();

        if (scrollMs === 0) {
            return undefined;
        }

        const frame = requestAnimationFrame(() => {
            setPlayIntro(true);
        });

        return () => cancelAnimationFrame(frame);
    }, []);

    return playIntro;
}
