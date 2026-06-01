import { getMotionTiming } from '@/lib/motionTiming';

const DASHBOARD_ENTRANCE_KEY = 'bondsystem.dashboardEntrance';

/** @deprecated Use getMotionTiming().scrollMs for viewport-aware duration */
export const DASHBOARD_SCROLL_MS = 750;

export function getDashboardScrollMs() {
    return getMotionTiming().scrollMs;
}

export function markDashboardEntrance() {
    sessionStorage.setItem(DASHBOARD_ENTRANCE_KEY, '1');
}

export function consumeDashboardEntrance() {
    if (sessionStorage.getItem(DASHBOARD_ENTRANCE_KEY) !== '1') {
        return false;
    }

    sessionStorage.removeItem(DASHBOARD_ENTRANCE_KEY);

    return true;
}
