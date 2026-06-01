import { router } from '@inertiajs/react';

function restoreInputFocus(inputRef) {
    const input = inputRef?.current;

    if (! input) {
        return;
    }

    const position = input.selectionStart ?? input.value.length;
    input.focus({ preventScroll: true });
    input.setSelectionRange(position, position);
}

export function visitTable(url, params, only, { inputRef, onFinish } = {}) {
    router.get(url, params, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only,
        onFinish: () => {
            onFinish?.();

            if (! inputRef) {
                return;
            }

            requestAnimationFrame(() => {
                requestAnimationFrame(() => restoreInputFocus(inputRef));
            });
        },
    });
}
