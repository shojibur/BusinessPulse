import type { Pagination } from './imports';

export type OutcomeResultCode =
    'improved' | 'unchanged' | 'worsened' | 'not_evaluable';
export type PhaseCode = 'pre_action' | 'during_action' | 'post_action';

export type OutcomeSummary = {
    id: number;
    result: OutcomeResultCode;
    resultLabel: string;
    phase: PhaseCode;
    phaseLabel: string;
    usedForVerification: boolean;
    outcomeMetricLabel: string;
    baselineLabel: string | null;
    followupLabel: string | null;
    changeLabels: string[];
    evaluatedAtLabel: string;
    resolutionConditionMet: boolean;
    notEvaluableText: string | null;
    manualNote: string | null;
    url: string;
};

export type OutcomeRow = OutcomeSummary & {
    issueId: number;
    issueTitle: string;
    ruleName: string;
    departmentName: string;
    issueStatus: string;
    issueStatusLabel: string;
};

export type OutcomeLink = {
    id: number;
    resultLabel: string;
    phaseLabel: string;
    evaluatedAtLabel: string;
    url: string;
};

export type OutcomePanelData = {
    heading: string;
    standingSentence: string;
    latest: OutcomeSummary;
    earlier: OutcomeLink[];
};

export type FeedbackPanelData = {
    mine: boolean | null;
    counts: { useful: number; notUseful: number };
    submitUrl: string;
};

export type TimelineEntry = {
    key: string;
    kind: 'activity' | 'record';
    at: string;
    atLabel: string;
    label: string;
    userName: string;
    phaseLabel: string | null;
    subject: {
        type: string;
        id: number;
        title: string | null;
        url: string | null;
    };
};

export type TimelineStage = {
    stage: string;
    stageLabel: string;
    empty: boolean;
    entries: TimelineEntry[];
};

export type TracePanelData = { stages: TimelineStage[]; truncated: boolean };

export type OutcomeDetail = {
    id: number;
    heading: string;
    standingSentence: string;
    issue: {
        id: number;
        title: string;
        status: string;
        statusLabel: string;
        url: string;
        severity: 'high' | 'medium' | 'low';
        detectedLabel: string;
    };
    ruleName: string;
    departmentName: string;
    outcomeMetric: 'weekly_revenue' | 'target_achievement' | 'coverage';
    outcomeMetricLabel: string;
    baselinePeriod: { start: string; end: string };
    followupPeriod: { start: string; end: string };
    referencePeriod: { start: string; end: string } | null;
    phase: {
        code: PhaseCode;
        label: string;
        usedForVerification: boolean;
        actionDate: string | null;
        monitoringDate: string | null;
    };
    values: {
        baseline: string | null;
        followup: string | null;
        reference: string | null;
        absoluteChange: string | null;
        relativeChangePct: string | null;
        pointChange: string | null;
        recoveredSharePct: string | null;
        unit: 'MYR' | '%';
        baselineLabel: string | null;
        followupLabel: string | null;
        referenceLabel: string | null;
        changeLabels: string[];
    };
    result: OutcomeResultCode;
    resultLabel: string;
    notEvaluableText: string | null;
    comparable: boolean;
    condition: { met: boolean; text: string; limitLabel: string | null };
    weeklyChangeEvidence: { baselinePct: string; followupPct: string } | null;
    evaluatedAtLabel: string;
    checks: {
        name: string;
        label: string;
        state: 'passed' | 'failed' | 'skipped' | 'not_applicable';
    }[];
    evidence: {
        thresholdUsed: string | null;
        coverage: {
            status: string;
            completeness: string | null;
            expectedBranchDays: number;
            presentBranchDays: number;
        } | null;
        band: string;
    };
    manualNote: {
        text: string;
        byName: string | null;
        atLabel: string | null;
    } | null;
};

export type { Pagination };
