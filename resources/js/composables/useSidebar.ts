import { ref } from 'vue';

const STORAGE_KEY = 'bp.sidebar.collapsed';

function initialState(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        if (stored === '1' || stored === '0') {
            return stored === '1';
        }
    } catch {
        // Storage can be blocked; fall back to the default below.
    }

    // First visit: a compact rail on narrower laptops, the full sidebar on wide screens.
    return window.innerWidth < 1281;
}

// One shared value for every page and both shells, so the choice survives navigation and reloads.
const collapsed = ref(initialState());

function toggle(): void {
    collapsed.value = !collapsed.value;

    try {
        window.localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0');
    } catch {
        // Not persisted when storage is unavailable; the toggle still works for this visit.
    }
}

/** Ctrl or Cmd + B toggles the sidebar, except while typing in a field. */
function onKeydown(event: KeyboardEvent): void {
    if (
        !(event.metaKey || event.ctrlKey) ||
        event.altKey ||
        event.shiftKey ||
        event.key.toLowerCase() !== 'b'
    ) {
        return;
    }

    const target = event.target as HTMLElement | null;

    if (
        target &&
        (target.isContentEditable ||
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
    ) {
        return;
    }

    event.preventDefault();
    toggle();
}

export function useSidebar() {
    return { collapsed, toggle, onKeydown };
}
