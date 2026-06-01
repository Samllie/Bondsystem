import { getMotionTiming } from '@/lib/motionTiming';
import { useMemo } from 'react';

export default function useMotionTiming() {
    return useMemo(() => getMotionTiming(), []);
}
