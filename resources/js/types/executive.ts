import type { Pagination } from './imports';

export type InsufficientFigure = {
    available: false;
    reason:
        | 'incomplete_period'
        | 'period_absent'
        | 'comparison_week_incomplete'
        | 'zero_denominator';
    message: string;
    period_start: string;
    period_end: string;
};

export type AvailableFigure = {
    available: true;
    value: string;
    display: string;
    period_start: string;
    period_end: string;
    target?: string;
    comparison?: string;
    comparison_period_start?: string;
    comparison_period_end?: string;
};

export type DashboardFigure = AvailableFigure | InsufficientFigure;

export type EvaluationStatus =
    'never' | 'current' | 'partial' | 'failed' | 'stale';

export type SeriesPoint = {
    week_start: string;
    week_end: string;
    label: string;
    revenue: string | null;
    target: string | null;
    is_complete: boolean;
};

export type LatestBrief = {
    id: number;
    headline: string | null;
    source: 'ai' | 'fallback' | null;
    coverage_status: CoverageStatusValue | null;
    generated_at: string | null;
};

export type CoverageStatusValue = 'sufficient' | 'partial' | 'stale';

export type DashboardData = {
    departmentName: string;
    revenue: DashboardFigure;
    change: DashboardFigure;
    targetAchievement: DashboardFigure;
    freshness: {
        state: string;
        behind: boolean;
        message: string | null;
        latest_sales_date: string | null;
        overdue_branch_days: number | null;
    };
    evaluation: {
        status: EvaluationStatus;
        last_evaluated_at: string | null;
        failed_rules: number;
    };
    coverageNotice: string | null;
    openIssueCount: number;
    highPriorityIssueCount: number;
    revenueVsTarget: SeriesPoint[];
    latestBrief: LatestBrief | null;
    taskCounts: { open: number; overdue: number };
};

export type BriefRow = {
    id: number;
    status: BriefStatusValue;
    source: 'ai' | 'fallback' | null;
    coverage_status: CoverageStatusValue | null;
    headline: string | null;
    created_at: string;
    generated_at: string | null;
};

export type BriefStatusValue = 'queued' | 'generating' | 'completed' | 'failed';

export type BriefItemView = {
    position: number;
    issue_id: number | null;
    issue_url: string | null;
    title: string;
    explanation: string;
    severity: 'high' | 'medium' | 'low';
    period_label: string;
    figures: string | null;
    note: string | null;
};

export type BriefView = {
    id: number;
    status: BriefStatusValue;
    source: 'ai' | 'fallback' | null;
    coverage_status: CoverageStatusValue | null;
    headline: string | null;
    limitation: string | null;
    period_start: string | null;
    period_end: string | null;
    as_of_date: string | null;
    created_at: string;
    generated_at: string | null;
    notice: string | null;
    /** Queued or writing for over 45 seconds: the background worker is probably not running. */
    stalled: boolean;
    items: BriefItemView[];
};

export type AiAvailability = { text: boolean };

export type AudioView = {
    status: 'none' | 'queued' | 'generating' | 'ready' | 'failed';
    configured: boolean;
    url: string | null;
    can_request: boolean;
};

export type { Pagination };
