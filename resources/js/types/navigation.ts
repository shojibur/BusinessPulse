export type NavItem = {
    label: string;
    href: string;
    /** Lucide icon name, resolved by the AppShell. */
    icon: string;
    active: boolean;
    /** Section heading shown above the first item of a group (for example Company). */
    group?: string | null;
    /** Count shown next to the label (open issues, open tasks). */
    badge?: number | null;
};

export type BreadcrumbItem = {
    label: string;
    href?: string;
};
