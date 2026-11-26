import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import SettingsLayout from '@/layouts/settings/SettingsLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'BusinessPulse';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        if (name.startsWith('auth/') || name === 'platform/Login') {
            return AuthLayout;
        }

        if (name.startsWith('platform/')) {
            return PlatformLayout;
        }

        return name.startsWith('settings/')
            ? [AppLayout, SettingsLayout]
            : AppLayout;
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        // Page progress indicator during Inertia visits (teal, matching --primary).
        color: '#0F9488',
    },
});
