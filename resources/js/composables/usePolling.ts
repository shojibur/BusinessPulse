import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Reloads the given page props every 3 seconds while `active()` is true and stops when it turns false.
 * A failed refresh sets `failed` so the page can offer a retry.
 */
export function usePolling(
    active: () => boolean,
    only: string[],
    intervalMs = 3000,
) {
    const failed = ref(false);
    let timer: ReturnType<typeof setInterval> | null = null;

    function refresh(): void {
        router.reload({
            only,
            onSuccess: () => {
                failed.value = false;
            },
            onError: () => {
                failed.value = true;
            },
        });
    }

    function stop(): void {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    function start(): void {
        if (timer === null) {
            timer = setInterval(refresh, intervalMs);
        }
    }

    watch(
        active,
        (isActive) => {
            if (isActive) {
                start();
            } else {
                stop();
            }
        },
        { immediate: true },
    );

    onBeforeUnmount(stop);

    return { failed, retry: refresh };
}
