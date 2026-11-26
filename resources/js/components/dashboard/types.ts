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
    title: string | null;
    url: string | null;
};

export type DashboardExtras = {
    urgentIssues: DashboardUrgentIssue[];
    taskStatus: TaskStatusCounts;
    outcome: DashboardOutcome | null;
    activity: DashboardActivityEntry[];
};
