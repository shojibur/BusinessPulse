import { toast } from 'vue-sonner';
import type { FlashMessage } from '@/types';

type ToastType = FlashMessage['type'];

/** Typed client-side toasts. Server messages arrive through the `flash` shared prop (see ToastHost). */
export function useToast() {
    const show = (type: ToastType, message: string): void => {
        toast[type](message);
    };

    return {
        show,
        success: (message: string) => show('success', message),
        error: (message: string) => show('error', message),
        info: (message: string) => show('info', message),
        warning: (message: string) => show('warning', message),
    };
}
