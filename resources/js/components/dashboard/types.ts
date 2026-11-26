// Extra dashboard data (Overview): urgent issues, task status, the latest outcome and recent activity.

export type DashboardUrgentIssue = {
    id: number;
    title: string;
    severity: 'low' | 'medium' | 'high' | 'critical';
    status_label: string;
    detail: string | null;
    detected_at: string | null;
    url: string;
};

export type TaskStatusCounts = {
    pending: number;
    in_progress: number;
    blocked: number;
    completed: number;
    cancelled: number;
    total: number;
    percent: Record<string, number>;
};

export type DashboardOutcome = {
    id: number;
    result: string;
    resultLabel: string;
    phaseLabel: string;
    usedForVerification: boolean;
    outcomeMetricLabel: string;
    baselineLabel: string | null;
    followupLabel: string | null;
    changeLabels: string[];
    evaluatedAtLabel: string;
    resolutionConditionMet: boolean | null;
    notEvaluableText: string | null;
    url: string;
    issueTitle: string | null;
    issueUrl: string | null;
};

export type DashboardActivityEntry = {
    id: number;
    label: string;
    by: string;
    at: string;
    atLabel: string;
    ago: string | null;
    title: string | null;
    url: string | null;
};

export type WeekOption = {
    value: string;
    label: string;
    complete: boolean;
    latest: boolean;
};

export type DailyBranchLine = {
    code: string;
    reported: boolean;
    revenue: string | null;
    target: string | null;
    achievement: string | null;
};

export type DailySales = {
    date: string;
    default_date: string;
    min_date: string;
    max_date: string;
    status: 'complete' | 'partial' | 'non_trading' | 'not_due' | 'none';
    message: string | null;
    total:
        | (DailyBranchLine & { reported_count: number; expected_count: number })
        | null;
    branches: DailyBranchLine[];
    missing: string[];
};

export type DashboardExtras = {
    /** Sales of one chosen day by branch; null when there is no sales data. */
    daily: DailySales | null;
    /** Closed weeks to choose from, newest first. */
    weekOptions: WeekOption[];
    /** The past week being viewed (its Monday), or null for the latest. */
    viewingWeek: string | null;
    urgentIssues: DashboardUrgentIssue[];
    taskStatus: TaskStatusCounts;
    outcome: DashboardOutcome | null;
    activity: DashboardActivityEntry[];
};
