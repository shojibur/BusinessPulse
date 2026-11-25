export type Role = 'owner' | 'manager' | 'staff';

/** The signed-in user as shared by the server. Only these fields are ever sent. */
export type AuthUser = {
    id: number;
    name: string;
    email: string;
    role: Role;
    job_title: string | null;
    department: { id: number; name: string } | null;
};

export type Auth = {
    user: AuthUser | null;
    role: Role | null;
};

export type CompanyProfile = {
    name: string;
    timezone: string;
    currency: string;
    /** ISO weekdays, 1 (Monday) to 7 (Sunday), ascending. */
    trading_days: number[];
};
