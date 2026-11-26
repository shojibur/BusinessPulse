import type { Pagination } from './imports';

export type TaskStatusValue =
    'pending' | 'in_progress' | 'blocked' | 'completed' | 'cancelled';

export type TaskRowData = {
    id: number;
    title: string;
    assignee: { id: number; name: string };
    department: { id: number; name: string };
    dueDate: string;
    status: TaskStatusValue;
    statusLabel: string;
    isOverdue: boolean;
    daysOverdue: number | null;
    issue: { id: number; title: string } | null;
    allowedTransitions: TaskStatusValue[];
    url: string;
    /** One short line of the description (management list only). */
    description?: string;
};

export type TaskSummary = { open: number; overdue: number };

export type TaskStats = {
    total: number;
    inProgress: number;
    completed: number;
    overdue: number;
    pendingDrafts: number;
};

export type TaskBoardColumn = {
    status: TaskStatusValue;
    label: string;
    count: number;
    items: TaskRowData[];
};

export type TaskBoardData = {
    columns: TaskBoardColumn[];
    shown: number;
    total: number;
    cancelled: number;
};

export type TaskDetailData = {
    id: number;
    title: string;
    description: string;
    status: TaskStatusValue;
    statusLabel: string;
    dueDate: string;
    isOverdue: boolean;
    daysOverdue: number | null;
    assignee: { id: number; name: string };
    assignedBy: { id: number; name: string };
    department: { id: number; name: string };
    createdAt: string | null;
    updatedAt: string | null;
    completedAt: string | null;
};

export type TaskIssueContext = {
    id: number;
    title: string;
    status: string;
    resolvedNotice: string | null;
    severity: 'high' | 'medium' | 'low' | null;
    url: string | null;
    evidence: { metricName: string; baseline: string; latest: string } | null;
};

export type TaskCommentData = {
    id: number;
    body: string;
    author: { name: string; role: string };
    createdAt: string | null;
};

export type IssueTasks = {
    counts: Record<TaskStatusValue, number>;
    items: {
        id: number;
        title: string;
        assigneeName: string;
        dueDate: string;
        status: TaskStatusValue;
        statusLabel: string;
        isOverdue: boolean;
        url: string;
    }[];
    createUrl: string | null;
};

export type DraftReviewItem = {
    id: number;
    status: 'pending' | 'approved' | 'rejected' | 'superseded';
    title: string;
    description: string;
    assigneeId: number | null;
    assigneeName: string | null;
    assigneeResolution: 'resolved' | 'ambiguous' | 'unmatched' | 'none';
    suggestedAssigneeName: string | null;
    dueDate: string | null;
    deadlineResolution: 'resolved' | 'ambiguous' | 'none';
    suggestedDueDateText: string | null;
    issueId: number | null;
    transcriptExcerpt: string;
    rejectionReason: string | null;
    taskId: number | null;
    taskUrl: string | null;
    blockers: string[];
    stale: boolean;
    extractionRun: number;
};

export type DraftReview = {
    pending: DraftReviewItem[];
    history: DraftReviewItem[];
    supersededCount: number;
    assigneeOptions: { id: number; name: string }[];
    issueOptions: { id: number; title: string }[];
    defaultIssueId: number | null;
    minDueDate: string;
    emptyReason: 'approved' | 'rejected' | 'never_extracted' | null;
    transcriptChangedSinceExtraction: boolean;
    canReExtract: boolean;
};

export type { Pagination };
