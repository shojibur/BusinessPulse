import type { Pagination } from './imports';

export type IssueStatusValue =
    'open' | 'acknowledged' | 'action_in_progress' | 'monitoring' | 'resolved';

export type IssueRow = {
    id: number;
    title: string;
    department: string;
    severity: 'high' | 'medium' | 'low';
    status: IssueStatusValue;
    status_label: string;
    first_detected_at: string | null;
    last_detected_at: string | null;
    detection_count: number;
    key_figure: { label: string; value: string | null };
};

export type MissingBranchDay = {
    branch_code: string;
    date: string;
    kind: 'inside_period' | 'after_latest';
};

export type EvaluationView = {
    id: number;
    metric_name:
        'weekly_change_pct' | 'target_achievement_pct' | 'coverage_pct';
    result: 'breached' | 'not_breached' | 'insufficient_data';
    severity: string | null;
    insufficient_reason: string | null;
    insufficient_reason_text: string | null;
    period_start: string;
    period_end: string;
    comparison_start: string | null;
    comparison_end: string | null;
    current_value: string | null;
    comparison_value: string | null;
    difference_value: string | null;
    difference_pct: string | null;
    threshold: string | null;
    params: Record<string, string>;
    as_of_date: string | null;
    last_due_date: string;
    evaluated_at: string | null;
    coverage: {
        expected_branches: string[];
        expected_due_branch_days: number | null;
        present_due_branch_days: number | null;
        missing: MissingBranchDay[];
    };
    import_ids: number[];
};

export type IssueDetail = {
    id: number;
    title: string;
    severity: 'high' | 'medium' | 'low';
    status: IssueStatusValue;
    status_label: string;
    department: string;
    rule_key:
        | 'weekly_revenue_decline'
        | 'sales_target_shortfall'
        | 'missing_sales_data';
    rule_name: string;
    first_detected_at: string | null;
    last_detected_at: string | null;
    detection_count: number;
    action_started_at: string | null;
    monitoring_started_at: string | null;
    resolved_at: string | null;
    resolution_type: 'manual' | 'system_verified' | null;
    resolution_note: string | null;
    recurrence_of: { id: number; title: string } | null;
};

export type StatusHistoryEntry = {
    from: string | null;
    to: string;
    at: string;
    reason: string | null;
    trigger: string | null;
    by_user_id: number | null;
};

export type NotificationItem = {
    id: string;
    title: string;
    severity: string | null;
    summary: string | null;
    department: string | null;
    url: string | null;
    read: boolean;
    created_at: string | null;
};

export type NotificationsShared = {
    unread_count: number;
    items: NotificationItem[];
};

export type { Pagination };
