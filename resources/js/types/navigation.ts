export type NavItem = {
    label: string;
    href: string;
    /** Lucide icon name, resolved by the AppShell. */
    icon: string;
    active: boolean;
};

export type BreadcrumbItem = {
    label: string;
    href?: string;
};
