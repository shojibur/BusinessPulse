export type PlatformCompanyRow = {
    id: number;
    name: string;
    timezone: string;
    is_demo: boolean;
    suspended: boolean;
    users_count: number;
    departments_count: number;
    owner_email: string | null;
    created_at: string | null;
};

export type PlatformCompanyDetail = {
    id: number;
    name: string;
    timezone: string;
    trading_days: number[];
    is_demo: boolean;
    suspended: boolean;
    suspended_at: string | null;
    created_at: string | null;
    users_count: number;
    departments_count: number;
    branches: string[];
};

export type PlatformOwner = {
    name: string;
    email: string;
    is_active: boolean;
};

export type PlatformActivityRow = {
    id: number;
    action: string;
    by: string | null;
    at: string | null;
};

export type AiCapabilityStatus = {
    configured: boolean;
    source: 'platform' | 'env' | 'none';
    provider: 'openai' | 'anthropic';
    model: string | null;
};

export type AiSettingsValues = {
    text_provider: 'openai' | 'anthropic';
    text_base_url: string | null;
    text_model: string | null;
    transcription_base_url: string | null;
    transcription_model: string | null;
    speech_base_url: string | null;
    speech_model: string | null;
    speech_voice: string | null;
};

export type PlatformStats = {
    companies: number;
    active: number;
    suspended: number;
    demo: number;
    users: number;
};

export type PlatformRecentCompany = {
    id: number;
    name: string;
    is_demo: boolean;
    suspended: boolean;
    users_count: number;
    created_at: string | null;
};

export type PlatformAiRow = AiCapabilityStatus & {
    key: string;
    label: string;
};

export type PlatformDashboardActivity = {
    id: number;
    action: string;
    by: string | null;
    company: string | null;
    at: string | null;
};

export type PlatformUserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    job_title: string | null;
    is_active: boolean;
    department_id: number | null;
    department: string | null;
    manager_id: number | null;
    manager: string | null;
};

export type PlatformDepartmentOption = { id: number; name: string };

export type PlatformManagerOption = {
    id: number;
    name: string;
    department_id: number | null;
};
