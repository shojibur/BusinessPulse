export type SettingsTab = {
    label: string;
    href: string;
    active: boolean;
};

export type CompanySettings = {
    name: string;
    timezone: string;
    currency: string;
    is_demo: boolean;
    trading_days: number[];
    reporting_cutoff_time: string;
    reporting_cutoff_lag_days: number;
    /** Only sent to the Owner. */
    recording_retention_days: number | null;
};

export type StaffRow = {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    role: 'owner' | 'manager' | 'staff';
    department: string | null;
    manager: string | null;
    is_active: boolean;
};

export type DepartmentDetail = {
    id: number;
    name: string;
    expected_branches: string[];
    managers: { id: number; name: string }[];
    active_staff_count: number;
    staff: { id: number; name: string; job_title: string | null }[];
};
