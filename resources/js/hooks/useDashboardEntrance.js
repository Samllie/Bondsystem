import { consumeDashboardEntrance } from '@/lib/dashboardEntrance';
import { getMotionTiming } from '@/lib/motionTiming';
import { useEffect, useState } from 'react';

export default function useDashboardEntrance() {
    const [entering] = useState(() => consumeDashboardEntrance());

    useEffect(() => {
        if (!entering) {
            return undefined;
        }

        const { scrollMs } = getMotionTiming();

        document.documentElement.classList.add('overflow-hidden');

        const timer = setTimeout(() => {
            document.documentElement.classList.remove('overflow-hidden');
        }, scrollMs + 80);

        return () => {
            clearTimeout(timer);
            document.documentElement.classList.remove('overflow-hidden');
        };
    }, [entering]);

    return entering;
}
